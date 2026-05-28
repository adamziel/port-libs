<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCapabilityPlan;
use PortLibs\LibSqlite\SQLiteVfsFileControlState;

$tests = [];

$makeState = static function (
    array $fileControls = [],
    string $filename = 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix'
): SQLiteVfsFileControlState {
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

$sqlCases = [
    'pragma mmap size equals' => ['PRAGMA mmap_size=65536', 'mmap_size', 65536, 65536, 'ok'],
    'pragma mmap size parenthesized' => ['PRAGMA mmap_size(32768)', 'mmap_size', 32768, 32768, 'ok'],
    'pragma main mmap size' => ['PRAGMA main.mmap_size=16384', 'mmap_size', 16384, 16384, 'ok'],
    'pragma chunk size equals' => ['PRAGMA chunk_size=8192', 'chunk_size', 8192, 8192, 'ok'],
    'pragma chunk size parenthesized' => ['PRAGMA chunk_size(4096)', 'chunk_size', 4096, 4096, 'ok'],
    'pragma max page count aliases size limit' => ['PRAGMA max_page_count=512', 'size_limit', 512, 512, 'ok'],
    'pragma journal size limit aliases size limit' => ['PRAGMA journal_size_limit=1048576', 'size_limit', 1048576, 1048576, 'ok'],
    'pragma reserve bytes equals' => ['PRAGMA reserve_bytes=24', 'reserve_bytes', 24, 24, 'ok'],
    'pragma lock timeout equals' => ['PRAGMA lock_timeout=2500', 'lock_timeout', 2500, 2500, 'ok'],
    'pragma busy timeout aliases lock timeout' => ['PRAGMA busy_timeout=1200', 'lock_timeout', 1200, 1200, 'ok'],
    'pragma data version query' => ['PRAGMA data_version', 'data_version', 1, 1, 'ok'],
    'file control size hint' => ['file_control(size_hint, 32768)', 'size_hint', 32768, null, 'ok'],
    'file control persist wal string truth' => ['file_control(persist_wal, on)', 'persist_wal', true, true, 'ok'],
    'file control powersafe overwrite false' => ['file_control(powersafe_overwrite, 0)', 'powersafe_overwrite', false, false, 'ok'],
    'file control temp filename suffix' => ['file_control(tempfilename, journal)', 'tempfilename', '.journal', null, 'ok'],
];

foreach ($sqlCases as $name => [$sql, $op, $expectedValue, $expectedControl, $status]) {
    $tests['vfs filecontrol current next69 parses ' . $name] = static function (TestRunner $t) use ($makeState, $sql, $op, $expectedValue, $expectedControl, $status): void {
        $sequence = $makeState()->currentNext69([$sql]);
        $pair = $sequence['pairs'][0];

        $t->same(1, $sequence['count']);
        $t->same($op, $pair['op']);
        $t->same($status, $pair['result']['status']);
        if ($op === 'tempfilename') {
            $t->same(true, str_ends_with($pair['result']['value'], $expectedValue));
        } else {
            $t->same($expectedValue, $pair['result']['value']);
        }
        if ($expectedControl !== null) {
            $t->same($expectedControl, $sequence['controls'][$op]);
            $t->same($expectedControl, $pair['next'][$op]);
        }
    };
}

$sequenceCases = [
    'threads sql mmap into next current' => [
        ['PRAGMA mmap_size=4096', 'PRAGMA chunk_size=8192'],
        'mmap_size',
        4096,
        1,
    ],
    'threads sql size limit into size hint guard' => [
        ['PRAGMA max_page_count=1024', 'file_control(size_hint, 2048)'],
        'size_limit',
        1024,
        1,
    ],
    'threads reserve bytes into following current' => [
        ['PRAGMA reserve_bytes=11', 'PRAGMA busy_timeout=10'],
        'reserve_bytes',
        11,
        1,
    ],
    'threads lock timeout into following current' => [
        ['PRAGMA busy_timeout=99', 'PRAGMA data_version'],
        'lock_timeout',
        99,
        1,
    ],
];

foreach ($sequenceCases as $name => [$controls, $field, $expected, $pairIndex]) {
    $tests['vfs filecontrol current next69 ' . $name] = static function (TestRunner $t) use ($makeState, $controls, $field, $expected, $pairIndex): void {
        $sequence = $makeState()->currentNext69($controls);

        $t->same($expected, $sequence['pairs'][$pairIndex]['current'][$field]);
        $t->same($expected, $sequence['controls'][$field]);
    };
}

$tests['vfs filecontrol current next69 reports applied ignored notfound changed counts'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['size_limit' => 1024])->currentNext69([
        'PRAGMA mmap_size=4096',
        'file_control(size_hint, 4096)',
        'PRAGMA unknown_control=1',
    ]);

    $t->same(3, $sequence['count']);
    $t->same(1, $sequence['applied']);
    $t->same(1, $sequence['ignored']);
    $t->same(1, $sequence['notfound']);
    $t->same(1, $sequence['changed']);
    $t->same('size_hint_exceeds_size_limit', $sequence['pairs'][1]['result']['reason']);
};

