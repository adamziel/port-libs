<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext230233Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext230233Plan;

$plan = SQLiteVfsCurrentSourceNext230233Plan::run([
    'snapshot(reader-ready)',
    'reuse(reader-ready,reader-ready-next233)',
    'publish(reader-ready,shared-cache-next233)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 22,
                'ready_receipts' => [
                    ['token' => 'ready-next214-217', 'data_version' => 18],
                    ['token' => 'ready-next225', 'data_version' => 22],
                ],
                'published' => [
                    ['token' => 'publish-next217', 'data_version' => 18],
                    ['token' => 'shared-cache-next229', 'data_version' => 22],
                ],
                'reuse_leases' => [
                    [
                        'token' => 'reader-ready-next229',
                        'snapshot' => 'reader-ready',
                        'data_version' => 22,
                        'published_count' => 1,
                        'receipt_digest' => hash('sha256', 'publish-next217'),
                    ],
                ],
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'captured-ready');
    assert($plan['events'][1]['status'] === 'leased-current-source');
    assert($plan['events'][1]['lease'] === 'reader-ready-next233');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-current-source');
    assert($plan['events'][2]['reuse_lease'] === 'reader-ready-next233');
    assert($plan['events'][2]['published_count'] === 3);
    assert(in_array('vfs-current-source-ready-publish-next222-225', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-reuse-lease-publish-next226-229', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next230-233', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next230-233 self-test passed\n";
    return;
}

print_r($plan);
