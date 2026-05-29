<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'access(-wal)',
    'delete(-wal)',
    'randomness(8,wpseed)',
    'sleep(1000)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 39],
        'sources' => [
            'main' => [
                'handle' => 'vfs170173-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'existing_paths' => [
                    '/srv/www/wp-content/database/wp.sqlite',
                    '/srv/www/wp-content/database/wp.sqlite-wal',
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'exists');
    assert($plan['events'][1]['path'] === '/srv/www/wp-content/database/wp.sqlite-wal');
    assert(strlen($plan['events'][2]['hex']) === 16);
    echo "wordpress-vfs-current-source-next174-177 self-test passed\n";
    return;
}

print_r($plan);
