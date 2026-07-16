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
    'shared-cache-next673',
    'shared-cache-next689',
    'shared-cache-next705',
    'shared-cache-next721',
    'shared-cache-next737',
    'shared-cache-next753',
    'shared-cache-next769',
    'shared-cache-next785',
    'shared-cache-next801',
    'shared-cache-next817',
    'shared-cache-next833',
    'shared-cache-next849',
    'shared-cache-next865',
    'shared-cache-next881',
    'shared-cache-next897',
    'shared-cache-next913',
    'shared-cache-next929',
    'shared-cache-next945',
    'shared-cache-next961',
    'shared-cache-next977',
    'shared-cache-next993',
    'shared-cache-next1009',
    'shared-cache-next1025',
    'shared-cache-next1041',
    'shared-cache-next1057',
    'shared-cache-next1073',
    'shared-cache-next1089',
    'shared-cache-next1105',
];
$publishedDigest = hash('sha256', implode('|', $published));

$plan = SQLiteVfsCurrentSourceNextPlan::run([
    'snapshot(reader-ready-next1121,shared-cache-next1105)',
    'claim(reader-ready-next1121,shared-cache-next1105,reader-reuse-next1121)',
    'publish(reader-ready-next1121,reader-reuse-next1121,shared-cache-next1121)',
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
            'reader-ready-next1105' => [
                'source' => 'main',
                'handle' => 'vfs214217-1',
                'path' => '/srv/www/wp-content/database/wp.sqlite',
                'owner' => '/srv/www/wp-content/database/wp.sqlite',
                'data_version' => 20,
                'published_count' => 60,
                'receipt_digest' => $publishedDigest,
            ],
        ],
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['events'][0]['status'] === 'snapshotted-current-source');
    assert($plan['events'][0]['ack'] === 'shared-cache-next1105');
    assert($plan['events'][0]['blocked_reasons'] === []);
    assert($plan['events'][1]['status'] === 'claimed-reusable-current-source');
    assert($plan['events'][1]['claim'] === 'reader-reuse-next1121');
    assert($plan['events'][1]['blocked_reasons'] === []);
    assert($plan['events'][2]['status'] === 'published-reused-current-source');
    assert($plan['events'][2]['reuse_ack'] === 'shared-cache-next1105');
    assert($plan['events'][2]['token'] === 'shared-cache-next1121');
    assert($plan['events'][2]['published_count'] === 61);
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next1106-1121', $plan['dependencies'], true));
    assert(in_array('vfs-current-source-snapshot-reuse-publish-next1090-1105', $plan['dependencies'], true));
    echo "application-vfs-current-source-next1106-1121 self-test passed\n";
    return;
}

print_r($plan);
