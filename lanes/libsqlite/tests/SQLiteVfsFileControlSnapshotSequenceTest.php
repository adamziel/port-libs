<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCapabilityPlan;
use PortLibs\LibSqlite\SQLiteVfsFileControlState;

$tests = [];

$makeState = static function (array $fileControls = [], string $filename = 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix'): SQLiteVfsFileControlState {
    $capability = SQLiteVfsCapabilityPlan::forFilename(
        $filename,
        true,
        true,
        4096,
        ['safe_append', 'powersafe_overwrite'],
        'full',
        false,
        8192,
        0
    );
    $capability['file_controls'] = array_merge($capability['file_controls'], $fileControls);

    return SQLiteVfsFileControlState::fromCapabilityPlan($capability);
};

$tests['vfs filecontrol filecontrol snapshot sequence exposes initial 64 bit controls'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 9223372036854775807, 'lock_timeout' => 2500, 'reserve_bytes' => 8, 'data_version' => 7]);
    $sequence = $state->fileControlSnapshotSequence([]);

    $t->same('ok', $sequence['status']);
    $t->same(0, $sequence['count']);
    $t->same(9223372036854775807, $sequence['controls']['size_limit']);
    $t->same(2500, $sequence['controls']['lock_timeout']);
    $t->same(8, $sequence['controls']['reserve_bytes']);
    $t->same(7, $sequence['controls']['data_version']);
    $t->same(true, in_array('vfs-file-control-snapshot-sequence', $sequence['dependencies'], true));
};

$operations = [
    'size limit can be raised to max int' => ['size_limit', 9223372036854775807, null, 9223372036854775807, true],
    'size limit can be lowered to import cap' => ['size_limit', 1048576, null, 1048576, true],
    'size limit can be set to zero' => ['size_limit', 0, null, 0, true],
    'size limit null reads current value' => ['size_limit', null, 65536, 65536, false],
    'size limit minus one reads current value' => ['size_limit', -1, 65536, 65536, false],
    'reserve bytes can be set to one' => ['reserve_bytes', 1, 0, 1, true],
    'reserve bytes can be set to max byte' => ['reserve_bytes', 255, 0, 255, true],
    'reserve bytes unchanged reports stable current next' => ['reserve_bytes', 8, 8, 8, false],
    'reserve bytes null reads current value' => ['reserve_bytes', null, 12, 12, false],
    'reserve bytes minus one reads current value' => ['reserve_bytes', -1, 12, 12, false],
    'lock timeout can be disabled' => ['lock_timeout', 0, 3000, 0, true],
    'lock timeout can use busy window' => ['lock_timeout', 5000, 0, 5000, true],
    'lock timeout unchanged reports no change' => ['lock_timeout', 250, 250, 250, false],
    'data version reads initial value' => ['data_version', null, 9, 9, false],
];

foreach ($operations as $name => [$op, $value, $initial, $expected, $changed]) {
    $tests['vfs filecontrol filecontrol snapshot sequence ' . $name] = static function (TestRunner $t) use ($makeState, $op, $value, $initial, $expected, $changed): void {
        $initialControls = [];
        if ($initial !== null) {
            $initialControls[$op] = $initial;
        }
        $state = $makeState($initialControls);
        $sequence = $state->fileControlSnapshotSequence([['op' => $op, 'value' => $value]]);
        $pair = $sequence['pairs'][0];

        $t->same(1, $sequence['count']);
        $t->same($op, $pair['op']);
        $t->same($changed, $pair['result']['changed']);
        $t->same($expected, $pair['result']['value']);
        $t->same($expected, $pair['next'][$op]);
        $t->same($expected, $sequence['controls'][$op]);
    };
}

$tests['vfs filecontrol filecontrol snapshot sequence preserves previous size limit in pair'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 4096]);
    $pair = $state->fileControlSnapshotSequence(['size_limit' => 8192])['pairs'][0];

    $t->same(4096, $pair['current']['size_limit']);
    $t->same(8192, $pair['next']['size_limit']);
    $t->same(4096, $pair['result']['previous']);
};

$tests['vfs filecontrol filecontrol snapshot sequence preserves previous reserve bytes in pair'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['reserve_bytes' => 4]);
    $pair = $state->fileControlSnapshotSequence(['reserve_bytes' => 16])['pairs'][0];

    $t->same(4, $pair['current']['reserve_bytes']);
    $t->same(16, $pair['next']['reserve_bytes']);
    $t->same(4, $pair['result']['previous']);
};

$tests['vfs filecontrol filecontrol snapshot sequence preserves previous lock timeout in pair'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['lock_timeout' => 75]);
    $pair = $state->fileControlSnapshotSequence(['lock_timeout' => 150])['pairs'][0];

    $t->same(75, $pair['current']['lock_timeout']);
    $t->same(150, $pair['next']['lock_timeout']);
    $t->same(75, $pair['result']['previous']);
};

