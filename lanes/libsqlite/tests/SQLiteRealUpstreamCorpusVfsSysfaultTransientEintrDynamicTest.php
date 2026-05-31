<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$syscalls = ['open', 'ftruncate', 'close', 'read', 'pread', 'pread64', 'write', 'fallocate'];
$journalModes = ['truncate', 'delete', 'persist', 'wal'];
$chunkSizes = [4096, 8192, 16384, 32768, 65536];
$blobSizes = [4096, 8192, 10000, 16384, 24576];
$case = 0;

foreach ($syscalls as $syscall) {
    foreach ($journalModes as $journalMode) {
        foreach ($chunkSizes as $chunkSize) {
            foreach ($blobSizes as $blobBytes) {
                foreach ([true, false] as $attachedWrite) {
                    $case++;
                    $faultPosition = (($case - 1) % 19) + 1;
                    $tests[sprintf(
                        'real upstream corpus vfs sysfault transient eintr dynamic sysfault.test 2.1 case %04d %s %s chunk %05d blob %05d attached %d',
                        $case,
                        $syscall,
                        $journalMode,
                        $chunkSize,
                        $blobBytes,
                        $attachedWrite ? 1 : 0
                    )] = static function (TestRunner $t) use ($syscall, $faultPosition, $journalMode, $chunkSize, $blobBytes, $attachedWrite): void {
                        $profile = SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile(
                            $syscall,
                            $faultPosition,
                            $journalMode,
                            $chunkSize,
                            $blobBytes,
                            $attachedWrite
                        );

                        $t->same('ok', $profile['status']);
                        $t->same('sysfault.test', $profile['script']);
                        $t->same('sysfault-2.1-' . $syscall . '-' . $faultPosition, $profile['scenario']);
                        $t->same([
                            'sysfault.test 2.setup attached database and primary-key table fixture',
                            'sysfault.test 2.1 vfsfault-transient single EINTR does not affect processing',
                        ], $profile['upstream']);
                        $t->same($syscall, $profile['syscall']);
                        $t->same($faultPosition, $profile['fault_position']);
                        $t->same('EINTR', $profile['errno']);
                        $t->same(true, $profile['transient_fault']);
                        $t->same(true, $profile['retry_required']);
                        $t->same($faultPosition + 1, $profile['retry_attempts_before_success']);
                        $t->same($journalMode, $profile['journal_mode']);
                        $t->same($journalMode, $profile['journal_mode_echo']);
                        $t->same($chunkSize, $profile['chunk_size']);
                        $t->same($blobBytes, $profile['blob_bytes']);
                        $t->same($attachedWrite, $profile['attached_write']);
                        $t->same([['a' => 'abc', 'b' => 'def', 'c' => 'ghi']], $profile['initial_rows']);
                        $t->same(3, count($profile['rows_after_commit_before_delete']));
                        $t->same([
                            ['a' => 'abc', 'b' => 'def', 'c' => 'ghi'],
                            ['a' => 'jkl', 'b' => 'mno', 'c' => 'pqr'],
                        ], $profile['rows_after_delete']);
                        $t->same([$attachedWrite ? 2 : 1], $profile['aux_rows_after_commit']);
                        $t->same(true, $profile['large_blob_row_deleted']);
                        $t->same([
                            'abc', 'def', 'ghi',
                            $journalMode,
                            'abc', 'def', 'ghi',
                            'jkl', 'mno', 'pqr',
                            $attachedWrite ? 2 : 1,
                        ], $profile['expected_result']);
                        $t->same('SQLITE_OK', $profile['result_code']);
                        $t->same(true, $profile['connection_reusable_after_fault']);
                        $t->same('ok', $profile['integrity_check']);
                        $t->same(true, in_array('sqlite-upstream-sysfault-test', $profile['dependencies'], true));
                        $t->same(true, in_array('sqlite-vfs-transient-eintr-retry', $profile['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs sysfault transient eintr dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile('stat', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile('open', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile('open', 1, 'memory'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile('open', 1, 'truncate', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sysfaultTransientEintrProfile('open', 1, 'truncate', 8192, 0));
};

return $tests;
