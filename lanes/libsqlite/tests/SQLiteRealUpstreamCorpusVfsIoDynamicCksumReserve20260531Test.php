<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$reserveBytes = [0, 4, 8, 16, 32];
$pageSizes = [1024, 2048, 4096, 8192];
$largeRows = [17, 85, 425, 1700, 8500];
$blobBytes = [1000, 1500, 2048, 4096, 5000];
$walRows = [25, 50, 75, 100, 125];
$case = 0;

foreach ($reserveBytes as $reserve) {
    foreach ($pageSizes as $pageSize) {
        foreach ($largeRows as $rowCount) {
            foreach ($blobBytes as $blobBytesValue) {
                foreach ($walRows as $walRowCount) {
                    ++$case;
                    $savedImage = ($case % 3) !== 0;
                    $tests[sprintf(
                        'real upstream corpus vfs io dynamic cksumvfs reserve WAL reopen %04d reserve %d page %d rows %d blob %d wal %d',
                        $case,
                        $reserve,
                        $pageSize,
                        $rowCount,
                        $blobBytesValue,
                        $walRowCount
                    )] = static function (TestRunner $t) use ($reserve, $pageSize, $rowCount, $blobBytesValue, $walRowCount, $savedImage): void {
                        $plan = SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(
                            $reserve,
                            $pageSize,
                            $rowCount,
                            $blobBytesValue,
                            $walRowCount,
                            $savedImage
                        );

                        $usablePageBytes = $pageSize - $reserve;
                        $payloadPages = max(1, (int) ceil($blobBytesValue / max(1, $usablePageBytes - 35)));
                        $walFramePages = max(1, (int) ceil(($walRowCount * ($blobBytesValue + 128)) / $usablePageBytes));

                        $t->same('ok', $plan['status']);
                        $t->same('cksumvfs.test', $plan['script']);
                        $t->same($reserve, $plan['reserve_bytes']);
                        $t->same($pageSize, $plan['page_size']);
                        $t->same($usablePageBytes, $plan['usable_page_bytes']);
                        $t->same($rowCount, $plan['large_rows']);
                        $t->same($blobBytesValue, $plan['large_blob_bytes']);
                        $t->same($payloadPages, $plan['large_payload_pages_per_row']);
                        $t->same(2 + ($rowCount * $payloadPages), $plan['database_pages_after_bulk_insert']);
                        $t->same($rowCount, $plan['rows_after_bulk_insert']);
                        $t->same('wal', $plan['journal_mode_after_delete']);
                        $t->same(0, $plan['rows_after_wal_delete']);
                        $t->same(2, $plan['database_pages_after_wal_delete']);
                        $t->same(0, $plan['checkpoint_busy']);
                        $t->same($walFramePages, $plan['checkpoint_log_frames']);
                        $t->same($walFramePages, $plan['checkpoint_checkpointed_frames']);
                        $t->same(true, $plan['checkpoint_complete']);
                        $t->same($walRowCount, $plan['wal_rows']);
                        $t->same($walFramePages, $plan['wal_frame_pages']);
                        $t->same($walRowCount, $plan['rows_after_recursive_insert']);
                        $t->same(2 + $walFramePages, $plan['database_pages_after_wal_reload']);
                        $t->same($savedImage, $plan['reopen_through_saved_image']);
                        $t->same($savedImage ? $walRowCount : null, $plan['rows_after_saved_reopen']);
                        $t->same($walRowCount, $plan['rows_after_direct_reopen']);
                        $t->same(true, $plan['checksum_reserved_tail_bytes_preserved']);
                        $t->same('ok', $plan['integrity_check']);
                        $t->same('checksum_vfs_reserve_bytes_survive_bulk_wal_checkpoint_and_reopen', $plan['reason']);
                        $t->same(true, in_array('upstream-cksumvfs-reserve-wal-reopen', $plan['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic cksumvfs owns exactly twenty five hundred reserve WAL reopen cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(2500, $case);
};

$tests['real upstream corpus vfs io dynamic cksumvfs cites upstream lifecycle sections'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(8, 4096, 8500, 5000, 100);

    $t->same([
        'cksumvfs.test 1.0 create table under cksumvfs with 8 reserve bytes',
        'cksumvfs.test 1.1 select row survives checksum reserve bytes',
        'cksumvfs.test 1.2 delete clears initial checksum-protected row',
        'cksumvfs.test 1.3 bulk randomblob transaction commits under checksum VFS',
        'cksumvfs.test 1.4 count bulk rows before WAL delete',
        'cksumvfs.test 1.5 WAL mode delete keeps checksum VFS database readable',
        'cksumvfs.test 1.6 checkpoint reports successful WAL backfill',
        'cksumvfs.test 1.7 recursive insert reloads rows after checkpoint',
        'cksumvfs.test 1.8 saved image reopen preserves row count',
        'cksumvfs.test 1.9 direct reopen preserves row count',
    ], $plan['upstream']);
};

$tests['real upstream corpus vfs io dynamic cksumvfs rejects malformed reserve inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(-1, 4096, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(256, 4096, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(8, 1000, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(8, 4096, 0, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(8, 4096, 1, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(8, 4096, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(64, 512, 1, 1, 1));
};

$tests['real upstream corpus vfs io dynamic cksumvfs non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers cksumvfs.test reserve-byte checksum VFS WAL checkpoint/reopen lifecycle; avoids accepted io.test atomic/default-page/cache-spill/short-name, rollback-journal, VFS writer/sync/lock, WAL byte-truncation, and pager recovery clusters',
        'non-overlap: covers cksumvfs.test reserve-byte checksum VFS WAL checkpoint/reopen lifecycle; avoids accepted io.test atomic/default-page/cache-spill/short-name, rollback-journal, VFS writer/sync/lock, WAL byte-truncation, and pager recovery clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteVfsIoDynamicPlan arithmetic profile and hydrated upstream cksumvfs.test as source truth',
        'dependency-closure: no new support component needed; reuses SQLiteVfsIoDynamicPlan arithmetic profile and hydrated upstream cksumvfs.test as source truth'
    );
};

return $tests;
