<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$publishedDigest = hash('sha256', 'publish-next217|shared-cache-next229');
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
            ],
            'reuse_leases' => [
                [
                    'token' => 'reader-ready-next229',
                    'snapshot' => 'reader-ready',
                    'data_version' => 18,
                    'published_count' => 1,
                    'receipt_digest' => hash('sha256', 'publish-next217'),
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 18,
            'published_count' => 2,
            'receipt_digest' => $publishedDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'ack(reader-ready,shared-cache-next229)',
            'republish(reader-ready,shared-cache-next237)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 3, 'bytes' => 4096, 'digest' => 'dirty-next234'],
];

$missingReceiptCurrent = $readyCurrent;
$missingReceiptCurrent['sources']['main']['published'] = [
    ['token' => 'publish-next217', 'data_version' => 18],
];
$missingReceiptCurrent['snapshots']['reader-ready']['published_count'] = 1;
$missingReceiptCurrent['snapshots']['reader-ready']['receipt_digest'] = hash('sha256', 'publish-next217');

$staleAckCurrent = $readyCurrent;
$staleAckCurrent['sources']['main']['reuse_acks'] = [
    [
        'snapshot' => 'reader-ready',
        'receipt' => 'shared-cache-next229',
        'data_version' => 17,
        'published_count' => 2,
        'receipt_digest' => $publishedDigest,
    ],
];

return [
    'vfs current source next234-237 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-ack-publish-next234-237', $plan()['dependencies'], true)),
    'vfs current source next234-237 records next226-229 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-lease-publish-next226-229', $plan()['dependencies'], true)),
    'vfs current source next234-237 records next218-221 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-publish-next218-221', $plan()['dependencies'], true)),
    'vfs current source next234-237 acknowledges reused publish' => static fn (TestRunner $t) => $t->same('acknowledged-reuse-publish', $plan()['events'][0]['status']),
    'vfs current source next234-237 records ack receipt' => static fn (TestRunner $t) => $t->same('shared-cache-next229', $plan()['events'][0]['receipt']),
    'vfs current source next234-237 ack has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][0]['blocked_reasons']),
    'vfs current source next234-237 republishes current source' => static fn (TestRunner $t) => $t->same('republished-current-source', $plan()['events'][1]['status']),
    'vfs current source next234-237 republish uses ack' => static fn (TestRunner $t) => $t->same('shared-cache-next229', $plan()['events'][1]['reuse_ack']),
    'vfs current source next234-237 publish count advances' => static fn (TestRunner $t) => $t->same(3, $plan()['events'][1]['published_count']),
    'vfs current source next234-237 blocks dirty ack' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNextPlan::run(['ack(reader-ready,shared-cache-next229)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next234-237 blocks missing receipt ack' => static fn (TestRunner $t) => $t->same(true, in_array('missing-publish-receipt', SQLiteVfsCurrentSourceNextPlan::run(['ack(reader-ready,shared-cache-next229)'], ['current' => $missingReceiptCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next234-237 blocks republish without ack' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-ack', SQLiteVfsCurrentSourceNextPlan::run(['republish(reader-ready,shared-cache-next237)'], ['current' => $readyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next234-237 blocks stale ack' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-ack', SQLiteVfsCurrentSourceNextPlan::run(['republish(reader-ready,shared-cache-next237)'], ['current' => $staleAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next234-237 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next234-237 rejects bad ack receipt' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'ack', 'snapshot' => 'reader-ready', 'receipt' => 'bad receipt']], ['current' => $readyCurrent])),
    'vfs current source next234-237 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready,shared-cache-next237)'], ['current' => $readyCurrent])),
    'vfs current source next234-237 notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'parallel next230-233')),
];
