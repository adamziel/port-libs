<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-ready)',
    'reuse(reader-ready)',
    'publish(reader-ready,shared-cache-next221)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 18,
                'ready_receipts' => [
                    ['token' => 'ready-next214-217', 'data_version' => 18],
                ],
                'published' => [
                    ['token' => 'publish-next217', 'data_version' => 18],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'captured-ready');
    assert($plan['events'][1]['status'] === 'reused-current-source');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-current-source');
    assert(in_array('vfs-current-source-ready-next214-217', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-reuse-publish-next218-221', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next218-221 self-test passed\n";
    return;
}

print_r($plan);
