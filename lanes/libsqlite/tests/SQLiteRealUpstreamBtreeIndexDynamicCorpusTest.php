<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

$tests = [];

$indexPayload = static fn (array $values): string => SQLiteRecord::encode($values);
$indexCell = static fn (array $values): string => SQLiteIndexCell::encode($indexPayload($values));

$recordsAfterDelete = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header),
    );
};

$freeblocks = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteBTreeFreeblock $freeblock): array => [
            'offset' => $freeblock->offset,
            'size' => $freeblock->size,
        ],
        $header->freeblocks($page),
    );
};

$firstFreeblockSize = static function (string $page) use ($freeblocks): int {
    $blocks = $freeblocks($page);

    return $blocks[0]['size'] ?? 0;
};

$headerSummary = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return [
        'type' => $header->pageType,
        'cells' => $header->cellCount,
        'firstFreeblock' => $header->firstFreeblockOffset,
        'fragmented' => $header->fragmentedFreeBytes,
        'pointers' => $header->cellPointers($page),
    ];
};

$duplicateSourceRows = [
    [1, 1, 1],
    [1, 2, 2],
    [1, 3, 3],
    [1, 4, 4],
    [1, 5, 5],
    [1, 6, 6],
    [1, 7, 7],
    [1, 8, 8],
    [1, 9, 9],
    [2, 0, 10],
];

$makeDuplicateIndexPage = static fn (): string => SQLiteIndexLeafPage::assemble(array_map($indexCell, $duplicateSourceRows));

$deleteCases = [
    'index-10.2 duplicate key delete tail row keeps first duplicate' => [[1, 9, 9], [1, 1, 1], 9],
    'index-10.2 duplicate key delete middle row keeps neighbors' => [[1, 5, 5], [1, 1, 1], 9],
    'index-10.2 duplicate key delete head row keeps later duplicate' => [[1, 1, 1], [1, 2, 2], 9],
    'index-10.5 duplicate key subquery delete b=2' => [[1, 2, 2], [1, 1, 1], 9],
    'index-10.5 duplicate key subquery delete b=4' => [[1, 4, 4], [1, 1, 1], 9],
    'index-10.5 duplicate key subquery delete b=6' => [[1, 6, 6], [1, 1, 1], 9],
    'index-10.5 duplicate key subquery delete b=8' => [[1, 8, 8], [1, 1, 1], 9],
    'index-10.6 range delete leaves low duplicate' => [[1, 3, 3], [1, 1, 1], 9],
    'index-10.7 last duplicate delete can leave nonmatching key' => [[1, 1, 1], [1, 2, 2], 9],
    'index-10.8 nonmatching key survives duplicate churn' => [[2, 0, 10], [1, 1, 1], 9],
];

foreach ($deleteCases as $name => [$deleteRecord, $firstExpected, $expectedRemaining]) {
    $tests['real upstream index.test ' . $name] = static function (TestRunner $t) use (
        $makeDuplicateIndexPage,
        $recordsAfterDelete,
        $freeblocks,
        $firstFreeblockSize,
        $headerSummary,
        $deleteRecord,
        $firstExpected,
        $expectedRemaining,
    ): void {
        $page = $makeDuplicateIndexPage();
        $before = $headerSummary($page);
        $deleted = SQLiteIndexLeafPage::deleteCellByRecordValues($page, $deleteRecord);
        $after = $headerSummary($deleted);
        $records = $recordsAfterDelete($deleted);
        $remainingKeys = array_column($records, 0);
        $remainingValues = array_column($records, 1);

        $t->same('index-leaf', $before['type']);
        $t->same(10, $before['cells']);
        $t->same('index-leaf', $after['type']);
        $t->same($expectedRemaining, $after['cells']);
        $t->same($firstExpected, $records[0]);
        $t->same(false, in_array($deleteRecord, $records, true));
        $t->same(true, in_array(1, $remainingKeys, true));
        $t->same($deleteRecord[0] === 2 ? false : true, in_array(2, $remainingKeys, true));
        $t->same(true, $after['firstFreeblock'] > 0);
        $t->same(1, count($freeblocks($deleted)));
        $t->same(true, $firstFreeblockSize($deleted) >= 4);
        $t->same($expectedRemaining, count($after['pointers']));
        $t->same($expectedRemaining, count($remainingValues));
    };
}

$multiDeleteCases = [
    'index-10.5 deletes alternating duplicates' => [[2, 4, 6, 8], [1, 3, 5, 7, 9, 0], 6],
    'index-10.6 deletes greater-than-two duplicates after subquery delete' => [[2, 4, 6, 8, 3, 5, 7, 9], [1, 0], 2],
    'index-10.7 deletes all duplicate key rows' => [[1, 2, 3, 4, 5, 6, 7, 8, 9], [0], 1],
    'index-10.8 preserves nonmatching key row after all duplicate deletes' => [[9, 8, 7, 6, 5, 4, 3, 2, 1], [0], 1],
    'index-10.4 repeated duplicate inserts remain independently deletable' => [[3, 6, 9], [1, 2, 4, 5, 7, 8, 0], 7],
];

