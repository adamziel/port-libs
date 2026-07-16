<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteChecksumVfsPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096, 8192];
$walRowCounts = [1, 2, 5, 10, 20, 25, 50, 100];
$payloadMatrices = [
    [5000],
    [5000, 1],
    [5000, 750],
    [750, 751, 752],
    [1, 2, 3, 4, 5],
];
$case = 0;

foreach ($pageSizes as $pageSize) {
    foreach ([0, 8, 16, 32, 64] as $reserveBytes) {
        foreach ($walRowCounts as $walRows) {
            foreach ($payloadMatrices as $payloadSizes) {
                $case++;
                $initialRows = ($case % 9) * 250;
                $restoreBeforeReopen = ($case % 7) !== 0;
                $scenario = sprintf('cksumvfs-1.dynamic.%04d', $case);

                $tests[sprintf('real upstream corpus vfs checksum dynamic %04d page %d reserve %d rows %d', $case, $pageSize, $reserveBytes, $walRows)] = static function (TestRunner $t) use ($scenario, $pageSize, $reserveBytes, $initialRows, $walRows, $payloadSizes, $restoreBeforeReopen): void {
                    $plan = SQLiteChecksumVfsPlan::checksumVfsProfile($scenario, $pageSize, $reserveBytes, $initialRows, $walRows, $payloadSizes, $restoreBeforeReopen);
                    $payloadBytes = array_sum($payloadSizes);
                    $usableBytes = $pageSize - $reserveBytes;
                    $expectedInitialPages = $initialRows === 0 ? 0 : (int) ceil(($initialRows * max(1, (int) ceil($payloadBytes / count($payloadSizes)))) / $usableBytes);
                    $expectedWalPages = (int) ceil(($walRows * max(1, min($usableBytes, (int) ceil(($payloadBytes + $walRows) / count($payloadSizes))))) / $usableBytes);
                    $expectedReopenRows = $restoreBeforeReopen ? $walRows : 0;

                    $t->same('ok', $plan['status']);
                    $t->same('cksumvfs.test', $plan['script']);
                    $t->same($scenario, $plan['scenario']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($reserveBytes, $plan['reserve_bytes']);
                    $t->same($usableBytes, $plan['usable_bytes']);
                    $t->same($payloadBytes, $plan['payload_bytes']);
                    $t->same($initialRows, $plan['initial_rows']);
                    $t->same($walRows, $plan['wal_rows']);
                    $t->same($expectedInitialPages, $plan['initial_pages']);
                    $t->same($expectedWalPages, $plan['wal_pages']);
                    $t->same($pageSize * max(1, 1 + $expectedInitialPages), $plan['database_bytes']);
                    $t->same($pageSize + ($expectedWalPages * ($pageSize + 24)), $plan['wal_bytes_before_checkpoint']);
                    $t->same('wal', $plan['journal_mode']);
                    $t->same(['busy' => 0, 'log' => $expectedWalPages, 'checkpointed' => $expectedWalPages], $plan['checkpoint']);
                    $t->same($reserveBytes === 8, $plan['reserve_bytes_preserved']);
                    $t->same($reserveBytes > 0, $plan['checksums_cover_reserved_tail']);
                    $t->same(true, $plan['delete_before_wal_insert']);
                    $t->same($restoreBeforeReopen, $plan['restore_before_reopen']);
                    $t->same($expectedReopenRows, $plan['reopen_count_after_restore']);
                    $t->same($expectedReopenRows, $plan['reopen_count_after_close']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, in_array('sqlite-upstream-cksumvfs-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-page-reserve-bytes', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-wal-checkpoint', $plan['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                    $t->same([
                        'cksumvfs.test cksumvfs-1.0 reserve-bytes page-size setup',
                        'cksumvfs.test cksumvfs-1.1 initial row readback',
                        'cksumvfs.test cksumvfs-1.3 large insert commit',
                        'cksumvfs.test cksumvfs-1.5 WAL delete checkpoint setup',
                        'cksumvfs.test cksumvfs-1.6 checkpoint returns zero busy',
                        'cksumvfs.test cksumvfs-1.7 recursive insert count',
                        'cksumvfs.test cksumvfs-1.8 restore and reopen count',
                        'cksumvfs.test cksumvfs-1.9 close and reopen count',
                    ], $plan['upstream']);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs checksum dynamic owns exactly one thousand cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs checksum dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('', 4096, 8, 1, 100, [5000]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('cksumvfs-bad-page', 1000, 8, 1, 100, [5000]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('cksumvfs-bad-reserve', 4096, 4096, 1, 100, [5000]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('cksumvfs-bad-rows', 4096, 8, -1, 100, [5000]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('cksumvfs-bad-wal', 4096, 8, 1, 0, [5000]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('cksumvfs-bad-payload', 4096, 8, 1, 100, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteChecksumVfsPlan::checksumVfsProfile('cksumvfs-negative-payload', 4096, 8, 1, 100, [5000, -1]));
};

return $tests;
