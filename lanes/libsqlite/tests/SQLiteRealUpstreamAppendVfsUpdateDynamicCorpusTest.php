<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAppendVfsPlan;

$tests = [];

$prefixSizes = [0, 11, 50, 511, 512, 513, 1023, 1024, 2047, 2048, 4095, 4096, 4097, 8191, 8192, 12289];
$pageSizes = [512, 1024, 2048, 4096];
$initialPages = [1, 2, 3, 7, 11, 31, 64];
$appendCounts = [1, 2, 3, 5, 8, 13, 21];
$case = 0;

foreach ($prefixSizes as $prefixBytes) {
    foreach ($pageSizes as $pageSize) {
        foreach ($initialPages as $initialPageCount) {
            foreach ($appendCounts as $appendCount) {
                ++$case;
                $scenario = sprintf('avfs-4.3.dynamic.%04d', $case);
                $existingRows = [
                    sprintf('base-%04d-a', $case),
                    sprintf('base-%04d-b', $case),
                ];
                $appendedRows = [];
                for ($i = 0; $i < $appendCount; ++$i) {
                    $appendedRows[] = sprintf('added-%04d-%02d', $case, $i);
                }

                $tests[sprintf(
                    'real upstream appendvfs update dynamic corpus avfs-4.3 %04d prefix %d page %d initial %d append %d',
                    $case,
                    $prefixBytes,
                    $pageSize,
                    $initialPageCount,
                    $appendCount
                )] = static function (TestRunner $t) use ($scenario, $prefixBytes, $pageSize, $initialPageCount, $existingRows, $appendedRows, $appendCount): void {
                    $plan = SQLiteAppendVfsPlan::updateExistingAppendDatabase(
                        $scenario,
                        $prefixBytes,
                        $pageSize,
                        $initialPageCount,
                        $existingRows,
                        $appendedRows
                    );

                    $expectedOffset = $prefixBytes === 0 ? 0 : (int) (ceil($prefixBytes / 4096) * 4096);
                    $expectedFinalPages = $initialPageCount + max(1, (int) ceil($appendCount / 8));
                    $expectedRows = array_values(array_merge($existingRows, $appendedRows));
                    sort($expectedRows, SORT_STRING);
                    $expectedRowsBefore = $existingRows;
                    sort($expectedRowsBefore, SORT_STRING);

                    $t->same('avfs.test', $plan['script']);
                    $t->same($scenario, $plan['scenario']);
                    $t->same($prefixBytes, $plan['prefix_bytes']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($initialPageCount, $plan['initial_page_count']);
                    $t->same($expectedFinalPages, $plan['final_page_count']);
                    $t->same($expectedOffset, $plan['offset']);
                    $t->same($expectedOffset, $plan['detected_offset']);
                    $t->same($expectedRowsBefore, $plan['rows_before']);
                    $t->same($appendedRows, $plan['appended_rows']);
                    $t->same($expectedRows, $plan['rows_after']);
                    $t->same(count($expectedRows), $plan['shell_output_rows']);
                    $t->same(true, $plan['final_total_bytes'] > $plan['initial_total_bytes']);
                    $t->same(true, $plan['trailer_rewritten']);
                    $t->same(true, $plan['appendee_preserved']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same('ok', $plan['reopen_integrity_check']);
                    $t->same(['avfs.test avfs-4.2', 'avfs.test avfs-4.3'], $plan['upstream']);
                    $t->same(true, in_array('sqlite-upstream-avfs-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-appendvfs-existing-update', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-io-dynamic', $plan['dependencies'], true));
                };
            }
        }
    }
}

$tests['real upstream appendvfs update dynamic corpus owns avfs 4.3 matrix count'] = static function (TestRunner $t) use ($case): void {
    $t->same(3136, $case);
    $plan = SQLiteAppendVfsPlan::updateExistingAppendDatabase('avfs-4.3.dynamic.count', 50, 512, 1, ['sqlar'], ['sh_app1.sql']);
    $t->same(['avfs.test avfs-4.2', 'avfs.test avfs-4.3'], $plan['upstream']);
    $t->same(['sh_app1.sql', 'sqlar'], $plan['rows_after']);
};

$tests['real upstream appendvfs update dynamic corpus rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::updateExistingAppendDatabase('', 0, 512, 1, ['sqlar'], ['sh_app1.sql']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::updateExistingAppendDatabase('avfs-bad-page-count', 0, 512, 0, ['sqlar'], ['sh_app1.sql']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::updateExistingAppendDatabase('avfs-bad-existing', 0, 512, 1, [], ['sh_app1.sql']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::updateExistingAppendDatabase('avfs-bad-added', 0, 512, 1, ['sqlar'], []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::updateExistingAppendDatabase('avfs-bad-page-size', 0, 1000, 1, ['sqlar'], ['sh_app1.sql']));
};

return $tests;
