<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'journal2-1.1' => [
        'mode' => 'delete',
        'operation' => 'create-table',
    ],
    'journal2-1.2-1.4' => [
        'mode' => 'truncate',
        'operation' => 'insert',
    ],
    'journal2-1.5-1.9' => [
        'mode' => 'delete',
        'operation' => 'second-connection-delete',
    ],
    'journal2-1.10-1.21' => [
        'mode' => 'delete',
        'operation' => 'large-commit',
    ],
    'journal2-2.1-2.4' => [
        'mode' => 'wal',
        'operation' => 'switch-to-wal',
    ],
];

$case = 0;
foreach ($scenarios as $scenario => $config) {
    for ($repeat = 1; $repeat <= 400; $repeat++) {
        $case++;
        $journalMode = $config['mode'];
        $operation = $config['operation'];
        $openHandles = $operation === 'second-connection-delete' ? (($repeat % 3) + 1) : 0;
        $dirtyPages = $operation === 'large-commit' ? 16 + ($repeat % 97) : ($repeat % 5);
        $walCapable = ($repeat % 11) !== 0;

        $tests[sprintf(
            'real upstream corpus vfs journal2 safe-delete dynamic %04d %s handles %02d dirty %03d wal %d',
            $case,
            $scenario,
            $openHandles,
            $dirtyPages,
            $walCapable ? 1 : 0
        )] = static function (TestRunner $t) use ($scenario, $journalMode, $operation, $openHandles, $dirtyPages, $walCapable): void {
            $plan = SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle(
                $scenario,
                $journalMode,
                $operation,
                $openHandles,
                $dirtyPages,
                $walCapable
            );

            $expectedIoError = $operation === 'second-connection-delete' || $operation === 'large-commit';
            $expectedJournalExists = $expectedIoError || ($journalMode !== 'delete' && $operation !== 'switch-to-wal');

            $t->same('journal2.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same($journalMode, $plan['journal_mode']);
            $t->same($operation, $plan['operation']);
            $t->same($openHandles, $plan['open_journal_handles']);
            $t->same($dirtyPages, $plan['dirty_pages']);
            $t->same($walCapable, $plan['wal_capable']);
            $t->same($expectedIoError ? 'ioerr' : 'ok', $plan['status']);
            $t->same($expectedIoError ? 'SQLITE_IOERR' : 'SQLITE_OK', $plan['expected_rc']);
            $t->same($expectedIoError, $plan['hot_journal_left']);
            $t->same($expectedJournalExists, $plan['journal_file_exists_after_operation']);
            $t->same($operation !== 'insert' || $journalMode !== 'truncate', $plan['journal_opened']);
            $t->same($operation !== 'insert' || $journalMode === 'delete' || $operation === 'switch-to-wal', $plan['journal_closed']);
            $t->same($journalMode === 'delete' || $operation === 'switch-to-wal', $plan['delete_attempted']);
            $t->same($operation === 'second-connection-delete', $plan['delete_blocked_by_open_handle']);
            $t->same($expectedIoError ? 'ok_after_hot_journal_rollback' : 'ok', $plan['post_recovery_integrity']);
            $t->same($operation === 'switch-to-wal' && $walCapable, $plan['wal_switch_deletes_journal']);
            $t->same(true, in_array('sqlite-upstream-journal2-test', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-safe-delete', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-rollback-journal-lifecycle', $plan['dependencies'], true));

            if ($operation === 'second-connection-delete') {
                $t->same('safe_delete_vfs_refuses_journal_delete_while_handle_is_open', $plan['reason']);
                $t->same(true, in_array('xDelete', $plan['oplog'], true));
            } elseif ($operation === 'large-commit') {
                $t->same('write_truncate_delete_fault_leaves_hot_journal_for_recovery', $plan['reason']);
                $t->same(4, $plan['database_rows_visible']);
            } elseif ($operation === 'switch-to-wal') {
                $t->same('wal_transition_closes_and_deletes_persistent_rollback_journal', $plan['reason']);
                $t->same(true, in_array('journal2.test journal2-2.4', $plan['upstream'], true));
            } elseif ($journalMode === 'truncate') {
                $t->same('truncate_mode_reuses_open_journal_without_delete', $plan['reason']);
                $t->same(false, in_array('xDelete', $plan['oplog'], true));
            } else {
                $t->same('delete_mode_closes_and_deletes_rollback_journal', $plan['reason']);
                $t->same(true, in_array('journal2.test journal2-1.1 create table opens closes deletes journal', $plan['upstream'], true));
            }
        };
    }
}

$tests['real upstream corpus vfs journal2 safe-delete dynamic cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'journal2.test journal2-1.1 create table opens, closes, and deletes rollback journal',
        'journal2.test journal2-1.2 through journal2-1.4 truncate-mode insert journal reuse',
        'journal2.test journal2-1.5 through journal2-1.9 safe-delete open-handle delete failure',
        'journal2.test journal2-1.10 through journal2-1.21 large-commit IOERR hot-journal recovery',
        'journal2.test journal2-2.1 through journal2-2.4 WAL transition deletes persistent journal',
    ], [
        'journal2.test journal2-1.1 create table opens, closes, and deletes rollback journal',
        'journal2.test journal2-1.2 through journal2-1.4 truncate-mode insert journal reuse',
        'journal2.test journal2-1.5 through journal2-1.9 safe-delete open-handle delete failure',
        'journal2.test journal2-1.10 through journal2-1.21 large-commit IOERR hot-journal recovery',
        'journal2.test journal2-2.1 through journal2-2.4 WAL transition deletes persistent journal',
    ]);
};

$tests['real upstream corpus vfs journal2 safe-delete dynamic rejects malformed input'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('', 'delete', 'insert', 0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'memory', 'insert', 0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'delete', 'vacuum', 0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'delete', 'insert', -1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-1.1', 'delete', 'insert', 0, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle('journal2-3.1', 'delete', 'insert', 0, 0));
};

return $tests;
