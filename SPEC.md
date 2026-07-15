# 🏸🥊 Spécification — Application « Badminton vs MMA »

Application web de gestion d'une compétition opposant des pratiquants de **MMA** à
des pratiquants de **Badminton**. Chaque affrontement se joue en **2 manches** :
une manche de badminton puis une manche de MMA, chacune avec des règles adaptées.

## Stack technique

| Couche       | Techno                                             |
|--------------|----------------------------------------------------|
| Frontend     | HTML + CSS + JavaScript vanilla (responsive, FR)   |
| Backend      | PHP 8.x (API REST, PDO)                             |
| Base         | MariaDB                                             |
| Serveur      | Nginx 1.30 + PHP-FPM                                |
| Déploiement  | Infomaniak Jelastic via Git (clés SSH configurées) |

## Rôles

- **Administrateur** : login `username` + mot de passe (hashé via `password_hash`).
  Gère les pratiquants, les affrontements et les résultats.
- **Pratiquant** : login `nom` + `prénom` (sans mot de passe). Accès en lecture
  seule à ses propres affrontements et résultats.

## Règles de la compétition

Chaque affrontement oppose **1 pratiquant MMA** à **1 pratiquant Badminton** et
comporte **2 manches** :

### Manche 1 — Badminton (règles adaptées)
- Le pratiquant de badminton **n'a pas droit au smash**.
- Le pratiquant **MMA gagne à 11 points**.
- Le pratiquant **Badminton gagne à 21 points** (handicap).

### Manche 2 — MMA (règles adaptées)
- Le pratiquant de **badminton a droit aux percussions**.
- Le pratiquant de **MMA n'a pas droit aux percussions**.
- Le pratiquant de **badminton gagne s'il n'est pas soumis au bout d'une minute**.
- Le pratiquant de **MMA gagne par soumission** avant la fin de la minute (60 s).

### Vainqueur global de l'affrontement
- Si le **même** pratiquant remporte les 2 manches → il est déclaré vainqueur.
- Si **1 manche chacun** (1-1) → **manche décisive** : l'administrateur désigne
  manuellement le vainqueur.
- L'administrateur peut toujours forcer/corriger le vainqueur.

## Format du tournoi

- **Appariements manuels** créés par l'administrateur.
- L'admin peut créer, modifier, supprimer un affrontement et saisir/modifier les
  scores et vainqueurs des 2 manches.

## Modèle de données (MariaDB)

- `admins(id, username, password_hash, created_at)`
- `participants(id, nom, prenom, categorie ENUM('MMA','BADMINTON'), created_at)`
- `matches(id, participant_mma_id, participant_bad_id, ordre, statut, vainqueur_id, created_at)`
- `match_rounds(id, match_id, type ENUM('BADMINTON','MMA'), score_mma, score_bad, soumission, duree_secondes, vainqueur_id)`

## API REST

| Méthode | Endpoint                        | Accès       | Rôle                          |
|---------|---------------------------------|-------------|-------------------------------|
| POST    | `/api/auth/admin/login`         | public      | Connexion admin               |
| POST    | `/api/auth/participant/login`   | public      | Connexion pratiquant          |
| POST    | `/api/auth/logout`              | connecté    | Déconnexion                   |
| GET     | `/api/auth/me`                  | connecté    | Session courante + CSRF token |
| GET     | `/api/participants`             | admin       | Liste des pratiquants         |
| POST    | `/api/participants`             | admin       | Créer un pratiquant           |
| PUT     | `/api/participants/{id}`        | admin       | Modifier un pratiquant        |
| DELETE  | `/api/participants/{id}`        | admin       | Supprimer un pratiquant       |
| GET     | `/api/matches`                  | admin       | Liste des affrontements       |
| POST    | `/api/matches`                  | admin       | Créer un affrontement         |
| PUT     | `/api/matches/{id}`             | admin       | Modifier statut/ordre/vainq.  |
| DELETE  | `/api/matches/{id}`             | admin       | Supprimer un affrontement     |
| PUT     | `/api/matches/{id}/rounds`      | admin       | Saisir les résultats          |
| GET     | `/api/me/matches`               | pratiquant  | Ses affrontements             |

## Sécurité (OWASP)

- Requêtes SQL **préparées** (anti-injection).
- **Échappement HTML** des sorties côté frontend (anti-XSS).
- **CSRF token** (header `X-CSRF-Token`) sur toutes les requêtes mutantes.
- Contrôle d'**autorisation serveur** (un pratiquant ne voit que ses données).
- Aucun secret dans Git (`api/config.php` est ignoré).
