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

Le blocage n'est donc pas technique. Mix concatène TinyMCE avec le JS applicatif dans un
`app.js` unique servi au navigateur : la question de l'œuvre combinée sous GPLv2+ se pose
réellement, et elle appartient à la Fondation. La branche est prête à être adoptée ou
abandonnée d'un seul coup.

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

- [ ] Pointer le `.env` de production vers un hôte SMTP (**D-04**)
- [ ] `MNR_MIN_QUIZ_RESPONSES` conforme au nombre de quiz de l'édition
- [ ] Transférer `storage/app/certificats/` et `storage/app/documents/`
- [ ] `APP_KEY` **copiée, jamais régénérée**
- [ ] Worker de queue + scheduler actifs (sans worker, **aucun mail ne part**)
- [ ] Vérifier que les URL générées sont bien en `https://` (schéma transmis par `fastcgi_param`, pas par en-tête proxy)
- [ ] Bascule dans la fenêtre d'été, entre le mail de fin d'année et l'ouverture des
      inscriptions
