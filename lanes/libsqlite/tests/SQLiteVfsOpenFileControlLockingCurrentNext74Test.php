<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenFileControl;

$vfsOpen74Root = static fn (): string => sys_get_temp_dir() . '/port-libsqlite-vfs-open-filecontrol-74-test-' . bin2hex(random_bytes(4));
$vfsOpen74Run = static fn (array $ops, array $options = []): array => SQLiteVfsOpenFileControl::currentNext74(
    $ops,
    $options + [
        'root' => $vfsOpen74Root(),
        'filename' => '/srv/www/wp-content/database/.ht.sqlite',
        'file_exists' => true,
        'directory_writable' => true,
        'sector_size' => 4096,
        'device_flags' => ['safe_append', 'powersafe_overwrite'],
        'sync_mode' => 'full',
    ],
);
$vfsOpen74Basic = static fn (): array => $vfsOpen74Run([
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
$vfsOpen74Blocked = static fn (): array => $vfsOpen74Run([
    'lock shared by wp-reader 3',
    'lock pending by wp-import',
    'lock shared by wp-rest 4',
]);
$vfsOpen74Readonly = static fn (): array => $vfsOpen74Run([
    'pragma chunk_size=4096',
    'file_control(size_hint, 8192)',
    'pragma mmap_size=16384',
], ['filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=ro', 'file_exists' => true]);
$vfsOpen74Nolock = static fn (): array => $vfsOpen74Run([
    'lock shared by repair-copy 1',
    'pragma busy_timeout=250',
    'pragma mmap_size=4096',
], ['filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?nolock=1']);
$vfsOpen74ArrayOps = static fn (): array => $vfsOpen74Run([
    ['op' => 'chunk_size', 'value' => 2048],
    ['op' => 'size_hint', 'value' => 2049],
    ['kind' => 'lock', 'level' => 'shared', 'connection' => 'wp-cli', 'shared_index' => 2],
    ['kind' => 'unlock', 'connection' => 'wp-cli'],
]);

return [
    'vfs open filecontrol locking current next74 count' => static fn (TestRunner $t) => $t->same(10, $vfsOpen74Basic()['count']),
    'vfs open filecontrol locking current next74 final status' => static fn (TestRunner $t) => $t->same('released', $vfsOpen74Basic()['status']),
    'vfs open filecontrol locking current next74 starts without holders' => static fn (TestRunner $t) => $t->same([], $vfsOpen74Basic()['current']['holders']),
    'vfs open filecontrol locking current next74 starts at zero file size' => static fn (TestRunner $t) => $t->same(0, $vfsOpen74Basic()['current']['stat']['size']),
    'vfs open filecontrol locking current next74 chunk op parsed' => static fn (TestRunner $t) => $t->same('chunk_size', $vfsOpen74Basic()['events'][0]['op']),
    'vfs open filecontrol locking current next74 chunk changed' => static fn (TestRunner $t) => $t->same(1, $vfsOpen74Basic()['events'][0]['result']['file_control']['changed']),
    'vfs open filecontrol locking current next74 chunk next state' => static fn (TestRunner $t) => $t->same(4096, $vfsOpen74Basic()['events'][0]['next']['controls']['chunk_size']),
    'vfs open filecontrol locking current next74 size hint op parsed' => static fn (TestRunner $t) => $t->same('size_hint', $vfsOpen74Basic()['events'][1]['op']),
    'vfs open filecontrol locking current next74 size hint preallocates' => static fn (TestRunner $t) => $t->same('preallocated', $vfsOpen74Basic()['events'][1]['result']['preallocations'][0]['status']),
    'vfs open filecontrol locking current next74 size hint rounds to chunk' => static fn (TestRunner $t) => $t->same(8192, $vfsOpen74Basic()['events'][1]['result']['preallocations'][0]['target_size']),
    'vfs open filecontrol locking current next74 size hint bytes added' => static fn (TestRunner $t) => $t->same(8192, $vfsOpen74Basic()['events'][1]['result']['bytes_preallocated']),
    'vfs open filecontrol locking current next74 stat reflects preallocation' => static fn (TestRunner $t) => $t->same(8192, $vfsOpen74Basic()['events'][1]['next']['stat']['size']),
    'vfs open filecontrol locking current next74 shared lock acquired' => static fn (TestRunner $t) => $t->same('acquired', $vfsOpen74Basic()['events'][2]['result']['status']),
    'vfs open filecontrol locking current next74 shared holder recorded' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $vfsOpen74Basic()['events'][2]['next']['holders']),
    'vfs open filecontrol locking current next74 shared byte offset' => static fn (TestRunner $t) => $t->same(1073741833, $vfsOpen74Basic()['events'][2]['plan']['ranges'][0]['offset']),
    'vfs open filecontrol locking current next74 reserved lock coexists with reader' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'reserved'], $vfsOpen74Basic()['events'][3]['next']['holders']),
    'vfs open filecontrol locking current next74 reserved byte planned' => static fn (TestRunner $t) => $t->same('reserved', $vfsOpen74Basic()['events'][3]['plan']['ranges'][1]['name']),
    'vfs open filecontrol locking current next74 pending lock upgrades writer' => static fn (TestRunner $t) => $t->same('pending', $vfsOpen74Basic()['events'][4]['result']['held']),
    'vfs open filecontrol locking current next74 pending keeps reader until release' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'pending'], $vfsOpen74Basic()['events'][4]['next']['holders']),
    'vfs open filecontrol locking current next74 reader release updates holders' => static fn (TestRunner $t) => $t->same(['wp-import' => 'pending'], $vfsOpen74Basic()['events'][5]['next']['holders']),
    'vfs open filecontrol locking current next74 exclusive lock acquired after readers drain' => static fn (TestRunner $t) => $t->same('exclusive', $vfsOpen74Basic()['events'][6]['result']['held']),
    'vfs open filecontrol locking current next74 exclusive holder recorded' => static fn (TestRunner $t) => $t->same(['wp-import' => 'exclusive'], $vfsOpen74Basic()['events'][6]['next']['holders']),
    'vfs open filecontrol locking current next74 exclusive range covers shared bytes' => static fn (TestRunner $t) => $t->same(510, $vfsOpen74Basic()['events'][6]['plan']['ranges'][2]['length']),
    'vfs open filecontrol locking current next74 mmap pragma parsed' => static fn (TestRunner $t) => $t->same('mmap_size', $vfsOpen74Basic()['events'][7]['op']),
    'vfs open filecontrol locking current next74 mmap changed' => static fn (TestRunner $t) => $t->same(8192, $vfsOpen74Basic()['events'][7]['next']['controls']['mmap_size']),
    'vfs open filecontrol locking current next74 persist wal parsed boolean' => static fn (TestRunner $t) => $t->same(true, $vfsOpen74Basic()['events'][8]['result']['file_control']['results'][0]['value']),
    'vfs open filecontrol locking current next74 persist wal next state' => static fn (TestRunner $t) => $t->same(true, $vfsOpen74Basic()['events'][8]['next']['controls']['persist_wal']),
    'vfs open filecontrol locking current next74 unlock releases writer' => static fn (TestRunner $t) => $t->same([], $vfsOpen74Basic()['events'][9]['next']['holders']),
    'vfs open filecontrol locking current next74 final file size remains preallocated' => static fn (TestRunner $t) => $t->same(8192, $vfsOpen74Basic()['next']['stat']['size']),
    'vfs open filecontrol locking current next74 final controls preserve chunk' => static fn (TestRunner $t) => $t->same(4096, $vfsOpen74Basic()['next']['controls']['chunk_size']),
    'vfs open filecontrol locking current next74 final controls preserve mmap' => static fn (TestRunner $t) => $t->same(8192, $vfsOpen74Basic()['next']['controls']['mmap_size']),
    'vfs open filecontrol locking current next74 dependencies include slice' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-file-control-locking-current-next74', $vfsOpen74Basic()['dependencies'], true)),
    'vfs open filecontrol locking current next74 dependencies include handle' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-file-handle-primitive', $vfsOpen74Basic()['dependencies'], true)),
    'vfs open filecontrol locking current next74 dependencies include lock state' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-lock-state-application', $vfsOpen74Basic()['events'][2]['result']['dependencies'], true)),
    'vfs open filecontrol locking current next74 dependencies include preallocation' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-size-hint-preallocation', $vfsOpen74Basic()['events'][1]['result']['dependencies'], true)),
    'vfs open filecontrol locking current next74 blocked reader status' => static fn (TestRunner $t) => $t->same('blocked', $vfsOpen74Blocked()['events'][2]['result']['status']),
    'vfs open filecontrol locking current next74 blocked reader reason' => static fn (TestRunner $t) => $t->same('pending_or_exclusive_lock_blocks_new_reader', $vfsOpen74Blocked()['events'][2]['result']['reason']),
    'vfs open filecontrol locking current next74 blocked reader preserves holders' => static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared', 'wp-import' => 'pending'], $vfsOpen74Blocked()['events'][2]['next']['holders']),
    'vfs open filecontrol locking current next74 readonly chunk ignored' => static fn (TestRunner $t) => $t->same('ignored', $vfsOpen74Readonly()['events'][0]['result']['file_control']['results'][0]['status']),
    'vfs open filecontrol locking current next74 readonly size hint ignored' => static fn (TestRunner $t) => $t->same('size_hint_requires_writable_file_handle', $vfsOpen74Readonly()['events'][1]['result']['file_control']['results'][0]['reason']),
    'vfs open filecontrol locking current next74 readonly does not preallocate' => static fn (TestRunner $t) => $t->same(0, $vfsOpen74Readonly()['events'][1]['result']['bytes_preallocated']),
    'vfs open filecontrol locking current next74 readonly mmap allowed' => static fn (TestRunner $t) => $t->same(16384, $vfsOpen74Readonly()['events'][2]['next']['controls']['mmap_size']),
    'vfs open filecontrol locking current next74 nolock lock blocked by open option' => static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $vfsOpen74Nolock()['events'][0]['result']['reason']),
    'vfs open filecontrol locking current next74 nolock busy timeout ignored' => static fn (TestRunner $t) => $t->same('lock_timeout_requires_lockable_file', $vfsOpen74Nolock()['events'][1]['result']['file_control']['results'][0]['reason']),
    'vfs open filecontrol locking current next74 nolock mmap forced zero' => static fn (TestRunner $t) => $t->same(0, $vfsOpen74Nolock()['events'][2]['next']['controls']['mmap_size']),
    'vfs open filecontrol locking current next74 array op chunk applies' => static fn (TestRunner $t) => $t->same(2048, $vfsOpen74ArrayOps()['events'][0]['next']['controls']['chunk_size']),
    'vfs open filecontrol locking current next74 array op size rounds' => static fn (TestRunner $t) => $t->same(4096, $vfsOpen74ArrayOps()['events'][1]['result']['preallocations'][0]['target_size']),
    'vfs open filecontrol locking current next74 array lock applies shared index' => static fn (TestRunner $t) => $t->same(1073741828, $vfsOpen74ArrayOps()['events'][2]['plan']['ranges'][0]['offset']),
    'vfs open filecontrol locking current next74 array unlock clears holder' => static fn (TestRunner $t) => $t->same([], $vfsOpen74ArrayOps()['events'][3]['next']['holders']),
    'vfs open filecontrol locking current next74 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenFileControl::currentNext74([])),
    'vfs open filecontrol locking current next74 rejects empty operation string' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpen74Run([''])),
    'vfs open filecontrol locking current next74 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpen74Run(['vacuum'])),
    'vfs open filecontrol locking current next74 rejects missing lock connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpen74Run([['kind' => 'lock', 'level' => 'shared']])),
    'vfs open filecontrol locking current next74 rejects empty root option' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenFileControl::currentNext74(['pragma mmap_size=1'], ['root' => ''])),
    'vfs open filecontrol locking current next74 rejects bad shared index' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $vfsOpen74Run([['kind' => 'lock', 'level' => 'shared', 'connection' => 'wp', 'shared_index' => 'bad']])),
];
