<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan;

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
    'sortDescending' => [false, false, false, false],
    'sortNulls' => [null, 'LAST', 'LAST', null],
    'valueColumn' => 'bytes',
    'partitionColumns' => ['site'],
    'partitionAffinities' => ['NUMERIC'],
    'partitionCollations' => ['BINARY'],
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

$plan = static fn (array $extra = []): array => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    ['site', 'autoload', 'priority', 'name'],
    'rowid',
    array_replace($options, $extra)
);
$field = static fn (array $rows, string $name): array => array_column($rows, $name);
$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[$segment];
    }

    return $value;
};

$cases = [
    'status changed' => [static fn (): mixed => $plan()['status'], 'sorter-affinity-window-current-source-changed'],
    'current order applies sorter affinity before window' => [static fn (): mixed => $plan()['currentOrder'], [5, 4, 3, 2, 1, 6, 7, 8]],
    'next order applies mutated priority and insert' => [static fn (): mixed => $plan()['nextOrder'], [5, 1, 3, 2, 9, 6, 7, 8]],
    'deleted cache padded row' => [static fn (): mixed => $plan()['deleted'], [4]],
    'inserted plugin row' => [static fn (): mixed => $plan()['inserted'], [9]],
    'moved rows include priority mutation' => [static fn (): mixed => array_column($plan()['moved'], 'id'), [1]],
    'stable ties are tracked for inserted duplicate' => [static fn (): mixed => $plan()['stableTieIds'], [9]],
    'dependency records next sorter' => [static fn (): mixed => $plan()['dependencies'][0], 'sqlite-vdbe-sorter-affinity-current-source-next'],
    'dependency records window yield' => [static fn (): mixed => $plan()['dependencies'][1], 'sqlite-vdbe-window-current-source-yield'],
    'dependency closure names no new component' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component'), true],
    'non overlap names distinct window recalculation' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next-source window frame recalculation'), true],
    'current window rowids follow sorted source' => [static fn (): mixed => $field($plan()['currentWindow'], 'currentRowid'), [5, 4, 3, 2, 1, 6, 7, 8]],
    'next window rowids follow next sorted source' => [static fn (): mixed => $field($plan()['nextWindow'], 'currentRowid'), [5, 1, 3, 2, 9, 6, 7, 8]],
    'current raw frames use current sorted peers' => [static fn (): mixed => $field($plan()['currentWindow'], 'rawFrameRowids'), [[5, 4], [4, 3], [3, 2], [2, 1], [1], [6, 7], [7, 8], [8]]],
    'next raw frames include inserted peer' => [static fn (): mixed => $field($plan()['nextWindow'], 'rawFrameRowids'), [[5, 1], [1, 3], [3, 2, 9], [2, 9], [2, 9], [6, 7], [7, 8], [8]]],
    'current excludes current row from frames' => [static fn (): mixed => $field($plan()['currentWindow'], 'frameRowids'), [[4], [3], [2], [1], [], [7], [8], []]],
    'next excludes current row from frames' => [static fn (): mixed => $field($plan()['nextWindow'], 'frameRowids'), [[1], [3], [2, 9], [9], [2], [7], [8], []]],
    'current filtered rowids honor SQL truthiness' => [static fn (): mixed => $field($plan()['currentWindow'], 'filteredRowids'), [[4], [], [2], [1], [], [7], [8], []]],
    'next filtered rowids honor inserted true row' => [static fn (): mixed => $field($plan()['nextWindow'], 'filteredRowids'), [[1], [], [2, 9], [9], [2], [7], [8], []]],
    'current sums follow filtered current source' => [static fn (): mixed => $field($plan()['currentWindow'], 'sum'), [5, null, 20, 100, null, 25, 40, null]],
    'next sums follow filtered next source' => [static fn (): mixed => $field($plan()['nextWindow'], 'sum'), [100, null, 70, 50, 20, 25, 40, null]],
    'current group concat uses separator' => [static fn (): mixed => $field($plan()['currentWindow'], 'groupConcat'), ['5', null, '20', '100', null, '25', '40', null]],
    'next group concat uses separator' => [static fn (): mixed => $field($plan()['nextWindow'], 'groupConcat'), ['100', null, '20|50', '50', '20', '25', '40', null]],
    'next same peer marks inserted duplicate plugin row' => [static fn (): mixed => $field($plan()['nextWindow'], 'nextSamePeer'), [false, false, false, true, false, false, false, false]],
    'current same peer marks old plugin priority peer' => [static fn (): mixed => $field($plan()['currentWindow'], 'nextSamePeer'), [false, false, false, false, false, false, false, false]],
    'peer changes include cache frame change' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.0.id'), 5],
    'peer changes include row one priority frame change' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.1.id'), 1],
    'peer changes include row three frame change' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.2.id'), 3],
    'peer changes count' => [static fn (): mixed => count($plan()['peerChanges']), 5],
    'row one current order key before mutation' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.1.currentOrderKey'), ['yes', '10', 'Plugin_10']],
    'row one next order key after affinity mutation' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.1.nextOrderKey'), ['yes', '01', 'Plugin_10']],
    'row three current frame rowids' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.2.currentFrameRowids'), [2]],
    'row three next frame rowids' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.2.nextFrameRowids'), [2, 9]],
    'row two current frame rowids' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.3.currentFrameRowids'), [1]],
    'row two next frame rowids' => [static fn (): mixed => $valueAt($plan(), 'peerChanges.3.nextFrameRowids'), [9]],
    'sort columns are reported' => [static fn (): mixed => $plan()['sortColumns'], ['site', 'autoload', 'priority', 'name']],
    'partition columns are reported' => [static fn (): mixed => $plan()['partitionColumns'], ['site']],
    'order columns are reported' => [static fn (): mixed => $plan()['orderColumns'], ['autoload', 'priority', 'name']],
    'rows frame changes first current frame' => [static fn (): mixed => $field($plan(['frameUnit' => 'ROWS'])['currentWindow'], 'frameRowids')[0], [4]],
    'rows frame changes second current frame' => [static fn (): mixed => $field($plan(['frameUnit' => 'ROWS'])['currentWindow'], 'frameRowids')[1], [3]],
    'exclude group removes peer group' => [static fn (): mixed => $field($plan(['exclude' => 'GROUP'])['nextWindow'], 'frameRowids')[2], [2, 9]],
    'exclude ties keeps current identity in next peer' => [static fn (): mixed => $field($plan(['exclude' => 'TIES'])['nextWindow'], 'frameRowids')[2], [3, 2, 9]],
    'no filter includes false rows in sums' => [static fn (): mixed => $field($plan(['filterColumn' => null])['nextWindow'], 'sum')[2], 70],
    'nulls first moves network late before yes group' => [static fn (): mixed => $field($plan(['sortNulls' => [null, 'FIRST', 'LAST', null], 'orderNulls' => ['FIRST', 'LAST', null]])['nextWindow'], 'currentRowid'), [5, 1, 3, 2, 9, 8, 6, 7]],
    'descending site starts with network rows' => [static fn (): mixed => $field($plan(['sortDescending' => [true, false, false, false]])['nextWindow'], 'currentRowid'), [1, 3, 2, 9, 5, 6, 7, 8]],
    'empty sources remain stable' => [static fn (): mixed => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare([], [], ['site'], 'rowid')['status'], 'sorter-affinity-window-current-source-stable'],
    'empty sources have no current window' => [static fn (): mixed => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare([], [], ['site'], 'rowid')['currentWindow'], []],
    'empty sources have no next window' => [static fn (): mixed => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare([], [], ['site'], 'rowid')['nextWindow'], []],
    'identical sources have no inserted rows' => [static fn (): mixed => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare($currentRows, $currentRows, ['site'], 'rowid', ['valueColumn' => 'bytes'])['inserted'], []],
    'identical sources have no deleted rows' => [static fn (): mixed => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare($currentRows, $currentRows, ['site'], 'rowid', ['valueColumn' => 'bytes'])['deleted'], []],
    'identical sources with same window are stable' => [static fn (): mixed => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare($currentRows, $currentRows, ['site'], 'rowid', ['valueColumn' => 'bytes'])['status'], 'sorter-affinity-window-current-source-stable'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe sorter affinity window current source next ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects missing row id' => static fn () => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare([['site' => 1]], [], ['site'], 'rowid'),
    'rejects empty row id' => static fn () => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare([], [], ['site'], ''),
    'rejects empty sort columns' => static fn () => SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare([], [], [], 'rowid'),
    'rejects bad sort collation list' => static fn () => $plan(['sortCollations' => 'NOCASE']),
    'rejects bad sort descending list' => static fn () => $plan(['sortDescending' => [1]]),
    'rejects bad sort nulls list' => static fn () => $plan(['sortNulls' => 'LAST']),
    'rejects bad partition affinities' => static fn () => $plan(['partitionAffinities' => 1]),
    'rejects bad order affinities' => static fn () => $plan(['orderAffinities' => 1]),
    'rejects missing value column' => static fn () => $plan(['valueColumn' => 'missing']),
    'rejects nonnumeric following' => static fn () => $plan(['following' => '1']),
];

foreach ($throws as $name => $callback) {
    $tests['vdbe sorter affinity window current source next ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
