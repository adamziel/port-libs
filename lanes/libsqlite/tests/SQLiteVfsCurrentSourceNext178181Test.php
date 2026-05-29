<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext178181Plan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 32,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs170173-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'size' => 4096,
            'reserved_bytes' => 0,
        ],
        'journal' => [
            'handle' => 'vfs170173-2',
            'path' => '/srv/www/wp-content/database/wp.sqlite-journal',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'size' => 1024,
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext178181Plan::run([
            'write(512,4096)',
            'sync(full)',
            ['op' => 'xWrite', 'bytes' => 128, 'offset' => 8192],
            'truncate(2048)',
            'reserve(64)',
            'source(journal)',
            ['op' => 'xSync', 'mode' => 'normal'],
            ['op' => 'open', 'source' => 'temp', 'path' => '/srv/www/wp-content/database/etilqs-9.tmp', 'size' => 256, 'reserved_bytes' => 8],
            'close(temp)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next178-181 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-sync-truncate-size-reserve-next178-181', $plan()['dependencies'], true)),
    'vfs current source next178-181 preserves 174-177 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-access-delete-random-sleep-next174-177', $plan()['dependencies'], true)),
    'vfs current source next178-181 starts with hydrated sources' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next178-181 write extends size' => static fn (TestRunner $t) => $t->same(4608, $plan()['events'][0]['size']),
    'vfs current source next178-181 write marks dirty bytes' => static fn (TestRunner $t) => $t->same(512, $plan()['events'][0]['dirty_bytes']),
    'vfs current source next178-181 sync flushes dirty bytes' => static fn (TestRunner $t) => $t->same(512, $plan()['events'][1]['flushed_bytes']),
    'vfs current source next178-181 sync records mode' => static fn (TestRunner $t) => $t->same('full', $plan()['events'][1]['next']['sources']['main']['last_sync']),
    'vfs current source next178-181 sparse write extends size' => static fn (TestRunner $t) => $t->same(8320, $plan()['events'][2]['size']),
    'vfs current source next178-181 truncate shrinks file' => static fn (TestRunner $t) => $t->same(2048, $plan()['events'][3]['next']['sources']['main']['size']),
    'vfs current source next178-181 reserve reports usable size' => static fn (TestRunner $t) => $t->same(1984, $plan()['events'][4]['usable_size']),
    'vfs current source next178-181 selects journal source' => static fn (TestRunner $t) => $t->same('journal', $plan()['events'][5]['source']),
    'vfs current source next178-181 syncs selected journal' => static fn (TestRunner $t) => $t->same('journal', $plan()['events'][6]['source']),
    'vfs current source next178-181 open increments temp owner generation' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][7]['next']['owner_generations']['/srv/www/wp-content/database/etilqs-9.tmp']),
    'vfs current source next178-181 open hydrates reserve bytes' => static fn (TestRunner $t) => $t->same(8, $plan()['events'][7]['next']['sources']['temp']['reserved_bytes']),
    'vfs current source next178-181 close restores first open source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][8]['next']['current_source']),
    'vfs current source next178-181 returns main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next178-181 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next178-181 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next178-181 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext178181Plan::run([])),
    'vfs current source next178-181 rejects bad sync mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext178181Plan::run([['op' => 'xSync', 'mode' => 'unsafe']], ['current' => $current])),
    'vfs current source next178-181 rejects zero write' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext178181Plan::run(['write(0)'], ['current' => $current])),
];
