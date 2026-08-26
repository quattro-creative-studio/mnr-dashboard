# Journal de migration — Laravel 5.7 → 13

> **À quoi sert ce fichier.** Il enregistre l'état de la migration et **les décisions
> prises**, avec leur raison. La roadmap dit où l'on va ; ce journal dit où l'on en est
> et pourquoi les choses ont été faites ainsi.
>
> Il est mis à jour **à la fin de chaque étape**. Si une session de travail est perdue,
> ce fichier plus `git log` suffisent à reprendre.

**Dernière mise à jour :** 21 août 2026 · branche `upgrade/laravel-5.7-to-13` · **ÉCHELLE TERMINÉE — 9/9**

---

## 1. État actuel

| | Aujourd'hui | Cible |
|---|---|---|
| Laravel | **13.26.1** ✅ | 13.x |
| PHP (application) | **8.5.8** ✅ | 8.5 |
| PHP (résolution composer) | `config.platform.php` = **8.5.0** ✅ | 8.3+ |
| Base de données | MySQL 8.0.33 (dev) · 5.7.31 (prod) — **schéma vérifié** | 8.4 LTS |
| Serveur | actuel (Hetzner) · **site local en PHP 8.5** | Ubuntu 26.04 + Forge |
| Suite de tests | **88 tests / 209 assertions — verte** | — |
| Production | **toujours en 5.7.29 — rien n'a été déployé** | — |

### Reprendre le travail

```bash
git checkout upgrade/laravel-5.7-to-13
composer test                 # passe par bin/test, qui épingle le binaire PHP
composer audit               # doit rester propre
```

`bin/test` épingle la version PHP du hop en cours (`MNR_PHP`, défaut **`php85`**) parce que
le PHP par défaut de la machine est 8.5, en avance sur le hop courant.

---

## 2. Progression

### Phase 0 — préparation (terminée)

| # | Étape | État |
|---|---|---|
| 1 | Isoler la base de test | ✅ |
| 2 | Suite de caractérisation (5 tripwires + Storage) | ✅ |
| 3 | Sortir `env()` des Mailables | ✅ |
| 4 | Neutraliser les bloqueurs Laravel 6 | ✅ |
| 5 | Routes cachables + doublons supprimés | ✅ |
| 6 | Transport mail neutre (SMTP) | ✅ |
| 7 | Séparation bulk / transactionnel | ⏸️ voir §5 |
| 8 | Audit du schéma vs MySQL 8.4 | ✅ dev **et prod** |
| 9 | Découpler les non-contrôleurs de `Controller` | ✅ |

### Échelle des versions

| Hop | Version | PHP | État |
|---|---|---|---|
| 1 | 5.7.29 → **5.8.38** | 7.4 | ✅ |
| 2 | 5.8 → **6.20.45** | 7.4 | ✅ |
| 3 | 6.0 → **7.30.7** | 7.4 | ✅ |
| 4 | 7.0 → **8.83.29** | 7.4 | ✅ |
| 5 | 8.0 → **9.52.22** | **8.0.30** | ✅ |
| 6 | 9.0 → **10.50.3** | **8.1.34** | ✅ |
| 7 | 10.0 → **11.56.0** | **8.2.32** | ✅ |
| 8 | 11.0 → **12.67.0** | 8.2.32 | ✅ |
| 9 | 12.0 → **13.26.1** | **8.5.8** | ✅ |

---

## 3. Journal des décisions

### D-01 · Blocage des avis de sécurité de Composer désactivé
**Hop 1.** `config.policy.advisories.block = false` dans `composer.json`.

Composer 2.10 refuse d'installer un paquet sous avis de sécurité publié. Toutes les
versions intermédiaires de l'échelle sont en fin de vie et en portent — **la montée est
impossible avec ce blocage actif**.

Ce n'est pas une tolérance au risque. Mesuré, pas supposé : la production en **5.7.29 est
affectée par 9 avis** `laravel/framework`, la **5.8.38 par 8**. Chaque hop *réduit*
l'exposition. Le critique CVE-2019-9081 (désérialisation) est **déjà en production
aujourd'hui**.

Ce qui rend la manœuvre sûre : **aucune version intermédiaire n'est déployée**, la
production reste en 5.7 pendant toute la montée.

> ✅ **RÉSOLU au hop 9.** `block` est remis à `true` et **`composer audit` ne signale plus
> rien**. `AdvisoryPolicyTest` a fait exactement son travail : il est passé au rouge à
> l'arrivée en Laravel 13 et a refusé de laisser passer le blocage désactivé.

### D-02 · `config.platform.php` épinglé
**Hop 1.** Composer tourne sous PHP 8.5 sur cette machine et ne peut pas résoudre un
arbre exigeant `php ^7.1.3`. La plateforme cible est donc déclarée dans `composer.json`.
**À monter à chaque bump PHP** : 7.4.33 → 8.0 → 8.1 → 8.2 → 8.3+.
`AdvisoryPolicyTest` vérifie qu'elle ne diverge pas du PHP réellement exécuté.

### D-03 · `minimum-stability` : `dev` → `stable`
**Hop 1.** Le projet installait silencieusement `5.8.x-dev`, une tête de branche mouvante,
au lieu de la v5.8.38 taguée. Le plan situait ce point au hop 10 ; il mord dès le premier.

### D-04 · Transport mail : SMTP neutre
**Phase 0.** `.env.example` prescrivait `MAIL_DRIVER=sparkpost` — driver **supprimé en
Laravel 6.0**, sans remplaçant first-party. La production est très probablement encore
dessus.

Le remplacement n'est pas le driver d'un autre fournisseur mais **SMTP**, supporté à
l'identique par toutes les versions de Laravel. Conséquence : **le fournisseur de mail
n'est plus qu'une affaire de `.env`** — changeable, réversible, sans code ni paquet.

### D-05 · Fournisseur de mail — décision en attente
**Recommandation : Scaleway TEM.** Voir §5.

### D-06 · Longueur minimale des mots de passe : 6 → 8
**Hop 1.** Laravel 5.8 monte le défaut du trait `ResetsPasswords` de 6 à 8, alors que
l'application imposait `min:6` explicitement ailleurs. Sans intervention, on pouvait
**s'inscrire avec un mot de passe que la réinitialisation refusait ensuite**.

Aligné vers le haut (`min:8`) dans `ProfileUpdateRequest`, `TeacherRegisterRequest`,
`AdminUserCreateRequest`, `Auth\RegisterController`. Aucun enseignant n'est bloqué : la
validation s'applique à la *définition* d'un mot de passe, jamais à sa vérification.

### D-07 · `spatie/laravel-backup` supprimé
**Hop 1.** Sauvegardes assurées par l'hébergeur — **snapshots Hetzner, rétention 7 jours**.
La planification était déjà commentée dans `Console/Kernel` avec cette raison.

Effet de bord majeur : `league/flysystem-sftp` n'existait que pour le disque `backup`.
Ce paquet est **abandonné** et son remplaçant porte un **nom différent**
(`league/flysystem-sftp-v3`), ce qui en faisait l'élément le plus lourd du hop 9
(Flysystem 1 → 3). **Ce travail n'existe plus.**

Cinq paquets retirés : `spatie/laravel-backup`, `spatie/db-dumper`,
`spatie/temporary-directory`, `league/flysystem-sftp`, `phpseclib/phpseclib`.
Supprimés aussi : `config/backup.php`, le disque `backup`, les variables `BACKUP_*`.

### D-08 · `guzzlehttp/guzzle` retiré des dépendances directes
**Hop 1.** Aucun usage dans `app/`. Ce n'est pas « retirer puis remettre » : **Laravel
l'exige en dur à partir de la 11** (`^7.8.2`, puis `^7.8.2||^8.0` en 13), donc le
framework le réinstallera lui-même au bon hop et à la bonne version. Il reste d'ailleurs
présent, en dépendance transitive de Bugsnag.

### D-09 · Redis : `predis` → `phpredis`
**Hop 1.** Redis est prévu pour le cache et les sessions sur le nouveau serveur. La
question était donc *quel client*, pas *si*.

**phpredis** : extension C plutôt que PHP pur, défaut de Laravel depuis la 6.0, et ce que
Forge provisionne. Présente et chargée dans **toutes** les versions PHP de Herd
(vérifié : 7.4, 8.0, 8.3, 8.5 — v5.3.7).

`config/database.php` avait `'client' => 'predis'` en dur, ce qui aurait **silencieusement
écrasé le nouveau défaut** au hop 6.0. Désormais `env('REDIS_CLIENT', 'phpredis')`, avec
`ext-redis` déclaré en requirement pour échouer à l'installation plutôt qu'à l'exécution.

### D-10 · Base de données : `migrate` + import des données seules
**Phase 0.** Correction d'une erreur antérieure de ce plan : Oracle ne supporte **aucun**
chemin direct 5.7 → 8.4, ni en place ni logique.

Sans objet ici, parce que **Laravel possède le schéma via 66 migrations** et que la cible
est une machine neuve : `artisan migrate` sur un 8.4 vide → vider les tables → importer
**les données seules** (`mysqldump --no-create-info --complete-insert
--skip-column-statistics`). Plus de DDL dans le transfert, donc plus aucun des sept pièges
de restauration.

