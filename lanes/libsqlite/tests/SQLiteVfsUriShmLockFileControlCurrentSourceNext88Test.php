<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmLockFileControlCurrentSource;

$tests = [];

$run88 = static fn (array $ops, array $options = []): array => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl(
    $ops,
    $options + ['filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared'],
);

$decoded = static function () use ($run88): array {
    static $result = null;
    if ($result === null) {
        $first = $run88([
            'open',
            'shm_lock(write, exclusive)',
            'file_control(persist_wal, on)',
            'file_control(chunk_size, 16384)',
            'file_control(name_hint, "wp copy")',
            'release',
            'close',
        ]);
        $result = $run88([
            'open',
            'shm_lock(read, shared)',
            'file_control(mmap_size, 262144)',
        ], [
            'filename' => 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private',
            'current' => $first['events'][6]['next'],
        ]);
    }

    return $result;
};

$readonly = static fn (): array => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([
    'open',
    'shm_lock(read, shared)',
    'file_control(mmap_size, 65536)',
    'file_control(chunk_size, 4096)',
], [
    'filename' => 'file:/srv/www/wp-content/database/archive%20copy.sqlite?mode=ro&cache=shared',
]);

$immutable = static fn (): array => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([
    'open',
    'shm_lock(read, shared)',
    'file_control(mmap_size, 1)',
], [
    'filename' => 'file://localhost/srv/www/wp-content/database/archive%20copy.sqlite?mode=rw&immutable=1',
]);

$nolock = static fn (): array => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([
    'open',
    'shm_lock(read, shared)',
], [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&nolock=1',
]);

$memoryOne = static fn (): array => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([
    'open',
    'shm_lock(read, shared)',
], [
    'filename' => 'file::memory:?mode=memory&cache=shared',
]);

$stale = static function () use ($run88): array {
    static $result = null;
    if ($result === null) {
        $result = $run88([
            'open',
            'open',
            ['op' => 'shmlock', 'handle' => 'shm-2', 'lock' => 'write', 'exclusive' => true],
            ['op' => 'filecontrol', 'handle' => 'shm-2', 'control' => 'reserve_bytes', 'value' => 32],
            ['op' => 'release', 'handle' => 'shm-2'],
            ['op' => 'shmlock', 'handle' => 'shm-1', 'lock' => 'write', 'exclusive' => true],
            ['op' => 'filecontrol', 'handle' => 'shm-1', 'control' => 'chunk_size', 'value' => 8192],
        ]);
    }

    return $result;
};

$conflict = static fn () => $run88([
    'open',
    'shm_lock(read, shared)',
    'open',
    'shm_lock(read, shared)',
    ['op' => 'shmlock', 'handle' => 'shm-2', 'lock' => 'write', 'exclusive' => true],
    ['op' => 'release', 'handle' => 'shm-1'],
    ['op' => 'shmlock', 'handle' => 'shm-2', 'lock' => 'write', 'exclusive' => true],
]);

