<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'bucket' => 1.0, 'option_name' => 'siteurl', 'bytes' => 10, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'bucket' => 1.0, 'option_name' => 'home', 'bytes' => 20, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'bucket' => 1.25, 'option_name' => 'blogname', 'bytes' => 30, 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'bucket' => 1.5, 'option_name' => 'active_plugins', 'bytes' => 40, 'include' => true],
    ['rowid' => 5, 'site' => 1, 'bucket' => 2.0, 'option_name' => '_transient_a', 'bytes' => null, 'include' => null],
    ['rowid' => 6, 'site' => 1, 'bucket' => 2.0, 'option_name' => '_transient_b', 'bytes' => 60, 'include' => '2'],
    ['rowid' => 7, 'site' => 2, 'bucket' => 1.0, 'option_name' => 'network_siteurl', 'bytes' => 70, 'include' => 1],
    ['rowid' => 8, 'site' => 2, 'bucket' => 1.4, 'option_name' => 'network_home', 'bytes' => 80, 'include' => '0'],
    ['rowid' => 9, 'site' => 2, 'bucket' => 1.4, 'option_name' => 'network_plugin', 'bytes' => 90, 'include' => 1],
    ['rowid' => 10, 'site' => 2, 'bucket' => 2.1, 'option_name' => 'network_cache', 'bytes' => 100, 'include' => 1],
];

$cursorFor = static function (string $exclude = 'CURRENT ROW', ?string $filter = 'include', float|int $following = 0.5, array $descending = []) use ($rows): SQLiteVdbeWindowAggregateCursor {
    return new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'bytes',
        ['site'],
        ['bucket'],
        $filter,
        0.0,
        $following,
        'D',
        [],
        'D',
        [],
        $descending,
        [],
        'RANGE',
        $exclude,
    );
};

$drainField = static function (SQLiteVdbeWindowAggregateCursor $cursor, string $field): array {
    $values = [];
    while (!$cursor->eof()) {
        $values[] = $cursor->currentYieldSummary('rowid', '|')[$field];
        $cursor->next();
    }

    return $values;
};

$tests = [];

