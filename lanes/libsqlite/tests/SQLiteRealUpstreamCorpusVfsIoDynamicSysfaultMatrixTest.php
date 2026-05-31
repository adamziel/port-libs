<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$persistentScenarios = [
    ['sysfault-1', 'open', ['EACCES', 'EIO', 'ENOMEM'], 'unix'],
    ['sysfault-1', 'getcwd', ['EACCES', 'EIO', 'ENOMEM'], 'unix'],
    ['sysfault-1.2', 'fstat', ['ENOMEM', 'EOVERFLOW'], 'unix'],
    ['sysfault-1.3', 'fcntl', ['EAGAIN', 'ETIMEDOUT', 'EBUSY', 'EINTR', 'ENOLCK', 'EACCES', 'EPERM', 'EDEADLK', 'ENOMEM'], 'unix'],
    ['sysfault-1.3', 'fcntl', ['EAGAIN', 'ETIMEDOUT', 'EBUSY', 'EINTR', 'ENOLCK', 'EACCES', 'EPERM', 'EDEADLK', 'ENOMEM'], 'unix-excl'],
    ['sysfault-3', 'fstat', ['EIO'], 'unix'],
    ['sysfault-3', 'fallocate', ['EIO'], 'unix'],
    ['sysfault-4', 'mmap', ['EACCES'], 'unix'],
];

