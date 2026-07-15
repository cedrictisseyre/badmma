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
    ordre              INT NOT NULL DEFAULT 0,
    statut             ENUM('a_venir','en_cours','termine') NOT NULL DEFAULT 'a_venir',
    vainqueur_id       INT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_match_mma FOREIGN KEY (participant_mma_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_bad FOREIGN KEY (participant_bad_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_vainqueur FOREIGN KEY (vainqueur_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_rounds (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    match_id       INT NOT NULL,
    type           ENUM('BADMINTON','MMA') NOT NULL,
    score_mma      INT NULL,
    score_bad      INT NULL,
    soumission     TINYINT(1) NULL,          -- 1 = le badminton a été soumis, 0 = non soumis
    duree_secondes INT NULL,                 -- durée de la manche MMA
    vainqueur_id   INT NULL,
    UNIQUE KEY uq_round (match_id, type),
    CONSTRAINT fk_round_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    CONSTRAINT fk_round_vainqueur FOREIGN KEY (vainqueur_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