$drainCases = [
    'current row raw frames follow range boundary' => ['CURRENT ROW', 'include', 0.5, 'rawFrameRowids', [[1, 2, 3, 4], [1, 2, 3, 4], [3, 4], [4, 5, 6], [5, 6], [5, 6], [7, 8, 9], [8, 9], [8, 9], [10]]],
    'current row exclude removes only physical current row' => ['CURRENT ROW', 'include', 0.5, 'excludedRowids', [[1], [2], [3], [4], [5], [6], [7], [8], [9], [10]]],
    'current row frame rowids preserve false filter rows before filtering' => ['CURRENT ROW', 'include', 0.5, 'frameRowids', [[2, 3, 4], [1, 3, 4], [4], [5, 6], [6], [5], [8, 9], [9], [8], []]],
    'current row filtered rowids apply aggregate filter after exclusion' => ['CURRENT ROW', 'include', 0.5, 'filteredRowids', [[3, 4], [1, 3, 4], [4], [6], [6], [], [9], [9], [], []]],
    'current row frame values retain nulls before aggregate filtering' => ['CURRENT ROW', 'include', 0.5, 'frameValues', [[20, 30, 40], [10, 30, 40], [40], [null, 60], [60], [null], [80, 90], [90], [80], []]],
    'current row filtered values feed aggregates' => ['CURRENT ROW', 'include', 0.5, 'filteredValues', [[30, 40], [10, 30, 40], [40], [60], [60], [], [90], [90], [], []]],
    'current row count all reports excluded frame rows' => ['CURRENT ROW', 'include', 0.5, 'countAll', [3, 3, 1, 2, 1, 1, 2, 1, 1, 0]],
    'current row count value skips filtered nulls' => ['CURRENT ROW', 'include', 0.5, 'countValue', [2, 3, 1, 1, 1, 0, 1, 1, 0, 0]],
    'current row sum follows filtered range frame' => ['CURRENT ROW', 'include', 0.5, 'sum', [70, 80, 40, 60, 60, null, 90, 90, null, null]],
    'current row total returns zero for empty filtered frame' => ['CURRENT ROW', 'include', 0.5, 'total', [70.0, 80.0, 40.0, 60.0, 60.0, 0.0, 90.0, 90.0, 0.0, 0.0]],
    'current row concat uses requested separator' => ['CURRENT ROW', 'include', 0.5, 'groupConcat', ['30|40', '10|30|40', '40', '60', '60', null, '90', '90', null, null]],
    'current row next rowid exposes VDBE yield step' => ['CURRENT ROW', 'include', 0.5, 'nextRowid', [2, 3, 4, 5, 6, 7, 8, 9, 10, null]],
    'current row next partition tracks boundaries' => ['CURRENT ROW', 'include', 0.5, 'nextSamePartition', [true, true, true, true, true, false, true, true, true, false]],
    'current row next peer tracks duplicate range keys' => ['CURRENT ROW', 'include', 0.5, 'nextSamePeer', [true, false, false, false, true, false, false, true, false, false]],
    'no others keeps raw range frame' => ['NO OTHERS', 'include', 0.5, 'frameRowids', [[1, 2, 3, 4], [1, 2, 3, 4], [3, 4], [4, 5, 6], [5, 6], [5, 6], [7, 8, 9], [8, 9], [8, 9], [10]]],
    'no others filtered rows keep truthy current peers' => ['NO OTHERS', 'include', 0.5, 'filteredRowids', [[1, 3, 4], [1, 3, 4], [3, 4], [4, 6], [6], [6], [7, 9], [9], [9], [10]]],
    'no others excludes nothing' => ['NO OTHERS', 'include', 0.5, 'excludedRowids', [[], [], [], [], [], [], [], [], [], []]],
    'exclude group removes all peers with current order key' => ['GROUP', 'include', 0.5, 'frameRowids', [[3, 4], [3, 4], [4], [5, 6], [], [], [8, 9], [], [], []]],
    'exclude group filtered rows remove false and null peers' => ['GROUP', 'include', 0.5, 'filteredRowids', [[3, 4], [3, 4], [4], [6], [], [], [9], [], [], []]],
    'exclude group records duplicate excluded peer rowids' => ['GROUP', 'include', 0.5, 'excludedRowids', [[1, 2], [1, 2], [3], [4], [5, 6], [5, 6], [7], [8, 9], [8, 9], [10]]],
    'exclude ties keeps current row identity' => ['TIES', 'include', 0.5, 'frameRowids', [[1, 3, 4], [2, 3, 4], [3, 4], [4, 5, 6], [5], [6], [7, 8, 9], [8], [9], [10]]],
    'exclude ties filtered rows keep truthy current peer only' => ['TIES', 'include', 0.5, 'filteredRowids', [[1, 3, 4], [3, 4], [3, 4], [4, 6], [], [6], [7, 9], [], [9], [10]]],
    'exclude ties records only peer ties as excluded' => ['TIES', 'include', 0.5, 'excludedRowids', [[2], [1], [], [], [6], [5], [], [9], [8], []]],
    'wider range reaches later network cache row' => ['CURRENT ROW', 'include', 1.25, 'rawFrameRowids', [[1, 2, 3, 4, 5, 6], [1, 2, 3, 4, 5, 6], [3, 4, 5, 6], [4, 5, 6], [5, 6], [5, 6], [7, 8, 9, 10], [8, 9, 10], [8, 9, 10], [10]]],
    'wider range filtered sums include future cache row' => ['CURRENT ROW', 'include', 1.25, 'sum', [130, 140, 100, 60, 60, null, 190, 190, 100, null]],
    'tiny range keeps only duplicate peer band' => ['CURRENT ROW', 'include', 0.1, 'rawFrameRowids', [[1, 2], [1, 2], [3], [4], [5, 6], [5, 6], [7], [8, 9], [8, 9], [10]]],
    'tiny range filtered sums show peer exclusion edge' => ['CURRENT ROW', 'include', 0.1, 'sum', [null, 10, null, null, 60, null, null, 90, null, null]],
    'unfiltered current row frame rowids are unchanged' => ['CURRENT ROW', null, 0.5, 'filteredRowids', [[2, 3, 4], [1, 3, 4], [4], [5, 6], [6], [5], [8, 9], [9], [8], []]],
    'unfiltered current row sums include false filter rows and skip nulls' => ['CURRENT ROW', null, 0.5, 'sum', [90, 80, 40, 60, 60, null, 170, 90, 80, null]],
    'unfiltered count value only skips null values' => ['CURRENT ROW', null, 0.5, 'countValue', [3, 3, 1, 1, 1, 0, 2, 1, 1, 0]],
];