$tests['vfs filecontrol current next69 size hint within limit remains ok'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['size_limit' => 8192])->currentNext69(['file_control(size_hint, 4096)'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(4096, $pair['result']['value']);
    $t->same('caller_may_preallocate_file', $pair['result']['reason']);
};

$tests['vfs filecontrol current next69 size hint over limit is ignored'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['size_limit' => 4096])->currentNext69(['file_control(size_hint, 4097)'])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same(4097, $pair['result']['value']);
    $t->same('size_hint_exceeds_size_limit', $pair['result']['reason']);
};

$tests['vfs filecontrol current next69 zero size limit blocks nonzero hint'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['size_limit' => 0])->currentNext69(['file_control(size_hint, 1)']);

    $t->same('ignored', $sequence['pairs'][0]['result']['status']);
    $t->same(0, $sequence['pairs'][0]['current']['size_limit']);
};

$tests['vfs filecontrol current next69 zero size hint survives zero limit'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['size_limit' => 0])->currentNext69(['file_control(size_hint, 0)'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(0, $pair['result']['value']);
};

$tests['vfs filecontrol current next69 absent size limit permits large hint'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(['file_control(size_hint, 10485760)'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(10485760, $pair['result']['value']);
};

$tests['vfs filecontrol current next69 read only size hint remains read only ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', true, false));
    $pair = $state->currentNext69(['file_control(size_hint, 1024)'])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('size_hint_requires_writable_file_handle', $pair['result']['reason']);
};

$tests['vfs filecontrol current next69 immutable mmap is ignored and counted'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', true, false));
    $sequence = $state->currentNext69(['PRAGMA mmap_size=65536']);

    $t->same(0, $sequence['pairs'][0]['result']['value']);
    $t->same('ignored', $sequence['pairs'][0]['result']['status']);
    $t->same(1, $sequence['ignored']);
};

$tests['vfs filecontrol current next69 nolock mmap is ignored and counted'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/nolock.sqlite?nolock=1', true, true));
    $sequence = $state->currentNext69(['PRAGMA mmap_size=65536']);

    $t->same(0, $sequence['pairs'][0]['result']['value']);
    $t->same('ignored', $sequence['pairs'][0]['result']['status']);
    $t->same(true, in_array('nolock-open', $sequence['dependencies'], true));
};

$tests['vfs filecontrol current next69 memory chunk size is ignored and counted'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file::memory:?mode=memory', false, true));
    $sequence = $state->currentNext69(['PRAGMA chunk_size=8192']);

    $t->same('ignored', $sequence['pairs'][0]['result']['status']);
    $t->same(1, $sequence['ignored']);
    $t->same(true, in_array('memory-open', $sequence['dependencies'], true));
};

$tests['vfs filecontrol current next69 quoted file control name hint'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->currentNext69(["file_control(name_hint, 'wp''s import')"]);

    $t->same("wp's import", $sequence['pairs'][0]['result']['value']);
    $t->same("wp's import", $sequence['controls']['name_hint']);
};

$tests['vfs filecontrol current next69 double quoted file control name hint'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->currentNext69(['file_control(name_hint, "wp import")']);

    $t->same('wp import', $sequence['pairs'][0]['result']['value']);
    $t->same('wp import', $sequence['controls']['name_hint']);
};

$tests['vfs filecontrol current next69 hex pragma value is accepted'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(['PRAGMA mmap_size=0x1000'])['pairs'][0];

    $t->same(4096, $pair['result']['value']);
    $t->same(4096, $pair['next']['mmap_size']);
};

$tests['vfs filecontrol current next69 source preserves sql text'] = static function (TestRunner $t) use ($makeState): void {
    $sql = 'PRAGMA busy_timeout=75';
    $pair = $makeState()->currentNext69([$sql])['pairs'][0];

    $t->same($sql, $pair['source']);
};

$tests['vfs filecontrol current next69 source preserves keyed control'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(['lock_timeout' => 75])['pairs'][0];

    $t->same(['lock_timeout' => 75], $pair['source']);
};

$tests['vfs filecontrol current next69 source preserves array control'] = static function (TestRunner $t) use ($makeState): void {
    $source = ['op' => 'reserve_bytes', 'value' => 5];
    $pair = $makeState()->currentNext69([$source])['pairs'][0];

    $t->same($source, $pair['source']);
};

$tests['vfs filecontrol current next69 snapshots include name hint and device info'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['sector_size' => 4096, 'device_characteristics' => 17])->currentNext69(["file_control(name_hint, 'wp import')"])['pairs'][0];

    $t->same(null, $pair['current']['name_hint']);
    $t->same('wp import', $pair['next']['name_hint']);
    $t->same(4096, $pair['next']['sector_size']);
    $t->same(17, $pair['next']['device_characteristics']);
};