$tests['vfs filecontrol filecontrol snapshot sequence leaves data version read only'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['data_version' => 44]);
    $pair = $state->fileControlSnapshotSequence(['data_version' => null])['pairs'][0];

    $t->same(44, $pair['current']['data_version']);
    $t->same(44, $pair['next']['data_version']);
    $t->same(false, $pair['result']['changed']);
};

$tests['vfs filecontrol filecontrol snapshot sequence reports same path has not moved'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $pair = $state->fileControlSnapshotSequence(['has_moved' => '/srv/www/wp-content/database/.ht.sqlite'])['pairs'][0];

    $t->same(false, $pair['result']['value']);
    $t->same(false, $pair['next']['has_moved']);
    $t->same(false, $pair['result']['changed']);
};

$tests['vfs filecontrol filecontrol snapshot sequence reports renamed path moved'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $pair = $state->fileControlSnapshotSequence(['has_moved' => '/srv/www/wp-content/database/.ht-renamed.sqlite'])['pairs'][0];

    $t->same(true, $pair['result']['value']);
    $t->same(true, $pair['next']['has_moved']);
    $t->same(true, $pair['result']['changed']);
};

$tests['vfs filecontrol filecontrol snapshot sequence has moved can return current flag'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $sequence = $state->fileControlSnapshotSequence([
        'has_moved' => '/srv/www/wp-content/database/renamed.sqlite',
        ['op' => 'has_moved', 'value' => null],
    ]);

    $t->same(true, $sequence['pairs'][0]['next']['has_moved']);
    $t->same(true, $sequence['pairs'][1]['result']['value']);
    $t->same(true, $sequence['pairs'][1]['current']['has_moved']);
};

$tests['vfs filecontrol filecontrol snapshot sequence temp filename is deterministic'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $sequence = $state->fileControlSnapshotSequence([
        ['op' => 'name_hint', 'value' => 'wp-options-import'],
        ['op' => 'tempfilename', 'value' => 'db'],
        ['op' => 'temp_filename', 'value' => 'db'],
    ]);

    $t->same('tempfilename', $sequence['pairs'][1]['op']);
    $t->same($sequence['pairs'][1]['result']['value'], $sequence['pairs'][2]['result']['value']);
    $t->same(true, str_starts_with($sequence['pairs'][1]['result']['value'], '/srv/www/wp-content/database/etilqs_'));
    $t->same('.db', substr($sequence['pairs'][1]['result']['value'], -3));
};

$tests['vfs filecontrol filecontrol snapshot sequence temp filename defaults to sqlite suffix'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $value = $state->fileControlSnapshotSequence(['tempfilename' => null])['pairs'][0]['result']['value'];

    $t->same(true, str_starts_with($value, '/srv/www/wp-content/database/etilqs_'));
    $t->same('.sqlite', substr($value, -7));
};

$tests['vfs filecontrol filecontrol snapshot sequence sequence reports ordinals'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $sequence = $state->fileControlSnapshotSequence([
        'size_limit' => 1024,
        'reserve_bytes' => 2,
        'lock_timeout' => 50,
    ]);

    $t->same([0, 1, 2], array_column($sequence['pairs'], 'ordinal'));
    $t->same(['size_limit', 'reserve_bytes', 'lock_timeout'], array_column($sequence['pairs'], 'op'));
};

$tests['vfs filecontrol filecontrol snapshot sequence sequence threads size limit into next current'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 2048]);
    $sequence = $state->fileControlSnapshotSequence([
        'size_limit' => 4096,
        'reserve_bytes' => 8,
    ]);

    $t->same(2048, $sequence['pairs'][0]['current']['size_limit']);
    $t->same(4096, $sequence['pairs'][0]['next']['size_limit']);
    $t->same(4096, $sequence['pairs'][1]['current']['size_limit']);
};

$tests['vfs filecontrol filecontrol snapshot sequence sequence threads reserve into next current'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['reserve_bytes' => 1]);
    $sequence = $state->fileControlSnapshotSequence([
        'reserve_bytes' => 2,
        'lock_timeout' => 25,
    ]);

    $t->same(1, $sequence['pairs'][0]['current']['reserve_bytes']);
    $t->same(2, $sequence['pairs'][0]['next']['reserve_bytes']);
    $t->same(2, $sequence['pairs'][1]['current']['reserve_bytes']);
};

$tests['vfs filecontrol filecontrol snapshot sequence sequence threads lock timeout into next current'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['lock_timeout' => 10]);
    $sequence = $state->fileControlSnapshotSequence([
        'lock_timeout' => 20,
        'size_limit' => 4096,
    ]);

    $t->same(10, $sequence['pairs'][0]['current']['lock_timeout']);
    $t->same(20, $sequence['pairs'][0]['next']['lock_timeout']);
    $t->same(20, $sequence['pairs'][1]['current']['lock_timeout']);
};

