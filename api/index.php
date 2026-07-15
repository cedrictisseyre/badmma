<?php
declare(strict_types=1);

/**
 * Front controller de l'API REST « Badminton vs MMA ».
 * Toutes les requêtes /api/... sont routées ici par Nginx.
 */

require __DIR__ . '/lib/Http.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/ParticipantController.php';
require __DIR__ . '/controllers/MatchController.php';
require __DIR__ . '/controllers/StatsController.php';

// Chargement de la config (pour les options de cookie).
$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : ['secure_cookies' => false];

Auth::start((bool) ($config['secure_cookies'] ?? false));

// Détermination de la route.
$method = $_SERVER['REQUEST_METHOD'];

// Deux modes supportés :
//  1. Paramètre ?r=/auth/me (robuste, fonctionne avec toute config Nginx/PHP).
//  2. URL propre /api/auth/me (nécessite une réécriture Nginx vers /api/index.php).
if (isset($_GET['r'])) {
    $path = '/' . trim((string) $_GET['r'], '/');
} else {
    $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    // On isole ce qui suit /api (et un éventuel /index.php).
    $path = preg_replace('#^.*/api(?:/index\.php)?#', '', $uri);
    $path = '/' . trim((string) $path, '/');
}

// Table de routage : [méthode, regex] => callable
$routes = [
    ['POST',   '#^/auth/admin/login$#',        fn() => AuthController::adminLogin()],
    ['POST',   '#^/auth/participant/login$#',  fn() => AuthController::participantLogin()],
    ['POST',   '#^/auth/logout$#',             fn() => AuthController::logout()],
    ['GET',    '#^/auth/me$#',                 fn() => AuthController::me()],

    ['GET',    '#^/participants$#',            fn() => ParticipantController::index()],
    ['POST',   '#^/participants$#',            fn() => ParticipantController::create()],
    ['PUT',    '#^/participants/(\d+)$#',      fn($m) => ParticipantController::update((int) $m[1])],
    ['DELETE', '#^/participants/(\d+)$#',      fn($m) => ParticipantController::delete((int) $m[1])],

    ['GET',    '#^/matches$#',                 fn() => MatchController::index()],
    ['POST',   '#^/matches$#',                 fn() => MatchController::create()],
    ['PUT',    '#^/matches/(\d+)/result$#',    fn($m) => MatchController::saveResult((int) $m[1])],
    ['PUT',    '#^/matches/(\d+)$#',           fn($m) => MatchController::update((int) $m[1])],
    ['DELETE', '#^/matches/(\d+)$#',           fn($m) => MatchController::delete((int) $m[1])],

    ['GET',    '#^/me/matches$#',              fn() => MatchController::mine()],

    ['GET',    '#^/stats$#',                   fn() => StatsController::global()],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod === $method && preg_match($pattern, $path, $matches)) {
        try {
            $handler($matches);
        } catch (Throwable $e) {
            // Message détaillé pour faciliter le diagnostic pendant la mise en place.
            Response::error('Erreur serveur : ' . $e->getMessage(), 500);
        }
        exit;
    }
}

Response::error('Route introuvable.', 404);
