<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;

$tests = [];

$runVfs80 = static fn (array $ops, array $options = []): array => SQLiteVfsFileControlPersistencePlan::currentNext80(
    $ops,
    $options + [
        'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix',
        'file_exists' => true,
        'directory_writable' => true,
        'sector_size' => 4096,
        'device_flags' => ['safe_append', 'powersafe_overwrite'],
        'sync_mode' => 'full',
    ],
);

$basicVfs80 = static function () use ($runVfs80): array {
    static $result = null;
    if ($result === null) {
        $result = $runVfs80([
            'file_control(persist_wal, on)',
            'PRAGMA reserve_bytes=16',
            'PRAGMA mmap_size=65536',
            'PRAGMA chunk_size=8192',
            "file_control(name_hint, 'wp import')",
            ['op' => 'write_hint', 'value' => 24576],
            'close',
            'reopen',
            'PRAGMA mmap_size=32768',
            'file_control(persist_wal, off)',
            'close',
            'reopen',
        ]);
    }

    return $result;
};

$nonPersistentVfs80 = static function () use ($runVfs80): array {
    static $result = null;
    if ($result === null) {
        $result = $runVfs80([
            ['op' => 'chunk_size', 'value' => 4096],
            ['op' => 'mmap_size', 'value' => 32768],
            ['op' => 'write_hint', 'value' => 1024],
            ['op' => 'overwrite', 'value' => 4],
            ['op' => 'sync', 'value' => 'full'],
            ['kind' => 'close'],
            ['kind' => 'reopen'],
        ]);
    }

    return $result;
};

$closedVfs80 = static fn (): array => $runVfs80([
    'close',
    'PRAGMA reserve_bytes=8',
    'reopen',
    'PRAGMA reserve_bytes=8',
]);

$readonlyVfs80 = static fn (): array => $runVfs80([
    'file_control(persist_wal, on)',
    'PRAGMA reserve_bytes=8',
    'close',
    'reopen',
], ['filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1']);

$powerSafeVfs80 = static fn (): array => $runVfs80([
    'file_control(powersafe_overwrite, off)',
    'close',
    'reopen',
    'file_control(powersafe_overwrite, on)',
    'close',
    'reopen',
]);

