<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    ['op' => 'xFileControl', 'name' => 'chunk-size', 'value' => 8192],
    ['op' => 'xFileControl', 'name' => 'persist_wal', 'value' => true],
    'pathname(-wal)',
    'tempname(etilqs,3)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 38],
        'sources' => [
            'main' => [
                'handle' => 'vfs166169-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'controls' => ['chunk_size' => 4096],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['value'] === 8192);
    assert($plan['events'][2]['path'] === '/srv/www/wp-content/database/wp.sqlite-wal');
    echo "wordpress-vfs-current-source-next170-173 self-test passed\n";
    return;
}

print_r($plan);
