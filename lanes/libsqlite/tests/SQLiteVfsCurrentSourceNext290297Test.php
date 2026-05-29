<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext290297Plan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273');
$publishedDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289');
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
                    'snapshot' => 'reader-ready-next273',
                    'receipt' => 'shared-cache-next273',
                    'data_version' => 20,
                    'published_count' => 7,
                    'receipt_digest' => $previousDigest,
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
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext290297Plan::run([
            'snapshot(reader-ready-next297,shared-cache-next289)',
            'claim(reader-ready-next297,shared-cache-next289,reader-reuse-next297)',
            'publish(reader-ready-next297,reader-reuse-next297,shared-cache-next297)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 8, 'bytes' => 4096, 'digest' => 'dirty-next242'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next290', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next297',
        'snapshot' => 'reader-ready-next297',
        'ack' => 'shared-cache-next289',
        'data_version' => 20,
        'published_count' => 9,
        'receipt_digest' => $publishedDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next297'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 9,
    'receipt_digest' => $publishedDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next297',
    'receipt' => 'shared-cache-next289',
    'data_version' => 20,
    'published_count' => 9,
    'receipt_digest' => $publishedDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next244', 'data_version' => 20];

return [
    'vfs current source next290-297 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next290-297', $plan()['dependencies'], true)),
    'vfs current source next290-297 records next282-289 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next282-289', $plan()['dependencies'], true)),
    'vfs current source next290-297 records next234-237 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan()['dependencies'], true)),
    'vfs current source next290-297 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next290-297 snapshot records ack' => static fn (TestRunner $t) => $t->same('shared-cache-next289', $plan()['events'][0]['ack']),
    'vfs current source next290-297 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next290-297 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next297', $plan()['events'][1]['claim']),
    'vfs current source next290-297 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next290-297 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next290-297 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next297', $plan()['events'][2]['claim']),
    'vfs current source next290-297 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next289', $plan()['events'][2]['reuse_ack']),
    'vfs current source next290-297 publish count advances' => static fn (TestRunner $t) => $t->same(10, $plan()['events'][2]['published_count']),
    'vfs current source next290-297 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext290297Plan::run(['snapshot(reader-ready-next297,shared-cache-next289)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next290-297 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext290297Plan::run(['snapshot(reader-ready-next297,shared-cache-next289)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next290-297 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext290297Plan::run(['snapshot(reader-ready-next297,shared-cache-next289)', 'publish(reader-ready-next297,reader-reuse-next297,shared-cache-next297)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next290-297 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext290297Plan::run(['publish(reader-ready-next297,reader-reuse-next297,shared-cache-next297)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next290-297 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext290297Plan::run(['publish(reader-ready-next297,reader-reuse-next297,shared-cache-next297)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next290-297 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext290297Plan::run([])),
    'vfs current source next290-297 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext290297Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next297', 'ack' => 'shared-cache-next289', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next290-297 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext290297Plan::run(['republish(reader-ready-next297,shared-cache-next289)'], ['current' => $readyCurrent])),
    'vfs current source next290-297 records next242-245 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next290-297 records next258-265 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next258-265', $plan()['dependencies'], true)),
    'vfs current source next290-297 records next266-273 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next266-273', $plan()['dependencies'], true)),
    'vfs current source next290-297 records next274-281 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next274-281', $plan()['dependencies'], true)),
    'vfs current source next290-297 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next282-289')),
];
