<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNext626641Plan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext626641Plan;

$published = [
    'publish-next217',
    'shared-cache-next229',
    'shared-cache-next237',
    'shared-cache-next245',
    'shared-cache-next257',
    'shared-cache-next265',
    'shared-cache-next273',
    'shared-cache-next281',
    'shared-cache-next289',
    'shared-cache-next297',
    'shared-cache-next313',
    'shared-cache-next321',
    'shared-cache-next337',
    'shared-cache-next353',
    'shared-cache-next369',
    'shared-cache-next385',
    'shared-cache-next401',
    'shared-cache-next417',
    'shared-cache-next433',
    'shared-cache-next449',
    'shared-cache-next465',
    'shared-cache-next481',
    'shared-cache-next497',
    'shared-cache-next513',
    'shared-cache-next529',
    'shared-cache-next545',
    'shared-cache-next561',
    'shared-cache-next577',
    'shared-cache-next593',
    'shared-cache-next625',
    'shared-cache-next641',
    'shared-cache-next657',
    'shared-cache-next673',
    'shared-cache-next689',
    'shared-cache-next705',
    'shared-cache-next721',
    'shared-cache-next737',
    'shared-cache-next753',
    'shared-cache-next769',
    'shared-cache-next785',
];
$publishedDigest = hash('sha256', implode('|', $published));

$plan = SQLiteVfsCurrentSourceNext626641Plan::run([
    'snapshot(reader-ready-next801,shared-cache-next785)',
    'claim(reader-ready-next801,shared-cache-next785,reader-reuse-next801)',
    'publish(reader-ready-next801,reader-reuse-next801,shared-cache-next801)',
], [
    'current' => [
        'current_source' => 'main',
        'sources' => [
            'main' => [
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published' => array_map(
                    static fn (string $token): array => ['token' => $token, 'data_version' => 20],
                    $published
                ),
            ],
        ],
        'snapshots' => [
            'reader-ready-next785' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 40,
                'receipt_digest' => $publishedDigest,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'snapshotted-current-source');
    assert($plan['events'][0]['ack'] === 'shared-cache-next785');
    assert($plan['events'][0]['blocked_reasons'] === []);
    assert($plan['events'][1]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][1]['claim'] === 'reader-reuse-next801');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-reused-current-source');
    assert($plan['events'][2]['reuse_ack'] === 'shared-cache-next785');
    assert($plan['events'][2]['token'] === 'shared-cache-next801');
    assert($plan['events'][2]['published_count'] === 41);
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next786-801', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next770-785', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next786-801 self-test passed\n";
    return;
}

print_r($plan);
