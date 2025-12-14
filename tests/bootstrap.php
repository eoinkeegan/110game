<?php
/**
 * PHPUnit bootstrap file
 * 
 * This file is loaded before tests run.
 * For unit tests of pure functions, we don't need database connections.
 */

// Autoload Composer dependencies if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Note: Don't include game.php or game-sqlite.php here as they have side effects.
// Individual test files should include only what they need, or define functions inline.
