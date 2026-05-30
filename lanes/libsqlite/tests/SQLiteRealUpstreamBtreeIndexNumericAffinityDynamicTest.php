<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

$tests = [];

$upstreamRows = [
    ['literal' => '1.234e5', 'b' => 1],
    ['literal' => '12.33e04', 'b' => 2],
    ['literal' => '12.35E4', 'b' => 3],
    ['literal' => '12.34e', 'b' => 4],
    ['literal' => '12.32e+4', 'b' => 5],
    ['literal' => '12.36E+04', 'b' => 6],
    ['literal' => '12.36E+', 'b' => 7],
    ['literal' => '+123.10000E+0003', 'b' => 8],
    ['literal' => '+', 'b' => 9],
    ['literal' => '+12347.E+02', 'b' => 10],
    ['literal' => '+12347E+02', 'b' => 11],
    ['literal' => '+.125E+04', 'b' => 12],
    ['literal' => '-.125E+04', 'b' => 13],
    ['literal' => '.125E+0', 'b' => 14],
    ['literal' => '.125', 'b' => 15],
];

$numericBValues = [1, 2, 3, 5, 6, 8, 10, 11, 12, 13, 14, 15];
$fullOrderBValues = [13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7];

$indexCell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));
$storedValue = static fn (string $literal): mixed => SQLiteAffinityComparison::applyAffinity($literal, 'NUMERIC');

$compareIndexRecord = static function (array $left, array $right): int {
    $keyComparison = SQLiteAffinityComparison::compare($left[0], $right[0], 'NONE', 'NONE', 'BINARY');
    if ($keyComparison !== 0) {
        return $keyComparison ?? 0;
    }

    return SQLiteAffinityComparison::compare($left[1], $right[1], 'NONE', 'NONE', 'BINARY') ?? 0;
};

$orderedRecords = static function (array $rows) use ($storedValue, $compareIndexRecord): array {
    $records = array_map(
        static fn (array $row): array => [$storedValue($row['literal']), $row['b'], $row['literal']],
        $rows,
    );
    usort($records, $compareIndexRecord);

    return $records;
};

$parseRecords = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header),
    );
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

foreach (range(0, 999) as $caseIndex) {
    $tests['real upstream btree index numeric affinity index.test index-15 dynamic order case ' . str_pad((string) ($caseIndex + 1), 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use (
        $upstreamRows,
        $fullOrderBValues,
        $numericBValues,
        $orderedRecords,
        $indexCell,
        $parseRecords,
        $headerSummary,
        $caseIndex,
    ): void {
        $offset = $caseIndex % count($upstreamRows);
        $count = 5 + ($caseIndex % (count($upstreamRows) - 4));
        $rotated = array_merge(array_slice($upstreamRows, $offset), array_slice($upstreamRows, 0, $offset));
        $selected = array_slice($rotated, 0, $count);
        if ($caseIndex % 3 === 1) {
            $selected = array_reverse($selected);
        } elseif ($caseIndex % 3 === 2) {
            usort($selected, static fn (array $left, array $right): int => $right['b'] <=> $left['b']);
        }

        $records = $orderedRecords($selected);
        $page = SQLiteIndexLeafPage::assemble(array_map($indexCell, array_map(
            static fn (array $record): array => [$record[0], $record[1]],
            $records,
        )));
        $parsed = $parseRecords($page);
        $summary = $headerSummary($page);
        $expectedBValues = array_values(array_map(static fn (array $record): int => $record[1], $records));
        $selectedBValues = array_values(array_map(static fn (array $row): int => $row['b'], $selected));
        $expectedNumericBValues = array_values(array_filter(
            $expectedBValues,
            static fn (int $b): bool => in_array($b, $numericBValues, true),
        ));
        $actualNumericBValues = array_values(array_filter(
            array_column($parsed, 1),
            static fn (int $b): bool => in_array($b, $numericBValues, true),
        ));

        $t->same('index-leaf', $summary['type']);
        $t->same($count, $summary['cells']);
        $t->same($count, count($summary['pointers']));
        $t->same(0, $summary['firstFreeblock']);
        $t->same(0, $summary['fragmented']);
        $t->same($expectedBValues, array_column($parsed, 1));
        $t->same($expectedNumericBValues, $actualNumericBValues);
        $t->same(array_values(array_intersect($fullOrderBValues, $selectedBValues)), $expectedBValues);
        $t->same($count, count($parsed));
        $t->contains('index.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test');
    };
}

$tests['real upstream btree index numeric affinity index.test index-15 full upstream order'] = static function (TestRunner $t) use (
    $upstreamRows,
    $fullOrderBValues,
    $numericBValues,
    $orderedRecords,
    $indexCell,
    $parseRecords,
    $headerSummary,
): void {
    $records = $orderedRecords($upstreamRows);
    $page = SQLiteIndexLeafPage::assemble(array_map($indexCell, array_map(
        static fn (array $record): array => [$record[0], $record[1]],
        $records,
    )));
    $parsed = $parseRecords($page);
    $summary = $headerSummary($page);

    $t->same('index-leaf', $summary['type']);
    $t->same(15, $summary['cells']);
    $t->same($fullOrderBValues, array_column($parsed, 1));
    $t->same(array_values(array_filter(
        $fullOrderBValues,
        static fn (int $b): bool => in_array($b, $numericBValues, true),
    )), array_values(array_filter(
        array_column($parsed, 1),
        static fn (int $b): bool => in_array($b, $numericBValues, true),
    )));
    $t->same(['integer', 'real', 'text'], array_values(array_unique(array_map(
        static fn (array $record): string => SQLiteAffinityComparison::storageClass($record[0]),
        $parsed,
    ))));
    $t->same(0, $summary['firstFreeblock']);
    $t->same(0, $summary['fragmented']);
    $t->contains('index-15.2', 'index-15.2');
    $t->contains('index-15.3', 'index-15.3');
};

return $tests;
