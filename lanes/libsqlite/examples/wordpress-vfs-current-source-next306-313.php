<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext306313Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext306313Plan;

$plan = SQLiteVfsCurrentSourceNext306313Plan::run([
    'snapshot(reader-ready-next313,shared-cache-next305)',
    'claim(reader-ready-next313,shared-cache-next305,reader-reuse-next313)',
    'publish(reader-ready-next313,reader-reuse-next313,shared-cache-next313)',
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
                    ['token' => 'shared-cache-next265', 'data_version' => 20],
                    ['token' => 'shared-cache-next273', 'data_version' => 20],
                    ['token' => 'shared-cache-next281', 'data_version' => 20],
                    ['token' => 'shared-cache-next289', 'data_version' => 20],
                    ['token' => 'shared-cache-next297', 'data_version' => 20],
                    ['token' => 'shared-cache-next305', 'data_version' => 20],
                ],
                'reuse_acks' => [
                    [
                        'snapshot' => 'reader-ready-next305',
                        'receipt' => 'shared-cache-next305',
                        'data_version' => 20,
                        'published_count' => 11,
                        'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next305'),
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready-next305' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 11,
                'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next305'),
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'snapshotted-current-source');
    assert($plan['events'][0]['ack'] === 'shared-cache-next305');
    assert($plan['events'][1]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][1]['claim'] === 'reader-reuse-next313');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-reused-current-source');
    assert($plan['events'][2]['reuse_ack'] === 'shared-cache-next305');
    assert($plan['events'][2]['published_count'] === 12);
    assert(in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next258-265', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next266-273', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next290-297', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next298-305', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next306-313', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next306-313 self-test passed\n";
    return;
}

print_r($plan);
