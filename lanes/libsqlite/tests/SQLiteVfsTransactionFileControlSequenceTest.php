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

$tests['vfs filecontrol transaction filecontrol sequence exposes initial transaction controls'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['sync_count' => 2, 'commit_phase_two_count' => 1, 'atomic_write_generation' => 3])->transactionFileControlSequence([]);

    $t->same('ok', $sequence['status']);
    $t->same(0, $sequence['count']);
    $t->same(false, $sequence['controls']['atomic_write_active']);
    $t->same(3, $sequence['controls']['atomic_write_generation']);
    $t->same(2, $sequence['controls']['sync_count']);
    $t->same(1, $sequence['controls']['commit_phase_two_count']);
    $t->same(true, in_array('vfs-transaction-file-control-sequence', $sequence['dependencies'], true));
};

$atomicSequences = [
    'begin marks atomic write active' => [
        [['op' => 'begin_atomic_write']],
        ['active' => true, 'generation' => 0, 'status' => 'ok', 'changed' => true],
    ],
    'begin twice keeps active state stable' => [
        [['op' => 'begin_atomic_write'], ['op' => 'begin_atomic_write']],
        ['active' => true, 'generation' => 0, 'status' => 'ok', 'changed' => false, 'reason' => 'atomic_write_already_active'],
    ],
    'commit after begin clears active and increments generation' => [
        [['op' => 'begin_atomic_write'], ['op' => 'commit_atomic_write']],
        ['active' => false, 'generation' => 1, 'status' => 'ok', 'changed' => true],
    ],
    'rollback after begin clears active without generation bump' => [
        [['op' => 'begin_atomic_write'], ['op' => 'rollback_atomic_write']],
        ['active' => false, 'generation' => 0, 'status' => 'ok', 'changed' => true],
    ],
    'commit without begin is ignored' => [
        [['op' => 'commit_atomic_write']],
        ['active' => false, 'generation' => 0, 'status' => 'ignored', 'changed' => false, 'reason' => 'atomic_write_not_active'],
    ],
    'rollback without begin is ignored' => [
        [['op' => 'rollback_atomic_write']],
        ['active' => false, 'generation' => 0, 'status' => 'ignored', 'changed' => false, 'reason' => 'atomic_write_not_active'],
    ],
];

foreach ($atomicSequences as $name => [$ops, $expected]) {
    $tests['vfs filecontrol transaction filecontrol sequence atomic ' . $name] = static function (TestRunner $t) use ($makeState, $ops, $expected): void {
        $sequence = $makeState()->transactionFileControlSequence($ops);
        $last = $sequence['pairs'][count($sequence['pairs']) - 1];

        $t->same(count($ops), $sequence['count']);
        $t->same($expected['active'], $last['next']['atomic_write_active']);
        $t->same($expected['generation'], $last['next']['atomic_write_generation']);
        $t->same($expected['status'], $last['result']['status']);
        $t->same($expected['changed'], $last['result']['changed']);
        if (isset($expected['reason'])) {
            $t->same($expected['reason'], $last['result']['reason']);
        }
    };
}

$tests['vfs filecontrol transaction filecontrol sequence commit threads generation into next current'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'begin_atomic_write'],
        ['op' => 'commit_atomic_write'],
        ['op' => 'begin_atomic_write'],
    ]);

    $t->same(0, $sequence['pairs'][0]['current']['atomic_write_generation']);
    $t->same(1, $sequence['pairs'][1]['next']['atomic_write_generation']);
    $t->same(1, $sequence['pairs'][2]['current']['atomic_write_generation']);
    $t->same(true, $sequence['pairs'][2]['next']['atomic_write_active']);
};

$tests['vfs filecontrol transaction filecontrol sequence readonly begin atomic is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', true, false));
    $pair = $state->transactionFileControlSequence([['op' => 'begin_atomic_write']])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('atomic_write_requires_writable_file_handle', $pair['result']['reason']);
    $t->same(false, $pair['next']['atomic_write_active']);
    $t->same(true, in_array('readonly-open', $pair['result']['dependencies'], true));
};

$tests['vfs filecontrol transaction filecontrol sequence memory begin atomic is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file::memory:?mode=memory', false, true));
    $pair = $state->transactionFileControlSequence([['op' => 'begin_atomic_write']])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('atomic_write_requires_writable_file_handle', $pair['result']['reason']);
    $t->same(false, $pair['next']['atomic_write_active']);
    $t->same(true, in_array('memory-open', $pair['result']['dependencies'], true));
};

