<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmLockFileControlCurrentSource;

$tests = [];

$run85 = static fn (array $ops, array $options = []): array => SQLiteVfsShmLockFileControlCurrentSource::planShmLockFileControl(
    $ops,
    $options + ['filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared'],
);

$writer = static function () use ($run85): array {
    static $result = null;
    if ($result === null) {
        $result = $run85([
            'open',
            'shm_lock(write, exclusive)',
            'file_control(persist_wal, on)',
            'file_control(chunk_size, 8192)',
            'file_control(mmap_size, 65536)',
            'release',
            'close',
            'open',
        ]);
    }

    return $result;
};

$readerBlocked = static function () use ($run85): array {
    static $result = null;
    if ($result === null) {
        $result = $run85([
            ['op' => 'open', 'readonly' => true],
            'shm_lock(read, shared)',
            'file_control(mmap_size, 32768)',
            'file_control(chunk_size, 4096)',
            'release',
        ]);
    }

    return $result;
};

$stale = static function () use ($run85): array {
    static $result = null;
    if ($result === null) {
        $result = $run85([
            'open',
            ['op' => 'open', 'handle' => null],
            ['op' => 'shm_lock', 'handle' => 'shm-2', 'lock' => 'write', 'exclusive' => true],
            ['op' => 'filecontrol', 'handle' => 'shm-2', 'control' => 'data_version', 'value' => 2],
            ['op' => 'release', 'handle' => 'shm-2'],
            ['op' => 'shm_lock', 'handle' => 'shm-1', 'lock' => 'write', 'exclusive' => true],
            ['op' => 'filecontrol', 'handle' => 'shm-1', 'control' => 'chunk_size', 'value' => 16384],
        ]);
    }

    return $result;
};

$conflict = static function () use ($run85): array {
    static $result = null;
    if ($result === null) {
        $result = $run85([
            'open',
            'shm_lock(read, shared)',
            'open',
            'shm_lock(write, exclusive)',
            ['op' => 'release', 'handle' => 'shm-1'],
            'shm_lock(write, exclusive)',
        ]);
    }

    return $result;
};

$checkpoint = static function () use ($run85): array {
    static $result = null;
    if ($result === null) {
        $result = $run85([
            'open',
            'shm_lock(checkpoint, exclusive)',
            'file_control(reserve_bytes, 24)',
            'file_control(powersafe_overwrite, off)',
            'release',
        ]);
    }

    return $result;
};

$tests['vfs shm lock filecontrol current source next85 writer status'] = static fn (TestRunner $t) => $t->same('open', $writer()['status']);
$tests['vfs shm lock filecontrol current source next85 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-lock-filecontrol-current-source-next85', $writer()['dependencies'], true));
$tests['vfs shm lock filecontrol current source next85 writer open path'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite', $writer()['events'][0]['path']);
$tests['vfs shm lock filecontrol current source next85 writer starts generation one'] = static fn (TestRunner $t) => $t->same(1, $writer()['events'][0]['source_generation']);
$tests['vfs shm lock filecontrol current source next85 writer lock acquired'] = static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][1]['status']);
$tests['vfs shm lock filecontrol current source next85 writer lock exclusive'] = static fn (TestRunner $t) => $t->same(true, $writer()['events'][1]['exclusive']);
$tests['vfs shm lock filecontrol current source next85 persist wal ok'] = static fn (TestRunner $t) => $t->same('ok', $writer()['events'][2]['status']);
$tests['vfs shm lock filecontrol current source next85 persist wal changes generation'] = static fn (TestRunner $t) => $t->same(2, $writer()['events'][2]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['generation']);
$tests['vfs shm lock filecontrol current source next85 persist wal true'] = static fn (TestRunner $t) => $t->same(true, $writer()['events'][2]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['controls']['persist_wal']);
$tests['vfs shm lock filecontrol current source next85 chunk ok'] = static fn (TestRunner $t) => $t->same('ok', $writer()['events'][3]['status']);
$tests['vfs shm lock filecontrol current source next85 chunk increments generation'] = static fn (TestRunner $t) => $t->same(3, $writer()['events'][3]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['generation']);
$tests['vfs shm lock filecontrol current source next85 mmap ok under write lock'] = static fn (TestRunner $t) => $t->same('ok', $writer()['events'][4]['status']);
$tests['vfs shm lock filecontrol current source next85 mmap does not advance source'] = static fn (TestRunner $t) => $t->same(3, $writer()['events'][4]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['generation']);
$tests['vfs shm lock filecontrol current source next85 release clears lock count'] = static fn (TestRunner $t) => $t->same(0, array_sum(array_map('count', $writer()['events'][5]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['locks'])));
$tests['vfs shm lock filecontrol current source next85 close clears handles'] = static fn (TestRunner $t) => $t->same([], $writer()['events'][6]['next']['handles']);
$tests['vfs shm lock filecontrol current source next85 reopen reused controls'] = static fn (TestRunner $t) => $t->same(true, $writer()['events'][7]['reused_controls']);
$tests['vfs shm lock filecontrol current source next85 reopen generation current'] = static fn (TestRunner $t) => $t->same(3, $writer()['events'][7]['source_generation']);
$tests['vfs shm lock filecontrol current source next85 final generation'] = static fn (TestRunner $t) => $t->same(3, $writer()['next']['generation']);
$tests['vfs shm lock filecontrol current source next85 final persist wal'] = static fn (TestRunner $t) => $t->same(true, $writer()['next']['controls']['persist_wal']);
$tests['vfs shm lock filecontrol current source next85 final chunk'] = static fn (TestRunner $t) => $t->same(8192, $writer()['next']['controls']['chunk_size']);
$tests['vfs shm lock filecontrol current source next85 final mmap'] = static fn (TestRunner $t) => $t->same(65536, $writer()['next']['controls']['mmap_size']);

