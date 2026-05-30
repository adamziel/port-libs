<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-ready-next353,shared-cache-next337)',
    'claim(reader-ready-next353,shared-cache-next337,reader-reuse-next353)',
    'publish(reader-ready-next353,reader-reuse-next353,shared-cache-next353)',
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
                ],
                'reuse_acks' => [
                    [
                        'snapshot' => 'reader-ready-next337',
                        'receipt' => 'shared-cache-next337',
                        'data_version' => 20,
                        'published_count' => 13,
                        'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337'),
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready-next337' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 13,
                'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337'),
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'snapshotted-current-source');
    assert($plan['events'][0]['ack'] === 'shared-cache-next337');
    assert($plan['events'][1]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][1]['claim'] === 'reader-reuse-next353');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-reused-current-source');
    assert($plan['events'][2]['reuse_ack'] === 'shared-cache-next337');
    assert($plan['events'][2]['published_count'] === 14);
    assert(in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next266-273', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next274-281', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next298-305', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next306-313', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next314-321', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next322-337', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next338-353', $plan['dependencies'], true));
    echo "application-vfs-current-source-next338-353 self-test passed\n";
    return;
}

print_r($plan);
