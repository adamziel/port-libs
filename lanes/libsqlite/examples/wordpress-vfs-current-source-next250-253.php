<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-ready)',
    'reuse(reader-ready,reader-ready-next253)',
    'publish(reader-ready,shared-cache-next253)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 34,
                'ready_receipts' => [
                    ['token' => 'ready-next241', 'data_version' => 30],
                    ['token' => 'ready-next245', 'data_version' => 34],
                ],
                'published' => [
                    ['token' => 'shared-cache-next245', 'data_version' => 30],
                    ['token' => 'shared-cache-next249', 'data_version' => 34],
                ],
                'reuse_leases' => [
                    [
                        'token' => 'reader-ready-next249',
                        'snapshot' => 'reader-ready',
                        'data_version' => 34,
                        'published_count' => 2,
                        'receipt_digest' => hash('sha256', 'shared-cache-next245'),
                    ],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'captured-ready');
    assert($plan['events'][1]['status'] === 'leased-current-source');
    assert($plan['events'][1]['lease'] === 'reader-ready-next253');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-current-source');
    assert($plan['events'][2]['reuse_lease'] === 'reader-ready-next253');
    assert($plan['events'][2]['published_count'] === 3);
    assert(in_array('vfs-current-source-ready-publish-next238-241', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-reuse-lease-publish-next242-245', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next246-249', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next250-253', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next250-253 self-test passed\n";
    return;
}

print_r($plan);
