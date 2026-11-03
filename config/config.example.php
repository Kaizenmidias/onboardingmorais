<?php
declare(strict_types=1);

return [
    'app_key' => 'gere-uma-chave-aleatoria-de-64-caracteres',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'onboarding',
        'user' => 'onboarding',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => 'https://onboarding.exemplo.com',
        'timezone' => 'America/Sao_Paulo',
        'session_name' => 'onboarding_session',
        'upload_dir' => dirname(__DIR__) . '/storage/uploads',
    ],
];
