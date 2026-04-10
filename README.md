# AMEA — Plateforme de gestion AEESGS

Plateforme web de gestion des membres de l'**Association des Étudiants et Élèves Sénégalais en Guinée et Sous-région (AEESGS)**. Elle couvre l'inscription des membres, la gestion des dossiers, les communications, les exports et les outils d'administration.

---

## Table des matières

1. [Stack technique](#stack-technique)
2. [Prérequis](#prérequis)
3. [Installation locale](#installation-locale)
4. [Variables d'environnement](#variables-denvironnement)
5. [Structure du projet](#structure-du-projet)
6. [Base de données](#base-de-données)
7. [Authentification & sessions](#authentification--sessions)
8. [Pages publiques](#pages-publiques)
9. [Pages admin](#pages-admin)
10. [Système d'email](#système-demail)
11. [Fonctionnalités détaillées](#fonctionnalités-détaillées)
12. [Tests](#tests)
13. [Déploiement (production)](#déploiement-production)
14. [Sécurité](#sécurité)

---

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.x (PDO, fileinfo, GD) |
| Base de données | MySQL 8.0 (charset utf8mb4) |
| Frontend | Bootstrap 5, JavaScript vanilla, Tagify |
| Email | PHPMailer 6.x via Brevo SMTP |
| Templating | Système maison (`strtr()` + placeholders `{{...}}`) |
| Dépendances | Composer (`phpmailer/phpmailer ^6.9`) |
| Serveur | Apache/LiteSpeed avec `.htaccess` |

---

## Prérequis

- PHP 8.0+ avec les extensions : `pdo_mysql`, `fileinfo`, `mbstring`, `gd`
- MySQL 8.0+
- Composer
- Serveur web Apache ou LiteSpeed (mod_rewrite activé)
- Compte **Brevo** (ex-Sendinblue) pour l'envoi d'emails SMTP

---

## Installation locale

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> amea
cd amea

# 2. Installer les dépendances PHP
composer install

# 3. Créer le fichier d'environnement
cp .env.example .env
# Éditer .env avec les identifiants locaux (voir section ci-dessous)

# 4. Créer la base de données et charger le schéma
mysql -u root -p -e "CREATE DATABASE amea_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p amea_db < schema.sql

# 5. Créer le premier compte administrateur
mysql -u root -p amea_db <<'SQL'
INSERT INTO users (username, password, nom, prenom, email, role, est_actif)
VALUES ('admin', '$2y$12$REMPLACER_PAR_HASH', 'Admin', 'Système', 'admin@aeesgs.org', 'admin', 1);
SQL
# Générer un hash PHP : php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"

# 6. Créer les dossiers d'upload et de logs
mkdir -p uploads/students/cvs uploads/slider logs
chmod -R 755 uploads logs

# 7. Démarrer le serveur de développement
php -S localhost:8000
```

Accès :
- Accueil : `http://localhost:8000`
- Connexion admin : `http://localhost:8000/login.php`

---

## Variables d'environnement

Fichier `.env` à la racine du projet :

```dotenv
# Base de données
DB_HOST=127.0.0.1
DB_NAME=amea_db
DB_USER=root
DB_PASS=secret

# Email SMTP (Brevo)
MAIL_USER=votre_login@brevo.com
MAIL_PASS=votre_cle_api_smtp_brevo

# URL de base (utilisée dans les liens des emails)
APP_URL=https://aeesgs.org
```

> **En production** : ne jamais committer `.env`. Le fichier est dans `.gitignore`.

---

## Structure du projet

```
amea/
├── .env                        # Variables d'environnement (non versionné)
├── .htaccess                   # Sécurité Apache : HTTPS, blocage VCS, headers
├── schema.sql                  # Schéma complet de la base de données
├── composer.json
│
├── config/
│   ├── database.php            # Connexion PDO (charge .env, UTF-8)
│   └── session.php             # Config session : durée 30 min, cookies sécurisés
│
├── functions/
│   ├── utility-functions.php   # env(), CSRF, flash messages, upload, export, versioning
│   ├── email-service.php       # sendMail() + renderEmailTemplate() via Brevo SMTP
│   └── document-reconcile.php  # Helpers de réconciliation des chemins de fichiers
│
├── includes/
│   └── sidebar.php             # Sidebar admin (navigation)
│
├── migrations/                 # Scripts de migration (CLI uniquement)
│
├── templates/
│   ├── index.html              # Accueil public
│   ├── login.html
│   ├── register.html
│   ├── partials/               # header.html, footer.html (public)
│   ├── admin/
│   │   ├── layout.html         # Wrapper de page admin
│   │   ├── partials/           # topbar.html, footer.html (admin)
│   │   └── pages/              # Un fichier HTML par page admin
│   └── emails/                 # Gabarits d'emails HTML
│
├── assets/
│   ├── css/                    # Bootstrap + CSS personnalisés
│   ├── js/                     # Scripts front-end
│   ├── img/                    # Logos, icônes
│   └── json/                   # Données statiques (liste des pays, etc.)
│
├── uploads/
│   ├── slider/                 # Images du carrousel d'accueil
│   └── students/               # Photos et CVs des étudiants
│       └── cvs/                # CVs (PDF/PNG)
│
├── tests/                      # Tests CLI PHP
├── migrations/                 # Migrations SQL incrémentales
└── logs/                       # Journaux d'erreurs
```

---

## Base de données

### Tables principales

#### `personnes` — Dossiers des membres
| Colonne | Type | Description |
|---|---|---|
| `id_personne` | INT PK | Identifiant unique |
| `nom`, `prenom` | VARCHAR | Nom complet |
| `sexe` | ENUM | `Masculin` / `Féminin` |
| `email` | VARCHAR UNIQUE | Adresse email |
| `telephone` | VARCHAR UNIQUE | Numéro de téléphone |
| `statut` | ENUM | `ELEVE` / `ETUDIANT` / `STAGIAIRE` / `Diplômé` |
| `etablissement` | VARCHAR | Établissement d'inscription |
| `niveau_etudes` | VARCHAR | Niveau actuel (FK logique vers `niveaux_etudes`) |
| `nationalites` | JSON | Liste des nationalités |
| `identite` | VARCHAR | Chemin relatif de la pièce d'identité |
| `cv_path` | VARCHAR | Chemin relatif du CV |
| `date_enregistrement` | DATETIME | Date d'inscription |
| `date_diplomation` | DATE | Date de diplomation (si statut = Diplômé) |
| `is_locked` | TINYINT | Dossier finalisé (1) ou modifiable (0) |
| `consent_privacy` | TINYINT | CGU acceptées (1) ou non (0) |
| `consent_privacy_date` | DATETIME | Date d'acceptation des CGU |
| `cgu_token` | VARCHAR(64) | Token unique pour lien d'acceptation CGU |
| `cgu_reminder_sent_at` | DATETIME | Dernier rappel CGU envoyé |
| `consent_refused_at` | DATETIME | Date de refus des CGU |
| `deletion_requested_at` | DATETIME | Date de demande de suppression |

#### `users` — Comptes administrateurs
| Colonne | Type | Description |
|---|---|---|
| `id_user` | INT PK | |
| `username` | VARCHAR UNIQUE | Identifiant de connexion |
| `password` | VARCHAR | Hash bcrypt |
| `role` | ENUM | `admin` / `utilisateur` |
| `est_actif` | TINYINT | Compte actif (1) ou désactivé (0) |
| `derniere_connexion` | DATETIME | Dernière connexion |

#### Autres tables

| Table | Description |
|---|---|
| `etablissements` | Établissements d'enseignement |
| `domaines_etudes` | Domaines d'études disponibles |
| `niveaux_etudes` | Niveaux d'études (Seconde → Doctorat, BTS, DUT…) |
| `slider_images` | Images du carrousel de la page d'accueil |
| `pending_level_upgrades` | Confirmations de passage de niveau (workflow email) |
| `communications` | Historique des campagnes email envoyées |
| `settings` | Configuration système (email contact, téléphone…) |
| `password_resets` | Tokens de réinitialisation de mot de passe |

### Migrations

Les migrations sont des scripts PHP CLI idempotents dans `migrations/`. Ils ajoutent des colonnes ou tables sans recréer le schéma existant. À exécuter en cas de mise à jour du schéma :

```bash
php migrations/migration_add_consent_privacy.php
php migrations/migration_add_is_locked.php
# etc.
```

> La plupart des fonctionnalités récentes utilisent des `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` auto-guérisants directement dans les pages PHP — aucune migration manuelle n'est requise.

---

## Authentification & sessions

- **Connexion** : vérification `password_verify()` + `session_regenerate_id(true)` après succès
- **Rôles** : `admin` (accès total) / `utilisateur` (accès restreint)
- **Durée de session** : 30 minutes d'inactivité
- **Cookies** : `HttpOnly`, `Secure`, `SameSite=Strict`
- **CSRF** : token `bin2hex(random_bytes(32))` vérifié via `hash_equals()` sur tous les formulaires POST

---

## Pages publiques

| Page | Description |
|---|---|
| `index.php` | Accueil avec carrousel dynamique |
| `register.php` | Formulaire d'inscription des membres (validation, upload photo/CV, consentement) |
| `login.php` | Connexion administrateur |
| `forgot-password.php` | Demande de réinitialisation de mot de passe |
| `reset-password.php` | Réinitialisation avec token |
| `legal-notice.php` | Mentions légales et politique de confidentialité (CGU) |
| `accept-cgu.php` | Acceptation/refus des CGU via lien tokenisé (email) |
| `registration-details.php` | Finalisation du dossier par le membre |
| `confirm-upgrade.php` | Confirmation d'un passage de niveau via lien email |
| `download.php` | Téléchargement sécurisé des documents d'un étudiant |

---

## Pages admin

Toutes les pages admin nécessitent une session avec `user_id` défini. Les pages marquées **(admin only)** nécessitent `role = 'admin'`.

| Page | Description |
|---|---|
| `dashboard.php` | Tableau de bord : KPIs, graphiques genre/statut/établissements |
| `students.php` | Liste des membres avec recherche, filtres, pagination |
| `student-details.php` | Fiche détaillée d'un membre (lecture seule) |
| `edit-student.php` | Modification du dossier d'un membre |
| `export.php` | Export CSV/JSON/PDF avec sélection de champs et filtres |
| `export-preview.php` | Aperçu avant export |
| `upgrade-levels.php` **(admin)** | Outil de passage de niveau annuel (auto ou par email) |
| `communications.php` **(admin)** | Campagnes CGU + emails de groupe |
| `communications-count.php` | Endpoint AJAX : compte les destinataires selon filtres |
| `reconcile-documents.php` **(admin)** | Réconciliation des chemins de fichiers corrompus |
| `manage-slider.php` **(admin)** | Gestion du carrousel d'accueil |
| `users.php` **(admin)** | Gestion des comptes administrateurs |
| `add-user.php` **(admin)** | Créer un compte admin |
| `edit-user.php` **(admin)** | Modifier un compte admin |
| `settings.php` **(admin)** | Paramètres système (email, téléphone, nom organisation) |
| `profile.php` | Modifier son propre profil admin |

---

## Système d'email

**Configuration** (`functions/email-service.php`) :

| Paramètre | Valeur |
|---|---|
| Serveur SMTP | `smtp-relay.brevo.com` |
| Port | `587` (STARTTLS) |
| Expéditeur | `no-reply@aeesgs.org` |
| Nom affiché | `AEESGS` |
| Charset | `UTF-8` |

**Fonctions disponibles** :

```php
sendMail(string $to, string $subject, string $body): bool
renderEmailTemplate(string $templatePath, array $data): string
```

**Gabarits d'emails** (`templates/emails/`) :

| Fichier | Usage |
|---|---|
| `registration-confirmation.html` | Envoyé après inscription |
| `registration-finalized.html` | Envoyé après finalisation du dossier |
| `cgu-reminder.html` | Rappel CGU avec lien d'acceptation tokenisé |
| `bulk-communication.html` | Email générique de campagne (supporte `{{prenom}}`, `{{nom}}`) |
| `grade-upgrade-email.html` | Demande de confirmation de passage de niveau |
| `new-registration-admin.html` | Notification admin — nouvelle inscription |
| `admin-update-notification.html` | Notification admin — dossier modifié |
| `password-reset-email.html` | Réinitialisation de mot de passe |

---

## Fonctionnalités détaillées

### Inscription des membres

1. Le membre remplit le formulaire (`register.php`) : informations personnelles, établissement, niveau, logement, pièce d'identité (photo), CV (PDF)
2. Validation serveur : unicité email/téléphone, type MIME des fichiers, taille max
3. Email de confirmation envoyé automatiquement
4. Le membre peut modifier son dossier jusqu'à finalisation (`registration-details.php`)
5. Après finalisation : `is_locked = 1`, email de confirmation définitif

### Gestion des membres (admin)

- **Liste** (`students.php`) : recherche plein texte, filtres (genre, statut, établissement, nationalité), pagination. Les **Diplômés** sont masqués par défaut et accessibles via filtre dédié avec indication de l'année de promo.
- **Fiche** (`student-details.php`) : documents consultables en ligne (photo en lightbox, PDF en iframe), badges de statut, historique d'upgrades
- **Modification** (`edit-student.php`) : tous les champs éditables, upload de nouveaux documents, champ date de diplomation affiché dynamiquement si statut = Diplômé

### Export

- **Formats** : CSV (UTF-8 BOM), JSON, PDF
- **Champs sélectionnables** : 24 champs dont `date_diplomation`
- **Filtres** : genre, statut (dont Diplômé), établissement, niveau, type de logement
- **Sécurité** : liste blanche des colonnes exportables (aucune interpolation SQL)

### Passage de niveau (`upgrade-levels.php`)

**Éligibilité** (3 conditions cumulatives) :
1. Inscrit depuis ≥ 1 an
2. Aucun upgrade confirmé dans les 12 derniers mois
3. Statut ≠ Diplômé

**Workflow** :
- L'admin sélectionne les étudiants éligibles et leur nouveau niveau (liste complète de `niveaux_etudes`)
- **Auto** : mise à jour immédiate du niveau en base
- **Email** : envoi d'un lien de confirmation tokenisé (validité 7 jours) ; la confirmation est enregistrée dans `pending_level_upgrades`

### Réconciliation des documents (`reconcile-documents.php`)

Outil de réparation lorsque les fichiers uploadés sont déplacés ou restaurés avec des noms différents.

**Diagnostics** :
- **Chemin valide** : fichier trouvé au chemin exact stocké en base
- **Corrigeable** : fichier trouvé dans un autre dossier d'upload (même nom, mauvais répertoire) → correction en un clic
- **Introuvable** : fichier absent de tous les dossiers
- **Orphelin** : fichier présent sur disque mais non référencé en base

**Assignation manuelle** des orphelins :
- Aperçu miniature pour les images (clic pour agrandir), lien "Ouvrir PDF" pour les PDFs
- Le timestamp encodé dans le nom de fichier (`uniqid()`) est décodé pour suggérer les membres inscrits à ±5 minutes de l'upload
- Formulaire d'assignation directement dans la ligne du tableau

### Communications & CGU (`communications.php`)

**Onglet Consentement CGU** :

| Option | Comportement |
|---|---|
| Sans consentement | Envoie uniquement aux membres avec `consent_privacy = 0` |
| Tous les membres | Envoie à tous (utilisé lors d'une mise à jour des CGU) |
| Membres spécifiques | Liste déroulante avec recherche en temps réel et cases à cocher |

Chaque email contient un lien unique tokenisé (`accept-cgu.php?token=xxx`).

**Page d'acceptation (`accept-cgu.php`)** :

| Action | Résultat |
|---|---|
| J'accepte | `consent_privacy = 1`, `consent_privacy_date = NOW()`, token invalidé |
| Je refuse | `consent_privacy = 0`, `consent_refused_at = NOW()` |
| Demander la suppression | `deletion_requested_at = NOW()`, email de notification envoyé à l'admin |
| Finalement j'accepte | Retour vers la page d'acceptation |

**Onglet Communication libre** :
- Sujet + corps libre (textarea)
- Filtres destinataires : statut, statut de consentement
- Personnalisation : `{{prenom}}` et `{{nom}}` remplacés automatiquement
- Bouton "Aperçu des destinataires" (AJAX) avant envoi
- Toutes les campagnes sont enregistrées dans la table `communications`

**Onglet Suppressions** :
- Liste les membres ayant demandé la suppression de leurs données
- Badge rouge sur l'onglet si demandes en attente
- Lien vers la fiche du membre pour traitement manuel (conformité RGPD)

**Onglet Historique** :
- 10 dernières campagnes : sujet, envoyés/destinataires, date, expéditeur

---

## Tests

Tests unitaires/intégration CLI dans `tests/` :

```bash
# Lancer tous les tests
php tests/test_document_reconcile.php
```

Résultat attendu : `21/21 tests passed`

Les tests couvrent les 5 fonctions de `functions/document-reconcile.php` :
`dbPathExists`, `findAlternativePath`, `scanUploadFiles`, `findOrphanedFiles`, `classifyDocument`

---

## Déploiement (production)

Le projet tourne sur un hébergement mutualisé Namecheap/LiteSpeed.

```bash
# Après merge sur main, tirer les changements sur le serveur
git pull origin main

# Vérifier les permissions uploads
chmod -R 755 uploads/
```

**Points de vigilance** :
- Le fichier `.env` doit exister sur le serveur et ne pas être versionné
- `uploads/` doit être accessible en écriture par le serveur web
- Le cache LiteSpeed peut nécessiter un flush après déploiement CSS/JS
- Les colonnes ajoutées via `ALTER TABLE` auto-guérissants s'exécutent au premier accès à la page concernée

---

## Sécurité

| Mesure | Implémentation |
|---|---|
| CSRF | Token `bin2hex(random_bytes(32))`, vérification `hash_equals()` sur tous les POST |
| Mots de passe | `password_hash()` / `password_verify()` (bcrypt) |
| Sessions | `HttpOnly`, `Secure`, `SameSite=Strict`, expiration 30 min |
| Injections SQL | PDO avec requêtes préparées systématiques, liste blanche des colonnes exportables |
| XSS | `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` sur toutes les sorties HTML |
| Upload | Vérification extension + MIME réel (`finfo`), taille max, nom aléatoire (`uniqid()`) |
| Path traversal | `realpath()` + vérification que le fichier résolu est dans `uploads/` |
| `.htaccess` | Blocage `.git`, `wp-admin`, `phpmyadmin` ; HTTPS forcé ; headers de sécurité (`X-Frame-Options`, `X-Content-Type-Options`, `CSP`) |
| Tokens CGU/upgrade | `bin2hex(random_bytes(32))`, usage unique, invalidés après utilisation |
