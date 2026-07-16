<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsWalShmLockBytePlan;

$path = '/srv/app/data/application.sqlite';
$tenantPath = '/srv/app/data/tenant-meta.sqlite';

$run = static fn (array $current, array $operations): array => SQLiteVfsWalShmLockBytePlan::plan($current, $operations);

$writer = static fn (): array => $run([], [
    'lock shared app-reader 4',
    'shm read1 shared app-reader',
    'lock reserved app-import 9',
    'shm write exclusive app-import',
    'lock pending app-import 9',
    'lock exclusive app-import 9',
    'yield app-reader',
    'lock exclusive app-import 9',
    'shm checkpoint exclusive app-import',
]);

$currentSource = [
    'selected_path' => $path,
    'sources' => [
        $path => [
            'path' => $path,
            'generation' => 7,
            'holders' => ['app-reader' => 'shared'],
            'shared_slots' => ['app-reader' => 13],
            'shm_locks' => ['read2' => ['app-reader' => 'shared']],
        ],
    ],
];

$replay = static fn (): array => $run($currentSource, [
    'lock reserved app-import 3',
    'lock pending app-import 3',
    'lock exclusive app-import 3',
    'yield app-reader',
    'lock exclusive app-import 3',
    'shm read2 unlock app-reader',
    'shm checkpoint exclusive app-import',
]);

$nolock = static fn (): array => $run([
    'sources' => [
        $path => ['path' => $path, 'nolock' => true],
    ],
], [
    'lock shared app-repair 1',
    'shm recover exclusive app-repair',
]);

$multi = static fn (): array => $run([], [
    ['op' => 'lock', 'path' => $path, 'level' => 'shared', 'connection' => 'app-main', 'shared_slot' => 1],
    ['op' => 'lock', 'path' => $tenantPath, 'level' => 'shared', 'connection' => 'app-meta', 'shared_slot' => 2],
    ['op' => 'shm', 'path' => $tenantPath, 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'app-meta'],
]);