$tests['vfs filecontrol persistence current next80 count'] = static fn (TestRunner $t) => $t->same(12, $basicVfs80()['count']);
$tests['vfs filecontrol persistence current next80 initial handle is open'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['current']['handle_open']);
$tests['vfs filecontrol persistence current next80 initial generation'] = static fn (TestRunner $t) => $t->same(1, $basicVfs80()['current']['open_generation']);
$tests['vfs filecontrol persistence current next80 starts persist wal false'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['current']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence current next80 persist wal op is parsed'] = static fn (TestRunner $t) => $t->same('persist_wal', $basicVfs80()['events'][0]['result']['op']);
$tests['vfs filecontrol persistence current next80 persist wal changes persistent'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['events'][0]['result']['persistent_changed']);
$tests['vfs filecontrol persistence current next80 persist wal next persistent true'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['events'][0]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence current next80 persist wal next handle true'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['events'][0]['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence current next80 reserve op is parsed'] = static fn (TestRunner $t) => $t->same('reserve_bytes', $basicVfs80()['events'][1]['result']['op']);
$tests['vfs filecontrol persistence current next80 reserve changes persistent'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['events'][1]['result']['persistent_changed']);
$tests['vfs filecontrol persistence current next80 reserve next persistent'] = static fn (TestRunner $t) => $t->same(16, $basicVfs80()['events'][1]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 reserve next handle'] = static fn (TestRunner $t) => $t->same(16, $basicVfs80()['events'][1]['next']['handle']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 mmap is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][2]['result']['persistent_changed']);
$tests['vfs filecontrol persistence current next80 mmap updates current handle'] = static fn (TestRunner $t) => $t->same(65536, $basicVfs80()['events'][2]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence current next80 chunk is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][3]['result']['persistent_changed']);
$tests['vfs filecontrol persistence current next80 chunk updates current handle'] = static fn (TestRunner $t) => $t->same(8192, $basicVfs80()['events'][3]['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence current next80 name hint is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][4]['result']['persistent_changed']);
$tests['vfs filecontrol persistence current next80 name hint updates current handle'] = static fn (TestRunner $t) => $t->same('wp import', $basicVfs80()['events'][4]['next']['handle']['name_hint']);
$tests['vfs filecontrol persistence current next80 write hint is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][5]['result']['persistent_changed']);
$tests['vfs filecontrol persistence current next80 write hint updates current handle'] = static fn (TestRunner $t) => $t->same(24576, $basicVfs80()['events'][5]['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence current next80 close marks handle closed'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][6]['next']['handle_open']);
$tests['vfs filecontrol persistence current next80 close preserves persistent wal'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['events'][6]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence current next80 close preserves reserve'] = static fn (TestRunner $t) => $t->same(16, $basicVfs80()['events'][6]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 reopen status'] = static fn (TestRunner $t) => $t->same('reopened', $basicVfs80()['events'][7]['result']['status']);
$tests['vfs filecontrol persistence current next80 reopen increments generation'] = static fn (TestRunner $t) => $t->same(2, $basicVfs80()['events'][7]['next']['open_generation']);
$tests['vfs filecontrol persistence current next80 reopen restores persist wal to handle'] = static fn (TestRunner $t) => $t->same(true, $basicVfs80()['events'][7]['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence current next80 reopen restores reserve bytes'] = static fn (TestRunner $t) => $t->same(16, $basicVfs80()['events'][7]['next']['handle']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 reopen resets mmap to capability default'] = static fn (TestRunner $t) => $t->same(0, $basicVfs80()['events'][7]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence current next80 reopen clears chunk'] = static fn (TestRunner $t) => $t->same(null, $basicVfs80()['events'][7]['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence current next80 reopen clears name hint'] = static fn (TestRunner $t) => $t->same(null, $basicVfs80()['events'][7]['next']['handle']['name_hint']);
$tests['vfs filecontrol persistence current next80 reopen clears write hint'] = static fn (TestRunner $t) => $t->same(null, $basicVfs80()['events'][7]['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence current next80 second mmap applies after reopen'] = static fn (TestRunner $t) => $t->same(32768, $basicVfs80()['events'][8]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence current next80 persist wal off changes persistent'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][9]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence current next80 persist wal off changes handle'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['events'][9]['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence current next80 final reopen increments generation'] = static fn (TestRunner $t) => $t->same(3, $basicVfs80()['next']['open_generation']);
$tests['vfs filecontrol persistence current next80 final persistent wal false'] = static fn (TestRunner $t) => $t->same(false, $basicVfs80()['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence current next80 final reserve remains persistent'] = static fn (TestRunner $t) => $t->same(16, $basicVfs80()['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 final handle reserve restored'] = static fn (TestRunner $t) => $t->same(16, $basicVfs80()['next']['handle']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 final handle mmap reset'] = static fn (TestRunner $t) => $t->same(0, $basicVfs80()['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence current next80 dependencies include slice'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-filecontrol-persistence-current-next80', $basicVfs80()['dependencies'], true));
$tests['vfs filecontrol persistence current next80 dependencies include state'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-file-control-state', $basicVfs80()['dependencies'], true));
$tests['vfs filecontrol persistence current next80 dependencies include xfilecontrol'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-xfilecontrol', $basicVfs80()['dependencies'], true));

$tests['vfs filecontrol persistence current next80 nonpersistent count'] = static fn (TestRunner $t) => $t->same(7, $nonPersistentVfs80()['count']);
$tests['vfs filecontrol persistence current next80 nonpersistent chunk before close'] = static fn (TestRunner $t) => $t->same(4096, $nonPersistentVfs80()['events'][0]['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence current next80 nonpersistent mmap before close'] = static fn (TestRunner $t) => $t->same(32768, $nonPersistentVfs80()['events'][1]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence current next80 nonpersistent write hint before close'] = static fn (TestRunner $t) => $t->same(1024, $nonPersistentVfs80()['events'][2]['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence current next80 nonpersistent overwrite before close'] = static fn (TestRunner $t) => $t->same([4], $nonPersistentVfs80()['events'][3]['next']['handle']['overwrite_pages']);
$tests['vfs filecontrol persistence current next80 nonpersistent sync before close'] = static fn (TestRunner $t) => $t->same(1, $nonPersistentVfs80()['events'][4]['next']['handle']['sync_count']);
$tests['vfs filecontrol persistence current next80 nonpersistent reopen clears chunk'] = static fn (TestRunner $t) => $t->same(null, $nonPersistentVfs80()['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence current next80 nonpersistent reopen resets mmap'] = static fn (TestRunner $t) => $t->same(0, $nonPersistentVfs80()['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence current next80 nonpersistent reopen clears write hint'] = static fn (TestRunner $t) => $t->same(null, $nonPersistentVfs80()['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence current next80 nonpersistent reopen clears overwrite pages'] = static fn (TestRunner $t) => $t->same([], $nonPersistentVfs80()['next']['handle']['overwrite_pages']);
$tests['vfs filecontrol persistence current next80 nonpersistent reopen clears sync count'] = static fn (TestRunner $t) => $t->same(0, $nonPersistentVfs80()['next']['handle']['sync_count']);
$tests['vfs filecontrol persistence current next80 nonpersistent persistent map unchanged'] = static fn (TestRunner $t) => $t->same(['persist_wal' => false, 'reserve_bytes' => 0, 'powersafe_overwrite' => true], $nonPersistentVfs80()['persistent']);

$tests['vfs filecontrol persistence current next80 closed control ignored'] = static fn (TestRunner $t) => $t->same('ignored', $closedVfs80()['events'][1]['result']['status']);
$tests['vfs filecontrol persistence current next80 closed control reason'] = static fn (TestRunner $t) => $t->same('file_control_requires_open_handle', $closedVfs80()['events'][1]['result']['reason']);
$tests['vfs filecontrol persistence current next80 closed control does not change reserve'] = static fn (TestRunner $t) => $t->same(0, $closedVfs80()['events'][1]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 reopened control can persist reserve'] = static fn (TestRunner $t) => $t->same(8, $closedVfs80()['events'][3]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 reopened control final status ok'] = static fn (TestRunner $t) => $t->same('ok', $closedVfs80()['status']);

$tests['vfs filecontrol persistence current next80 readonly persist wal can persist'] = static fn (TestRunner $t) => $t->same(true, $readonlyVfs80()['events'][0]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence current next80 readonly reserve ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readonlyVfs80()['events'][1]['result']['status']);
$tests['vfs filecontrol persistence current next80 readonly reserve does not persist'] = static fn (TestRunner $t) => $t->same(0, $readonlyVfs80()['events'][1]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence current next80 readonly reopen restores persist wal'] = static fn (TestRunner $t) => $t->same(true, $readonlyVfs80()['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence current next80 readonly dependencies include immutable'] = static fn (TestRunner $t) => $t->same(true, in_array('immutable-readonly-open', $readonlyVfs80()['dependencies'], true));

$tests['vfs filecontrol persistence current next80 powersafe off persists'] = static fn (TestRunner $t) => $t->same(false, $powerSafeVfs80()['events'][0]['next']['persistent']['powersafe_overwrite']);
$tests['vfs filecontrol persistence current next80 powersafe off survives reopen'] = static fn (TestRunner $t) => $t->same(false, $powerSafeVfs80()['events'][2]['next']['handle']['powersafe_overwrite']);
$tests['vfs filecontrol persistence current next80 powersafe on persists'] = static fn (TestRunner $t) => $t->same(true, $powerSafeVfs80()['events'][3]['next']['persistent']['powersafe_overwrite']);
$tests['vfs filecontrol persistence current next80 powersafe on survives final reopen'] = static fn (TestRunner $t) => $t->same(true, $powerSafeVfs80()['next']['handle']['powersafe_overwrite']);

$tests['vfs filecontrol persistence current next80 constructor accepts initial persisted controls'] = static function (TestRunner $t) use ($runVfs80): void {
    $result = $runVfs80(['close', 'reopen'], ['file_controls' => ['persist_wal' => true, 'reserve_bytes' => 24, 'powersafe_overwrite' => false]]);

    $t->same(true, $result['current']['persistent']['persist_wal']);
    $t->same(24, $result['next']['handle']['reserve_bytes']);
    $t->same(false, $result['next']['handle']['powersafe_overwrite']);
};

$tests['vfs filecontrol persistence current next80 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::currentNext80([]));
$tests['vfs filecontrol persistence current next80 rejects empty string operation'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runVfs80(['']));
$tests['vfs filecontrol persistence current next80 rejects array without op'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runVfs80([['value' => 1]]));
$tests['vfs filecontrol persistence current next80 rejects empty filename'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::currentNext80(['reopen'], ['filename' => '']));
$tests['vfs filecontrol persistence current next80 rejects bad sector size type'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::currentNext80(['reopen'], ['sector_size' => '4096']));
$tests['vfs filecontrol persistence current next80 rejects bad initial reserve'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::currentNext80(['reopen'], ['file_controls' => ['reserve_bytes' => 300]]));

return $tests;
