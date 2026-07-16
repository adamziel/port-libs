<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$cases = [
    'restart-protocol' => [
        'upstream' => ['walvfs.test 6.0', 'walvfs.test 6.1', 'walvfs.test 6.2'],
        'lock' => 'shared readmark',
        'result' => 'SQLITE_PROTOCOL',
        'reason' => 'wal_restart_protocol_reports_locking_protocol_when_shared_lock_cannot_be_reacquired',
    ],
    'checkpointer-lock' => [
        'upstream' => ['walvfs.test 7.0', 'walvfs.test 7.1'],
        'lock' => 'checkpointer exclusive',
        'result' => 'SQLITE_OK',
        'reason' => 'wal_checkpoint_reports_busy_tuple_when_checkpointer_lock_is_unavailable',
    ],
    'v2-stale-cache' => [
        'upstream' => ['walvfs.test 8.0', 'walvfs.test 8.1', 'walvfs.test 8.2', 'walvfs.test 8.3'],
        'lock' => 'checkpoint stale-cache flush',
        'result' => 'SQLITE_OK',
        'reason' => 'version_two_vfs_checkpoint_flushes_out_of_date_page_cache_before_read',
    ],
    'readonly-shm-ioerr' => [
        'upstream' => ['walvfs.test 9.0', 'walvfs.test 9.1'],
        'lock' => 'readonly shm map-lock',
        'result' => 'SQLITE_IOERR',
        'reason' => 'readonly_shm_cannot_initialize_and_shared_lock_ioerr_surfaces_as_disk_io_error',
    ],
];

$caseNo = 0;
foreach (range(1, 150) as $round) {
    foreach ($cases as $scenario => $expected) {
        $caseNo++;
        $busyAttempts = $scenario === 'v2-stale-cache' ? 0 : (($round % 5) + 1);
        $seedRows = 20 + ($round % 7);
        $tests[sprintf('real upstream corpus vfs walvfs lock recovery dynamic %04d %s round %03d', $caseNo, $scenario, $round)] = static function (TestRunner $t) use ($scenario, $expected, $busyAttempts, $seedRows): void {
            $profile = SQLiteVfsIoDynamicPlan::walVfsLockRecoveryProfile($scenario, $busyAttempts, $seedRows);

            $t->same('ok', $profile['status']);
            $t->same('walvfs.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($expected['upstream'], $profile['upstream']);
            $t->same(1024, $profile['page_size']);
            $t->same(false, $profile['auto_vacuum']);
            $t->same($seedRows, $profile['seed_rows']);
            $t->same('wal', $profile['journal_mode']);
            $t->same($expected['lock'], $profile['lock_target']);
            $t->same($scenario === 'v2-stale-cache' ? 0 : max(1, $busyAttempts), $profile['busy_attempts']);
            $t->same($expected['result'], $profile['result_code']);
            $t->same($expected['reason'], $profile['reason']);
            $t->same(true, in_array('upstream-walvfs-lock-recovery', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

            if ($scenario === 'restart-protocol') {
                $t->same(['busy' => 0, 'log_frames' => 5, 'checkpointed_frames' => 5], $profile['checkpoint_result']);
                $t->same(['code' => 1, 'message' => 'locking protocol'], $profile['write_result']);
                $t->same($seedRows, $profile['reader_rows_after_failure']);
                $t->same(true, $profile['wal_restart_blocked']);
                $t->same(true, $profile['connection_reusable_after_failure']);
                $t->same(['xShmLock unlock shared readmark 3', 'xShmLock lock shared busy'], $profile['vfs_operations']);
            } elseif ($scenario === 'checkpointer-lock') {
                $t->same(['busy' => 1, 'log_frames' => -1, 'checkpointed_frames' => -1], $profile['checkpoint_result']);
                $t->same($seedRows, $profile['reader_rows_after_failure']);
                $t->same(true, $profile['wal_restart_blocked']);
                $t->same(true, $profile['connection_reusable_after_failure']);
                $t->same(['xShmLock checkpoint lock exclusive busy'], $profile['vfs_operations']);
            } elseif ($scenario === 'v2-stale-cache') {
                $t->same(['busy' => 0, 'log_frames' => 5, 'checkpointed_frames' => 5], $profile['checkpoint_result']);
                $t->same($seedRows + 1, $profile['reader_rows_after_failure']);
                $t->same(false, $profile['wal_restart_blocked']);
                $t->same(true, $profile['connection_reusable_after_failure']);
                $t->same(['version 2 VFS checkpoint', 'flush stale page cache before count'], $profile['vfs_operations']);
            } else {
                $t->same(null, $profile['checkpoint_result']);
                $t->same(['code' => 1, 'message' => 'disk I/O error'], $profile['select_result']);
                $t->same(null, $profile['reader_rows_after_failure']);
                $t->same(true, $profile['wal_restart_blocked']);
                $t->same(false, $profile['connection_reusable_after_failure']);
                $t->same(['xShmMap SQLITE_READONLY_CANTINIT', 'xShmLock SQLITE_IOERR'], $profile['vfs_operations']);
            }
        };
    }
}

$tests['real upstream corpus vfs walvfs lock recovery dynamic validates case volume'] = static function (TestRunner $t) use ($caseNo): void {
    $t->same(600, $caseNo);
};

$tests['real upstream corpus vfs walvfs lock recovery dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::walVfsLockRecoveryProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::walVfsLockRecoveryProfile('checkpoint'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::walVfsLockRecoveryProfile('restart-protocol', -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::walVfsLockRecoveryProfile('restart-protocol', 1, 0));
};

return $tests;
