<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeAffinityCollationSorterSourcePlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_10', 'priority' => '10', 'site_id' => 1],
    ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'site_id' => 1],
    ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => null, 'site_id' => 1],
    ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => '1', 'site_id' => 1],
    ['option_id' => 5, 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'site_id' => 1],
    ['option_id' => 6, 'autoload' => 'YES', 'option_name' => 'plugin_02', 'priority' => '02', 'site_id' => 1],
    ['option_id' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2.0', 'site_id' => 2],
    ['option_id' => 8, 'autoload' => 'no', 'option_name' => 'Cache', 'priority' => '1', 'site_id' => 2],
];

$nextRows = [
    ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_10', 'priority' => '01', 'site_id' => 1],
    ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'site_id' => 1],
    ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => '1', 'site_id' => 1],
    ['option_id' => 5, 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'site_id' => 1],
    ['option_id' => 6, 'autoload' => 'YES', 'option_name' => 'plugin_02', 'priority' => '02', 'site_id' => 1],
    ['option_id' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2.0', 'site_id' => 2],
    ['option_id' => 8, 'autoload' => 'no', 'option_name' => 'Cache', 'priority' => '1', 'site_id' => 2],
    ['option_id' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'site_id' => 9],
];

$plan = static fn (): array => SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources(
    $currentRows,
    $nextRows,
    ['autoload', 'priority', 'option_name', 'site_id'],
    'option_id',
    'GCGD',
    ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'],
    [false, false, false, true],
    ['LAST', 'LAST', 'LAST', null],
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[$segment];
    }

    return $value;
};

$cases = [
    'current order' => ['currentOrder', [8, 4, 3, 6, 7, 2, 1, 5]],
    'next order' => ['nextOrder', [8, 4, 1, 6, 9, 7, 2, 5]],
    'inserted row' => ['inserted', [9]],
    'deleted row' => ['deleted', [3]],
    'changed flag' => ['changed', true],
    'first moved id' => ['moved.0.id', 1],
    'first moved from' => ['moved.0.from', 6],
    'first moved to' => ['moved.0.to', 2],
    'second moved id' => ['moved.1.id', 7],
    'second moved from' => ['moved.1.from', 4],
    'second moved to' => ['moved.1.to', 5],
    'stable tie ids' => ['stableTieIds', []],
    'dependency sorter yield' => ['dependencies.0', 'sqlite-vdbe-sorter-yield'],
    'dependency affinity' => ['dependencies.1', 'sqlite-affinity-comparison'],
    'dependency collation' => ['dependencies.2', 'sqlite-collation-sequence'],
    'current first id' => ['currentTrace.0.id', 8],
    'current first sequence' => ['currentTrace.0.sequence', 7],
    'current first record' => ['currentTrace.0.record', ['no', '1', 'Cache', 2]],
    'current second stable tie false' => ['currentTrace.1.stableTie', false],
    'current second deciding collation' => ['currentTrace.1.decidingCollation', 'RTRIM'],
    'current third null priority id' => ['currentTrace.2.id', 3],
    'current third deciding nulls' => ['currentTrace.2.decidingNulls', 'LAST'],
    'current fourth id' => ['currentTrace.3.id', 6],
    'current fourth deciding collation' => ['currentTrace.3.decidingCollation', 'NOCASE'],
    'current fifth id' => ['currentTrace.4.id', 7],
    'current fifth numeric comparison tie' => ['currentTrace.4.steps.0.result', 0],
    'current fifth option name decides' => ['currentTrace.4.decidingIndex', 2],
    'current sixth id' => ['currentTrace.5.id', 2],
    'current sixth site desc decides' => ['currentTrace.5.decidingIndex', 3],
    'current sixth descending true' => ['currentTrace.5.decidingDescending', true],
    'current seventh id' => ['currentTrace.6.id', 1],
    'current seventh priority decides' => ['currentTrace.6.decidingIndex', 1],
    'current eighth id' => ['currentTrace.7.id', 5],
    'current eighth null autoload last' => ['currentTrace.7.decidingNulls', 'LAST'],
    'next first id' => ['nextTrace.0.id', 8],
    'next second id' => ['nextTrace.1.id', 4],
    'next second stable tie false' => ['nextTrace.1.stableTie', false],
    'next third id' => ['nextTrace.2.id', 1],
    'next third autoload decides' => ['nextTrace.2.decidingIndex', 0],
    'next fourth id' => ['nextTrace.3.id', 6],
    'next fourth priority decides' => ['nextTrace.3.decidingIndex', 1],
    'next fifth id' => ['nextTrace.4.id', 9],
    'next fifth option name decides' => ['nextTrace.4.decidingIndex', 2],
    'next sixth id' => ['nextTrace.5.id', 7],
    'next sixth site desc decides' => ['nextTrace.5.decidingIndex', 3],
    'next seventh id' => ['nextTrace.6.id', 2],
    'next seventh site desc decides' => ['nextTrace.6.decidingIndex', 3],
    'next eighth id' => ['nextTrace.7.id', 5],
    'next eighth autoload null placement' => ['nextTrace.7.decidingNulls', 'LAST'],
    'next inserted row sequence' => ['nextTrace.4.sequence', 7],
    'next inserted record' => ['nextTrace.4.record', ['yes', '2', 'plugin_2', 9]],
    'current trace count' => ['currentTrace', 8, 'count'],
    'next trace count' => ['nextTrace', 8, 'count'],
];

