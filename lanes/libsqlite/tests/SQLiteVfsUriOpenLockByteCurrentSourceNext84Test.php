<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsUriOpenLockByteCurrentSourceNext;

$vfsUriLock84Plan = static fn (): array => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([
    [
        'name' => 'main',
        'filename' => 'file:/srv/www/wp-content/database/site.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
        'operations' => [
            'shared wp-reader 4',
            'reserved wp-import 9',
            'pending wp-import',
        ],
    ],
    [
        'name' => 'analytics',
        'filename' => 'file://localhost/srv/www/wp-content/database/analytics%20copy.sqlite?mode=ro&immutable=1&cache=private',
        'operations' => [
            ['level' => 'shared', 'connection' => 'wp-report', 'shared_slot' => 11],
        ],
    ],
    [
        'name' => 'repair',
        'filename' => 'file:/srv/www/wp-content/database/repair.sqlite?mode=rw&nolock=1',
        'operations' => [
            'shared wp-repair 2',
        ],
    ],
]);

$vfsUriLock84Busy = static fn (): array => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([
    [
        'name' => 'busy',
        'filename' => 'file:/srv/www/wp-content/database/site.sqlite?mode=rw&cache=shared',
        'lock_available' => false,
        'busy_timeout' => 25,
        'operations' => ['shared wp-import'],
    ],
]);

