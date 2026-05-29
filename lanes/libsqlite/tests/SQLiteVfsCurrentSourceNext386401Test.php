<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext386401Plan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385');
$readyCurrent = [
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
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready-next385',
                    'receipt' => 'shared-cache-next385',
                    'data_version' => 20,
                    'published_count' => 16,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next385' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 16,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext386401Plan::run([
            'snapshot(reader-ready-next401,shared-cache-next385)',
            'claim(reader-ready-next401,shared-cache-next385,reader-reuse-next401)',
            'publish(reader-ready-next401,reader-reuse-next401,shared-cache-next401)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 12, 'bytes' => 4096, 'digest' => 'dirty-next386'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next386', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next401',
        'snapshot' => 'reader-ready-next401',
        'ack' => 'shared-cache-next385',
        'data_version' => 20,
        'published_count' => 16,
        'receipt_digest' => $previousDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next401'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 16,
    'receipt_digest' => $previousDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next401',
    'receipt' => 'shared-cache-next385',
    'data_version' => 20,
    'published_count' => 16,
    'receipt_digest' => $previousDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next386', 'data_version' => 20];

return [
    'vfs current source next386-401 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next386-401', $plan()['dependencies'], true)),
    'vfs current source next386-401 records next370-385 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next370-385', $plan()['dependencies'], true)),
    'vfs current source next386-401 records next354-369 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next354-369', $plan()['dependencies'], true)),
    'vfs current source next386-401 records next322-337 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next322-337', $plan()['dependencies'], true)),
    'vfs current source next386-401 records next314-321 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next314-321', $plan()['dependencies'], true)),
    'vfs current source next386-401 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next386-401 snapshot records next385 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next385', $plan()['events'][0]['ack']),
    'vfs current source next386-401 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next386-401 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next401', $plan()['events'][1]['claim']),
    'vfs current source next386-401 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next386-401 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next386-401 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next401', $plan()['events'][2]['claim']),
    'vfs current source next386-401 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next385', $plan()['events'][2]['reuse_ack']),
    'vfs current source next386-401 publish count advances' => static fn (TestRunner $t) => $t->same(17, $plan()['events'][2]['published_count']),
    'vfs current source next386-401 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext386401Plan::run(['snapshot(reader-ready-next401,shared-cache-next385)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next386-401 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext386401Plan::run(['snapshot(reader-ready-next401,shared-cache-next385)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next386-401 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext386401Plan::run(['snapshot(reader-ready-next401,shared-cache-next385)', 'publish(reader-ready-next401,reader-reuse-next401,shared-cache-next401)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next386-401 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext386401Plan::run(['publish(reader-ready-next401,reader-reuse-next401,shared-cache-next401)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next386-401 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext386401Plan::run(['publish(reader-ready-next401,reader-reuse-next401,shared-cache-next401)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next386-401 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext386401Plan::run([])),
    'vfs current source next386-401 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext386401Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next401', 'ack' => 'shared-cache-next385', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next386-401 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext386401Plan::run(['republish(reader-ready-next401,shared-cache-next385)'], ['current' => $readyCurrent])),
    'vfs current source next386-401 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next370-385')),
];
