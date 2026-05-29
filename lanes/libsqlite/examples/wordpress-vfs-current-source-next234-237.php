<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'ack(reader-ready,shared-cache-next229)',
    'republish(reader-ready,shared-cache-next237)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 18,
                'published' => [
                    ['token' => 'publish-next217', 'data_version' => 18],
                    ['token' => 'shared-cache-next229', 'data_version' => 18],
                ],
                'reuse_leases' => [
                    [
                        'token' => 'reader-ready-next229',
                        'snapshot' => 'reader-ready',
                        'data_version' => 18,
                        'published_count' => 1,
                        'receipt_digest' => hash('sha256', 'publish-next217'),
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 18,
                'published_count' => 2,
                'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229'),
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'acknowledged-reuse-publish');
    assert($plan['events'][0]['receipt'] === 'shared-cache-next229');
    assert($plan['events'][0]['blocked_reasons'] === []);
    assert($plan['events'][1]['status'] === 'republished-current-source');
    assert($plan['events'][1]['reuse_ack'] === 'shared-cache-next229');
    assert($plan['events'][1]['published_count'] === 3);
    assert(in_array('vfs-current-source-reuse-lease-publish-next226-229', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next234-237 self-test passed\n";
    return;
}

print_r($plan);
