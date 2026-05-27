<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [10, 20, 30, 40, 50];
$keys = ['a', 'a', 'b', 'c', 'c'];
$filters = [1, 0, 1, null, '2'];

$windowCases = [
    'no others count over preceding current' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 0), 'count'), [1, 2, 2, 2, 2]],
    'no others sum over preceding current' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 0), 'sum'), [10, 30, 50, 70, 90]],
    'no others concat over preceding current' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 0), 'groupConcat'), ['10', '10,20', '20,30', '30,40', '40,50']],
    'current row excludes only current count' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'CURRENT ROW'), 'count'), [1, 2, 2, 2, 1]],
    'current row excludes only current sum' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'CURRENT ROW'), 'sum'), [20, 40, 60, 80, 40]],
    'current row excludes only current frames' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'CURRENT ROW'), 'frame'), [[1], [0, 2], [1, 3], [2, 4], [3]]],
    'group excludes peer group count' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'GROUP'), 'count'), [0, 1, 2, 1, 0]],
    'group excludes peer group sum' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'GROUP'), 'sum'), [null, 30, 60, 30, null]],
    'group excludes peer group frames' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'GROUP'), 'frame'), [[], [2], [1, 3], [2], []]],
    'ties keeps current but excludes peers count' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'TIES'), 'count'), [1, 2, 3, 2, 1]],
    'ties keeps current but excludes peers sum' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'TIES'), 'sum'), [10, 50, 90, 70, 50]],
    'ties keeps current but excludes peers frames' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'TIES'), 'frame'), [[0], [1, 2], [1, 2, 3], [2, 3], [4]]],
    'filter count applies after no others frame' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'NO OTHERS', $filters), 'count'), [1, 2, 1, 2, 1]],
    'filter sum applies after no others frame' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'NO OTHERS', $filters), 'sum'), [10, 40, 30, 80, 50]],
    'filter concat applies after no others frame' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'NO OTHERS', $filters), 'groupConcat'), ['10', '10,30', '30', '30,50', '50']],
    'filter combines with current row exclude' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'CURRENT ROW', $filters), 'sum'), [null, 40, null, 80, null]],
    'filter combines with group exclude' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'GROUP', $filters), 'sum'), [null, 30, null, 30, null]],
    'filter combines with ties exclude' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 1, 'TIES', $filters), 'sum'), [10, 30, 30, 30, 50]],
    'unbounded looking frame clamps at partition start' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 9, 0), 'frame'), [[0], [0, 1], [0, 1, 2], [0, 1, 2, 3], [0, 1, 2, 3, 4]]],
    'unbounded looking frame clamps at partition end' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 0, 9), 'frame'), [[0, 1, 2, 3, 4], [1, 2, 3, 4], [2, 3, 4], [3, 4], [4]]],
    'empty input returns empty aggregates' => [static fn (): mixed => SQLiteWindowFunction::aggregateRows([], [], 1, 1), []],
    'null values count but do not sum or concat' => [static fn (): mixed => SQLiteWindowFunction::aggregateRows([null, 2, null], [1, 2, 3], 1, 0), [['count' => 1, 'sum' => null, 'groupConcat' => null, 'frame' => [0]], ['count' => 2, 'sum' => 2, 'groupConcat' => '2', 'frame' => [0, 1]], ['count' => 2, 'sum' => 2, 'groupConcat' => '2', 'frame' => [1, 2]]]],
    'boolean values sum as numeric values' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([true, false, 3], [1, 2, 3], 1, 0), 'sum'), [1, 1, 3]],
    'string filter numeric truthiness matches sqlite' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([1, 2, 3, 4], [1, 2, 3, 4], 1, 0, 'NO OTHERS', ['0', '1x', '2', '']), 'sum'), [null, 2, 5, 3]],
    'blob order peers are compared by bytes' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([1, 2, 3], [new SQLiteBlobValue('a'), new SQLiteBlobValue('a'), new SQLiteBlobValue('b')], 1, 1, 'GROUP'), 'frame'), [[], [2], [1]]],
    'case insensitive exclude spelling is accepted' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([1, 2, 3], [1, 1, 2], 1, 1, 'ties'), 'sum'), [1, 5, 5]],
    'wide current row exclude leaves empty single row frame' => [static fn (): mixed => SQLiteWindowFunction::aggregateRows([7], [1], 9, 9, 'CURRENT ROW'), [['count' => 0, 'sum' => null, 'groupConcat' => null, 'frame' => []]]],
    'wide ties exclude keeps single row frame' => [static fn (): mixed => SQLiteWindowFunction::aggregateRows([7], [1], 9, 9, 'TIES'), [['count' => 1, 'sum' => 7, 'groupConcat' => '7', 'frame' => [0]]]],
    'peer null order keys are excluded as a group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows([1, 2, 3], [null, null, 1], 1, 1, 'GROUP'), 'frame'), [[], [2], [1]]],
    'filter false rows still appear in diagnostic frame before filtering' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateRows($values, $keys, 1, 0, 'NO OTHERS', $filters), 'frame'), [[0], [0], [2], [2], [4]]],
];

foreach ($windowCases as $name => [$callback, $expected]) {
    $tests['upstream corpus window exclude filter ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['upstream corpus window exclude filter rejects invalid frame offsets'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], -1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], 0, -1));
};

$tests['upstream corpus window exclude filter rejects mismatched order keys'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1, 2], [1], 0, 0));
};

$tests['upstream corpus window exclude filter rejects mismatched filter values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1, 2], [1, 2], 0, 0, 'NO OTHERS', [1]));
};

$tests['upstream corpus window exclude filter rejects unknown exclude mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], 0, 0, 'SIDEWAYS'));
};

$tests['upstream corpus window exclude filter accepts composite rows keys and rejects unsupported compared members'] = static function (TestRunner $t): void {
    $summary = SQLiteWindowFunction::aggregateRows([1], [['bad']], 0, 0);
    $t->same([0], $summary[0]['frame']);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1, 2], [[new stdClass()], [1]], 'GROUPS', 0, 0));
};

$tests['upstream corpus window exclude filter rejects non scalar filter values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows([1], [1], 0, 0, 'NO OTHERS', [[]]));
};

$tests['upstream corpus window exclude filter rejects non numeric sum values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateRows(['text'], [1], 0, 0));
};

return $tests;
