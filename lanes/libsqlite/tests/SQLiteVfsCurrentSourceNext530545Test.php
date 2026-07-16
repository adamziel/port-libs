<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529');
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
                ['token' => 'shared-cache-next401', 'data_version' => 20],
                ['token' => 'shared-cache-next417', 'data_version' => 20],
                ['token' => 'shared-cache-next433', 'data_version' => 20],
                ['token' => 'shared-cache-next449', 'data_version' => 20],
                ['token' => 'shared-cache-next465', 'data_version' => 20],
                ['token' => 'shared-cache-next481', 'data_version' => 20],
                ['token' => 'shared-cache-next497', 'data_version' => 20],
                ['token' => 'shared-cache-next513', 'data_version' => 20],
                ['token' => 'shared-cache-next529', 'data_version' => 20],
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready-next529',
                    'receipt' => 'shared-cache-next529',
                    'data_version' => 20,
                    'published_count' => 25,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next529' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 25,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'snapshot(reader-ready-next545,shared-cache-next529)',
            'claim(reader-ready-next545,shared-cache-next529,reader-reuse-next545)',
            'publish(reader-ready-next545,reader-reuse-next545,shared-cache-next545)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 12, 'bytes' => 4096, 'digest' => 'dirty-next530'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next530', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next545',
        'snapshot' => 'reader-ready-next545',
        'ack' => 'shared-cache-next529',
        'data_version' => 20,
        'published_count' => 25,
        'receipt_digest' => $previousDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next545'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 25,
    'receipt_digest' => $previousDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next545',
    'receipt' => 'shared-cache-next529',
    'data_version' => 20,
    'published_count' => 25,
    'receipt_digest' => $previousDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next530', 'data_version' => 20];

return [
    'vfs current source next530-545 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next530-545', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next514-529 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next514-529', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next498-513 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next498-513', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next482-497 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next482-497', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next466-481 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next466-481', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next434-449 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next434-449', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next418-433 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next418-433', $plan()['dependencies'], true)),
    'vfs current source next530-545 records next402-417 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next402-417', $plan()['dependencies'], true)),
    'vfs current source next530-545 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next530-545 snapshot records next529 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next529', $plan()['events'][0]['ack']),
    'vfs current source next530-545 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next530-545 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next545', $plan()['events'][1]['claim']),
    'vfs current source next530-545 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next530-545 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next530-545 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next545', $plan()['events'][2]['claim']),
    'vfs current source next530-545 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next529', $plan()['events'][2]['reuse_ack']),
    'vfs current source next530-545 publish count advances' => static fn (TestRunner $t) => $t->same(26, $plan()['events'][2]['published_count']),
    'vfs current source next530-545 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready-next545,shared-cache-next529)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next530-545 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready-next545,shared-cache-next529)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next530-545 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready-next545,shared-cache-next529)', 'publish(reader-ready-next545,reader-reuse-next545,shared-cache-next545)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next530-545 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next545,reader-reuse-next545,shared-cache-next545)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next530-545 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next545,reader-reuse-next545,shared-cache-next545)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next530-545 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next530-545 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next545', 'ack' => 'shared-cache-next529', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next530-545 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['republish(reader-ready-next545,shared-cache-next545)'], ['current' => $readyCurrent])),
    'vfs current source next530-545 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next514-529')),
];
