<?php
declare(strict_types=1);

/**
 * Statistiques globales de la compétition (tableau des scores).
 * Accessible publiquement (données agrégées, non sensibles) pour affichage live.
 */
final class StatsController
{
    public static function global(): void
    {
        $pdo = Database::get();

        // Victoires par camp (catégorie du vainqueur).
        $camp = ['BADMINTON' => 0, 'MMA' => 0];
        $stmt = $pdo->query(
            'SELECT p.categorie AS camp, COUNT(*) AS n
             FROM matches m
             JOIN participants p ON p.id = m.vainqueur_id
             WHERE m.statut = \'termine\' AND m.vainqueur_id IS NOT NULL
             GROUP BY p.categorie'
        );
        foreach ($stmt->fetchAll() as $r) {
            $camp[$r['camp']] = (int) $r['n'];
        }

        // Répartition des matchs par discipline et statut.
        $disc = [
            'BADMINTON' => ['total' => 0, 'termine' => 0],
            'MMA'       => ['total' => 0, 'termine' => 0],
        ];
        $stmt = $pdo->query(
            'SELECT discipline,
                    COUNT(*) AS total,
                    SUM(statut = \'termine\') AS termine
             FROM matches
             GROUP BY discipline'
        );
        foreach ($stmt->fetchAll() as $r) {
            $disc[$r['discipline']] = [
                'total'   => (int) $r['total'],
                'termine' => (int) $r['termine'],
            ];
        }

        $stmt = $pdo->query('SELECT COUNT(*) AS n FROM matches');
        $totalMatches = (int) ($stmt->fetch()['n'] ?? 0);

        Response::json([
            'victoires' => [
                'badminton' => $camp['BADMINTON'],
                'mma'       => $camp['MMA'],
            ],
            'disciplines'   => $disc,
            'total_matches' => $totalMatches,
        ]);
    }
}
