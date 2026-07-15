<?php
declare(strict_types=1);

final class AuthController
{
    public static function adminLogin(): void
    {
        $data     = Request::body();
        $username = Request::str($data, 'username');
        $password = Request::str($data, 'password');

        if ($username === '' || $password === '') {
            Response::error('Identifiant et mot de passe requis.', 422);
        }

        $stmt = Database::get()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            Response::error('Identifiants incorrects.', 401);
        }

        Auth::loginAdmin((int) $admin['id'], $admin['username']);
        Response::json(Auth::me());
    }

    public static function participantLogin(): void
    {
        $data   = Request::body();
        $nom    = Request::str($data, 'nom');
        $prenom = Request::str($data, 'prenom');

        if ($nom === '' || $prenom === '') {
            Response::error('Nom et prénom requis.', 422);
        }

        $stmt = Database::get()->prepare(
            'SELECT id, nom, prenom FROM participants WHERE nom = ? AND prenom = ?'
        );
        $stmt->execute([$nom, $prenom]);
        $p = $stmt->fetch();

        if (!$p) {
            Response::error('Aucun pratiquant trouvé avec ce nom et prénom.', 401);
        }

        Auth::loginParticipant((int) $p['id'], $p['prenom'] . ' ' . $p['nom']);
        Response::json(Auth::me());
    }

    public static function logout(): void
    {
        Auth::logout();
        Response::json(['ok' => true]);
    }

    public static function me(): void
    {
        Response::json(Auth::me());
    }
}
