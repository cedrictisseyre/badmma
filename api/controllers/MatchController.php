<?php
declare(strict_types=1);

final class MatchController
{
    /** Liste complète des affrontements (admin). */
    public static function index(): void
    {
        Auth::requireAdmin();
        Response::json(self::fetchMatches());
    }

    /** Affrontements du pratiquant connecté. */
    public static function mine(): void
    {
        Auth::requireParticipant();
        $pid = Auth::participantId();
        Response::json(self::fetchMatches($pid));
    }

    public static function create(): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $data       = Request::body();
        $mma        = Request::intOrNull($data, 'participant_mma_id');
        $bad        = Request::intOrNull($data, 'participant_bad_id');
        $ordre      = Request::intOrNull($data, 'ordre') ?? 0;
        $discipline = strtoupper(Request::str($data, 'discipline'));

        if (!in_array($discipline, ['BADMINTON', 'MMA'], true)) {
            Response::error('Discipline invalide (BADMINTON ou MMA).', 422);
        }
        if ($mma === null || $bad === null) {
            Response::error('Un pratiquant MMA et un pratiquant Badminton sont requis.', 422);
        }

        self::assertCategorie($mma, 'MMA');
        self::assertCategorie($bad, 'BADMINTON');

        $stmt = Database::get()->prepare(
            'INSERT INTO matches (participant_mma_id, participant_bad_id, discipline, ordre, statut)
             VALUES (?, ?, ?, ?, "a_venir")'
        );
        $stmt->execute([$mma, $bad, $discipline, $ordre]);
        Response::json(['id' => (int) Database::get()->lastInsertId()], 201);
    }

    /** Mise à jour du statut / ordre / vainqueur (ex. manche décisive). */
    public static function update(int $id): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $data  = Request::body();
        $match = self::findMatch($id);

        $ordre  = Request::intOrNull($data, 'ordre') ?? (int) $match['ordre'];
        $statut = Request::str($data, 'statut', $match['statut']);
        if (!in_array($statut, ['a_venir', 'en_cours', 'termine'], true)) {
            Response::error('Statut invalide.', 422);
        }

        $vainqueur = array_key_exists('vainqueur_id', $data)
            ? Request::intOrNull($data, 'vainqueur_id')
            : (isset($match['vainqueur_id']) ? (int) $match['vainqueur_id'] : null);
        self::assertWinnerBelongs($vainqueur, $match);

        $stmt = Database::get()->prepare(
            'UPDATE matches SET ordre = ?, statut = ?, vainqueur_id = ? WHERE id = ?'
        );
        $stmt->execute([$ordre, $statut, $vainqueur, $id]);
        Response::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $stmt = Database::get()->prepare('DELETE FROM matches WHERE id = ?');
        $stmt->execute([$id]);
        Response::json(['ok' => true]);
    }

    /**
     * Saisie du résultat d'un match, selon sa discipline.
     *   BADMINTON : { score_mma, score_bad }
     *   MMA       : { soumission (0|1), duree_secondes }
     * vainqueur_id (optionnel) : forçage manuel du vainqueur.
     */
    public static function saveResult(int $id): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $match = self::findMatch($id);
        $mmaId = (int) $match['participant_mma_id'];
        $badId = (int) $match['participant_bad_id'];
        $data  = Request::body();

        $scoreMma = null;
        $scoreBad = null;
        $soum     = null;
        $duree    = null;

        if ($match['discipline'] === 'BADMINTON') {
            $scoreMma  = Request::intOrNull($data, 'score_mma');
            $scoreBad  = Request::intOrNull($data, 'score_bad');
            $vainqueur = self::winnerBadminton($scoreMma, $scoreBad, $mmaId, $badId);
        } else { // MMA
            $soum      = Request::intOrNull($data, 'soumission');
            $duree     = Request::intOrNull($data, 'duree_secondes');
            $vainqueur = self::winnerMma($soum, $mmaId, $badId);
        }

        // Forçage manuel éventuel du vainqueur.
        if (array_key_exists('vainqueur_id', $data)) {
            $forced = Request::intOrNull($data, 'vainqueur_id');
            if ($forced !== null) {
                self::assertWinnerBelongs($forced, $match);
                $vainqueur = $forced;
            }
        }

        $statut = $vainqueur !== null ? 'termine' : 'en_cours';

        $stmt = Database::get()->prepare(
            'UPDATE matches
                SET score_mma = ?, score_bad = ?, soumission = ?, duree_secondes = ?,
                    vainqueur_id = ?, statut = ?
              WHERE id = ?'
        );
        $stmt->execute([$scoreMma, $scoreBad, $soum, $duree, $vainqueur, $statut, $id]);

        Response::json(['ok' => true, 'vainqueur_id' => $vainqueur, 'statut' => $statut]);
    }

    // ------------------------------------------------------------------ //
    // Logique métier
    // ------------------------------------------------------------------ //

    /** Badminton : MMA gagne à 11, Badminton gagne à 21. */
    private static function winnerBadminton(?int $scoreMma, ?int $scoreBad, int $mmaId, int $badId): ?int
    {
        if ($scoreMma === null || $scoreBad === null) {
            return null;
        }
        if ($scoreBad >= 21 && $scoreBad > $scoreMma) {
            return $badId;
        }
        if ($scoreMma >= 11 && $scoreMma > $scoreBad) {
            return $mmaId;
        }
        return null; // Match non conclu.
    }

    /** MMA : soumission => le MMA gagne ; pas de soumission (60 s) => le badminton gagne. */
    private static function winnerMma(?int $soumission, int $mmaId, int $badId): ?int
    {
        if ($soumission === null) {
            return null;
        }
        return $soumission === 1 ? $mmaId : $badId;
    }

    // ------------------------------------------------------------------ //
    // Accès données
    // ------------------------------------------------------------------ //

    private static function fetchMatches(?int $participantId = null): array
    {
        $sql = 'SELECT m.id, m.discipline, m.ordre, m.statut, m.vainqueur_id,
                       m.participant_mma_id, m.participant_bad_id,
                       m.score_mma, m.score_bad, m.soumission, m.duree_secondes,
                       CONCAT(pm.prenom, " ", pm.nom) AS mma_nom,
                       CONCAT(pb.prenom, " ", pb.nom) AS bad_nom
                FROM matches m
                JOIN participants pm ON pm.id = m.participant_mma_id
                JOIN participants pb ON pb.id = m.participant_bad_id';
        $params = [];

        if ($participantId !== null) {
            $sql .= ' WHERE m.participant_mma_id = ? OR m.participant_bad_id = ?';
            $params = [$participantId, $participantId];
        }
        $sql .= ' ORDER BY m.ordre, m.id';

        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function findMatch(int $id): array
    {
        $stmt = Database::get()->prepare('SELECT * FROM matches WHERE id = ?');
        $stmt->execute([$id]);
        $match = $stmt->fetch();
        if (!$match) {
            Response::error('Affrontement introuvable.', 404);
        }
        return $match;
    }

    private static function assertCategorie(int $participantId, string $categorie): void
    {
        $stmt = Database::get()->prepare('SELECT categorie FROM participants WHERE id = ?');
        $stmt->execute([$participantId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Pratiquant introuvable.', 404);
        }
        if ($row['categorie'] !== $categorie) {
            Response::error("Le pratiquant sélectionné n'est pas de catégorie $categorie.", 422);
        }
    }

    private static function assertWinnerBelongs(?int $vainqueur, array $match): void
    {
        if ($vainqueur !== null
            && $vainqueur !== (int) $match['participant_mma_id']
            && $vainqueur !== (int) $match['participant_bad_id']) {
            Response::error('Le vainqueur doit être l\'un des deux pratiquants de l\'affrontement.', 422);
        }
    }
}
