<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext254257Plan;

$publishedDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245');
$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 19,
            'published' => [
                ['token' => 'publish-next217', 'data_version' => 19],
                ['token' => 'shared-cache-next229', 'data_version' => 19],
                ['token' => 'shared-cache-next237', 'data_version' => 19],
                ['token' => 'shared-cache-next245', 'data_version' => 19],
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready',
                    'receipt' => 'shared-cache-next229',
                    'data_version' => 19,
                    'published_count' => 2,
                    'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229'),
                ],
                [
                    'snapshot' => 'reader-ready-next245',
                    'receipt' => 'shared-cache-next245',
                    'data_version' => 19,
                    'published_count' => 4,
                    'receipt_digest' => $publishedDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next245' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 19,
            'published_count' => 4,
            'receipt_digest' => $publishedDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext254257Plan::run([
            'claim(reader-ready-next245,shared-cache-next245,reader-reuse-next257)',
            'publish(reader-ready-next245,reader-reuse-next257,shared-cache-next257)',
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
        'token' => 'reader-reuse-next257',
        'snapshot' => 'reader-ready-next245',
        'ack' => 'shared-cache-next245',
        'data_version' => 19,
        'published_count' => 4,
        'receipt_digest' => $publishedDigest,
    ],
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next244', 'data_version' => 19];

return [
    'vfs current source next254-257 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next254-257', $plan()['dependencies'], true)),
    'vfs current source next254-257 records next234-237 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan()['dependencies'], true)),
    'vfs current source next254-257 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][0]['status']),
    'vfs current source next254-257 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next257', $plan()['events'][0]['claim']),
    'vfs current source next254-257 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][0]['blocked_reasons']),
    'vfs current source next254-257 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][1]['status']),
    'vfs current source next254-257 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next257', $plan()['events'][1]['claim']),
    'vfs current source next254-257 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next245', $plan()['events'][1]['reuse_ack']),
    'vfs current source next254-257 publish count advances' => static fn (TestRunner $t) => $t->same(5, $plan()['events'][1]['published_count']),
    'vfs current source next254-257 blocks dirty claim' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext254257Plan::run(['claim(reader-ready-next245,shared-cache-next245,reader-reuse-next257)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next254-257 blocks stale ack claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-ack', SQLiteVfsCurrentSourceNext254257Plan::run(['claim(reader-ready-next245,shared-cache-next245,reader-reuse-next257)'], ['current' => $staleAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next254-257 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext254257Plan::run(['publish(reader-ready-next245,reader-reuse-next257,shared-cache-next257)'], ['current' => $readyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next254-257 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext254257Plan::run(['publish(reader-ready-next245,reader-reuse-next257,shared-cache-next257)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next254-257 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext254257Plan::run(['publish(reader-ready-next245,reader-reuse-next257,shared-cache-next257)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next254-257 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext254257Plan::run([])),
    'vfs current source next254-257 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext254257Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next245', 'ack' => 'shared-cache-next245', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next254-257 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext254257Plan::run(['republish(reader-ready-next245,shared-cache-next257)'], ['current' => $readyCurrent])),
    'vfs current source next254-257 records next242-245 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next254-257 notes active window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'active next246-253')),
];
