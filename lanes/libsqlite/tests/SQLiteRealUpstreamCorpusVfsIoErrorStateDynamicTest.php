<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$ioerr5Cases = [
    'ioerr5-1 normal failpoint 1 keeps dirty page out of database during utf16 compile' => [
        ['name' => 'ioerr5-1', 'locking_mode' => 'normal', 'read_cursor_open' => true],
        1,
        'sync',
        'SQLITE_IOERR_FSYNC',
        'compile_utf16_after_pager_error_does_not_spill_dirty_page',
    ],
    'ioerr5-1 exclusive failpoint 37 keeps dirty page out of database during utf16 compile' => [
        ['name' => 'ioerr5-1', 'locking_mode' => 'exclusive', 'read_cursor_open' => true],
        37,
        'write',
        'SQLITE_IOERR',
        'compile_utf16_after_pager_error_does_not_spill_dirty_page',
    ],
    'ioerr5-2 normal release memory preserves pager error state' => [
        ['name' => 'ioerr5-2', 'locking_mode' => 'normal', 'release_memory' => true],
        9,
        'write',
        'SQLITE_IOERR',
        'release_memory_from_error_state_preserves_dirty_page_until_rollback',
    ],
    'ioerr5-2 exclusive release memory preserves pager error state' => [
        ['name' => 'ioerr5-2', 'locking_mode' => 'exclusive', 'release_memory' => true],
        117,
        'sync',
        'SQLITE_IOERR_FSYNC',
        'release_memory_from_error_state_preserves_dirty_page_until_rollback',
    ],
];

foreach ($ioerr5Cases as $name => [$scenario, $failpoint, $operation, $expectedRc, $recovery]) {
    $tests['real upstream corpus vfs io error state dynamic ' . $name] = static function (TestRunner $t) use ($scenario, $failpoint, $operation, $expectedRc, $recovery): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome($scenario, $failpoint, $operation);

        $t->same('ok', $plan['status']);
        $t->same('ioerr5.test', $plan['script']);
        $t->same($scenario['name'], $plan['scenario']);
        $t->same($scenario['locking_mode'], $plan['locking_mode']);
        $t->same($failpoint, $plan['failpoint']);
        $t->same($operation, $plan['operation']);
        $t->same($expectedRc, $plan['expected_rc']);
        $t->same(true, $plan['persistent_error']);
        $t->same(true, $plan['shared_cache']);
        $t->same($scenario['name'] === 'ioerr5-1', $plan['read_cursor_open']);
        $t->same($scenario['name'] === 'ioerr5-2', $plan['release_memory_requested']);
        $t->same(1048576, $plan['soft_heap_limit_before']);
        $t->same(1024, $plan['soft_heap_limit_after']);
        $t->same(true, $plan['pager_error_state']);
        $t->same(true, $plan['dirty_pages_spill_blocked']);
        $t->same(true, $plan['database_image_stable']);
        $t->same(0, $plan['open_file_count']);
        $t->same('ok', $plan['integrity_check']);
        $t->same($recovery, $plan['recovery_action']);
        $t->same(true, in_array('pager-error-state-recovery', $plan['dependencies'], true));
        $t->same(true, in_array('real-upstream-corpus-ioerr-test', $plan['dependencies'], true));
        $t->same(true, str_starts_with($plan['upstream'][0], 'ioerr5.test ' . $scenario['name']));
    };
}

$ioerr6Cases = [
    'ioerr6-1 atomic first write full rolls back insert' => [
        ['name' => 'ioerr6-1', 'locking_mode' => 'normal'],
        1,
        'full',
        'atomic_write_full_error_rolls_back_single_statement',
    ],
    'ioerr6-2 atomic primary key setup survives full fault failpoint 2' => [
        ['name' => 'ioerr6-2', 'locking_mode' => 'normal'],
        2,
        'full',
        'atomic_write_full_error_preserves_primary_key_integrity',
    ],
    'ioerr6-3 atomic schema setup survives full fault failpoint 3' => [
        ['name' => 'ioerr6-3', 'locking_mode' => 'exclusive'],
        3,
        'full',
        'atomic_write_full_error_allows_followup_schema_change',
    ],
];

