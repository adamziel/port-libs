<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [5, 10, 15, 20, 25, 30];
$keys = [1, 1, 2, 3, 3, 4];
$filters = [1, 0, '2', null, true, false];

$column = static fn (int $preceding, int $following, string $name, string $exclude = 'NO OTHERS', ?array $filterValues = null): array => array_column(
    SQLiteWindowFunction::aggregateRows($values, $keys, $preceding, $following, $exclude, $filterValues),
    $name,
);

$boundaryCases = [
    'current row frames stay single row' => [static fn (): mixed => $column(0, 0, 'frame'), [[0], [1], [2], [3], [4], [5]]],
    'current row counts stay one' => [static fn (): mixed => $column(0, 0, 'count'), [1, 1, 1, 1, 1, 1]],
    'current row sums use only current value' => [static fn (): mixed => $column(0, 0, 'sum'), [5, 10, 15, 20, 25, 30]],
    'current row concat uses only current value' => [static fn (): mixed => $column(0, 0, 'groupConcat'), ['5', '10', '15', '20', '25', '30']],

    'one preceding clamps at first row' => [static fn (): mixed => $column(1, 0, 'frame'), [[0], [0, 1], [1, 2], [2, 3], [3, 4], [4, 5]]],
    'one preceding counts include clamped first row' => [static fn (): mixed => $column(1, 0, 'count'), [1, 2, 2, 2, 2, 2]],
    'one preceding sums slide forward' => [static fn (): mixed => $column(1, 0, 'sum'), [5, 15, 25, 35, 45, 55]],
    'one preceding concat preserves row order' => [static fn (): mixed => $column(1, 0, 'groupConcat'), ['5', '5,10', '10,15', '15,20', '20,25', '25,30']],

    'one following clamps at last row' => [static fn (): mixed => $column(0, 1, 'frame'), [[0, 1], [1, 2], [2, 3], [3, 4], [4, 5], [5]]],
    'one following counts include clamped last row' => [static fn (): mixed => $column(0, 1, 'count'), [2, 2, 2, 2, 2, 1]],
    'one following sums slide forward' => [static fn (): mixed => $column(0, 1, 'sum'), [15, 25, 35, 45, 55, 30]],
    'one following concat preserves row order' => [static fn (): mixed => $column(0, 1, 'groupConcat'), ['5,10', '10,15', '15,20', '20,25', '25,30', '30']],

    'two preceding one following frame shape' => [static fn (): mixed => $column(2, 1, 'frame'), [[0, 1], [0, 1, 2], [0, 1, 2, 3], [1, 2, 3, 4], [2, 3, 4, 5], [3, 4, 5]]],
    'two preceding one following counts' => [static fn (): mixed => $column(2, 1, 'count'), [2, 3, 4, 4, 4, 3]],
    'two preceding one following sums' => [static fn (): mixed => $column(2, 1, 'sum'), [15, 30, 50, 70, 90, 75]],
    'two preceding one following concat' => [static fn (): mixed => $column(2, 1, 'groupConcat'), ['5,10', '5,10,15', '5,10,15,20', '10,15,20,25', '15,20,25,30', '20,25,30']],

    'one preceding two following frame shape' => [static fn (): mixed => $column(1, 2, 'frame'), [[0, 1, 2], [0, 1, 2, 3], [1, 2, 3, 4], [2, 3, 4, 5], [3, 4, 5], [4, 5]]],
    'one preceding two following counts' => [static fn (): mixed => $column(1, 2, 'count'), [3, 4, 4, 4, 3, 2]],
    'one preceding two following sums' => [static fn (): mixed => $column(1, 2, 'sum'), [30, 50, 70, 90, 75, 55]],
    'one preceding two following concat' => [static fn (): mixed => $column(1, 2, 'groupConcat'), ['5,10,15', '5,10,15,20', '10,15,20,25', '15,20,25,30', '20,25,30', '25,30']],

    'wide frame clamps to partition for every row' => [static fn (): mixed => $column(99, 99, 'frame'), [[0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5]]],
    'wide frame counts full partition' => [static fn (): mixed => $column(99, 99, 'count'), [6, 6, 6, 6, 6, 6]],
    'wide frame sums full partition' => [static fn (): mixed => $column(99, 99, 'sum'), [105, 105, 105, 105, 105, 105]],
    'wide frame concat full partition' => [static fn (): mixed => $column(99, 99, 'groupConcat'), ['5,10,15,20,25,30', '5,10,15,20,25,30', '5,10,15,20,25,30', '5,10,15,20,25,30', '5,10,15,20,25,30', '5,10,15,20,25,30']],

    'unbounded preceding style frame grows from start' => [static fn (): mixed => $column(99, 0, 'frame'), [[0], [0, 1], [0, 1, 2], [0, 1, 2, 3], [0, 1, 2, 3, 4], [0, 1, 2, 3, 4, 5]]],
    'unbounded preceding style sums grow from start' => [static fn (): mixed => $column(99, 0, 'sum'), [5, 15, 30, 50, 75, 105]],
    'unbounded following style frame shrinks to end' => [static fn (): mixed => $column(0, 99, 'frame'), [[0, 1, 2, 3, 4, 5], [1, 2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5], [4, 5], [5]]],
    'unbounded following style sums shrink to end' => [static fn (): mixed => $column(0, 99, 'sum'), [105, 100, 90, 75, 55, 30]],

    'current row exclusion empties current-only frame' => [static fn (): mixed => $column(0, 0, 'frame', 'CURRENT ROW'), [[], [], [], [], [], []]],
    'current row exclusion current-only counts zero' => [static fn (): mixed => $column(0, 0, 'count', 'CURRENT ROW'), [0, 0, 0, 0, 0, 0]],
    'current row exclusion current-only sums null' => [static fn (): mixed => $column(0, 0, 'sum', 'CURRENT ROW'), [null, null, null, null, null, null]],
    'current row exclusion current-only concat null' => [static fn (): mixed => $column(0, 0, 'groupConcat', 'CURRENT ROW'), [null, null, null, null, null, null]],

    'current row exclusion leaves neighbors at low boundary' => [static fn (): mixed => $column(0, 1, 'frame', 'CURRENT ROW'), [[1], [2], [3], [4], [5], []]],
    'current row exclusion leaves neighbors at high boundary' => [static fn (): mixed => $column(1, 0, 'frame', 'CURRENT ROW'), [[], [0], [1], [2], [3], [4]]],
    'group exclusion removes leading peer group inside wide frame' => [static fn (): mixed => $column(99, 99, 'frame', 'GROUP'), [[2, 3, 4, 5], [2, 3, 4, 5], [0, 1, 3, 4, 5], [0, 1, 2, 5], [0, 1, 2, 5], [0, 1, 2, 3, 4]]],
    'group exclusion wide sums' => [static fn (): mixed => $column(99, 99, 'sum', 'GROUP'), [90, 90, 90, 60, 60, 75]],
    'ties exclusion keeps current while removing peers' => [static fn (): mixed => $column(99, 99, 'frame', 'TIES'), [[0, 2, 3, 4, 5], [1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 5], [0, 1, 2, 4, 5], [0, 1, 2, 3, 4, 5]]],
    'ties exclusion wide sums keep current row' => [static fn (): mixed => $column(99, 99, 'sum', 'TIES'), [95, 100, 105, 80, 85, 105]],
    'ties exclusion boundary with following peer' => [static fn (): mixed => $column(0, 1, 'frame', 'TIES'), [[0], [1, 2], [2, 3], [3], [4, 5], [5]]],
    'group exclusion boundary with following peer' => [static fn (): mixed => $column(0, 1, 'frame', 'GROUP'), [[], [2], [3], [], [5], []]],

    'filter after frame keeps truthy rows only' => [static fn (): mixed => $column(1, 1, 'frame', 'NO OTHERS', $filters), [[0], [0, 2], [2], [2, 4], [4], [4]]],
    'filter after frame counts truthy rows only' => [static fn (): mixed => $column(1, 1, 'count', 'NO OTHERS', $filters), [1, 2, 1, 2, 1, 1]],
    'filter after frame sums truthy rows only' => [static fn (): mixed => $column(1, 1, 'sum', 'NO OTHERS', $filters), [5, 20, 15, 40, 25, 25]],
    'filter after current-row exclusion can empty boundary frames' => [static fn (): mixed => $column(1, 1, 'frame', 'CURRENT ROW', $filters), [[], [0, 2], [], [2, 4], [], [4]]],
    'filter after group exclusion honors peer removal' => [static fn (): mixed => $column(1, 1, 'frame', 'GROUP', $filters), [[], [2], [], [2], [], [4]]],
    'filter after ties exclusion keeps current truthy peer' => [static fn (): mixed => $column(1, 1, 'frame', 'TIES', $filters), [[0], [2], [2], [2], [4], [4]]],

    'null values count in boundary frame' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([null, 4, null, 8], [1, 2, 3, 4], 1, 1), 'count'), [2, 3, 3, 2]],
    'null values do not contribute to boundary sums' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([null, 4, null, 8], [1, 2, 3, 4], 1, 1), 'sum'), [4, 4, 12, 8]],
    'null values do not contribute to boundary concat' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([null, 4, null, 8], [1, 2, 3, 4], 1, 1), 'groupConcat'), ['4', '4', '4,8', '8']],
    'boolean values are numeric in boundary sums' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([true, false, true], [1, 2, 3], 1, 1), 'sum'), [1, 2, 1]],
    'blob peers group at boundary by byte equality' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([1, 2, 3, 4], [new SQLiteBlobValue('aa'), new SQLiteBlobValue('aa'), new SQLiteBlobValue('ab'), new SQLiteBlobValue('ab')], 1, 1, 'GROUP'), 'frame'), [[], [2], [1], []]],
    'string numeric filters preserve sqlite truthiness at boundary' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([1, 2, 3, 4], [1, 2, 3, 4], 1, 1, 'NO OTHERS', ['0x', '0.0', '0.5x', '2e0']), 'frame'), [[], [2], [2, 3], [2, 3]]],
];

foreach ($boundaryCases as $name => [$callback, $expected]) {
    $tests['upstream corpus window frame boundary ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['upstream corpus window frame boundary rejects negative preceding offset'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], -1, 0));
};

$tests['upstream corpus window frame boundary rejects negative following offset'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], 0, -1));
};

$tests['upstream corpus window frame boundary rejects mismatched order keys'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1, 2], [1], 1, 1));
};

$tests['upstream corpus window frame boundary rejects mismatched filters'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1, 2], [1, 2], 1, 1, 'NO OTHERS', [1]));
};

$tests['upstream corpus window frame boundary rejects non scalar filter values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], 1, 1, 'NO OTHERS', [new stdClass()]));
};

$tests['upstream corpus window frame boundary rejects non numeric sum payloads'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows(['text'], [1], 1, 1));
};

return $tests;
