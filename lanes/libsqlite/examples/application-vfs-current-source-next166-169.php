<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'currenttime(1717000000)',
    'currenttimeint64(1717000001)',
    ['op' => 'xSetSystemCall', 'name' => 'pread64', 'enabled' => true],
    ['op' => 'xGetSystemCall', 'name' => 'pread64'],
    ['op' => 'xGetLastError', 'code' => 'SQLITE_IOERR_SHORT_READ', 'message' => 'short read during wp_options scan'],
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 34],
        'sources' => [
            'main' => [
                'handle' => 'vfs162165-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'system_calls' => ['open', 'read', 'write'],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][3]['enabled'] === true);
    assert($plan['events'][4]['code'] === 'SQLITE_IOERR_SHORT_READ');
    echo "application-vfs-current-source-next166-169 self-test passed\n";
    return;
}

print_r($plan);
