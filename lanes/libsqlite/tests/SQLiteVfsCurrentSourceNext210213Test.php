<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs206209-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 13,
            'durable_receipts' => [
                ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
                ['page' => 12, 'bytes' => 4096, 'digest' => 'seed000000000012'],
            ],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'snapshot(wp-options-visible)',
            'reuse(wp-options-visible)',
            'publish(wp-options-publication)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next210-213 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next210-213', $plan()['dependencies'], true)),
    'vfs current source next210-213 preserves next206-209 prerequisite marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-ready-next206-209', $plan()['dependencies'], true)),
    'vfs current source next210-213 captures clean snapshot' => static fn (TestRunner $t) => $t->same('captured', $plan()['events'][0]['status']),
    'vfs current source next210-213 snapshot carries current data version' => static fn (TestRunner $t) => $t->same(13, $plan()['events'][0]['data_version']),
    'vfs current source next210-213 snapshot carries durable count' => static fn (TestRunner $t) => $t->same(2, $plan()['events'][0]['durable_count']),
    'vfs current source next210-213 reuses current snapshot' => static fn (TestRunner $t) => $t->same('reused', $plan()['events'][1]['status']),
    'vfs current source next210-213 reuse receipt is recorded' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][1]['reuse_count']),
    'vfs current source next210-213 publishes reused snapshot' => static fn (TestRunner $t) => $t->same('published', $plan()['events'][2]['status']),
    'vfs current source next210-213 publish receipt is recorded' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][2]['publish_count']),
    'vfs current source next210-213 final status is published' => static fn (TestRunner $t) => $t->same('published', $plan()['status']),
    'vfs current source next210-213 blocks publish without reuse' => static fn (TestRunner $t) => $t->same('blocked-no-reuse', SQLiteVfsCurrentSourceNextPlan::run(['publish(empty)'], ['current' => $current])['status']),
    'vfs current source next210-213 blocks reuse when current dirty' => static fn (TestRunner $t) => $t->same('blocked-dirty', SQLiteVfsCurrentSourceNextPlan::run(['snapshot(stale)', 'reuse(stale)'], ['current' => array_replace_recursive($current, ['sources' => ['main' => ['dirty_pages' => [7 => ['page' => 7]]]]])])['events'][1]['status']),
    'vfs current source next210-213 reports missing snapshot' => static fn (TestRunner $t) => $t->same('missing-snapshot', SQLiteVfsCurrentSourceNextPlan::run(['reuse(missing)'], ['current' => $current])['status']),
    'vfs current source next210-213 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next210-213 rejects bad token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'snapshot', 'token' => 'bad token']], ['current' => $current])),
    'vfs current source next210-213 rejects missing selected source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['snapshot(no-source)'])),
];
