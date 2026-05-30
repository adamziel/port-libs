<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    [
        'scenario' => 'journal2-1.1',
        'mode' => 'delete',
        'operation' => 'create-table',
        'handles' => 0,
        'dirty_pages' => 1,
        'expected_oplog' => ['xOpen', 'xClose', 'xDelete'],
        'journal_exists' => false,
        'rows' => 6,
        'reason' => 'delete_mode_closes_and_deletes_rollback_journal',
    ],
    [
        'scenario' => 'journal2-1.2-1.4',
        'mode' => 'truncate',
        'operation' => 'insert',
        'handles' => 0,
        'dirty_pages' => 1,
        'expected_oplog' => [],
        'journal_exists' => true,
        'rows' => 6,
        'reason' => 'truncate_mode_reuses_open_journal_without_delete',
    ],
    [
        'scenario' => 'journal2-1.5-1.9',
        'mode' => 'delete',
        'operation' => 'second-connection-delete',
        'handles' => 1,
        'dirty_pages' => 2,
        'expected_oplog' => ['xOpen', 'xClose', 'xDelete'],
        'journal_exists' => true,
        'rows' => 4,
        'reason' => 'safe_delete_vfs_refuses_journal_delete_while_handle_is_open',
    ],
    [
        'scenario' => 'journal2-1.10-1.21',
        'mode' => 'delete',
        'operation' => 'large-commit',
        'handles' => 0,
        'dirty_pages' => 128,
        'expected_oplog' => ['xOpen', 'xClose', 'xDelete'],
        'journal_exists' => true,
        'rows' => 4,
        'reason' => 'write_truncate_delete_fault_leaves_hot_journal_for_recovery',
    ],
    [
        'scenario' => 'journal2-2.1-2.4',
        'mode' => 'persist',
        'operation' => 'switch-to-wal',
        'handles' => 0,
        'dirty_pages' => 1,
        'expected_oplog' => ['xOpen', 'xClose', 'xDelete'],
        'journal_exists' => false,
        'rows' => 6,
        'reason' => 'wal_transition_closes_and_deletes_persistent_rollback_journal',
    ],
];

$case = 0;
foreach ($scenarios as $scenario) {
    for ($variant = 1; $variant <= 200; $variant++) {
        $case++;
        $tests[sprintf(
            'real upstream corpus vfs journal2 safe-delete dynamic %04d %s variant %03d',
            $case,
            $scenario['scenario'],
            $variant
        )] = static function (TestRunner $t) use ($scenario, $variant): void {
            $dirtyPages = (int) $scenario['dirty_pages'] + ($scenario['operation'] === 'large-commit' ? $variant : $variant % 3);
            $handles = (int) $scenario['handles'];
            if ($scenario['operation'] === 'second-connection-delete') {
                $handles = 1 + ($variant % 4);
            }

            $plan = SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle(
                $scenario['scenario'],
                $scenario['mode'],
                $scenario['operation'],
                $handles,
                $dirtyPages,
                true
            );

            $ioError = in_array($scenario['operation'], ['second-connection-delete', 'large-commit'], true);

            $t->same($ioError ? 'ioerr' : 'ok', $plan['status']);
            $t->same('journal2.test', $plan['script']);
            $t->same($scenario['scenario'], $plan['scenario']);
            $t->same($scenario['mode'], $plan['journal_mode']);
            $t->same($scenario['operation'], $plan['operation']);
            $t->same(['undeletable_when_open', 'powersafe_overwrite'], $plan['device_flags']);
            $t->same($handles, $plan['open_journal_handles']);
            $t->same($dirtyPages, $plan['dirty_pages']);
            $t->same($scenario['expected_oplog'], $plan['oplog']);
            $t->same($scenario['expected_oplog'] !== [], $plan['journal_opened']);
            $t->same(in_array('xClose', $scenario['expected_oplog'], true), $plan['journal_closed']);
            $t->same(in_array('xDelete', $scenario['expected_oplog'], true), $plan['delete_attempted']);
            $t->same($scenario['operation'] === 'second-connection-delete', $plan['delete_blocked_by_open_handle']);
            $t->same($ioError ? 'SQLITE_IOERR' : 'SQLITE_OK', $plan['expected_rc']);
            $t->same($ioError ? 'disk I/O error' : 'ok', $plan['message']);
            $t->same($scenario['journal_exists'], $plan['journal_file_exists_after_operation']);
            $t->same($ioError, $plan['hot_journal_left']);
            $t->same($scenario['rows'], $plan['database_rows_visible']);
            $t->same($scenario['operation'] === 'large-commit' ? 'not ok' : 'ok', $plan['pre_recovery_copy_integrity']);
            $t->same($ioError ? 'ok_after_hot_journal_rollback' : 'ok', $plan['post_recovery_integrity']);
            $t->same($scenario['operation'] === 'switch-to-wal', $plan['wal_switch_deletes_journal']);
            $t->same($scenario['reason'], $plan['reason']);
            $t->same(true, in_array('sqlite-upstream-journal2-test', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-safe-delete', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-rollback-journal-lifecycle', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-hot-journal-recovery', $plan['dependencies'], true));
            $t->same(true, count($plan['upstream']) >= 1);
        };
    }
}

$tests['real upstream corpus vfs journal2 safe-delete records source sections'] = static function (TestRunner $t) use ($scenarios, $case): void {
    $t->same(5, count($scenarios));
    $t->same(1000, $case);
    $t->same([
        'journal2-1.1',
        'journal2-1.2-1.4',
        'journal2-1.5-1.9',
        'journal2-1.10-1.21',
        'journal2-2.1-2.4',
    ], array_column($scenarios, 'scenario'));
};

$tests['real upstream corpus vfs journal2 safe-delete rejects malformed requests'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('', 'delete', 'insert', 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'memory', 'insert', 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'delete', 'checkpoint', 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'delete', 'insert', -1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-9', 'delete', 'insert', 0, 1));
};

return $tests;
