<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext222225Plan;

$current = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs222225-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 17,
            'publish_receipts' => [
                ['token' => 'wp-options-publication', 'reuse_count' => 1, 'data_version' => 17],
            ],
        ],
    ],
];

$withoutPriorPublish = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs222225-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 17,
            'publish_receipts' => [],
        ],
    ],
];

$dirtyAfterReady = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs222225-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 17,
            'publish_receipts' => [
                ['token' => 'wp-options-publication', 'reuse_count' => 1, 'data_version' => 17],
            ],
            'ready_receipts' => [
                'clean' => ['token' => 'clean', 'data_version' => 17, 'digest' => 'ready-clean-digest'],
            ],
            'dirty_pages' => [4 => ['page' => 4]],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext222225Plan::run([
            'prepare(wp-options-after-ready)',
            'reuse(wp-options-after-ready)',
            'publish(wp-options-after-ready-publication)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next222-225 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-after-ready-reuse-publish-next222-225', $plan()['dependencies'], true)),
    'vfs current source next222-225 preserves next210-213 prerequisite marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next210-213', $plan()['dependencies'], true)),
    'vfs current source next222-225 prepares after prior publish' => static fn (TestRunner $t) => $t->same('ready', $plan()['events'][0]['status']),
    'vfs current source next222-225 records prior publish count' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['prior_publish_count']),
    'vfs current source next222-225 records ready receipt' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['ready_count']),
    'vfs current source next222-225 reuses after-ready receipt' => static fn (TestRunner $t) => $t->same('reused', $plan()['events'][1]['status']),
    'vfs current source next222-225 reuse receipt is flagged after ready' => static fn (TestRunner $t) => $t->same(true, $plan()['next']['sources']['main']['reuse_receipts'][0]['after_ready']),
    'vfs current source next222-225 publishes after-ready reuse' => static fn (TestRunner $t) => $t->same('published', $plan()['events'][2]['status']),
    'vfs current source next222-225 final status is published' => static fn (TestRunner $t) => $t->same('published', $plan()['status']),
    'vfs current source next222-225 blocks prepare without prior publish' => static fn (TestRunner $t) => $t->same('blocked-no-prior-publish', SQLiteVfsCurrentSourceNext222225Plan::run(['prepare(empty)'], ['current' => $withoutPriorPublish])['status']),
    'vfs current source next222-225 blocks publish without after-ready reuse' => static fn (TestRunner $t) => $t->same('blocked-no-after-ready-reuse', SQLiteVfsCurrentSourceNext222225Plan::run(['publish(empty)'], ['current' => $current])['status']),
    'vfs current source next222-225 blocks reuse when current dirty' => static fn (TestRunner $t) => $t->same('blocked-dirty', SQLiteVfsCurrentSourceNext222225Plan::run(['reuse(clean)'], ['current' => $dirtyAfterReady])['status']),
    'vfs current source next222-225 reports missing ready receipt' => static fn (TestRunner $t) => $t->same('missing-ready-receipt', SQLiteVfsCurrentSourceNext222225Plan::run(['reuse(missing)'], ['current' => $current])['status']),
    'vfs current source next222-225 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext222225Plan::run([])),
    'vfs current source next222-225 rejects bad token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext222225Plan::run([['op' => 'prepare', 'token' => 'bad token']], ['current' => $current])),
    'vfs current source next222-225 rejects missing selected source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext222225Plan::run(['prepare(no-source)'])),
];
