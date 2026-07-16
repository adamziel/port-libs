<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'claim(reader-ready-next273,shared-cache-next273,reader-reuse-next281)',
    'publish(reader-ready-next273,reader-reuse-next281,shared-cache-next281)',
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
                ],
                'reuse_acks' => [
                    [
                        'snapshot' => 'reader-ready-next273',
                        'receipt' => 'shared-cache-next273',
                        'data_version' => 20,
                        'published_count' => 7,
                        'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273'),
                    ],
                ],
            ],
        ],
        'snapshots' => [
            'reader-ready-next273' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 7,
                'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273'),
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][0]['claim'] === 'reader-reuse-next281');
    assert($plan['events'][0]['blocked_reasons'] === []);
    assert($plan['events'][1]['status'] === 'published-reused-current-source');
    assert($plan['events'][1]['reuse_ack'] === 'shared-cache-next273');
    assert($plan['events'][1]['published_count'] === 8);
    assert(in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next258-265', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next266-273', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next274-281', $plan['dependencies'], true));
    echo "application-vfs-current-source-next274-281 self-test passed\n";
    return;
}

print_r($plan);
