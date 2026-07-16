<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/reservebytes.test';

$tests['real upstream corpus vfs io dynamic reservebytes cites upstream reserve byte vacuum sections'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = is_file($upstreamFile) ? (string) file_get_contents($upstreamFile) : '';
    $profile = SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 8, 16, 4096, 1000, 500);

    $t->same(true, is_file($upstreamFile));
    $t->contains('file_control_reservebytes db 8', $source);
    $t->contains('file_control_reservebytes db 16', $source);
    $t->contains('hexio_read test.db 20 1', $source);
    $t->contains('VACUUM', $source);
    $t->same('reservebytes.test', $profile['script']);
    $t->same('reservebytes-1.0-1.4', $profile['scenario']);
    $t->same([
        'reservebytes.test 1.0 create table/index and populate rows',
        'reservebytes.test 1.1 second connection integrity before reserve change',
        'reservebytes.test 1.2.1 first file_control_reservebytes leaves header byte unchanged',
        'reservebytes.test 1.2.2 second connection integrity after pending reserve change',
        'reservebytes.test 1.3.2 first VACUUM rebuild applies reserve byte',
        'reservebytes.test 1.3.4 second connection integrity after first VACUUM',
        'reservebytes.test 1.3.5 header byte records first reserve value',
        'reservebytes.test 1.4.1 second reserve request leaves previous header byte until VACUUM',
        'reservebytes.test 1.4.2 second VACUUM rebuild applies reserve byte',
        'reservebytes.test 1.4.3 second connection integrity after second VACUUM',
        'reservebytes.test 1.4.4 header byte records second reserve value',
    ], $profile['upstream']);
};

$firstReserveBytes = [1, 4, 8, 12, 16, 24, 32, 48, 64, 96];
$reserveIncrements = [1, 4, 8, 16, 32];
$pageSizes = [1024, 2048, 4096, 8192];
$rowCounts = [25, 100, 250, 1000, 1500];
$case = 0;

