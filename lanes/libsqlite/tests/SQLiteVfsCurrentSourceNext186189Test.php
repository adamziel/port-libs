<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 47,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs182185-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'sector_size' => 4096,
            'characteristics' => ['powersafe_overwrite', 'safe_append'],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'lock(shared)',
            'checkreservedlock()',
            'lock(reserved)',
            'checkreservedlock()',
            'filecontrol(chunk_size,8192)',
            'sector()',
            'characteristics()',
            ['op' => 'open', 'source' => 'wal', 'path' => '/srv/www/wp-content/database/wp.sqlite-wal', 'sector_size' => 8192, 'characteristics' => ['sequential']],
            ['op' => 'xLock', 'level' => 'exclusive'],
            ['op' => 'xUnlock', 'level' => 'none'],
            'close(wal)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next186-189 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-lock-filecontrol-next186-189', $plan()['dependencies'], true)),
    'vfs current source next186-189 preserves 182-185 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-temp-dir-readonly-next182-185', $plan()['dependencies'], true)),
    'vfs current source next186-189 starts with hydrated source' => static fn (TestRunner $t) => $t->same(1, $plan()['current']['source_count']),
    'vfs current source next186-189 shared lock is not reserved' => static fn (TestRunner $t) => $t->same(false, $plan()['events'][0]['reserved']),
    'vfs current source next186-189 check reports clear before reserved' => static fn (TestRunner $t) => $t->same('clear', $plan()['events'][1]['status']),
    'vfs current source next186-189 records reserved lock' => static fn (TestRunner $t) => $t->same('reserved', $plan()['events'][2]['lock']),
    'vfs current source next186-189 check reports reserved' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][3]['reserved']),
    'vfs current source next186-189 records file control value' => static fn (TestRunner $t) => $t->same(8192, $plan()['events'][4]['value']),
    'vfs current source next186-189 reports sector size' => static fn (TestRunner $t) => $t->same(4096, $plan()['events'][5]['sector_size']),
    'vfs current source next186-189 reports sorted characteristics' => static fn (TestRunner $t) => $t->same(['powersafe_overwrite', 'safe_append'], $plan()['events'][6]['characteristics']),
    'vfs current source next186-189 open increments wal owner generation' => static fn (TestRunner $t) => $t->same(48, $plan()['events'][7]['next']['owner_generations']['/srv/www/wp-content/database/wp.sqlite']),
    'vfs current source next186-189 open hydrates wal sector size' => static fn (TestRunner $t) => $t->same(8192, $plan()['events'][7]['next']['sources']['wal']['sector_size']),
    'vfs current source next186-189 exclusive lock is reserved' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][8]['reserved']),
    'vfs current source next186-189 unlock clears reserved state' => static fn (TestRunner $t) => $t->same(false, $plan()['events'][9]['reserved']),
    'vfs current source next186-189 close restores main source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][10]['next']['current_source']),
    'vfs current source next186-189 final source count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['source_count']),
    'vfs current source next186-189 final open count' => static fn (TestRunner $t) => $t->same(1, $plan()['next']['open_source_count']),
    'vfs current source next186-189 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next186-189 rejects bad lock level' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'xLock', 'level' => 'writer']], ['current' => $current])),
    'vfs current source next186-189 rejects small sector size' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'open', 'source' => 'bad', 'path' => '/tmp/bad.sqlite', 'sector_size' => 128]], ['current' => $current])),
    'vfs current source next186-189 rejects unknown characteristic' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'open', 'source' => 'bad', 'path' => '/tmp/bad.sqlite', 'characteristics' => ['mystery']]], ['current' => $current])),
];