$syncCases = [
    'default sync records normal flag' => [null, ['normal'], 1, true],
    'full sync records full flag' => ['full', ['full'], 1, true],
    'dataonly sync records dataonly flag' => ['dataonly', ['dataonly'], 1, true],
    'combined string sync deduplicates flags' => ['full|dataonly|full', ['full', 'dataonly'], 1, true],
    'list sync deduplicates flags' => [['normal', 'full', 'normal'], ['normal', 'full'], 1, true],
];

foreach ($syncCases as $name => [$argument, $flags, $count, $changed]) {
    $tests['vfs filecontrol transaction filecontrol sequence ' . $name] = static function (TestRunner $t) use ($makeState, $argument, $flags, $count, $changed): void {
        $pair = $makeState()->transactionFileControlSequence([['op' => 'sync', 'value' => $argument]])['pairs'][0];

        $t->same('ok', $pair['result']['status']);
        $t->same($flags, $pair['next']['last_sync_flags']);
        $t->same($count, $pair['next']['sync_count']);
        $t->same($changed, $pair['result']['changed']);
    };
}

$tests['vfs filecontrol transaction filecontrol sequence repeated same sync increments count'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'sync', 'value' => 'full'],
        ['op' => 'sync', 'value' => 'full'],
    ]);

    $t->same(1, $sequence['pairs'][0]['next']['sync_count']);
    $t->same(2, $sequence['pairs'][1]['next']['sync_count']);
    $t->same(false, $sequence['pairs'][1]['result']['changed']);
    $t->same(['full'], $sequence['controls']['last_sync_flags']);
};

$tests['vfs filecontrol transaction filecontrol sequence readonly sync is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro', true, false));
    $pair = $state->transactionFileControlSequence([['op' => 'sync', 'value' => 'full']])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('sync_requires_writable_file_handle', $pair['result']['reason']);
    $t->same(0, $pair['next']['sync_count']);
};

$tests['vfs filecontrol transaction filecontrol sequence memory sync is ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file::memory:?mode=memory', false, true));
    $pair = $state->transactionFileControlSequence([['op' => 'sync', 'value' => 'full']])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same(0, $pair['next']['sync_count']);
    $t->same(true, in_array('memory-open', $pair['result']['dependencies'], true));
};

$phaseTwoCases = [
    'commit phasetwo increments count' => 'commit_phasetwo',
    'commit phase two alias increments count' => 'commit_phase_two',
];

foreach ($phaseTwoCases as $name => $op) {
    $tests['vfs filecontrol transaction filecontrol sequence ' . $name] = static function (TestRunner $t) use ($makeState, $op): void {
        $sequence = $makeState()->transactionFileControlSequence([
            ['op' => $op],
            ['op' => $op],
        ]);

        $t->same('commit_phasetwo', $sequence['pairs'][0]['op']);
        $t->same(1, $sequence['pairs'][0]['next']['commit_phase_two_count']);
        $t->same(2, $sequence['pairs'][1]['next']['commit_phase_two_count']);
        $t->same(true, $sequence['pairs'][1]['result']['changed']);
    };
}

$tests['vfs filecontrol transaction filecontrol sequence readonly commit phasetwo ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro', true, false));
    $pair = $state->transactionFileControlSequence([['op' => 'commit_phasetwo']])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('commit_phase_two_requires_writable_file_handle', $pair['result']['reason']);
    $t->same(0, $pair['next']['commit_phase_two_count']);
};

$writeHints = [
    'write hint can be set to database growth' => [1048576, 1048576, true],
    'write hint can be reset to null' => [null, null, false],
    'write hint can be zero' => [0, 0, true],
];

foreach ($writeHints as $name => [$argument, $expected, $changed]) {
    $tests['vfs filecontrol transaction filecontrol sequence ' . $name] = static function (TestRunner $t) use ($makeState, $argument, $expected, $changed): void {
        $pair = $makeState()->transactionFileControlSequence([['op' => 'write_hint', 'value' => $argument]])['pairs'][0];

        $t->same('ok', $pair['result']['status']);
        $t->same($expected, $pair['next']['write_hint_bytes']);
        $t->same($changed, $pair['result']['changed']);
    };
}

$tests['vfs filecontrol transaction filecontrol sequence write hint threads into next current'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'write_hint', 'value' => 4096],
        ['op' => 'sync', 'value' => 'normal'],
    ]);

    $t->same(4096, $sequence['pairs'][0]['next']['write_hint_bytes']);
    $t->same(4096, $sequence['pairs'][1]['current']['write_hint_bytes']);
    $t->same(4096, $sequence['controls']['write_hint_bytes']);
};

$tests['vfs filecontrol transaction filecontrol sequence readonly write hint ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro', true, false));
    $pair = $state->transactionFileControlSequence([['op' => 'write_hint', 'value' => 4096]])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('write_hint_requires_writable_file_handle', $pair['result']['reason']);
    $t->same(null, $pair['next']['write_hint_bytes']);
};

