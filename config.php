<?php
/**
 * Configuration loader for 110 Card Game
 * 
 * Loads configuration from environment variables with fallback to .env file.
 * For production, set environment variables directly on the server.
 * For local development, copy env.example to .env and customize.
 */

// Load .env file if it exists (for local development)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Don't override existing environment variables
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Configuration array
return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: '110_user',
        'password' => getenv('DB_PASSWORD') ?: '',
        'name' => getenv('DB_NAME') ?: '110_user',
    ],
    'websocket' => [
        'host' => getenv('WS_HOST') ?: '0.0.0.0',
        'port' => getenv('WS_PORT') ?: '8081',
    ],
    'app' => [
        'env' => getenv('APP_ENV') ?: 'development',
        'debug' => getenv('APP_DEBUG') === 'true',
        'domain' => getenv('APP_DOMAIN') ?: 'localhost',
    ],
];