foreach ($ioerr6Cases as $name => [$scenario, $failpoint, $operation, $recovery]) {
    $tests['real upstream corpus vfs io error state dynamic ' . $name] = static function (TestRunner $t) use ($scenario, $failpoint, $operation, $recovery): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome($scenario, $failpoint, $operation);

        $t->same('ok', $plan['status']);
        $t->same('ioerr6.test', $plan['script']);
        $t->same($scenario['name'], $plan['scenario']);
        $t->same($scenario['locking_mode'], $plan['locking_mode']);
        $t->same($failpoint, $plan['failpoint']);
        $t->same('full', $plan['operation']);
        $t->same('SQLITE_FULL', $plan['expected_rc']);
        $t->same(false, $plan['persistent_error']);
        $t->same(false, $plan['shared_cache']);
        $t->same(false, $plan['read_cursor_open']);
        $t->same(false, $plan['release_memory_requested']);
        $t->same(1048576, $plan['soft_heap_limit_before']);
        $t->same(1048576, $plan['soft_heap_limit_after']);
        $t->same(false, $plan['pager_error_state']);
        $t->same(false, $plan['dirty_pages_spill_blocked']);
        $t->same(true, $plan['database_image_stable']);
        $t->same(0, $plan['open_file_count']);
        $t->same('ok', $plan['integrity_check']);
        $t->same($recovery, $plan['recovery_action']);
        $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
        $t->same(true, str_starts_with($plan['upstream'][0], 'ioerr6.test ' . $scenario['name']));
    };
}

$matrixOrdinal = 0;
foreach (['normal', 'exclusive'] as $lockingMode) {
    foreach (['ioerr5-1', 'ioerr5-2'] as $scenarioName) {
        foreach ([1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 199] as $failpoint) {
            $matrixOrdinal++;
            $tests["real upstream corpus vfs io error state dynamic ioerr5 matrix {$matrixOrdinal} {$scenarioName} {$lockingMode} failpoint {$failpoint}"] = static function (TestRunner $t) use ($scenarioName, $lockingMode, $failpoint): void {
                $operation = $failpoint % 2 === 0 ? 'write' : 'sync';
                $plan = SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome([
                    'name' => $scenarioName,
                    'locking_mode' => $lockingMode,
                    'read_cursor_open' => $scenarioName === 'ioerr5-1',
                    'release_memory' => $scenarioName === 'ioerr5-2',
                ], $failpoint, $operation);

                $t->same('ioerr5.test', $plan['script']);
                $t->same($scenarioName, $plan['scenario']);
                $t->same($lockingMode, $plan['locking_mode']);
                $t->same($failpoint, $plan['failpoint']);
                $t->same($operation, $plan['operation']);
                $t->same($operation === 'sync' ? 'SQLITE_IOERR_FSYNC' : 'SQLITE_IOERR', $plan['expected_rc']);
                $t->same(true, $plan['persistent_error']);
                $t->same(true, $plan['database_image_stable']);
                $t->same(true, $plan['dirty_pages_spill_blocked']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(true, in_array('vfs-io-error-injection', $plan['dependencies'], true));
                $t->same(true, str_contains($plan['upstream'][0], $scenarioName));
            };
        }
    }
}

foreach ([1, 2, 3] as $scenarioNumber) {
    foreach ([1, 2, 4, 8, 16, 32] as $failpoint) {
        $tests["real upstream corpus vfs io error state dynamic ioerr6 matrix {$scenarioNumber} failpoint {$failpoint}"] = static function (TestRunner $t) use ($scenarioNumber, $failpoint): void {
            $scenarioName = 'ioerr6-' . $scenarioNumber;
            $plan = SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome([
                'name' => $scenarioName,
                'locking_mode' => $scenarioNumber === 3 ? 'exclusive' : 'normal',
            ], $failpoint, 'full');

            $t->same('ioerr6.test', $plan['script']);
            $t->same($scenarioName, $plan['scenario']);
            $t->same($failpoint, $plan['failpoint']);
            $t->same('SQLITE_FULL', $plan['expected_rc']);
            $t->same(false, $plan['pager_error_state']);
            $t->same(true, $plan['database_image_stable']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(0, $plan['open_file_count']);
            $t->same(true, str_contains($plan['upstream'][0], $scenarioName));
        };
    }
}

$guardCases = [
    'rejects missing scenario name' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome([], 1),
    'rejects unsupported scenario' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome(['name' => 'ioerr4-1'], 1),
    'rejects zero failpoint' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome(['name' => 'ioerr5-1'], 0),
    'rejects unsupported operation' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome(['name' => 'ioerr5-1'], 1, 'rename'),
    'rejects unsupported locking mode' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::pagerErrorStateMemoryReclaimOutcome(['name' => 'ioerr5-1', 'locking_mode' => 'shared'], 1),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs io error state dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
