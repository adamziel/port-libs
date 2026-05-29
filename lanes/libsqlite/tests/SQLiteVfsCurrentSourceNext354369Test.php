<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext354369Plan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353');
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
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready-next353',
                    'receipt' => 'shared-cache-next353',
                    'data_version' => 20,
                    'published_count' => 14,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next353' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 14,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext354369Plan::run([
            'snapshot(reader-ready-next369,shared-cache-next353)',
            'claim(reader-ready-next369,shared-cache-next353,reader-reuse-next369)',
            'publish(reader-ready-next369,reader-reuse-next369,shared-cache-next369)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 12, 'bytes' => 4096, 'digest' => 'dirty-next362'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next354', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next369',
        'snapshot' => 'reader-ready-next369',
        'ack' => 'shared-cache-next353',
        'data_version' => 20,
        'published_count' => 14,
        'receipt_digest' => $previousDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next369'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 14,
    'receipt_digest' => $previousDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next369',
    'receipt' => 'shared-cache-next353',
    'data_version' => 20,
    'published_count' => 14,
    'receipt_digest' => $previousDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next354', 'data_version' => 20];

return [
    'vfs current source next354-369 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next354-369', $plan()['dependencies'], true)),
    'vfs current source next354-369 records next338-353 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next338-353', $plan()['dependencies'], true)),
    'vfs current source next354-369 records next322-337 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next322-337', $plan()['dependencies'], true)),
    'vfs current source next354-369 records next314-321 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next314-321', $plan()['dependencies'], true)),
    'vfs current source next354-369 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next354-369 snapshot records next353 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next353', $plan()['events'][0]['ack']),
    'vfs current source next354-369 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next354-369 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next369', $plan()['events'][1]['claim']),
    'vfs current source next354-369 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next354-369 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next354-369 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next369', $plan()['events'][2]['claim']),
    'vfs current source next354-369 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next353', $plan()['events'][2]['reuse_ack']),
    'vfs current source next354-369 publish count advances' => static fn (TestRunner $t) => $t->same(15, $plan()['events'][2]['published_count']),
    'vfs current source next354-369 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext354369Plan::run(['snapshot(reader-ready-next369,shared-cache-next353)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next354-369 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext354369Plan::run(['snapshot(reader-ready-next369,shared-cache-next353)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next354-369 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext354369Plan::run(['snapshot(reader-ready-next369,shared-cache-next353)', 'publish(reader-ready-next369,reader-reuse-next369,shared-cache-next369)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next354-369 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext354369Plan::run(['publish(reader-ready-next369,reader-reuse-next369,shared-cache-next369)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next354-369 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext354369Plan::run(['publish(reader-ready-next369,reader-reuse-next369,shared-cache-next369)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next354-369 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext354369Plan::run([])),
    'vfs current source next354-369 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext354369Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next369', 'ack' => 'shared-cache-next353', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next354-369 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext354369Plan::run(['republish(reader-ready-next369,shared-cache-next353)'], ['current' => $readyCurrent])),
    'vfs current source next354-369 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next338-353')),
];
