<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$cleanCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs-snapshot-reuse-1',
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
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'snapshot(reader-cache)',
            'reuse(reader-cache)',
            'publish(shared-cache-visible)',
        ], ['current' => $cleanCurrent, 'slice' => 'snapshot-reuse-publication']);
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
        'handle' => 'vfs-snapshot-reuse-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 11,
        'durable_count' => 1,
        'checkpoint_token' => 'before-flush',
    ],
];

return [
    'vfs current source snapshot reuse dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publication', $plan()['dependencies'], true)),
    'vfs current source snapshot reuse preserves next198-201 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-dirty-flush-checkpoint-next198-201', $plan()['dependencies'], true)),
    'vfs current source snapshot reuse records ready next202-205 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next202-205', $plan()['dependencies'], true)),
    'vfs current source snapshot reuse captures clean checkpoint snapshot' => static fn (TestRunner $t) => $t->same('captured', $plan()['events'][0]['status']),
    'vfs current source snapshot reuse snapshot includes checkpoint token' => static fn (TestRunner $t) => $t->same('after-flush', $plan()['events'][0]['checkpoint_token']),
    'vfs current source snapshot reuse snapshot count advances' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['next']['snapshot_count']),
    'vfs current source snapshot reuse reuses unchanged source' => static fn (TestRunner $t) => $t->same('reused', $plan()['events'][1]['status']),
    'vfs current source snapshot reuse reuse has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source snapshot reuse publish records clean source' => static fn (TestRunner $t) => $t->same('published', $plan()['events'][2]['status']),
    'vfs current source snapshot reuse publish count advances' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][2]['published_count']),
    'vfs current source snapshot reuse blocks dirty snapshot' => static fn (TestRunner $t) => $t->same('blocked-unpublished', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(reader-cache)'], ['current' => $dirtyCurrent, 'slice' => 'snapshot-reuse-publication'])['events'][0]['status']),
    'vfs current source snapshot reuse blocks dirty publish' => static fn (TestRunner $t) => $t->same('blocked-dirty', SQLiteVfsCurrentSourceNextPlan::run(['publish(shared-cache-visible)'], ['current' => $dirtyCurrent, 'slice' => 'snapshot-reuse-publication'])['events'][0]['status']),
    'vfs current source snapshot reuse stale reuse is fenced' => static fn (TestRunner $t) => $t->same('blocked-stale', SQLiteVfsCurrentSourceNextPlan::run(['reuse(reader-cache)'], ['current' => $staleCurrent, 'slice' => 'snapshot-reuse-publication'])['events'][0]['status']),
    'vfs current source snapshot reuse stale reuse names data version blocker' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNextPlan::run(['reuse(reader-cache)'], ['current' => $staleCurrent, 'slice' => 'snapshot-reuse-publication'])['events'][0]['blocked_reasons'], true)),
    'vfs current source snapshot reuse stale reuse names durable blocker' => static fn (TestRunner $t) => $t->same(true, in_array('durable-count-changed', SQLiteVfsCurrentSourceNextPlan::run(['reuse(reader-cache)'], ['current' => $staleCurrent, 'slice' => 'snapshot-reuse-publication'])['events'][0]['blocked_reasons'], true)),
    'vfs current source snapshot reuse rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source snapshot reuse rejects bad snapshot token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'snapshot', 'snapshot' => 'bad token']], ['current' => $cleanCurrent, 'slice' => 'snapshot-reuse-publication'])),
    'vfs current source snapshot reuse rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['write(1,4096)'], ['current' => $cleanCurrent, 'slice' => 'snapshot-reuse-publication'])),
    'vfs current source snapshot reuse notes non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'does not repeat open/write/flush/checkpoint')),
];
