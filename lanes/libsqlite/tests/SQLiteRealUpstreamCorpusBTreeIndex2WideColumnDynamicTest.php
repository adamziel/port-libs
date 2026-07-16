<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamBTreeIndexDynamicCorpus;

$tests = [];

$wideRows = static function (): array {
    static $rows = null;

    return $rows ??= SQLiteRealUpstreamBTreeIndexDynamicCorpus::wideRows();
};
$wideColumns = static function (): array {
    static $columns = null;

    return $columns ??= SQLiteRealUpstreamBTreeIndexDynamicCorpus::wideIndexColumns();
};
$widePrefixIndex = static function () use ($wideRows): array {
    static $index = null;

    return $index ??= SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf(
        $wideRows(),
        ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'],
        65536,
    );
};
$widePrefixRecords = static function () use ($widePrefixIndex): array {
    static $records = null;

    return $records ??= SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($widePrefixIndex()['page'], 65536);
};

$tests['real upstream corpus index2.test wide column dynamic cites source'] = static function (TestRunner $t) use ($wideRows, $wideColumns): void {
    $t->same(101, count($wideRows()));
    $t->same(1000, count($wideColumns()));
    $t->same('index2.test index2-1.1 through index2-1.5 and index2-2.1/index2-2.2', 'index2.test index2-1.1 through index2-1.5 and index2-2.1/index2-2.2');
};

$tests['real upstream corpus index2.test index2-1.5 c1000 sum stays exact'] = static function (TestRunner $t) use ($wideRows): void {
    $sum = array_sum(array_map(static fn (array $row): int => $row['c1000'], $wideRows()));

    $t->same(50601000, $sum);
    $t->same('50601000.0', number_format((float) $sum, 1, '.', ''));
};

foreach (range(1, 1000) as $column) {
    $tests["real upstream corpus index2.test index2-1 wide column c{$column} materializes rows"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $column): void {
        $rows = $wideRows();
        $columns = $wideColumns();
        $columnName = 'c' . $column;

        $t->same($columnName, $columns[$column - 1]);
        $t->same($column, $rows[0][$columnName]);
        $t->same(10000 + $column, $rows[1][$columnName]);
        $t->same(1000000 + $column, $rows[100][$columnName]);
        $t->same(101, count($rows));
    };
}

foreach (range(1, 1000) as $column) {
    $tests["real upstream corpus index2.test index2-2 single-column index c{$column} is ordered"] = static function (TestRunner $t) use ($wideRows, $column): void {
        $columnName = 'c' . $column;
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), [$columnName], 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);

        $t->same(101, $index['row_count']);
        $t->same(1, $index['column_count']);
        $t->same($column, $records[0][0]);
        $t->same(10000 + $column, $records[1][0]);
        $t->same(500000 + $column, $records[50][0]);
        $t->same(1000000 + $column, $records[100][0]);
        $t->same(1, $records[0][1]);
        $t->same(101, $records[100][1]);
    };
}

foreach ([1, 2, 3, 5, 8, 13, 21, 34, 55, 89] as $rowid) {
    $tests["real upstream corpus index2.test index2-2 wide index prefix seek row {$rowid}"] = static function (TestRunner $t) use ($widePrefixRecords, $rowid): void {
        $records = $widePrefixRecords();
        $base = $rowid === 1 ? 0 : ($rowid - 1) * 10000;
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$base + 1, $base + 2, $base + 3, $base + 4]);

        $t->same(1, count($matches));
        $t->same($rowid, $matches[0][6]);
        $t->same($base + 1, $matches[0][0]);
        $t->same($base + 4, $matches[0][3]);
        $t->same($base + 6, $matches[0][5]);
    };
}

return $tests;
