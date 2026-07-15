-- Schéma de la base « Badminton vs MMA » (MariaDB)
-- Encodage utf8mb4 pour supporter les accents et emojis.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participants (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(80) NOT NULL,
    prenom     VARCHAR(80) NOT NULL,
    categorie  ENUM('MMA','BADMINTON') NOT NULL,
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant (nom, prenom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matches (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    participant_mma_id INT NOT NULL,
    participant_bad_id INT NOT NULL,
    discipline         ENUM('BADMINTON','MMA') NOT NULL,
    ordre              INT NOT NULL DEFAULT 0,
    statut             ENUM('a_venir','en_cours','termine') NOT NULL DEFAULT 'a_venir',
    -- Résultat (selon la discipline du match) :
    score_mma          INT NULL,        -- discipline BADMINTON : points du pratiquant MMA
    score_bad          INT NULL,        -- discipline BADMINTON : points du pratiquant Badminton
    soumission         TINYINT(1) NULL, -- discipline MMA : 1 = badminton soumis (MMA gagne)
    duree_secondes     INT NULL,        -- discipline MMA : durée du combat
    vainqueur_id       INT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_match_mma FOREIGN KEY (participant_mma_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_bad FOREIGN KEY (participant_bad_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_vainqueur FOREIGN KEY (vainqueur_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
