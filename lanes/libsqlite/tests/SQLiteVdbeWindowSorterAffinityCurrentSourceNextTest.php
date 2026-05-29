<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['rowid' => 1, 'site' => '1', 'autoload' => 'yes', 'name' => 'Plugin_10', 'priority' => '10', 'bytes' => 100, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'autoload' => 'YES', 'name' => 'plugin_2', 'priority' => '2', 'bytes' => 20, 'include' => '1'],
    ['rowid' => 3, 'site' => 1, 'autoload' => 'yes', 'name' => 'plugin_02', 'priority' => '02', 'bytes' => 30, 'include' => 0],
    ['rowid' => 4, 'site' => 1, 'autoload' => 'no', 'name' => 'cache ', 'priority' => null, 'bytes' => 5, 'include' => true],
    ['rowid' => 5, 'site' => 1, 'autoload' => 'no', 'name' => 'cache', 'priority' => '1', 'bytes' => 7, 'include' => 1],
    ['rowid' => 6, 'site' => 2, 'autoload' => 'yes', 'name' => 'network_a', 'priority' => new SQLiteBlobValue('2'), 'bytes' => 15, 'include' => 1],
    ['rowid' => 7, 'site' => 2, 'autoload' => 'YES', 'name' => 'network_b', 'priority' => '2.0', 'bytes' => 25, 'include' => -1],
    ['rowid' => 8, 'site' => 2, 'autoload' => null, 'name' => 'network_late', 'priority' => '1', 'bytes' => 40, 'include' => 1],
];

$nextRows = [
    ['rowid' => 1, 'site' => '1', 'autoload' => 'yes', 'name' => 'Plugin_10', 'priority' => '01', 'bytes' => 100, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'autoload' => 'YES', 'name' => 'plugin_2', 'priority' => '2', 'bytes' => 20, 'include' => '1'],
    ['rowid' => 3, 'site' => 1, 'autoload' => 'yes', 'name' => 'plugin_02', 'priority' => '02', 'bytes' => 30, 'include' => 0],
    ['rowid' => 5, 'site' => 1, 'autoload' => 'no', 'name' => 'cache', 'priority' => '1', 'bytes' => 7, 'include' => 1],
    ['rowid' => 6, 'site' => 2, 'autoload' => 'yes', 'name' => 'network_a', 'priority' => new SQLiteBlobValue('2'), 'bytes' => 15, 'include' => 1],
    ['rowid' => 7, 'site' => 2, 'autoload' => 'YES', 'name' => 'network_b', 'priority' => '2.0', 'bytes' => 25, 'include' => -1],
    ['rowid' => 8, 'site' => 2, 'autoload' => null, 'name' => 'network_late', 'priority' => '1', 'bytes' => 40, 'include' => 1],
    ['rowid' => 9, 'site' => 1, 'autoload' => 'yes', 'name' => 'plugin_2', 'priority' => '2', 'bytes' => 50, 'include' => 1],
];

$options = [
    'sortAffinities' => ['NUMERIC', 'TEXT', 'NUMERIC', 'TEXT'],
    'sortCollations' => ['BINARY', 'NOCASE', 'BINARY', 'RTRIM'],
    'sortNulls' => [null, 'LAST', 'LAST', null],
    'valueColumn' => 'bytes',
    'partitionColumns' => ['site'],
    'partitionAffinities' => ['NUMERIC'],
    'orderColumns' => ['autoload', 'priority', 'name'],
    'orderAffinities' => ['TEXT', 'NUMERIC', 'TEXT'],
    'orderCollations' => ['NOCASE', 'BINARY', 'RTRIM'],
    'orderNulls' => ['LAST', 'LAST', null],
    'filterColumn' => 'include',
    'preceding' => 0,
    'following' => 1,
    'frameUnit' => 'GROUPS',
    'exclude' => 'CURRENT ROW',
    'separator' => '|',
];

$plan = static fn (array $extra = []): array => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    ['site', 'autoload', 'priority', 'name'],
    'rowid',
    array_replace($options, $extra)
);
$field = static fn (array $rows, string $name): array => array_column($rows, $name);
$path = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[$segment];
    }

    return $value;
};

