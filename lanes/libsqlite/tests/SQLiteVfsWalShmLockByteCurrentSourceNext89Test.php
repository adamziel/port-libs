<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsWalShmLockByteCurrentSourceNext;

$path = '/srv/www/wp-content/database/.ht.sqlite';

$run = static fn (array $current, array $operations): array => SQLiteVfsWalShmLockByteCurrentSourceNext::plan($current, $operations);

$writer = static fn (): array => $run([], [
    'lock shared wp-reader 4',
    'shm read1 shared wp-reader',
    'lock reserved wp-import 9',
    'shm write exclusive wp-import',
    'lock pending wp-import 9',
    'lock exclusive wp-import 9',
    'yield wp-reader',
    'lock exclusive wp-import 9',
    'shm checkpoint exclusive wp-import',
]);

$currentSource = [
    'selected_path' => $path,
    'sources' => [
        $path => [
            'path' => $path,
            'generation' => 7,
            'holders' => ['wp-reader' => 'shared'],
            'shared_slots' => ['wp-reader' => 13],
            'shm_locks' => ['read2' => ['wp-reader' => 'shared']],
        ],
    ],
];

$replay = static fn (): array => $run($currentSource, [
    'lock reserved wp-import 3',
    'lock pending wp-import 3',
    'lock exclusive wp-import 3',
    'yield wp-reader',
    'lock exclusive wp-import 3',
    'shm read2 unlock wp-reader',
    'shm checkpoint exclusive wp-import',
]);

$nolock = static fn (): array => $run([
    'sources' => [
        $path => ['path' => $path, 'nolock' => true],
    ],
], [
    'lock shared wp-repair 1',
    'shm recover exclusive wp-repair',
]);

