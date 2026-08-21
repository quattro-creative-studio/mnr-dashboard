# Journal de migration — Laravel 5.7 → 13

> **À quoi sert ce fichier.** Il enregistre l'état de la migration et **les décisions
> prises**, avec leur raison. La roadmap dit où l'on va ; ce journal dit où l'on en est
> et pourquoi les choses ont été faites ainsi.
>
> Il est mis à jour **à la fin de chaque étape**. Si une session de travail est perdue,
> ce fichier plus `git log` suffisent à reprendre.

**Dernière mise à jour :** 21 août 2026 · branche `upgrade/phase-0` · hop 1/9 fait

---

## 1. État actuel

| | Aujourd'hui | Cible |
|---|---|---|
| Laravel | **5.8.38** | 13.x |
| PHP (application) | **7.4.33** | 8.5 |
| PHP (résolution composer) | `config.platform.php` = **7.4.33** | 8.3+ |
| Base de données | MySQL 8.0.33 (dev) · 5.7.31 (prod) — **schéma vérifié** | 8.4 LTS |
| Serveur | actuel (Hetzner) | Ubuntu 26.04 + Forge |
| Suite de tests | **57 tests / 141 assertions — verte** | — |
| Production | **toujours en 5.7.29 — rien n'a été déployé** | — |

### Reprendre le travail

```bash
git checkout upgrade/phase-0
composer test                 # passe par bin/test, qui épingle le binaire PHP
MNR_PHP=php80 composer test   # pour changer de version PHP après un bump
```

`bin/test` épingle la version PHP du hop en cours (`MNR_PHP`, défaut `php74`) parce que
le PHP par défaut de la machine est 8.5, sur lequel Laravel 5.x ne démarre pas.

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
| 2 | 5.8 → 6.0 | 7.4 | ⏭️ suivant |
| 3 | 6.0 → 7.0 | 7.4 | — |
| 4 | 7.0 → 8.0 | 7.4 | — |
| 5 | 8.0 → 9.0 | **8.0.2** | — |
| 6 | 9.0 → 10.0 | **8.1** | — |
| 7 | 10.0 → 11.0 | **8.2** | — |
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

---

## 4. Défauts constatés, volontairement non corrigés

Caractérisés par des tests pour que la migration signale tout changement, mais **laissés
tels quels** : les corriger pendant la montée mélangerait les signaux.

| # | Défaut | Où | Note |
|---|---|---|---|
| B-01 | Un uid de certificat inconnu renvoie **500 au lieu de 404**, sur une route publique non authentifiée. `->first()` déréférencé sans garde. | `CertificateController::downloadCertificate()` | `CertificateDownloadTest`. Chemin le plus exposé au passage Flysystem 3. |
| B-02 | Le webhook fait **`return` et non `continue`** sur un code déjà enregistré : un rejeu contenant un résultat connu **abandonne tous les suivants**. | `Api\QuizController` | `QuizMakerWebhookTest`. Vrai bug, correctif à prévoir après la migration. |
| B-03 | `route('follow-up', [... 'status' => …])` ne correspond pas au paramètre déclaré `{stillNonSmoking}`. Laravel 5.7 comble par position. | `PlaceHolder::getReplacement()` | `PlaceHolderTest`. Tolérance du générateur d'URL, pas conception. |

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
- [ ] Ne pas reporter `'proxies' => '*'` sur Forge
- [ ] Bascule dans la fenêtre d'été, entre le mail de fin d'année et l'ouverture des
      inscriptions
