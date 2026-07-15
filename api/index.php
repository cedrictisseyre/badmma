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

// Chargement de la config (pour les options de cookie).
$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : ['secure_cookies' => false];

Auth::start((bool) ($config['secure_cookies'] ?? false));

// Détermination de la route.
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$path   = preg_replace('#^.*/api#', '', $uri);   // on isole ce qui suit /api
$path   = '/' . trim($path, '/');

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
    ['PUT',    '#^/matches/(\d+)/rounds$#',    fn($m) => MatchController::saveRounds((int) $m[1])],
    ['PUT',    '#^/matches/(\d+)$#',           fn($m) => MatchController::update((int) $m[1])],
    ['DELETE', '#^/matches/(\d+)$#',           fn($m) => MatchController::delete((int) $m[1])],

    ['GET',    '#^/me/matches$#',              fn() => MatchController::mine()],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod === $method && preg_match($pattern, $path, $matches)) {
        $handler($matches);
        exit;
    }
}

Response::error('Route introuvable.', 404);
