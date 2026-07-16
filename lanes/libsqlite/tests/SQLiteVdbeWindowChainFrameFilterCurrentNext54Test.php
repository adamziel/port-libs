<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;
use PortLibs\LibSqlite\SQLiteVdbeWindowChainFramePlan;

$tests = [];

$rows = [
    ['rowid' => 1, 'site' => 1, 'name' => 'alpha', 'bytes' => 10, 'ok' => 1, 'hot' => 1],
    ['rowid' => 2, 'site' => 1, 'name' => 'alpha', 'bytes' => 10, 'ok' => 0, 'hot' => 1],
    ['rowid' => 3, 'site' => 1, 'name' => 'beta', 'bytes' => 15, 'ok' => 1, 'hot' => 0],
    ['rowid' => 4, 'site' => 1, 'name' => 'cron', 'bytes' => 20, 'ok' => 1, 'hot' => 1],
    ['rowid' => 5, 'site' => 1, 'name' => 'theme', 'bytes' => 25, 'ok' => 0, 'hot' => 1],
    ['rowid' => 6, 'site' => 1, 'name' => 'theme', 'bytes' => 25, 'ok' => 1, 'hot' => 0],
    ['rowid' => 7, 'site' => 2, 'name' => 'alpha', 'bytes' => 11, 'ok' => 1, 'hot' => 1],
    ['rowid' => 8, 'site' => 2, 'name' => 'beta', 'bytes' => 16, 'ok' => 0, 'hot' => 1],
    ['rowid' => 9, 'site' => 2, 'name' => 'cron', 'bytes' => 21, 'ok' => 1, 'hot' => 0],
    ['rowid' => 10, 'site' => 2, 'name' => 'omega', 'bytes' => 26, 'ok' => 1, 'hot' => 1],
];

$cursor = static fn (string $filter, int $preceding, int $following, string $unit, string $exclude): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bytes', 'name'],
    $filter,
    $preceding,
    $following,
    ['INTEGER'],
    [],
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'NOCASE'],
    [false, false],
    [],
    $unit,
    $exclude,
);

$chain = static fn (): SQLiteVdbeWindowChainFramePlan => new SQLiteVdbeWindowChainFramePlan([
    'rows' => $cursor('ok', 1, 1, 'ROWS', 'NO OTHERS'),
    'groups' => $cursor('hot', 0, 1, 'GROUPS', 'CURRENT ROW'),
    'ties' => $cursor('ok', 0, 1, 'GROUPS', 'TIES'),
]);

$drain = static fn (): array => $chain()->drain();
$column = static fn (string $window, string $field): array => array_map(
    static fn (array $row): mixed => $row[$window][$field],
    $drain()
);
$summaryColumn = static fn (string $window, string $side, string $field): array => array_map(
    static fn (array $row): mixed => $row[$window][$side][$field] ?? null,
    $drain()
);

