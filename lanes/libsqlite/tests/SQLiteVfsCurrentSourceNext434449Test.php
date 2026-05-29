<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext434449Plan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433');
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
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready-next433',
                    'receipt' => 'shared-cache-next433',
                    'data_version' => 20,
                    'published_count' => 19,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next433' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 19,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext434449Plan::run([
            'snapshot(reader-ready-next449,shared-cache-next433)',
            'claim(reader-ready-next449,shared-cache-next433,reader-reuse-next449)',
            'publish(reader-ready-next449,reader-reuse-next449,shared-cache-next449)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 12, 'bytes' => 4096, 'digest' => 'dirty-next434'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next434', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next449',
        'snapshot' => 'reader-ready-next449',
        'ack' => 'shared-cache-next433',
        'data_version' => 20,
        'published_count' => 19,
        'receipt_digest' => $previousDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next449'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 19,
    'receipt_digest' => $previousDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next449',
    'receipt' => 'shared-cache-next433',
    'data_version' => 20,
    'published_count' => 19,
    'receipt_digest' => $previousDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next434', 'data_version' => 20];

return [
    'vfs current source next434-449 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next434-449', $plan()['dependencies'], true)),
    'vfs current source next434-449 records next418-433 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next418-433', $plan()['dependencies'], true)),
    'vfs current source next434-449 records next402-417 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next402-417', $plan()['dependencies'], true)),
    'vfs current source next434-449 records next386-401 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next386-401', $plan()['dependencies'], true)),
    'vfs current source next434-449 records next370-385 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next370-385', $plan()['dependencies'], true)),
    'vfs current source next434-449 records next354-369 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next354-369', $plan()['dependencies'], true)),
    'vfs current source next434-449 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next434-449 snapshot records next433 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next433', $plan()['events'][0]['ack']),
    'vfs current source next434-449 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next434-449 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next449', $plan()['events'][1]['claim']),
    'vfs current source next434-449 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next434-449 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next434-449 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next449', $plan()['events'][2]['claim']),
    'vfs current source next434-449 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next433', $plan()['events'][2]['reuse_ack']),
    'vfs current source next434-449 publish count advances' => static fn (TestRunner $t) => $t->same(20, $plan()['events'][2]['published_count']),
    'vfs current source next434-449 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext434449Plan::run(['snapshot(reader-ready-next449,shared-cache-next433)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next434-449 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext434449Plan::run(['snapshot(reader-ready-next449,shared-cache-next433)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next434-449 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext434449Plan::run(['snapshot(reader-ready-next449,shared-cache-next433)', 'publish(reader-ready-next449,reader-reuse-next449,shared-cache-next449)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next434-449 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext434449Plan::run(['publish(reader-ready-next449,reader-reuse-next449,shared-cache-next449)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next434-449 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext434449Plan::run(['publish(reader-ready-next449,reader-reuse-next449,shared-cache-next449)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next434-449 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext434449Plan::run([])),
    'vfs current source next434-449 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext434449Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next449', 'ack' => 'shared-cache-next433', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next434-449 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext434449Plan::run(['republish(reader-ready-next449,shared-cache-next433)'], ['current' => $readyCurrent])),
    'vfs current source next434-449 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next418-433')),
];
