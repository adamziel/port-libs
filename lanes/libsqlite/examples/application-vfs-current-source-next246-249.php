<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-ready)',
    'reuse(reader-ready,reader-ready-next249)',
    'publish(reader-ready,shared-cache-next249)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 30,
                'ready_receipts' => [
                    ['token' => 'ready-next225', 'data_version' => 26],
                    ['token' => 'ready-next241', 'data_version' => 30],
                ],
                'published' => [
                    ['token' => 'publish-next225', 'data_version' => 26],
                    ['token' => 'shared-cache-next245', 'data_version' => 30],
                ],
                'reuse_leases' => [
                    [
                        'token' => 'reader-ready-next245',
                        'snapshot' => 'reader-ready',
                        'data_version' => 30,
                        'published_count' => 1,
                        'receipt_digest' => hash('sha256', 'publish-next225'),
                    ],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'captured-ready');
    assert($plan['events'][1]['status'] === 'leased-current-source');
    assert($plan['events'][1]['lease'] === 'reader-ready-next249');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-current-source');
    assert($plan['events'][2]['reuse_lease'] === 'reader-ready-next249');
    assert($plan['events'][2]['published_count'] === 3);
    assert(in_array('vfs-current-source-ready-publish-next238-241', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-reuse-lease-publish-next242-245', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next246-249', $plan['dependencies'], true));
    echo "application-vfs-current-source-next246-249 self-test passed\n";
    return;
}

print_r($plan);
