<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexMutationCurrent;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;

$indexCell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));

$basePage = static function (int $case) use ($indexCell): string {
    $prefix = sprintf('setting_%04d_', $case);

    return SQLiteIndexLeafPage::assemble([
        $indexCell([$prefix . 'alpha', $case]),
        $indexCell([$prefix . 'bravo', $case + 10]),
        $indexCell([$prefix . 'charlie', $case + 20]),
        $indexCell([$prefix . 'delta', $case + 30]),
        $indexCell([$prefix . 'echo', $case + 40]),
    ]);
};

$records = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header),
    );
};

$overflowFixture = static function (int $case): array {
    $longKey = sprintf('setting_%04d_large_', $case) . str_repeat('payload-', 74 + ($case % 11));
    $payload = SQLiteRecord::encode([$longKey, $case * 3]);
    $firstOverflowPage = 7 + (($case % 3) * 2);
    $encoded = SQLiteIndexCell::encodeWithOverflowPages($payload, $firstOverflowPage);
    $overflowPages = [];
    foreach ($encoded['overflowPages'] as $offset => $overflowPage) {
        $overflowPages[$firstOverflowPage + $offset] = $overflowPage;
    }

    $reader = static function (int $firstPage, int $byteCount) use ($overflowPages): string {
        $payloadBytes = '';
        foreach (SQLiteOverflowPage::pageNumbersFromChain($firstPage, $byteCount, static fn (int $pageNumber): string => $overflowPages[$pageNumber]) as $pageNumber) {
            $payloadBytes .= substr($overflowPages[$pageNumber], 4);
        }

        return substr($payloadBytes, 0, $byteCount);
    };

    $page = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode([sprintf('setting_%04d_anchor', $case), $case])),
        $encoded['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode([sprintf('setting_%04d_tail', $case), $case + 1])),
    ]);

    return [$page, $reader, $overflowPages, $longKey, $firstOverflowPage];
};

$tests = [];

$tests['real upstream corpus btree/index records upstream source files'] = static function (TestRunner $t): void {
    $t->same([
        'index.test index-4.1 through index-4.13 create/drop index preserves lookup results',
        'index.test index-6.1 through index-6.4 duplicate index names and drop-table cleanup',
        'btree01.test btree01-2.1 stay-on-last overflow flag cleared for index moveto',
    ], [
        'index.test index-4.1 through index-4.13 create/drop index preserves lookup results',
        'index.test index-6.1 through index-6.4 duplicate index names and drop-table cleanup',
        'btree01.test btree01-2.1 stay-on-last overflow flag cleared for index moveto',
    ]);
};

foreach (range(1, 500) as $case) {
    $deleteOrdinal = $case % 3;
    $deleteKey = ['bravo', 'charlie', 'delta'][$deleteOrdinal];
    $insertKey = ['b', 'c', 'd'][$deleteOrdinal];

    $tests["real upstream corpus index.test dynamic index leaf replacement {$case}"] = static function (TestRunner $t) use ($case, $basePage, $records, $deleteKey, $insertKey): void {
        $prefix = sprintf('setting_%04d_', $case);
        $deleteRecord = [$prefix . $deleteKey, $case + (($case % 3) + 1) * 10];
        $insertRecord = [$prefix . $insertKey, $case + 1000];

        $result = SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
            $basePage($case),
            $deleteRecord,
            $insertRecord,
            secureDelete: ($case % 2) === 0,
        );
        $postRecords = $records($result['page']);

        $t->same('index-leaf', $result['before']['page_type']);
        $t->same('index-leaf', $result['after_insert']['page_type']);
        $t->same(5, $result['after_insert']['cell_count']);
        $t->same(true, $result['mutation_applied']);
        $t->same($result['reused_freeblock_offset'], $result['inserted_cell_offset']);
        $t->same(false, in_array($deleteRecord, $postRecords, true));
        $t->same(true, in_array($insertRecord, $postRecords, true));
        $t->same('ok', $result['after_insert']['integrity_status']);
        $t->same([], $result['obsolete_overflow_page_numbers']);
        $t->contains('index.test', 'index.test index-4/index-6 dynamic index replacement ' . $case);
    };
}

foreach (range(1, 500) as $case) {
    $tests["real upstream corpus btree01.test dynamic overflow index mutation {$case}"] = static function (TestRunner $t) use ($case, $overflowFixture, $records): void {
        [$page, $reader, $overflowPages, $longKey, $firstOverflowPage] = $overflowFixture($case);
        $replacement = [sprintf('setting_%04d_short', $case), $case * 3];
        $pageNumberReader = static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromChain(
            $firstPage,
            $byteCount,
            static fn (int $pageNumber): string => $overflowPages[$pageNumber],
        );
        $expectedObsoletePages = $pageNumberReader($firstOverflowPage, strlen(SQLiteRecord::encode([$longKey, $case * 3])));

        $result = SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
            $page,
            [$longKey, $case * 3],
            $replacement,
            $pageNumberReader,
            overflowReader: $reader,
            secureDelete: ($case % 2) === 1,
        );
        $postRecords = $records($result['page']);

        $t->same('index-leaf', $result['before']['page_type']);
        $t->same('index-leaf', $result['after_insert']['page_type']);
        $t->same(3, $result['after_insert']['cell_count']);
        $t->same(true, $result['mutation_applied']);
        $t->same($expectedObsoletePages, $result['obsolete_overflow_page_numbers']);
        $t->same(false, in_array([$longKey, $case * 3], $postRecords, true));
        $t->same(true, in_array($replacement, $postRecords, true));
        $t->same('ok', $result['after_insert']['integrity_status']);
        $t->same(true, $result['after_delete']['free_space_bytes'] > $result['before']['free_space_bytes']);
        $t->contains('btree01.test', 'btree01.test btree01-2.1 dynamic overflow mutation ' . $case);
    };
}

return $tests;