foreach ($cases as $name => $case) {
    $tests['vdbe affinity collation sorter current source next108 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$path, $expected] = $case;
        $actual = $valueAt($plan(), $path);
        if (($case[2] ?? null) === 'count') {
            $actual = count($actual);
        }

        $t->same($expected, $actual);
    };
}

$tests['vdbe affinity collation sorter current source next108 no change keeps changed false'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources($currentRows, $currentRows, ['autoload', 'priority'], 'option_id', 'GC', ['NOCASE']);

    $t->same(false, $plan['changed']);
};

$tests['vdbe affinity collation sorter current source next108 no change has no inserted rows'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources($currentRows, $currentRows, ['autoload', 'priority'], 'option_id', 'GC', ['NOCASE']);

    $t->same([], $plan['inserted']);
};

$tests['vdbe affinity collation sorter current source next108 no change has no deleted rows'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources($currentRows, $currentRows, ['autoload', 'priority'], 'option_id', 'GC', ['NOCASE']);

    $t->same([], $plan['deleted']);
};

$tests['vdbe affinity collation sorter current source next108 blob priority sorts after numeric under none affinity'] = static function (TestRunner $t): void {
    $plan = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources(
        [
            ['option_id' => 1, 'priority' => '2'],
            ['option_id' => 2, 'priority' => new SQLiteBlobValue('1')],
        ],
        [
            ['option_id' => 1, 'priority' => '2'],
            ['option_id' => 2, 'priority' => new SQLiteBlobValue('1')],
        ],
        ['priority'],
        'option_id',
        'B'
    );

    $t->same([1, 2], $plan['currentOrder']);
};

$tests['vdbe affinity collation sorter current source next108 numeric affinity keeps blob storage before converted text numbers'] = static function (TestRunner $t): void {
    $plan = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources(
        [
            ['option_id' => 1, 'priority' => '10'],
            ['option_id' => 2, 'priority' => '2'],
            ['option_id' => 3, 'priority' => new SQLiteBlobValue('1')],
        ],
        [
            ['option_id' => 1, 'priority' => '10'],
            ['option_id' => 2, 'priority' => '2'],
            ['option_id' => 3, 'priority' => new SQLiteBlobValue('1')],
        ],
        ['priority'],
        'option_id',
        'C'
    );

    $t->same([3, 2, 1], $plan['currentOrder']);
};

$tests['vdbe affinity collation sorter current source next108 rejects missing row id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources([['k' => 1]], [], ['k'], 'option_id'));
};

$tests['vdbe affinity collation sorter current source next108 rejects empty row id column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources([], [], ['k'], ''));
};

$tests['vdbe affinity collation sorter current source next108 rejects missing sort column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources([['option_id' => 1]], [], ['k'], 'option_id'));
};

return $tests;
