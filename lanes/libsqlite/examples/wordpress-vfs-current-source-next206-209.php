<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-cache)',
    'reuse(reader-cache)',
    'publish(shared-cache-visible)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs202205-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 12,
                'durable_receipts' => [
                    ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
                    ['page' => 7, 'bytes' => 4096, 'digest' => 'seed000000000007'],
                ],
                'checkpoints' => [
                    ['token' => 'after-flush', 'data_version' => 12, 'durable_count' => 2],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'captured');
    assert($plan['events'][1]['status'] === 'reused');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published');
    assert(in_array('vfs-current-source-snapshot-reuse-next206-209', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next206-209 self-test passed\n";
    return;
}

print_r($plan);
