<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 52,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs186189-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'lock' => 'reserved',
            'data_version' => 7,
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'write(12,4096)',
            'sync(full)',
            'barrier(checkpoint-194)',
            ['op' => 'open', 'source' => 'readonly', 'path' => '/srv/www/wp-content/database/read.sqlite', 'readonly' => true, 'lock' => 'shared'],
            ['op' => 'xWrite', 'page' => 1, 'bytes' => 1024],
            'close(readonly)',
            'source(main)',
            'sync(normal)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next194-197 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-durable-receipts-next194-197', $plan()['dependencies'], true)),
    'vfs current source next194-197 preserves 186-189 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-lock-filecontrol-next186-189', $plan()['dependencies'], true)),
    'vfs current source next194-197 records ready next190-193 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-ready-next190-193', $plan()['dependencies'], true)),
    'vfs current source next194-197 starts with hydrated source' => static fn (TestRunner $t) => $t->same(1, $plan()['current']['source_count']),
    'vfs current source next194-197 records write receipt' => static fn (TestRunner $t) => $t->same('recorded', $plan()['events'][0]['status']),
    'vfs current source next194-197 advances data version' => static fn (TestRunner $t) => $t->same(8, $plan()['events'][0]['data_version']),
    'vfs current source next194-197 emits stable receipt digest' => static fn (TestRunner $t) => $t->same(16, strlen($plan()['events'][0]['receipt']['digest'])),
    'vfs current source next194-197 syncs pending receipt' => static fn (TestRunner $t) => $t->same('synced', $plan()['events'][1]['status']),
    'vfs current source next194-197 sync records durable count' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][1]['durable_count']),
    'vfs current source next194-197 barrier records version' => static fn (TestRunner $t) => $t->same(8, $plan()['events'][2]['next']['sources']['main']['barriers'][0]['data_version']),
    'vfs current source next194-197 open increments owner generation' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][3]['next']['owner_generations']['/srv/www/wp-content/database/read.sqlite']),
    'vfs current source next194-197 blocks readonly shared write' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][4]['status']),
    'vfs current source next194-197 close restores main source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][5]['next']['current_source']),
    'vfs current source next194-197 source switches back to main' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][6]['source']),
    'vfs current source next194-197 second sync is noop' => static fn (TestRunner $t) => $t->same('noop', $plan()['events'][7]['status']),
    'vfs current source next194-197 final source count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['source_count']),
    'vfs current source next194-197 final open count' => static fn (TestRunner $t) => $t->same(1, $plan()['next']['open_source_count']),
    'vfs current source next194-197 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next194-197 rejects unsupported sync mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'xSync', 'mode' => 'unsafe']], ['current' => $current])),
    'vfs current source next194-197 rejects bad barrier token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'barrier', 'token' => 'bad token']], ['current' => $current])),
    'vfs current source next194-197 blocks write below reserved lock' => static fn (TestRunner $t) => $t->same('blocked', SQLiteVfsCurrentSourceNextPlan::run(['write(1,512)'], ['current' => ['current_source' => 'main', 'sources' => ['main' => ['path' => '/tmp/wp.sqlite', 'lock' => 'shared']]]])['events'][0]['status']),
];
