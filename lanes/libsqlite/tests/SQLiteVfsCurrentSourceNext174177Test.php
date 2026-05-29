<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext174177Plan;

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
            'existing_paths' => [
                '/srv/www/wp-content/database/wp.sqlite',
                '/srv/www/wp-content/database/wp.sqlite-wal',
                '/srv/www/wp-content/database/wp.sqlite-shm',
            ],
        ],
        'journal' => [
            'handle' => 'vfs170173-2',
            'path' => '/srv/www/wp-content/database/wp.sqlite-journal',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'existing_paths' => ['/srv/www/wp-content/database/wp.sqlite-journal'],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext174177Plan::run([
            'access(-wal)',
            'delete(-wal)',
            'access(-wal)',
            'randomness(8,wpseed)',
            'sleep(2500)',
            'source(journal)',
            ['op' => 'xDelete', 'path' => '/tmp/outside.sqlite-journal'],
            ['op' => 'open', 'source' => 'temp', 'path' => '/srv/www/wp-content/database/etilqs-9.tmp', 'existing_paths' => ['/srv/www/wp-content/database/etilqs-9.tmp']],
            'close(temp)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next174-177 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-access-delete-random-sleep-next174-177', $plan()['dependencies'], true)),
    'vfs current source next174-177 preserves 170-173 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-path-control-names-next170-173', $plan()['dependencies'], true)),
    'vfs current source next174-177 starts with hydrated sources' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next174-177 sees wal before delete' => static fn (TestRunner $t) => $t->same('exists', $plan()['events'][0]['status']),
    'vfs current source next174-177 deletes wal on same owner' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][1]['same_owner']),
    'vfs current source next174-177 removes wal from selected source paths' => static fn (TestRunner $t) => $t->same(false, in_array('/srv/www/wp-content/database/wp.sqlite-wal', $plan()['events'][1]['next']['sources']['main']['existing_paths'], true)),
    'vfs current source next174-177 misses wal after delete' => static fn (TestRunner $t) => $t->same('missing', $plan()['events'][2]['status']),
    'vfs current source next174-177 returns requested random bytes as hex' => static fn (TestRunner $t) => $t->same(16, strlen($plan()['events'][3]['hex'])),
    'vfs current source next174-177 records sleep total' => static fn (TestRunner $t) => $t->same(2500, $plan()['events'][4]['total_microseconds']),
    'vfs current source next174-177 selects journal source' => static fn (TestRunner $t) => $t->same('journal', $plan()['events'][5]['source']),
    'vfs current source next174-177 blocks cross owner delete' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][6]['status']),
    'vfs current source next174-177 open increments temp owner generation' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][7]['next']['owner_generations']['/srv/www/wp-content/database/etilqs-9.tmp']),
    'vfs current source next174-177 close restores first open source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][8]['next']['current_source']),
    'vfs current source next174-177 returns main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next174-177 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next174-177 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next174-177 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext174177Plan::run([])),
    'vfs current source next174-177 rejects bad random seed' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext174177Plan::run([['op' => 'xRandomness', 'seed' => 'bad seed']], ['current' => $current])),
    'vfs current source next174-177 rejects negative sleep' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext174177Plan::run([['op' => 'xSleep', 'microseconds' => -1]], ['current' => $current])),
];
