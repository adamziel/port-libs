<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext226229Plan;

$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 18,
            'ready_receipts' => [
                ['token' => 'ready-next214-217', 'data_version' => 18],
            ],
            'published' => [
                ['token' => 'publish-next217', 'data_version' => 18],
            ],
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext226229Plan::run([
            'snapshot(reader-ready)',
            'reuse(reader-ready,reader-ready-next229)',
            'publish(reader-ready,shared-cache-next229)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 3, 'bytes' => 4096, 'digest' => 'dirty-next226'],
];

$staleCurrent = $readyCurrent;
$staleCurrent['snapshots'] = [
    'reader-ready' => [
        'source' => 'main',
        'handle' => 'vfs214217-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 17,
        'ready_token' => 'ready-next214-217',
        'publish_token' => 'publish-next217',
        'published_count' => 1,
        'receipt_digest' => hash('sha256', 'publish-next217'),
    ],
];

$changedReceiptCurrent = $readyCurrent;
$changedReceiptCurrent['snapshots'] = [
    'reader-ready' => [
        'source' => 'main',
        'handle' => 'vfs214217-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 18,
        'ready_token' => 'ready-next214-217',
        'publish_token' => 'publish-next217',
        'published_count' => 1,
        'receipt_digest' => hash('sha256', 'older-publish-token'),
    ],
];

return [
    'vfs current source next226-229 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-lease-publish-next226-229', $plan()['dependencies'], true)),
    'vfs current source next226-229 records next218-221 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-publish-next218-221', $plan()['dependencies'], true)),
    'vfs current source next226-229 records ready next214-217 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next214-217', $plan()['dependencies'], true)),
    'vfs current source next226-229 preserves next206-209 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-next206-209', $plan()['dependencies'], true)),
    'vfs current source next226-229 captures ready snapshot' => static fn (TestRunner $t) => $t->same('captured-ready', $plan()['events'][0]['status']),
    'vfs current source next226-229 records ready token' => static fn (TestRunner $t) => $t->same('ready-next214-217', $plan()['events'][0]['ready_token']),
    'vfs current source next226-229 records publish token' => static fn (TestRunner $t) => $t->same('publish-next217', $plan()['events'][0]['publish_token']),
    'vfs current source next226-229 leases unchanged snapshot' => static fn (TestRunner $t) => $t->same('leased-current-source', $plan()['events'][1]['status']),
    'vfs current source next226-229 records reuse lease token' => static fn (TestRunner $t) => $t->same('reader-ready-next229', $plan()['events'][1]['lease']),
    'vfs current source next226-229 reuse has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next226-229 publishes current source' => static fn (TestRunner $t) => $t->same('published-current-source', $plan()['events'][2]['status']),
    'vfs current source next226-229 publish uses reuse lease' => static fn (TestRunner $t) => $t->same('reader-ready-next229', $plan()['events'][2]['reuse_lease']),
    'vfs current source next226-229 publish count advances' => static fn (TestRunner $t) => $t->same(2, $plan()['events'][2]['published_count']),
    'vfs current source next226-229 blocks dirty ready capture' => static fn (TestRunner $t) => $t->same('blocked-not-ready', SQLiteVfsCurrentSourceNext226229Plan::run(['snapshot(reader-ready)'], ['current' => $dirtyCurrent])['events'][0]['status']),
    'vfs current source next226-229 blocks missing snapshot publish' => static fn (TestRunner $t) => $t->same('missing-snapshot', SQLiteVfsCurrentSourceNext226229Plan::run(['publish(reader-ready,shared-cache-next229)'], ['current' => $readyCurrent])['events'][0]['status']),
    'vfs current source next226-229 blocks publish without reuse lease' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-lease', SQLiteVfsCurrentSourceNext226229Plan::run(['snapshot(reader-ready)', 'publish(reader-ready,shared-cache-next229)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next226-229 stale reuse is fenced' => static fn (TestRunner $t) => $t->same('blocked-stale', SQLiteVfsCurrentSourceNext226229Plan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['status']),
    'vfs current source next226-229 stale reuse names data version blocker' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNext226229Plan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next226-229 changed receipt is fenced' => static fn (TestRunner $t) => $t->same(true, in_array('publish-receipt-digest-changed', SQLiteVfsCurrentSourceNext226229Plan::run(['reuse(reader-ready)'], ['current' => $changedReceiptCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next226-229 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext226229Plan::run([])),
    'vfs current source next226-229 rejects bad publish token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext226229Plan::run([['op' => 'publish', 'snapshot' => 'reader-ready', 'token' => 'bad token']], ['current' => $readyCurrent])),
    'vfs current source next226-229 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext226229Plan::run(['write(1,4096)'], ['current' => $readyCurrent])),
    'vfs current source next226-229 notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'does not repeat next206-209')),
];