$tests['vfs filecontrol filecontrol snapshot sequence applies mixed integer batch controls'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $sequence = $state->fileControlSnapshotSequence([
        ['op' => 'size_limit', 'value' => 7000],
        ['op' => 'reserve_bytes', 'value' => 7],
        ['op' => 'lock_timeout', 'value' => 70],
    ]);

    $t->same(3, $sequence['count']);
    $t->same(7000, $sequence['controls']['size_limit']);
    $t->same(7, $sequence['controls']['reserve_bytes']);
    $t->same(70, $sequence['controls']['lock_timeout']);
};

$tests['vfs filecontrol filecontrol snapshot sequence counts notfound without applying'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 7000]);
    $sequence = $state->fileControlSnapshotSequence(['not_a_control' => 1]);

    $t->same(1, $sequence['count']);
    $t->same('notfound', $sequence['pairs'][0]['result']['status']);
    $t->same(7000, $sequence['pairs'][0]['current']['size_limit']);
    $t->same(7000, $sequence['pairs'][0]['next']['size_limit']);
};

$tests['vfs filecontrol filecontrol snapshot sequence apply many counts new controls'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 1024]);
    $batch = $state->applyMany([
        'size_limit' => 2048,
        'reserve_bytes' => 3,
        'lock_timeout' => 40,
        'data_version' => null,
    ]);

    $t->same('ok', $batch['status']);
    $t->same(4, $batch['applied']);
    $t->same(3, $batch['changed']);
    $t->same(2048, $batch['controls']['size_limit']);
    $t->same(3, $batch['controls']['reserve_bytes']);
    $t->same(40, $batch['controls']['lock_timeout']);
};

$tests['vfs filecontrol filecontrol snapshot sequence readonly reserve is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', true, false));
    $pair = $state->fileControlSnapshotSequence(['reserve_bytes' => 8])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('reserve_bytes_requires_writable_file_handle', $pair['result']['reason']);
    $t->same(0, $pair['next']['reserve_bytes']);
    $t->same(true, in_array('readonly-open', $pair['result']['dependencies'], true));
};

$tests['vfs filecontrol filecontrol snapshot sequence memory reserve is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file::memory:?mode=memory', false, true));
    $pair = $state->fileControlSnapshotSequence(['reserve_bytes' => 8])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same(0, $pair['next']['reserve_bytes']);
    $t->same(true, in_array('memory-open', $pair['result']['dependencies'], true));
};

$tests['vfs filecontrol filecontrol snapshot sequence nolock lock timeout is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/nolock.sqlite?nolock=1', true, true));
    $pair = $state->fileControlSnapshotSequence(['lock_timeout' => 500])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('lock_timeout_requires_lockable_file', $pair['result']['reason']);
    $t->same(0, $pair['next']['lock_timeout']);
    $t->same(true, in_array('nolock-open', $pair['result']['dependencies'], true));
};

$tests['vfs filecontrol filecontrol snapshot sequence memory lock timeout is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file::memory:?mode=memory', false, true));
    $pair = $state->fileControlSnapshotSequence(['lock_timeout' => 500])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same(0, $pair['next']['lock_timeout']);
    $t->same(true, in_array('memory-open', $pair['result']['dependencies'], true));
};

$tests['vfs filecontrol filecontrol snapshot sequence keeps mmap and chunk in pair snapshots'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['chunk_size' => 16384, 'mmap_size' => 32768]);
    $pair = $state->fileControlSnapshotSequence(['size_limit' => 123456])['pairs'][0];

    $t->same(16384, $pair['current']['chunk_size']);
    $t->same(32768, $pair['current']['mmap_size']);
    $t->same(16384, $pair['next']['chunk_size']);
    $t->same(32768, $pair['next']['mmap_size']);
};

$tests['vfs filecontrol filecontrol snapshot sequence keeps boolean controls in pair snapshots'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['persist_wal' => true, 'powersafe_overwrite' => true]);
    $pair = $state->fileControlSnapshotSequence(['reserve_bytes' => 4])['pairs'][0];

    $t->same(true, $pair['current']['persist_wal']);
    $t->same(true, $pair['current']['powersafe_overwrite']);
    $t->same(true, $pair['next']['persist_wal']);
    $t->same(true, $pair['next']['powersafe_overwrite']);
};

$tests['vfs filecontrol filecontrol snapshot sequence supports dash normalized size limit'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $pair = $state->fileControlSnapshotSequence(['size-limit' => 512])['pairs'][0];

    $t->same('size_limit', $pair['op']);
    $t->same(512, $pair['next']['size_limit']);
};

