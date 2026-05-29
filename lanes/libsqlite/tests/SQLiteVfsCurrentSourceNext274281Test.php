<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$publishedDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273');
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
                    'receipt_digest' => $publishedDigest,
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
            'receipt_digest' => $publishedDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'claim(reader-ready-next273,shared-cache-next273,reader-reuse-next281)',
            'publish(reader-ready-next273,reader-reuse-next281,shared-cache-next281)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 8, 'bytes' => 4096, 'digest' => 'dirty-next242'],
];

$staleAckCurrent = $readyCurrent;
$staleAckCurrent['sources']['main']['reuse_acks'][1]['published_count'] = 2;

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next281',
        'snapshot' => 'reader-ready-next273',
        'ack' => 'shared-cache-next273',
        'data_version' => 20,
        'published_count' => 7,
        'receipt_digest' => $publishedDigest,
    ],
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next244', 'data_version' => 20];

return [
    'vfs current source next274-281 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next274-281', $plan()['dependencies'], true)),
    'vfs current source next274-281 records next234-237 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan()['dependencies'], true)),
    'vfs current source next274-281 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][0]['status']),
    'vfs current source next274-281 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next281', $plan()['events'][0]['claim']),
    'vfs current source next274-281 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][0]['blocked_reasons']),
    'vfs current source next274-281 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][1]['status']),
    'vfs current source next274-281 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next281', $plan()['events'][1]['claim']),
    'vfs current source next274-281 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next273', $plan()['events'][1]['reuse_ack']),
    'vfs current source next274-281 publish count advances' => static fn (TestRunner $t) => $t->same(8, $plan()['events'][1]['published_count']),
    'vfs current source next274-281 blocks dirty claim' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNextPlan::run(['claim(reader-ready-next273,shared-cache-next273,reader-reuse-next281)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next274-281 blocks stale ack claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-ack', SQLiteVfsCurrentSourceNextPlan::run(['claim(reader-ready-next273,shared-cache-next273,reader-reuse-next281)'], ['current' => $staleAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next274-281 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next273,reader-reuse-next281,shared-cache-next281)'], ['current' => $readyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next274-281 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next273,reader-reuse-next281,shared-cache-next281)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next274-281 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready-next273,reader-reuse-next281,shared-cache-next281)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next274-281 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next274-281 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next273', 'ack' => 'shared-cache-next273', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next274-281 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['republish(reader-ready-next273,shared-cache-next273)'], ['current' => $readyCurrent])),
    'vfs current source next274-281 records next242-245 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next274-281 records next258-265 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next258-265', $plan()['dependencies'], true)),
    'vfs current source next274-281 records next266-273 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next266-273', $plan()['dependencies'], true)),
    'vfs current source next274-281 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next266-273')),
];
