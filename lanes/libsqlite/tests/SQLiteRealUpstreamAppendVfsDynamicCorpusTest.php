<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAppendVfsPlan;

$tests = [];

$fixedCases = [
    'avfs-1.0 empty appendee starts sqlite payload at offset zero' => ['avfs-1.0', 0, 1024, 1, ['cat', 'dog'], 0, 1049, ['cat', 'dog'], ['dog', 'cat']],
    'avfs-1.2 text appendee aligns sqlite payload to 4096 bytes' => ['avfs-1.2', 50, 512, 2, ['cat', 'dog', 'pig'], 4096, 5145, ['cat', 'dog', 'pig'], ['pig', 'dog', 'cat']],
    'avfs-4.1 shell append mode aligns text appendee' => ['avfs-4.1', 11, 512, 1, ['sqlar'], 4096, 4633, ['sqlar'], ['sqlar']],
    'avfs-4.2 shell append mode keeps empty appendee offset zero' => ['avfs-4.2', 0, 512, 1, ['sqlar'], 0, 537, ['sqlar'], ['sqlar']],
];

foreach ($fixedCases as $name => [$scenario, $prefixBytes, $pageSize, $pageCount, $rows, $offset, $totalBytes, $sortedRows, $reopenRows]) {
    $tests['real upstream appendvfs dynamic corpus ' . $name] = static function (TestRunner $t) use ($scenario, $prefixBytes, $pageSize, $pageCount, $rows, $offset, $totalBytes, $sortedRows, $reopenRows): void {
        $plan = SQLiteAppendVfsPlan::appendImage($scenario, $prefixBytes, $pageSize, $pageCount, $rows);

        $t->same('avfs.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($prefixBytes, $plan['prefix_bytes']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($pageCount, $plan['page_count']);
        $t->same($offset, $plan['offset']);
        $t->same($totalBytes, $plan['total_bytes']);
        $t->same(25, $plan['trailer_bytes']);
        $t->same('Start-Of-SQLite3-', $plan['marker']);
        $t->same($offset, $plan['detected_offset']);
        $t->same($sortedRows, $plan['rows']);
        $t->same($reopenRows, $plan['reopen_rows']);
        $t->same(true, $plan['appendee_preserved']);
        $t->same('ok', $plan['integrity_check']);
        $t->same('ok', $plan['open_result']);
        $t->same(true, in_array('sqlite-upstream-avfs-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-appendvfs-offset-trailer', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-io-dynamic', $plan['dependencies'], true));
        $t->same(true, str_starts_with($plan['upstream'][0], 'avfs.test'));
    };
}

$prefixSizes = [0, 1, 17, 24, 25, 49, 50, 511, 512, 513, 1023, 1024, 2047, 2048, 2049, 3071, 3072, 4095, 4096, 4097, 8191, 8192, 12289, 16383, 16384];
$pageSizes = [512, 1024, 2048, 4096, 8192];
$pageCounts = [1, 2, 3, 7, 11, 31, 64, 127, 255];
$case = 0;

foreach ($prefixSizes as $prefixBytes) {
    foreach ($pageSizes as $pageSize) {
        foreach ($pageCounts as $pageCount) {
            $case++;
            $scenario = sprintf('avfs-1.dynamic.%04d', $case);
            $tests[sprintf('real upstream appendvfs dynamic corpus offset matrix %04d prefix %d page %d count %d', $case, $prefixBytes, $pageSize, $pageCount)] = static function (TestRunner $t) use ($scenario, $prefixBytes, $pageSize, $pageCount): void {
                $plan = SQLiteAppendVfsPlan::appendImage($scenario, $prefixBytes, $pageSize, $pageCount, ['dog', 'cat', 'pig']);
                $expectedOffset = $prefixBytes === 0 ? 0 : (int) (ceil($prefixBytes / 4096) * 4096);
                $expectedTotal = $expectedOffset + ($pageSize * $pageCount) + 25;

                $t->same('avfs.test', $plan['script']);
                $t->same($scenario, $plan['scenario']);
                $t->same($expectedOffset, $plan['offset']);
                $t->same($expectedOffset, $plan['detected_offset']);
                $t->same($expectedTotal, $plan['total_bytes']);
                $t->same($pageSize * $pageCount, $plan['database_bytes']);
                $t->same(0, $plan['offset'] % 4096);
                $t->same(['cat', 'dog', 'pig'], $plan['rows']);
                $t->same(['pig', 'dog', 'cat'], $plan['reopen_rows']);
                $t->same(true, $plan['appendee_preserved']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(true, in_array('sqlite-appendvfs-offset-trailer', $plan['dependencies'], true));
                $t->same(['avfs.test'], $plan['upstream']);
            };
        }
    }
}

$growPrefixes = [0, 50, 511, 1024, 4095, 4096, 4097, 8192];
$growPageSizes = [512, 1024, 2048, 4096];
$insertedPages = [2, 7, 31, 64, 127, 300];
$keepEveryValues = [2, 5, 8, 17];
$growCase = 0;

foreach ($growPrefixes as $prefixBytes) {
    foreach ($growPageSizes as $pageSize) {
        foreach ($insertedPages as $inserted) {
            foreach ($keepEveryValues as $keepEvery) {
                $growCase++;
                $initialPages = 2 + ($growCase % 5);
                $scenario = sprintf('avfs-3.dynamic.%04d', $growCase);
                $tests[sprintf('real upstream appendvfs dynamic corpus grow shrink matrix %04d prefix %d page %d inserted %d keep %d', $growCase, $prefixBytes, $pageSize, $inserted, $keepEvery)] = static function (TestRunner $t) use ($scenario, $prefixBytes, $pageSize, $initialPages, $inserted, $keepEvery): void {
                    $plan = SQLiteAppendVfsPlan::growShrinkPlan($scenario, $prefixBytes, $pageSize, $initialPages, $inserted, $keepEvery);

                    $t->same('avfs.test', $plan['script']);
                    $t->same($scenario, $plan['scenario']);
                    $t->same($prefixBytes, $plan['prefix_bytes']);
                    $t->same($initialPages, $plan['initial_pages']);
                    $t->same($initialPages + $inserted, $plan['grown_pages']);
                    $t->same(max(1, (int) ceil(($initialPages + $inserted) / $keepEvery)), $plan['remaining_pages']);
                    $t->same(true, $plan['grown_total_bytes'] > $plan['initial_total_bytes']);
                    $t->same(true, $plan['grown_total_bytes'] > $plan['shrunk_total_bytes']);
                    $t->same(true, $plan['grow_ratio'] > 1.0);
                    $t->same(true, $plan['shrink_ratio'] > 1.0);
                    $t->same(['ok', 'ok', 'ok'], $plan['integrity_sequence']);
                    $t->same('ok', $plan['reopen_integrity_check']);
                    $t->same(true, $plan['appendee_preserved']);
                    $t->same(true, in_array('sqlite-appendvfs-grow-shrink', $plan['dependencies'], true));
                    $t->same(['avfs.test avfs-3.1', 'avfs.test avfs-3.2', 'avfs.test avfs-3.3', 'avfs.test avfs-3.4', 'avfs.test avfs-3.5'], $plan['upstream']);
                };
            }
        }
    }
}

foreach ([0, 19, 50, 4096] as $prefixBytes) {
    foreach ([0, $prefixBytes] as $declaredOffset) {
        $scenario = 'avfs-5.dynamic.' . $prefixBytes . '.' . $declaredOffset;
        $tests['real upstream appendvfs dynamic corpus tiny appended database rejection ' . $scenario] = static function (TestRunner $t) use ($scenario, $prefixBytes, $declaredOffset): void {
            $plan = SQLiteAppendVfsPlan::tinyOpenAttempt($scenario, $prefixBytes, $declaredOffset);

            $t->same('avfs.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same($prefixBytes, $plan['prefix_bytes']);
            $t->same($declaredOffset, $plan['declared_offset']);
            $t->same('failed', $plan['open_result']);
            $t->same('appended_database_too_small', $plan['reject_reason']);
            $t->same(['avfs.test avfs-5.1', 'avfs.test avfs-5.2'], $plan['upstream']);
            $t->same(true, in_array('sqlite-appendvfs-tiny-open-guard', $plan['dependencies'], true));
        };
    }
}

$tests['real upstream appendvfs dynamic corpus detects missing and truncated trailers'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteAppendVfsPlan::detectOffset(''));
    $t->same(null, SQLiteAppendVfsPlan::detectOffset(str_repeat('x', 24)));
    $t->same(null, SQLiteAppendVfsPlan::detectOffset(str_repeat('x', 17) . str_repeat("\0", 8)));
};

$tests['real upstream appendvfs dynamic corpus rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::appendImage('', 0, 1024, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::appendImage('avfs-bad-prefix', -1, 1024, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::appendImage('avfs-bad-page', 0, 1000, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::appendImage('avfs-bad-count', 0, 1024, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::appendImage('avfs-bad-rows', 0, 1024, 1, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::growShrinkPlan('avfs-bad-grow', 0, 1024, 0, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteAppendVfsPlan::tinyOpenAttempt('avfs-bad-tiny', -1, 0));
};

return $tests;
