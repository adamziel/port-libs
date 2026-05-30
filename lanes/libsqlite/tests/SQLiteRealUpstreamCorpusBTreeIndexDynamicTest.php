<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamBTreeIndexDynamicCorpus;

$tests = [];

$powerRows = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::powerRows();
$cntIndex = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($powerRows(), ['cnt']);
$powerIndex = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($powerRows(), ['power']);
$scan = static fn (array $index): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page']);

$tests['real upstream corpus btree index dynamic cites hydrated upstream scenarios'] = static function (TestRunner $t) use ($cntIndex): void {
    $index = $cntIndex();
    $t->same('index.test index-4.1 through index-4.13 and index2.test index2-2.1/index2-2.2', $index['source']);
    $t->same(19, $index['row_count']);
    $t->same(1, $index['column_count']);
};

foreach ([1, 2, 3, 4, 5, 6, 10, 12, 16, 19] as $cnt) {
    $tests["real upstream corpus index.test index-4 cnt index seeks row {$cnt}"] = static function (TestRunner $t) use ($cntIndex, $scan, $cnt): void {
        $records = $scan($cntIndex());
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$cnt]);
        $t->same([[$cnt, $cnt]], $matches);
        $t->same($cnt, $matches[0][0]);
        $t->same($cnt, $matches[0][1]);
        $t->same(19, count($records));
        $t->same([$cnt, $cnt], $records[$cnt - 1]);
    };
}

foreach ([2 => 4, 6 => 64, 10 => 1024, 14 => 16384, 19 => 524288] as $cnt => $power) {
    $tests["real upstream corpus index.test index-4 power index seeks power {$power}"] = static function (TestRunner $t) use ($powerIndex, $scan, $cnt, $power): void {
        $records = $scan($powerIndex());
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$power]);
        $t->same([[$power, $cnt]], $matches);
        $t->same($power, $matches[0][0]);
        $t->same($cnt, $matches[0][1]);
        $t->same(19, count($records));
        $t->same([$power, $cnt], $records[$cnt - 1]);
    };
}

foreach (range(1, 19) as $cnt) {
    $tests["real upstream corpus index.test index-4 cnt leaf sorted cell {$cnt}"] = static function (TestRunner $t) use ($cntIndex, $scan, $cnt): void {
        $records = $scan($cntIndex());
        $t->same([$cnt, $cnt], $records[$cnt - 1]);
        $t->same($cnt, $records[$cnt - 1][0]);
        $t->same($cnt, $records[$cnt - 1][1]);
        $t->same(true, $cnt === 1 || $records[$cnt - 2][0] < $records[$cnt - 1][0]);
        $t->same(true, $cnt === 19 || $records[$cnt - 1][0] < $records[$cnt][0]);
    };
}

foreach (range(1, 19) as $cnt) {
    $power = 1 << $cnt;
    $tests["real upstream corpus index.test index-4 power leaf sorted cell {$cnt}"] = static function (TestRunner $t) use ($powerIndex, $scan, $cnt, $power): void {
        $records = $scan($powerIndex());
        $t->same([$power, $cnt], $records[$cnt - 1]);
        $t->same($power, $records[$cnt - 1][0]);
        $t->same($cnt, $records[$cnt - 1][1]);
        $t->same(true, $cnt === 1 || $records[$cnt - 2][0] < $records[$cnt - 1][0]);
        $t->same(true, $cnt === 19 || $records[$cnt - 1][0] < $records[$cnt][0]);
    };
}

$catalogEvents = [
    ['name' => 'index9', 'column' => 'cnt', 'active' => true],
    ['name' => 'indext', 'column' => 'power', 'active' => true],
    ['name' => 'indext', 'column' => 'power', 'active' => false],
    ['name' => 'indext', 'column' => 'cnt', 'active' => true],
    ['name' => 'index9', 'column' => 'cnt', 'active' => false],
    ['name' => 'indext', 'column' => 'cnt', 'active' => false],
];

