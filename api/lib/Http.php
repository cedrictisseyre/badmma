<?php
declare(strict_types=1);

/**
 * Helpers de réponse JSON et de lecture des requêtes.
 */
final class Response
{
    /** Envoie une réponse JSON et termine le script. */
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Envoie une erreur JSON standardisée et termine le script. */
    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }
}

final class Request
{
    /** Décode le corps JSON de la requête en tableau associatif. */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /** Récupère une chaîne nettoyée depuis un tableau de données. */
    public static function str(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) ? trim((string) $data[$key]) : $default;
    }

    /** Récupère un entier (ou null) depuis un tableau de données. */
    public static function intOrNull(array $data, string $key): ?int
    {
        if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
            return null;
        }
        return (int) $data[$key];
    }
}
