<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext182185Plan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 41,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs178181-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'directory' => '/srv/www/wp-content/database',
            'known_dirs' => ['/srv/www/wp-content/database'],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext182185Plan::run([
            'tempname(upload)',
            'syncdir(/srv/www/wp-content/database)',
            'readonly(true)',
            'unlink(/srv/www/wp-content/database/wp.sqlite)',
            'readonly(false)',
            'unlink(/srv/www/wp-content/database/wp.sqlite-journal)',
            ['op' => 'open', 'source' => 'scratch', 'path' => '/srv/www/wp-content/database/etilqs-182.tmp', 'directory' => '/srv/www/wp-content/database'],
            ['op' => 'xMkDir', 'path' => '/tmp/not-owned'],
            'close(scratch)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next182-185 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-temp-dir-readonly-next182-185', $plan()['dependencies'], true)),
    'vfs current source next182-185 preserves 178-181 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-sync-truncate-size-reserve-next178-181', $plan()['dependencies'], true)),
    'vfs current source next182-185 starts with one hydrated source' => static fn (TestRunner $t) => $t->same(1, $plan()['current']['source_count']),
    'vfs current source next182-185 creates temp path in current directory' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][0]['same_directory']),
    'vfs current source next182-185 records temp path' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][0]['temp_count']),
    'vfs current source next182-185 syncs known directory' => static fn (TestRunner $t) => $t->same('synced', $plan()['events'][1]['status']),
    'vfs current source next182-185 marks readonly' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][2]['readonly']),
    'vfs current source next182-185 blocks readonly unlink' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][3]['status']),
    'vfs current source next182-185 restores writable mode' => static fn (TestRunner $t) => $t->same('writable', $plan()['events'][4]['status']),
    'vfs current source next182-185 unlinks same owner file' => static fn (TestRunner $t) => $t->same('unlinked', $plan()['events'][5]['status']),
    'vfs current source next182-185 open increments scratch owner generation' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][6]['next']['owner_generations']['/srv/www/wp-content/database/etilqs-182.tmp']),
    'vfs current source next182-185 blocks foreign mkdir' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][7]['status']),
    'vfs current source next182-185 close restores main source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][8]['next']['current_source']),
    'vfs current source next182-185 final source count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['source_count']),
    'vfs current source next182-185 final open count' => static fn (TestRunner $t) => $t->same(1, $plan()['next']['open_source_count']),
    'vfs current source next182-185 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext182185Plan::run([])),
    'vfs current source next182-185 rejects bad temp suffix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext182185Plan::run([['op' => 'xTempName', 'suffix' => 'bad suffix']], ['current' => $current])),
    'vfs current source next182-185 rejects negative directory sync count' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext182185Plan::run(['tempname(ok)'], ['current' => ['sources' => ['main' => ['path' => '/tmp/a.sqlite', 'directory_syncs' => -1]], 'current_source' => 'main']])),
];
