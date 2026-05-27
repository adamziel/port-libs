<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [10, 20, 30, 40, 50, 60, 70, 80];
$keys = [1, 1, 2, 4, 4, 7, 9, 9];
$filters = [1, 0, 1, 1, null, '2', '0', true];

$column = static fn (string $unit, int $preceding, int $following, string $name, string $exclude = 'NO OTHERS', ?array $filterValues = null): array => array_column(
    SQLiteWindowFunction::aggregateFrameRows($values, $keys, $unit, $preceding, $following, $exclude, $filterValues),
    $name,
);

$cases = [
    'range numeric frame includes current peer group and distance peers' => [static fn (): mixed => $column('RANGE', 1, 2, 'frame'), [[0, 1, 2], [0, 1, 2], [0, 1, 2, 3, 4], [3, 4], [3, 4], [5, 6, 7], [6, 7], [6, 7]]],
    'range numeric frame counts distance peers' => [static fn (): mixed => $column('RANGE', 1, 2, 'count'), [3, 3, 5, 2, 2, 3, 2, 2]],
    'range numeric frame sums distance peers' => [static fn (): mixed => $column('RANGE', 1, 2, 'sum'), [60, 60, 150, 90, 90, 210, 150, 150]],
    'range numeric frame concat preserves row order' => [static fn (): mixed => $column('RANGE', 1, 2, 'groupConcat'), ['10,20,30', '10,20,30', '10,20,30,40,50', '40,50', '40,50', '60,70,80', '70,80', '70,80']],
    'range current row behaves as peer group frame' => [static fn (): mixed => $column('RANGE', 0, 0, 'frame'), [[0, 1], [0, 1], [2], [3, 4], [3, 4], [5], [6, 7], [6, 7]]],
    'range current row peer counts' => [static fn (): mixed => $column('RANGE', 0, 0, 'count'), [2, 2, 1, 2, 2, 1, 2, 2]],
    'range current row peer sums' => [static fn (): mixed => $column('RANGE', 0, 0, 'sum'), [30, 30, 30, 90, 90, 60, 150, 150]],
    'range following only searches forward by key distance' => [static fn (): mixed => $column('RANGE', 0, 2, 'frame'), [[0, 1, 2], [0, 1, 2], [2, 3, 4], [3, 4], [3, 4], [5, 6, 7], [6, 7], [6, 7]]],
    'range preceding only searches backward by key distance' => [static fn (): mixed => $column('RANGE', 2, 0, 'frame'), [[0, 1], [0, 1], [0, 1, 2], [2, 3, 4], [2, 3, 4], [5], [5, 6, 7], [5, 6, 7]]],
    'range wide frame covers full partition for every row' => [static fn (): mixed => $column('RANGE', 99, 99, 'count'), [8, 8, 8, 8, 8, 8, 8, 8]],
    'range exclude current removes just current row' => [static fn (): mixed => $column('RANGE', 0, 0, 'frame', 'CURRENT ROW'), [[1], [0], [], [4], [3], [], [7], [6]]],
    'range exclude group removes all current peers' => [static fn (): mixed => $column('RANGE', 1, 2, 'frame', 'GROUP'), [[2], [2], [0, 1, 3, 4], [], [], [6, 7], [], []]],
    'range exclude group sums remaining distance rows' => [static fn (): mixed => $column('RANGE', 1, 2, 'sum', 'GROUP'), [30, 30, 120, null, null, 150, null, null]],
    'range exclude ties keeps current but removes peer ties' => [static fn (): mixed => $column('RANGE', 1, 2, 'frame', 'TIES'), [[0, 2], [1, 2], [0, 1, 2, 3, 4], [3], [4], [5, 6, 7], [6], [7]]],
    'range exclude ties sums keep current rows' => [static fn (): mixed => $column('RANGE', 1, 2, 'sum', 'TIES'), [40, 50, 150, 40, 50, 210, 70, 80]],
    'range filter applies after distance frame' => [static fn (): mixed => $column('RANGE', 1, 2, 'frame', 'NO OTHERS', $filters), [[0, 2], [0, 2], [0, 2, 3], [3], [3], [5, 7], [7], [7]]],
    'range filter counts truthy rows after frame' => [static fn (): mixed => $column('RANGE', 1, 2, 'count', 'NO OTHERS', $filters), [2, 2, 3, 1, 1, 2, 1, 1]],
    'range filter sums truthy rows after frame' => [static fn (): mixed => $column('RANGE', 1, 2, 'sum', 'NO OTHERS', $filters), [40, 40, 80, 40, 40, 140, 80, 80]],
    'range filter combines with group exclusion' => [static fn (): mixed => $column('RANGE', 1, 2, 'frame', 'GROUP', $filters), [[2], [2], [0, 3], [], [], [7], [], []]],
    'range boolean keys compare as numeric keys' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [false, true, true], 'RANGE', 0, 0), 'frame'), [[0], [1, 2], [1, 2]]],

    'groups frame walks peer groups instead of rows' => [static fn (): mixed => $column('GROUPS', 1, 1, 'frame'), [[0, 1, 2], [0, 1, 2], [0, 1, 2, 3, 4], [2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5, 6, 7], [5, 6, 7], [5, 6, 7]]],
    'groups frame counts peer group windows' => [static fn (): mixed => $column('GROUPS', 1, 1, 'count'), [3, 3, 5, 4, 4, 5, 3, 3]],
    'groups frame sums peer group windows' => [static fn (): mixed => $column('GROUPS', 1, 1, 'sum'), [60, 60, 150, 180, 180, 300, 210, 210]],
    'groups frame concat preserves group row order' => [static fn (): mixed => $column('GROUPS', 1, 1, 'groupConcat'), ['10,20,30', '10,20,30', '10,20,30,40,50', '30,40,50,60', '30,40,50,60', '40,50,60,70,80', '60,70,80', '60,70,80']],
    'groups current row unit expands to current peer group' => [static fn (): mixed => $column('GROUPS', 0, 0, 'frame'), [[0, 1], [0, 1], [2], [3, 4], [3, 4], [5], [6, 7], [6, 7]]],
    'groups two preceding reaches earlier peer groups' => [static fn (): mixed => $column('GROUPS', 2, 0, 'frame'), [[0, 1], [0, 1], [0, 1, 2], [0, 1, 2, 3, 4], [0, 1, 2, 3, 4], [2, 3, 4, 5], [3, 4, 5, 6, 7], [3, 4, 5, 6, 7]]],
    'groups two following reaches later peer groups' => [static fn (): mixed => $column('GROUPS', 0, 2, 'frame'), [[0, 1, 2, 3, 4], [0, 1, 2, 3, 4], [2, 3, 4, 5], [3, 4, 5, 6, 7], [3, 4, 5, 6, 7], [5, 6, 7], [6, 7], [6, 7]]],
    'groups wide frame covers full partition' => [static fn (): mixed => $column('GROUPS', 99, 99, 'sum'), [360, 360, 360, 360, 360, 360, 360, 360]],
    'groups exclude current leaves peers in current group' => [static fn (): mixed => $column('GROUPS', 0, 0, 'frame', 'CURRENT ROW'), [[1], [0], [], [4], [3], [], [7], [6]]],
    'groups exclude group removes current peer group' => [static fn (): mixed => $column('GROUPS', 1, 1, 'frame', 'GROUP'), [[2], [2], [0, 1, 3, 4], [2, 5], [2, 5], [3, 4, 6, 7], [5], [5]]],
    'groups exclude group sums neighboring groups' => [static fn (): mixed => $column('GROUPS', 1, 1, 'sum', 'GROUP'), [30, 30, 120, 90, 90, 240, 60, 60]],
    'groups exclude ties keeps current peer only' => [static fn (): mixed => $column('GROUPS', 1, 1, 'frame', 'TIES'), [[0, 2], [1, 2], [0, 1, 2, 3, 4], [2, 3, 5], [2, 4, 5], [3, 4, 5, 6, 7], [5, 6], [5, 7]]],
    'groups exclude ties sums keep current row' => [static fn (): mixed => $column('GROUPS', 1, 1, 'sum', 'TIES'), [40, 50, 150, 130, 140, 300, 130, 140]],
    'groups filter applies after peer frame' => [static fn (): mixed => $column('GROUPS', 1, 1, 'frame', 'NO OTHERS', $filters), [[0, 2], [0, 2], [0, 2, 3], [2, 3, 5], [2, 3, 5], [3, 5, 7], [5, 7], [5, 7]]],
    'groups filter sums truthy rows after peer frame' => [static fn (): mixed => $column('GROUPS', 1, 1, 'sum', 'NO OTHERS', $filters), [40, 40, 80, 130, 130, 180, 140, 140]],
    'groups filter combines with ties exclusion' => [static fn (): mixed => $column('GROUPS', 1, 1, 'frame', 'TIES', $filters), [[0, 2], [2], [0, 2, 3], [2, 3, 5], [2, 5], [3, 5, 7], [5], [5, 7]]],
    'groups null keys form one peer group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [null, null, 1, 2], 'GROUPS', 0, 1), 'frame'), [[0, 1, 2], [0, 1, 2], [2, 3], [3]]],
    'groups blob keys form byte peers' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [new SQLiteBlobValue('aa'), new SQLiteBlobValue('aa'), new SQLiteBlobValue('ab'), new SQLiteBlobValue('ac')], 'GROUPS', 1, 0, 'GROUP'), 'frame'), [[], [], [0, 1], [2]]],
    'groups empty partition returns empty result' => [static fn (): mixed => SQLiteWindowFunction::aggregateFrameRows([], [], 'GROUPS', 1, 1), []],
    'range empty partition returns empty result' => [static fn (): mixed => SQLiteWindowFunction::aggregateFrameRows([], [], 'RANGE', 1, 1), []],
    'rows frame unit preserves existing aggregate row behavior' => [static fn (): mixed => $column('ROWS', 1, 0, 'frame'), [[0], [0, 1], [1, 2], [2, 3], [3, 4], [4, 5], [5, 6], [6, 7]]],
    'lowercase frame unit names are accepted' => [static fn (): mixed => $column('groups', 0, 0, 'count'), [2, 2, 1, 2, 2, 1, 2, 2]],
    'range single peer group with ties exclusion keeps current rows' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([5, 6, 7], [3, 3, 3], 'RANGE', 0, 0, 'TIES'), 'frame'), [[0], [1], [2]]],
    'groups single peer group with group exclusion empties rows' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([5, 6, 7], [3, 3, 3], 'GROUPS', 0, 0, 'GROUP'), 'frame'), [[], [], []]],
    'range current row exclusion can empty single row peer group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([5], [3], 'RANGE', 0, 0, 'CURRENT ROW'), 'sum'), [null]],
    'groups current row exclusion can empty single row peer group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([5], [3], 'GROUPS', 0, 0, 'CURRENT ROW'), 'sum'), [null]],
    'range filter false peers can empty current range' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([5, 6], [3, 3], 'RANGE', 0, 0, 'NO OTHERS', [0, '0.0']), 'sum'), [null, null]],
    'groups filter false peers can empty current group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([5, 6], [3, 3], 'GROUPS', 0, 0, 'NO OTHERS', [0, '0.0']), 'count'), [0, 0]],
    'range decimal keys honor numeric distance' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [1.0, 1.5, 2.25, 3.0], 'RANGE', 1, 0), 'frame'), [[0], [0, 1], [1, 2], [2, 3]]],
    'range decimal key sums honor numeric distance' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [1.0, 1.5, 2.25, 3.0], 'RANGE', 1, 0), 'sum'), [1, 3, 5, 7]],
    'groups frame with following zero clamps at first group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [1, 1, 2, 3], 'GROUPS', 1, 0), 'frame'), [[0, 1], [0, 1], [0, 1, 2], [2, 3]]],
    'groups frame with preceding zero clamps at last group' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [1, 2, 3, 3], 'GROUPS', 0, 1), 'frame'), [[0, 1], [1, 2, 3], [2, 3], [2, 3]]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream corpus window range groups exclude next10 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['upstream corpus window range groups exclude next10 rejects unknown frame unit'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1], [1], 'SIDEWAYS', 0, 0));
};

$tests['upstream corpus window range groups exclude next10 rejects non numeric range keys'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1], ['a'], 'RANGE', 0, 0));
};

$tests['upstream corpus window range groups exclude next10 rejects invalid offsets'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1], [1], 'GROUPS', -1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1], [1], 'RANGE', 0, -1));
};

$tests['upstream corpus window range groups exclude next10 rejects mismatched inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1, 2], [1], 'GROUPS', 0, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameRows([1], [1], 'GROUPS', 0, 0, 'NO OTHERS', [1, 2]));
};

return $tests;
