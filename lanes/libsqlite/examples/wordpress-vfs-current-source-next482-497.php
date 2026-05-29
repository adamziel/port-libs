<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext482497Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext482497Plan;

$publishedDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481');

$plan = SQLiteVfsCurrentSourceNext482497Plan::run([
    'snapshot(reader-ready-next497,shared-cache-next481)',
    'claim(reader-ready-next497,shared-cache-next481,reader-reuse-next497)',
    'publish(reader-ready-next497,reader-reuse-next497,shared-cache-next497)',
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
                    ['token' => 'shared-cache-next313', 'data_version' => 20],
                    ['token' => 'shared-cache-next321', 'data_version' => 20],
                    ['token' => 'shared-cache-next337', 'data_version' => 20],
                    ['token' => 'shared-cache-next353', 'data_version' => 20],
                    ['token' => 'shared-cache-next369', 'data_version' => 20],
                    ['token' => 'shared-cache-next385', 'data_version' => 20],
                    ['token' => 'shared-cache-next401', 'data_version' => 20],
                    ['token' => 'shared-cache-next417', 'data_version' => 20],
                    ['token' => 'shared-cache-next433', 'data_version' => 20],
                    ['token' => 'shared-cache-next449', 'data_version' => 20],
                    ['token' => 'shared-cache-next465', 'data_version' => 20],
                    ['token' => 'shared-cache-next481', 'data_version' => 20],
                ],
                'reuse_acks' => [
                    [
                        'snapshot' => 'reader-ready-next481',
                        'receipt' => 'shared-cache-next481',
                        'data_version' => 20,
                        'published_count' => 22,
                        'receipt_digest' => $publishedDigest,
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready-next481' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 22,
                'receipt_digest' => $publishedDigest,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'snapshotted-current-source');
    assert($plan['events'][0]['ack'] === 'shared-cache-next481');
    assert($plan['events'][1]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][1]['claim'] === 'reader-reuse-next497');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-reused-current-source');
    assert($plan['events'][2]['reuse_ack'] === 'shared-cache-next481');
    assert($plan['events'][2]['token'] === 'shared-cache-next497');
    assert($plan['events'][2]['published_count'] === 23);
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next482-497', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next466-481', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next450-465', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next482-497 self-test passed\n";
    return;
}

print_r($plan);