foreach ($multiDeleteCases as $name => [$deleteValues, $expectedBValues, $expectedCells]) {
    $tests['real upstream index.test ' . $name] = static function (TestRunner $t) use (
        $makeDuplicateIndexPage,
        $recordsAfterDelete,
        $headerSummary,
        $freeblocks,
        $deleteValues,
        $expectedBValues,
        $expectedCells,
    ): void {
        $page = $makeDuplicateIndexPage();
        foreach ($deleteValues as $value) {
            $key = $value === 0 ? 2 : 1;
            $rowid = $value === 0 ? 10 : $value;
            $page = SQLiteIndexLeafPage::deleteCellByRecordValues($page, [$key, $value, $rowid]);
        }

        $records = $recordsAfterDelete($page);
        $summary = $headerSummary($page);
        $blocks = $freeblocks($page);

        $t->same($expectedCells, $summary['cells']);
        $t->same($expectedBValues, array_column($records, 1));
        $t->same($expectedCells, count($summary['pointers']));
        $t->same(true, $summary['firstFreeblock'] > 0);
        $t->same(true, count($blocks) >= 1);
        $t->same(true, array_sum(array_column($blocks, 'size')) >= 4 * count($deleteValues));
        $t->same(false, array_intersect($deleteValues, array_column($records, 1)) !== []);
        $t->same(array_values($expectedBValues), array_values(array_column($records, 1)));
        $t->same('index-leaf', $summary['type']);
        $t->same(0, $summary['fragmented']);
    };
}

$reuseCases = [
    'index-10.4 reinserts duplicate in middle after freeblock delete' => [[1, 5, 5], [1, 5, 105], 4],
    'index-10.4 reinserts duplicate at tail after freeblock delete' => [[1, 9, 9], [1, 9, 109], 8],
    'index-10.4 reinserts nonmatching key at tail after freeblock delete' => [[2, 0, 10], [2, 0, 110], 9],
    'index-10.5 reinserts alternating duplicate key after delete' => [[1, 4, 4], [1, 4, 104], 3],
];

foreach ($reuseCases as $name => [$deleteRecord, $insertRecord, $expectedIndex]) {
    $tests['real upstream index.test ' . $name] = static function (TestRunner $t) use (
        $makeDuplicateIndexPage,
        $recordsAfterDelete,
        $headerSummary,
        $deleteRecord,
        $insertRecord,
        $expectedIndex,
    ): void {
        $page = SQLiteIndexLeafPage::deleteCellByRecordValues($makeDuplicateIndexPage(), $deleteRecord);
        $reused = SQLiteIndexLeafPage::insertCellByRecordValuesReusingFreeblock($page, $insertRecord);
        $records = $recordsAfterDelete($reused);
        $summary = $headerSummary($reused);

        $t->same(10, $summary['cells']);
        $t->same($insertRecord, $records[$expectedIndex]);
        $t->same(true, in_array($insertRecord, $records, true));
        $t->same(false, in_array($deleteRecord, $records, true));
        $t->same(10, count($summary['pointers']));
        $t->same('index-leaf', $summary['type']);
        $t->same(0, $summary['fragmented']);
        $t->same(true, $summary['firstFreeblock'] >= 0);
        $t->same([1, 1, 1], array_slice(array_column($records, 0), 0, 3));
        $t->same(2, $records[9][0]);
    };
}

$compareSqliteSortValue = static function (mixed $left, mixed $right): int {
    if ($left === null && $right === null) {
        return 0;
    }
    if ($left === null) {
        return -1;
    }
    if ($right === null) {
        return 1;
    }

    return SQLiteAffinityComparison::compare($left, $right, 'NONE', 'NONE', 'BINARY') ?? 0;
};

$compareIndexRows = static function (array $left, array $right) use ($compareSqliteSortValue): int {
    $count = min(count($left), count($right));
    for ($i = 0; $i < $count; $i++) {
        $comparison = $compareSqliteSortValue($left[$i], $right[$i]);
        if ($comparison !== 0) {
            return $comparison < 0 ? -1 : 1;
        }
    }

    return count($left) <=> count($right);
};

$mixedOrderRows = [
    ['', '', 1],
    ['', null, 2],
    [null, '', 3],
    ['abc', 123, 4],
    [123, 'abc', 5],
];

$mixedSorted = $mixedOrderRows;
usort($mixedSorted, $compareIndexRows);

