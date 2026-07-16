<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313');
$publishedDigest = $previousDigest;
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
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready',
                    'receipt' => 'shared-cache-next229',
                    'data_version' => 20,
                    'published_count' => 2,
                    'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229'),
                ],
                [
                    'snapshot' => 'reader-ready-next313',
                    'receipt' => 'shared-cache-next313',
                    'data_version' => 20,
                    'published_count' => 11,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next313' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 11,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'snapshot(reader-ready-next321,shared-cache-next313)',
            'claim(reader-ready-next321,shared-cache-next313,reader-reuse-next321)',
            'publish(reader-ready-next321,reader-reuse-next321,shared-cache-next321)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 8, 'bytes' => 4096, 'digest' => 'dirty-next242'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next314', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next321',
        'snapshot' => 'reader-ready-next321',
        'ack' => 'shared-cache-next313',
        'data_version' => 20,
        'published_count' => 11,
        'receipt_digest' => $publishedDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next321'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 11,
    'receipt_digest' => $publishedDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next321',
    'receipt' => 'shared-cache-next313',
    'data_version' => 20,
    'published_count' => 11,
    'receipt_digest' => $publishedDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next314', 'data_version' => 20];

return [
    'vfs current source next314-321 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next314-321', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next306-313 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next306-313', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next298-305 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next298-305', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next234-237 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan()['dependencies'], true)),
    'vfs current source next314-321 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next314-321 snapshot records ack' => static fn (TestRunner $t) => $t->same('shared-cache-next313', $plan()['events'][0]['ack']),
    'vfs current source next314-321 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next314-321 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next321', $plan()['events'][1]['claim']),
    'vfs current source next314-321 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next314-321 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next314-321 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next321', $plan()['events'][2]['claim']),
    'vfs current source next314-321 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next313', $plan()['events'][2]['reuse_ack']),
    'vfs current source next314-321 publish count advances' => static fn (TestRunner $t) => $t->same(12, $plan()['events'][2]['published_count']),
    'vfs current source next314-321 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready-next321,shared-cache-next313)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next314-321 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready-next321,shared-cache-next313)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next314-321 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready-next321,shared-cache-next313)', 'publish(reader-ready-next321,reader-reuse-next321,shared-cache-next321)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next314-321 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next321,reader-reuse-next321,shared-cache-next321)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next314-321 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next321,reader-reuse-next321,shared-cache-next321)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next314-321 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next314-321 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next321', 'ack' => 'shared-cache-next313', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next314-321 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['republish(reader-ready-next321,shared-cache-next313)'], ['current' => $readyCurrent])),
    'vfs current source next314-321 records next242-245 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next258-265 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next258-265', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next266-273 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next266-273', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next274-281 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next274-281', $plan()['dependencies'], true)),
    'vfs current source next314-321 records next290-297 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next290-297', $plan()['dependencies'], true)),
    'vfs current source next314-321 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next306-313')),
];
