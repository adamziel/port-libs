<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'claim(reader-ready-next245,shared-cache-next245,reader-reuse-next257)',
    'publish(reader-ready-next245,reader-reuse-next257,shared-cache-next257)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 19,
                'published' => [
                    ['token' => 'publish-next217', 'data_version' => 19],
                    ['token' => 'shared-cache-next229', 'data_version' => 19],
                    ['token' => 'shared-cache-next237', 'data_version' => 19],
                    ['token' => 'shared-cache-next245', 'data_version' => 19],
                ],
                'reuse_acks' => [
                    [
                        'snapshot' => 'reader-ready-next245',
                        'receipt' => 'shared-cache-next245',
                        'data_version' => 19,
                        'published_count' => 4,
                        'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245'),
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready-next245' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 19,
                'published_count' => 4,
                'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245'),
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][0]['claim'] === 'reader-reuse-next257');
    assert($plan['events'][0]['blocked_reasons'] === []);
    assert($plan['events'][1]['status'] === 'published-reused-current-source');
    assert($plan['events'][1]['reuse_ack'] === 'shared-cache-next245');
    assert($plan['events'][1]['published_count'] === 5);
    assert(in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next242-245', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next254-257', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next254-257 self-test passed\n";
    return;
}

print_r($plan);