$multi = static fn (): array => $run([], [
    ['op' => 'lock', 'path' => $path, 'level' => 'shared', 'connection' => 'wp-main', 'shared_slot' => 1],
    ['op' => 'lock', 'path' => '/srv/www/wp-content/database/site-meta.sqlite', 'level' => 'shared', 'connection' => 'wp-meta', 'shared_slot' => 2],
    ['op' => 'shm', 'path' => '/srv/www/wp-content/database/site-meta.sqlite', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-meta'],
]);

return [
    'vfs wal shm lock byte current source next89 writer dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-wal-shm-lock-byte-current-source-next89', $writer()['dependencies'], true)),
    'vfs wal shm lock byte current source next89 writer event count' => static fn (TestRunner $t) => $t->same(9, count($writer()['events'])),
    'vfs wal shm lock byte current source next89 reader shared planned' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][0]['status']),
    'vfs wal shm lock byte current source next89 reader shared offset' => static fn (TestRunner $t) => $t->same(1073741830, $writer()['events'][0]['plan']['acquire'][0]['offset']),
    'vfs wal shm lock byte current source next89 reader holder stored' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $writer()['events'][0]['next']['selected']['holders']),
    'vfs wal shm lock byte current source next89 reader shm acquired' => static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][1]['status']),
    'vfs wal shm lock byte current source next89 reader shm stored' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $writer()['events'][1]['next']['selected']['shm_locks']['read1']),
    'vfs wal shm lock byte current source next89 reserved coexists with reader' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][2]['status']),
    'vfs wal shm lock byte current source next89 reserved byte acquired' => static fn (TestRunner $t) => $t->same('reserved', $writer()['events'][2]['plan']['acquire'][1]['name']),
    'vfs wal shm lock byte current source next89 write shm acquired' => static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][3]['status']),
    'vfs wal shm lock byte current source next89 pending coexists with reader' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][4]['status']),
    'vfs wal shm lock byte current source next89 pending keeps import' => static fn (TestRunner $t) => $t->same('pending', $writer()['events'][4]['next']['selected']['holders']['wp-import']),
    'vfs wal shm lock byte current source next89 exclusive blocked by reader' => static fn (TestRunner $t) => $t->same('blocked', $writer()['events'][5]['status']),
    'vfs wal shm lock byte current source next89 exclusive blocker names reader' => static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $writer()['events'][5]['blocking']),
    'vfs wal shm lock byte current source next89 exclusive block reason' => static fn (TestRunner $t) => $t->same('main_lock_conflict', $writer()['events'][5]['reason']),
    'vfs wal shm lock byte current source next89 yield releases main holder' => static fn (TestRunner $t) => $t->same(false, array_key_exists('wp-reader', $writer()['events'][6]['next']['selected']['holders'])),
    'vfs wal shm lock byte current source next89 yield releases shm read' => static fn (TestRunner $t) => $t->same([], $writer()['events'][6]['next']['selected']['shm_locks']['read1']),
    'vfs wal shm lock byte current source next89 exclusive succeeds after yield' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][7]['status']),
    'vfs wal shm lock byte current source next89 exclusive records shared range' => static fn (TestRunner $t) => $t->same(510, $writer()['events'][7]['plan']['acquire'][0]['length']),
    'vfs wal shm lock byte current source next89 checkpoint shm succeeds' => static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][8]['status']),
    'vfs wal shm lock byte current source next89 final status checkpoint' => static fn (TestRunner $t) => $t->same('acquired', $writer()['status']),
    'vfs wal shm lock byte current source next89 final holder count one' => static fn (TestRunner $t) => $t->same(1, $writer()['next']['holder_count']),
    'vfs wal shm lock byte current source next89 final shm lock count two' => static fn (TestRunner $t) => $t->same(2, $writer()['next']['shm_lock_count']),
    'vfs wal shm lock byte current source next89 final pending constant' => static fn (TestRunner $t) => $t->same(1073741824, $writer()['next']['selected']['constants']['pending']),
    'vfs wal shm lock byte current source next89 final shared last constant' => static fn (TestRunner $t) => $t->same(1073742335, $writer()['next']['selected']['constants']['shared_last']),

    'vfs wal shm lock byte current source next89 replay keeps current generation' => static fn (TestRunner $t) => $t->same(7, $replay()['current']['selected']['generation']),
    'vfs wal shm lock byte current source next89 replay current reader present' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $replay()['current']['selected']['holders']),
    'vfs wal shm lock byte current source next89 replay reserved planned' => static fn (TestRunner $t) => $t->same('planned', $replay()['events'][0]['status']),
    'vfs wal shm lock byte current source next89 replay pending planned' => static fn (TestRunner $t) => $t->same('planned', $replay()['events'][1]['status']),
    'vfs wal shm lock byte current source next89 replay exclusive blocked current reader' => static fn (TestRunner $t) => $t->same('blocked', $replay()['events'][2]['status']),
    'vfs wal shm lock byte current source next89 replay yield advances generation' => static fn (TestRunner $t) => $t->same(10, $replay()['events'][3]['generation']),
    'vfs wal shm lock byte current source next89 replay exclusive succeeds after yield' => static fn (TestRunner $t) => $t->same('planned', $replay()['events'][4]['status']),
    'vfs wal shm lock byte current source next89 replay shm unlock idempotent' => static fn (TestRunner $t) => $t->same('unlock', $replay()['events'][5]['mode']),
    'vfs wal shm lock byte current source next89 replay checkpoint acquired' => static fn (TestRunner $t) => $t->same('acquired', $replay()['events'][6]['status']),
    'vfs wal shm lock byte current source next89 replay final generation' => static fn (TestRunner $t) => $t->same(13, $replay()['next']['selected']['generation']),
    'vfs wal shm lock byte current source next89 replay reader gone' => static fn (TestRunner $t) => $t->same(false, array_key_exists('wp-reader', $replay()['next']['selected']['holders'])),
    'vfs wal shm lock byte current source next89 replay import exclusive' => static fn (TestRunner $t) => $t->same('exclusive', $replay()['next']['selected']['holders']['wp-import']),

    'vfs wal shm lock byte current source next89 nolock main blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][0]['status']),
    'vfs wal shm lock byte current source next89 nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $nolock()['events'][0]['reason']),
    'vfs wal shm lock byte current source next89 nolock no byte holders' => static fn (TestRunner $t) => $t->same([], $nolock()['events'][0]['next']['selected']['holders']),
    'vfs wal shm lock byte current source next89 nolock shm still acquired' => static fn (TestRunner $t) => $t->same('acquired', $nolock()['events'][1]['status']),
    'vfs wal shm lock byte current source next89 nolock recover holder' => static fn (TestRunner $t) => $t->same(['wp-repair' => 'exclusive'], $nolock()['next']['selected']['shm_locks']['recover']),

    'vfs wal shm lock byte current source next89 multi source count' => static fn (TestRunner $t) => $t->same(2, $multi()['next']['source_count']),
    'vfs wal shm lock byte current source next89 multi selected path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site-meta.sqlite', $multi()['next']['selected_path']),
    'vfs wal shm lock byte current source next89 multi main holder isolated' => static fn (TestRunner $t) => $t->same(['wp-main' => 'shared'], $multi()['next']['sources'][$path]['holders']),
    'vfs wal shm lock byte current source next89 multi meta holder isolated' => static fn (TestRunner $t) => $t->same(['wp-meta' => 'shared'], $multi()['next']['selected']['holders']),
    'vfs wal shm lock byte current source next89 multi meta shm lock' => static fn (TestRunner $t) => $t->same(['wp-meta' => 'shared'], $multi()['next']['selected']['shm_locks']['read0']),

    'vfs wal shm lock byte current source next89 shared conflict absent' => static fn (TestRunner $t) => $t->same([], $run([], ['lock shared a 1', 'lock shared b 2'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source next89 second reserved blocked' => static fn (TestRunner $t) => $t->same(['a:reserved'], $run([], ['lock reserved a 1', 'lock reserved b 2'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source next89 second pending blocked by pending' => static fn (TestRunner $t) => $t->same(['a:pending'], $run([], ['lock pending a 1', 'lock pending b 2'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source next89 shm shared coexists' => static fn (TestRunner $t) => $t->same('acquired', $run([], ['shm read0 shared a', 'shm read0 shared b'])['events'][1]['status']),
    'vfs wal shm lock byte current source next89 shm exclusive blocked by shared' => static fn (TestRunner $t) => $t->same(['a:shared'], $run([], ['shm read0 shared a', 'shm read0 exclusive b'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source next89 shm shared blocked by exclusive' => static fn (TestRunner $t) => $t->same(['a:exclusive'], $run([], ['shm read0 exclusive a', 'shm read0 shared b'])['events'][1]['blocking']),

    'vfs wal shm lock byte current source next89 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsWalShmLockByteCurrentSourceNext::plan([], [])),
    'vfs wal shm lock byte current source next89 rejects bad operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], ['checkpoint wp'])),
    'vfs wal shm lock byte current source next89 rejects bad path' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'path' => '', 'level' => 'shared', 'connection' => 'wp']])),
    'vfs wal shm lock byte current source next89 rejects bad connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'level' => 'shared', 'connection' => '']])),
    'vfs wal shm lock byte current source next89 rejects bad level' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'level' => 'bogus', 'connection' => 'wp']])),
    'vfs wal shm lock byte current source next89 rejects bad shm lock' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'shm', 'lock' => 'read9', 'mode' => 'shared', 'connection' => 'wp']])),
    'vfs wal shm lock byte current source next89 rejects bad shm mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'shm', 'lock' => 'read0', 'mode' => 'bad', 'connection' => 'wp']])),
    'vfs wal shm lock byte current source next89 rejects bad slot' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'level' => 'shared', 'connection' => 'wp', 'shared_slot' => 510]])),
];