$tests['vfs filecontrol current next69 temp filename uses prior name hint'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->currentNext69([
        "file_control(name_hint, 'wp import')",
        'file_control(tempfilename, journal)',
    ]);

    $t->same('wp import', $sequence['pairs'][1]['current']['name_hint']);
    $t->same(true, str_ends_with($sequence['pairs'][1]['result']['value'], '.journal'));
};

$tests['vfs filecontrol current next69 unsupported pragma is notfound'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(['PRAGMA cache_size=2000'])['pairs'][0];

    $t->same('notfound', $pair['result']['status']);
    $t->same('cache_size', $pair['op']);
};

$tests['vfs filecontrol current next69 unsupported file control is notfound'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(['file_control(unknown_control, 1)'])['pairs'][0];

    $t->same('notfound', $pair['result']['status']);
    $t->same('unknown_control', $pair['op']);
};

$tests['vfs filecontrol current next69 pragma max page count query reads limit'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['size_limit' => 2048])->currentNext69(['PRAGMA max_page_count'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(2048, $pair['result']['value']);
    $t->same('current_size_limit_returned', $pair['result']['reason']);
};

$tests['vfs filecontrol current next69 pragma journal size limit query reads limit'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['size_limit' => 4096])->currentNext69(['PRAGMA journal_size_limit'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(4096, $pair['result']['value']);
    $t->same(4096, $pair['next']['size_limit']);
};

$tests['vfs filecontrol current next69 pragma reserve bytes query reads current'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState(['reserve_bytes' => 13])->currentNext69(['PRAGMA reserve_bytes'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(13, $pair['result']['value']);
    $t->same('current_reserve_bytes_returned', $pair['result']['reason']);
};

$tests['vfs filecontrol current next69 file control has moved sql text detects same path'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(["file_control(has_moved, '/srv/www/wp-content/database/.ht.sqlite')"])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(false, $pair['result']['value']);
    $t->same(false, $pair['next']['has_moved']);
};

$tests['vfs filecontrol current next69 file control has moved sql text detects rename'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(["file_control(has_moved, '/srv/www/wp-content/database/renamed.sqlite')"])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(true, $pair['result']['value']);
    $t->same(true, $pair['next']['has_moved']);
};

$tests['vfs filecontrol current next69 tempfile query reports current handle kind'] = static function (TestRunner $t) use ($makeState): void {
    $pair = $makeState()->currentNext69(['file_control(tempfile)'])['pairs'][0];

    $t->same('ok', $pair['result']['status']);
    $t->same(false, $pair['result']['value']);
    $t->same(false, $pair['next']['tempfile']);
};

$tests['vfs filecontrol current next69 dependencies are distinct from next64'] = static function (TestRunner $t) use ($makeState): void {
    $dependencies = $makeState()->currentNext69(['PRAGMA data_version'])['dependencies'];

    $t->same(true, in_array('vfs-file-control-current-next69', $dependencies, true));
    $t->same(false, in_array('vfs-file-control-current-next64', $dependencies, true));
};

$tests['vfs filecontrol current next69 rejects empty sql control'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->currentNext69(['  ; ']));
};

$tests['vfs filecontrol current next69 rejects unsupported sql shape'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->currentNext69(['SELECT 1']));
};

$tests['vfs filecontrol current next69 rejects array without op'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->currentNext69([['value' => 1]]));
};

$tests['vfs filecontrol current next69 rejects negative pragma value through control'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->currentNext69(['PRAGMA mmap_size=-1']));
};

