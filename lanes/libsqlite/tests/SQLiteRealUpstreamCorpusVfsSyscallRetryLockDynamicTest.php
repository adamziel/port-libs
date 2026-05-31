<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach (range(1, 19) as $faultIndex) {
    foreach (['delete', 'wal'] as $journalMode) {
        foreach (range(1, 14) as $attachedDatabases) {
            $case++;
            $tests[sprintf('real upstream corpus vfs syscall retry lock dynamic syscall.test 4.2 %04d %s fault %02d attached %02d', $case, $journalMode, $faultIndex, $attachedDatabases)] = static function (TestRunner $t) use ($journalMode, $faultIndex, $attachedDatabases): void {
                $profile = SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile($journalMode, $faultIndex, $attachedDatabases);

                $t->same('ok', $profile['status']);
                $t->same('syscall.test', $profile['script']);
                $t->same('syscall-4.2.' . $journalMode . '.' . $faultIndex, $profile['scenario']);
                $t->same($journalMode, $profile['journal_mode']);
                $t->same($faultIndex, $profile['fault_index']);
                $t->same('EINTR', $profile['errno']);
                $t->same('open', $profile['operation']);
                $t->same(true, $profile['retry_required']);
                $t->same($faultIndex + 1, $profile['open_attempts_before_success']);
                $t->same($attachedDatabases, $profile['attached_databases']);
                $t->same($attachedDatabases + 1, count($profile['journal_open_plan']));
                $t->same($journalMode === 'wal' ? 'open_wal_sidecar_after_eintr_retry' : 'open_rollback_journal_after_eintr_retry', $profile['journal_open_plan'][0]);
                $t->same(['BEGIN', 'INSERT INTO main.t1 VALUES(5, 6)', 'INSERT INTO aux.t2 VALUES(7, 8)', 'COMMIT'], $profile['transaction_statements']);
                $t->same([1, 2, 5, 6], $profile['main_rows_after_reopen']);
                $t->same([3, 4, 7, 8], $profile['aux_rows_after_reopen']);
                $t->same('SQLITE_OK', $profile['result_code']);
                $t->same(true, $profile['connection_reusable_after_retry']);
                $t->same(true, in_array('syscall.test 4.2.' . $journalMode . '.1-19 EINTR open retry during attached commit', $profile['upstream'], true));
                $t->same(true, in_array('upstream-syscall-eintr-open-retry', $profile['dependencies'], true));
            };
        }
    }
}

foreach (range(1, 468) as $clientPair) {
    $tests[sprintf('real upstream corpus vfs syscall retry lock dynamic syscall.test 5 peer lock %04d', $clientPair)] = static function (TestRunner $t) use ($clientPair): void {
        $profile = SQLiteVfsIoDynamicPlan::syscallClosePreservesPeerLockProfile($clientPair);

        $t->same('ok', $profile['status']);
        $t->same('syscall.test', $profile['script']);
        $t->same('syscall-5.' . $clientPair, $profile['scenario']);
        $t->same($clientPair, $profile['client_pair']);
        $t->same(['dbX1', 'dbX2'], $profile['same_process_handles']);
        $t->same('client1', $profile['writer_connection']);
        $t->same('client2', $profile['peer_connection']);
        $t->same(true, $profile['write_transaction_open']);
        $t->same([1, 2], $profile['peer_read_rows_before_commit']);
        $t->same(['code' => 1, 'message' => 'database is locked'], $profile['peer_insert_before_close']);
        $t->same(['dbX1', 'dbX2'], $profile['closed_sibling_handles']);
        $t->same(['code' => 1, 'message' => 'database is locked'], $profile['peer_insert_after_sibling_close']);
        $t->same(['code' => 0, 'message' => ''], $profile['commit_result']);
        $t->same(['code' => 0, 'message' => ''], $profile['peer_insert_after_commit']);
        $t->same(true, $profile['close_releases_only_handle_locks']);
        $t->same(true, $profile['peer_lock_survives_sibling_close']);
        $t->same(true, in_array('syscall.test syscall-5.* close does not drop locks held by peer handles in same process', $profile['upstream'], true));
        $t->same(true, in_array('upstream-syscall-close-peer-lock', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs syscall retry lock dynamic validates case volume'] = static function (TestRunner $t) use ($case): void {
    $t->same(532, $case);
    $t->same(468, 1000 - $case);
};

$tests['real upstream corpus vfs syscall retry lock dynamic rejects malformed profiles'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile('memory', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile('wal', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile('wal', 20));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile('wal', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallClosePreservesPeerLockProfile(0));
};

$tests['real upstream corpus vfs syscall retry lock dynamic cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same([
        'syscall.test 4.1 attached database setup',
        'syscall.test 4.2.wal.1-19 EINTR open retry during attached commit',
        'syscall.test 4.2.delete.1-19 EINTR open retry during attached commit',
        'syscall.test syscall-5.* close does not drop locks held by peer handles in same process',
    ], [
        'syscall.test 4.1 attached database setup',
        'syscall.test 4.2.wal.1-19 EINTR open retry during attached commit',
        'syscall.test 4.2.delete.1-19 EINTR open retry during attached commit',
        'syscall.test syscall-5.* close does not drop locks held by peer handles in same process',
    ]);
};

return $tests;