$cases = [
    'rows window current filtered frame rowids' => [static fn (): mixed => $column('rows', 'frameRowids'), [[1], [1, 3], [3, 4], [3, 4], [4, 6], [6], [7], [7, 9], [9, 10], [9, 10]]],
    'rows window next filtered frame rowids' => [static fn (): mixed => $column('rows', 'nextFrameRowids'), [[1, 3], [3, 4], [3, 4], [4, 6], [6], [7], [7, 9], [9, 10], [9, 10], null]],
    'rows window totals follow filtered ROWS frame' => [static fn (): mixed => $column('rows', 'total'), [10.0, 25.0, 35.0, 35.0, 45.0, 25.0, 11.0, 32.0, 47.0, 47.0]],
    'rows window group concat follows filtered ROWS frame' => [static fn (): mixed => $column('rows', 'groupConcat'), ['10', '10|15', '15|20', '15|20', '20|25', '25', '11', '11|21', '21|26', '21|26']],
    'rows window first values follow filtered ROWS frame' => [static fn (): mixed => $column('rows', 'firstValue'), [10, 10, 15, 15, 20, 25, 11, 11, 21, 21]],
    'rows window last values follow filtered ROWS frame' => [static fn (): mixed => $column('rows', 'lastValue'), [10, 15, 20, 20, 25, 25, 11, 21, 26, 26]],
    'rows window nth values follow filtered ROWS frame' => [static fn (): mixed => $column('rows', 'nthValue'), [null, 15, 20, 20, 25, null, null, 21, 26, 26]],
    'rows current summaries report filtered row counts' => [static fn (): mixed => $summaryColumn('rows', 'current', 'filteredRows'), [1, 2, 2, 2, 2, 1, 1, 2, 2, 2]],
    'rows next summaries report filtered row counts' => [static fn (): mixed => $summaryColumn('rows', 'next', 'filteredRows'), [2, 2, 2, 2, 1, 1, 2, 2, 2, null]],
    'rows current summaries keep order keys' => [static fn (): mixed => $summaryColumn('rows', 'current', 'orderKey'), [[10, 'alpha'], [10, 'alpha'], [15, 'beta'], [20, 'cron'], [25, 'theme'], [25, 'theme'], [11, 'alpha'], [16, 'beta'], [21, 'cron'], [26, 'omega']]],
    'groups current filtered frame rowids exclude current row' => [static fn (): mixed => $column('groups', 'frameRowids'), [[2], [1], [4], [5], [], [5], [8], [], [10], []]],
    'groups next filtered frame rowids are peeks' => [static fn (): mixed => $column('groups', 'nextFrameRowids'), [[1], [4], [5], [], [5], [8], [], [10], [], null]],
    'groups totals use hot filter independently' => [static fn (): mixed => $column('groups', 'total'), [10.0, 10.0, 20.0, 25.0, 0.0, 25.0, 16.0, 0.0, 26.0, 0.0]],
    'groups group concat reports empty frames as null' => [static fn (): mixed => $column('groups', 'groupConcat'), ['10', '10', '20', '25', null, '25', '16', null, '26', null]],
    'groups first values report null for filtered empty frame' => [static fn (): mixed => $column('groups', 'firstValue'), [10, 10, 20, 25, null, 25, 16, null, 26, null]],
    'groups nth values remain null for single-row frames' => [static fn (): mixed => $column('groups', 'nthValue'), [null, null, null, null, null, null, null, null, null, null]],
    'groups current summaries count filtered rows after exclude' => [static fn (): mixed => $summaryColumn('groups', 'current', 'filteredRows'), [1, 1, 1, 1, 0, 1, 1, 0, 1, 0]],
    'groups current filter pass bits use hot column' => [static fn (): mixed => $summaryColumn('groups', 'current', 'currentFilterPassed'), [true, true, false, true, true, false, true, true, false, true]],
    'ties current filtered frame rowids preserve current peer identity' => [static fn (): mixed => $column('ties', 'frameRowids'), [[1, 3], [3], [3, 4], [4, 6], [], [6], [7], [9], [9, 10], [10]]],
    'ties next filtered frame rowids are current-next stable' => [static fn (): mixed => $column('ties', 'nextFrameRowids'), [[3], [3, 4], [4, 6], [], [6], [7], [9], [9, 10], [10], null]],
    'ties totals use ok filter independently' => [static fn (): mixed => $column('ties', 'total'), [25.0, 15.0, 35.0, 45.0, 0.0, 25.0, 11.0, 21.0, 47.0, 26.0]],
    'ties group concat omits false peer ties' => [static fn (): mixed => $column('ties', 'groupConcat'), ['10|15', '15', '15|20', '20|25', null, '25', '11', '21', '21|26', '26']],
    'ties first values keep current row when ties excluded' => [static fn (): mixed => $column('ties', 'firstValue'), [10, 15, 15, 20, null, 25, 11, 21, 21, 26]],
    'ties last values keep following group contributors' => [static fn (): mixed => $column('ties', 'lastValue'), [15, 15, 20, 25, null, 25, 11, 21, 26, 26]],
    'ties nth values track second filtered contributor' => [static fn (): mixed => $column('ties', 'nthValue'), [15, null, 20, 25, null, null, null, null, 26, null]],
    'ties current summaries report raw frame starts after exclude before filter' => [static fn (): mixed => $summaryColumn('ties', 'current', 'frameStart'), [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'ties next summaries report raw frame starts without advancing' => [static fn (): mixed => $summaryColumn('ties', 'next', 'frameStart'), [1, 2, 3, 4, 5, 6, 7, 8, 9, null]],
    'all chained windows report non advancing peeks' => [static fn (): mixed => array_map(static fn (array $row): array => [$row['rows']['advanced'], $row['groups']['advanced'], $row['ties']['advanced']], $drain()), array_fill(0, 10, [false, false, false])],
    'current positions stay aligned across chained windows' => [static fn (): mixed => array_map(static fn (array $row): array => [$row['rows']['current']['position'], $row['groups']['current']['position'], $row['ties']['current']['position']], $drain()), [[0, 0, 0], [1, 1, 1], [2, 2, 2], [3, 3, 3], [4, 4, 4], [5, 5, 5], [6, 6, 6], [7, 7, 7], [8, 8, 8], [9, 9, 9]]],
    'next positions stay aligned across chained windows' => [static fn (): mixed => array_map(static fn (array $row): array => [$row['rows']['next']['position'] ?? null, $row['groups']['next']['position'] ?? null, $row['ties']['next']['position'] ?? null], $drain()), [[1, 1, 1], [2, 2, 2], [3, 3, 3], [4, 4, 4], [5, 5, 5], [6, 6, 6], [7, 7, 7], [8, 8, 8], [9, 9, 9], [null, null, null]]],
    'partition keys stay aligned across chained windows' => [static fn (): mixed => array_map(static fn (array $row): array => [$row['rows']['current']['partitionKey'], $row['groups']['current']['partitionKey'], $row['ties']['current']['partitionKey']], $drain()), array_merge(array_fill(0, 6, [[1], [1], [1]]), array_fill(0, 4, [[2], [2], [2]]))],
    'rows frame starts clamp to partition boundaries' => [static fn (): mixed => $summaryColumn('rows', 'current', 'frameStart'), [0, 0, 1, 2, 3, 4, 6, 6, 7, 8]],
    'rows frame ends clamp to partition boundaries' => [static fn (): mixed => $summaryColumn('rows', 'current', 'frameEnd'), [1, 2, 3, 4, 5, 5, 7, 8, 9, 9]],
    'groups current summary frame rows are raw rows after exclude' => [static fn (): mixed => $summaryColumn('groups', 'current', 'frameRows'), [2, 2, 1, 2, 1, 1, 1, 1, 1, 0]],
    'ties current summary frame rows keep current peer identity' => [static fn (): mixed => $summaryColumn('ties', 'current', 'frameRows'), [2, 2, 2, 3, 1, 1, 2, 2, 2, 1]],
    'next order keys stay visible for chained lookahead' => [static fn (): mixed => $summaryColumn('rows', 'next', 'orderKey'), [[10, 'alpha'], [15, 'beta'], [20, 'cron'], [25, 'theme'], [25, 'theme'], [11, 'alpha'], [16, 'beta'], [21, 'cron'], [26, 'omega'], null]],
    'next partition keys cross only at partition edge' => [static fn (): mixed => $summaryColumn('rows', 'current', 'nextPartitionKey'), [[1], [1], [1], [1], [1], [2], [2], [2], [2], null]],
    'group totals and tie totals differ on peer filtered rows' => [static fn (): mixed => array_map(static fn (array $row): array => [$row['groups']['total'], $row['ties']['total']], $drain()), [[10.0, 25.0], [10.0, 15.0], [20.0, 35.0], [25.0, 45.0], [0.0, 0.0], [25.0, 25.0], [16.0, 11.0], [0.0, 21.0], [26.0, 47.0], [0.0, 26.0]]],
    'row current and next frame rowids are independent arrays' => [static fn (): mixed => (static function () use ($chain): array {
        $pair = $chain()->currentNext();

        return [$pair['rows']['frameRowids'], $pair['rows']['nextFrameRowids'], $pair['groups']['frameRowids'], $pair['groups']['nextFrameRowids']];
    })(), [[1], [1, 3], [2], [1]]],
    'custom nth value returns null when third contributor is absent' => [static fn (): mixed => (static function () use ($chain): array {
        $pair = $chain()->currentNext('rowid', '|', 3);

        return [$pair['rows']['nthValue'], $pair['ties']['nthValue']];
    })(), [null, null]],
    'custom nth value can read first contributor' => [static fn (): mixed => (static function () use ($chain): array {
        $pair = $chain()->currentNext('rowid', '|', 1);

        return [$pair['rows']['nthValue'], $pair['ties']['nthValue'], $pair['groups']['nthValue']];
    })(), [10, 10, 10]],
    'custom separator applies to chained group concat' => [static fn (): mixed => (static function () use ($chain): array {
        $pair = $chain()->currentNext('rowid', ',');

        return [$pair['rows']['groupConcat'], $pair['ties']['groupConcat']];
    })(), ['10', '10,15']],
    'rowid column can be customized for frame ids' => [static fn (): mixed => (static function () use ($rows): array {
        $withNames = array_map(static function (array $row): array {
            $row['rid'] = 'r' . $row['rowid'];

            return $row;
        }, $rows);
        $cursor = new SQLiteVdbeWindowAggregateCursor($withNames, 'bytes', ['site'], ['bytes', 'name'], 'ok', 1, 1, ['INTEGER'], [], ['NUMERIC', 'TEXT']);
        $pair = (new SQLiteVdbeWindowChainFramePlan(['rows' => $cursor]))->currentNext('rid');

        return [$pair['rows']['frameRowids'], $pair['rows']['nextFrameRowids']];
    })(), [['r1'], ['r1', 'r3']]],
    'rewind restores first chained frame after drain' => [static fn (): mixed => (static function () use ($chain): array {
        $plan = $chain();
        $plan->drain();
        $plan->rewind();

        return $plan->currentNext()['rows']['frameRowids'];
    })(), [1]],
    'manual next advances all chained windows together' => [static fn (): mixed => (static function () use ($chain): array {
        $plan = $chain();
        $plan->next();
        $plan->next();
        $pair = $plan->currentNext();

        return [$pair['rows']['current']['position'], $pair['groups']['frameRowids'], $pair['ties']['groupConcat']];
    })(), [2, [4], '15|20']],
    'chain reports eof only after all windows drain' => [static fn (): mixed => (static function () use ($chain): array {
        $plan = $chain();
        $seen = [];
        while (!$plan->eof()) {
            $seen[] = $plan->currentNext()['rows']['current']['position'];
            $plan->next();
        }

        return [$seen, $plan->eof()];
    })(), [[0, 1, 2, 3, 4, 5, 6, 7, 8, 9], true]],
    'constructor rejects empty chain' => [static fn (): mixed => (static function (): string {
        try {
            new SQLiteVdbeWindowChainFramePlan([]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window chain requires at least one window cursor'],
    'constructor rejects blank window names' => [static fn (): mixed => (static function () use ($cursor): string {
        try {
            new SQLiteVdbeWindowChainFramePlan(['' => $cursor('ok', 1, 1, 'ROWS', 'NO OTHERS')]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window chain names must be non-empty strings'],
    'constructor rejects non cursor entries' => [static fn (): mixed => (static function (): string {
        try {
            new SQLiteVdbeWindowChainFramePlan(['bad' => new stdClass()]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window chain entries must be window aggregate cursors'],
    'current next rejects non positive nth value' => [static fn (): mixed => (static function () use ($chain): string {
        try {
            $chain()->currentNext('rowid', '|', 0);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    })(), 'SQLite VDBE window chain nth value index must be positive'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe window chain frame filter current next54 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
