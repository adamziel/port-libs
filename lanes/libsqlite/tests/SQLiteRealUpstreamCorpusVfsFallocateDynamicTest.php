<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$alignChunk = static function (int $bytes, int $chunkSize): int {
    if ($bytes === 0) {
        return 0;
    }

    return intdiv($bytes + $chunkSize - 1, $chunkSize) * $chunkSize;
};

$deleteCases = [
    [1024 * 1024, 1024, 1024 * 900, 1024 * 900, 128],
    [1024 * 1024, 2048, 1024 * 700, 1024 * 920, 256],
    [2 * 1024 * 1024, 1024, 1024 * 1400, 1024 * 1100, 512],
    [2 * 1024 * 1024, 4096, 1024 * 1200, 1024 * 900, 128],
    [4 * 1024 * 1024, 4096, 1024 * 2100, 1024 * 1600, 512],
];

$caseNo = 0;
foreach (range(1, 100) as $round) {
    foreach ($deleteCases as $index => [$chunkSize, $pageSize, $baseFirstPayload, $baseSecondPayload, $smallPayload]) {
        ++$caseNo;
        $firstPayload = $baseFirstPayload + (($round % 5) * $pageSize);
        $secondPayload = $baseSecondPayload + (($round % 7) * $pageSize);
        $scenario = sprintf('fallocate-1.dynamic.%03d.%02d', $round, $index + 1);
        $testName = sprintf(
            'real upstream corpus vfs fallocate dynamic rollback chunk lifecycle %04d %s',
            $caseNo,
            $scenario
        );

        $tests[$testName] = static function (TestRunner $t) use (
            $alignChunk,
            $scenario,
            $chunkSize,
            $pageSize,
            $firstPayload,
            $secondPayload,
            $smallPayload
        ): void {
            $profile = SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile(
                $scenario,
                $chunkSize,
                'delete',
                $pageSize,
                $firstPayload,
                $secondPayload,
                $smallPayload
            );
            $expectedAfterFirst = $alignChunk(max($chunkSize, $firstPayload + (2 * $pageSize)), $chunkSize);
            $expectedAfterSecond = $alignChunk(max($chunkSize, $firstPayload + $secondPayload + (2 * $pageSize)), $chunkSize);
            $expectedAfterDeleteFirst = $alignChunk(max($chunkSize, $secondPayload + (2 * $pageSize)), $chunkSize);

            $t->same('ok', $profile['status']);
            $t->same('fallocate.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same('delete', $profile['journal_mode']);
            $t->same($chunkSize, $profile['chunk_size']);
            $t->same($pageSize, $profile['page_size']);
            $t->same($firstPayload, $profile['large_payload_bytes']);
            $t->same($secondPayload, $profile['second_large_payload_bytes']);
            $t->same($smallPayload, $profile['small_payload_bytes']);
            $t->same(true, $profile['auto_vacuum']);
            $t->same($chunkSize, $profile['initial_file_bytes_after_create']);
            $t->same($expectedAfterFirst, $profile['file_bytes_after_first_insert']);
            $t->same($expectedAfterSecond, $profile['file_bytes_after_second_insert']);
            $t->same($expectedAfterDeleteFirst, $profile['file_bytes_after_delete_first_row']);
            $t->same($chunkSize, $profile['file_bytes_after_delete_all_rows']);
            $t->same(0, $profile['freelist_count_after_deletes']);
            $t->same(intdiv($chunkSize, $pageSize), $profile['journal_database_size_pages']);
            $t->same(true, $profile['logical_page_count_after_commit'] < 100);
            $t->same(true, $profile['file_pages_after_commit'] > 100);
            $t->same(100, $profile['max_page_count_after_pragma']);
            $t->same(true, $profile['chunk_aligned_files']);
            $t->same(0, $profile['file_bytes_after_first_insert'] % $chunkSize);
            $t->same(0, $profile['file_bytes_after_second_insert'] % $chunkSize);
            $t->same(0, $profile['file_bytes_after_delete_first_row'] % $chunkSize);
            $t->same(true, $profile['file_bytes_after_second_insert'] >= $profile['file_bytes_after_first_insert']);
            $t->same(true, $profile['file_bytes_after_delete_first_row'] <= $profile['file_bytes_after_second_insert']);
            $t->same('chunk_size_preallocation_tracks_disk_file_size_not_logical_page_count', $profile['reason']);
            $t->same(true, in_array('upstream-fallocate-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-chunk-size-preallocation', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, in_array('fallocate.test fallocate-1.7 transaction header records logical page count before truncation', $profile['upstream'], true));
            $t->same(true, in_array('fallocate.test fallocate-1.9 max_page_count remains enforceable after chunk preallocation', $profile['upstream'], true));
        };
    }
}

$rollbackCaseCount = $caseNo;

$walCases = [
    [32 * 1024, 1024, 35 * 1024, 35 * 1024, 128, false],
    [32 * 1024, 1024, 35 * 1024, 35 * 1024, 128, true],
    [64 * 1024, 1024, 53 * 1024, 35 * 1024, 256, false],
    [64 * 1024, 2048, 53 * 1024, 40 * 1024, 512, true],
    [128 * 1024, 4096, 90 * 1024, 48 * 1024, 1024, true],
];

foreach (range(1, 100) as $round) {
    foreach ($walCases as $index => [$chunkSize, $pageSize, $baseLargePayload, $secondPayload, $smallPayload, $readerPinned]) {
        ++$caseNo;
        $largePayload = $baseLargePayload + (($round % 6) * $pageSize);
        $scenario = sprintf('fallocate-2.dynamic.%03d.%02d', $round, $index + 1);
        $testName = sprintf(
            'real upstream corpus vfs fallocate dynamic wal chunk lifecycle %04d %s',
            $caseNo,
            $scenario
        );

        $tests[$testName] = static function (TestRunner $t) use (
            $alignChunk,
            $scenario,
            $chunkSize,
            $pageSize,
            $largePayload,
            $secondPayload,
            $smallPayload,
            $readerPinned
        ): void {
            $profile = SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile(
                $scenario,
                $chunkSize,
                'wal',
                $pageSize,
                $largePayload,
                $secondPayload,
                $smallPayload,
                $readerPinned
            );
            $expectedAfterLargeCheckpoint = $alignChunk(max($chunkSize, $largePayload + (2 * $pageSize)), $chunkSize);
            $expectedAfterMixedVacuum = $alignChunk(max($chunkSize, $largePayload + $smallPayload + (2 * $pageSize)), $chunkSize);

            $t->same('ok', $profile['status']);
            $t->same('fallocate.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same('wal', $profile['journal_mode']);
            $t->same($chunkSize, $profile['chunk_size']);
            $t->same($pageSize, $profile['page_size']);
            $t->same($largePayload, $profile['large_payload_bytes']);
            $t->same($secondPayload, $profile['second_large_payload_bytes']);
            $t->same($smallPayload, $profile['small_payload_bytes']);
            $t->same($chunkSize, $profile['initial_file_bytes_after_create']);
            $t->same($chunkSize, $profile['wal_file_bytes_after_create']);
            $t->same($expectedAfterLargeCheckpoint, $profile['file_bytes_after_wal_checkpoint_large_insert']);
            $t->same($expectedAfterLargeCheckpoint, $profile['file_bytes_after_wal_delete_vacuum_before_checkpoint']);
            $t->same($chunkSize, $profile['file_bytes_after_wal_checkpoint_truncate']);
            $t->same($expectedAfterMixedVacuum, $profile['file_bytes_after_wal_mixed_vacuum']);
            $t->same($readerPinned, $profile['reader_pinned']);
            $t->same(true, $profile['chunk_aligned_files']);
            $t->same(0, $profile['file_bytes_after_wal_checkpoint_large_insert'] % $chunkSize);
            $t->same(0, $profile['file_bytes_after_wal_mixed_vacuum'] % $chunkSize);
            $t->same(true, $profile['file_bytes_after_wal_checkpoint_large_insert'] >= $profile['wal_file_bytes_after_create']);
            $t->same(true, $profile['file_bytes_after_wal_mixed_vacuum'] >= $profile['file_bytes_after_wal_checkpoint_truncate']);
            $t->same('wal_checkpoint_respects_chunk_size_and_reader_pinned_truncation_boundary', $profile['reason']);
            $t->same(true, in_array('upstream-fallocate-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-chunk-size-preallocation', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, in_array('fallocate.test fallocate-2.4 checkpoint truncates back to one chunk', $profile['upstream'], true));
            $t->same(true, in_array('fallocate.test fallocate-2.8 reader release allows checkpoint truncation', $profile['upstream'], true));

            if ($readerPinned) {
                $t->same($expectedAfterMixedVacuum, $profile['file_bytes_after_reader_pinned_checkpoint']);
                $t->same(1, $profile['pinned_reader_visible_rows']);
                $t->same($chunkSize, $profile['file_bytes_after_reader_release_checkpoint']);
                $t->same(['checkpoint-large-insert', 'vacuum-before-checkpoint', 'checkpoint-truncate', 'checkpoint-reader-blocked', 'reader-release-checkpoint'], $profile['checkpoint_sequence']);
            } else {
                $t->same(null, $profile['file_bytes_after_reader_pinned_checkpoint']);
                $t->same(null, $profile['pinned_reader_visible_rows']);
                $t->same(null, $profile['file_bytes_after_reader_release_checkpoint']);
                $t->same(['checkpoint-large-insert', 'vacuum-before-checkpoint', 'checkpoint-truncate'], $profile['checkpoint_sequence']);
            }
        };
    }
}

$tests['real upstream corpus vfs fallocate dynamic cites hydrated upstream source'] = static function (TestRunner $t): void {
    $upstreamPath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fallocate.test';
    $source = file_get_contents($upstreamPath);

    $t->same(true, is_string($source));
    $t->same(true, str_contains($source, 'file_control_chunksize_test db main'));
    $t->same(true, str_contains($source, 'do_test fallocate-1.7'));
    $t->same(true, str_contains($source, 'do_test fallocate-2.8'));
    $t->same(true, str_contains($source, 'PRAGMA max_page_count = 100'));
};

$tests['real upstream corpus vfs fallocate dynamic validates case volume'] = static function (TestRunner $t) use ($rollbackCaseCount, $caseNo): void {
    $t->same(500, $rollbackCaseCount);
    $t->same(1000, $caseNo);
};

$tests['real upstream corpus vfs fallocate dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('', 1024 * 1024, 'delete', 1024, 1024, 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('fallocate-1.bad', 1024 * 1024, 'wal', 1024, 1024, 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('fallocate-2.bad', 1024 * 1024, 'delete', 1024, 1024, 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('fallocate-1.bad', 256, 'delete', 1024, 1024, 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('fallocate-1.bad', 1024 * 1024, 'delete', 1000, 1024, 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('fallocate-1.bad', 1024 * 1024, 'delete', 1024, 0, 1024));
};

$tests['real upstream corpus vfs fallocate dynamic records non overlap'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::fallocateChunkLifecycleProfile('fallocate-2.nonoverlap', 32 * 1024, 'wal', 1024, 35 * 1024, 35 * 1024, 128, true);

    $t->same('fallocate.test', $profile['script']);
    $t->same(false, in_array('upstream-syscall-sizehint-chunks', $profile['dependencies'], true));
    $t->same(false, in_array('upstream-sysfault-fallocate-faults', $profile['dependencies'], true));
    $t->same(true, in_array('sqlite-vfs-chunk-size-preallocation', $profile['dependencies'], true));
};

return $tests;