$orderCases = [
    'index-14.1 mixed type index order c projection' => [$mixedSorted, [3, 5, 2, 1, 4]],
    'index-14.2 equality on empty text key' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $row[0] === '')), [2, 1]],
    'index-14.3 equality on empty trailing key' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $row[1] === '')), [3, 1]],
    'index-14.4 greater than empty text key' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], '') > 0)), [4]],
    'index-14.5 greater or equal empty text key' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], '') >= 0)), [2, 1, 4]],
    'index-14.6 greater than numeric text boundary' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], 123) > 0)), [2, 1, 4]],
    'index-14.7 greater or equal numeric text boundary' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], 123) >= 0)), [5, 2, 1, 4]],
    'index-14.8 less than abc boundary' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], 'abc') < 0)), [3, 5, 2, 1]],
    'index-14.9 less or equal abc boundary' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], 'abc') <= 0)), [3, 5, 2, 1, 4]],
    'index-14.10 less or equal empty boundary' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], '') <= 0)), [3, 5, 2, 1]],
    'index-14.11 less than empty boundary' => [array_values(array_filter($mixedSorted, static fn (array $row): bool => $compareSqliteSortValue($row[0], '') < 0)), [3, 5]],
];

foreach ($orderCases as $name => [$rows, $expectedCValues]) {
    $tests['real upstream index.test ' . $name] = static function (TestRunner $t) use (
        $indexCell,
        $recordsAfterDelete,
        $headerSummary,
        $rows,
        $expectedCValues,
    ): void {
        $page = SQLiteIndexLeafPage::assemble(array_map($indexCell, $rows));
        $records = $recordsAfterDelete($page);
        $summary = $headerSummary($page);

        $t->same('index-leaf', $summary['type']);
        $t->same(count($rows), $summary['cells']);
        $t->same($expectedCValues, array_column($records, 2));
        $t->same(count($expectedCValues), count($records));
        $t->same(0, $summary['fragmented']);
        $t->same(0, $summary['firstFreeblock']);
        $t->same(count($rows), count($summary['pointers']));
        $t->same($expectedCValues[0] ?? null, $records[0][2] ?? null);
        $t->same($expectedCValues[count($expectedCValues) - 1] ?? null, $records[count($records) - 1][2] ?? null);
    };
}

$generatedDeleteValues = [];
for ($i = 1; $i <= 9; $i++) {
    $generatedDeleteValues[] = [$i];
}
for ($i = 1; $i <= 9; $i++) {
    for ($j = $i + 1; $j <= 9 && count($generatedDeleteValues) < 55; $j++) {
        $generatedDeleteValues[] = [$i, $j];
    }
}
for ($i = 1; $i <= 9 && count($generatedDeleteValues) < 55; $i++) {
    for ($j = $i + 1; $j <= 9 && count($generatedDeleteValues) < 55; $j++) {
        for ($k = $j + 1; $k <= 9 && count($generatedDeleteValues) < 55; $k++) {
            $generatedDeleteValues[] = [$i, $j, $k];
        }
    }
}

foreach ($generatedDeleteValues as $caseIndex => $deleteValues) {
    $tests['real upstream index.test index-10 dynamic duplicate delete case ' . str_pad((string) ($caseIndex + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use (
        $makeDuplicateIndexPage,
        $recordsAfterDelete,
        $headerSummary,
        $deleteValues,
    ): void {
        $page = $makeDuplicateIndexPage();
        foreach ($deleteValues as $value) {
            $page = SQLiteIndexLeafPage::deleteCellByRecordValues($page, [1, $value, $value]);
        }

        $records = $recordsAfterDelete($page);
        $summary = $headerSummary($page);
        $remainingBValues = array_column($records, 1);
        $expectedBValues = array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8, 9, 0], $deleteValues));

        $t->same(10 - count($deleteValues), $summary['cells']);
        $t->same($expectedBValues, $remainingBValues);
        $t->same(false, array_intersect($deleteValues, $remainingBValues) !== []);
        $t->same(true, in_array(0, $remainingBValues, true));
        $t->same(1, $records[count($records) - 2][0] ?? 1);
        $t->same(2, $records[count($records) - 1][0]);
        $t->same('index-leaf', $summary['type']);
        $t->same(10 - count($deleteValues), count($summary['pointers']));
    };
}

$tests['real upstream index.test rejects missing duplicate index record'] = static function (TestRunner $t) use ($makeDuplicateIndexPage): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexLeafPage::deleteCellByRecordValues($makeDuplicateIndexPage(), [1, 12, 12]));
};

$tests['real upstream index.test rejects duplicate reinsertion without freeblock'] = static function (TestRunner $t) use ($makeDuplicateIndexPage): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexLeafPage::insertCellByRecordValuesReusingFreeblock($makeDuplicateIndexPage(), [1, 5, 5]));
};

$tests['real upstream index.test rejects wrong page type for index delete'] = static function (TestRunner $t): void {
    $page = str_repeat("\0", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 512), 5, 2);

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexLeafPage::deleteCellByRecordValues($page, [1, 1, 1]));
};

return $tests;
