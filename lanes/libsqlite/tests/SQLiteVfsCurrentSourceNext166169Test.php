<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 25,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs162165-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'system_calls' => [
                'open' => true,
                'read' => true,
                'write' => true,
            ],
        ],
        'wal' => [
            'handle' => 'vfs162165-2',
            'path' => '/srv/www/wp-content/database/wp.sqlite-wal',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'system_calls' => [
                'open' => true,
            ],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'currenttime(1717000000)',
            'currenttimeint64(1717000001)',
            ['op' => 'xSetSystemCall', 'name' => 'pread64', 'enabled' => true],
            ['op' => 'xGetSystemCall', 'name' => 'pread64'],
            'nextsystemcall(open)',
            ['op' => 'xGetLastError', 'code' => 'SQLITE_IOERR_READ', 'message' => 'short read from current source'],
            'source(wal)',
            ['op' => 'xSetSystemCall', 'name' => 'pwrite64', 'enabled' => false],
            ['op' => 'xGetSystemCall', 'name' => 'pwrite64'],
            ['op' => 'open', 'source' => 'shm', 'path' => '/srv/www/wp-content/database/wp.sqlite-shm', 'system_calls' => ['open', 'mmap']],
            'close(shm)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next166-169 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-time-error-syscall-next166-169', $plan()['dependencies'], true)),
    'vfs current source next166-169 preserves prior dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-environment-next162-165', $plan()['dependencies'], true)),
    'vfs current source next166-169 starts with hydrated sources' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next166-169 current time records julian day' => static fn (TestRunner $t) => $t->same(1717000000, $plan()['events'][0]['unix']),
    'vfs current source next166-169 int64 time is monotonic' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][1]['monotonic_for_source']),
    'vfs current source next166-169 system call enables per source' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][3]['enabled']),
    'vfs current source next166-169 next system call enumerates enabled calls' => static fn (TestRunner $t) => $t->same('pread64', $plan()['events'][4]['name']),
    'vfs current source next166-169 last error is source scoped' => static fn (TestRunner $t) => $t->same('SQLITE_IOERR_READ', $plan()['events'][5]['next']['sources']['main']['last_error']['code']),
    'vfs current source next166-169 selects wal source' => static fn (TestRunner $t) => $t->same('wal', $plan()['events'][6]['source']),
    'vfs current source next166-169 disabled system call reads false' => static fn (TestRunner $t) => $t->same(false, $plan()['events'][8]['enabled']),
    'vfs current source next166-169 open increments owner generation' => static fn (TestRunner $t) => $t->same(26, $plan()['events'][9]['next']['owner_generations']['/srv/www/wp-content/database/wp.sqlite']),
    'vfs current source next166-169 close restores first open source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][10]['next']['current_source']),
    'vfs current source next166-169 returns main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next166-169 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next166-169 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next166-169 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next166-169 rejects bad system call' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'xGetSystemCall', 'name' => 'bad name']], ['current' => $current])),
    'vfs current source next166-169 rejects bad error code' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'xGetLastError', 'code' => 'ioerr']], ['current' => $current])),
];
