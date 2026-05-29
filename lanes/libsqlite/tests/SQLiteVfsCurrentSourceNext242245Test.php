<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext242245Plan;

$publishedDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237');
$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 18,
            'published' => [
                ['token' => 'publish-next217', 'data_version' => 18],
                ['token' => 'shared-cache-next229', 'data_version' => 18],
                ['token' => 'shared-cache-next237', 'data_version' => 18],
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready',
                    'receipt' => 'shared-cache-next229',
                    'data_version' => 18,
                    'published_count' => 2,
                    'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229'),
                ],
                [
                    'snapshot' => 'reader-ready-republished',
                    'receipt' => 'shared-cache-next237',
                    'data_version' => 18,
                    'published_count' => 3,
                    'receipt_digest' => $publishedDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-republished' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 18,
            'published_count' => 3,
            'receipt_digest' => $publishedDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext242245Plan::run([
            'claim(reader-ready-republished,shared-cache-next237,reader-reuse-next245)',
            'publish(reader-ready-republished,reader-reuse-next245,shared-cache-next245)',
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
        'token' => 'reader-reuse-next245',
        'snapshot' => 'reader-ready-republished',
        'ack' => 'shared-cache-next237',
        'data_version' => 18,
        'published_count' => 3,
        'receipt_digest' => $publishedDigest,
    ],
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next244', 'data_version' => 18];

return [
    'vfs current source next242-245 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next242-245 records next234-237 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan()['dependencies'], true)),
    'vfs current source next242-245 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][0]['status']),
    'vfs current source next242-245 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next245', $plan()['events'][0]['claim']),
    'vfs current source next242-245 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][0]['blocked_reasons']),
    'vfs current source next242-245 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][1]['status']),
    'vfs current source next242-245 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next245', $plan()['events'][1]['claim']),
    'vfs current source next242-245 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next237', $plan()['events'][1]['reuse_ack']),
    'vfs current source next242-245 publish count advances' => static fn (TestRunner $t) => $t->same(4, $plan()['events'][1]['published_count']),
    'vfs current source next242-245 blocks dirty claim' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext242245Plan::run(['claim(reader-ready-republished,shared-cache-next237,reader-reuse-next245)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next242-245 blocks stale ack claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-ack', SQLiteVfsCurrentSourceNext242245Plan::run(['claim(reader-ready-republished,shared-cache-next237,reader-reuse-next245)'], ['current' => $staleAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next242-245 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext242245Plan::run(['publish(reader-ready-republished,reader-reuse-next245,shared-cache-next245)'], ['current' => $readyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next242-245 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext242245Plan::run(['publish(reader-ready-republished,reader-reuse-next245,shared-cache-next245)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next242-245 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext242245Plan::run(['publish(reader-ready-republished,reader-reuse-next245,shared-cache-next245)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next242-245 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext242245Plan::run([])),
    'vfs current source next242-245 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext242245Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-republished', 'ack' => 'shared-cache-next237', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next242-245 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext242245Plan::run(['republish(reader-ready-republished,shared-cache-next245)'], ['current' => $readyCurrent])),
    'vfs current source next242-245 notes active window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'active next238-241')),
];