$tests['vfs uri shm lock filecontrol current source next88 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-shm-lock-filecontrol-current-source-next88', $decoded()['dependencies'], true));
$tests['vfs uri shm lock filecontrol current source next88 decoded status'] = static fn (TestRunner $t) => $t->same('ok', $decoded()['status']);
$tests['vfs uri shm lock filecontrol current source next88 localhost path decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $decoded()['events'][0]['path']);
$tests['vfs uri shm lock filecontrol current source next88 localhost source key decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $decoded()['events'][0]['source_key']);
$tests['vfs uri shm lock filecontrol current source next88 localhost authority captured'] = static fn (TestRunner $t) => $t->same('localhost', $decoded()['events'][0]['uri']['authority']);
$tests['vfs uri shm lock filecontrol current source next88 cache private retained'] = static fn (TestRunner $t) => $t->same('private', $decoded()['events'][0]['uri']['cache']);
$tests['vfs uri shm lock filecontrol current source next88 reused controls from decoded source'] = static fn (TestRunner $t) => $t->same(true, $decoded()['events'][0]['reused_controls']);
$tests['vfs uri shm lock filecontrol current source next88 reopen generation current'] = static fn (TestRunner $t) => $t->same(3, $decoded()['events'][0]['source_generation']);
$tests['vfs uri shm lock filecontrol current source next88 reopened persist wal'] = static fn (TestRunner $t) => $t->same(true, $decoded()['events'][0]['next']['handles']['shm-2']['controls']['persist_wal']);
$tests['vfs uri shm lock filecontrol current source next88 reopened chunk'] = static fn (TestRunner $t) => $t->same(16384, $decoded()['events'][0]['next']['handles']['shm-2']['controls']['chunk_size']);
$tests['vfs uri shm lock filecontrol current source next88 name hint retained'] = static fn (TestRunner $t) => $t->same('wp copy', $decoded()['events'][0]['next']['handles']['shm-2']['controls']['name_hint']);
$tests['vfs uri shm lock filecontrol current source next88 read lock acquired after localhost reopen'] = static fn (TestRunner $t) => $t->same('acquired', $decoded()['events'][1]['status']);
$tests['vfs uri shm lock filecontrol current source next88 mmap read control ok'] = static fn (TestRunner $t) => $t->same('ok', $decoded()['events'][2]['status']);
$tests['vfs uri shm lock filecontrol current source next88 mmap value applied'] = static fn (TestRunner $t) => $t->same(262144, $decoded()['next']['controls']['mmap_size']);
$tests['vfs uri shm lock filecontrol current source next88 write controls preserved'] = static fn (TestRunner $t) => $t->same(16384, $decoded()['next']['controls']['chunk_size']);
$tests['vfs uri shm lock filecontrol current source next88 read control does not advance generation'] = static fn (TestRunner $t) => $t->same(3, $decoded()['next']['generation']);
$tests['vfs uri shm lock filecontrol current source next88 locked count one'] = static fn (TestRunner $t) => $t->same(1, $decoded()['next']['locked_count']);
$tests['vfs uri shm lock filecontrol current source next88 source uri is uri'] = static fn (TestRunner $t) => $t->same(true, $decoded()['events'][0]['next']['sources']['/srv/www/wp-content/database/wp copy.sqlite']['uri']['is_uri']);

$tests['vfs uri shm lock filecontrol current source next88 readonly path decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/archive copy.sqlite', $readonly()['events'][0]['path']);
$tests['vfs uri shm lock filecontrol current source next88 readonly handle flag'] = static fn (TestRunner $t) => $t->same(true, $readonly()['events'][0]['next']['handles']['shm-1']['readonly']);
$tests['vfs uri shm lock filecontrol current source next88 readonly read lock ok'] = static fn (TestRunner $t) => $t->same('acquired', $readonly()['events'][1]['status']);
$tests['vfs uri shm lock filecontrol current source next88 readonly mmap ok'] = static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][2]['status']);
$tests['vfs uri shm lock filecontrol current source next88 readonly mmap value'] = static fn (TestRunner $t) => $t->same(65536, $readonly()['events'][2]['next']['sources']['/srv/www/wp-content/database/archive copy.sqlite']['controls']['mmap_size']);
$tests['vfs uri shm lock filecontrol current source next88 readonly chunk ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][3]['status']);
$tests['vfs uri shm lock filecontrol current source next88 readonly chunk reason'] = static fn (TestRunner $t) => $t->same('readonly_handle', $readonly()['events'][3]['reason']);
$tests['vfs uri shm lock filecontrol current source next88 readonly generation unchanged'] = static fn (TestRunner $t) => $t->same(1, $readonly()['next']['generation']);

$tests['vfs uri shm lock filecontrol current source next88 immutable parsed'] = static fn (TestRunner $t) => $t->same(true, $immutable()['events'][0]['uri']['immutable']);
$tests['vfs uri shm lock filecontrol current source next88 immutable handle readonly'] = static fn (TestRunner $t) => $t->same(true, $immutable()['events'][0]['next']['handles']['shm-1']['readonly']);
$tests['vfs uri shm lock filecontrol current source next88 immutable lock blocked'] = static fn (TestRunner $t) => $t->same('blocked', $immutable()['events'][1]['status']);
$tests['vfs uri shm lock filecontrol current source next88 immutable lock reason'] = static fn (TestRunner $t) => $t->same('immutable_uri_disables_shm_locking', $immutable()['events'][1]['reason']);
$tests['vfs uri shm lock filecontrol current source next88 immutable filecontrol blocked by missing read lock'] = static fn (TestRunner $t) => $t->same('blocked', $immutable()['events'][2]['status']);
$tests['vfs uri shm lock filecontrol current source next88 immutable filecontrol reason'] = static fn (TestRunner $t) => $t->same('requires_shm_read_lock', $immutable()['events'][2]['reason']);

$tests['vfs uri shm lock filecontrol current source next88 nolock parsed'] = static fn (TestRunner $t) => $t->same(true, $nolock()['events'][0]['uri']['nolock']);
$tests['vfs uri shm lock filecontrol current source next88 nolock handle flag'] = static fn (TestRunner $t) => $t->same(true, $nolock()['events'][0]['next']['handles']['shm-1']['nolock']);
$tests['vfs uri shm lock filecontrol current source next88 nolock lock blocked'] = static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']);
$tests['vfs uri shm lock filecontrol current source next88 nolock reason'] = static fn (TestRunner $t) => $t->same('nolock_uri_disables_shm_locking', $nolock()['events'][1]['reason']);

$tests['vfs uri shm lock filecontrol current source next88 memory open path empty'] = static fn (TestRunner $t) => $t->same('', $memoryOne()['events'][0]['path']);
$tests['vfs uri shm lock filecontrol current source next88 memory source key unique'] = static fn (TestRunner $t) => $t->same('memory:shm-1', $memoryOne()['events'][0]['source_key']);
$tests['vfs uri shm lock filecontrol current source next88 memory source nonpersistent'] = static fn (TestRunner $t) => $t->same(false, $memoryOne()['events'][0]['next']['sources']['memory:shm-1']['persistent']);
$tests['vfs uri shm lock filecontrol current source next88 memory lock blocked'] = static fn (TestRunner $t) => $t->same('blocked', $memoryOne()['events'][1]['status']);
$tests['vfs uri shm lock filecontrol current source next88 memory lock reason'] = static fn (TestRunner $t) => $t->same('memory_uri_has_private_shm', $memoryOne()['events'][1]['reason']);
$tests['vfs uri shm lock filecontrol current source next88 memory locked count zero'] = static fn (TestRunner $t) => $t->same(0, $memoryOne()['next']['locked_count']);

$tests['vfs uri shm lock filecontrol current source next88 stale writer generation advance'] = static fn (TestRunner $t) => $t->same(2, $stale()['events'][3]['next']['sources']['/srv/www/wp-content/database/wp copy.sqlite']['generation']);
$tests['vfs uri shm lock filecontrol current source next88 stale old handle blocked'] = static fn (TestRunner $t) => $t->same('blocked', $stale()['events'][6]['status']);
$tests['vfs uri shm lock filecontrol current source next88 stale old handle reason'] = static fn (TestRunner $t) => $t->same('stale_current_source', $stale()['events'][6]['reason']);
$tests['vfs uri shm lock filecontrol current source next88 stale chunk absent'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('chunk_size', $stale()['next']['controls']));

$tests['vfs uri shm lock filecontrol current source next88 shared readers coexist'] = static fn (TestRunner $t) => $t->same('acquired', $conflict()['events'][3]['status']);
$tests['vfs uri shm lock filecontrol current source next88 writer blocked by readers'] = static fn (TestRunner $t) => $t->same('blocked', $conflict()['events'][4]['status']);
$tests['vfs uri shm lock filecontrol current source next88 writer blocker list'] = static fn (TestRunner $t) => $t->same(['shm-1:read:shared'], $conflict()['events'][4]['blocking']);
$tests['vfs uri shm lock filecontrol current source next88 writer succeeds after first release'] = static fn (TestRunner $t) => $t->same('acquired', $conflict()['events'][6]['status']);
$tests['vfs uri shm lock filecontrol current source next88 writer final locked count'] = static fn (TestRunner $t) => $t->same(2, $conflict()['next']['locked_count']);

$tests['vfs uri shm lock filecontrol current source next88 rejects remote authority'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl(['open'], ['filename' => 'file://example.com/srv/db.sqlite?mode=rw']));
$tests['vfs uri shm lock filecontrol current source next88 rejects bad percent'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl(['open'], ['filename' => 'file:/srv/db%2.sqlite?mode=rw']));
$tests['vfs uri shm lock filecontrol current source next88 rejects bad immutable boolean'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl(['open'], ['filename' => 'file:/srv/db.sqlite?immutable=yes']));
$tests['vfs uri shm lock filecontrol current source next88 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([]));

return $tests;
