<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'write(3,4096)',
    'sync(full)',
    'barrier(checkpoint-commit)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 52],
        'sources' => [
            'main' => [
                'handle' => 'vfs186189-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'lock' => 'reserved',
                'data_version' => 7,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'recorded');
    assert($plan['events'][1]['durable_count'] === 1);
    assert($plan['events'][2]['barrier_count'] === 1);
    assert(in_array('vfs-current-source-durable-receipts-next194-197', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next194-197 self-test passed\n";
    return;
}

print_r($plan);
