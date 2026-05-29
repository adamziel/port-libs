<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 7,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs146149-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'generation' => 7,
            'file_controls' => ['persist_wal' => true],
            'syncs' => ['normal'],
        ],
        'wal' => [
            'handle' => 'vfs146149-2',
            'path' => '/srv/www/wp-content/database/wp.sqlite-wal',
            'generation' => 6,
            'locks' => ['shared' => 'wp-reader'],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'source(wal)',
            ['op' => 'filecontrol', 'control' => 'data_version'],
            'source(main)',
            ['op' => 'filecontrol', 'control' => 'checkpoint_fullfsync', 'value' => true],
            'sync(full)',
            'source(wal)',
            ['op' => 'filecontrol', 'control' => 'data_version'],
            'close(wal)',
            ['op' => 'open', 'source' => 'shm', 'path' => '/srv/www/wp-content/database/wp.sqlite-shm'],
            'lock(shared, wp-reader)',
            ['op' => 'open', 'source' => 'archive', 'path' => '/srv/www/wp-content/database/archive.sqlite', 'readonly' => true],
            'lock(exclusive, wp-import)',
            'source(main)',
        ], ['current' => $current, 'slice' => 'close-reopen-current-source']);
    }
    return $result;
};

return [
    'vfs current source close-reopen-current-source dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-close-reopen', $plan()['dependencies'], true)),
    'vfs current source close-reopen-current-source current source count' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source close-reopen-current-source current open source count' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['open_source_count']),
    'vfs current source close-reopen-current-source selects wal' => static fn (TestRunner $t) => $t->same('wal', $plan()['events'][0]['source']),
    'vfs current source close-reopen-current-source initial wal data version stale' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][1]['stale_current_source']),
    'vfs current source close-reopen-current-source initial wal opened generation' => static fn (TestRunner $t) => $t->same(6, $plan()['events'][1]['opened_generation']),
    'vfs current source close-reopen-current-source main control bumps owner generation' => static fn (TestRunner $t) => $t->same(8, $plan()['events'][3]['owner_generation']),
    'vfs current source close-reopen-current-source full sync recorded' => static fn (TestRunner $t) => $t->same(['normal', 'full'], $plan()['events'][4]['next']['sources']['main']['syncs']),
    'vfs current source close-reopen-current-source wal stays stale after main generation bump' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][6]['stale_current_source']),
    'vfs current source close-reopen-current-source wal sees generation eight' => static fn (TestRunner $t) => $t->same(8, $plan()['events'][6]['value']),
    'vfs current source close-reopen-current-source close wal releases locks' => static fn (TestRunner $t) => $t->same([], $plan()['events'][7]['next']['sources']['wal']['locks']),
    'vfs current source close-reopen-current-source close wal marks closed' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][7]['next']['sources']['wal']['closed']),
    'vfs current source close-reopen-current-source close wal selects main' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][7]['next']['current_source']),
    'vfs current source close-reopen-current-source opens shm fresh handle' => static fn (TestRunner $t) => $t->same('vfs-close-reopen-3', $plan()['events'][8]['handle']),
    'vfs current source close-reopen-current-source shm generation follows owner' => static fn (TestRunner $t) => $t->same(10, $plan()['events'][8]['generation']),
    'vfs current source close-reopen-current-source shm shared lock ok' => static fn (TestRunner $t) => $t->same('ok', $plan()['events'][9]['status']),
    'vfs current source close-reopen-current-source opens readonly archive' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][10]['readonly']),
    'vfs current source close-reopen-current-source readonly exclusive blocked' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][11]['status']),
    'vfs current source close-reopen-current-source blocked reason' => static fn (TestRunner $t) => $t->same('readonly current-source cannot take writer lock', $plan()['events'][11]['reason']),
    'vfs current source close-reopen-current-source final source main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source close-reopen-current-source final source count' => static fn (TestRunner $t) => $t->same(4, $plan()['next']['source_count']),
    'vfs current source close-reopen-current-source final open count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['open_source_count']),
    'vfs current source close-reopen-current-source rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([], ['slice' => 'close-reopen-current-source'])),
    'vfs current source close-reopen-current-source rejects missing current source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['source(main)'], ['slice' => 'close-reopen-current-source', 'current' => ['current_source' => 'missing', 'sources' => []]])),
    'vfs current source close-reopen-current-source rejects nonpositive generation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['source(main)'], ['slice' => 'close-reopen-current-source', 'current' => ['sources' => ['main' => ['handle' => 'vfs1', 'path' => '/tmp/a', 'generation' => 0]]]])),
];
