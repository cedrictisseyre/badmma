-- Migration : découplage des matchs par discipline.
-- À exécuter UNE FOIS sur une base déjà déployée (créée avec l'ancien schéma).
-- Passe d'un modèle « 1 match = 2 manches » à « 1 match = 1 discipline ».

SET NAMES utf8mb4;

-- 1. Nouvelles colonnes sur `matches` (résultat porté directement par le match).
--    « IF NOT EXISTS » (MariaDB) rend la migration ré-exécutable sans erreur #1060.
ALTER TABLE matches
    ADD COLUMN IF NOT EXISTS discipline ENUM('BADMINTON','MMA') NOT NULL DEFAULT 'BADMINTON' AFTER participant_bad_id,
    ADD COLUMN IF NOT EXISTS score_mma      INT NULL        AFTER statut,
    ADD COLUMN IF NOT EXISTS score_bad      INT NULL        AFTER score_mma,
    ADD COLUMN IF NOT EXISTS soumission     TINYINT(1) NULL AFTER score_bad,
    ADD COLUMN IF NOT EXISTS duree_secondes INT NULL        AFTER soumission;

-- 2. L'ancienne table des manches n'est plus utilisée.
DROP TABLE IF EXISTS match_rounds;

-- 3. (Optionnel) Repartir d'affrontements propres après migration :
-- DELETE FROM matches;
-- ALTER TABLE matches AUTO_INCREMENT = 1;
