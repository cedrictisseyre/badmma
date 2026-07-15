-- Réinitialisation des données de jeu (participants, affrontements, manches).
-- Supprime TOUS les participants et leurs affrontements/manches, pour repartir
-- d'une base propre après une phase de test.
-- Les comptes administrateurs (table `admins`) sont conservés.

SET NAMES utf8mb4;

-- On supprime dans l'ordre des dépendances.
DELETE FROM matches;
DELETE FROM participants;

-- Remise à zéro des compteurs d'auto-increment (les prochains ID repartent à 1).
ALTER TABLE matches       AUTO_INCREMENT = 1;
ALTER TABLE participants  AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------------
-- Optionnel : supprimer aussi le compte administrateur de démonstration.
-- ⚠️ N'exécutez ces lignes QUE si vous avez déjà créé votre propre admin,
--    sinon vous ne pourrez plus vous connecter à l'espace administrateur.
-- ---------------------------------------------------------------------------
-- DELETE FROM admins WHERE username = 'admin';
