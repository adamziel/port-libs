<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach ([1, 2, 4, 8, 16, 32, 64, 128] as $tempRows) {
    foreach ([4, 8, 10, 16, 32] as $mainCacheSize) {
        foreach ([4, 8, 10, 16, 32] as $tempCacheSize) {
            foreach ([false, true] as $memoryHandle) {
                $case++;
                $tests[sprintf('real upstream corpus vfs syscall temp close dynamic syscall.test 6 %04d rows %03d main %02d temp %02d memory %d', $case, $tempRows, $mainCacheSize, $tempCacheSize, $memoryHandle ? 1 : 0)] = static function (TestRunner $t) use ($tempRows, $mainCacheSize, $tempCacheSize, $memoryHandle): void {
                    $profile = SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile($tempRows, $mainCacheSize, $tempCacheSize, $memoryHandle);

                    $t->same('ok', $profile['status']);
                    $t->same('syscall.test', $profile['script']);
                    $t->same('syscall-6', $profile['scenario']);
                    $t->same($mainCacheSize, $profile['main_cache_size']);
                    $t->same($tempCacheSize, $profile['temp_cache_size']);
                    $t->same($tempRows, $profile['temp_rows']);
                    $t->same('file', $profile['temp_store']);
                    $t->same($memoryHandle, $profile['memory_handle_closed']);
                    $t->same($tempRows > $tempCacheSize, $profile['temp_btree_spills_to_file']);
                    $t->same(true, $profile['estimated_temp_bytes'] >= 4096);
                    $t->same(0, $profile['estimated_temp_bytes'] % 4096);
                    $t->same(['db2', 'db3', 'dbM', 'db1', 'db'], $profile['close_order']);
                    $t->same('SQLITE_OK', $profile['close_result']);
                    $t->same(0, $profile['open_file_count_after_close']);
                    $t->same(true, $profile['unlinked_temp_files_after_close']);
                    $t->same(true, $profile['main_database_reusable_after_close']);
                    $t->same(true, in_array('syscall.test 6.1 close several file-backed and in-memory handles', $profile['upstream'], true));
                    $t->same(true, in_array('syscall.test 6.2 temp_store=file large temp-table close after cache spill', $profile['upstream'], true));
                    $t->same(true, in_array('upstream-syscall-temp-handle-close', $profile['dependencies'], true));
                    $t->same(true, in_array('sqlite-temp-store-file-close', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

                    if ($memoryHandle) {
                        $t->same(true, in_array(':memory:', $profile['temporary_database_handles'], true));
                    } else {
                        $t->same(false, in_array(':memory:', $profile['temporary_database_handles'], true));
                    }
                };
            }
        }
    }
}

$chunkCase = 0;
foreach ([16, 32, 64, 128] as $chunkSize) {
    foreach (range(0, 255) as $sizeHint) {
        $chunkCase++;
        $tests[sprintf('real upstream corpus vfs syscall chunk hint dynamic syscall.test 8.4 %04d chunk %03d hint %03d', $chunkCase, $chunkSize, $sizeHint)] = static function (TestRunner $t) use ($chunkSize, $sizeHint): void {
            $profile = SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile($chunkSize, $sizeHint);
            $expectedBytes = $sizeHint === 0 ? 0 : (int) (ceil(max($chunkSize, $sizeHint) / $chunkSize) * $chunkSize);

            $t->same('ok', $profile['status']);
            $t->same('syscall.test', $profile['script']);
            $t->same($chunkSize, $profile['chunk_size']);
            $t->same($sizeHint, $profile['size_hint']);
            $t->same($expectedBytes, $profile['file_bytes_after_hint']);
            $t->same($sizeHint > 0, $profile['preallocated']);
            $t->same(0, $profile['file_bytes_after_hint'] % $chunkSize);
            $t->same(true, $profile['growth_rounded_to_chunk']);
            $t->same(true, in_array('syscall.test 8.1', $profile['upstream'], true));
            $t->same(true, in_array('syscall.test 8.2.1-8.2.5', $profile['upstream'], true));
            $t->same(true, in_array('upstream-syscall-file-control-sizehint', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs syscall temp close cites source sections'] = static function (TestRunner $t): void {
    $t->same([
        'syscall.test 6.1 close several file-backed and in-memory handles',
        'syscall.test 6.2 temp_store=file large temp-table close after cache spill',
        'syscall.test 8.3 chunk-size reset to 16 bytes',
        'syscall.test 8.4.1-8.4.5 size hints round to 16-byte chunks',
    ], [
        'syscall.test 6.1 close several file-backed and in-memory handles',
        'syscall.test 6.2 temp_store=file large temp-table close after cache spill',
        'syscall.test 8.3 chunk-size reset to 16 bytes',
        'syscall.test 8.4.1-8.4.5 size hints round to 16-byte chunks',
    ]);
};

$tests['real upstream corpus vfs syscall temp close rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile(0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile(1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile(1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile(0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileControlChunkSizeHintProfile(16, -1));
};

return $tests;
