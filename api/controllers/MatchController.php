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

        $data  = Request::body();
        $mma   = Request::intOrNull($data, 'participant_mma_id');
        $bad   = Request::intOrNull($data, 'participant_bad_id');
        $ordre = Request::intOrNull($data, 'ordre') ?? 0;

        if ($mma === null || $bad === null) {
            Response::error('Un pratiquant MMA et un pratiquant Badminton sont requis.', 422);
        }

        self::assertCategorie($mma, 'MMA');
        self::assertCategorie($bad, 'BADMINTON');

        $stmt = Database::get()->prepare(
            'INSERT INTO matches (participant_mma_id, participant_bad_id, ordre, statut)
             VALUES (?, ?, ?, "a_venir")'
        );
        $stmt->execute([$mma, $bad, $ordre]);
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

        if ($vainqueur !== null
            && $vainqueur !== (int) $match['participant_mma_id']
            && $vainqueur !== (int) $match['participant_bad_id']) {
            Response::error('Le vainqueur doit être l\'un des deux pratiquants de l\'affrontement.', 422);
        }

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
     * Saisie des résultats des 2 manches, puis calcul automatique du vainqueur.
     * Corps attendu :
     *   badminton: { score_mma, score_bad }
     *   mma:       { soumission (0|1), duree_secondes }
     *   vainqueur_id (optionnel) : forçage manuel, requis en cas de 1-1.
     */
    public static function saveRounds(int $id): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $match = self::findMatch($id);
        $mmaId = (int) $match['participant_mma_id'];
        $badId = (int) $match['participant_bad_id'];
        $data  = Request::body();

        $pdo = Database::get();
        $pdo->beginTransaction();

        try {
            $winners = [];

            // --- Manche BADMINTON ---
            if (isset($data['badminton'])) {
                $b        = $data['badminton'];
                $scoreMma = Request::intOrNull($b, 'score_mma');
                $scoreBad = Request::intOrNull($b, 'score_bad');
                $winBad   = self::computeBadminton($scoreMma, $scoreBad, $mmaId, $badId);

                self::upsertRound($pdo, $id, 'BADMINTON', [
                    'score_mma'      => $scoreMma,
                    'score_bad'      => $scoreBad,
                    'soumission'     => null,
                    'duree_secondes' => null,
                    'vainqueur_id'   => $winBad,
                ]);
                if ($winBad !== null) {
                    $winners['BADMINTON'] = $winBad;
                }
            }

            // --- Manche MMA ---
            if (isset($data['mma'])) {
                $m       = $data['mma'];
                $soum    = Request::intOrNull($m, 'soumission');
                $duree   = Request::intOrNull($m, 'duree_secondes');
                $winMma  = self::computeMma($soum, $mmaId, $badId);

                self::upsertRound($pdo, $id, 'MMA', [
                    'score_mma'      => null,
                    'score_bad'      => null,
                    'soumission'     => $soum,
                    'duree_secondes' => $duree,
                    'vainqueur_id'   => $winMma,
                ]);
                if ($winMma !== null) {
                    $winners['MMA'] = $winMma;
                }
            }

            // --- Vainqueur global ---
            $existing = self::roundWinners($pdo, $id);
            $winners  = array_merge($existing, $winners);

            $vainqueur = self::intOrNullFromBody($data, 'vainqueur_id');
            $statut    = 'en_cours';

            if (isset($winners['BADMINTON'], $winners['MMA'])) {
                if ($winners['BADMINTON'] === $winners['MMA']) {
                    // Même vainqueur sur les 2 manches.
                    $vainqueur = $winners['BADMINTON'];
                    $statut    = 'termine';
                } elseif ($vainqueur !== null) {
                    // 1-1 : l'admin a désigné le vainqueur de la manche décisive.
                    $statut = 'termine';
                }
            }

            if ($vainqueur !== null
                && $vainqueur !== $mmaId
                && $vainqueur !== $badId) {
                throw new RuntimeException('Vainqueur invalide.');
            }

            $stmt = $pdo->prepare('UPDATE matches SET vainqueur_id = ?, statut = ? WHERE id = ?');
            $stmt->execute([$vainqueur, $statut, $id]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof RuntimeException) {
                Response::error($e->getMessage(), 422);
            }
            throw $e;
        }

        Response::json(self::fetchMatches()[0] ?? ['ok' => true]);
    }

    // ------------------------------------------------------------------ //
    // Logique métier
    // ------------------------------------------------------------------ //

    /** Badminton : MMA gagne à 11, Badminton gagne à 21. */
    private static function computeBadminton(?int $scoreMma, ?int $scoreBad, int $mmaId, int $badId): ?int
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
        return null; // Manche non conclue.
    }

    /** MMA : soumission => le MMA gagne ; pas de soumission (60 s) => le badminton gagne. */
    private static function computeMma(?int $soumission, int $mmaId, int $badId): ?int
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
        $sql = 'SELECT m.id, m.ordre, m.statut, m.vainqueur_id,
                       m.participant_mma_id, m.participant_bad_id,
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
        $matches = $stmt->fetchAll();

        if (!$matches) {
            return [];
        }

        // Récupération des manches associées.
        $ids   = array_column($matches, 'id');
        $place = implode(',', array_fill(0, count($ids), '?'));
        $rstmt = Database::get()->prepare(
            "SELECT match_id, type, score_mma, score_bad, soumission, duree_secondes, vainqueur_id
             FROM match_rounds WHERE match_id IN ($place)"
        );
        $rstmt->execute($ids);
        $roundsByMatch = [];
        foreach ($rstmt->fetchAll() as $r) {
            $roundsByMatch[$r['match_id']][$r['type']] = $r;
        }

        foreach ($matches as &$m) {
            $m['rounds'] = $roundsByMatch[$m['id']] ?? [];
        }
        unset($m);

        return $matches;
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

    private static function upsertRound(PDO $pdo, int $matchId, string $type, array $vals): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO match_rounds (match_id, type, score_mma, score_bad, soumission, duree_secondes, vainqueur_id)
             VALUES (:match_id, :type, :score_mma, :score_bad, :soumission, :duree, :vainqueur)
             ON DUPLICATE KEY UPDATE
                score_mma = VALUES(score_mma),
                score_bad = VALUES(score_bad),
                soumission = VALUES(soumission),
                duree_secondes = VALUES(duree_secondes),
                vainqueur_id = VALUES(vainqueur_id)'
        );
        $stmt->execute([
            ':match_id'  => $matchId,
            ':type'      => $type,
            ':score_mma' => $vals['score_mma'],
            ':score_bad' => $vals['score_bad'],
            ':soumission'=> $vals['soumission'],
            ':duree'     => $vals['duree_secondes'],
            ':vainqueur' => $vals['vainqueur_id'],
        ]);
    }

    /** Retourne les vainqueurs déjà enregistrés par type de manche. */
    private static function roundWinners(PDO $pdo, int $matchId): array
    {
        $stmt = $pdo->prepare('SELECT type, vainqueur_id FROM match_rounds WHERE match_id = ?');
        $stmt->execute([$matchId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            if ($r['vainqueur_id'] !== null) {
                $out[$r['type']] = (int) $r['vainqueur_id'];
            }
        }
        return $out;
    }

    private static function intOrNullFromBody(array $data, string $key): ?int
    {
        return Request::intOrNull($data, $key);
    }
}
