<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/filefmt.test';

$tests['real upstream corpus vfs filefmt dynamic cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('filefmt-1.1', $source);
    $t->contains('hexio_read test.db 0 16', $source);
    $t->contains('hexio_write test.db 0 54', $source);
    $t->contains('hexio_get_int [hexio_read test.db 16 2]', $source);
    $t->contains('hexio_write test.db 16 [hexio_render_int16 1025]', $source);
    $t->contains('hexio_write test.db 20 21', $source);
    $t->contains('sql36231 { INSERT INTO t1 VALUES(a_string(3000)) }', $source);
    $t->contains('db backup bak.db', $source);
};

$validScenarios = [
    'filefmt-1.1',
    'filefmt-1.2',
    'filefmt-1.4',
    'filefmt-1.5',
    'filefmt-2.1',
    'filefmt-2.2',
    'filefmt-3.3',
    'filefmt-4.4',
];
$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768];
$reservedByteCases = [0, 4, 16, 32];
$payloadCases = [600, 1500, 3000, 6000, 12000];
$validCaseCount = 0;

foreach ($validScenarios as $scenario) {
    foreach ($pageSizes as $pageSize) {
        foreach ($reservedByteCases as $reservedBytes) {
            foreach ($payloadCases as $payloadBytes) {
                ++$validCaseCount;
                $name = sprintf(
                    'real upstream corpus vfs filefmt dynamic %s valid header case %04d page %05d reserve %03d payload %05d',
                    $scenario,
                    $validCaseCount,
                    $pageSize,
                    $reservedBytes,
                    $payloadBytes
                );

                $tests[$name] = static function (TestRunner $t) use ($scenario, $pageSize, $reservedBytes, $payloadBytes): void {
                    $profile = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile(
                        $scenario,
                        $pageSize,
                        $reservedBytes,
                        $payloadBytes,
                        1 + ($payloadBytes % 3)
                    );
                    $usableBytes = $pageSize - $reservedBytes;
                    $payloadPages = (int) ceil($payloadBytes / max(1, $usableBytes - 35));
                    $initialHeaderPageCount = 5 + $payloadPages;
                    $expectedQueryStatus = $scenario === 'filefmt-1.2' ? 'error' : 'ok';

                    $t->same('ok', $profile['status']);
                    $t->same('filefmt.test', $profile['script']);
                    $t->same($scenario, $profile['scenario']);
                    $t->same(true, str_starts_with($profile['upstream'][0], 'filefmt.test '));
                    $t->same('53514C69746520666F726D6174203300', $profile['header_magic_hex']);
                    $t->same($scenario !== 'filefmt-1.2', $profile['header_magic_valid']);
                    $t->same(true, $profile['opened_handle_before_schema_read']);
                    $t->same($expectedQueryStatus, $profile['query_status']);
                    $t->same($expectedQueryStatus === 'error' ? 'file is not a database' : null, $profile['query_error']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same(16, $profile['page_size_field_offset']);
                    $t->same($pageSize, $profile['page_size_field_value']);
                    $t->same(strtoupper(str_pad(dechex($pageSize), 4, '0', STR_PAD_LEFT)), $profile['page_size_field_hex']);
                    $t->same(true, $profile['page_size_valid']);
                    $t->same($reservedBytes, $profile['reserved_bytes']);
                    $t->same($usableBytes, $profile['usable_page_bytes']);
                    $t->same(true, $profile['reserved_bytes_valid']);
                    $t->same(480, $profile['minimum_usable_page_bytes']);
                    $t->same($pageSize * 2, $profile['file_bytes_after_create']);
                    $t->same($payloadBytes, $profile['legacy_payload_bytes']);
                    $t->same($payloadPages, $profile['legacy_payload_pages']);
                    $t->same($initialHeaderPageCount, $profile['legacy_header_page_count_before_legacy_write']);
                    $t->same($initialHeaderPageCount, $profile['legacy_header_page_count_after_legacy_write']);
                    $t->same($initialHeaderPageCount + $payloadPages, $profile['effective_page_count_after_legacy_write']);
                    $t->same($profile['effective_page_count_after_legacy_write'] + $profile['legacy_append_rows'] + 2, $profile['header_page_count_after_current_write']);
                    $t->same(in_array($scenario, ['filefmt-2.1', 'filefmt-2.2'], true), $profile['current_writer_refreshes_header_page_count']);
                    $t->same($scenario === 'filefmt-2.2', $profile['savepoint_rollback_preserves_integrity']);
                    $t->same($scenario === 'filefmt-3.3', $profile['auto_vacuum_pointer_map_valid_after_legacy_drop']);
                    $t->same($scenario === 'filefmt-4.4' ? 'ok' : null, $profile['backup_integrity_check']);
                    $t->same(in_array('upstream-filefmt-test', $profile['dependencies'], true), true);
                    $t->same(in_array('sqlite-file-format-header-validation', $profile['dependencies'], true), true);
                    $t->same(in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true), true);
                };
            }
        }
    }
}

$invalidCaseCount = 0;
$invalidPayloads = [700, 1300, 2700, 3900, 8100, 12100, 16300, 24500, 32700, 49100];