Audit du schéma de dev (déjà en MySQL 8.0.33) : 24 tables toutes InnoDB, collations
`utf8mb4_unicode_ci` uniformes, aucune colonne `utf8mb3`, **19 clés étrangères toutes vers
des PK/uniques** (donc `restrict_fk_on_non_standard_key` n'a rien à rejeter), aucune date
zéro, aucun `FLOAT(M,D)`.

**Schéma de production vérifié le 21 août 2026** — le dernier risque base de données est
levé. Le dump de structure de production a été chargé dans une base neuve et comparé à une
base construite uniquement par les 66 migrations :

| | Production | Migrations | Écarts |
|---|---|---|---|
| Tables | 24 | 24 | **0** |
| Colonnes | 166 | 166 | **0** |
| Index | 44 | 44 | **0** |
| Clés étrangères | 19 | 19 | **0** |

Comparaison portant sur les types, la nullabilité, les valeurs par défaut, `EXTRA`, les
collations, la composition et l'unicité des index, et les règles `ON UPDATE`/`ON DELETE`.
**Aucun `ALTER` manuel n'a jamais divergé** : les migrations sont bien la seule source de
vérité du schéma. La structure de production passe par ailleurs tous les contrôles 8.4.

### D-11 · Vite écarté, Laravel Mix conservé
**Phase 0.** La contrainte réelle n'est pas « Mix ou Vite » mais : **le bundle doit se
charger en script classique, synchrone, avant les blocs `@stack('js')`**. `@vite` émet
`type="module"`, différé par spécification, ce qui casserait 15 blocs, 54 appels jQuery et
4 appels TinyMCE. S'y ajoute la résolution des skins TinyMCE à l'exécution.

Mix 6 épinglé (`webpack@5.106.2` + override `webpackbar@^7`). Sortie de secours si
nécessaire : **webpack 5 nu**, ~40 lignes, qui produit le même `mix-manifest.json` — donc
`mix()` continue de fonctionner et **aucune vue ne change**.

### D-12 · Paramètre de route de suivi corrigé
**Hop 2.** `PlaceHolder::getReplacement()` appelait
`route('follow-up', ['token' => …, 'status' => …])` alors que la route déclare
`/suivi/{token}/{stillNonSmoking}`. Laravel 5.x comblait le segment manquant **par
position** et produisait la bonne URL par accident ; **Laravel 6 lève
`UrlGenerationException`**.

Portée réelle : n'importe quel corps d'email éditable peut contenir `%SUIVI_OUI%` ou
`%SUIVI_NON%`, donc **tout envoi utilisant ces jetons aurait planté**. Les URL produites
sont inchangées (`/suivi/TOK/true`), seul le nom du paramètre l'est.

C'est le test `PlaceHolderTest` — écrit en Phase 0 précisément pour surveiller cette
tolérance — qui l'a attrapé. Trois tests en échec, cause unique.

### D-13 · `filp/whoops` → `facade/ignition`
**Hop 2.** Laravel 6 remplace whoops par ignition comme page d'erreur. `filp/whoops`
retiré, `facade/ignition ^1.4` ajouté en dev.

### D-14 · Gestionnaire d'exceptions typé sur `Throwable`
**Hop 3.** Symfony 5, dont dépend Laravel 7, a élargi la signature parente :
`report(Throwable $e)` et `render($request, Throwable $e)`. `App\Exceptions\Handler`
typait `Exception` et faisait **planter le boot** — panne bruyante, immédiate, sans risque
de passer inaperçue. Retypé.

### D-15 · `laravel/ui` ajouté
**Hop 3.** Laravel 7 sort les traits d'authentification du framework. Les **cinq**
contrôleurs de `Auth/` en dépendent : `AuthenticatesUsers`, `RegistersUsers`,
`ResetsPasswords`, `SendsPasswordResetEmails`, `VerifiesEmails`.

Deux options : réinstaller `laravel/ui`, ou internaliser les traits. **Paquet retenu** —
internaliser signifiait recopier ~400 lignes de code framework dans une application qui
n'a aucune couverture de test sur l'authentification. `laravel/ui` couvre toute l'échelle :
v2.5 (L7), v3.4 (L8/9), v4.6 (`^9.21|^10|^11|^12|^13`). **À monter à chaque hop.**

### D-16 · Sérialisation des dates en ISO-8601 — sans effet ici
**Hop 3.** `toArray()`/`toJson()` émettent désormais `2021-08-03T09:45:00.000000Z` au lieu
de `2021-08-03 09:45:00`. Audité avant le bump : les cinq usages de `toJson()` sont
**uniquement dans des messages de log ou d'exception**, aucune réponse API, aucun `@json()`
en vue, aucune sérialisation vers du JS. **Aucune surcharge de `serializeDate()` nécessaire**
— seul le format des lignes de log change.

> **Correction au plan de migration.** Il annonçait qu'en Laravel 7 les commandes artisan
> *devaient* retourner un entier depuis `handle()`. **C'est faux** : la valeur de retour
> sert de code de sortie, `null` valant 0. Vérifié — les 13 commandes fonctionnent
> inchangées et sortent en 0. Douze d'entre elles ne retournent rien ; rendre les codes de
> sortie explicites reste souhaitable pour du cron, mais c'est une amélioration, pas une
> exigence du hop.

### D-17 · Les 91 routes en syntaxe chaîne conservées
**Hop 4.** Laravel 8 ne met `$namespace` à `null` que dans les **nouvelles** applications.
En conservant `protected $namespace = 'App\Http\Controllers'` et les
`->namespace($this->namespace)` de `RouteServiceProvider`, **`routes/web.php` n'a pas
changé d'une ligne**. Vérifié après le bump : 90 routes résolues, 0 non résolue.

### D-18 · Seeders et factories migrés plutôt que compatibilisés
**Hop 4.** Deux échappatoires existaient — `laravel/legacy-factories` et le maintien du
`classmap`. Écartées : il n'y avait que **3 définitions de factory et 5 usages**, donc
migrer proprement coûtait moins cher que porter un paquet de transition jusqu'à la 13.

- `database/seeds` → `database/seeders`, namespace `Database\Seeders`
- `autoload` : `classmap` → **PSR-4** (`Database\Factories\`, `Database\Seeders\`)
- 3 factories de classe (`SchoolClassFactory`, `TeacherFactory`, `UserFactory`),
  `HasFactory` sur les trois modèles, `factory(X::class)` → `X::factory()`

Bénéfice inattendu : l'ancienne `UserFactory` créait un `Teacher` **en dur dans sa
définition**, donc tout appel qui surchargeait `teacher_id` laissait un orphelin derrière
lui. La version en classe utilise `Teacher::factory()` en relation. Vérifié sur base
vierge : 20 utilisateurs → **exactement 20 enseignants**.

### D-19 · Colonne `uuid` ajoutée à `failed_jobs`
**Hop 4.** La table datait de 2018 ; Laravel 8 y a ajouté `uuid` et s'en sert pour
adresser un job précis avec `queue:retry`. **Tout le mail de cette application est mis en
file**, donc `failed_jobs` est l'endroit où atterrissent les échecs d'envoi — c'est la
table qu'on consulte quand un enseignant dit n'avoir rien reçu. Migration ajoutée.

### D-20 · Test de mot de passe : de la syntaxe au comportement
**Hop 4.** Laravel 8 remplace la chaîne `'min:8'` par un objet
`Rules\Password::defaults()`. Le test interrogeait la syntaxe des règles et s'est cassé.

Réécrit pour interroger le **comportement** : sept caractères refusés, huit acceptés, via
le `Validator`. Laravel a exprimé ce minimum successivement en `min:6`, `min:8` puis en
objet — les trois signifient la même chose pour un enseignant, et seul le comportement
survit au prochain changement de représentation.

> Note : `Password::defaults()` sans configuration vaut `Password::min(8)`. Le déclarer
> explicitement dans `AppServiceProvider` le rendrait visible et permettrait d'ajouter des
> règles de complexité. Amélioration possible, non faite.

### D-21 · Premier saut PHP : 7.4 → 8.0.30
**Hop 5.** `require.php` en `^8.0.2`, `config.platform.php` en `8.0.2`, et `bin/test`
passe par défaut sur `php80`. Les 204 fichiers passaient déjà le linter PHP 8.0 avant le
bump — vérifié, pas supposé.

### D-22 · Flysystem 1 → 3 : aucun impact pratique
**Hop 5.** L'élément que le plan désignait comme **le plus coûteux du hop**. Le
`StorageBehaviourTest`, écrit en Phase 0 pour prédire cette bascule, a fait rougir
**quatre assertions au commit exact** qui l'a provoquée.

Ce qui a réellement changé :

| Appel sur fichier manquant | Flysystem 1 | Flysystem 3 |
|---|---|---|
| `get()` | levait | **`null`** |
| `readStream()` | levait | **`null`** |
| `delete()` | `false` | **`true`** |
| `size()` | `FileNotFoundException` | **`UnableToRetrieveMetadata`** |

**Impact mesuré : nul.** `Storage::get()` et `readStream()` n'ont **aucun** appelant dans
l'application. Les **quatre** appels à `Storage::delete()` ignorent tous la valeur de
retour. Rien ne dépendait de l'ancienne sémantique.

> **Correction au plan.** Il prédisait que `Storage::download()` sur un fichier manquant
> cesserait d'échouer vite et construirait une réponse mourant en plein flux. **Faux** :
> `download()` lève toujours, parce qu'il lit la taille du fichier pour `Content-Length`.
> Vérifié. Le chemin de téléchargement des certificats (B-01) est donc inchangé.

### D-23 · `fideloper/proxy` supprimé, `TrustProxies` reparenté
**Hop 5.** Symfony 6 supprime `Request::HEADER_X_FORWARDED_ALL`, référencé à **deux**
endroits — le middleware et `config/trustedproxy.php`, ce dernier évalué au boot.

`TrustProxies` hérite désormais de `Illuminate\Http\Middleware\TrustProxies` (absorbé
dans le cœur en 8.54.0), `config/trustedproxy.php` est supprimé, et `$proxies` porte sa
valeur en propriété.

**Vérifié plutôt que supposé** : le remplacement vaut exactement **30 (`0b011110`)**, la
même valeur que l'ancienne constante. C'est une équivalence, pas un élargissement —
`X_FORWARDED_PREFIX` reste exclu. **La confiance aux proxies est inchangée par ce hop.**

**`$proxies` passé de `'*'` à `null`** dans la foulée, pour ne pas reporter la question
jusqu'à la bascule. `'*'` fait confiance à tout en-tête `X-Forwarded-*` reçu ; ce n'était
tolérable que parce que le conteneur est injoignable autrement que par nginx en loopback,
ce qui cesse d'être vrai sur Forge.

`null` est le défaut du squelette Laravel et le bon réglage pour un site Forge standard :
nginx y est le serveur web parlant à PHP-FPM sur socket local, pas un reverse proxy devant
un autre, et il transmet le schéma par `fastcgi_param` plutôt que par un en-tête. Le
framework traite par ailleurs `*.on-forge.com` en cas particulier et y fait confiance à
l'IP appelante — **le domaine de préproduction Forge continue donc de fonctionner**.

Vérifié : avec `null`, un `X-Forwarded-For: 203.0.113.9` forgé est ignoré et l'application
voit l'IP réelle. `RouteIntegrityTest::testTheApplicationDoesNotTrustEveryProxy` empêche
tout retour à `'*'`. Si un CDN est ajouté un jour, y déclarer **ses plages publiées**.

### D-24 · Swift Mailer → Symfony Mailer : sans effet
**Hop 5.** Audité avant le bump : les cinq Mailables n'utilisent que `from`, `replyTo`,
`subject`, `view` et `with` — toutes préservées. **Aucun fichier de `app/Mail/` n'a
changé.** Transport confirmé après coup : `Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport`,
SwiftMailer désinstallé.

### D-25 · `facade/ignition` → `spatie/laravel-ignition`
**Hop 5.** Le paquet a changé de mainteneur et de nom en Laravel 9.

### D-26 · `protected $dates` → `$casts`
**Hop 6.** Supprimé en Laravel 10. Quatre modèles : `SchoolClass` (9 colonnes), `Quiz` (2),
`EditableDate` (1), `QuizResponse` (1). Aucun `$casts` préexistant, donc conversion sans
conflit.

Converti **avant** le bump : `$casts` avec `'datetime'` fonctionne déjà en Laravel 9, donc
la suite a validé le changement isolément au lieu de le noyer dans le saut de version.

### D-27 · `isCurrentDay()` est une méthode magique — garde-fou posé
**Hop 6.** Trouvé en vérifiant que `EditableDate::find()` renvoyait toujours un Carbon après
la conversion. `method_exists()` renvoie **`false`** sur `isCurrentDay()` : Carbon résout
toute la famille `is<Unité>()` dynamiquement via `__call`. Aucune analyse statique ne la voit.

Or **neuf portes de dates en dépendent** (`MailController` ×8, `NewsletterController` ×1) —
c'est littéralement ce qui décide si le calendrier email se déclenche. **Carbon 3 devient
obligatoire au hop 8.** Si la résolution magique disparaissait, plus aucun mail programmé ne
partirait et **le seul symptôme serait le silence**.

`MailDateGateTest::testCarbonStillResolvesIsCurrentDayDynamically` l'assertionne
directement, avec un message qui nomme sa propre cause.

### D-28 · PHPUnit 9 → 10, schéma de configuration migré
**Hop 6.** `--migrate-configuration` a réécrit `phpunit.xml` au schéma 10.5 et retiré
`convertErrorsToExceptions` / `convertNoticesToExceptions` / `convertWarningsToExceptions`,
supprimés en PHPUnit 10.

**Vérifié après coup** : les huit surcharges d'environnement ont survécu, notamment
`DB_DATABASE=missionnichtrauchendb_test` et `MAIL_DRIVER=array` — ce sont elles qui
empêchent d'effacer la base de dev et d'envoyer de vrais mails. `.phpunit.cache` ajouté au
`.gitignore`.

### D-29 · Piège rencontré : `composer.lock` avancé sans installation
**Hop 6.** Le `composer update` a dépassé le délai d'exécution et a été basculé en arrière-plan ;
il a **écrit le lock puis a été interrompu avant d'installer**. Résultat : lock en 10.50.3,
`vendor/` resté en 9.52.22 — et **une suite verte qui testait encore Laravel 9**.

Faux positif exactement du type qui fait croire qu'un hop est passé alors qu'il ne l'est pas.

> **Règle adoptée pour la suite** : après chaque bump, comparer `vendor/composer/installed.json`
> à `composer.lock` paquet par paquet, et ne jamais se fier au seul code de sortie. Le contrôle
> tient en une commande et vaut mieux qu'un doute.

### D-30 · Carbon 3 : décalage horaire sur deux chemins — **corrigé**
**Hop 7.** Carbon 3 arrive avec Laravel 11 et change `createFromTimestamp()` /
`createFromTimestampMs()` pour **défaut UTC**, là où Carbon 2 utilisait le fuseau de
l'application. Trois sites d'appel, deux conséquences distinctes.

`Api\QuizController` écrivait `responded_at` **une heure en arrière** (deux en été).
`QuizMakerWebhookTest` l'a attrapé — c'est le test qui a fait rougir la suite.

**Le plus grave n'avait aucune couverture.** `Admin\QuizController` fixe `closes_at` de la
même manière, et `quiz:update` tourne **chaque minute** en comparant `closes_at` à
`CURRENT_TIMESTAMP` côté MySQL. Un admin saisissant `18:00` aurait vu **`17:00` stocké** —
`16:00` en heure d'été — et **le quiz aurait fermé une à deux heures trop tôt**, sans
erreur, sans log. Les enseignants auraient simplement perdu l'accès en avance.

Les trois sites déclarent désormais le fuseau explicitement. `QuizClosingTimeTest` couvre
le chemin non testé, avec une marge de 30 minutes choisie pour qu'un décalage d'une heure
inverse le résultat.

### D-31 · `beyondcode/laravel-dump-server` supprimé
**Hop 7.** Il plafonnait à `illuminate/support ^10.0` et **bloquait le hop à lui seul**.
Jamais référencé dans le code, en `require-dev`, et même sa dernière version plafonne à
Laravel 12 — il aurait rebloqué au hop 9. Son mainteneur renvoie vers **la fenêtre Dump de
Herd**, déjà installée.

> Le message d'erreur de Composer désignait un conflit entre `illuminate/support` et
> `laravel/framework` « qui ne peuvent coexister », en listant cinquante versions écartées.
> La cause réelle n'y figurait pas. `composer why illuminate/support` l'a donnée en une ligne.

### D-32 · `doctrine/dbal` supprimé — `->change()` vérifié sur le schéma réel
**Hop 7.** Laravel 11 gère `->change()` en SQL natif. Le plan qualifiait ça de sans risque
« les migrations ont déjà été appliquées » — **faux ici** : la base du serveur neuf est
construite par `artisan migrate`, donc **les 66 migrations s'exécutent réellement**, dont
les deux qui utilisent `->change()` sans restater tous les modificateurs.

Vérifié plutôt que supposé, en rejouant la comparaison de schéma : base construite par les
migrations **sous Laravel 11 sans dbal**, diffée contre la structure de production.

| | Production | Migrations L11 | Écart |
|---|---|---|---|
| Colonnes | 166 | 167 | `failed_jobs.uuid` (ajout volontaire, D-19) |
| Index | 44 | 45 | son index unique |
| Clés étrangères | 19 | 19 | **0** |

**Aucun autre écart.** Les deux `->change()` produisent un schéma identique sans dbal.

### D-33 · Structure d'application volontairement **non** migrée
**Hop 7.** La doc Laravel déconseille explicitement la migration du squelette pour une
application existante. `app/Http/Kernel.php`, `app/Console/Kernel.php`,
`app/Exceptions/Handler.php`, `bootstrap/app.php` ancien format et les 13 fichiers de
config sont **conservés tels quels**. C'est le plus gros coût que la plupart des plans
s'infligent sans nécessité. `registerPolicies()` existe toujours et n'est pas déprécié en
11.56.

### D-34 · `guzzlehttp/guzzle` revenu tout seul
**Hop 7.** Comme annoncé en D-08 : retiré des dépendances directes au hop 1, Laravel 11
l'exige en dur et l'a réinstallé en **7.15.3**. Aucune intervention.

### D-35 · Racine du disque `local` — garde-fou posé
**Hop 8.** Laravel 12 déplace la racine **par défaut** du disque `local` de `storage/app`
vers `storage/app/private`. L'application y échappe **uniquement** parce que
`config/filesystems.php` déclare `'root' => storage_path('app')` explicitement.

L'enjeu est concret : `certificates.url` contient des chemins **relatifs**
(`certificats/{uuid}/certificat.pdf`), résolus contre cette racine. Si elle bougeait, les
certificats et les documents enseignants deviendraient **introuvables d'un coup** — et ni
les uns ni les autres ne sont régénérables.

`StorageRootTest` verrouille les trois propriétés : racine, disque par défaut, et
résolution effective des chemins. Vérifié après le bump : **7 certificats en base, 7
fichiers présents.**

### D-36 · Hop 12 sans incident
**Hop 8.** Les quatre points du plan étaient déjà neutralisés : Carbon 3 en place depuis le
hop 7, aucun appel `diffIn*`, aucun nom de route en double (Phase 0), et la racine du
disque déclarée explicitement. Laravel estime ce hop à cinq minutes ; ici il n'a rien
demandé d'autre que la montée de version.

### D-37 · Laravel 13 + PHP 8.5 — arrivée
**Hop 9.** `VerifyCsrfToken` renommé `PreventRequestForgery` (l'ancien nom survit en alias
**déprécié** — reparenté et renommé, plus aucune référence). tinker ^3.0, PHPUnit ^12.5.33.

Les points redoutés par le plan ne s'appliquaient pas : **aucun `upsert()`**, **aucun objet
mis en cache** (donc `cache.serializable_classes` sans effet), et surtout **aucune collision
avec `array_first()`/`array_last()`** du polyfill PHP 8.5 — parce qu'on n'a jamais eu besoin
du contournement `laravel/helpers` aux hops 2 à 4.

Préfixes de cache et cookie de session **insensibles** au changement de séparateur du
squelette : ils sont dérivés dans nos propres fichiers de config avec `'_'` explicite.
Valeurs inchangées : `mission_nichtrauchen_cache`, `mission_nichtrauchen_session`.

**Une dépréciation, de mon fait** : `makeClass(Teacher $teacher = null)` dans les fixtures —
nullable implicite, déprécié en PHP 8.4. J'avais audité l'application pour exactement ça au
départ et trouvé zéro ; j'en avais introduit un. Corrigé.

### D-38 · `phpoffice/phpspreadsheet` 1.30 → 5.9 et ses 9 sites d'appel
**Hop 9.** C'est `composer audit`, une fois le blocage réactivé, qui l'a imposé : **9 avis,
un seul paquet**. Tout le reste de l'arbre Laravel 13 était déjà propre.

PhpSpreadsheet 2.0 a supprimé toute la famille `*ByColumnAndRow()`. Les 9 sites étaient tous
dans `ClassExportController`. **Rien de tout ça n'est visible pour un linter** : le code
parse, et échoue seulement quand un administrateur demande son export.

Convertis en coordonnées `[colonne, ligne]`, et en notation A1 via `Coordinate::stringFromColumnIndex()`
pour les deux plages (filtre automatique, bordures de ligne).

### D-39 · Chemin d'export codé en dur — corrigé
**Hop 9.** Découvert en écrivant le test : les deux contrôleurs d'export mélangeaient la
façade `Storage` et un chemin **relatif en dur**, `"../storage/app/$relPath"`.

Ce chemin ne se résout correctement que si le répertoire courant est `public/` — vrai sous
php-fpm, **faux depuis la console, un worker de queue ou un test**, où il écrirait *hors du
projet*. Remplacé par `\Storage::path($relPath)` dans les deux contrôleurs.

### D-40 · Les exports ont enfin une couverture
**Hop 9.** `SpreadsheetExportTest` pilote les deux points d'entrée de bout en bout avec des
données réelles — classes, enseignants, quiz, réponses, groupes de fête — plus le cas du
concours vide et le contrôle d'accès administrateur. Sans ça, les 9 conversions n'auraient
été que compilées, jamais exécutées.

### D-41 · tFPDF → `fpdf/fpdf` — rendu identique au pixel près
**Après l'échelle.** `setasign/tfpdf` était mort (dernière version décembre 2022) et portait
**7 signatures à nullable implicite** plus **6 appels `utf8_encode()`** — supprimé en PHP 9.

Le remplacement était quasi gratuit parce que **l'application n'utilisait pas l'Unicode de
tFPDF** : `NewCertificateService::conv()` convertit en `windows-1252` et charge des
définitions MakeFont, donc elle pilotait déjà tFPDF **en mode FPDF classique**.
`fpdf/fpdf` était d'ailleurs **déjà installé** et déjà utilisé par l'ancien
`CertificateService`.

Polices régénérées en JSON avec l'utilitaire `makefont` fourni — `_loadphpfont()` déclenche
un `E_USER_DEPRECATED` par police chargée. **Le TTF d'origine n'était pas nécessaire** : la
fonction `ConvertToJSON()` transforme les définitions `.php` existantes. Les fichiers `.z`
restent requis, le JSON les référence.

**Vérification, pas supposition.** Un certificat de référence a été produit avant migration,
un autre après, puis comparés :

| Contrôle | Résultat |
|---|---|
| Texte extrait (`pdftotext`) | **identique** |
| Rendu PNG 150 dpi (`ghostscript`) | **identique octet pour octet** — 734 351 o |
| Différence binaire | **5 octets**, uniquement `/Producer` |

`CertificateGenerationTest` couvre désormais ce chemin : production du PDF, police et fond
JPEG effectivement embarqués, noms d'école accentués, écriture sur disque via
`SchoolClassManager`, et non-génération sans quiz répondu.

Au passage : `SchoolClassManager` documentait `@var CertificateService` alors qu'il injecte
`NewCertificateService` — corrigé. L'ancien `CertificateService` est **du code mort**
(référencé nulle part) ; ses polices sont passées en JSON et son statut est documenté, mais
**il n'a pas été supprimé** — c'est une décision à prendre, pas un effet de bord.

### D-42 · Laravel Mix 4 → 6, Node 16 → 22
**Après l'échelle.** Ce n'était **pas** un chantier optionnel : `public/.gitignore` — un
second fichier, à l'intérieur de `public/` — exclut `js/app.js`, `css/*` et
`mix-manifest.json`. **Les assets compilés ne sont pas dans le dépôt**, donc le déploiement
doit les construire. Or Mix 4 / webpack 4 ne tourne pas sur Node ≥ 17, et un serveur Forge
neuf a du Node moderne : sans cette montée, `npm run prod` échoue à la bascule.

Les deux épingles nécessaires, identifiées par la recherche préalable :
`webpack` figé à **5.106.2** (bug Mix #3413 — webpack ≥ 5.107 a déplacé
`webpack/lib/SizeFormatHelpers`) et un override **`webpackbar@^7`** (#3410). Sans elles une
installation neuve de Mix 6 échoue, indépendamment de Node.

Les scripts `package.json` passent au CLI `mix` : les anciens invoquaient webpack
directement avec `--hide-modules` et `--progress`, **deux options supprimées du CLI de
webpack 5**. `cross-env` disparaît (Mix gère `NODE_ENV`, et le paquet est archivé).

Retirés au passage, tous vérifiés sans usage : **`axios`** (assigné à `window`, jamais
appelé, **24 avis publiés** à la version épinglée `0.18`), **`vue-template-compiler`** et
`resources/js/components/` (Vue n'était jamais instancié). Correctifs de sécurité :
jQuery 3.3.1 → 3.7.1 (CVE‑2020‑11022/11023), lodash → 4.17.21, **Bootstrap 4.1.3 → 4.6.2**
(CVE‑2019‑8331). **Bootstrap reste en 4** — la 5 est un chantier séparé d'environ 180
occurrences sur 54 fichiers Blade.

**TinyMCE reste volontairement en 5.10.9.** La montée en v8 est une **décision de licence**
(GPLv2+ à partir de la v7), pas une décision d'outillage — elle n'a pas sa place dans ce
commit. Voir §5.

Vérifié après build : le bundle expose `window.$`, `window.jQuery`, `window._`,
`window.Popper`, TinyMCE, DataTables et le datepicker ; **c'est un IIFE classique, pas un
module ES** ; les 7 URL de polices du CSS résolvent toutes ; les skins TinyMCE de
`public/js/skins` sont intacts.

### D-43 · `layouts/frontend` n'invalidait pas le cache — corrigé
**Après l'échelle.** Trouvé en vérifiant le build. Trois layouts utilisaient `mix()`, mais
**`layouts/frontend` utilisait `asset()`** — donc les pages **publiques** (connexion, suivi,
réponse fête, téléchargement de certificat) servaient des URL sans empreinte de contenu,
**sans aucune invalidation de cache**.

Anodin tant que le bundle ne bougeait pas ; nettement moins au moment précis où il vient de
changer de fond en comble. Un visiteur revenant avec l'ancien CSS aurait vu une page cassée.
Les quatre layouts sont désormais cohérents, et `AssetPipelineTest` verrouille les deux
propriétés : versionnement par `mix()` partout, et **absence de `type="module"`** — la
contrainte qui interdit Vite (voir D-11).

### D-44 · Passe navigateur : quatre pannes qu'aucun test ne voyait
**Après l'échelle.** Premier affichage réel d'un écran depuis Laravel 5.7. La suite comptait
alors 79 tests **mais ne touchait que 3 des 68 routes GET** — toute la couche vue avait
traversé huit versions majeures sans être exercée.

Le site Herd était resté épinglé sur **PHP 7.4** et renvoyait 500 au contrôle de plateforme ;
basculé sur 8.5. Au passage, `composer.json` déclarait `"php": "^8.3"` alors que le lock ne
peut **pas** tourner sur 8.3 — les composants **Symfony 8 exigent ≥ 8.4.1**. Contrainte
corrigée en `^8.4.1`.

**1 · Carbon 3 refuse les dates nulles.** `EditableDate::find()` renvoie `null` pour une clé
absente ; Carbon 2 l'acceptait en le traitant comme « maintenant », Carbon 3 lève une
`TypeError`. **Treize sites d'appel** passaient `find()` directement dans une comparaison, et
**12 des 23 clés déclarées étaient absentes de la base** — `/admin/classes` renvoyait 500 dès
la connexion d'un administrateur.

Le plus grave n'a pas encore mordu : **`isRegistrationOpen()` est appelé depuis
`layouts/app-sidebar`**, la coquille de *toutes* les pages authentifiées. Perdre une des deux
dates d'inscription ferait tomber **l'application entière, pour tout le monde**.

Corrigé à la source par `EditableDate::hasPassed()`, qui répond `false` pour une date absente
— un événement non configuré n'est pas atteint. **Changement délibéré** par rapport au `true`
accidentel de Carbon 2, qui faisait apparaître un suivi non démarré comme commencé.

> Constat séparé : un `artisan migrate` neuf ne crée que **10 des 23 clés**.
> `TEACHER_INSCRIPTION_END` n'en fait pas partie — une base construite par les seules
> migrations aurait les inscriptions fermées en permanence. La bascule importe les données de
> production, donc ça ne mord pas là ; mais ça explique l'écart.

**2 · Noms de paramètres de route, dans les vues cette fois.** Même défaut qu'au hop 2 (D-12),
que l'audit précédent avait manqué **parce qu'il ne cherchait que dans le PHP**.
`external/certificate-download.blade.php` passait `certificate`, et
`emails/teacher-certificate.blade.php` passait `uid`, à des routes déclarant `{certificate_uid}`.

Le second est dans le **modèle d'email du certificat** : son rendu levait, donc **l'envoi
aurait échoué pour chaque classe éligible**, au moment de l'année qui compte le plus pour un
enseignant.

**3 · Argument positionnel nul.** `admin/certificates.blade.php` passait
`[$class->certificate]` sur chaque ligne, alors qu'il calculait déjà `$cert` pour griser le
bouton. Une seule classe sans certificat suffisait à faire tomber la page — et en début
d'année de concours, presque toutes le sont.

**4 · Vues formatant une date nulle** : `admin/classes.blade.php`, `admin/emails.blade.php` et
`EditableEmail::getDatesStringAttribute()`.

`RouteParameterNamesTest` compare désormais **chaque appel `route()` à clés nommées** contre la
déclaration de sa route, sur `app/` **et** `resources/views/`. Un grep les trouve une fois ;
ce test les trouve pour toujours.

**Vérifié après correction : les 52 routes GET résolvables répondent** (les 16 autres exigent
des données absentes en local). TinyMCE, DataTables, le datepicker et l'aperçu en direct des
emails fonctionnent tous à l'écran.

### D-45 · B-01 corrigé · les liens de certificat renvoient 404
**Après la passe navigateur.** Les deux routes de certificat sont publiques, atteintes par un
uid non devinable envoyé par mail, et **déréférençaient `->first()` sans garde**.

Deux façons ordinaires de n'avoir rien à servir, toutes deux en 500 auparavant : un uid
inconnu (lien mal recopié, périmé, régénéré) ; et une ligne dont le PDF a disparu — les
certificats sont régénérés entre éditions et l'ancien répertoire supprimé avec eux, donc un
lien ancien pointe vers une ligne existante et un fichier absent. `Storage::download()` lève
alors, puisqu'il lit la taille pour `Content-Length`.

`firstOrFail()` pour la ligne, `abort_unless(Storage::exists(...))` pour le fichier.

### D-46 · B-02 corrigé · un rejeu ne perd plus le reste du lot
**Après la passe navigateur.** `Api\QuizController` faisait `return` au lieu de `continue`
sur un code déjà enregistré — alors que la ligne de log juste au-dessus dit *« skipping it »*,
ce qui était manifestement l'intention.

La conséquence est précise : **quiz-maker rejoue une livraison en entier**, donc la tentative
censée récupérer un résultat perdu était elle-même garantie de **perdre tout ce qui suivait le
premier code déjà connu**. Rien ne le signalait : l'endpoint répond toujours un 200 vide.

> Corriger le contrôleur tenait en un mot. Prouver la correction a pris plus longtemps, et la
> raison mérite d'être notée : **c'est la fixture qui était fausse, pas l'application**. Elle
> créait un **second** `QuizInLanguage` pour le même `quiz_maker_id`, alors que le contrôleur
> le résout par un unique `->first()` — le second code était donc inatteignable et le test
> échouait pour une raison étrangère au correctif. La forme réelle est **un enregistrement par
> langue portant plusieurs codes**, un par classe participante.

### D-47 · Un seul `CertificateService`
**Après la passe navigateur.** Il y en avait deux, dont un mort depuis des années :
`SchoolClassManager` injectait `NewCertificateService`, et rien ne référençait l'ancien hors
sa propre déclaration et un docblock périmé qui nommait le mauvais.

L'ancien est supprimé, le survivant prend le nom simple — le préfixe « New » ne veut plus rien
dire dès que l'autre disparaît. Sept fichiers de police partent avec lui : Calibri et CALIBRIB
n'existaient que pour lui, et les définitions `.php` sont supersédées par les `.json`, que
FPDF 1.9 déprécie à chaque chargement. Restent `rockweb.json` et son `rockweb.z`.

### D-48 · SparkPost en SMTP, en attendant le DNS de Scaleway
**Le DNS de Scaleway dépend du client.** En attendant, l'expéditeur redevient SparkPost —
sans réinstaller quoi que ce soit. Ce que Laravel 6.0 a supprimé, c'est le *driver API*
`sparkpost` (qui exigeait Guzzle, retiré en D-31) ; le SMTP est le transport que toutes les
versions parlent à l'identique. L'hôte `smtp.eu.sparkpostmail.com` garde en outre le contenu
dans l'UE, ce qui était l'argument retenu pour Scaleway. Le passage à Scaleway sera trois
lignes de `.env` : hôte, identifiant, clé.

**Au passage, une configuration morte qui ne le disait pas.** `MAIL_ENCRYPTION=tls` traînait
depuis la 5.7. Laravel 13 ne la lit plus du tout : `MailManager::createSmtpTransport()`
déduit la connexion du seul schéma. La clé donnait donc l'impression que la sécurité du
transport était fixée alors que rien ne la fixait. Elle est supprimée plutôt que laissée à
faire illusion.

Ce qui protège réellement les identifiants, c'est le STARTTLS opportuniste de Symfony, qui
élève la socket **avant `AUTH`** — mais seulement si le serveur annonce `STARTTLS`. Un
serveur qui ne l'annonce pas, mal configuré ou parce que la connexion a été dégradée, est
accepté en silence et la clé API part en clair. `require_tls` transforme ce silence en refus ;
`config/mail.php` l'active par défaut, un puits local comme Mailpit s'en exclut avec
`MAIL_REQUIRE_TLS=false`. `SmtpTransportSecurityTest` fixe les trois cas (587 → STARTTLS
exigé, 465 → TLS implicite, schéma explicite prioritaire) et interdit le retour de la clé
morte.

**`MAIL_ALWAYS_TO`.** Pointer une machine de développement vers un vrai fournisseur la met
à un `queue:work` d'écrire à de vrais enseignants depuis une copie de la base de production.
Quand la variable est renseignée, `AppServiceProvider` redirige tout vers cette adresse ;
vide en production, où elle doit le rester. `phpunit.xml` la neutralise pour que la suite ne
dépende pas du `.env` de la machine.

**`php artisan mail:test <adresse>`** envoie un message de façon **synchrone**. Tout le
courrier de cette application est mis en file : un échec d'identifiants finit normalement en
ligne dans `failed_jobs` que personne ne lit — exactement la mauvaise boucle de retour quand
ce qu'on teste, ce sont justement les identifiants et le domaine d'envoi. La commande affiche
le transport résolu et rend le refus SMTP tel quel (un 550 nomme un domaine non vérifié, un
535 de mauvais identifiants).

---

### D-49 · B-04 corrigé · `quiz:update` interrogeait l'horloge de MySQL
**Découvert en relançant la suite après un redémarrage de MySQL.**
`QuizClosingTimeTest` — le détecteur de dérive d'horloge écrit au hop Carbon 3 — est passé
au rouge sans qu'une seule ligne du code quiz ait bougé. Il ne s'agissait pas d'un test
instable : sur cette machine, PHP annonce 12:02 et MySQL 11:02.

`quiz:update` comparait `closes_at` à `CURRENT_TIMESTAMP`, c'est-à-dire posait à l'horloge
de la base une question sur une valeur écrite par l'horloge de PHP. `config/database.php`
ne fixe aucun fuseau de session, MySQL hérite donc de `SYSTEM`. Les deux horloges ne
s'accordaient que par accident — et sur un serveur Forge, MySQL tourne en UTC pendant que
l'application tourne en `Europe/Luxembourg` : une heure d'écart en hiver, deux en été.

La panne aurait été silencieuse. Un quiz qui se ferme à la mauvaise heure ressemble
exactement à un quiz qui se ferme : pas d'erreur, pas de log, juste des enseignants qui
perdent l'accès une heure trop tôt. C'est la commande la plus fréquente de l'application,
ordonnancée **chaque minute**.

Au passage, une contradiction interne : `Quiz::validate()` (`app/Quiz.php:70`) tranchait
déjà la même question avec `now()` de PHP. Deux horloges décidaient de la même chose.
`quiz:update` utilise désormais `now()` comme le reste.

**Le test qui le fixe reproduit la condition déployée** plutôt que d'inspecter le source :
il force `SET time_zone = '+05:00'` avant d'écrire le quiz, donc tout le scénario se déroule
dans un cadre cohérent, comme une requête déployée. Vérifié en rétablissant l'ancienne
implémentation : le test échoue bien, sur « closed early ».

À noter pour la mise en production : `closes_at` est une colonne `TIMESTAMP`, que MySQL
convertit via le fuseau de session à l'écriture comme à la lecture. Le correctif tient quel
que soit ce fuseau, puisque les deux côtés de la comparaison passent désormais par la même
conversion.

---

### D-50 · TinyMCE 5.10.9 → 6.8.6 · la licence n'était pas le problème
**Question posée : peut-on rester sur la version actuelle, faute de licence ?** La prémisse
ne tient pas — aucune licence n'est nécessaire, ni maintenant ni pour monter.

| Version | Licence | État |
|---|---|---|
| 5.10.9 | LGPL-2.1 | dernière 5.x publique, non corrigée |
| 5.11.0 | *absente de npm* | correctif des CVE 2024 — **LTS commerciale uniquement** |
| **6.8.6** | **MIT** | dernière 6.x, retenue |
| 7.9.3 | GPL-2.0-or-later | copyleft |
| 8.8.2 | `SEE LICENSE IN license.md` | clé de licence requise |

TinyMCE 6 est **MIT depuis la 6.0** — plus permissive que la LGPL actuelle. Le vrai piège
était ailleurs : le correctif des CVE-2024-38356/38357 pour la ligne 5.x est sorti en
**5.11.0 LTS, qui n'existe pas sur npm**. La 5.10.9 est donc définitivement non corrigée.

**Ce n'est pas pour autant une victoire nette côté sécurité, et il faut le dire.** Mesuré
dans les mêmes conditions : 5.10.9 → 7 avis (3 high), 6.8.6 → 5 avis (4 high), 7.9.3 → aucun.
Les jeux ne se recouvrent pas : monter en 6.8.6 en retire sept et en introduit quatre propres
à la ligne 6. Le gain réel est la **licence** (LGPL → MIT), la sortie d'une version morte dont
le correctif est derrière un paywall, et le fait que 6.x est le passage obligé vers 7 ou 8.

**Exposition réelle : faible.** L'éditeur est derrière le middleware `admin`, et le contenu
édité est celui que les admins écrivent eux-mêmes — aucun contenu d'enseignant ou du public
n'y entre. Les avis exigent tous du HTML hostile chargé *dans* l'éditeur. Vérifié en outre :
le plugin `media` n'est pas embarqué (seul `link` l'est), ce qui écarte GHSA-vg35-5wq7-3x7w,
et `noneditable_regexp` n'est utilisé nulle part, ce qui écartait déjà CVE-2024-38356.

**Deux ruptures 5→6, vérifiées dans le paquet plutôt que de mémoire :** `models/dom` est un
point d'entrée séparé depuis la 6 et un bundle qui l'omet n'initialise jamais l'éditeur, en
silence ; et `styleselect` n'existe plus (zéro occurrence dans `tinymce.js`), remplacé par
`styles`. Le reste de la configuration passe tel quel — `addSplitButton`, `addIcon`,
`insertContent`, thème `silver`, plugin `link`.

**Vérifié dans le navigateur, pas seulement à la compilation :** l'éditeur s'initialise sur
l'asset réellement construit, `styles` rend « Format Paragraph », et le bouton « Texte
réservé » ouvre son menu et insère bien le placeholder dans le contenu. Sans identifiants :
la sonde recharge la configuration exacte des vues depuis une page publique qui charge
`app.js`.

**Décision restante, et elle est juridique, pas technique.** Zéro avis n'existe qu'en 7.9.3,
sous GPL-2.0-or-later. Comme Mix concatène TinyMCE avec le JS applicatif dans un `app.js`
unique servi au navigateur, la question « œuvre combinée » se pose vraiment. À trancher par
la Fondation, pas ici.

---

### D-51 · Une publicité s'était glissée dans l'éditeur — et TinyMCE 7 évalué
**Régression introduite par D-50, repérée en évaluant la 7.** TinyMCE 6 ajoute un bouton
promotionnel dans le châssis de l'éditeur — que la 7 rebaptise « Get all features » — et la
5.10.9 ne l'avait pas. Vérifié dans le paquet : le balisage `tox-promotion` est absent de
la 5.10.9 et présent à partir de la 6.8.6. Une publicité n'a pas sa place dans l'éditeur où
la Fondation Cancer rédige son courrier ; `promotion: false` la coupe.

Le branding « Powered by Tiny » est **laissé tel quel** : il était déjà là en 5.10.9, ce
n'est donc pas une régression, et le retirer relève d'une autre discussion.

**TinyMCE 7.9.3 évalué sur la branche `essai/tinymce-7-gpl`.** Elle n'est pas payante —
GPL-2.0-or-later, gratuite, auto-hébergeable ; seule la 8 exige une clé achetée. Son
attrait : **c'est la seule ligne sans avis de sécurité connu**, quand la 5.10.9 en porte 7
et la 6.8.6 en porte 5 que personne ne corrigera plus.

Techniquement, la migration 6 → 7 est **une ligne** : `license_key: 'gpl'`. Tous les points
d'entrée et toutes les APIs utilisées survivent. Mesuré plutôt que supposé : avec la ligne,
zéro avertissement ; sans elle, un avertissement console « evaluation mode ». **Contrairement
à ce que laisse entendre la documentation, l'éditeur n'est pas désactivé sans elle** — il
fonctionne dans les deux cas.

Le blocage n'était donc pas technique mais juridique — et **D-52 le supprime** : TinyMCE
n'est plus concaténé au JS applicatif, la question de l'œuvre combinée ne se pose plus, et
la 7.9.3 est retenue sur ses mérites.

---

### D-52 · TinyMCE servi à part plutôt qu'embarqué — et la 7.9.3 retenue
**La piste chiffrée après la question GPL. Elle paie deux fois.**

**Licence.** Mix concaténait TinyMCE avec le JS de l'application dans un `app.js` unique —
c'est précisément ce qui rend l'argument « œuvre combinée » disponible à une licence
copyleft. Copié tel quel dans `public/vendor/tinymce` et chargé par sa propre balise
`<script>`, il relève de la simple agrégation : une bibliothèque non modifiée distribuée à
côté de l'application, pas fondue dedans. La licence sort de l'équation, et la **7.9.3** —
seule ligne sans avis de sécurité connu — devient choisissable sur ses mérites. C'est elle
qui est retenue.

**Poids, et c'était la surprise.** `app.js` est chargé par les **quatre** layouts — chaque
page enseignant, chaque page publique à jeton, l'écran de connexion — alors que l'éditeur
n'apparaît que sur **deux pages admin**. Mesuré gzippé, comme nginx le sert :

| | avant | après |
|---|---|---|
| `app.js` (toutes les pages) | 464 Ko | **114 Ko** |
| TinyMCE (2 pages admin) | — | ~371 Ko, puis en cache |

**349 Ko économisés par page non-admin.** Chaque enseignant téléchargeait un éditeur de
texte qu'il ne verrait jamais. Le build passe de 8,8 s à 3,8 s.

Seul le nécessaire est copié : le paquet fait 10 Mo, presque entièrement des plugins et
skins jamais chargés. `license.md` voyage avec — distribuer la bibliothèque, c'est
distribuer sa licence. Aucun `base_url` requis : TinyMCE résout thème, modèle, plugin et
skin depuis l'URL de son propre script (`baseURL` vérifié à `/vendor/tinymce`).

**Une trouvaille au passage.** `app.scss` importait la feuille de contenu de TinyMCE, qui
porte une règle `body` **non scopée**, servie sur toutes les pages du site. Elle n'était
inerte que **par accident** — hoistée au-dessus du reboot Bootstrap, qui écrasait ses trois
propriétés. Un simple changement d'ordre d'import l'aurait réveillée. Supprimée.

**Correction consignée :** j'avais écrit que sans `license_key` la 7 désactive l'éditeur.
C'est faux, et mesuré comme tel : sans la ligne, un avertissement console « evaluation
mode » ; avec, zéro. L'éditeur fonctionne dans les deux cas. La ligne reste — c'est
l'acceptation explicite des termes GPL — mais elle n'est pas indispensable au
fonctionnement.

Deux gardes ajoutées à `AssetPipelineTest`, **vérifiées en échec** quand on défait le
découpage.

---

### D-53 · La checklist de pré-production devient une commande
`php artisan deploy:check`. Tous les points de cette liste partagent une propriété : **quand
ils sont faux, rien ne casse.** Le site sert ses pages, l'admin enregistre son mail, la queue
accepte le job — et le courrier ne part jamais, ou part avec des liens que personne ne peut
ouvrir. C'est exactement la catégorie de panne qu'une checklist humaine rate le mieux, parce
qu'il n'y a rien à remarquer.

Deux contrôles méritent d'être expliqués, car ils lisent des symptômes plutôt que d'ajouter
de la machinerie :

- **Worker vivant** — mesuré à l'âge du plus ancien job en attente. Rien dans cette
  application n'envoie de mail directement, tout est `->queue()`. Pas de worker, pas de mail,
  aucune erreur nulle part.
- **Ordonnanceur vivant** — mesuré aux quiz dépassés et toujours `running`. `quiz:update`
  tourne chaque minute ; un quiz resté ouvert est le seul symptôme qu'un ordonnanceur mort
  produise de lui-même. Formulé honnêtement dans la sortie : *absence de symptôme, pas une
  preuve*.

`DeployCheckTest` casse un réglage à la fois depuis une base saine et exige que la commande
le remarque — une vérification qui ne peut pas échouer n'en est pas une.

**Trouvaille à l'exécution :** 9 jobs en attente depuis trois jours sur la machine locale,
dont trois `SendElegibleClassesCertificateMail`. Avec `MNR_MIN_QUIZ_RESPONSES=0` en local,
*toutes* les classes sont éligibles : au premier `queue:work`, ils écriraient à chaque
enseignant de la base locale. Seul `MAIL_ALWAYS_TO` (D-48) les retient — ce qui valide la
garde après coup.

---

### D-54 · MySQL 8.4 et les premières routes en écriture
**MySQL 8.4.7 en local, port 3308.** `phpunit.xml` pointait encore sur 3306 : la suite serait
restée verte en testant l'ancien 8.0.33, sans que rien ne le signale. Les deux ports étaient
ouverts, les deux moteurs tournaient. Corrigé, avec un commentaire disant pourquoi le port du
test doit suivre celui du `.env`. **105 tests repassent sur 8.4.7** sans une seule adaptation.

**22 nouveaux tests sur les 9 routes en écriture les plus coûteuses.** La suite n'en touchait
**aucune** des 26 : toute la chaîne requête → validation → persistance avait traversé huit
majeures sans être exercée. Écartées d'emblée : `_ignition/*` (paquet en `require-dev`, absent
en production sous `--no-dev`) et le webhook, déjà couvert.

*Priorité 1 — sans elles le concours s'arrête* : inscription enseignant (hachage du mot de
passe, unicité de l'email, consentement RGPD, mail de confirmation mis en file), ajout et
édition de classe, fenêtre d'inscription, et la garde de propriété inline — un enseignant ne
doit pas pouvoir modifier la classe d'un collègue.

*Priorité 2 — le pilotage admin* : réécriture d'un mail éditable et refus d'un corps vide,
déplacement des dates du concours, refus d'une clé inconnue, création de quiz (un
`QuizInLanguage` par langue, extraction de l'id quiz-maker), refus d'une URL étrangère au
service, refus d'une clôture passée, et les gardes d'état (`abort_if`) sur l'édition et
l'import de codes.

**Trois choses apprises en écrivant ces tests, aucune n'étant un bug :**

- Les routes d'ajout et d'édition de classe **ne nomment pas leurs champs pareil** —
  `class_name`/`class_students` d'un côté, `name`/`students` de l'autre.
- `editable_dates.value` est une colonne **`date`**, pas `datetime` : l'heure soumise est
  écartée. Granularité voulue, cohérente avec le `isCurrentDay()` des envois programmés.
  Mon test attendait `08:00:00` ; c'est l'attente qui était fausse.
- `editable_dates.label` est `NOT NULL` sans défaut, et un `migrate` neuf ne sème que 10 des
  23 clés (D-44) : la fixture doit créer la ligne, pas seulement la mettre à jour.

**Au passage :** deux commentaires de `Admin\QuizController` affirmaient encore que
`quiz:update` compare `closes_at` à `CURRENT_TIMESTAMP`. Faux depuis D-49.

---

### D-55 · B-05 · un provider de dev déclaré à la main tuait le déploiement
**Trouvé par la toute première installation Forge**, avant même un déploiement.
`composer install --no-dev` s'est terminé sur :

> `Class "Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider" not found`

**Bug préexistant, pas une régression de la migration.** Le provider était listé à la main
dans `config/app.php` depuis le commit `0105eb2 "Add ide helper"`, alors que le paquet est en
`require-dev` — donc absent de tout serveur installé en `--no-dev`. Un provider déclaré dans
la configuration est chargé inconditionnellement : `package:discover` meurt avant qu'une seule
page ne soit servie.

Le paquet **supporte l'auto-discovery** (`extra.laravel.providers` dans son `composer.json`) :
la ligne manuelle était donc redondante en développement et fatale en production. Retirée,
l'auto-discovery l'enregistre là où il existe et se tait là où il n'existe pas — les 5
commandes `ide-helper:*` restent disponibles en local.

**Vérifié contre la commande exacte de Forge**, pas par raisonnement. Première tentative
invalide : un `composer dump-autoload --no-dev` laisse les paquets de dev dans
`installed.json`, donc l'auto-discovery les retrouvait et l'erreur persistait pour une raison
étrangère au correctif. Le vrai `composer install --no-dev` passe, `package:discover` sort
en 0, puis `composer install` restaure les dépendances de dev.

`ProductionAutoloadTest` ferme la catégorie entière : il lit les espaces de noms des paquets
`require-dev` depuis `vendor/composer/installed.json` et refuse tout provider **ou alias** de
`config/app.php` qui en relève. Vérifié en échec en remettant la ligne. Cette panne est
invisible en local — tout y est installé, donc tout s'y résout.

---

### D-56 · La porte d'entrée, que rien ne testait
**Trou repéré en mesurant la couverture des routes, pas en lisant le code.** `POST /login`
n'avait aucun test. Toute la suite s'authentifie par `actingAs()` — et c'est précisément le
problème : `actingAs()` place l'utilisateur directement sur le garde et n'exécute **jamais**
`LoginController`, le trait `AuthenticatesUsers`, le throttle, la régénération de session ni
la redirection par type. Le login pouvait être cassé, la suite serait restée verte, et
**personne n'aurait pu entrer** — ni enseignant, ni administrateur.

C'est aussi le chemin le plus perturbé par la montée : Laravel 7 a sorti ces traits du
framework vers `laravel/ui`.

12 tests ajoutés : connexion, mauvais mot de passe, adresse inconnue, redirection par type,
régénération de l'identifiant de session, throttle, déconnexion, envoi du lien de
réinitialisation, silence sur adresse inconnue, réinitialisation effective, et refus d'un
jeton forgé.

**Trois particularités de cette application découvertes en écrivant ces tests :**

- `/logout` est une route **GET**, pas le POST que Laravel génère par défaut.
- La réinitialisation **n'utilise pas** la Notification de Laravel : `User::sendPasswordResetNotification()`
  met en file un Mailable maison, `ResetPasswordMail`. Un `Notification::fake()` ne voit donc
  rien — il faut `Mail::fake()`.
- **`RedirectIfAuthenticated` teste `teacher !== null` AVANT le type admin.** Un utilisateur
  portant à la fois `type = admin` et un `teacher_id` serait envoyé dans l'espace enseignant
  quoi qu'en dise son type. Ce n'est pas un défaut en production — un vrai administrateur n'a
  pas de fiche enseignant — mais la factory `User` en attache une à tout le monde, ce qui
  rendait ma fixture irréaliste. À garder en tête si une fiche admin héritait un jour d'un
  `teacher_id`.

**Couverture des routes en écriture : 9/25 → 12/25.** Les 11 restantes (documents, écoles,
profils, réponses fête admin, settings, ajout d'utilisateur) partagent une propriété qui
justifie de les laisser : **leur panne est bruyante**. Un admin qui clique et voit une erreur
la signale. Le critère retenu tout au long de cette migration reste le même — couvrir en
priorité ce qui casse en silence.

---

### D-57 · Un cache de configuration périmé, et un diagnostic trompeur
**Premier `deploy:check` réel en staging.** Il signalait `APP_URL` en `http://` et des
identifiants mail manquants, alors que le `.env` du serveur contenait bien
`https://mnr-staging.quattro.dev`. Le symptôme décisif était ailleurs dans le tableau :
`APP_ENV` affichait **`production`** quand le `.env` disait `staging`.

Cause : `php artisan config:cache` compile le `.env` dans `bootstrap/cache/config.php`, et
l'application **ne relit plus jamais le `.env`**. Forge met la configuration en cache à chaque
déploiement ; toute modification du `.env` faite ensuite via l'interface Forge laisse
exactement cet état. Correction sur le serveur : `config:clear && config:cache`.

**Le vrai défaut était dans ma commande.** Elle rapportait fidèlement les conséquences —
mauvaise URL, identifiants absents — et envoyait chercher au mauvais endroit. Un contrôle
`Cache de configuration` s'exécute désormais **en premier** : il relit le `.env` du disque,
le compare aux valeurs effectives sur six clés témoins, et nomme celles qui divergent avec la
commande à lancer. Vérifié en reproduisant le scénario en local — `.env` modifié après un
`config:cache` — la ligne apparaît en tête et pointe la vraie cause.

Le `.env` est lu à la main plutôt qu'en rechargeant Dotenv : le recharger muterait
l'environnement que la commande est précisément en train de juger. Les valeurs interpolées
(`"${APP_NAME}"`) sont ignorées pour la même raison.

**Pas de test automatisé pour ce contrôle, délibérément.** Il faudrait écrire un
`bootstrap/cache/config.php` pendant la suite ; un plantage entre l'écriture et le nettoyage
laisserait le dépôt avec une configuration figée — exactement la panne silencieuse que ce
contrôle existe pour attraper. La branche « cache absent » est couverte par les tests
existants ; la branche « périmé » a été vérifiée à la main, ci-dessus.

---

### D-58 · Queue sur Redis — et l'angle mort que ça révélait
**Staging passe en `QUEUE_CONNECTION=redis`.** Deux conséquences.

**La configuration est correcte telle quelle.** `config/database.php` déclare
`'client' => env('REDIS_CLIENT', 'phpredis')`, et `predis` a été retiré au hop 3 (D-05) :
`phpredis` est donc la seule valeur qui fonctionne. **Ne pas surcharger `REDIS_CLIENT`** —
une valeur `predis` casserait toute la file sans que rien ne l'explique.

**Le défaut était dans `deploy:check`.** `checkQueue()` sortait immédiatement dès que la
connexion n'était pas `database` — donc sur Redis, **ni contrôle du worker, ni contrôle des
jobs en échec**. La commande affichait un bilan vert alors qu'elle n'avait rien vérifié.
Exactement le mode de panne qu'elle existe pour attraper, dans la commande elle-même.

Trois contrôles désormais :

- **Redis joignable** — un `ping()`. C'est la panne la plus probable, et la plus totale.
- **Profondeur de file** — Redis ne porte aucun horodatage de mise en file, donc l'âge du plus
  ancien job est illisible. Rapporté comme tel plutôt que déguisé en preuve de vitalité :
  un worker actif vide la file en quelques secondes, il suffit de relancer pour voir le
  nombre descendre.
- **Jobs en échec** — sorti de la branche `database`, il s'exécute maintenant quel que soit
  le driver, puisque `failed_jobs` reste en MySQL (`config/queue.php`). Ce sont des **pertes
  silencieuses** : le constructeur de `CustomEmail` marque le mail comme envoyé, donc le
  registre interdira tout renvoi.

**Rappel opérationnel Forge** : worker et ordonnanceur sont deux mécanismes distincts — le
scheduler *met en file*, le worker *envoie*. Et `php artisan queue:restart` doit figurer dans
le script de déploiement : un worker est un processus long qui garde l'ancien code en mémoire.

---

### D-59 · Deux commandes d'exploitation : `queue:test` et `admin:create`
**`php artisan queue:test [--mail=adresse]`.** `deploy:check` ne peut dire que « la file est
vide » — ce qui est vrai d'un worker en bonne santé comme d'un worker inexistant. Cette
commande dépose un job et attend qu'il ressorte : c'est la seule façon de distinguer les deux.

Le job ne touche **aucune donnée métier**, délibérément : tester la file en déclenchant une
vraie fonctionnalité mêle « le worker tourne-t-il » et « cette fonctionnalité marche-t-elle »,
et un échec ne dit alors plus laquelle. Avec `--mail`, le worker envoie **depuis son propre
processus** — la combinaison qui compte ici, puisqu'un worker qui tourne mais ne joint pas le
SMTP ne délivre rien tout en paraissant sain. L'échec d'envoi est capturé et rapporté plutôt
que relancé : c'est la réponse cherchée, pas un incident.

En cas d'échec, la commande énumère les trois causes par fréquence — pas de worker, worker sur
une autre file, worker exécutant l'ancien code faute de `queue:restart` au déploiement.
Vérifiée dans les deux sens : sans worker elle échoue, avec un worker elle passe en 2,0 s.

**`php artisan admin:create [email]`.** Le mot de passe est demandé **interactivement**, jamais
en argument : une ligne de commande atterrit dans l'historique du shell et dans la liste des
processus, lisible par tout autre compte de la machine.

Le point non évident est ailleurs : le compte est créé avec **`teacher_id` à `null`**, et c'est
indispensable. `RedirectIfAuthenticated` teste la présence d'une fiche enseignant **avant** le
type admin (D-56), donc un administrateur en portant une serait renvoyé dans l'espace
enseignant et n'atteindrait **jamais** l'administration. Un test vérifie explicitement que le
compte créé arrive bien sur `admin.classes`.

---

### D-60 · B-06 · une alerte DataTables sur la première page de l'admin
**Signalé depuis staging, juste après la création du premier compte administrateur.** Une
boîte `alert()` bloquante : *« DataTables warning: Incorrect column count »*, sur
`/admin/classes` — la page d'atterrissage après connexion.

**Bug préexistant, révélé par une base vide.** Sept vues rendaient leur ligne « aucune donnée »
sous la forme d'un `<td colspan="N">` unique. DataTables ne sait pas faire correspondre une
cellule fusionnée à ses colonnes et abandonne. Le défaut ne se manifeste **que lorsque la table
est vide** — d'où sa survie pendant des années en production, et son apparition immédiate sur
un staging fraîchement migré.

Les valeurs étaient fausses de toute façon : `admin/classes` codait `colspan="16"` pour une
table dont la largeur vaut 16 **plus une colonne par quiz**.

Correction : supprimer ces lignes et laisser DataTables rendre son propre état vide, avec les
libellés français posés dans `resources/js/app.js` — l'interface de cette application est
intégralement en français, la traduction devait suivre. Les trois vues dont la table n'est
**pas** un DataTable (`admin/emails`, `external/classes`, `teacher/classes-list`) gardent leur
`colspan` : il y est valide.

**Vérifié dans le navigateur, pas seulement en compilant :** `/admin/documents`, table vide,
affiche « Aucune donnée disponible » sans aucune alerte ; `/admin/classes` avec 4 lignes
rapporte 16 colonnes dont 4 masquées par `columnDefs`, ce qui est cohérent.

`DataTableMarkupTest` découvre les vues initialisant un DataTable plutôt que d'en tenir la
liste — une nouvelle vue est couverte le jour où elle est écrite — et refuse tout `<td colspan>`
écrit à la main. Vérifié en échec sur le code d'avant.

**Au passage, une erreur de méthode de ma part :** ma première vérification syntaxique des
sept vues affichait « OK » pour toutes alors qu'elle ne testait rien — `php -l` s'exécutait sur
un fichier vide, l'erreur réelle se produisant dans le sous-processus. Remplacée par
`php artisan view:cache`, qui compile réellement l'ensemble des vues.

---

### D-61 · B-07 · `/admin/emails` en 500 sur une base neuve
**Signalé depuis staging.** `Attempt to read property "label" on null` : la vue faisait
`{{ $email->dates->first()->label }}`, et `first()` renvoie `null` pour un mail sans date.

**Reproduit plutôt que déduit.** Base fraîchement migrée : **19 mails, dont 9 sans aucune
date**, et **11 clés de dates semées sur 23**. Huit des neuf orphelins sont les mails de suivi
janvier/mars — le mécanisme volontairement désactivé mais conservé (`CLAUDE.md`) — plus
`newsletter_encouragement`. En local le défaut est invisible : les données importées de
production ont tous leurs liens.

C'est la même racine que D-44 : un `migrate` neuf ne construit pas un jeu de données complet.
Ça ne mordra pas à la bascule, qui importe la production — mais ça mord sur **tout nouveau
serveur**, ce que staging vient de démontrer.

Correction : repli sur `$email->title`, colonne déjà présente et renseignée, plutôt que sur la
clé technique. La ligne reste lisible au lieu d'être fatale. La ligne voisine utilisait déjà
`optional()` pour la date d'envoi — l'incohérence était dans la même vue.

**Le vrai enseignement est côté tests.** `RefreshDatabase` migre exactement de la même façon :
la condition d'échec était présente dans la suite depuis le début, personne n'avait ouvert la
page. La couverture des routes GET était de **8 sur 67**. `AdminPagesRenderTest` ouvre
désormais les **10 pages d'administration** sur base neuve, et un test nommé isole la
régression précise. Vérifié en échec sur le code d'avant.

**Au passage :** PHPUnit 12 ne lit plus l'annotation `@dataProvider`, seul l'attribut
`#[DataProvider]` est reconnu.

---

### D-62 · Les routes GET, ouvertes avec des données derrière
**Suite de D-61.** Couverture des routes GET portée de **8/67 à 40/67**, en deux tests
complémentaires : `AdminPagesRenderTest` ouvre les pages sur base **vide**,
`GetRoutesRenderTest` les ouvre **avec** une classe, un quiz, un document, un certificat et un
groupe de fête. Les deux moitiés comptent — `/admin/quiz` est parfaitement heureux tant que la
table est vide et meurt sur la première ligne.

**Méthode : un balayage jetable d'abord, les tests ensuite.** Une sonde a appelé toutes les
routes GET et listé les non-200. Trois faux positifs, tous instructifs :

- **Cinq 500 dus à `closes_at` à `null`** — *ma fixture*, pas l'application. `Quiz::create`
  dans le trait omettait la date, que les deux formulaires admin valident comme obligatoire.
  La colonne est `nullable` en base mais jamais nulle en pratique. Corriger la vue aurait été
  corriger un état que l'application ne sait pas produire.
- **Trois 404 « No query results for SchoolClass 1 »** — la sonde appelait les routes de
  **suppression**, qui sont en GET, et détruisait ses propres fixtures avant d'atteindre les
  pages suivantes.
- **Deux exceptions sur les exports** — `StreamedResponse` n'a pas de `status()`.

**Aucune panne applicative nouvelle.** C'est le résultat honnête : D-61 était bien le défaut,
et le reste tient debout une fois les fixtures correctes.

**Mais le balayage a mis au jour autre chose.** Douze routes **GET** changent l'état ou
envoient du courrier : suppression de classe, de quiz, de document, de certificat, et
`admin.quiz.send`, `send-reminder`, `admin.certificates.send`, `admin.classes.resend`. Un
préchargement de navigateur, un robot d'indexation ou une URL mal tapée les exécute, et **GET
est exempt de CSRF par conception**. Un GET qui écrit à tous les enseignants est plus grave
qu'un GET qui supprime.

Les passer en POST touche toutes les vues qui les référencent : c'est une décision, pas un
détail à glisser dans une passe de tests. `testDestructiveActionsAreReachableByGet` **fige la
liste actuelle** pour que le jour où quelqu'un la corrige, le test le dise.

**Détail de fixture consigné :** plusieurs pages enseignant sont fermées par les
`EditableDate` et redirigent en 302. Sans ouvrir ces fenêtres, le test serait passé sur une
assertion plus faible en laissant la vue non exercée.

---

---

## 4. Défauts constatés, volontairement non corrigés

Caractérisés par des tests pour que la migration signale tout changement, mais **laissés
tels quels** : les corriger pendant la montée mélangerait les signaux.

| # | Défaut | Où | Note |
|---|---|---|---|
| ~~B-01~~ | **Corrigé** — voir D-45. | | |
| ~~B-02~~ | **Corrigé** — voir D-46. | | |
| ~~B-03~~ | **Corrigé au hop 2** — voir D-12. | | |
| ~~B-04~~ | **Corrigé** — voir D-49. | | |
| ~~B-05~~ | **Corrigé** — voir D-55. | | |
| ~~B-06~~ | **Corrigé** — voir D-60. | | |
| ~~B-07~~ | **Corrigé** — voir D-61. | | |

---

## 5. En attente / bloqué

**Fournisseur de mail.** **Provisoirement SparkPost en SMTP** (D-48), le temps que le
client publie le DNS de Scaleway. Cible inchangée : **Scaleway TEM** — 0–1,20 €/an pour ~5 000 mails, stockage
entièrement en UE (fr-par), société française, SMTP `smtp.tem.scaleway.com`.
Resend a été écarté sur son argument principal : sa « région UE » ne couvre **que
l'envoi**, le contenu et les logs restant aux États-Unis. Voir aussi : Mailjet (UE, mais
plafond 200/jour sur le gratuit), MailPace (UE, zéro tracking).

**Millésime du certificat.** Reporté à l'édition suivante : le certificat se met en place
vers la fin de l'édition, pas à la migration. Concerne les deux `$text` codés en dur et leurs
`SetXY` dans `CertificateService::generateCertificate()`, plus le choix du fond parmi
`public/images/pdf/*-certificate-bg.jpg`. Déjà listé au *Yearly rollover* du `CLAUDE.md`.

**Séparation bulk / transactionnel** (Phase 0 §7). Réduite à : ajouter les en-têtes
`List-Unsubscribe` aux **4 annonces identiques** (`newsletter_start`,
`newsletter_encouragement`, `new_educational_tool`, `end_year_communication_email`) et un
drapeau d'opt-out. Tout le reste est transactionnel (jeton unique par destinataire).

---

## 6. À faire avant la production

**`php artisan deploy:check` répond à la majorité de cette liste** (D-53). Sortie non nulle
= ne pas livrer. À lancer après chaque déploiement, pas seulement à la bascule.

Vérifié automatiquement :

- [x] `APP_DEBUG` désactivé — sinon la moindre erreur affiche l'environnement entier,
      identifiants de base et mot de passe mail compris
- [x] `APP_KEY` présente
- [x] `APP_URL` en `https://` et pas un domaine local
- [x] Base connectée, migrations toutes appliquées
- [x] Transport mail réel, identifiants complets, TLS exigé
- [x] `MAIL_ALWAYS_TO` **non défini** — sinon tout le courrier du concours est détourné
- [x] Worker de queue vivant (mesuré à l'âge du plus ancien job en attente)
- [x] Ordonnanceur vivant (mesuré aux quiz dépassés et toujours ouverts)
- [x] `storage/app/{certificats,documents,quiz-maker-hooks}` accessibles en écriture
- [x] Driver de session ≠ `array`
- [x] `MNR_MIN_QUIZ_RESPONSES` affiché (aucune valeur juste à imposer : elle doit
      correspondre au nombre de quiz de l'édition)

Restant manuel :

- [ ] Transférer `storage/app/certificats/` (1,7 Mo en local) et `storage/app/documents/`
- [ ] `APP_KEY` **copiée** depuis l'ancien serveur, jamais régénérée
- [ ] Bascule dans la fenêtre d'été, entre le mail de fin d'année et l'ouverture des
      inscriptions

### Trois affirmations de cette liste étaient fausses ou imprécises

**Le schéma des liens ne vient pas de `fastcgi_param`.** C'était écrit ici, et c'est vrai
uniquement pour les requêtes web. Or les mails sont construits par les **workers de queue**,
qui n'ont aucune requête HTTP : `SetRequestForConsole` en fabrique une à partir de
**`APP_URL`** au démarrage. Vérifié en variant `APP_URL` à l'exécution — c'est elle, et elle
seule, qui décide du schéma de chaque lien certificat, fête et quiz envoyé par mail.
`deploy:check` contrôle donc `APP_URL`, pas la configuration nginx.

**Régénérer `APP_KEY` ne détruirait pas de données.** L'application ne chiffre rien qui lui
soit propre : aucun `Crypt::`, aucun cast `encrypted`, aucune route signée — vérifié. Le coût
se limite aux sessions et aux cookies, soit une déconnexion générale. À copier quand même,
mais la conséquence était surestimée ici.

**Tronquer `sessions` à l'import n'est pas nécessaire.** Le motif avancé — « Laravel 13
sérialise en JSON » — est faux : `Session\Store::$serialization` vaut `'php'` par défaut et
`config/session.php` ne le surcharge pas, exactement comme en 5.7. Tronquer reste anodin,
mais un mauvais motif dans un runbook est un piège.
