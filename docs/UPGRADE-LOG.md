# Journal de migration — Laravel 5.7 → 13

> **À quoi sert ce fichier.** Il enregistre l'état de la migration et **les décisions
> prises**, avec leur raison. La roadmap dit où l'on va ; ce journal dit où l'on en est
> et pourquoi les choses ont été faites ainsi.
>
> Il est mis à jour **à la fin de chaque étape**. Si une session de travail est perdue,
> ce fichier plus `git log` suffisent à reprendre.

**Dernière mise à jour :** 21 août 2026 · branche `upgrade/phase-0` · **hop 6/9 fait**

---

## 1. État actuel

| | Aujourd'hui | Cible |
|---|---|---|
| Laravel | **10.50.3** | 13.x |
| PHP (application) | **8.1.34** | 8.5 |
| PHP (résolution composer) | `config.platform.php` = **8.1.0** | 8.3+ |
| Base de données | MySQL 8.0.33 (dev) · 5.7.31 (prod) — **schéma vérifié** | 8.4 LTS |
| Serveur | actuel (Hetzner) | Ubuntu 26.04 + Forge |
| Suite de tests | **60 tests / 149 assertions — verte** | — |
| Production | **toujours en 5.7.29 — rien n'a été déployé** | — |

### Reprendre le travail

```bash
git checkout upgrade/phase-0
composer test                 # passe par bin/test, qui épingle le binaire PHP
MNR_PHP=php82 composer test   # pour changer de version PHP après un bump
```

`bin/test` épingle la version PHP du hop en cours (`MNR_PHP`, défaut **`php81`**) parce que
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
| 7 | 10.0 → 11.0 | **8.2** | ⏭️ suivant |
| 8 | 11.0 → 12.0 | 8.2 | — |
| 9 | 12.0 → 13.0 | **8.3 → 8.5** | — |

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

> **À faire avant toute mise en production :** remettre `block` à `true`, lancer
> `composer audit`, obtenir un résultat propre. `AdvisoryPolicyTest` fait échouer le
> build si Laravel 13 est atteint avec le blocage encore désactivé.

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

---

## 4. Défauts constatés, volontairement non corrigés

Caractérisés par des tests pour que la migration signale tout changement, mais **laissés
tels quels** : les corriger pendant la montée mélangerait les signaux.

| # | Défaut | Où | Note |
|---|---|---|---|
| B-01 | Un uid de certificat inconnu renvoie **500 au lieu de 404**, sur une route publique non authentifiée. `->first()` déréférencé sans garde. | `CertificateController::downloadCertificate()` | `CertificateDownloadTest`. Chemin le plus exposé au passage Flysystem 3. |
| ~~B-03~~ | **Corrigé au hop 2** — voir D-12. | | |
| B-02 | Le webhook fait **`return` et non `continue`** sur un code déjà enregistré : un rejeu contenant un résultat connu **abandonne tous les suivants**. | `Api\QuizController` | `QuizMakerWebhookTest`. Vrai bug, correctif à prévoir après la migration. |

---

## 5. En attente / bloqué

**Fournisseur de mail.** Décision en cours côté hiérarchie. Ne bloque **aucun** hop (voir
D-04). Recommandation : **Scaleway TEM** — 0–1,20 €/an pour ~5 000 mails, stockage
entièrement en UE (fr-par), société française, SMTP `smtp.tem.scaleway.com`.
Resend a été écarté sur son argument principal : sa « région UE » ne couvre **que
l'envoi**, le contenu et les logs restant aux États-Unis. Voir aussi : Mailjet (UE, mais
plafond 200/jour sur le gratuit), MailPace (UE, zéro tracking).

**Séparation bulk / transactionnel** (Phase 0 §7). Réduite à : ajouter les en-têtes
`List-Unsubscribe` aux **4 annonces identiques** (`newsletter_start`,
`newsletter_encouragement`, `new_educational_tool`, `end_year_communication_email`) et un
drapeau d'opt-out. Tout le reste est transactionnel (jeton unique par destinataire).

---

## 6. À faire avant la production

- [ ] Remettre `config.policy.advisories.block` à `true` · `composer audit` propre (**D-01**)
- [ ] Pointer le `.env` de production vers un hôte SMTP (**D-04**)
- [ ] Millésime du certificat codé en dur dans `NewCertificateService::generateCertificate()`
- [ ] `MNR_MIN_QUIZ_RESPONSES` conforme au nombre de quiz de l'édition
- [ ] Transférer `storage/app/certificats/` et `storage/app/documents/`
- [ ] `APP_KEY` **copiée, jamais régénérée**
- [ ] Worker de queue + scheduler actifs (sans worker, **aucun mail ne part**)
- [ ] Vérifier que les URL générées sont bien en `https://` (schéma transmis par `fastcgi_param`, pas par en-tête proxy)
- [ ] Bascule dans la fenêtre d'été, entre le mail de fin d'année et l'ouverture des
      inscriptions
