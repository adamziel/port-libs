<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach ([0, 4, 8, 16, 24, 32, 48, 64, 96, 128] as $reserveBytes) {
    foreach ([1024, 2048, 4096, 8192] as $pageSize) {
        foreach ([7, 13, 29, 53, 97] as $largeRows) {
            foreach ([3, 11, 19, 41, 73] as $smallRows) {
                ++$case;
                $largePayloadBytes = 180 + (($case * 37) % 700);
                $smallPayloadBytes = 25 + (($case * 19) % 180);

                $name = sprintf(
                    'real upstream corpus vfs io checksum reserve cksumvfs.test case %04d reserve %03d page %05d large %03d small %03d',
                    $case,
                    $reserveBytes,
                    $pageSize,
                    $largeRows,
                    $smallRows
                );

                $tests[$name] = static function (TestRunner $t) use ($reserveBytes, $pageSize, $largeRows, $smallRows, $largePayloadBytes, $smallPayloadBytes): void {
                    $profile = SQLiteVfsIoDynamicPlan::checksumReserveProfile(
                        $reserveBytes,
                        $pageSize,
                        $largeRows,
                        $smallRows,
                        $largePayloadBytes,
                        $smallPayloadBytes
                    );

                    $usableBytes = $pageSize - $reserveBytes;
                    $largePages = intdiv((int) ceil(($largeRows * ($largePayloadBytes + $reserveBytes + 16)) / $usableBytes) * $usableBytes, $usableBytes);
                    $smallPages = intdiv((int) ceil(($smallRows * ($smallPayloadBytes + $reserveBytes + 16)) / $usableBytes) * $usableBytes, $usableBytes);

                    $t->same('ok', $profile['status']);
                    $t->same('cksumvfs.test', $profile['script']);
                    $t->same($reserveBytes, $profile['reserve_bytes']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same($usableBytes, $profile['usable_bytes']);
                    $t->same($largeRows, $profile['large_rows_inserted']);
                    $t->same($largePayloadBytes, $profile['large_payload_bytes']);
                    $t->same($largePages, $profile['large_payload_pages']);
                    $t->same($largeRows, $profile['large_count_after_commit']);
                    $t->same('wal', $profile['journal_mode_after_delete']);
                    $t->same(['busy' => 0, 'log' => 'nonzero', 'checkpointed' => 'nonzero'], $profile['checkpoint_result']);
                    $t->same($smallRows, $profile['small_rows_inserted']);
                    $t->same($smallPayloadBytes, $profile['small_payload_bytes']);
                    $t->same($smallPages, $profile['small_payload_pages']);
                    $t->same($smallRows, $profile['small_count_before_reopen']);
                    $t->same($smallRows, $profile['small_count_after_restore_reopen']);
                    $t->same($smallRows, $profile['small_count_after_plain_reopen']);
                    $t->same($reserveBytes > 0, $profile['checksum_trailer_reserved']);
                    $t->same(['ok', 'ok', 'ok', 'ok'], $profile['integrity_sequence']);
                    $t->same(true, in_array('upstream-cksumvfs-reserve-bytes', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io checksum reserve cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::checksumReserveProfile(8, 4096, 13, 11, 350, 90);

    $t->same([
        'cksumvfs.test 1.3',
        'cksumvfs.test 1.4',
        'cksumvfs.test 1.5',
        'cksumvfs.test 1.6',
        'cksumvfs.test 1.7',
        'cksumvfs.test 1.8',
        'cksumvfs.test 1.9',
    ], $profile['upstream']);
};

return $tests;
