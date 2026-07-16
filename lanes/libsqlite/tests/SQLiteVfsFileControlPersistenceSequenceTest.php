<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;

$tests = [];

$runPersistentVfs = static fn (array $ops, array $options = []): array => SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence(
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

$basicPersistentVfs = static function () use ($runPersistentVfs): array {
    static $result = null;
    if ($result === null) {
        $result = $runPersistentVfs([
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

$nonPersistentVfs = static function () use ($runPersistentVfs): array {
    static $result = null;
    if ($result === null) {
        $result = $runPersistentVfs([
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

$closedPersistentVfs = static fn (): array => $runPersistentVfs([
    'close',
    'PRAGMA reserve_bytes=8',
    'reopen',
    'PRAGMA reserve_bytes=8',
]);

$readonlyPersistentVfs = static fn (): array => $runPersistentVfs([
    'file_control(persist_wal, on)',
    'PRAGMA reserve_bytes=8',
    'close',
    'reopen',
], ['filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1']);

$powerSafePersistentVfs = static fn (): array => $runPersistentVfs([
    'file_control(powersafe_overwrite, off)',
    'close',
    'reopen',
    'file_control(powersafe_overwrite, on)',
    'close',
    'reopen',
]);

$tests['vfs filecontrol persistence persistent filecontrol sequence count'] = static fn (TestRunner $t) => $t->same(12, $basicPersistentVfs()['count']);
$tests['vfs filecontrol persistence persistent filecontrol sequence initial handle is open'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['current']['handle_open']);
$tests['vfs filecontrol persistence persistent filecontrol sequence initial generation'] = static fn (TestRunner $t) => $t->same(1, $basicPersistentVfs()['current']['open_generation']);
$tests['vfs filecontrol persistence persistent filecontrol sequence starts persist wal false'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['current']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence persist wal op is parsed'] = static fn (TestRunner $t) => $t->same('persist_wal', $basicPersistentVfs()['events'][0]['result']['op']);
$tests['vfs filecontrol persistence persistent filecontrol sequence persist wal changes persistent'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['events'][0]['result']['persistent_changed']);
$tests['vfs filecontrol persistence persistent filecontrol sequence persist wal next persistent true'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['events'][0]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence persist wal next handle true'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['events'][0]['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reserve op is parsed'] = static fn (TestRunner $t) => $t->same('reserve_bytes', $basicPersistentVfs()['events'][1]['result']['op']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reserve changes persistent'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['events'][1]['result']['persistent_changed']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reserve next persistent'] = static fn (TestRunner $t) => $t->same(16, $basicPersistentVfs()['events'][1]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reserve next handle'] = static fn (TestRunner $t) => $t->same(16, $basicPersistentVfs()['events'][1]['next']['handle']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence mmap is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][2]['result']['persistent_changed']);
$tests['vfs filecontrol persistence persistent filecontrol sequence mmap updates current handle'] = static fn (TestRunner $t) => $t->same(65536, $basicPersistentVfs()['events'][2]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence chunk is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][3]['result']['persistent_changed']);
$tests['vfs filecontrol persistence persistent filecontrol sequence chunk updates current handle'] = static fn (TestRunner $t) => $t->same(8192, $basicPersistentVfs()['events'][3]['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence name hint is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][4]['result']['persistent_changed']);
$tests['vfs filecontrol persistence persistent filecontrol sequence name hint updates current handle'] = static fn (TestRunner $t) => $t->same('wp import', $basicPersistentVfs()['events'][4]['next']['handle']['name_hint']);
$tests['vfs filecontrol persistence persistent filecontrol sequence write hint is handle local'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][5]['result']['persistent_changed']);
$tests['vfs filecontrol persistence persistent filecontrol sequence write hint updates current handle'] = static fn (TestRunner $t) => $t->same(24576, $basicPersistentVfs()['events'][5]['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence close marks handle closed'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][6]['next']['handle_open']);
$tests['vfs filecontrol persistence persistent filecontrol sequence close preserves persistent wal'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['events'][6]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence close preserves reserve'] = static fn (TestRunner $t) => $t->same(16, $basicPersistentVfs()['events'][6]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen status'] = static fn (TestRunner $t) => $t->same('reopened', $basicPersistentVfs()['events'][7]['result']['status']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen increments generation'] = static fn (TestRunner $t) => $t->same(2, $basicPersistentVfs()['events'][7]['next']['open_generation']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen restores persist wal to handle'] = static fn (TestRunner $t) => $t->same(true, $basicPersistentVfs()['events'][7]['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen restores reserve bytes'] = static fn (TestRunner $t) => $t->same(16, $basicPersistentVfs()['events'][7]['next']['handle']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen resets mmap to capability default'] = static fn (TestRunner $t) => $t->same(0, $basicPersistentVfs()['events'][7]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen clears chunk'] = static fn (TestRunner $t) => $t->same(null, $basicPersistentVfs()['events'][7]['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen clears name hint'] = static fn (TestRunner $t) => $t->same(null, $basicPersistentVfs()['events'][7]['next']['handle']['name_hint']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopen clears write hint'] = static fn (TestRunner $t) => $t->same(null, $basicPersistentVfs()['events'][7]['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence second mmap applies after reopen'] = static fn (TestRunner $t) => $t->same(32768, $basicPersistentVfs()['events'][8]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence persist wal off changes persistent'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][9]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence persist wal off changes handle'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['events'][9]['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence final reopen increments generation'] = static fn (TestRunner $t) => $t->same(3, $basicPersistentVfs()['next']['open_generation']);
$tests['vfs filecontrol persistence persistent filecontrol sequence final persistent wal false'] = static fn (TestRunner $t) => $t->same(false, $basicPersistentVfs()['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence final reserve remains persistent'] = static fn (TestRunner $t) => $t->same(16, $basicPersistentVfs()['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence final handle reserve restored'] = static fn (TestRunner $t) => $t->same(16, $basicPersistentVfs()['next']['handle']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence final handle mmap reset'] = static fn (TestRunner $t) => $t->same(0, $basicPersistentVfs()['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence dependencies include slice'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-filecontrol-persistence-sequence', $basicPersistentVfs()['dependencies'], true));
$tests['vfs filecontrol persistence persistent filecontrol sequence dependencies include state'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-file-control-state', $basicPersistentVfs()['dependencies'], true));
$tests['vfs filecontrol persistence persistent filecontrol sequence dependencies include xfilecontrol'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-xfilecontrol', $basicPersistentVfs()['dependencies'], true));

$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent count'] = static fn (TestRunner $t) => $t->same(7, $nonPersistentVfs()['count']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent chunk before close'] = static fn (TestRunner $t) => $t->same(4096, $nonPersistentVfs()['events'][0]['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent mmap before close'] = static fn (TestRunner $t) => $t->same(32768, $nonPersistentVfs()['events'][1]['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent write hint before close'] = static fn (TestRunner $t) => $t->same(1024, $nonPersistentVfs()['events'][2]['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent overwrite before close'] = static fn (TestRunner $t) => $t->same([4], $nonPersistentVfs()['events'][3]['next']['handle']['overwrite_pages']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent sync before close'] = static fn (TestRunner $t) => $t->same(1, $nonPersistentVfs()['events'][4]['next']['handle']['sync_count']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent reopen clears chunk'] = static fn (TestRunner $t) => $t->same(null, $nonPersistentVfs()['next']['handle']['chunk_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent reopen resets mmap'] = static fn (TestRunner $t) => $t->same(0, $nonPersistentVfs()['next']['handle']['mmap_size']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent reopen clears write hint'] = static fn (TestRunner $t) => $t->same(null, $nonPersistentVfs()['next']['handle']['write_hint_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent reopen clears overwrite pages'] = static fn (TestRunner $t) => $t->same([], $nonPersistentVfs()['next']['handle']['overwrite_pages']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent reopen clears sync count'] = static fn (TestRunner $t) => $t->same(0, $nonPersistentVfs()['next']['handle']['sync_count']);
$tests['vfs filecontrol persistence persistent filecontrol sequence nonpersistent persistent map unchanged'] = static fn (TestRunner $t) => $t->same(['persist_wal' => false, 'reserve_bytes' => 0, 'powersafe_overwrite' => true], $nonPersistentVfs()['persistent']);

$tests['vfs filecontrol persistence persistent filecontrol sequence closed control ignored'] = static fn (TestRunner $t) => $t->same('ignored', $closedPersistentVfs()['events'][1]['result']['status']);
$tests['vfs filecontrol persistence persistent filecontrol sequence closed control reason'] = static fn (TestRunner $t) => $t->same('file_control_requires_open_handle', $closedPersistentVfs()['events'][1]['result']['reason']);
$tests['vfs filecontrol persistence persistent filecontrol sequence closed control does not change reserve'] = static fn (TestRunner $t) => $t->same(0, $closedPersistentVfs()['events'][1]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopened control can persist reserve'] = static fn (TestRunner $t) => $t->same(8, $closedPersistentVfs()['events'][3]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence reopened control final status ok'] = static fn (TestRunner $t) => $t->same('ok', $closedPersistentVfs()['status']);

$tests['vfs filecontrol persistence persistent filecontrol sequence readonly persist wal can persist'] = static fn (TestRunner $t) => $t->same(true, $readonlyPersistentVfs()['events'][0]['next']['persistent']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence readonly reserve ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readonlyPersistentVfs()['events'][1]['result']['status']);
$tests['vfs filecontrol persistence persistent filecontrol sequence readonly reserve does not persist'] = static fn (TestRunner $t) => $t->same(0, $readonlyPersistentVfs()['events'][1]['next']['persistent']['reserve_bytes']);
$tests['vfs filecontrol persistence persistent filecontrol sequence readonly reopen restores persist wal'] = static fn (TestRunner $t) => $t->same(true, $readonlyPersistentVfs()['next']['handle']['persist_wal']);
$tests['vfs filecontrol persistence persistent filecontrol sequence readonly dependencies include immutable'] = static fn (TestRunner $t) => $t->same(true, in_array('immutable-readonly-open', $readonlyPersistentVfs()['dependencies'], true));

$tests['vfs filecontrol persistence persistent filecontrol sequence powersafe off persists'] = static fn (TestRunner $t) => $t->same(false, $powerSafePersistentVfs()['events'][0]['next']['persistent']['powersafe_overwrite']);
$tests['vfs filecontrol persistence persistent filecontrol sequence powersafe off survives reopen'] = static fn (TestRunner $t) => $t->same(false, $powerSafePersistentVfs()['events'][2]['next']['handle']['powersafe_overwrite']);
$tests['vfs filecontrol persistence persistent filecontrol sequence powersafe on persists'] = static fn (TestRunner $t) => $t->same(true, $powerSafePersistentVfs()['events'][3]['next']['persistent']['powersafe_overwrite']);
$tests['vfs filecontrol persistence persistent filecontrol sequence powersafe on survives final reopen'] = static fn (TestRunner $t) => $t->same(true, $powerSafePersistentVfs()['next']['handle']['powersafe_overwrite']);

$tests['vfs filecontrol persistence persistent filecontrol sequence constructor accepts initial persisted controls'] = static function (TestRunner $t) use ($runPersistentVfs): void {
    $result = $runPersistentVfs(['close', 'reopen'], ['file_controls' => ['persist_wal' => true, 'reserve_bytes' => 24, 'powersafe_overwrite' => false]]);

    $t->same(true, $result['current']['persistent']['persist_wal']);
    $t->same(24, $result['next']['handle']['reserve_bytes']);
    $t->same(false, $result['next']['handle']['powersafe_overwrite']);
};

$tests['vfs filecontrol persistence persistent filecontrol sequence rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence([]));
$tests['vfs filecontrol persistence persistent filecontrol sequence rejects empty string operation'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runPersistentVfs(['']));
$tests['vfs filecontrol persistence persistent filecontrol sequence rejects array without op'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runPersistentVfs([['value' => 1]]));
$tests['vfs filecontrol persistence persistent filecontrol sequence rejects empty filename'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence(['reopen'], ['filename' => '']));
$tests['vfs filecontrol persistence persistent filecontrol sequence rejects bad sector size type'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence(['reopen'], ['sector_size' => '4096']));
$tests['vfs filecontrol persistence persistent filecontrol sequence rejects bad initial reserve'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence(['reopen'], ['file_controls' => ['reserve_bytes' => 300]]));

return $tests;
