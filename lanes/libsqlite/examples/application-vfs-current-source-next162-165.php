<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'access(/srv/www/wp-content/database/wp.sqlite-wal,exists)',
    ['op' => 'xFullPathname', 'path' => 'wp.sqlite'],
    ['op' => 'xDeviceCharacteristics', 'flags' => 'safe_append powersafe_overwrite'],
    ['op' => 'xRandomness', 'bytes' => 16, 'seed' => 'wpnonce162'],
    ['op' => 'xDelete', 'path' => '/srv/www/wp-content/database/wp.sqlite-wal', 'sync_dir' => true],
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 30],
        'sources' => [
            'main' => [
                'handle' => 'vfs158161-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'sector_size' => 4096,
                'device' => ['safe_append'],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'ok');
    assert($plan['events'][4]['delete_count'] === 1);
    echo "application-vfs-current-source-next162-165 self-test passed\n";
    return;
}

print_r($plan);
