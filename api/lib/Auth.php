<?php
declare(strict_types=1);

/**
 * Gestion de la session, de l'authentification et de la protection CSRF.
 */
final class Auth
{
    /** Démarre la session avec des paramètres de cookie sécurisés. */
    public static function start(bool $secure): void
    {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
        session_start();

        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ?? '';
    }

    /** Vérifie le token CSRF pour les requêtes mutantes. */
    public static function requireCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($sent === '' || !hash_equals(self::csrfToken(), $sent)) {
            Response::error('Token CSRF invalide ou manquant.', 403);
        }
    }

    public static function loginAdmin(int $id, string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['csrf']       = bin2hex(random_bytes(32));
        $_SESSION['admin_id']   = $id;
        $_SESSION['admin_name'] = $username;
        unset($_SESSION['participant_id'], $_SESSION['participant_name']);
    }

    public static function loginParticipant(int $id, string $label): void
    {
        session_regenerate_id(true);
        $_SESSION['csrf']              = bin2hex(random_bytes(32));
        $_SESSION['participant_id']    = $id;
        $_SESSION['participant_name']  = $label;
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function isAdmin(): bool
    {
        return !empty($_SESSION['admin_id']);
    }

    public static function isParticipant(): bool
    {
        return !empty($_SESSION['participant_id']);
    }

    public static function participantId(): ?int
    {
        return $_SESSION['participant_id'] ?? null;
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            Response::error('Accès réservé aux administrateurs.', 401);
        }
    }

    public static function requireParticipant(): void
    {
        if (!self::isParticipant()) {
            Response::error('Accès réservé aux pratiquants connectés.', 401);
        }
    }

    /** Retourne l'état de session courant pour le frontend. */
    public static function me(): array
    {
        if (self::isAdmin()) {
            return [
                'role' => 'admin',
                'id'   => $_SESSION['admin_id'],
                'name' => $_SESSION['admin_name'] ?? '',
                'csrf' => self::csrfToken(),
            ];
        }
        if (self::isParticipant()) {
            return [
                'role' => 'participant',
                'id'   => $_SESSION['participant_id'],
                'name' => $_SESSION['participant_name'] ?? '',
                'csrf' => self::csrfToken(),
            ];
        }
        return ['role' => null, 'csrf' => self::csrfToken()];
    }
}