$tests['vfs filecontrol filecontrol snapshot sequence supports dash normalized reserve bytes'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $pair = $state->fileControlSnapshotSequence(['reserve-bytes' => 5])['pairs'][0];

    $t->same('reserve_bytes', $pair['op']);
    $t->same(5, $pair['next']['reserve_bytes']);
};

$tests['vfs filecontrol filecontrol snapshot sequence supports dash normalized lock timeout'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState();
    $pair = $state->fileControlSnapshotSequence(['lock-timeout' => 15])['pairs'][0];

    $t->same('lock_timeout', $pair['op']);
    $t->same(15, $pair['next']['lock_timeout']);
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects negative size limit'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['size_limit' => -2]));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects negative reserve bytes'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['reserve_bytes' => -2]));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects oversized reserve bytes'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['reserve_bytes' => 256]));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects negative lock timeout'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['lock_timeout' => -1]));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects non integer lock timeout'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['lock_timeout' => '100']));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects malformed sequence item'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence([['value' => 1]]));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects empty has moved comparison'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['has_moved' => '']));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects temp filename slash'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['tempfilename' => '../bad']));
};

$tests['vfs filecontrol filecontrol snapshot sequence rejects temp filename nul'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->fileControlSnapshotSequence(['tempfilename' => "bad\0name"]));
};

$tests['vfs filecontrol filecontrol snapshot sequence constructor rejects invalid reserve bytes'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState(['reserve_bytes' => 300]));
};

$tests['vfs filecontrol filecontrol snapshot sequence constructor rejects invalid data version'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState(['data_version' => 0]));
};

$tests['vfs filecontrol filecontrol snapshot sequence constructor rejects invalid lock timeout'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState(['lock_timeout' => -1]));
};

$tests['vfs filecontrol filecontrol snapshot sequence constructor accepts zero size limit'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 0]);

    $t->same(0, $state->snapshot()['controls']['size_limit']);
};

$tests['vfs filecontrol filecontrol snapshot sequence snapshot includes new controls'] = static function (TestRunner $t) use ($makeState): void {
    $snapshot = $makeState(['size_limit' => 123, 'reserve_bytes' => 9, 'lock_timeout' => 90, 'data_version' => 3])->snapshot();

    $t->same(123, $snapshot['controls']['size_limit']);
    $t->same(9, $snapshot['controls']['reserve_bytes']);
    $t->same(90, $snapshot['controls']['lock_timeout']);
    $t->same(3, $snapshot['controls']['data_version']);
    $t->same(false, $snapshot['controls']['has_moved']);
};

$tests['vfs filecontrol filecontrol snapshot sequence wordpress import sequence summary'] = static function (TestRunner $t) use ($makeState): void {
    $state = $makeState(['size_limit' => 1048576, 'data_version' => 12]);
    $sequence = $state->fileControlSnapshotSequence([
        ['op' => 'name_hint', 'value' => 'wp-options-bulk-import'],
        ['op' => 'lock_timeout', 'value' => 2500],
        ['op' => 'reserve_bytes', 'value' => 32],
        ['op' => 'size_limit', 'value' => 8388608],
        ['op' => 'data_version', 'value' => null],
        ['op' => 'tempfilename', 'value' => 'journal'],
    ]);

    $t->same(6, $sequence['count']);
    $t->same(2500, $sequence['controls']['lock_timeout']);
    $t->same(32, $sequence['controls']['reserve_bytes']);
    $t->same(8388608, $sequence['controls']['size_limit']);
    $t->same(12, $sequence['controls']['data_version']);
    $t->same('.journal', substr($sequence['pairs'][5]['result']['value'], -8));
};

$tests['vfs filecontrol filecontrol snapshot sequence wordpress readonly archive sequence'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', true, false));
    $sequence = $state->fileControlSnapshotSequence([
        ['op' => 'size_limit', 'value' => 1048576],
        ['op' => 'reserve_bytes', 'value' => 12],
        ['op' => 'lock_timeout', 'value' => 100],
        ['op' => 'data_version', 'value' => null],
    ]);

    $t->same('ok', $sequence['pairs'][0]['result']['status']);
    $t->same('ignored', $sequence['pairs'][1]['result']['status']);
    $t->same('ok', $sequence['pairs'][2]['result']['status']);
    $t->same(1, $sequence['pairs'][3]['result']['value']);
    $t->same(1048576, $sequence['controls']['size_limit']);
};

$tests['vfs filecontrol filecontrol snapshot sequence dependencies stay lane local'] = static function (TestRunner $t) use ($makeState): void {
    $dependencies = $makeState()->fileControlSnapshotSequence(['size_limit' => 1])['dependencies'];

    $t->same(true, in_array('vfs-file-control-state', $dependencies, true));
    $t->same(true, in_array('vfs-xfilecontrol', $dependencies, true));
    $t->same(true, in_array('vfs-file-control-snapshot-sequence', $dependencies, true));
};

return $tests;