$cases = [
    'status changed' => [static fn (): mixed => $plan()['status'], 'window-sorter-affinity-current-source-next-changed'],
    'dependency names next' => [static fn (): mixed => $plan()['dependencies'][0], 'sqlite-vdbe-window-sorter-affinity-current-source-next'],
    'dependency names sorter current next yield' => [static fn (): mixed => $plan()['dependencies'][2], 'sqlite-vdbe-sorter-current-next-yield'],
    'dependency closure reuses primitives' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component'), true],
    'non overlap names sorter data next loop' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'OP_SorterData/OP_SorterNext'), true],
    'current loop rowids follow sorted order' => [static fn (): mixed => $field($plan()['currentLoop'], 'currentRowid'), [5, 4, 3, 2, 1, 6, 7, 8]],
    'next loop rowids follow changed sorted order' => [static fn (): mixed => $field($plan()['nextLoop'], 'currentRowid'), [5, 1, 3, 2, 9, 6, 7, 8]],
    'current loop next rowids snapshot before advancing' => [static fn (): mixed => $field($plan()['currentLoop'], 'nextRowid'), [4, 3, 2, 1, 6, 7, 8, null]],
    'next loop next rowids snapshot before advancing' => [static fn (): mixed => $field($plan()['nextLoop'], 'nextRowid'), [1, 3, 2, 9, 6, 7, 8, null]],
    'current loop frame rowids match window before sorter next' => [static fn (): mixed => $field($plan()['currentLoop'], 'frameRowids'), [[4], [3], [1], [5], [], [8], [6], []]],
    'next loop frame rowids match inserted peer before sorter next' => [static fn (): mixed => $field($plan()['nextLoop'], 'frameRowids'), [[1], [3], [9], [5], [], [8], [6], []]],
    'current loop raw frame rowids' => [static fn (): mixed => $field($plan()['currentLoop'], 'rawFrameRowids'), [[5, 4], [4, 3], [3, 1], [2, 5], [1], [6, 8], [7, 6], [8]]],
    'next loop raw frame rowids include same peer duplicate' => [static fn (): mixed => $field($plan()['nextLoop'], 'rawFrameRowids'), [[5, 1], [1, 3], [3, 9], [2, 5], [9], [6, 8], [7, 6], [8]]],
    'current loop filtered rowids' => [static fn (): mixed => $field($plan()['currentLoop'], 'filteredRowids'), [[4], [], [1], [5], [], [8], [6], []]],
    'next loop filtered rowids' => [static fn (): mixed => $field($plan()['nextLoop'], 'filteredRowids'), [[1], [], [9], [5], [], [8], [6], []]],
    'current loop sums' => [static fn (): mixed => $field($plan()['currentLoop'], 'sum'), [5, null, 100, 7, null, 40, 15, null]],
    'next loop sums' => [static fn (): mixed => $field($plan()['nextLoop'], 'sum'), [100, null, 50, 7, null, 40, 15, null]],
    'current loop group concat' => [static fn (): mixed => $field($plan()['currentLoop'], 'groupConcat'), ['5', null, '100', '7', null, '40', '15', null]],
    'next loop group concat' => [static fn (): mixed => $field($plan()['nextLoop'], 'groupConcat'), ['100', null, '50', '7', null, '40', '15', null]],
    'current loop stable ties' => [static fn (): mixed => $field($plan()['currentLoop'], 'stableTieFromPrevious'), [false, false, false, false, false, false, false, false]],
    'next loop stable ties include inserted duplicate' => [static fn (): mixed => $field($plan()['nextLoop'], 'stableTieFromPrevious'), [false, false, false, false, true, false, false, false]],
    'current loop next same peer all false' => [static fn (): mixed => $field($plan()['currentLoop'], 'nextSamePeer'), [false, false, false, false, false, false, false, false]],
    'next loop marks row two same peer before advance' => [static fn (): mixed => $field($plan()['nextLoop'], 'nextSamePeer'), [false, false, false, false, false, false, false, false]],
    'current loop records preserve affinity inputs' => [static fn (): mixed => $field($plan()['currentLoop'], 'record')[0], [1, 'no', '1', 'cache']],
    'next loop records preserve changed priority text' => [static fn (): mixed => $field($plan()['nextLoop'], 'record')[1], ['1', 'yes', '01', 'Plugin_10']],
    'next loop next record points at row three' => [static fn (): mixed => $path($plan(), 'nextLoop.1.nextRecord'), [1, 'yes', '02', 'plugin_02']],
    'first current loop sequence is original row five' => [static fn (): mixed => $path($plan(), 'currentLoop.0.sequence'), 4],
    'first next loop sequence is original next row five' => [static fn (): mixed => $path($plan(), 'nextLoop.0.sequence'), 3],
    'inserted row keeps original next sequence' => [static fn (): mixed => $path($plan(), 'nextLoop.4.sequence'), 7],
    'loop changes include row five next pointer' => [static fn (): mixed => $path($plan(), 'loopChanges.0.id'), 5],
    'loop changes row five next rowid changed' => [static fn (): mixed => [$path($plan(), 'loopChanges.0.currentNextRowid'), $path($plan(), 'loopChanges.0.nextNextRowid')], [4, 1]],
    'loop changes row one group changed' => [static fn (): mixed => $path($plan(), 'loopChanges.1.nextGroupConcat'), null],
    'loop changes row three inserted peer concat' => [static fn (): mixed => $path($plan(), 'loopChanges.2.nextGroupConcat'), '50'],
    'loop changes row two next peer points inserted row' => [static fn (): mixed => $path($plan(), 'loopChanges.3.nextNextRowid'), 9],
    'loop changes count' => [static fn (): mixed => count($plan()['loopChanges']), 4],
    'base inserted rows remain reported' => [static fn (): mixed => $plan()['inserted'], [9]],
    'base deleted rows remain reported' => [static fn (): mixed => $plan()['deleted'], [4]],
    'base moved rows remain reported' => [static fn (): mixed => array_column($plan()['moved'], 'id'), [1]],
    'base current order remains reported' => [static fn (): mixed => $plan()['currentOrder'], [5, 4, 3, 2, 1, 6, 7, 8]],
    'base next order remains reported' => [static fn (): mixed => $plan()['nextOrder'], [5, 1, 3, 2, 9, 6, 7, 8]],
    'no filter loop includes false row value' => [static fn (): mixed => $field($plan(['filterColumn' => null])['nextLoop'], 'groupConcat')[2], '50'],
    'rows frame keeps loop order' => [static fn (): mixed => $field($plan(['frameUnit' => 'ROWS'])['nextLoop'], 'frameRowids')[2], [9]],
    'exclude group loop removes current peer group' => [static fn (): mixed => $field($plan(['exclude' => 'GROUP'])['nextLoop'], 'frameRowids')[2], [9]],
    'exclude ties loop keeps current row and following peers' => [static fn (): mixed => $field($plan(['exclude' => 'TIES'])['nextLoop'], 'frameRowids')[2], [3, 9]],
    'nulls first changes network loop order' => [static fn (): mixed => $field($plan(['sortNulls' => [null, 'FIRST', 'LAST', null], 'orderNulls' => ['FIRST', 'LAST', null]])['nextLoop'], 'currentRowid'), [5, 1, 3, 2, 9, 8, 6, 7]],
    'empty sources stable' => [static fn (): mixed => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare([], [], ['site'], 'rowid')['status'], 'window-sorter-affinity-current-source-next-stable'],
    'empty current loop' => [static fn (): mixed => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare([], [], ['site'], 'rowid')['currentLoop'], []],
    'empty next loop' => [static fn (): mixed => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare([], [], ['site'], 'rowid')['nextLoop'], []],
    'identical sources stable' => [static fn (): mixed => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare($currentRows, $currentRows, ['site'], 'rowid', ['valueColumn' => 'bytes'])['status'], 'window-sorter-affinity-current-source-next-stable'],
    'identical sources no loop changes' => [static fn (): mixed => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare($currentRows, $currentRows, ['site'], 'rowid', ['valueColumn' => 'bytes'])['loopChanges'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe window sorter affinity current source next ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects missing row id' => static fn () => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare([['site' => 1]], [], ['site'], 'rowid'),
    'rejects empty row id' => static fn () => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare([], [], ['site'], ''),
    'rejects empty sort columns' => static fn () => SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare([], [], [], 'rowid'),
    'rejects bad sort affinity type' => static fn () => $plan(['sortAffinities' => 1]),
    'rejects bad sort collation list' => static fn () => $plan(['sortCollations' => 'NOCASE']),
    'rejects bad sort descending list' => static fn () => $plan(['sortDescending' => [1]]),
    'rejects bad sort nulls list' => static fn () => $plan(['sortNulls' => 'LAST']),
    'rejects missing value column' => static fn () => $plan(['valueColumn' => 'missing']),
];

foreach ($throws as $name => $callback) {
    $tests['vdbe window sorter affinity current source next ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
