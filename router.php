<?php
/**
 * Routeur pour le serveur de développement intégré de PHP UNIQUEMENT.
 * Ne pas utiliser en production (utiliser Nginx + PHP-FPM).
 *
 * Lancement :
 *   php -S localhost:8000 router.php
 * puis ouvrir http://localhost:8000/
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Toutes les requêtes /api/... sont traitées par le front controller.
if (preg_match('#^/api(/|$)#', $uri)) {
    require __DIR__ . '/api/index.php';
    return true;
}

// Sinon, servir le fichier statique s'il existe.
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false; // Laisse le serveur intégré servir le fichier.
}

// Fallback : page principale.
require __DIR__ . '/index.html';
return true;
