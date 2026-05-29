<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'publish-snapshot(reader-cache,ticket-next214)',
    'reuse-publication(ticket-next214,reader-a)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs210213-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 16,
                'durable_receipts' => [
                    ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
                    ['page' => 7, 'bytes' => 4096, 'digest' => 'seed000000000007'],
                    ['page' => 9, 'bytes' => 4096, 'digest' => 'seed000000000009'],
                ],
            ],
        ],
        'snapshots' => [
            'reader-cache' => [
                'source' => 'main',
                'handle' => 'vfs210213-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 16,
                'durable_count' => 3,
                'checkpoint_token' => 'ready-next213',
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'published');
    assert($plan['events'][0]['published_count'] === 1);
    assert($plan['events'][1]['status'] === 'publication-reused');
    assert($plan['events'][1]['next']['reader_reuse']['reader-a']['ticket'] === 'ticket-next214');
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next214-217', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next214-217 self-test passed\n";
    return;
}

print_r($plan);
