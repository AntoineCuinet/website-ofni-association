<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Rebuild the test database (var/test.db, see .env.test) from the entity mapping
// before every run. The migration history cannot be replayed on an empty database.
if ('test' === ($_SERVER['APP_ENV'] ?? null)) {
    // Guard against an exported DATABASE_URL pointing the drop at the dev database.
    $databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
    if (!str_contains($databaseUrl, 'test')) {
        throw new RuntimeException("Refusing to reset \"$databaseUrl\": the test DATABASE_URL must contain \"test\".");
    }

    $console = escapeshellarg(dirname(__DIR__).'/bin/console');
    foreach (['doctrine:schema:drop --force --full-database', 'doctrine:schema:create'] as $command) {
        passthru(sprintf('php %s --env=test --no-interaction --quiet %s', $console, $command), $exitCode);
        if (0 !== $exitCode) {
            throw new RuntimeException("Could not prepare the test database: \"$command\" failed.");
        }
    }
}