foreach ($drainCases as $name => [$exclude, $filter, $following, $field, $expected]) {
    $tests['vdbe window exclude filter range current next51 ' . $name] = static function (TestRunner $t) use ($cursorFor, $drainField, $exclude, $filter, $following, $field, $expected): void {
        $t->same($expected, $drainField($cursorFor($exclude, $filter, $following), $field));
    };
}

$positionCases = [
    'row1 summary exposes current rowid' => [0, 'CURRENT ROW', 'currentRowid', 1],
    'row2 summary exposes order key' => [1, 'CURRENT ROW', 'orderKey', [1.0]],
    'row3 summary exposes next order key' => [2, 'CURRENT ROW', 'nextOrderKey', [1.5]],
    'row6 summary exposes next partition key' => [5, 'CURRENT ROW', 'nextPartitionKey', [2]],
    'row10 summary has null next partition key' => [9, 'CURRENT ROW', 'nextPartitionKey', null],
    'row1 group exclusion leaves filtered frame values' => [0, 'GROUP', 'filteredValues', [30, 40]],
    'row2 group exclusion leaves same filtered frame values' => [1, 'GROUP', 'filteredValues', [30, 40]],
    'row5 group exclusion empties duplicate peer tail' => [4, 'GROUP', 'filteredValues', []],
    'row8 ties exclusion keeps false current row unfiltered but filtered drops it' => [7, 'TIES', 'frameValues', [80]],
    'row8 ties exclusion filtered values are empty' => [7, 'TIES', 'filteredValues', []],
    'row9 ties exclusion keeps truthy current peer' => [8, 'TIES', 'filteredValues', [90]],
    'row4 current exclusion shows null plus truthy peer before filtering' => [3, 'CURRENT ROW', 'frameValues', [null, 60]],
    'row4 current exclusion filtered values drop null row' => [3, 'CURRENT ROW', 'filteredValues', [60]],
    'row6 current exclusion leaves null current peer unfiltered' => [5, 'CURRENT ROW', 'frameValues', [null]],
    'row6 current exclusion filtered values empty on null include' => [5, 'CURRENT ROW', 'filteredValues', []],
    'row7 current exclusion keeps false and truthy network peers before filter' => [6, 'CURRENT ROW', 'frameValues', [80, 90]],
    'row7 current exclusion filtered values drop numeric zero string' => [6, 'CURRENT ROW', 'filteredValues', [90]],
    'row10 current exclusion frame is empty' => [9, 'CURRENT ROW', 'frameRowids', []],
    'row10 no others keeps current tail row' => [9, 'NO OTHERS', 'frameRowids', [10]],
    'row10 no others filtered sum keeps tail row' => [9, 'NO OTHERS', 'sum', 100],
];

foreach ($positionCases as $name => [$advance, $exclude, $field, $expected]) {
    $tests['vdbe window exclude filter range current next51 ' . $name] = static function (TestRunner $t) use ($cursorFor, $advance, $exclude, $field, $expected): void {
        $cursor = $cursorFor($exclude);
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $t->same($expected, $cursor->currentYieldSummary('rowid', '|')[$field]);
    };
}

$tests['vdbe window exclude filter range current next51 descending range uses reversed numeric band'] = static function (TestRunner $t) use ($rows, $drainField): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket'], 'include', 0.0, 0.5, 'D', [], 'D', [], [true], [], 'RANGE', 'CURRENT ROW');
    $t->same([[5, 6, 4], [5, 6, 4], [4, 3, 1, 2], [3, 1, 2], [1, 2], [1, 2], [10], [8, 9, 7], [8, 9, 7], [7]], $drainField($cursor, 'rawFrameRowids'));
};

$tests['vdbe window exclude filter range current next51 descending filtered sums respect exclusion'] = static function (TestRunner $t) use ($rows, $drainField): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket'], 'include', 0.0, 0.5, 'D', [], 'D', [], [true], [], 'RANGE', 'CURRENT ROW');
    $t->same([100, 40, 40, 10, null, 10, null, 160, 70, null], $drainField($cursor, 'sum'));
};

$tests['vdbe window exclude filter range current next51 yield summary throws at eof'] = static function (TestRunner $t) use ($cursorFor): void {
    $cursor = $cursorFor();
    while (!$cursor->eof()) {
        $cursor->next();
    }
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentYieldSummary());
};

return $tests;
