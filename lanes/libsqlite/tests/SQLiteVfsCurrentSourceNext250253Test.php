<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext250253Plan;

$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 34,
            'ready_receipts' => [
                ['token' => 'ready-next241', 'data_version' => 30],
                ['token' => 'ready-next245', 'data_version' => 34],
            ],
            'published' => [
                ['token' => 'shared-cache-next245', 'data_version' => 30],
                ['token' => 'shared-cache-next249', 'data_version' => 34],
            ],
            'reuse_leases' => [
                [
                    'token' => 'reader-ready-next249',
                    'snapshot' => 'reader-ready',
                    'data_version' => 34,
                    'published_count' => 2,
                    'receipt_digest' => hash('sha256', 'shared-cache-next245'),
                ],
            ],
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext250253Plan::run([
            'snapshot(reader-ready)',
            'reuse(reader-ready,reader-ready-next253)',
            'publish(reader-ready,shared-cache-next253)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 3, 'bytes' => 4096, 'digest' => 'dirty-next250'],
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
        'ready_token' => 'ready-next245',
        'publish_token' => 'shared-cache-next249',
        'published_count' => 2,
        'reuse_lease_count' => 1,
        'receipt_digest' => hash('sha256', 'shared-cache-next245|shared-cache-next249'),
    ],
];

$changedReceiptCurrent = $readyCurrent;
$changedReceiptCurrent['snapshots'] = [
    'reader-ready' => [
        'source' => 'main',
        'handle' => 'vfs214217-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 34,
        'ready_token' => 'ready-next245',
        'publish_token' => 'shared-cache-next249',
        'published_count' => 2,
        'reuse_lease_count' => 1,
        'receipt_digest' => hash('sha256', 'older-publish-token'),
    ],
];

return [
    'vfs current source next250-253 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next250-253', $plan()['dependencies'], true)),
    'vfs current source next250-253 records ready next238-241 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-publish-next238-241', $plan()['dependencies'], true)),
    'vfs current source next250-253 records lease next242-245 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-lease-publish-next242-245', $plan()['dependencies'], true)),
    'vfs current source next250-253 records ready next246-249 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next246-249', $plan()['dependencies'], true)),
    'vfs current source next250-253 records next226-229 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-reuse-publish-next226-229', $plan()['dependencies'], true)),
    'vfs current source next250-253 records ready next222-225 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next225', $plan()['dependencies'], true)),
    'vfs current source next250-253 preserves next214-217 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-next214-217', $plan()['dependencies'], true)),
    'vfs current source next250-253 captures ready snapshot' => static fn (TestRunner $t) => $t->same('captured-ready', $plan()['events'][0]['status']),
    'vfs current source next250-253 records ready token' => static fn (TestRunner $t) => $t->same('ready-next245', $plan()['events'][0]['ready_token']),
    'vfs current source next250-253 records publish token' => static fn (TestRunner $t) => $t->same('shared-cache-next249', $plan()['events'][0]['publish_token']),
    'vfs current source next250-253 leases unchanged snapshot' => static fn (TestRunner $t) => $t->same('leased-current-source', $plan()['events'][1]['status']),
    'vfs current source next250-253 records reuse lease token' => static fn (TestRunner $t) => $t->same('reader-ready-next253', $plan()['events'][1]['lease']),
    'vfs current source next250-253 reuse has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next250-253 publishes current source' => static fn (TestRunner $t) => $t->same('published-current-source', $plan()['events'][2]['status']),
    'vfs current source next250-253 publish uses reuse lease' => static fn (TestRunner $t) => $t->same('reader-ready-next253', $plan()['events'][2]['reuse_lease']),
    'vfs current source next250-253 publish count advances' => static fn (TestRunner $t) => $t->same(3, $plan()['events'][2]['published_count']),
    'vfs current source next250-253 blocks dirty ready capture' => static fn (TestRunner $t) => $t->same('blocked-not-ready', SQLiteVfsCurrentSourceNext250253Plan::run(['snapshot(reader-ready)'], ['current' => $dirtyCurrent])['events'][0]['status']),
    'vfs current source next250-253 blocks missing snapshot publish' => static fn (TestRunner $t) => $t->same('missing-snapshot', SQLiteVfsCurrentSourceNext250253Plan::run(['publish(reader-ready,shared-cache-next253)'], ['current' => $readyCurrent])['events'][0]['status']),
    'vfs current source next250-253 blocks publish without reuse lease' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-lease', SQLiteVfsCurrentSourceNext250253Plan::run(['snapshot(reader-ready)', 'publish(reader-ready,shared-cache-next253)'], ['current' => $missingLeaseCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next250-253 stale reuse is fenced' => static fn (TestRunner $t) => $t->same('blocked-stale', SQLiteVfsCurrentSourceNext250253Plan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['status']),
    'vfs current source next250-253 stale reuse names data version blocker' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNext250253Plan::run(['reuse(reader-ready)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next250-253 changed receipt is fenced' => static fn (TestRunner $t) => $t->same(true, in_array('publish-receipt-digest-changed', SQLiteVfsCurrentSourceNext250253Plan::run(['reuse(reader-ready)'], ['current' => $changedReceiptCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next250-253 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext250253Plan::run([])),
    'vfs current source next250-253 rejects bad publish token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext250253Plan::run([['op' => 'publish', 'snapshot' => 'reader-ready', 'token' => 'bad token']], ['current' => $readyCurrent])),
    'vfs current source next250-253 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext250253Plan::run(['write(1,4096)'], ['current' => $readyCurrent])),
    'vfs current source next250-253 notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'does not repeat next214-217')),
];
