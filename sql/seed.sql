-- Jeu de données de démonstration.
-- Mot de passe admin par défaut : « admin123 » (à changer en production !).
-- Le hash ci-dessous correspond à password_hash('admin123', PASSWORD_DEFAULT).

SET NAMES utf8mb4;

INSERT INTO admins (username, password_hash) VALUES
    ('admin', '$2y$10$ZJJ2EPlIbhTGwubCMYsNu.1A1H3gsls6wnFNXHN8tNfNnwaLuTbKm');
-- ^ Si ce hash ne fonctionne pas sur votre PHP, régénérez-le avec :
--   php -r "echo password_hash('admin123', PASSWORD_DEFAULT), PHP_EOL;"
-- puis remplacez la valeur ci-dessus.

INSERT INTO participants (nom, prenom, categorie) VALUES
    ('Durand',  'Marc',   'MMA'),
    ('Petit',   'Julie',  'MMA'),
    ('Moreau',  'Thomas', 'MMA'),
    ('Lefevre', 'Sophie', 'BADMINTON'),
    ('Garcia',  'Lucas',  'BADMINTON'),
    ('Roux',    'Emma',   'BADMINTON');

-- Deux affrontements de démonstration (disciplines distinctes).
INSERT INTO matches (participant_mma_id, participant_bad_id, discipline, ordre, statut)
VALUES
    (1, 4, 'BADMINTON', 1, 'a_venir'),
    (2, 5, 'MMA',       2, 'a_venir');
