<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'tempname(import)',
    'syncdir(/srv/www/wp-content/database)',
    'readonly(true)',
    'unlink(/srv/www/wp-content/database/wp.sqlite)',
    'readonly(false)',
    'unlink(/srv/www/wp-content/database/wp.sqlite-journal)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => [
            '/srv/www/wp-content/database/wp.sqlite' => 41,
        ],
        'sources' => [
            'main' => [
                'handle' => 'vfs178181-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'directory' => '/srv/www/wp-content/database',
                'known_dirs' => ['/srv/www/wp-content/database'],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['same_directory'] === true);
    assert($plan['events'][1]['status'] === 'synced');
    assert($plan['events'][3]['status'] === 'blocked');
    assert($plan['events'][5]['status'] === 'unlinked');
    echo "wordpress-vfs-current-source-next182-185 self-test passed\n";
    return;
}

print_r($plan);
