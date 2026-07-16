<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'lock(shared)',
    'access()',
    'truncate(12288)',
    'sync()',
    ['op' => 'open', 'source' => 'journal', 'path' => '/srv/www/wp-content/database/wp.sqlite-journal', 'size' => 4096],
    ['op' => 'xLock', 'level' => 'reserved'],
    ['op' => 'xDelete'],
    ['op' => 'xUnlock', 'level' => 'none'],
    ['op' => 'xDelete'],
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
    assert($plan['events'][1]['exists'] === true);
    assert($plan['events'][2]['size'] === 12288);
    assert($plan['events'][3]['sync_count'] === 1);
    assert($plan['events'][6]['status'] === 'blocked');
    assert($plan['events'][8]['status'] === 'deleted');
    assert($plan['next']['sources']['journal']['exists'] === false);
    echo "application-vfs-current-source-next190-193 self-test passed\n";
    return;
}

print_r($plan);
