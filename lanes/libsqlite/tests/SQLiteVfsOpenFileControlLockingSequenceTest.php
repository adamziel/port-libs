<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenFileControl;

$vfsOpenSequenceRoot = static fn (): string => sys_get_temp_dir() . '/port-libsqlite-vfs-open-filecontrol-sequence-test-' . bin2hex(random_bytes(4));
$vfsOpenSequenceRun = static fn (array $ops, array $options = []): array => SQLiteVfsOpenFileControl::openFileControlSequence(
    $ops,
    $options + [
        'root' => $vfsOpenSequenceRoot(),
        'filename' => '/srv/www/wp-content/database/.ht.sqlite',
        'file_exists' => true,
        'directory_writable' => true,
        'sector_size' => 4096,
        'device_flags' => ['safe_append', 'powersafe_overwrite'],
        'sync_mode' => 'full',
    ],
);
$vfsOpenSequenceBasic = static fn (): array => $vfsOpenSequenceRun([
    'file_control(chunk_size, 4096)',
    'file_control(size_hint, 5000)',
    'lock shared by wp-reader 7',
    'lock reserved by wp-import 9',
    'lock pending by wp-import',
    'release wp-reader',
    'lock exclusive by wp-import',
    'pragma mmap_size=8192',
    'file_control(persist_wal, on)',
    'unlock wp-import',
]);
$vfsOpenSequenceBlocked = static fn (): array => $vfsOpenSequenceRun([
    'lock shared by wp-reader 3',
    'lock pending by wp-import',
    'lock shared by wp-rest 4',
]);
$vfsOpenSequenceReadonly = static fn (): array => $vfsOpenSequenceRun([
    'pragma chunk_size=4096',
    'file_control(size_hint, 8192)',
    'pragma mmap_size=16384',
], ['filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=ro', 'file_exists' => true]);
$vfsOpenSequenceNolock = static fn (): array => $vfsOpenSequenceRun([
    'lock shared by repair-copy 1',
    'pragma busy_timeout=250',
    'pragma mmap_size=4096',
], ['filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?nolock=1']);
$vfsOpenSequenceArrayOps = static fn (): array => $vfsOpenSequenceRun([
    ['op' => 'chunk_size', 'value' => 2048],
    ['op' => 'size_hint', 'value' => 2049],
    ['kind' => 'lock', 'level' => 'shared', 'connection' => 'wp-cli', 'shared_index' => 2],
    ['kind' => 'unlock', 'connection' => 'wp-cli'],
]);

