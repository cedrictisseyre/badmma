<?php
// Copiez ce fichier en « config.php » et renseignez vos identifiants réels.
// config.php est ignoré par Git (voir .gitignore) : ne committez jamais vos secrets.

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'badmma',
        'user'    => 'badmma_user',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    // Mettre à true en production (HTTPS) pour sécuriser le cookie de session.
    'secure_cookies' => false,
];
