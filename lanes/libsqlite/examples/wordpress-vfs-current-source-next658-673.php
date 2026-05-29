<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVfsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

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
];
$publishedDigest = hash('sha256', implode('|', $published));

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-ready-next673,shared-cache-next657)',
    'claim(reader-ready-next673,shared-cache-next657,reader-reuse-next673)',
    'publish(reader-ready-next673,reader-reuse-next673,shared-cache-next673)',
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
            'reader-ready-next657' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 32,
                'receipt_digest' => $publishedDigest,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'snapshotted-current-source');
    assert($plan['events'][0]['ack'] === 'shared-cache-next657');
    assert($plan['events'][1]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][1]['claim'] === 'reader-reuse-next673');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-reused-current-source');
    assert($plan['events'][2]['reuse_ack'] === 'shared-cache-next657');
    assert($plan['events'][2]['token'] === 'shared-cache-next673');
    assert($plan['events'][2]['published_count'] === 33);
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next658-673', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next642-657', $plan['dependencies'], true));
    echo "wordpress-vfs-current-source-next658-673 self-test passed\n";
    return;
}

print_r($plan);
