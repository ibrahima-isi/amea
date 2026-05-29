# AMEA — Plateforme de gestion AEESGS

Plateforme web de gestion des membres de l'**Amicale des Élèves, Étudiants et Stagiaires Guinéens au Sénégal (AEESGS)**. Elle couvre l'inscription des membres, la gestion des dossiers, les communications, les exports et les outils d'administration.

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
| Backend | PHP 8.x (OOP, PDO, fileinfo, GD) |
| Base de données | MySQL 8.0 (charset utf8mb4) |
| Frontend | Bootstrap 5, JavaScript vanilla, Tagify, **Dark Mode** |
| Email | PHPMailer 6.x via Brevo SMTP |
| Templating | Système maison (`strtr()`) & Twig |
| Dépendances | Composer (`phpmailer/phpmailer`, `twig/twig`) |
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
INSERT INTO users (username, password, last_name, first_name, email, role, is_active)
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
├── src/                        # Coeur applicatif (OOP, PSR-4)
│   ├── Core/                   # Moteur de template, Router, Session
│   ├── Controller/             # Contrôleurs (Auth, Registration, KYC)
│   ├── Model/                  # Entités (User, Student)
│   ├── Repository/             # Accès aux données
│   └── Service/                # Logique métier (AuthService, StudentService)
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
│   ├── js/                     # Scripts front-end (dont theme.js pour le Dark Mode)
│   ├── img/                    # Logos, icônes
│   └── json/                   # Données statiques (liste des pays, etc.)
│
├── uploads/
│   ├── slider/                 # Images du carrousel d'accueil
│   └── students/               # Photos et CVs des étudiants
│       └── cvs/                # CVs (PDF/PNG)
│
├── tests/                      # Tests unitaires et d'intégration
└── logs/                       # Journaux d'erreurs
```

---

## Base de données

### Tables principales

#### `students` — Dossiers des membres
| Colonne | Type | Description |
|---|---|---|
| `id` | INT PK | Identifiant unique |
| `last_name`, `first_name` | VARCHAR | Nom complet |
| `gender` | ENUM | `Masculin` / `Féminin` |
| `email` | VARCHAR UNIQUE | Adresse email |
| `phone` | VARCHAR UNIQUE | Numéro de téléphone |
| `status` | ENUM | `ELEVE` / `ETUDIANT` / `STAGIAIRE` |
| `institution` | VARCHAR | Établissement d'inscription |
| `study_level` | VARCHAR | Niveau actuel |
| `nationalities` | JSON | Liste des nationalités |
| `identity_document` | VARCHAR | Chemin relatif de la pièce d'identité |
| `cv_path` | VARCHAR | Chemin relatif du CV |
| `registration_date` | DATETIME | Date d'inscription |
| `graduation_date` | DATE | Date de diplomation (si Diplômé) |
| `is_locked` | TINYINT | Dossier finalisé (1) ou modifiable (0) |
| `consent_privacy` | TINYINT | CGU acceptées (1) ou non (0) |

#### `users` — Comptes administrateurs
| Colonne | Type | Description |
|---|---|---|
| `id` | INT PK | |
| `username` | VARCHAR UNIQUE | Identifiant de connexion |
| `password` | VARCHAR | Hash bcrypt |
| `role` | ENUM | `admin` / `user` |
| `is_active` | TINYINT | Compte actif (1) ou désactivé (0) |
| `last_login` | DATETIME | Dernière connexion |

#### Autres tables

| Table | Description |
|---|---|
| `institutions` | Établissements d'enseignement |
| `study_fields` | Domaines d'études disponibles |
| `study_levels` | Niveaux d'études |
| `slider_images` | Images du carrousel de la page d'accueil |
| `student_country` | Table de pivot pour les nationalités |
| `settings` | Configuration système (email contact, téléphone…) |
| `password_resets` | Tokens de réinitialisation de mot de passe |

### Migrations

Les migrations sont des scripts PHP CLI idempotents dans `migrations/`. Ils ajoutent des colonnes, modifient les contraintes ou créent des tables sans recréer le schéma existant.

À exécuter dans le répertoire `public_html/` de production en cas de mise à jour du schéma :

```bash
cd /home/aeessqgf/public_html
php -d display_errors=1 migrations/migration_translate_to_english.php
# etc.
```

---

## Authentification & sessions

- **Connexion** : vérification `password_verify()` + `session_regenerate_id(true)` après succès
- **Rôles** : `admin` (accès total) / `user` (accès restreint)
- **Durée de session** : 30 minutes d'inactivité
- **Cookies** : `HttpOnly`, `Secure`, `SameSite=Strict`
- **CSRF** : token `bin2hex(random_bytes(32))` vérifié via `hash_equals()` sur tous les formulaires POST

---

## Pages publiques

| Page | Description |
|---|---|
| `index.php` | Accueil avec carrousel dynamique |
| `register.php` | Formulaire d'inscription (**Dark Mode supporté**) |
| `login.php` | Connexion administrateur |
| `forgot-password.php` | Demande de réinitialisation de mot de passe |
| `reset-password.php` | Réinitialisation avec token |
| `legal-notice.php` | Mentions légales et politique de confidentialité (CGU) |
| `accept-cgu.php` | Acceptation/refus des CGU via lien tokenisé (email) |
| `registration-details.php` | Finalisation du dossier par le membre |
| `download.php` | Téléchargement sécurisé des documents d'un étudiant |

---

## Pages admin

Toutes les pages admin nécessitent une session avec `user_id` défini. Les pages marquées **(admin only)** nécessitent `role = 'admin'`.

| Page | Description |
|---|---|
| `dashboard.php` | Tableau de bord : KPIs, graphiques adaptés au thème |
| `students.php` | Liste des membres avec recherche, filtres, pagination |
| `student-details.php` | Fiche détaillée d'un membre (lecture seule) |
| `edit-student.php` | Modification synchronisée avec le formulaire d'inscription |
| `export.php` | Export CSV/JSON/PDF avec sélection de champs et filtres |
| `upgrade-levels.php` **(admin)** | Outil de passage de niveau annuel |
| `communications.php` **(admin)** | Campagnes CGU + emails de groupe |
| `settings.php` **(admin)** | Paramètres système |
| `profile.php` | Modifier son propre profil admin |

---

## Fonctionnalités détaillées

### Dark Mode (Thème Sombre)

Le projet intègre un support complet du mode sombre.
- **Toggle responsive** : Affiche un texte sur tablette/desktop et une icône sur mobile.
- **Persistance** : Le choix est sauvegardé localement.
- **Charts dynamiques** : Les graphiques du dashboard ajustent leurs couleurs automatiquement.

### Inscription et Édition synchronisées

Les formulaires d'inscription (`register.php`) et d'édition admin (`edit-student.php`) sont identiques en termes de :
- **Groupement visuel** : Sections dépliables (Accordions).
- **Validation** : Logique métier partagée via `StudentService.php`.
- **Champs requis** : Les contraintes sont strictement alignées.

---

## Tests

Tests unitaires/intégration CLI dans `tests/` :

```bash
# Lancer tous les tests OOP
php tests/test_oop_unit.php
php tests/test_oop_integration.php
```

---

## Déploiement (production)

Le projet utilise un pipeline **CI/CD via GitHub Actions** (`.github/workflows/deploy.yml`) pour se déployer automatiquement sur un hébergement mutualisé.

---

## Sécurité

| Mesure | Implémentation |
|---|---|
| CSRF | Token unique vérifié sur tous les POST |
| Mots de passe | `password_hash()` / `password_verify()` (bcrypt) |
| Sessions | `HttpOnly`, `Secure`, `SameSite=Strict`, expiration 30 min |
| Injections SQL | PDO avec requêtes préparées systématiques |
| XSS | `htmlspecialchars()` systématique sur les sorties |
| Dark Mode Flash | Script de prévention de flash blanc dans le `<head>` |
