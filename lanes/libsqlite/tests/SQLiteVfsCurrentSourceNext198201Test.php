<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 53,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs194197-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'lock' => 'reserved',
            'data_version' => 8,
            'durable_receipts' => [
                ['page' => 1, 'bytes' => 4096, 'digest' => 'seed000000000001'],
            ],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'write(12,4096)',
            ['op' => 'checkpoint', 'token' => 'before-flush'],
            'flush(full)',
            'checkpoint(after-flush)',
            ['op' => 'open', 'source' => 'readonly', 'path' => '/srv/www/wp-content/database/read.sqlite', 'readonly' => true, 'lock' => 'shared'],
            ['op' => 'xWrite', 'page' => 2, 'bytes' => 1024],
            'close(readonly)',
            'source(main)',
            'close(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next198-201 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-dirty-flush-checkpoint-next198-201', $plan()['dependencies'], true)),
    'vfs current source next198-201 preserves durable receipt prerequisite marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-durable-receipts-next194-197', $plan()['dependencies'], true)),
    'vfs current source next198-201 starts with hydrated durable source' => static fn (TestRunner $t) => $t->same(1, $plan()['current']['source_count']),
    'vfs current source next198-201 records dirty page write' => static fn (TestRunner $t) => $t->same('recorded', $plan()['events'][0]['status']),
    'vfs current source next198-201 dirty write advances data version' => static fn (TestRunner $t) => $t->same(9, $plan()['events'][0]['data_version']),
    'vfs current source next198-201 dirty write tracks count' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['dirty_count']),
    'vfs current source next198-201 blocks checkpoint while dirty' => static fn (TestRunner $t) => $t->same('blocked-dirty', $plan()['events'][1]['status']),
    'vfs current source next198-201 flush publishes dirty page' => static fn (TestRunner $t) => $t->same('flushed', $plan()['events'][2]['status']),
    'vfs current source next198-201 flush clears dirty pages' => static fn (TestRunner $t) => $t->same(0, count($plan()['events'][2]['next']['sources']['main']['dirty_pages'])),
    'vfs current source next198-201 flush merges durable receipts' => static fn (TestRunner $t) => $t->same(2, $plan()['events'][2]['durable_count']),
    'vfs current source next198-201 checkpoint records after clean flush' => static fn (TestRunner $t) => $t->same('recorded', $plan()['events'][3]['status']),
    'vfs current source next198-201 checkpoint preserves data version' => static fn (TestRunner $t) => $t->same(9, $plan()['events'][3]['next']['sources']['main']['checkpoints'][0]['data_version']),
    'vfs current source next198-201 open increments readonly owner generation' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][4]['next']['owner_generations']['/srv/www/wp-content/database/read.sqlite']),
    'vfs current source next198-201 blocks readonly dirty write' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][5]['status']),
    'vfs current source next198-201 close readonly restores main source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][6]['next']['current_source']),
    'vfs current source next198-201 source handoff reports clean state' => static fn (TestRunner $t) => $t->same(0, $plan()['events'][7]['dirty_count']),
    'vfs current source next198-201 clean main close succeeds' => static fn (TestRunner $t) => $t->same('closed', $plan()['events'][8]['status']),
    'vfs current source next198-201 final open count' => static fn (TestRunner $t) => $t->same(0, $plan()['next']['open_source_count']),
    'vfs current source next198-201 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next198-201 rejects unsupported flush mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'xFlush', 'mode' => 'unsafe']], ['current' => $current])),
    'vfs current source next198-201 rejects bad checkpoint token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'checkpoint', 'token' => 'bad token']], ['current' => $current])),
    'vfs current source next198-201 blocks clean close when dirty' => static fn (TestRunner $t) => $t->same('blocked-dirty', SQLiteVfsCurrentSourceNextPlan::run(['write(2,4096)', 'close(main)'], ['current' => $current])['events'][1]['status']),
];
