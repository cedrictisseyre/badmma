<?php
declare(strict_types=1);

final class ParticipantController
{
    public static function index(): void
    {
        Auth::requireAdmin();
        $rows = Database::get()
            ->query('SELECT id, nom, prenom, categorie FROM participants ORDER BY categorie, nom, prenom')
            ->fetchAll();
        Response::json($rows);
    }

    public static function create(): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $data      = Request::body();
        $nom       = Request::str($data, 'nom');
        $prenom    = Request::str($data, 'prenom');
        $categorie = strtoupper(Request::str($data, 'categorie'));

        self::validate($nom, $prenom, $categorie);

        try {
            $stmt = Database::get()->prepare(
                'INSERT INTO participants (nom, prenom, categorie) VALUES (?, ?, ?)'
            );
            $stmt->execute([$nom, $prenom, $categorie]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error('Ce pratiquant (nom + prénom) existe déjà.', 409);
            }
            throw $e;
        }

        Response::json(['id' => (int) Database::get()->lastInsertId()], 201);
    }

    public static function update(int $id): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $data      = Request::body();
        $nom       = Request::str($data, 'nom');
        $prenom    = Request::str($data, 'prenom');
        $categorie = strtoupper(Request::str($data, 'categorie'));

        self::validate($nom, $prenom, $categorie);

        $stmt = Database::get()->prepare(
            'UPDATE participants SET nom = ?, prenom = ?, categorie = ? WHERE id = ?'
        );
        $stmt->execute([$nom, $prenom, $categorie, $id]);
        Response::json(['ok' => true]);
    }

    public static function delete(int $id): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf();

        $stmt = Database::get()->prepare('DELETE FROM participants WHERE id = ?');
        $stmt->execute([$id]);
        Response::json(['ok' => true]);
    }

    private static function validate(string $nom, string $prenom, string $categorie): void
    {
        if ($nom === '' || $prenom === '') {
            Response::error('Nom et prénom requis.', 422);
        }
        if (!in_array($categorie, ['MMA', 'BADMINTON'], true)) {
            Response::error('Catégorie invalide (MMA ou BADMINTON).', 422);
        }
    }
}