$overwritePages = [
    'overwrite records first page' => [3, [3], true],
    'overwrite records root page' => [1, [1], true],
    'overwrite records high page' => [128, [128], true],
];

foreach ($overwritePages as $name => [$page, $expected, $changed]) {
    $tests['vfs filecontrol transaction filecontrol sequence ' . $name] = static function (TestRunner $t) use ($makeState, $page, $expected, $changed): void {
        $pair = $makeState()->transactionFileControlSequence([['op' => 'overwrite', 'value' => $page]])['pairs'][0];

        $t->same('ok', $pair['result']['status']);
        $t->same($expected, $pair['next']['overwrite_pages']);
        $t->same($changed, $pair['result']['changed']);
    };
}

$tests['vfs filecontrol transaction filecontrol sequence overwrite pages sort and deduplicate'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'overwrite', 'value' => 9],
        ['op' => 'overwrite', 'value' => 4],
        ['op' => 'overwrite', 'value' => 9],
    ]);

    $t->same([9], $sequence['pairs'][0]['next']['overwrite_pages']);
    $t->same([4, 9], $sequence['pairs'][1]['next']['overwrite_pages']);
    $t->same([4, 9], $sequence['pairs'][2]['next']['overwrite_pages']);
    $t->same(false, $sequence['pairs'][2]['result']['changed']);
};

$tests['vfs filecontrol transaction filecontrol sequence readonly overwrite ignored'] = static function (TestRunner $t): void {
    $state = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename('file:/srv/www/wp-content/database/archive.sqlite?mode=ro', true, false));
    $pair = $state->transactionFileControlSequence([['op' => 'overwrite', 'value' => 3]])['pairs'][0];

    $t->same('ignored', $pair['result']['status']);
    $t->same('overwrite_requires_writable_file_handle', $pair['result']['reason']);
    $t->same([], $pair['next']['overwrite_pages']);
};

$tests['vfs filecontrol transaction filecontrol sequence mixed pager commit sequence'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'write_hint', 'value' => 12288],
        ['op' => 'begin_atomic_write'],
        ['op' => 'overwrite', 'value' => 2],
        ['op' => 'overwrite', 'value' => 5],
        ['op' => 'sync', 'value' => 'full|dataonly'],
        ['op' => 'commit_atomic_write'],
        ['op' => 'commit_phasetwo'],
    ]);

    $t->same(7, $sequence['count']);
    $t->same(12288, $sequence['controls']['write_hint_bytes']);
    $t->same([2, 5], $sequence['controls']['overwrite_pages']);
    $t->same(['full', 'dataonly'], $sequence['controls']['last_sync_flags']);
    $t->same(1, $sequence['controls']['sync_count']);
    $t->same(1, $sequence['controls']['atomic_write_generation']);
    $t->same(1, $sequence['controls']['commit_phase_two_count']);
    $t->same(false, $sequence['controls']['atomic_write_active']);
};

$tests['vfs filecontrol transaction filecontrol sequence mixed pager rollback sequence'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'begin_atomic_write'],
        ['op' => 'overwrite', 'value' => 8],
        ['op' => 'sync', 'value' => 'normal'],
        ['op' => 'rollback_atomic_write'],
    ]);

    $t->same(4, $sequence['count']);
    $t->same([8], $sequence['controls']['overwrite_pages']);
    $t->same(1, $sequence['controls']['sync_count']);
    $t->same(0, $sequence['controls']['atomic_write_generation']);
    $t->same(false, $sequence['controls']['atomic_write_active']);
};

$tests['vfs filecontrol transaction filecontrol sequence keeps existing filecontrol snapshot sequence controls'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['size_limit' => 1024, 'reserve_bytes' => 4, 'lock_timeout' => 25])->transactionFileControlSequence([
        ['op' => 'begin_atomic_write'],
    ]);
    $pair = $sequence['pairs'][0];

    $t->same(1024, $pair['current']['size_limit']);
    $t->same(4, $pair['current']['reserve_bytes']);
    $t->same(25, $pair['current']['lock_timeout']);
    $t->same(1024, $pair['next']['size_limit']);
    $t->same(4, $pair['next']['reserve_bytes']);
    $t->same(25, $pair['next']['lock_timeout']);
};

$tests['vfs filecontrol transaction filecontrol sequence normalizes dash ops'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'begin-atomic-write'],
        ['op' => 'commit-atomic-write'],
        ['op' => 'write-hint', 'value' => 512],
    ]);

    $t->same('begin_atomic_write', $sequence['pairs'][0]['op']);
    $t->same('commit_atomic_write', $sequence['pairs'][1]['op']);
    $t->same('write_hint', $sequence['pairs'][2]['op']);
    $t->same(512, $sequence['controls']['write_hint_bytes']);
};

