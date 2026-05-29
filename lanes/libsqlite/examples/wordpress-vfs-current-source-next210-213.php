<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext210213Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext210213Plan;

$plan = SQLiteVfsCurrentSourceNext210213Plan::run([
    'snapshot(wp-options-visible)',
    'reuse(wp-options-visible)',
    'publish(wp-options-publication)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs206209-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 13,
                'durable_receipts' => [
                    ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
                    ['page' => 12, 'bytes' => 4096, 'digest' => 'seed000000000012'],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'captured');
    assert($plan['events'][1]['status'] === 'reused');
    assert($plan['events'][2]['status'] === 'published');
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next210-213', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next210-213 self-test passed\n";
    return;
}

print_r($plan);
