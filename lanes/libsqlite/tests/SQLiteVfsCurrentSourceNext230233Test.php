<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 22,
            'ready_receipts' => [
                ['token' => 'ready-next214-217', 'data_version' => 18],
                ['token' => 'ready-next225', 'data_version' => 22],
            ],
            'published' => [
                ['token' => 'publish-next217', 'data_version' => 18],
                ['token' => 'shared-cache-next229', 'data_version' => 22],
            ],
            'reuse_leases' => [
                [
                    'token' => 'reader-ready-next229',
                    'snapshot' => 'reader-ready',
                    'data_version' => 22,
                    'published_count' => 1,
                    'receipt_digest' => hash('sha256', 'publish-next217'),
                ],
            ],
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'snapshot(reader-ready)',
            'reuse(reader-ready,reader-ready-next233)',
            'publish(reader-ready,shared-cache-next233)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 3, 'bytes' => 4096, 'digest' => 'dirty-next230'],
];

$missingLeaseCurrent = $readyCurrent;
$missingLeaseCurrent['sources']['main']['reuse_leases'] = [];

$staleCurrent = $readyCurrent;
$staleCurrent['snapshots'] = [
    'reader-ready' => [
        'source' => 'main',
        'handle' => 'vfs214217-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 21,
        'ready_token' => 'ready-next225',
        'publish_token' => 'shared-cache-next229',
        'published_count' => 2,
        'reuse_lease_count' => 1,
        'receipt_digest' => hash('sha256', 'publish-next217|shared-cache-next229'),
    ],
];

$changedReceiptCurrent = $readyCurrent;
$changedReceiptCurrent['snapshots'] = [
    'reader-ready' => [
        'source' => 'main',
        'handle' => 'vfs214217-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 22,
        'ready_token' => 'ready-next225',
        'publish_token' => 'shared-cache-next229',
        'published_count' => 2,
        'reuse_lease_count' => 1,
        'receipt_digest' => hash('sha256', 'older-publish-token'),
    ],
];

return [
    'vfs current source next230-233 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next230-233', $plan()['dependencies'], true)),
    'vfs current source next230-233 records ready next222-225 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-publish-next222-225', $plan()['dependencies'], true)),
    'vfs current source next230-233 records lease next226-229 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-lease-publish-next226-229', $plan()['dependencies'], true)),
    'vfs current source next230-233 records next218-221 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-publish-next218-221', $plan()['dependencies'], true)),
    'vfs current source next230-233 records ready next214-217 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next214-217', $plan()['dependencies'], true)),
    'vfs current source next230-233 preserves next206-209 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-next206-209', $plan()['dependencies'], true)),
    'vfs current source next230-233 captures ready snapshot' => static fn (TestRunner $t) => $t->same('captured-ready', $plan()['events'][0]['status']),
    'vfs current source next230-233 records ready token' => static fn (TestRunner $t) => $t->same('ready-next225', $plan()['events'][0]['ready_token']),
    'vfs current source next230-233 records publish token' => static fn (TestRunner $t) => $t->same('shared-cache-next229', $plan()['events'][0]['publish_token']),
    'vfs current source next230-233 leases unchanged snapshot' => static fn (TestRunner $t) => $t->same('leased-current-source', $plan()['events'][1]['status']),
    'vfs current source next230-233 records reuse lease token' => static fn (TestRunner $t) => $t->same('reader-ready-next233', $plan()['events'][1]['lease']),
    'vfs current source next230-233 reuse has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next230-233 publishes current source' => static fn (TestRunner $t) => $t->same('published-current-source', $plan()['events'][2]['status']),
    'vfs current source next230-233 publish uses reuse lease' => static fn (TestRunner $t) => $t->same('reader-ready-next233', $plan()['events'][2]['reuse_lease']),
    'vfs current source next230-233 publish count advances' => static fn (TestRunner $t) => $t->same(3, $plan()['events'][2]['published_count']),
    'vfs current source next230-233 blocks dirty ready capture' => static fn (TestRunner $t) => $t->same('blocked-not-ready', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready)'], ['current' => $dirtyCurrent])['events'][0]['status']),
    'vfs current source next230-233 blocks missing snapshot publish' => static fn (TestRunner $t) => $t->same('missing-snapshot', SQLiteVfsCurrentSourceNextPlan::run(['publish(reader-ready,shared-cache-next233)'], ['current' => $readyCurrent])['events'][0]['status']),
    'vfs current source next230-233 blocks publish without reuse lease' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-lease', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-ready)', 'publish(reader-ready,shared-cache-next233)'], ['current' => $missingLeaseCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next230-233 stale reuse is fenced' => static fn (TestRunner $t) => $t->same('blocked-stale', SQLiteVfsCurrentSourceNextPlan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['status']),
    'vfs current source next230-233 stale reuse names data version blocker' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNextPlan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next230-233 changed receipt is fenced' => static fn (TestRunner $t) => $t->same(true, in_array('publish-receipt-digest-changed', SQLiteVfsCurrentSourceNextPlan::run(['reuse(reader-ready)'], ['current' => $changedReceiptCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next230-233 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next230-233 rejects bad publish token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'publish', 'snapshot' => 'reader-ready', 'token' => 'bad token']], ['current' => $readyCurrent])),
    'vfs current source next230-233 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['write(1,4096)'], ['current' => $readyCurrent])),
    'vfs current source next230-233 notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'does not repeat next206-209')),
];
