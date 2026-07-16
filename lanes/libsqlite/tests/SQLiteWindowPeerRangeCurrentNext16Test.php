<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [10, 20, 30, 40, 50, 60, 70, 80];
$keys = [1.0, 1.0, 1.25, 1.5, 2.0, 2.0, 2.75, 3.25];
$filters = [1, 0, '1', true, null, '2', '0', 1];

$column = static fn (float $preceding, float $following, string $name, string $exclude = 'NO OTHERS', ?array $filterValues = null): array => array_column(
    SQLiteWindowFunction::aggregateFrameRows($values, $keys, 'RANGE', $preceding, $following, $exclude, $filterValues),
    $name,
);

$cases = [
    'fractional current to following peers frame' => [static fn (): mixed => $column(0.0, 0.5, 'frame'), [[0, 1, 2, 3], [0, 1, 2, 3], [2, 3], [3, 4, 5], [4, 5], [4, 5], [6, 7], [7]]],
    'fractional current to following peers count' => [static fn (): mixed => $column(0.0, 0.5, 'count'), [4, 4, 2, 3, 2, 2, 2, 1]],
    'fractional current to following peers sum' => [static fn (): mixed => $column(0.0, 0.5, 'sum'), [100, 100, 70, 150, 110, 110, 150, 80]],
    'fractional current to following peers concat' => [static fn (): mixed => $column(0.0, 0.5, 'groupConcat'), ['10,20,30,40', '10,20,30,40', '30,40', '40,50,60', '50,60', '50,60', '70,80', '80']],
    'fractional preceding to current peers frame' => [static fn (): mixed => $column(0.25, 0.0, 'frame'), [[0, 1], [0, 1], [0, 1, 2], [2, 3], [4, 5], [4, 5], [6], [7]]],
    'fractional preceding to current peers count' => [static fn (): mixed => $column(0.25, 0.0, 'count'), [2, 2, 3, 2, 2, 2, 1, 1]],
    'fractional preceding to current peers sum' => [static fn (): mixed => $column(0.25, 0.0, 'sum'), [30, 30, 60, 70, 110, 110, 70, 80]],
    'fractional preceding to current peers concat' => [static fn (): mixed => $column(0.25, 0.0, 'groupConcat'), ['10,20', '10,20', '10,20,30', '30,40', '50,60', '50,60', '70', '80']],
    'fractional band around current row frame' => [static fn (): mixed => $column(0.25, 0.5, 'frame'), [[0, 1, 2, 3], [0, 1, 2, 3], [0, 1, 2, 3], [2, 3, 4, 5], [4, 5], [4, 5], [6, 7], [7]]],
    'fractional band around current row count' => [static fn (): mixed => $column(0.25, 0.5, 'count'), [4, 4, 4, 4, 2, 2, 2, 1]],
    'fractional band around current row sum' => [static fn (): mixed => $column(0.25, 0.5, 'sum'), [100, 100, 100, 180, 110, 110, 150, 80]],
    'fractional band around current row concat' => [static fn (): mixed => $column(0.25, 0.5, 'groupConcat'), ['10,20,30,40', '10,20,30,40', '10,20,30,40', '30,40,50,60', '50,60', '50,60', '70,80', '80']],
    'fractional following exactly reaches boundary peer' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [1.0, 1.25, 1.5, 1.75], 'RANGE', 0.0, 0.5), 'frame'), [[0, 1, 2], [1, 2, 3], [2, 3], [3]]],
    'fractional preceding exactly reaches boundary peer' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [1.0, 1.25, 1.5, 1.75], 'RANGE', 0.5, 0.0), 'frame'), [[0], [0, 1], [0, 1, 2], [1, 2, 3]]],
    'fractional tiny following keeps current peer group only' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [2.0, 2.0, 2.1, 2.2], 'RANGE', 0.0, 0.05), 'frame'), [[0, 1], [0, 1], [2], [3]]],
    'fractional tiny preceding keeps current peer group only' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [2.0, 2.0, 2.1, 2.2], 'RANGE', 0.05, 0.0), 'frame'), [[0, 1], [0, 1], [2], [3]]],
    'fractional current row expands duplicate decimal peers' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.25, 1.25, 1.25], 'RANGE', 0.0, 0.0), 'sum'), [6, 6, 6]],
    'fractional current row exclude current leaves decimal peers' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.25, 1.25, 1.25], 'RANGE', 0.0, 0.0, 'CURRENT ROW'), 'frame'), [[1, 2], [0, 2], [0, 1]]],
    'fractional current row exclude group removes decimal peers' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.25, 1.25, 1.25], 'RANGE', 0.0, 0.0, 'GROUP'), 'sum'), [null, null, null]],
    'fractional current row exclude ties keeps each decimal peer current' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.25, 1.25, 1.25], 'RANGE', 0.0, 0.0, 'TIES'), 'frame'), [[0], [1], [2]]],
    'fractional following exclude group leaves future peer band' => [static fn (): mixed => $column(0.0, 0.5, 'frame', 'GROUP'), [[2, 3], [2, 3], [3], [4, 5], [], [], [7], []]],
    'fractional following exclude group sums future peer band' => [static fn (): mixed => $column(0.0, 0.5, 'sum', 'GROUP'), [70, 70, 40, 110, null, null, 80, null]],
    'fractional following exclude ties keeps current peer row only' => [static fn (): mixed => $column(0.0, 0.5, 'frame', 'TIES'), [[0, 2, 3], [1, 2, 3], [2, 3], [3, 4, 5], [4], [5], [6, 7], [7]]],
    'fractional following exclude ties sums keep current peer row only' => [static fn (): mixed => $column(0.0, 0.5, 'sum', 'TIES'), [80, 90, 70, 150, 50, 60, 150, 80]],
    'fractional following filters after peer frame' => [static fn (): mixed => $column(0.0, 0.5, 'frame', 'NO OTHERS', $filters), [[0, 2, 3], [0, 2, 3], [2, 3], [3, 5], [5], [5], [7], [7]]],
    'fractional following filter counts truthy rows' => [static fn (): mixed => $column(0.0, 0.5, 'count', 'NO OTHERS', $filters), [3, 3, 2, 2, 1, 1, 1, 1]],
    'fractional following filter sums truthy rows' => [static fn (): mixed => $column(0.0, 0.5, 'sum', 'NO OTHERS', $filters), [80, 80, 70, 100, 60, 60, 80, 80]],
    'fractional following filter combines with current row exclude' => [static fn (): mixed => $column(0.0, 0.5, 'frame', 'CURRENT ROW', $filters), [[2, 3], [0, 2, 3], [3], [5], [5], [], [7], []]],
    'fractional following filter combines with group exclude' => [static fn (): mixed => $column(0.0, 0.5, 'frame', 'GROUP', $filters), [[2, 3], [2, 3], [3], [5], [], [], [7], []]],
    'fractional following filter combines with ties exclude' => [static fn (): mixed => $column(0.0, 0.5, 'frame', 'TIES', $filters), [[0, 2, 3], [2, 3], [2, 3], [3, 5], [], [5], [7], [7]]],
    'fractional negative keys current to following' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4, 5], [-2.0, -1.75, -1.5, -1.5, -1.0], 'RANGE', 0.0, 0.5), 'frame'), [[0, 1, 2, 3], [1, 2, 3], [2, 3, 4], [2, 3, 4], [4]]],
    'fractional negative keys preceding to current' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4, 5], [-2.0, -1.75, -1.5, -1.5, -1.0], 'RANGE', 0.5, 0.0), 'frame'), [[0], [0, 1], [0, 1, 2, 3], [0, 1, 2, 3], [2, 3, 4]]],
    'fractional boolean keys still group numerically' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [false, false, true, true], 'RANGE', 0.0, 0.5), 'frame'), [[0, 1], [0, 1], [2, 3], [2, 3]]],
    'fractional integer float offsets are accepted for rows' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1, 2, 3], 'ROWS', 1.0, 0.0), 'frame'), [[0], [0, 1], [1, 2]]],
    'fractional integer float offsets are accepted for groups' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1, 1, 2], 'GROUPS', 1.0, 0.0), 'frame'), [[0, 1], [0, 1], [0, 1, 2]]],
    'fractional range offset can be smaller than one' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [10.0, 10.4, 10.8, 11.2], 'RANGE', 0.0, 0.39), 'frame'), [[0], [1], [2], [3]]],
    'fractional range offset includes floating point boundary' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3, 4], [10.0, 10.4, 10.8, 11.2], 'RANGE', 0.0, 0.4), 'frame'), [[0, 1], [1, 2], [2, 3], [3]]],
    'fractional range offset with null payload counts peers' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([null, 2, null, 4], [1.0, 1.0, 1.4, 1.8], 'RANGE', 0.0, 0.4), 'count'), [3, 3, 2, 1]],
    'fractional range offset with null payload sums non null peers' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([null, 2, null, 4], [1.0, 1.0, 1.4, 1.8], 'RANGE', 0.0, 0.4), 'sum'), [2, 2, 4, 4]],
    'fractional range offset with null payload concat skips nulls' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([null, 2, null, 4], [1.0, 1.0, 1.4, 1.8], 'RANGE', 0.0, 0.4), 'groupConcat'), ['2', '2', '4', '4']],
    'fractional current next all false filter empties peer band' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.0, 1.0, 1.25], 'RANGE', 0.0, 0.25, 'NO OTHERS', [0, '0.0', null]), 'count'), [0, 0, 0]],
    'fractional current next string truth filter keeps numeric text' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.0, 1.0, 1.25], 'RANGE', 0.0, 0.25, 'NO OTHERS', ['0x', '0.5x', '2e0']), 'frame'), [[1, 2], [1, 2], [2]]],
    'fractional range uppercase unit remains accepted' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2], [1.0, 1.25], 'range', 0.0, 0.25), 'sum'), [3, 2]],
    'fractional range single row current following' => [static fn (): mixed => SQLiteWindowFunction::aggregateFrameRows([9], [1.5], 'RANGE', 0.0, 0.5), [['count' => 1, 'sum' => 9, 'groupConcat' => '9', 'frame' => [0]]]],
    'fractional range single row current exclude current' => [static fn (): mixed => SQLiteWindowFunction::aggregateFrameRows([9], [1.5], 'RANGE', 0.0, 0.5, 'CURRENT ROW'), [['count' => 0, 'sum' => null, 'groupConcat' => null, 'frame' => []]]],
    'fractional range empty partition returns empty rows' => [static fn (): mixed => SQLiteWindowFunction::aggregateFrameRows([], [], 'RANGE', 0.0, 0.5), []],
    'fractional range zero following keeps peer sum' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([4, 5, 6], [2.5, 2.5, 3.0], 'RANGE', 0.0, 0.0), 'sum'), [9, 9, 6]],
    'fractional range zero preceding keeps peer sum' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([4, 5, 6], [2.5, 2.5, 3.0], 'RANGE', 0.0, 0.0), 'count'), [2, 2, 1]],
    'fractional range wide following clamps at end' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.1, 1.2, 4.0], 'RANGE', 0.0, 99.5), 'frame'), [[0, 1, 2], [1, 2], [2]]],
    'fractional range wide preceding clamps at start' => [static fn (): mixed => array_column(SQLiteWindowFunction::aggregateFrameRows([1, 2, 3], [1.1, 1.2, 4.0], 'RANGE', 99.5, 0.0), 'frame'), [[0], [0, 1], [0, 1, 2]]],
    'fractional range rejects non integer rows preceding' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [1], 'ROWS', 0.5, 0);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite ROWS frame offsets must be integers'],
    'fractional range rejects non integer rows following' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [1], 'ROWS', 0, 0.5);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite ROWS frame offsets must be integers'],
    'fractional range rejects non integer groups preceding' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [1], 'GROUPS', 0.5, 0);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite GROUPS frame offsets must be integers'],
    'fractional range rejects non integer groups following' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [1], 'GROUPS', 0, 0.5);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite GROUPS frame offsets must be integers'],
    'fractional range still rejects negative preceding' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [1], 'RANGE', -0.5, 0);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite window frame offsets must be non-negative'],
    'fractional range still rejects negative following' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [1], 'RANGE', 0, -0.5);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite window frame offsets must be non-negative'],
    'fractional range still rejects text keys' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], ['1.5'], 'RANGE', 0.0, 0.5);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite RANGE frame offsets require numeric ORDER BY keys'],
    'fractional range still rejects blob keys' => [static fn (): mixed => (static function (): string {
        try {
            SQLiteWindowFunction::aggregateFrameRows([1], [new PortLibs\LibSqlite\SQLiteBlobValue('aa')], 'RANGE', 0.0, 0.5);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite RANGE frame offsets require numeric ORDER BY keys'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream corpus window peer range current next16 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