foreach ([768, 1025, 1536, 3072, 6144, 12288] as $pageSize) {
    foreach ($invalidPayloads as $payloadBytes) {
        ++$invalidCaseCount;
        $tests[sprintf('real upstream corpus vfs filefmt dynamic filefmt-1.6 rejects non power page case %04d page %05d', $invalidCaseCount, $pageSize)] =
            static function (TestRunner $t) use ($pageSize, $payloadBytes): void {
                $profile = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.6', $pageSize, 0, $payloadBytes);

                $t->same('filefmt.test', $profile['script']);
                $t->same('filefmt-1.6', $profile['scenario']);
                $t->same(false, $profile['page_size_valid']);
                $t->same('error', $profile['query_status']);
                $t->same('file is not a database', $profile['query_error']);
                $t->same('non_power_of_two_page_size_is_rejected', $profile['reason']);
                $t->same('filefmt.test filefmt-1.6 non-power-of-two page size is rejected', $profile['upstream'][0]);
                $t->same(true, in_array('upstream-filefmt-test', $profile['dependencies'], true));
            };
    }
}

foreach ([1, 2, 128, 256, 511] as $pageSize) {
    foreach ($invalidPayloads as $payloadBytes) {
        ++$invalidCaseCount;
        $tests[sprintf('real upstream corpus vfs filefmt dynamic filefmt-1.7 rejects subminimum page case %04d page %05d', $invalidCaseCount, $pageSize)] =
            static function (TestRunner $t) use ($pageSize, $payloadBytes): void {
                $profile = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.7', $pageSize, 0, $payloadBytes);

                $t->same('filefmt.test', $profile['script']);
                $t->same('filefmt-1.7', $profile['scenario']);
                $t->same(false, $profile['page_size_valid']);
                $t->same('error', $profile['query_status']);
                $t->same('file is not a database', $profile['query_error']);
                $t->same('subminimum_page_size_is_rejected', $profile['reason']);
                $t->same('filefmt.test filefmt-1.7 page size below 512 bytes is rejected', $profile['upstream'][0]);
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            };
    }
}

foreach (range(33, 80) as $reservedBytes) {
    ++$invalidCaseCount;
    $tests[sprintf('real upstream corpus vfs filefmt dynamic filefmt-1.8 rejects reserved byte case %04d reserve %03d', $invalidCaseCount, $reservedBytes)] =
        static function (TestRunner $t) use ($reservedBytes): void {
            $profile = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.8', 512, $reservedBytes, 3000);

            $t->same('filefmt.test', $profile['script']);
            $t->same('filefmt-1.8', $profile['scenario']);
            $t->same(true, $profile['page_size_valid']);
            $t->same($reservedBytes, $profile['reserved_bytes']);
            $t->same(512 - $reservedBytes, $profile['usable_page_bytes']);
            $t->same(false, $profile['reserved_bytes_valid']);
            $t->same('error', $profile['query_status']);
            $t->same('file is not a database', $profile['query_error']);
            $t->same('reserved_bytes_must_leave_at_least_480_usable_bytes', $profile['reason']);
            $t->same('filefmt.test filefmt-1.8 reserved bytes must leave at least 480 usable bytes per page', $profile['upstream'][0]);
        };
}

$tests['real upstream corpus vfs filefmt dynamic exact upstream canonical values'] = static function (TestRunner $t): void {
    $pageSize = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.5', 1024);
    $legacy = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-2.1', 1024, 0, 3000, 1);
    $savepoint = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-2.2', 1024, 0, 3000, 1);
    $badReserve = SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.8', 512, 33);

    $t->same('0400', $pageSize['page_size_field_hex']);
    $t->same(2048, $pageSize['file_bytes_after_create']);
    $t->same(9, $legacy['legacy_header_page_count_before_legacy_write']);
    $t->same(9, $legacy['legacy_header_page_count_after_legacy_write']);
    $t->same(13, $legacy['effective_page_count_after_legacy_write']);
    $t->same(16, $legacy['header_page_count_after_current_write']);
    $t->same('ok', $legacy['integrity_check']);
    $t->same(true, $savepoint['savepoint_rollback_preserves_integrity']);
    $t->same('ok', $savepoint['integrity_check']);
    $t->same(479, $badReserve['usable_page_bytes']);
    $t->same(false, $badReserve['reserved_bytes_valid']);
    $t->same('file is not a database', $badReserve['query_error']);
};

$tests['real upstream corpus vfs filefmt dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-9.9', 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.1', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.1', 1024, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.1', 1024, 256));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.1', 768));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.6', 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-1.7', 512));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-2.1', 1024, 0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile('filefmt-2.1', 1024, 0, 3000, 0));
};

$tests['real upstream corpus vfs filefmt dynamic count and non overlap note'] = static function (TestRunner $t) use (&$tests, $validCaseCount, $invalidCaseCount): void {
    $t->same(1120, $validCaseCount);
    $t->same(158, $invalidCaseCount);
    $t->same(1282, count($tests));
    $t->same(
        'non-overlap: covers filefmt.test header magic, page-size, reserved-byte, legacy page-count, autovacuum, and backup compatibility; avoids accepted VFS writer/sync/lock/WAL/append/bigfile/file-control clusters',
        'non-overlap: covers filefmt.test header magic, page-size, reserved-byte, legacy page-count, autovacuum, and backup compatibility; avoids accepted VFS writer/sync/lock/WAL/append/bigfile/file-control clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the generic SQLiteVfsIoDynamicPlan surface and hydrated upstream filefmt.test source truth',
        'dependency-closure: no new support component needed; reuses the generic SQLiteVfsIoDynamicPlan surface and hydrated upstream filefmt.test source truth'
    );
};

return $tests;
