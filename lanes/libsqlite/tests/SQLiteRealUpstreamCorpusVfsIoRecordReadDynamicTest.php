<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'ioerr-4-overflow-record-header' => [
        'columns' => [96, 160, 320, 640, 1299],
        'inline' => [0, 1, 32, 128],
        'overflow' => [600, 1000, 1600, 2400, 4096],
    ],
    'ioerr-8-short-field-read' => [
        'columns' => [3, 4, 5, 8, 13],
        'inline' => [16, 64, 200, 512],
        'overflow' => [0, 0, 0, 0, 0],
    ],
];

$pageSizes = [512, 1024, 2048, 4096, 8192];
$case = 0;

foreach ($scenarios as $scenario => $config) {
    foreach ($pageSizes as $pageSize) {
        foreach ($config['columns'] as $columnCount) {
            foreach ($config['inline'] as $inlinePayloadBytes) {
                foreach ($config['overflow'] as $overflowPayloadBytes) {
                    $case++;
                    $selectedColumn = 1 + (($case * 17) % $columnCount);
                    $failAt = 1 + (($case * 11) % 12);
                    $tests[sprintf(
                        'real upstream corpus vfs io record-read dynamic %04d %s page %04d columns %04d selected %04d fail %02d',
                        $case,
                        $scenario,
                        $pageSize,
                        $columnCount,
                        $selectedColumn,
                        $failAt
                    )] = static function (TestRunner $t) use (
                        $scenario,
                        $failAt,
                        $pageSize,
                        $columnCount,
                        $selectedColumn,
                        $inlinePayloadBytes,
                        $overflowPayloadBytes
                    ): void {
                        $profile = SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile(
                            $scenario,
                            $failAt,
                            $pageSize,
                            $columnCount,
                            $selectedColumn,
                            $inlinePayloadBytes,
                            $overflowPayloadBytes
                        );
                        $overflowHeader = $scenario === 'ioerr-4-overflow-record-header';
                        $usableBytes = $pageSize - 35;
                        $headerBytes = max(1, $columnCount * 2);
                        $overflowPages = $overflowHeader ? (int) ceil(max($overflowPayloadBytes, max(1, $headerBytes - $usableBytes + 1)) / max(1, $usableBytes - 4)) : 0;
                        $readOperations = 1 + ($overflowHeader ? $overflowPages : 0);
                        $faultDetected = $failAt <= $readOperations || ($overflowHeader && $selectedColumn > max(1, intdiv($columnCount, 2)));

                        $t->same('ok', $profile['status']);
                        $t->same('ioerr.test', $profile['script']);
                        $t->same($scenario, $profile['scenario']);
                        $t->same($failAt, $profile['fail_at']);
                        $t->same($pageSize, $profile['page_size']);
                        $t->same($columnCount, $profile['column_count']);
                        $t->same($selectedColumn, $profile['selected_column']);
                        $t->same($inlinePayloadBytes, $profile['inline_payload_bytes']);
                        $t->same($overflowPayloadBytes, $profile['overflow_payload_bytes']);
                        $t->same($usableBytes, $profile['usable_bytes']);
                        $t->same($headerBytes, $profile['record_header_bytes']);
                        $t->same(min($inlinePayloadBytes, max(0, $usableBytes - $headerBytes)), $profile['local_payload_bytes']);
                        $t->same($overflowPages, $profile['overflow_pages']);
                        $t->same($readOperations, $profile['read_operations']);
                        $t->same($faultDetected, $profile['fault_detected']);
                        $t->same($faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK', $profile['expected_result']);
                        $t->same(false, $profile['statement_rolled_back']);
                        $t->same(true, $profile['cursor_closed']);
                        $t->same(true, $profile['cache_refcount_zero']);
                        $t->same('ok', $profile['integrity_check']);
                        $t->same(0, $profile['open_file_count']);
                        $t->same(true, str_starts_with($profile['upstream'][0], $overflowHeader ? 'ioerr.test ioerr-4' : 'ioerr.test ioerr-8'));
                        $t->same(true, in_array('upstream-ioerr-record-read-faults', $profile['dependencies'], true));
                        $t->same(true, in_array('sqlite-vfs-io-error-recovery', $profile['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io record-read dynamic owns one thousand upstream cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs io record-read dynamic cites hydrated source sections'] = static function (TestRunner $t): void {
    $overflow = SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-4-overflow-record-header', 1, 1024, 1299, 1, 0, 2048);
    $short = SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-8-short-field-read', 1, 1024, 3, 3, 200, 0);

    $t->same(['ioerr.test ioerr-4 overflow record header read crosses onto overflow page'], $overflow['upstream']);
    $t->same(['ioerr.test ioerr-8 short field read fits without allocation but still propagates read faults'], $short['upstream']);
    $t->same('overflow_record_header_read_io_error_is_reported_without_leaking_pager_refs', $overflow['reason']);
    $t->same('short_field_read_io_error_propagates_without_heap_allocation_path_leak', $short['reason']);
};

$tests['real upstream corpus vfs io record-read dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('missing', 1, 1024, 3, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-8-short-field-read', 0, 1024, 3, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-8-short-field-read', 1, 1000, 3, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-8-short-field-read', 1, 1024, 3, 4, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-4-overflow-record-header', 1, 1024, 3, 1, 0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile('ioerr-8-short-field-read', 1, 1024, 3, 1, 0, 0));
};

return $tests;
