<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext258265Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext258265Plan;

$plan = SQLiteVfsCurrentSourceNext258265Plan::run([
    'claim(reader-ready-next257,shared-cache-next257,reader-reuse-next265)',
    'publish(reader-ready-next257,reader-reuse-next265,shared-cache-next265)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published' => [
                    ['token' => 'publish-next217', 'data_version' => 20],
                    ['token' => 'shared-cache-next229', 'data_version' => 20],
                    ['token' => 'shared-cache-next237', 'data_version' => 20],
                    ['token' => 'shared-cache-next245', 'data_version' => 20],
                    ['token' => 'shared-cache-next257', 'data_version' => 20],
                ],
                'reuse_acks' => [
                    [
                        'snapshot' => 'reader-ready-next257',
                        'receipt' => 'shared-cache-next257',
                        'data_version' => 20,
                        'published_count' => 5,
                        'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257'),
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready-next257' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 5,
                'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257'),
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][0]['claim'] === 'reader-reuse-next265');
    assert($plan['events'][0]['blocked_reasons'] === []);
    assert($plan['events'][1]['status'] === 'published-reused-current-source');
    assert($plan['events'][1]['reuse_ack'] === 'shared-cache-next257');
    assert($plan['events'][1]['published_count'] === 6);
    assert(in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next254-257', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next258-265', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next258-265 self-test passed\n";
    return;
}

print_r($plan);