return [
    'vfs uri open lock byte current source next84 event count' => static fn (TestRunner $t) => $t->same(8, $vfsUriLock84Plan()['count']),
    'vfs uri open lock byte current source next84 final status follows nolock block' => static fn (TestRunner $t) => $t->same('blocked', $vfsUriLock84Plan()['status']),
    'vfs uri open lock byte current source next84 starts without sources' => static fn (TestRunner $t) => $t->same([], $vfsUriLock84Plan()['current']['sources']),
    'vfs uri open lock byte current source next84 main open event ready' => static fn (TestRunner $t) => $t->same('ready', $vfsUriLock84Plan()['events'][0]['status']),
    'vfs uri open lock byte current source next84 main source name' => static fn (TestRunner $t) => $t->same('main', $vfsUriLock84Plan()['events'][0]['source']),
    'vfs uri open lock byte current source next84 main uri input preserved' => static fn (TestRunner $t) => $t->same('file:/srv/www/wp-content/database/site.sqlite?mode=rw&cache=shared&vfs=unix&psow=1', $vfsUriLock84Plan()['events'][0]['next_source']['input']),
    'vfs uri open lock byte current source next84 main path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site.sqlite', $vfsUriLock84Plan()['events'][0]['next_source']['path']),
    'vfs uri open lock byte current source next84 main cache shared' => static fn (TestRunner $t) => $t->same('shared', $vfsUriLock84Plan()['events'][0]['next_source']['cache']),
    'vfs uri open lock byte current source next84 main vfs retained' => static fn (TestRunner $t) => $t->same('unix', $vfsUriLock84Plan()['events'][0]['next_source']['vfs']),
    'vfs uri open lock byte current source next84 main open dependencies include shared cache' => static fn (TestRunner $t) => $t->same(true, in_array('shared-cache-coordination', $vfsUriLock84Plan()['events'][0]['dependencies'], true)),
    'vfs uri open lock byte current source next84 main open dependencies include vfs' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-admission', $vfsUriLock84Plan()['events'][0]['dependencies'], true)),
    'vfs uri open lock byte current source next84 pending byte constant' => static fn (TestRunner $t) => $t->same(1073741824, $vfsUriLock84Plan()['events'][0]['next_source']['constants']['pending']),
    'vfs uri open lock byte current source next84 shared first constant' => static fn (TestRunner $t) => $t->same(1073741826, $vfsUriLock84Plan()['events'][0]['next_source']['constants']['shared_first']),
    'vfs uri open lock byte current source next84 shared last constant' => static fn (TestRunner $t) => $t->same(1073742335, $vfsUriLock84Plan()['events'][0]['next_source']['constants']['shared_last']),
    'vfs uri open lock byte current source next84 main shared lock event' => static fn (TestRunner $t) => $t->same('lock', $vfsUriLock84Plan()['events'][1]['kind']),
    'vfs uri open lock byte current source next84 main shared current has no holders' => static fn (TestRunner $t) => $t->same([], $vfsUriLock84Plan()['events'][1]['current_source']['holders']),
    'vfs uri open lock byte current source next84 main shared acquired' => static fn (TestRunner $t) => $t->same('acquired', $vfsUriLock84Plan()['events'][1]['result']['status']),
    'vfs uri open lock byte current source next84 main shared holder recorded' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $vfsUriLock84Plan()['events'][1]['next_source']['holders']),
    'vfs uri open lock byte current source next84 main shared slot offset' => static fn (TestRunner $t) => $t->same(1073741830, $vfsUriLock84Plan()['events'][1]['plan']['ranges'][0]['offset']),
    'vfs uri open lock byte current source next84 reserved retains reader' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'reserved'], $vfsUriLock84Plan()['events'][2]['next_source']['holders']),
    'vfs uri open lock byte current source next84 reserved byte name' => static fn (TestRunner $t) => $t->same('reserved', $vfsUriLock84Plan()['events'][2]['plan']['ranges'][1]['name']),
    'vfs uri open lock byte current source next84 pending holder replaces reserved' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'pending'], $vfsUriLock84Plan()['events'][3]['next_source']['holders']),
    'vfs uri open lock byte current source next84 pending lock has pending range' => static fn (TestRunner $t) => $t->same('pending', $vfsUriLock84Plan()['events'][3]['plan']['ranges'][0]['name']),
    'vfs uri open lock byte current source next84 analytics open event follows main locks' => static fn (TestRunner $t) => $t->same('analytics', $vfsUriLock84Plan()['events'][4]['source']),
    'vfs uri open lock byte current source next84 analytics current source absent before open' => static fn (TestRunner $t) => $t->same(null, $vfsUriLock84Plan()['events'][4]['current_source']),
    'vfs uri open lock byte current source next84 analytics decoded space path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/analytics copy.sqlite', $vfsUriLock84Plan()['events'][4]['next_source']['path']),
    'vfs uri open lock byte current source next84 analytics is immutable' => static fn (TestRunner $t) => $t->same(true, $vfsUriLock84Plan()['events'][4]['next_source']['immutable']),
    'vfs uri open lock byte current source next84 analytics mode ro' => static fn (TestRunner $t) => $t->same('ro', $vfsUriLock84Plan()['events'][4]['next_source']['mode']),
    'vfs uri open lock byte current source next84 analytics dependency immutable' => static fn (TestRunner $t) => $t->same(true, in_array('immutable-readonly-open', $vfsUriLock84Plan()['events'][4]['dependencies'], true)),
    'vfs uri open lock byte current source next84 analytics shared read lock acquired' => static fn (TestRunner $t) => $t->same('acquired', $vfsUriLock84Plan()['events'][5]['result']['status']),
    'vfs uri open lock byte current source next84 analytics shared offset' => static fn (TestRunner $t) => $t->same(1073741837, $vfsUriLock84Plan()['events'][5]['plan']['ranges'][0]['offset']),
    'vfs uri open lock byte current source next84 analytics holder isolated by path' => static fn (TestRunner $t) => $t->same(['wp-report' => 'shared'], $vfsUriLock84Plan()['events'][5]['next_source']['holders']),
    'vfs uri open lock byte current source next84 repair open uses nolock uri' => static fn (TestRunner $t) => $t->same(true, $vfsUriLock84Plan()['events'][6]['next_source']['nolock']),
    'vfs uri open lock byte current source next84 repair dependency nolock' => static fn (TestRunner $t) => $t->same(true, in_array('nolock-open', $vfsUriLock84Plan()['events'][6]['dependencies'], true)),
    'vfs uri open lock byte current source next84 repair lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $vfsUriLock84Plan()['events'][7]['result']['status']),
    'vfs uri open lock byte current source next84 repair lock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $vfsUriLock84Plan()['events'][7]['result']['reason']),
    'vfs uri open lock byte current source next84 repair lock has no ranges' => static fn (TestRunner $t) => $t->same([], $vfsUriLock84Plan()['events'][7]['plan']['ranges']),
    'vfs uri open lock byte current source next84 final selected repair source' => static fn (TestRunner $t) => $t->same('repair', $vfsUriLock84Plan()['next']['selected_source']),
    'vfs uri open lock byte current source next84 final has three sources' => static fn (TestRunner $t) => $t->same(['main', 'analytics', 'repair'], array_keys($vfsUriLock84Plan()['next']['sources'])),
    'vfs uri open lock byte current source next84 final main holders preserved' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'pending'], $vfsUriLock84Plan()['next']['sources']['main']['holders']),
    'vfs uri open lock byte current source next84 final analytics holders preserved' => static fn (TestRunner $t) => $t->same(['wp-report' => 'shared'], $vfsUriLock84Plan()['next']['sources']['analytics']['holders']),
    'vfs uri open lock byte current source next84 final repair holders empty' => static fn (TestRunner $t) => $t->same([], $vfsUriLock84Plan()['next']['sources']['repair']['holders']),
    'vfs uri open lock byte current source next84 aggregate holders keyed by source' => static fn (TestRunner $t) => $t->same(['wp-report' => 'shared'], $vfsUriLock84Plan()['next']['holders']['analytics']),
    'vfs uri open lock byte current source next84 dependency marker present' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-open-lock-byte-current-source-next84', $vfsUriLock84Plan()['dependencies'], true)),
    'vfs uri open lock byte current source next84 dependency has file uri parser' => static fn (TestRunner $t) => $t->same(true, in_array('file-uri-parser', $vfsUriLock84Plan()['dependencies'], true)),
    'vfs uri open lock byte current source next84 dependency has byte range' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-lock-byte-range', $vfsUriLock84Plan()['dependencies'], true)),
    'vfs uri open lock byte current source next84 dependency has lock state' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-lock-state-application', $vfsUriLock84Plan()['dependencies'], true)),
    'vfs uri open lock byte current source next84 busy open is blocked' => static fn (TestRunner $t) => $t->same('blocked', $vfsUriLock84Busy()['status']),
    'vfs uri open lock byte current source next84 busy has one event' => static fn (TestRunner $t) => $t->same(1, $vfsUriLock84Busy()['count']),
    'vfs uri open lock byte current source next84 busy preserves source snapshot' => static fn (TestRunner $t) => $t->same('busy', $vfsUriLock84Busy()['events'][0]['next_source']['name']),
    'vfs uri open lock byte current source next84 busy skips requested locks' => static fn (TestRunner $t) => $t->same([], $vfsUriLock84Busy()['next']['sources']['busy']['holders']),
    'vfs uri open lock byte current source next84 busy dependency includes busy handler' => static fn (TestRunner $t) => $t->same(true, in_array('busy-handler', $vfsUriLock84Busy()['dependencies'], true)),
    'vfs uri open lock byte current source next84 rejects empty sources' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([])),
    'vfs uri open lock byte current source next84 rejects empty source name' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([['name' => '', 'filename' => '/tmp/a.sqlite']])),
    'vfs uri open lock byte current source next84 rejects missing filename' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([['name' => 'main']])),
    'vfs uri open lock byte current source next84 rejects malformed operation string' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([['name' => 'main', 'filename' => '/tmp/a.sqlite', 'operations' => ['unlock wp']]])),
    'vfs uri open lock byte current source next84 rejects bad shared slot type' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([['name' => 'main', 'filename' => '/tmp/a.sqlite', 'operations' => [['connection' => 'wp', 'shared_slot' => '2']]]])),
];