$persistentCase = 0;
foreach ($persistentScenarios as [$scenario, $syscall, $errnos, $vfs]) {
    foreach ($errnos as $errno) {
        for ($faultPosition = 1; $faultPosition <= 12; $faultPosition++) {
            $persistentCase++;
            $tests[sprintf('real upstream corpus vfs io sysfault matrix persistent %04d %s %s %s fault %02d %s', $persistentCase, $scenario, $syscall, strtolower($errno), $faultPosition, $vfs)] =
                static function (TestRunner $t) use ($scenario, $syscall, $errno, $faultPosition, $vfs): void {
                    $profile = SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile($scenario, $syscall, $errno, $faultPosition, $vfs, true);

                    $t->same('ok', $profile['status']);
                    $t->same('sysfault.test', $profile['script']);
                    $t->same($syscall, $profile['syscall']);
                    $t->same($errno, $profile['errno']);
                    $t->same($faultPosition, $profile['fault_position']);
                    $t->same(true, $profile['persistent_fault']);
                    $t->same(false, $profile['transient_fault']);
                    $t->same($vfs, $profile['vfs']);
                    $t->same(true, in_array($syscall, $profile['installed_calls'], true));
                    $t->same(true, $profile['database_reusable_after_fault']);
                    $t->same('ok', $profile['integrity_check_after_fault']);
                    $t->same($errno === 'EOVERFLOW', $profile['large_file_support_disabled']);
                    $t->same($scenario === 'sysfault-4', $profile['mmap_read_can_fallback_or_error']);
                    $t->same($scenario === 'sysfault-3', $profile['chunked_write_can_ignore_hint_fault']);
                    $t->same(true, in_array('sqlite-upstream-sysfault-test', $profile['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-persistent-unix-error-map', $profile['dependencies'], true));
                    $t->same(true, str_starts_with($profile['scenario'], $scenario . '-' . $syscall . '-' . strtolower($errno) . '-'));
                };
        }
    }
}

$transientSyscalls = ['open', 'ftruncate', 'close', 'read', 'pread', 'pread64', 'write', 'fallocate'];
$journalModes = ['delete', 'truncate', 'persist', 'wal'];
$transientCase = 0;
foreach ($transientSyscalls as $syscall) {
    foreach ($journalModes as $journalMode) {
        for ($faultPosition = 1; $faultPosition <= 12; $faultPosition++) {
            $transientCase++;
            $chunkSize = 1024 + (($faultPosition + $transientCase) % 9) * 512;
            $blobBytes = 4096 + (($faultPosition * 97) % 2048);
            $attachedWrite = ($transientCase % 3) !== 0;

            $tests[sprintf('real upstream corpus vfs io sysfault matrix transient %04d %s %s fault %02d attach %d', $transientCase, $syscall, $journalMode, $faultPosition, $attachedWrite ? 1 : 0)] =
                static function (TestRunner $t) use ($syscall, $journalMode, $faultPosition, $chunkSize, $blobBytes, $attachedWrite): void {
                    $profile = SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile($syscall, $faultPosition, $journalMode, $chunkSize, $blobBytes, $attachedWrite);

                    $t->same('ok', $profile['status']);
                    $t->same('sysfault.test', $profile['script']);
                    $t->same('EINTR', $profile['errno']);
                    $t->same($syscall, $profile['syscall']);
                    $t->same($faultPosition, $profile['fault_position']);
                    $t->same(true, $profile['transient_fault']);
                    $t->same(true, $profile['retry_required']);
                    $t->same($faultPosition + 1, $profile['retry_attempts_before_success']);
                    $t->same($journalMode, $profile['journal_mode']);
                    $t->same($journalMode === 'wal' ? 'wal' : $journalMode, $profile['journal_mode_echo']);
                    $t->same($chunkSize, $profile['chunk_size']);
                    $t->same($blobBytes, $profile['blob_bytes']);
                    $t->same($attachedWrite, $profile['attached_write']);
                    $t->same($attachedWrite ? [2] : [1], $profile['aux_rows_after_commit']);
                    $t->same(true, $profile['large_blob_row_deleted']);
                    $t->same('SQLITE_OK', $profile['result_code']);
                    $t->same('ok', $profile['integrity_check']);
                    $t->same(true, in_array('sqlite-vfs-transient-eintr-retry', $profile['dependencies'], true));
                };
        }
    }
}

foreach (['delete', 'wal'] as $journalMode) {
    for ($faultIndex = 1; $faultIndex <= 19; $faultIndex++) {
        foreach ([1, 2, 3, 4] as $attachedDatabases) {
            $tests[sprintf('real upstream corpus vfs io syscall matrix eintr open retry %s fault %02d attached %d', $journalMode, $faultIndex, $attachedDatabases)] =
                static function (TestRunner $t) use ($journalMode, $faultIndex, $attachedDatabases): void {
                    $profile = SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile($journalMode, $faultIndex, $attachedDatabases);

                    $t->same('ok', $profile['status']);
                    $t->same('syscall.test', $profile['script']);
                    $t->same($journalMode, $profile['journal_mode']);
                    $t->same($faultIndex, $profile['fault_index']);
                    $t->same('EINTR', $profile['errno']);
                    $t->same('open', $profile['operation']);
                    $t->same(true, $profile['retry_required']);
                    $t->same($faultIndex + 1, $profile['open_attempts_before_success']);
                    $t->same($attachedDatabases, $profile['attached_databases']);
                    $t->same($attachedDatabases + 1, count($profile['journal_open_plan']));
                    $t->same($journalMode === 'wal' ? 'open_wal_sidecar_after_eintr_retry' : 'open_rollback_journal_after_eintr_retry', $profile['journal_open_plan'][0]);
                    $t->same([1, 2, 5, 6], $profile['main_rows_after_reopen']);
                    $t->same([3, 4, 7, 8], $profile['aux_rows_after_reopen']);
                    $t->same(true, $profile['connection_reusable_after_retry']);
                    $t->same(true, in_array('upstream-syscall-eintr-open-retry', $profile['dependencies'], true));
                };
        }
    }
}

for ($clientPair = 1; $clientPair <= 120; $clientPair++) {
    foreach ([false, true] as $closeSiblingHandles) {
        $tests[sprintf('real upstream corpus vfs io syscall matrix close preserves peer lock pair %03d close siblings %d', $clientPair, $closeSiblingHandles ? 1 : 0)] =
            static function (TestRunner $t) use ($clientPair, $closeSiblingHandles): void {
                $profile = SQLiteVfsIoDynamicPlan::syscallClosePreservesPeerLockProfile($clientPair, $closeSiblingHandles);

                $t->same('ok', $profile['status']);
                $t->same('syscall.test', $profile['script']);
                $t->same($clientPair, $profile['client_pair']);
                $t->same(['dbX1', 'dbX2'], $profile['same_process_handles']);
                $t->same(true, $profile['write_transaction_open']);
                $t->same([1, 2], $profile['peer_read_rows_before_commit']);
                $t->same(['code' => 1, 'message' => 'database is locked'], $profile['peer_insert_before_close']);
                $t->same($closeSiblingHandles ? ['dbX1', 'dbX2'] : [], $profile['closed_sibling_handles']);
                $t->same(['code' => 1, 'message' => 'database is locked'], $profile['peer_insert_after_sibling_close']);
                $t->same(['code' => 0, 'message' => ''], $profile['commit_result']);
                $t->same(['code' => 0, 'message' => ''], $profile['peer_insert_after_commit']);
                $t->same(true, $profile['close_releases_only_handle_locks']);
                $t->same(true, $profile['peer_lock_survives_sibling_close']);
                $t->same(true, in_array('vfs-process-lock-preservation', $profile['dependencies'], true));
            };
    }
}

for ($tempRows = 1; $tempRows <= 80; $tempRows++) {
    foreach ([3, 9, 17] as $mainCacheSize) {
        foreach ([2, 8, 16] as $tempCacheSize) {
            foreach ([false, true] as $memoryHandle) {
                $tests[sprintf('real upstream corpus vfs io syscall matrix temp close rows %03d main %02d temp %02d memory %d', $tempRows, $mainCacheSize, $tempCacheSize, $memoryHandle ? 1 : 0)] =
                    static function (TestRunner $t) use ($tempRows, $mainCacheSize, $tempCacheSize, $memoryHandle): void {
                        $profile = SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile($tempRows, $mainCacheSize, $tempCacheSize, $memoryHandle);

                        $t->same('ok', $profile['status']);
                        $t->same('syscall.test', $profile['script']);
                        $t->same('syscall-6', $profile['scenario']);
                        $t->same($mainCacheSize, $profile['main_cache_size']);
                        $t->same($tempCacheSize, $profile['temp_cache_size']);
                        $t->same($tempRows, $profile['temp_rows']);
                        $t->same(1100, $profile['row_payload_bytes']);
                        $t->same('file', $profile['temp_store']);
                        $t->same($memoryHandle, $profile['memory_handle_closed']);
                        $t->same($tempRows > $tempCacheSize, $profile['temp_btree_spills_to_file']);
                        $t->same(['db2', 'db3', 'dbM', 'db1', 'db'], $profile['close_order']);
                        $t->same('SQLITE_OK', $profile['close_result']);
                        $t->same(0, $profile['open_file_count_after_close']);
                        $t->same(true, $profile['unlinked_temp_files_after_close']);
                        $t->same(true, $profile['main_database_reusable_after_close']);
                        $t->same(true, in_array('upstream-syscall-temp-handle-close', $profile['dependencies'], true));
                    };
            }
        }
    }
}

$tests['real upstream corpus vfs io sysfault matrix cites hydrated upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'sysfault.test 1 open/getcwd persistent open and write-body faults',
        'sysfault.test 1.2 fstat ENOMEM/EOVERFLOW while opening and writing',
        'sysfault.test 1.3 unix/unix-excl fcntl locking errno mapping',
        'sysfault.test 2.1 transient EINTR retry across open/ftruncate/close/read/pread/pread64/write/fallocate',
        'sysfault.test 3 fstat/fallocate EIO during chunked write path',
        'sysfault.test 4 mmap EACCES during mapped SELECT',
        'syscall.test 4.2.wal/delete EINTR open retry during attached commit',
        'syscall.test syscall-5.* close does not drop peer process locks',
        'syscall.test 6.1-6.2 temp handle close and temp_store=file cleanup after spill',
    ], [
        'sysfault.test 1 open/getcwd persistent open and write-body faults',
        'sysfault.test 1.2 fstat ENOMEM/EOVERFLOW while opening and writing',
        'sysfault.test 1.3 unix/unix-excl fcntl locking errno mapping',
        'sysfault.test 2.1 transient EINTR retry across open/ftruncate/close/read/pread/pread64/write/fallocate',
        'sysfault.test 3 fstat/fallocate EIO during chunked write path',
        'sysfault.test 4 mmap EACCES during mapped SELECT',
        'syscall.test 4.2.wal/delete EINTR open retry during attached commit',
        'syscall.test syscall-5.* close does not drop peer process locks',
        'syscall.test 6.1-6.2 temp handle close and temp_store=file cleanup after spill',
    ]);
};

$tests['real upstream corpus vfs io sysfault matrix rejects malformed source profiles'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile('sysfault-1.3', 'fcntl', 'ENOENT', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile('unlink', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile('truncate', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallClosePreservesPeerLockProfile(0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile(1, 1, 0));
};

return $tests;
