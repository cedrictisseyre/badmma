# 🏸🥊 Badminton vs MMA

Application web de gestion d'une compétition opposant des pratiquants de **MMA**
à des pratiquants de **Badminton**. Voir [SPEC.md](SPEC.md) pour la spécification
complète (règles, rôles, API).

## Stack

- **Frontend** : HTML / CSS / JavaScript vanilla (responsive, français)
- **Backend** : PHP 8.x (API REST, PDO)
- **Base de données** : MariaDB
- **Serveur** : Nginx 1.30 + PHP-FPM

## Arborescence

```
.
├── index.html                 # Application frontend (SPA légère)
├── assets/
│   ├── css/style.css
│   └── js/
│       ├── api.js             # Client de l'API REST
│       └── app.js             # Logique applicative
├── api/                       # Backend PHP
│   ├── index.php              # Front controller / routeur
│   ├── config.example.php     # Modèle de config (à copier en config.php)
│   ├── lib/                   # Database, Auth, Http
│   └── controllers/           # Auth, Participant, Match
├── sql/
│   ├── schema.sql             # Création des tables
│   └── seed.sql               # Données de démonstration
├── nginx.conf.example         # Exemple de config Nginx
├── SPEC.md
└── README.md
```

## Installation locale

### 1. Base de données

```bash
mysql -u root -p -e "CREATE DATABASE badmma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p badmma < sql/schema.sql
mysql -u root -p badmma < sql/seed.sql
```

### 2. Configuration

```bash
cp api/config.example.php api/config.php
# Éditez api/config.php avec vos identifiants MariaDB.
```

Régénérez au besoin le hash du mot de passe admin :

```bash
php -r "echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT), PHP_EOL;"
```

puis mettez à jour la table `admins` avec ce hash.

### 3. Lancement (serveur de test PHP)

Un `router.php` est fourni pour le serveur intégré de PHP (développement uniquement) :

```bash
php -S localhost:8000 router.php
```

Puis ouvrez `http://localhost:8000/`. En production, utilisez Nginx + PHP-FPM
(voir `nginx.conf.example`).

**Identifiants de démonstration**
- Admin : `admin` / `admin123`
- Pratiquants : n'importe quel `nom` + `prénom` du seed (ex. `Lefevre` / `Sophie`)

## Déploiement Infomaniak Jelastic (via Git)

1. Poussez le dépôt sur votre remote Git (clé privée GitHub / clé publique Infomaniak déjà configurées).
2. Sur le nœud Jelastic, déployez le dépôt dans la racine web (ex. `/var/www/webroot/ROOT`).
3. Créez la base et importez `sql/schema.sql` puis `sql/seed.sql` dans MariaDB.
4. Créez `api/config.php` (non versionné) avec les identifiants de la base Jelastic
   et passez `secure_cookies` à `true` (HTTPS).
5. Adaptez et appliquez `nginx.conf.example` (root, `server_name`, socket PHP-FPM).
6. **Changez le mot de passe admin par défaut.**

## Règles du jeu (rappel)

Chaque affrontement = 2 manches :

1. **Badminton** — pas de smash ; MMA gagne à **11**, Badminton gagne à **21**.
2. **MMA** — le badminton a droit aux percussions ; il gagne s'il **n'est pas
   soumis en 60 s**, sinon le MMA gagne par soumission.

En cas de 1-1, l'administrateur désigne le vainqueur (manche décisive).

## Sécurité

- Requêtes SQL préparées (PDO) — anti-injection.
- Échappement HTML côté frontend — anti-XSS.
- Token CSRF sur toutes les requêtes mutantes.
- Contrôle d'autorisation serveur (un pratiquant ne voit que ses affrontements).
- `api/config.php` ignoré par Git.
