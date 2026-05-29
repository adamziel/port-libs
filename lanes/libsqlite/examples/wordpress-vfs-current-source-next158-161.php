<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext158161Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext158161Plan;

$plan = SQLiteVfsCurrentSourceNext158161Plan::run([
    'mmap(49152)',
    'fetch(0,4096)',
    ['op' => 'xShmMap', 'page' => 0, 'size' => 32768, 'extend' => true],
    ['op' => 'xShmLock', 'offset' => 2, 'count' => 1, 'mode' => 'exclusive'],
    'unfetch()',
    ['op' => 'xShmUnmap', 'delete' => false],
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 15],
        'sources' => [
            'main' => [
                'handle' => 'vfs154157-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'generation' => 15,
                'size' => 98304,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['mapped'] === 49152);
    assert($plan['events'][5]['released_pages'] === 1);
    echo "wordpress-vfs-current-source-next158-161 self-test passed\n";
    return;
}

print_r($plan);
