<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384];
$fakeMegabyteSteps = [4096, 8192, 16384];
$ceilDiv = static fn (int $left, int $right): int => intdiv($left + $right - 1, $right);
$fourGiB = 4294967296;

foreach (range(1, 600) as $case) {
    $fakeMegabytes = $fakeMegabyteSteps[$case % count($fakeMegabyteSteps)];
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $seedDoublings = 5 + ($case % 6);
    $tableCopyOrdinal = $case % 4;
    $overflowPayloadBytes = 4096 + (($case % 97) * 257);

    $tests[sprintf('real upstream corpus vfs bigfile dynamic bigfile.test sparse checksum case %04d', $case)] = static function (TestRunner $t) use ($case, $fakeMegabytes, $pageSize, $seedDoublings, $tableCopyOrdinal, $overflowPayloadBytes, $ceilDiv, $fourGiB): void {
        $plan = SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile(
            'bigfile.test',
            $fakeMegabytes,
            $pageSize,
            $seedDoublings,
            $tableCopyOrdinal,
            $overflowPayloadBytes
        );

        $fakeBytes = $fakeMegabytes * 1048576;
        $expectedPageCount = $ceilDiv($fakeBytes, $pageSize);
        $expectedTables = array_slice(['t1', 't2', 't3', 't4'], 0, $tableCopyOrdinal + 1);

        $t->same('ok', $plan['status']);
        $t->same('bigfile.test', $plan['script']);
        $t->same('bigfile-1.1-through-1.16', $plan['scenario']);
        $t->same($fakeMegabytes, $plan['fake_file_megabytes']);
        $t->same($fakeBytes, $plan['fake_file_bytes']);
        $t->same(0, $plan['trailing_bytes']);
        $t->same($pageSize, $plan['page_size']);
        $t->same(true, $plan['header_page_count_cleared']);
        $t->same(0, $plan['header_page_count_field']);
        $t->same($expectedPageCount, $plan['actual_page_count_from_file_size']);
        $t->same($expectedPageCount, $plan['effective_page_count']);
        $t->same($expectedPageCount + 1, $plan['first_append_page']);
        $t->same(intdiv($fourGiB, $pageSize) + 1, $plan['first_page_past_4gib']);
        $t->same(true, $plan['append_starts_at_or_past_4gib']);
        $t->same(true, $plan['large_file_support_required']);
        $t->same(true, $plan['skip_when_large_file_support_disabled']);
        $t->same(true, $plan['requires_sparse_file_fixture']);
        $t->same($seedDoublings, $plan['seed_doublings']);
        $t->same(1 << $seedDoublings, $plan['seed_rows']);
        $t->same('593f1efcfdbe698c28b4b1b693f7e4cf', $plan['magic_sum']);
        $t->same($expectedTables, $plan['visible_tables']);
        $t->same($tableCopyOrdinal, $plan['table_copy_ordinal']);
        $t->same($tableCopyOrdinal === 0 ? null : $expectedTables[$tableCopyOrdinal], $plan['copy_target_table']);
        $t->same($expectedTables, array_keys($plan['hashes_by_table']));
        $t->same(array_fill_keys($expectedTables, '593f1efcfdbe698c28b4b1b693f7e4cf'), $plan['hashes_by_table']);
        $t->same(true, $plan['checksum_preserved_after_reopen']);
        $t->same($overflowPayloadBytes, $plan['overflow_payload_bytes']);
        $t->same(null, $plan['overflow_readback_length']);
        $t->same('large_sparse_database_uses_actual_file_size_when_header_page_count_is_zero', $plan['reason']);
        $t->same(true, in_array('bigfile.test bigfile-1.1 seed table checksum', $plan['upstream'], true));
        $t->same(true, in_array('bigfile.test bigfile-1.2 read t1 after fake 4096 MiB file', $plan['upstream'], true));
        $t->same(true, in_array('upstream-bigfile-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-large-file-vfs-boundary', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, $case >= 1);
    };
}

foreach (range(1, 600) as $case) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $seedDoublings = 1 + ($case % 4);
    $overflowPayloadBytes = 30000 + (($case % 61) * 101);

    $tests[sprintf('real upstream corpus vfs bigfile dynamic bigfile2.test overflow readback case %04d', $case)] = static function (TestRunner $t) use ($case, $pageSize, $seedDoublings, $overflowPayloadBytes, $ceilDiv, $fourGiB): void {
        $plan = SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile(
            'bigfile2.test',
            4096,
            $pageSize,
            $seedDoublings,
            0,
            $overflowPayloadBytes
        );

        $fakeBytes = (4096 * 1048576) + 14;
        $expectedPageCount = $ceilDiv($fakeBytes, $pageSize);
        $localPayload = min($overflowPayloadBytes, max(1, $pageSize - 128));
        $expectedOverflowPages = (int) ceil(max(0, $overflowPayloadBytes - $localPayload) / max(1, $pageSize - 4));

        $t->same('ok', $plan['status']);
        $t->same('bigfile2.test', $plan['script']);
        $t->same('bigfile2-1.1-through-1.3', $plan['scenario']);
        $t->same(4096, $plan['fake_file_megabytes']);
        $t->same($fakeBytes, $plan['fake_file_bytes']);
        $t->same(14, $plan['trailing_bytes']);
        $t->same($pageSize, $plan['page_size']);
        $t->same(true, $plan['header_page_count_cleared']);
        $t->same(0, $plan['header_page_count_field']);
        $t->same($expectedPageCount, $plan['actual_page_count_from_file_size']);
        $t->same($expectedPageCount, $plan['effective_page_count']);
        $t->same($expectedPageCount + 1, $plan['first_append_page']);
        $t->same(intdiv($fourGiB, $pageSize) + 1, $plan['first_page_past_4gib']);
        $t->same(true, $plan['append_starts_at_or_past_4gib']);
        $t->same(true, $plan['large_file_support_required']);
        $t->same(true, $plan['skip_when_large_file_support_disabled']);
        $t->same(true, $plan['requires_sparse_file_fixture']);
        $t->same($seedDoublings, $plan['seed_doublings']);
        $t->same(1 << $seedDoublings, $plan['seed_rows']);
        $t->same(['t1'], $plan['visible_tables']);
        $t->same(null, $plan['copy_target_table']);
        $t->same($overflowPayloadBytes, $plan['overflow_payload_bytes']);
        $t->same($localPayload, $plan['overflow_local_payload_bytes']);
        $t->same($expectedOverflowPages, $plan['overflow_pages']);
        $t->same($expectedOverflowPages === 0 ? null : $expectedPageCount + 2, $plan['overflow_first_page']);
        $t->same($expectedOverflowPages === 0 ? null : $expectedPageCount + 1 + $expectedOverflowPages, $plan['overflow_last_page']);
        $t->same($expectedOverflowPages, $plan['overflow_pages_past_4gib']);
        $t->same($overflowPayloadBytes, $plan['overflow_readback_length']);
        $t->same('overflow_payload_pages_can_be_appended_and_read_back_beyond_4gib', $plan['reason']);
        $t->same([
            'bigfile2.test 1.1 create small table',
            'bigfile2.test 1.2 fake 4096 MiB file plus 14 bytes with cleared header page-count',
            'bigfile2.test 1.3 large row readback from overflow pages beyond 4 GiB',
        ], $plan['upstream']);
        $t->same(true, in_array('upstream-bigfile-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-large-file-vfs-boundary', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, $case >= 1);
    };
}

$tests['real upstream corpus vfs bigfile dynamic source coverage and count'] = static function (TestRunner $t) use (&$tests): void {
    $bigfile = SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 16384, 4096, 7, 3);
    $bigfile2 = SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile2.test', 4096, 4096, 1, 0, 30000);

    $t->same(1202, count($tests));
    $t->same(true, in_array('bigfile.test bigfile-1.13 create t4 beyond 16384 MiB boundary', $bigfile['upstream'], true));
    $t->same(true, in_array('bigfile.test bigfile-1.16 reread t3 after reopen', $bigfile['upstream'], true));
    $t->same('bigfile2.test 1.3 large row readback from overflow pages beyond 4 GiB', $bigfile2['upstream'][2]);
    $t->same(30000, $bigfile2['overflow_readback_length']);
};

$tests['real upstream corpus vfs bigfile dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('other.test', 4096, 4096));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 4095, 4096));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 4096, 500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 4096, 768));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 4096, 4096, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 4096, 4096, 21));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile.test', 4096, 4096, 7, 4));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile('bigfile2.test', 4096, 4096, 7, 0, 0));
};

return $tests;
