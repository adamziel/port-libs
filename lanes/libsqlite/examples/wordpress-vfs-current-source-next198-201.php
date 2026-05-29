<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'write(7,4096)',
    'flush(full)',
    'checkpoint(wp-options-commit)',
    'close(main)',
], [
    'current' => [
        'current_source' => 'main',
        'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 53],
        'sources' => [
            'main' => [
                'handle' => 'vfs194197-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'lock' => 'reserved',
                'data_version' => 8,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'recorded');
    assert($plan['events'][1]['flushed_count'] === 1);
    assert($plan['events'][2]['checkpoint_count'] === 1);
    assert($plan['events'][3]['status'] === 'closed');
    assert(in_array('vfs-current-source-dirty-flush-checkpoint-next198-201', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next198-201 self-test passed\n";
    return;
}

print_r($plan);
