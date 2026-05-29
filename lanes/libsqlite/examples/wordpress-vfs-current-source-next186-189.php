<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'lock(shared)',
    'lock(reserved)',
    'checkreservedlock()',
    'filecontrol(chunk_size,8192)',
    'sector()',
    'characteristics()',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 48],
        'sources' => [
            'main' => [
                'handle' => 'vfs182185-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'sector_size' => 4096,
                'characteristics' => ['powersafe_overwrite', 'safe_append'],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][2]['reserved'] === true);
    assert($plan['events'][3]['value'] === 8192);
    assert($plan['events'][4]['sector_size'] === 4096);
    assert($plan['events'][5]['characteristics'] === ['powersafe_overwrite', 'safe_append']);
    echo "wordpress-vfs-current-source-next186-189 self-test passed\n";
    return;
}

print_r($plan);
