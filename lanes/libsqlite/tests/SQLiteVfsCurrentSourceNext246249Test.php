<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext246249Plan;

$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 30,
            'ready_receipts' => [
                ['token' => 'ready-next225', 'data_version' => 26],
                ['token' => 'ready-next241', 'data_version' => 30],
            ],
            'published' => [
                ['token' => 'publish-next225', 'data_version' => 26],
                ['token' => 'shared-cache-next245', 'data_version' => 30],
            ],
            'reuse_leases' => [
                [
                    'token' => 'reader-ready-next245',
                    'snapshot' => 'reader-ready',
                    'data_version' => 30,
                    'published_count' => 1,
                    'receipt_digest' => hash('sha256', 'publish-next225'),
                ],
            ],
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext246249Plan::run([
            'snapshot(reader-ready)',
            'reuse(reader-ready,reader-ready-next249)',
            'publish(reader-ready,shared-cache-next249)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 3, 'bytes' => 4096, 'digest' => 'dirty-next246'],
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
        'ready_token' => 'ready-next241',
        'publish_token' => 'shared-cache-next245',
        'published_count' => 2,
        'reuse_lease_count' => 1,
        'receipt_digest' => hash('sha256', 'publish-next225|shared-cache-next245'),
    ],
];

$changedReceiptCurrent = $readyCurrent;
$changedReceiptCurrent['snapshots'] = [
    'reader-ready' => [
        'source' => 'main',
        'handle' => 'vfs214217-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 30,
        'ready_token' => 'ready-next241',
        'publish_token' => 'shared-cache-next245',
        'published_count' => 2,
        'reuse_lease_count' => 1,
        'receipt_digest' => hash('sha256', 'older-publish-token'),
    ],
];

return [
    'vfs current source next246-249 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next246-249', $plan()['dependencies'], true)),
    'vfs current source next246-249 records ready next238-241 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-publish-next238-241', $plan()['dependencies'], true)),
    'vfs current source next246-249 records lease next242-245 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-lease-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next246-249 records next226-229 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-publish-next226-229', $plan()['dependencies'], true)),
    'vfs current source next246-249 records ready next222-225 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next225', $plan()['dependencies'], true)),
    'vfs current source next246-249 preserves next214-217 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-next214-217', $plan()['dependencies'], true)),
    'vfs current source next246-249 captures ready snapshot' => static fn (TestRunner $t) => $t->same('captured-ready', $plan()['events'][0]['status']),
    'vfs current source next246-249 records ready token' => static fn (TestRunner $t) => $t->same('ready-next241', $plan()['events'][0]['ready_token']),
    'vfs current source next246-249 records publish token' => static fn (TestRunner $t) => $t->same('shared-cache-next245', $plan()['events'][0]['publish_token']),
    'vfs current source next246-249 leases unchanged snapshot' => static fn (TestRunner $t) => $t->same('leased-current-source', $plan()['events'][1]['status']),
    'vfs current source next246-249 records reuse lease token' => static fn (TestRunner $t) => $t->same('reader-ready-next249', $plan()['events'][1]['lease']),
    'vfs current source next246-249 reuse has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next246-249 publishes current source' => static fn (TestRunner $t) => $t->same('published-current-source', $plan()['events'][2]['status']),
    'vfs current source next246-249 publish uses reuse lease' => static fn (TestRunner $t) => $t->same('reader-ready-next249', $plan()['events'][2]['reuse_lease']),
    'vfs current source next246-249 publish count advances' => static fn (TestRunner $t) => $t->same(3, $plan()['events'][2]['published_count']),
    'vfs current source next246-249 blocks dirty ready capture' => static fn (TestRunner $t) => $t->same('blocked-not-ready', SQLiteVfsCurrentSourceNext246249Plan::run(['snapshot(reader-ready)'], ['current' => $dirtyCurrent])['events'][0]['status']),
    'vfs current source next246-249 blocks missing snapshot publish' => static fn (TestRunner $t) => $t->same('missing-snapshot', SQLiteVfsCurrentSourceNext246249Plan::run(['publish(reader-ready,shared-cache-next249)'], ['current' => $readyCurrent])['events'][0]['status']),
    'vfs current source next246-249 blocks publish without reuse lease' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-lease', SQLiteVfsCurrentSourceNext246249Plan::run(['snapshot(reader-ready)', 'publish(reader-ready,shared-cache-next249)'], ['current' => $missingLeaseCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next246-249 stale reuse is fenced' => static fn (TestRunner $t) => $t->same('blocked-stale', SQLiteVfsCurrentSourceNext246249Plan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['status']),
    'vfs current source next246-249 stale reuse names data version blocker' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNext246249Plan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next246-249 changed receipt is fenced' => static fn (TestRunner $t) => $t->same(true, in_array('publish-receipt-digest-changed', SQLiteVfsCurrentSourceNext246249Plan::run(['reuse(reader-ready)'], ['current' => $changedReceiptCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next246-249 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext246249Plan::run([])),
    'vfs current source next246-249 rejects bad publish token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext246249Plan::run([['op' => 'publish', 'snapshot' => 'reader-ready', 'token' => 'bad token']], ['current' => $readyCurrent])),
    'vfs current source next246-249 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext246249Plan::run(['write(1,4096)'], ['current' => $readyCurrent])),
    'vfs current source next246-249 notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'does not repeat next214-217')),
];