return [
    'vfs wal shm lock byte canonical writer dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-wal-shm-lock-byte', $writer()['dependencies'], true)),
    'vfs wal shm lock byte current source writer event count' => static fn (TestRunner $t) => $t->same(9, count($writer()['events'])),
    'vfs wal shm lock byte current source reader shared planned' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][0]['status']),
    'vfs wal shm lock byte current source reader shared offset' => static fn (TestRunner $t) => $t->same(1073741830, $writer()['events'][0]['plan']['acquire'][0]['offset']),
    'vfs wal shm lock byte current source reader holder stored' => static fn (TestRunner $t) => $t->same(['app-reader' => 'shared'], $writer()['events'][0]['next']['selected']['holders']),
    'vfs wal shm lock byte current source reader shm acquired' => static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][1]['status']),
    'vfs wal shm lock byte current source reader shm stored' => static fn (TestRunner $t) => $t->same(['app-reader' => 'shared'], $writer()['events'][1]['next']['selected']['shm_locks']['read1']),
    'vfs wal shm lock byte current source reserved coexists with reader' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][2]['status']),
    'vfs wal shm lock byte current source reserved byte acquired' => static fn (TestRunner $t) => $t->same('reserved', $writer()['events'][2]['plan']['acquire'][1]['name']),
    'vfs wal shm lock byte current source write shm acquired' => static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][3]['status']),
    'vfs wal shm lock byte current source pending coexists with reader' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][4]['status']),
    'vfs wal shm lock byte current source pending keeps import' => static fn (TestRunner $t) => $t->same('pending', $writer()['events'][4]['next']['selected']['holders']['app-import']),
    'vfs wal shm lock byte current source exclusive blocked by reader' => static fn (TestRunner $t) => $t->same('blocked', $writer()['events'][5]['status']),
    'vfs wal shm lock byte current source exclusive blocker names reader' => static fn (TestRunner $t) => $t->same(['app-reader:shared'], $writer()['events'][5]['blocking']),
    'vfs wal shm lock byte current source exclusive block reason' => static fn (TestRunner $t) => $t->same('main_lock_conflict', $writer()['events'][5]['reason']),
    'vfs wal shm lock byte current source yield releases main holder' => static fn (TestRunner $t) => $t->same(false, array_key_exists('app-reader', $writer()['events'][6]['next']['selected']['holders'])),
    'vfs wal shm lock byte current source yield releases shm read' => static fn (TestRunner $t) => $t->same([], $writer()['events'][6]['next']['selected']['shm_locks']['read1']),
    'vfs wal shm lock byte current source exclusive succeeds after yield' => static fn (TestRunner $t) => $t->same('planned', $writer()['events'][7]['status']),
    'vfs wal shm lock byte current source exclusive records shared range' => static fn (TestRunner $t) => $t->same(510, $writer()['events'][7]['plan']['acquire'][0]['length']),
    'vfs wal shm lock byte current source checkpoint shm succeeds' => static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][8]['status']),
    'vfs wal shm lock byte current source final status checkpoint' => static fn (TestRunner $t) => $t->same('acquired', $writer()['status']),
    'vfs wal shm lock byte current source final holder count one' => static fn (TestRunner $t) => $t->same(1, $writer()['next']['holder_count']),
    'vfs wal shm lock byte current source final shm lock count two' => static fn (TestRunner $t) => $t->same(2, $writer()['next']['shm_lock_count']),
    'vfs wal shm lock byte current source final pending constant' => static fn (TestRunner $t) => $t->same(1073741824, $writer()['next']['selected']['constants']['pending']),
    'vfs wal shm lock byte current source final shared last constant' => static fn (TestRunner $t) => $t->same(1073742335, $writer()['next']['selected']['constants']['shared_last']),

    'vfs wal shm lock byte current source replay keeps current generation' => static fn (TestRunner $t) => $t->same(7, $replay()['current']['selected']['generation']),
    'vfs wal shm lock byte current source replay current reader present' => static fn (TestRunner $t) => $t->same(['app-reader' => 'shared'], $replay()['current']['selected']['holders']),
    'vfs wal shm lock byte current source replay reserved planned' => static fn (TestRunner $t) => $t->same('planned', $replay()['events'][0]['status']),
    'vfs wal shm lock byte current source replay pending planned' => static fn (TestRunner $t) => $t->same('planned', $replay()['events'][1]['status']),
    'vfs wal shm lock byte current source replay exclusive blocked current reader' => static fn (TestRunner $t) => $t->same('blocked', $replay()['events'][2]['status']),
    'vfs wal shm lock byte current source replay yield advances generation' => static fn (TestRunner $t) => $t->same(10, $replay()['events'][3]['generation']),
    'vfs wal shm lock byte current source replay exclusive succeeds after yield' => static fn (TestRunner $t) => $t->same('planned', $replay()['events'][4]['status']),
    'vfs wal shm lock byte current source replay shm unlock idempotent' => static fn (TestRunner $t) => $t->same('unlock', $replay()['events'][5]['mode']),
    'vfs wal shm lock byte current source replay checkpoint acquired' => static fn (TestRunner $t) => $t->same('acquired', $replay()['events'][6]['status']),
    'vfs wal shm lock byte current source replay final generation' => static fn (TestRunner $t) => $t->same(13, $replay()['next']['selected']['generation']),
    'vfs wal shm lock byte current source replay reader gone' => static fn (TestRunner $t) => $t->same(false, array_key_exists('app-reader', $replay()['next']['selected']['holders'])),
    'vfs wal shm lock byte current source replay import exclusive' => static fn (TestRunner $t) => $t->same('exclusive', $replay()['next']['selected']['holders']['app-import']),

    'vfs wal shm lock byte current source nolock main blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][0]['status']),
    'vfs wal shm lock byte current source nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $nolock()['events'][0]['reason']),
    'vfs wal shm lock byte current source nolock no byte holders' => static fn (TestRunner $t) => $t->same([], $nolock()['events'][0]['next']['selected']['holders']),
    'vfs wal shm lock byte current source nolock shm still acquired' => static fn (TestRunner $t) => $t->same('acquired', $nolock()['events'][1]['status']),
    'vfs wal shm lock byte current source nolock recover holder' => static fn (TestRunner $t) => $t->same(['app-repair' => 'exclusive'], $nolock()['next']['selected']['shm_locks']['recover']),

    'vfs wal shm lock byte current source multi source count' => static fn (TestRunner $t) => $t->same(2, $multi()['next']['source_count']),
    'vfs wal shm lock byte current source multi selected path' => static fn (TestRunner $t) => $t->same($tenantPath, $multi()['next']['selected_path']),
    'vfs wal shm lock byte current source multi main holder isolated' => static fn (TestRunner $t) => $t->same(['app-main' => 'shared'], $multi()['next']['sources'][$path]['holders']),
    'vfs wal shm lock byte current source multi meta holder isolated' => static fn (TestRunner $t) => $t->same(['app-meta' => 'shared'], $multi()['next']['selected']['holders']),
    'vfs wal shm lock byte current source multi meta shm lock' => static fn (TestRunner $t) => $t->same(['app-meta' => 'shared'], $multi()['next']['selected']['shm_locks']['read0']),

    'vfs wal shm lock byte current source shared conflict absent' => static fn (TestRunner $t) => $t->same([], $run([], ['lock shared a 1', 'lock shared b 2'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source second reserved blocked' => static fn (TestRunner $t) => $t->same(['a:reserved'], $run([], ['lock reserved a 1', 'lock reserved b 2'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source second pending blocked by pending' => static fn (TestRunner $t) => $t->same(['a:pending'], $run([], ['lock pending a 1', 'lock pending b 2'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source shm shared coexists' => static fn (TestRunner $t) => $t->same('acquired', $run([], ['shm read0 shared a', 'shm read0 shared b'])['events'][1]['status']),
    'vfs wal shm lock byte current source shm exclusive blocked by shared' => static fn (TestRunner $t) => $t->same(['a:shared'], $run([], ['shm read0 shared a', 'shm read0 exclusive b'])['events'][1]['blocking']),
    'vfs wal shm lock byte current source shm shared blocked by exclusive' => static fn (TestRunner $t) => $t->same(['a:exclusive'], $run([], ['shm read0 exclusive a', 'shm read0 shared b'])['events'][1]['blocking']),

    'vfs wal shm lock byte canonical rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsWalShmLockBytePlan::plan([], [])),
    'vfs wal shm lock byte current source rejects bad operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], ['checkpoint app'])),
    'vfs wal shm lock byte current source rejects bad path' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'path' => '', 'level' => 'shared', 'connection' => 'app']])),
    'vfs wal shm lock byte current source rejects bad connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'level' => 'shared', 'connection' => '']])),
    'vfs wal shm lock byte current source rejects bad level' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'level' => 'bogus', 'connection' => 'app']])),
    'vfs wal shm lock byte current source rejects bad shm lock' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'shm', 'lock' => 'read9', 'mode' => 'shared', 'connection' => 'app']])),
    'vfs wal shm lock byte current source rejects bad shm mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'shm', 'lock' => 'read0', 'mode' => 'bad', 'connection' => 'app']])),
    'vfs wal shm lock byte current source rejects bad slot' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'lock', 'level' => 'shared', 'connection' => 'app', 'shared_slot' => 510]])),
];
