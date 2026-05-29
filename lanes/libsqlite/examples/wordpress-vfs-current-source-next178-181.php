<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'write(1024,4096)',
    'sync(full)',
    'truncate(2048)',
    'reserve(64)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 39],
        'sources' => [
            'main' => [
                'handle' => 'vfs170173-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'size' => 4096,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['size'] === 5120);
    assert($plan['events'][1]['flushed_bytes'] === 1024);
    assert($plan['events'][2]['next']['sources']['main']['size'] === 2048);
    assert($plan['events'][3]['usable_size'] === 1984);
    echo "wordpress-vfs-current-source-next178-181 self-test passed\n";
    return;
}

print_r($plan);