$tests['vfs shm lock filecontrol current source next85 readonly read lock acquired'] = static fn (TestRunner $t) => $t->same('acquired', $readerBlocked()['events'][1]['status']);
$tests['vfs shm lock filecontrol current source next85 readonly mmap allowed'] = static fn (TestRunner $t) => $t->same('ok', $readerBlocked()['events'][2]['status']);
$tests['vfs shm lock filecontrol current source next85 readonly mmap value'] = static fn (TestRunner $t) => $t->same(32768, $readerBlocked()['events'][2]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['controls']['mmap_size']);
$tests['vfs shm lock filecontrol current source next85 readonly chunk ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readerBlocked()['events'][3]['status']);
$tests['vfs shm lock filecontrol current source next85 readonly chunk reason'] = static fn (TestRunner $t) => $t->same('readonly_handle', $readerBlocked()['events'][3]['reason']);
$tests['vfs shm lock filecontrol current source next85 readonly generation unchanged'] = static fn (TestRunner $t) => $t->same(1, $readerBlocked()['next']['generation']);

$tests['vfs shm lock filecontrol current source next85 stale writer advances generation'] = static fn (TestRunner $t) => $t->same(2, $stale()['events'][3]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['generation']);
$tests['vfs shm lock filecontrol current source next85 stale old handle lock acquired'] = static fn (TestRunner $t) => $t->same('acquired', $stale()['events'][5]['status']);
$tests['vfs shm lock filecontrol current source next85 stale old handle blocked'] = static fn (TestRunner $t) => $t->same('blocked', $stale()['events'][6]['status']);
$tests['vfs shm lock filecontrol current source next85 stale old handle reason'] = static fn (TestRunner $t) => $t->same('stale_current_source', $stale()['events'][6]['reason']);
$tests['vfs shm lock filecontrol current source next85 stale did not change chunk'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('chunk_size', $stale()['next']['controls']));

$tests['vfs shm lock filecontrol current source next85 read lock blocks writer'] = static fn (TestRunner $t) => $t->same('blocked', $conflict()['events'][3]['status']);
$tests['vfs shm lock filecontrol current source next85 conflict names reader'] = static fn (TestRunner $t) => $t->same(['shm-1:read:shared'], $conflict()['events'][3]['blocking']);
$tests['vfs shm lock filecontrol current source next85 writer succeeds after release'] = static fn (TestRunner $t) => $t->same('acquired', $conflict()['events'][5]['status']);
$tests['vfs shm lock filecontrol current source next85 final writer lock held'] = static fn (TestRunner $t) => $t->same(1, $conflict()['next']['locked_count']);

$tests['vfs shm lock filecontrol current source next85 checkpoint lock can write reserve'] = static fn (TestRunner $t) => $t->same('ok', $checkpoint()['events'][2]['status']);
$tests['vfs shm lock filecontrol current source next85 checkpoint reserve value'] = static fn (TestRunner $t) => $t->same(24, $checkpoint()['events'][2]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['controls']['reserve_bytes']);
$tests['vfs shm lock filecontrol current source next85 checkpoint powersafe value'] = static fn (TestRunner $t) => $t->same(false, $checkpoint()['events'][3]['next']['sources']['/srv/www/wp-content/database/.ht.sqlite']['controls']['powersafe_overwrite']);
$tests['vfs shm lock filecontrol current source next85 checkpoint generation two writes'] = static fn (TestRunner $t) => $t->same(3, $checkpoint()['next']['generation']);

$tests['vfs shm lock filecontrol current source next85 blocks filecontrol without lock'] = static fn (TestRunner $t) => $t->same('blocked', $run85(['open', 'file_control(mmap_size, 1)'])['events'][1]['status']);
$tests['vfs shm lock filecontrol current source next85 no lock read reason'] = static fn (TestRunner $t) => $t->same('requires_shm_read_lock', $run85(['open', 'file_control(mmap_size, 1)'])['events'][1]['reason']);
$tests['vfs shm lock filecontrol current source next85 no lock write reason'] = static fn (TestRunner $t) => $t->same('requires_exclusive_shm_lock', $run85(['open', 'file_control(chunk_size, 1)'])['events'][1]['reason']);
$tests['vfs shm lock filecontrol current source next85 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmLockFileControlCurrentSource::planShmLockFileControl([]));
$tests['vfs shm lock filecontrol current source next85 rejects bad lock'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run85(['open', 'shm_lock(bogus)']));
$tests['vfs shm lock filecontrol current source next85 rejects bad chunk'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run85(['open', 'shm_lock(write, exclusive)', ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => -1]]));
$tests['vfs shm lock filecontrol current source next85 rejects bad name hint'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run85(['open', 'shm_lock(read)', ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => '']]));

return $tests;
