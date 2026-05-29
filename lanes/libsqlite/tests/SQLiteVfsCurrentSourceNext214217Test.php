<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext214217Plan;

$current = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs210213-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 16,
            'durable_receipts' => [
                ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
                ['page' => 7, 'bytes' => 4096, 'digest' => 'seed000000000007'],
                ['page' => 9, 'bytes' => 4096, 'digest' => 'seed000000000009'],
            ],
        ],
    ],
    'snapshots' => [
        'reader-cache' => [
            'source' => 'main',
            'handle' => 'vfs210213-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 16,
            'durable_count' => 3,
            'checkpoint_token' => 'ready-next213',
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext214217Plan::run([
            'publish-snapshot(reader-cache,ticket-next214)',
            'reuse-publication(ticket-next214,reader-a)',
        ], ['current' => $current]);
    }
    return $result;
};

$dirtyCurrent = $current;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 11, 'bytes' => 4096, 'digest' => 'dirty000000000011'],
];

$staleCurrent = $current;
$staleCurrent['sources']['main']['data_version'] = 17;

$publishedCurrent = $current;
$publishedCurrent['publications'] = [
    'ticket-next214' => [
        'source' => 'main',
        'snapshot' => 'reader-cache',
        'handle' => 'vfs210213-1',
        'path' => '/srv/www/wp-content/database/wp.sqlite',
        'owner' => '/srv/www/wp-content/database/wp.sqlite',
        'data_version' => 16,
        'durable_count' => 3,
        'checkpoint_token' => 'ready-next213',
    ],
];

return [
    'vfs current source next214-217 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next214-217', $plan()['dependencies'], true)),
    'vfs current source next214-217 records ready next210-213 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next210-213', $plan()['dependencies'], true)),
    'vfs current source next214-217 publishes snapshot ticket' => static fn (TestRunner $t) => $t->same('published', $plan()['events'][0]['status']),
    'vfs current source next214-217 advances publication count' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['published_count']),
    'vfs current source next214-217 records publication snapshot' => static fn (TestRunner $t) => $t->same('reader-cache', $plan()['events'][0]['next']['publications']['ticket-next214']['snapshot']),
    'vfs current source next214-217 reuses publication for reader' => static fn (TestRunner $t) => $t->same('publication-reused', $plan()['events'][1]['status']),
    'vfs current source next214-217 records reader ticket' => static fn (TestRunner $t) => $t->same('ticket-next214', $plan()['events'][1]['next']['reader_reuse']['reader-a']['ticket']),
    'vfs current source next214-217 blocks dirty publication' => static fn (TestRunner $t) => $t->same(['dirty-pages-present'], SQLiteVfsCurrentSourceNext214217Plan::run(['publish-snapshot(reader-cache,ticket-next214)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons']),
    'vfs current source next214-217 blocks stale publication' => static fn (TestRunner $t) => $t->same(true, in_array('data-version-changed', SQLiteVfsCurrentSourceNext214217Plan::run(['publish-snapshot(reader-cache,ticket-next214)'], ['current' => $staleCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next214-217 blocks missing publication reuse' => static fn (TestRunner $t) => $t->same(['missing-publication'], SQLiteVfsCurrentSourceNext214217Plan::run(['reuse-publication(ticket-missing,reader-a)'], ['current' => $current])['events'][0]['blocked_reasons']),
    'vfs current source next214-217 blocks revoked publication reuse' => static fn (TestRunner $t) => $t->same(['publication-revoked'], SQLiteVfsCurrentSourceNext214217Plan::run(['revoke-publication(ticket-next214)', 'reuse-publication(ticket-next214,reader-a)'], ['current' => $publishedCurrent])['events'][1]['blocked_reasons']),
    'vfs current source next214-217 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext214217Plan::run([])),
    'vfs current source next214-217 rejects bad ticket token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext214217Plan::run([['op' => 'publish_snapshot', 'snapshot' => 'reader-cache', 'ticket' => 'bad token']], ['current' => $current])),
    'vfs current source next214-217 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext214217Plan::run(['snapshot(reader-cache)'], ['current' => $current])),
    'vfs current source next214-217 notes non-overlap' => static fn (TestRunner $t) => $t->contains('does not repeat snapshot capture/reuse/publish mechanics from next206-209', $plan()['non_overlap']),
];
