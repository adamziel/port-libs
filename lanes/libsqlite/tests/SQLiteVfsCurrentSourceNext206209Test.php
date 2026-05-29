<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext206209Plan;

$cleanCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs202205-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 12,
            'durable_receipts' => [
                ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
                ['page' => 7, 'bytes' => 4096, 'digest' => 'seed000000000007'],
            ],
            'checkpoints' => [
                ['token' => 'after-flush', 'data_version' => 12, 'durable_count' => 2],
            ],
        ],
    ],
];

$plan = static function () use ($cleanCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext206209Plan::run([
            'snapshot(reader-cache)',
            'reuse(reader-cache)',
            'publish(shared-cache-visible)',
        ], ['current' => $cleanCurrent]);
    }
    return $result;
};

$dirtyCurrent = $cleanCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 9, 'bytes' => 4096, 'digest' => 'dirty000000000009'],
];

$staleCurrent = $cleanCurrent;
$staleCurrent['snapshots'] = [
    'reader-cache' => [
        'source' => 'main',
        'handle' => 'vfs202205-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 11,
        'durable_count' => 1,
        'checkpoint_token' => 'before-flush',
    ],
];

return [
    'vfs current source next206-209 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-next206-209', $plan()['dependencies'], true)),
    'vfs current source next206-209 preserves next198-201 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-dirty-flush-checkpoint-next198-201', $plan()['dependencies'], true)),
    'vfs current source next206-209 records ready next202-205 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next202-205', $plan()['dependencies'], true)),
    'vfs current source next206-209 captures clean checkpoint snapshot' => static fn (TestRunner $t) => $t->same('captured', $plan()['events'][0]['status']),
    'vfs current source next206-209 snapshot includes checkpoint token' => static fn (TestRunner $t) => $t->same('after-flush', $plan()['events'][0]['checkpoint_token']),
    'vfs current source next206-209 snapshot count advances' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['next']['snapshot_count']),
    'vfs current source next206-209 reuses unchanged source' => static fn (TestRunner $t) => $t->same('reused', $plan()['events'][1]['status']),
    'vfs current source next206-209 reuse has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next206-209 publish records clean source' => static fn (TestRunner $t) => $t->same('published', $plan()['events'][2]['status']),
    'vfs current source next206-209 publish count advances' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][2]['published_count']),
    'vfs current source next206-209 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same('blocked-unpublished', SQLiteVfsCurrentSourceNext206209Plan::run(['snapshot(reader-cache)'], ['current' => $dirtyCurrent])['events'][0]['status']),
    'vfs current source next206-209 blocks dirty publish' => static fn (TestRunner $t) => $t->same('blocked-dirty', SQLiteVfsCurrentSourceNext206209Plan::run(['publish(shared-cache-visible)'], ['current' => $dirtyCurrent])['events'][0]['status']),
    'vfs current source next206-209 stale reuse is fenced' => static fn (TestRunner $t) => $t->same('blocked-stale', SQLiteVfsCurrentSourceNext206209Plan::run(['reuse(reader-cache)'], ['current' => $staleCurrent])['events'][0]['status']),
    'vfs current source next206-209 stale reuse names data version blocker' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNext206209Plan::run(['reuse(reader-cache)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next206-209 stale reuse names durable blocker' => static fn (TestRunner $t) => $t->same(true, in_array('durable-count-changed', SQLiteVfsCurrentSourceNext206209Plan::run(['reuse(reader-cache)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next206-209 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext206209Plan::run([])),
    'vfs current source next206-209 rejects bad snapshot token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext206209Plan::run([['op' => 'snapshot', 'snapshot' => 'bad token']], ['current' => $cleanCurrent])),
    'vfs current source next206-209 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext206209Plan::run(['write(1,4096)'], ['current' => $cleanCurrent])),
    'vfs current source next206-209 notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'does not repeat open/write/flush/checkpoint')),
];