return [
    'vfs open filecontrol locking open filecontrol sequence count' => static fn (TestRunner $t) => $t->same(10, $vfsOpenSequenceBasic()['count']),
    'vfs open filecontrol locking open filecontrol sequence final status' => static fn (TestRunner $t) => $t->same('released', $vfsOpenSequenceBasic()['status']),
    'vfs open filecontrol locking open filecontrol sequence starts without holders' => static fn (TestRunner $t) => $t->same([], $vfsOpenSequenceBasic()['current']['holders']),
    'vfs open filecontrol locking open filecontrol sequence starts at zero file size' => static fn (TestRunner $t) => $t->same(0, $vfsOpenSequenceBasic()['current']['stat']['size']),
    'vfs open filecontrol locking open filecontrol sequence chunk op parsed' => static fn (TestRunner $t) => $t->same('chunk_size', $vfsOpenSequenceBasic()['events'][0]['op']),
    'vfs open filecontrol locking open filecontrol sequence chunk changed' => static fn (TestRunner $t) => $t->same(1, $vfsOpenSequenceBasic()['events'][0]['result']['file_control']['changed']),
    'vfs open filecontrol locking open filecontrol sequence chunk next state' => static fn (TestRunner $t) => $t->same(4096, $vfsOpenSequenceBasic()['events'][0]['next']['controls']['chunk_size']),
    'vfs open filecontrol locking open filecontrol sequence size hint op parsed' => static fn (TestRunner $t) => $t->same('size_hint', $vfsOpenSequenceBasic()['events'][1]['op']),
    'vfs open filecontrol locking open filecontrol sequence size hint preallocates' => static fn (TestRunner $t) => $t->same('preallocated', $vfsOpenSequenceBasic()['events'][1]['result']['preallocations'][0]['status']),
    'vfs open filecontrol locking open filecontrol sequence size hint rounds to chunk' => static fn (TestRunner $t) => $t->same(8192, $vfsOpenSequenceBasic()['events'][1]['result']['preallocations'][0]['target_size']),
    'vfs open filecontrol locking open filecontrol sequence size hint bytes added' => static fn (TestRunner $t) => $t->same(8192, $vfsOpenSequenceBasic()['events'][1]['result']['bytes_preallocated']),
    'vfs open filecontrol locking open filecontrol sequence stat reflects preallocation' => static fn (TestRunner $t) => $t->same(8192, $vfsOpenSequenceBasic()['events'][1]['next']['stat']['size']),
    'vfs open filecontrol locking open filecontrol sequence shared lock acquired' => static fn (TestRunner $t) => $t->same('acquired', $vfsOpenSequenceBasic()['events'][2]['result']['status']),
    'vfs open filecontrol locking open filecontrol sequence shared holder recorded' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $vfsOpenSequenceBasic()['events'][2]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence shared byte offset' => static fn (TestRunner $t) => $t->same(1073741833, $vfsOpenSequenceBasic()['events'][2]['plan']['ranges'][0]['offset']),
    'vfs open filecontrol locking open filecontrol sequence reserved lock coexists with reader' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'reserved'], $vfsOpenSequenceBasic()['events'][3]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence reserved byte planned' => static fn (TestRunner $t) => $t->same('reserved', $vfsOpenSequenceBasic()['events'][3]['plan']['ranges'][1]['name']),
    'vfs open filecontrol locking open filecontrol sequence pending lock upgrades writer' => static fn (TestRunner $t) => $t->same('pending', $vfsOpenSequenceBasic()['events'][4]['result']['held']),
    'vfs open filecontrol locking open filecontrol sequence pending keeps reader until release' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'pending'], $vfsOpenSequenceBasic()['events'][4]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence reader release updates holders' => static fn (TestRunner $t) => $t->same(['wp-import' => 'pending'], $vfsOpenSequenceBasic()['events'][5]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence exclusive lock acquired after readers drain' => static fn (TestRunner $t) => $t->same('exclusive', $vfsOpenSequenceBasic()['events'][6]['result']['held']),
    'vfs open filecontrol locking open filecontrol sequence exclusive holder recorded' => static fn (TestRunner $t) => $t->same(['wp-import' => 'exclusive'], $vfsOpenSequenceBasic()['events'][6]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence exclusive range covers shared bytes' => static fn (TestRunner $t) => $t->same(510, $vfsOpenSequenceBasic()['events'][6]['plan']['ranges'][2]['length']),
    'vfs open filecontrol locking open filecontrol sequence mmap pragma parsed' => static fn (TestRunner $t) => $t->same('mmap_size', $vfsOpenSequenceBasic()['events'][7]['op']),
    'vfs open filecontrol locking open filecontrol sequence mmap changed' => static fn (TestRunner $t) => $t->same(8192, $vfsOpenSequenceBasic()['events'][7]['next']['controls']['mmap_size']),
    'vfs open filecontrol locking open filecontrol sequence persist wal parsed boolean' => static fn (TestRunner $t) => $t->same(true, $vfsOpenSequenceBasic()['events'][8]['result']['file_control']['results'][0]['value']),
    'vfs open filecontrol locking open filecontrol sequence persist wal next state' => static fn (TestRunner $t) => $t->same(true, $vfsOpenSequenceBasic()['events'][8]['next']['controls']['persist_wal']),
    'vfs open filecontrol locking open filecontrol sequence unlock releases writer' => static fn (TestRunner $t) => $t->same([], $vfsOpenSequenceBasic()['events'][9]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence final file size remains preallocated' => static fn (TestRunner $t) => $t->same(8192, $vfsOpenSequenceBasic()['next']['stat']['size']),
    'vfs open filecontrol locking open filecontrol sequence final controls preserve chunk' => static fn (TestRunner $t) => $t->same(4096, $vfsOpenSequenceBasic()['next']['controls']['chunk_size']),
    'vfs open filecontrol locking open filecontrol sequence final controls preserve mmap' => static fn (TestRunner $t) => $t->same(8192, $vfsOpenSequenceBasic()['next']['controls']['mmap_size']),
    'vfs open filecontrol locking open filecontrol sequence dependencies include slice' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-file-control-locking-sequence', $vfsOpenSequenceBasic()['dependencies'], true)),
    'vfs open filecontrol locking open filecontrol sequence dependencies include handle' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-file-handle-primitive', $vfsOpenSequenceBasic()['dependencies'], true)),
    'vfs open filecontrol locking open filecontrol sequence dependencies include lock state' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-lock-state-application', $vfsOpenSequenceBasic()['events'][2]['result']['dependencies'], true)),
    'vfs open filecontrol locking open filecontrol sequence dependencies include preallocation' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-size-hint-preallocation', $vfsOpenSequenceBasic()['events'][1]['result']['dependencies'], true)),
    'vfs open filecontrol locking open filecontrol sequence blocked reader status' => static fn (TestRunner $t) => $t->same('blocked', $vfsOpenSequenceBlocked()['events'][2]['result']['status']),
    'vfs open filecontrol locking open filecontrol sequence blocked reader reason' => static fn (TestRunner $t) => $t->same('pending_or_exclusive_lock_blocks_new_reader', $vfsOpenSequenceBlocked()['events'][2]['result']['reason']),
    'vfs open filecontrol locking open filecontrol sequence blocked reader preserves holders' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'pending'], $vfsOpenSequenceBlocked()['events'][2]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence readonly chunk ignored' => static fn (TestRunner $t) => $t->same('ignored', $vfsOpenSequenceReadonly()['events'][0]['result']['file_control']['results'][0]['status']),
    'vfs open filecontrol locking open filecontrol sequence readonly size hint ignored' => static fn (TestRunner $t) => $t->same('size_hint_requires_writable_file_handle', $vfsOpenSequenceReadonly()['events'][1]['result']['file_control']['results'][0]['reason']),
    'vfs open filecontrol locking open filecontrol sequence readonly does not preallocate' => static fn (TestRunner $t) => $t->same(0, $vfsOpenSequenceReadonly()['events'][1]['result']['bytes_preallocated']),
    'vfs open filecontrol locking open filecontrol sequence readonly mmap allowed' => static fn (TestRunner $t) => $t->same(16384, $vfsOpenSequenceReadonly()['events'][2]['next']['controls']['mmap_size']),
    'vfs open filecontrol locking open filecontrol sequence nolock lock blocked by open option' => static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $vfsOpenSequenceNolock()['events'][0]['result']['reason']),
    'vfs open filecontrol locking open filecontrol sequence nolock busy timeout ignored' => static fn (TestRunner $t) => $t->same('lock_timeout_requires_lockable_file', $vfsOpenSequenceNolock()['events'][1]['result']['file_control']['results'][0]['reason']),
    'vfs open filecontrol locking open filecontrol sequence nolock mmap forced zero' => static fn (TestRunner $t) => $t->same(0, $vfsOpenSequenceNolock()['events'][2]['next']['controls']['mmap_size']),
    'vfs open filecontrol locking open filecontrol sequence array op chunk applies' => static fn (TestRunner $t) => $t->same(2048, $vfsOpenSequenceArrayOps()['events'][0]['next']['controls']['chunk_size']),
    'vfs open filecontrol locking open filecontrol sequence array op size rounds' => static fn (TestRunner $t) => $t->same(4096, $vfsOpenSequenceArrayOps()['events'][1]['result']['preallocations'][0]['target_size']),
    'vfs open filecontrol locking open filecontrol sequence array lock applies shared index' => static fn (TestRunner $t) => $t->same(1073741828, $vfsOpenSequenceArrayOps()['events'][2]['plan']['ranges'][0]['offset']),
    'vfs open filecontrol locking open filecontrol sequence array unlock clears holder' => static fn (TestRunner $t) => $t->same([], $vfsOpenSequenceArrayOps()['events'][3]['next']['holders']),
    'vfs open filecontrol locking open filecontrol sequence rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenFileControl::openFileControlSequence([])),
    'vfs open filecontrol locking open filecontrol sequence rejects empty operation string' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpenSequenceRun([''])),
    'vfs open filecontrol locking open filecontrol sequence rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpenSequenceRun(['vacuum'])),
    'vfs open filecontrol locking open filecontrol sequence rejects missing lock connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpenSequenceRun([['kind' => 'lock', 'level' => 'shared']])),
    'vfs open filecontrol locking open filecontrol sequence rejects empty root option' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenFileControl::openFileControlSequence(['pragma mmap_size=1'], ['root' => ''])),
    'vfs open filecontrol locking open filecontrol sequence rejects bad shared index' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpenSequenceRun([['kind' => 'lock', 'level' => 'shared', 'connection' => 'wp', 'shared_index' => 'bad']])),
];