$expectedCatalogs = [
    1 => ['index9' => 'cnt'],
    2 => ['index9' => 'cnt', 'indext' => 'power'],
    3 => ['index9' => 'cnt'],
    4 => ['index9' => 'cnt', 'indext' => 'cnt'],
    5 => ['indext' => 'cnt'],
    6 => [],
];

foreach ($expectedCatalogs as $step => $expected) {
    $tests["real upstream corpus index.test index-4 create drop catalog step {$step}"] = static function (TestRunner $t) use ($catalogEvents, $step, $expected): void {
        $catalog = SQLiteRealUpstreamBTreeIndexDynamicCorpus::activeIndexCatalog(array_slice($catalogEvents, 0, $step));
        $t->same($expected, $catalog);
        $t->same(count($expected), count($catalog));
        $t->same(array_keys($expected), array_keys($catalog));
    };
}

$wideRows = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::wideRows();
$wideColumns = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::wideIndexColumns();

foreach ([1, 2, 3, 4, 5] as $limitIndex) {
    $tests["real upstream corpus index2.test index2-2 wide index order limit {$limitIndex}"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $limitIndex): void {
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), array_slice($wideColumns(), 0, 6), 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);
        $expectedC9 = $limitIndex === 1 ? 9 : (($limitIndex - 1) * 10000) + 9;
        $t->same(101, $index['row_count']);
        $t->same(6, $index['column_count']);
        $t->same($expectedC9 - 8, $records[$limitIndex - 1][0]);
        $t->same($expectedC9 - 7, $records[$limitIndex - 1][1]);
        $t->same($expectedC9 - 6, $records[$limitIndex - 1][2]);
        $t->same($expectedC9 - 5, $records[$limitIndex - 1][3]);
        $t->same($expectedC9 - 4, $records[$limitIndex - 1][4]);
        $t->same($expectedC9 - 3, $records[$limitIndex - 1][5]);
        $t->same($limitIndex, $records[$limitIndex - 1][6]);
    };
}

foreach ([1, 17, 33, 65, 101] as $rowNumber) {
    $tests["real upstream corpus index2.test index2-2 wide index seeks row {$rowNumber}"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $rowNumber): void {
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), array_slice($wideColumns(), 0, 6), 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);
        $base = $rowNumber === 1 ? 0 : ($rowNumber - 1) * 10000;
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$base + 1, $base + 2, $base + 3]);
        $t->same(1, count($matches));
        $t->same($base + 1, $matches[0][0]);
        $t->same($base + 2, $matches[0][1]);
        $t->same($base + 3, $matches[0][2]);
        $t->same($base + 6, $matches[0][5]);
        $t->same($rowNumber, $matches[0][6]);
    };
}

foreach (range(1, 101, 2) as $rowNumber) {
    $tests["real upstream corpus index2.test index2-2 wide prefix order odd row {$rowNumber}"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $rowNumber): void {
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), array_slice($wideColumns(), 0, 6), 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);
        $base = $rowNumber === 1 ? 0 : ($rowNumber - 1) * 10000;
        $record = $records[$rowNumber - 1];
        $t->same($base + 1, $record[0]);
        $t->same($base + 2, $record[1]);
        $t->same($base + 3, $record[2]);
        $t->same($base + 4, $record[3]);
        $t->same($base + 5, $record[4]);
        $t->same($base + 6, $record[5]);
        $t->same($rowNumber, $record[6]);
        $t->same(true, $rowNumber === 1 || $records[$rowNumber - 2][0] < $record[0]);
        $t->same(true, $rowNumber === 101 || $record[0] < $records[$rowNumber][0]);
    };
}

$tests['real upstream corpus btree index dynamic rejects missing indexed column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf([
        ['rowid' => 1, 'cnt' => 1],
    ], ['power']));
};

$tests['real upstream corpus btree index dynamic rejects empty row set'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf([], ['cnt']));
};

$tests['real upstream corpus btree index dynamic rejects empty column list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf([
        ['rowid' => 1, 'cnt' => 1],
    ], []));
};

$tests['real upstream corpus btree index dynamic rejects empty seek prefix'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix([[1, 1]], []));
};

return $tests;