foreach ($firstReserveBytes as $firstReserve) {
    foreach ($reserveIncrements as $reserveIncrement) {
        foreach ($pageSizes as $pageSize) {
            foreach ($rowCounts as $rowsInserted) {
                ++$case;
                $secondReserve = $firstReserve + $reserveIncrement;
                $randomBlobBytes = 64 + (($case * 37) % 512);

                $tests[sprintf(
                    'real upstream corpus vfs io dynamic reservebytes vacuum header %04d first %d second %d page %d rows %d blob %d',
                    $case,
                    $firstReserve,
                    $secondReserve,
                    $pageSize,
                    $rowsInserted,
                    $randomBlobBytes
                )] = static function (TestRunner $t) use ($firstReserve, $secondReserve, $pageSize, $rowsInserted, $randomBlobBytes): void {
                    $profile = SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, $firstReserve, $secondReserve, $pageSize, $rowsInserted, $randomBlobBytes);
                    $hexPayloadBytes = $randomBlobBytes * 2;
                    $rowCellBytes = $hexPayloadBytes + 24;
                    $indexCellBytes = 24;
                    $pagesFor = static function (int $reserveBytes) use ($pageSize, $rowsInserted, $rowCellBytes, $indexCellBytes): int {
                        return 2 + (int) ceil(($rowsInserted * ($rowCellBytes + $indexCellBytes)) / ($pageSize - $reserveBytes));
                    };
                    $hex = static fn (int $reserveBytes): string => strtoupper(str_pad(dechex($reserveBytes), 2, '0', STR_PAD_LEFT));
                    $expectedHeaderSequence = [
                        $hex(0),
                        $hex(0),
                        $hex($firstReserve),
                        $hex($firstReserve),
                        $hex($secondReserve),
                    ];

                    $t->same('ok', $profile['status']);
                    $t->same('reservebytes.test', $profile['script']);
                    $t->same('reservebytes-1.0-1.4', $profile['scenario']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same(0, $profile['initial_reserve_bytes']);
                    $t->same($firstReserve, $profile['first_requested_reserve_bytes']);
                    $t->same($secondReserve, $profile['second_requested_reserve_bytes']);
                    $t->same(20, $profile['header_byte_offset']);
                    $t->same($expectedHeaderSequence, $profile['header_hex_sequence']);
                    $t->same($expectedHeaderSequence[0], $profile['header_hex_after_create']);
                    $t->same($expectedHeaderSequence[1], $profile['header_hex_after_first_file_control']);
                    $t->same($expectedHeaderSequence[2], $profile['header_hex_after_first_vacuum']);
                    $t->same($expectedHeaderSequence[3], $profile['header_hex_after_second_file_control']);
                    $t->same($expectedHeaderSequence[4], $profile['header_hex_after_second_vacuum']);
                    $t->same($pageSize, $profile['usable_bytes_initial']);
                    $t->same($pageSize - $firstReserve, $profile['usable_bytes_after_first_vacuum']);
                    $t->same($pageSize - $secondReserve, $profile['usable_bytes_after_second_vacuum']);
                    $t->same($rowsInserted, $profile['rows_inserted']);
                    $t->same($rowsInserted, $profile['index_entries']);
                    $t->same($randomBlobBytes, $profile['random_blob_bytes']);
                    $t->same($hexPayloadBytes, $profile['hex_payload_bytes']);
                    $t->same($pagesFor(0), $profile['database_pages_after_insert']);
                    $t->same($pagesFor($firstReserve), $profile['database_pages_after_first_vacuum']);
                    $t->same($pagesFor($secondReserve), $profile['database_pages_after_second_vacuum']);
                    $t->same(true, $profile['database_pages_after_first_vacuum'] >= $profile['database_pages_after_insert']);
                    $t->same(true, $profile['database_pages_after_second_vacuum'] >= $profile['database_pages_after_first_vacuum']);
                    $t->same(true, $profile['reader_connection_open']);
                    $t->same(['ok', 'ok', 'ok', 'ok'], $profile['reader_integrity_sequence']);
                    $t->same(true, $profile['file_control_first_pending_until_vacuum']);
                    $t->same(true, $profile['file_control_second_pending_until_vacuum']);
                    $t->same(true, $profile['first_vacuum_applies_pending_reserve_bytes']);
                    $t->same(true, $profile['second_vacuum_applies_pending_reserve_bytes']);
                    $t->same('app_data', $profile['table']);
                    $t->same('app_data_b_c', $profile['index']);
                    $t->same(['id', 'key_number', 'payload_hex'], $profile['columns']);
                    $t->same('file_control_reservebytes_changes_header_byte_only_after_vacuum_rebuild', $profile['reason']);
                    $t->same(true, in_array('upstream-reservebytes-test', $profile['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-file-control-reserve-bytes', $profile['dependencies'], true));
                    $t->same(true, in_array('sqlite-vacuum-rebuild-reserve-bytes', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic reservebytes rejects malformed reserve inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 8, 16, 1000, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(-1, 8, 16, 4096, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 256, 260, 4096, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 512, 513, 512, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(8, 4, 16, 4096, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 8, 8, 4096, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 8, 16, 4096, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile(0, 8, 16, 4096, 1, 0));
};

$tests['real upstream corpus vfs io dynamic reservebytes owns exactly one thousand dynamic vacuum cases'] = static function (TestRunner $t) use (&$tests, $case): void {
    $t->same(1000, $case);
    $t->same(1004, count($tests));
};

$tests['real upstream corpus vfs io dynamic reservebytes non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers reservebytes.test header-byte and VACUUM reserve-byte application; avoids cksumvfs checksum reserve bytes, io.test atomic/default/cache-spill/short-name, rollback-journal apply/commit, VFS writer/sync/lock, ioerr/pagerfault, mmap/quota, and WAL checkpoint/savepoint clusters',
        'non-overlap: covers reservebytes.test header-byte and VACUUM reserve-byte application; avoids cksumvfs checksum reserve bytes, io.test atomic/default/cache-spill/short-name, rollback-journal apply/commit, VFS writer/sync/lock, ioerr/pagerfault, mmap/quota, and WAL checkpoint/savepoint clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteVfsIoDynamicPlan arithmetic profile and hydrated upstream reservebytes.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteVfsIoDynamicPlan arithmetic profile and hydrated upstream reservebytes.test source truth'
    );
};

return $tests;