$tests['vfs filecontrol current next69 rejects invalid quoted boolean'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->currentNext69(["file_control(persist_wal, 'maybe')"]));
};

$tests['vfs filecontrol current next69 rejects name hint nul'] = static function (TestRunner $t) use ($makeState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeState()->currentNext69([['op' => 'name_hint', 'value' => "bad\0hint"]]));
};

$tests['vfs filecontrol current next69 wordpress import sequence'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['size_limit' => 8388608, 'data_version' => 19])->currentNext69([
        "file_control(name_hint, 'wp-options-import')",
        'PRAGMA busy_timeout=2500',
        'PRAGMA reserve_bytes=32',
        'PRAGMA mmap_size=262144',
        'file_control(size_hint, 4194304)',
        'PRAGMA data_version',
    ]);

    $t->same(6, $sequence['count']);
    $t->same(6, $sequence['applied']);
    $t->same(0, $sequence['ignored']);
    $t->same(4, $sequence['changed']);
    $t->same(2500, $sequence['controls']['lock_timeout']);
    $t->same(32, $sequence['controls']['reserve_bytes']);
    $t->same(262144, $sequence['controls']['mmap_size']);
    $t->same(19, $sequence['pairs'][5]['result']['value']);
};

$tests['vfs filecontrol current next69 wordpress import cap blocks oversized preallocation'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['size_limit' => 1048576])->currentNext69([
        "file_control(name_hint, 'wp-options-import')",
        'file_control(size_hint, 4194304)',
    ]);

    $t->same(1, $sequence['ignored']);
    $t->same('size_hint_exceeds_size_limit', $sequence['pairs'][1]['result']['reason']);
    $t->same(1048576, $sequence['pairs'][1]['current']['size_limit']);
};

$tests['vfs filecontrol current next69 wordpress readonly archive summary'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', true, false));
    $sequence = $state->currentNext69([
        'PRAGMA mmap_size=262144',
        'PRAGMA busy_timeout=500',
        'file_control(size_hint, 4096)',
        'PRAGMA data_version',
    ]);

    $t->same(2, $sequence['applied']);
    $t->same(2, $sequence['ignored']);
    $t->same(0, $sequence['notfound']);
    $t->same(500, $sequence['controls']['lock_timeout']);
    $t->same(1, $sequence['pairs'][3]['result']['value']);
};

return $tests;