$tests['vfs filecontrol transaction filecontrol sequence counts notfound without state change'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState()->transactionFileControlSequence([
        ['op' => 'not_a_file_control', 'value' => 1],
    ]);

    $t->same(1, $sequence['count']);
    $t->same('notfound', $sequence['pairs'][0]['result']['status']);
    $t->same(false, $sequence['pairs'][0]['next']['atomic_write_active']);
    $t->same(0, $sequence['pairs'][0]['next']['sync_count']);
};

$errorCases = [
    'rejects invalid sync scalar' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['op' => 'sync', 'value' => 7]]),
    'rejects invalid sync flag' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['op' => 'sync', 'value' => 'invalid']]),
    'rejects negative write hint' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['op' => 'write_hint', 'value' => -1]]),
    'rejects zero overwrite page' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['op' => 'overwrite', 'value' => 0]]),
    'rejects negative overwrite page' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['op' => 'overwrite', 'value' => -4]]),
    'rejects non integer overwrite page' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['op' => 'overwrite', 'value' => '4']]),
    'rejects malformed transaction filecontrol sequence item' => static fn (SQLiteVfsFileControlState $state) => $state->transactionFileControlSequence([['value' => 1]]),
];

foreach ($errorCases as $name => $callback) {
    $tests['vfs filecontrol transaction filecontrol sequence ' . $name] = static function (TestRunner $t) use ($makeState, $callback): void {
        $t->throws(InvalidArgumentException::class, static fn () => $callback($makeState()));
    };
}

$constructorErrors = [
    'constructor rejects negative atomic generation' => ['atomic_write_generation' => -1],
    'constructor rejects negative sync count' => ['sync_count' => -1],
    'constructor rejects negative phase two count' => ['commit_phase_two_count' => -1],
    'constructor rejects negative write hint bytes' => ['write_hint_bytes' => -1],
];

foreach ($constructorErrors as $name => $controls) {
    $tests['vfs filecontrol transaction filecontrol sequence ' . $name] = static function (TestRunner $t) use ($makeState, $controls): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeState($controls));
    };
}

$tests['vfs filecontrol transaction filecontrol sequence constructor accepts initial write hint'] = static function (TestRunner $t) use ($makeState): void {
    $snapshot = $makeState(['write_hint_bytes' => 8192])->snapshot();

    $t->same(8192, $snapshot['controls']['write_hint_bytes']);
};

$tests['vfs filecontrol transaction filecontrol sequence constructor accepts initial counters'] = static function (TestRunner $t) use ($makeState): void {
    $snapshot = $makeState(['atomic_write_generation' => 4, 'sync_count' => 5, 'commit_phase_two_count' => 6])->snapshot();

    $t->same(4, $snapshot['controls']['atomic_write_generation']);
    $t->same(5, $snapshot['controls']['sync_count']);
    $t->same(6, $snapshot['controls']['commit_phase_two_count']);
};

$tests['vfs filecontrol transaction filecontrol sequence wordpress rollback journal import summary'] = static function (TestRunner $t) use ($makeState): void {
    $sequence = $makeState(['size_limit' => 8388608])->transactionFileControlSequence([
        ['op' => 'write_hint', 'value' => 16384],
        ['op' => 'begin_atomic_write'],
        ['op' => 'overwrite', 'value' => 1],
        ['op' => 'overwrite', 'value' => 4],
        ['op' => 'sync', 'value' => 'full'],
        ['op' => 'commit_atomic_write'],
        ['op' => 'commit_phase_two'],
    ]);

    $t->same(7, $sequence['count']);
    $t->same(16384, $sequence['controls']['write_hint_bytes']);
    $t->same([1, 4], $sequence['controls']['overwrite_pages']);
    $t->same(['full'], $sequence['controls']['last_sync_flags']);
    $t->same(1, $sequence['controls']['atomic_write_generation']);
    $t->same(1, $sequence['controls']['commit_phase_two_count']);
};

$tests['vfs filecontrol transaction filecontrol sequence dependencies stay lane local'] = static function (TestRunner $t) use ($makeState): void {
    $dependencies = $makeState()->transactionFileControlSequence([['op' => 'begin_atomic_write']])['dependencies'];

    $t->same(true, in_array('vfs-file-control-state', $dependencies, true));
    $t->same(true, in_array('vfs-xfilecontrol', $dependencies, true));
    $t->same(true, in_array('vfs-transaction-file-control-sequence', $dependencies, true));
};

return $tests;
