<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'site' => '1', 'autoload' => 'yes', 'option_name' => 'Plugin_A ', 'priority' => '02', 'bytes' => 20, 'enabled' => 1],
    ['option_id' => 2, 'site' => 1, 'autoload' => 'YES', 'option_name' => 'plugin_a', 'priority' => 2, 'bytes' => 10, 'enabled' => '1'],
    ['option_id' => 3, 'site' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_b', 'priority' => '10', 'bytes' => 30, 'enabled' => 0],
    ['option_id' => 4, 'site' => 1, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => null, 'bytes' => 5, 'enabled' => 1],
    ['option_id' => 5, 'site' => '01', 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'bytes' => 40, 'enabled' => 1],
    ['option_id' => 6, 'site' => 2, 'autoload' => 'yes', 'option_name' => 'network', 'priority' => '1', 'bytes' => 12, 'enabled' => 1],
    ['option_id' => 7, 'site' => 2, 'autoload' => 'YES', 'option_name' => 'network ', 'priority' => 1, 'bytes' => null, 'enabled' => 1],
    ['option_id' => 8, 'site' => 2, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => '4', 'bytes' => 8, 'enabled' => '0'],
];

$nextRows = $currentRows;
$nextRows[0]['priority'] = '20';
unset($nextRows[2]);
$nextRows = array_values($nextRows);
$nextRows[] = ['option_id' => 9, 'site' => 2, 'autoload' => 'yes', 'option_name' => 'network', 'priority' => '1.0', 'bytes' => 18, 'enabled' => 1];

$plan = static fn (): array => SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan::compareWindowSources(
    $currentRows,
    $nextRows,
    'option_id',
    'bytes',
    ['site'],
    ['autoload', 'priority', 'option_name'],
    'enabled',
    1,
    1,
    'C',
    ['BINARY'],
    'GCG',
    ['NOCASE', 'BINARY', 'RTRIM'],
    [false, false, false],
    ['LAST', 'LAST', 'LAST']
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[$segment];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'vdbe-affinity-sorter-window-current-source-next-ready'],
    'source token' => ['sourceToken', 'vdbe-affinity-sorter-window-current-source-next'],
    'current source row count' => ['currentSource.rowCount', 8],
    'current source window count' => ['currentSource.windowCount', 8],
    'next source row count' => ['nextSource.rowCount', 8],
    'next source window count' => ['nextSource.windowCount', 8],
    'inserted option id' => ['inserted', [9]],
    'deleted option id' => ['deleted', [3]],
    'changed flag' => ['changed', true],
    'current sorted order' => ['currentOrder', [4, 1, 2, 3, 5, 8, 6, 7]],
    'next sorted order' => ['nextOrder', [4, 2, 1, 5, 8, 6, 7, 9]],
    'first moved id' => ['moved.0.id', 2],
    'first moved from' => ['moved.0.from', 2],
    'first moved to' => ['moved.0.to', 1],
    'last moved id' => ['moved.5.id', 7],
    'last moved from' => ['moved.5.from', 7],
    'last moved to' => ['moved.5.to', 6],
    'dependency sorter' => ['dependencies.0', 'sqlite-vdbe-sorter-yield'],
    'dependency window' => ['dependencies.1', 'sqlite-vdbe-window-current-next'],
    'dependency affinity' => ['dependencies.2', 'sqlite-affinity-comparison'],
    'dependency collation' => ['dependencies.3', 'sqlite-collation-sequence'],
    'current first rowid' => ['currentWindows.0.rowid', 4],
    'current first next rowid' => ['currentWindows.0.nextRowid', 1],
    'current first frame rowids' => ['currentWindows.0.frameRowids', [4, 1]],
    'current first group concat' => ['currentWindows.0.groupConcat', '5|20'],
    'current first sum' => ['currentWindows.0.sum', 25],
    'current second partition key keeps raw source' => ['currentWindows.1.partitionKey', ['1']],
    'current second next order key' => ['currentWindows.1.nextOrderKey', ['YES', 2, 'plugin_a']],
    'current second frame rowids' => ['currentWindows.1.frameRowids', [4, 1, 2]],
    'current second sum' => ['currentWindows.1.sum', 35],
    'current third filtered frame skips deleted false row later' => ['currentWindows.2.filteredFrameRowids', [1, 2]],
    'current third count value' => ['currentWindows.2.countValue', 2],
    'current third group concat' => ['currentWindows.2.groupConcat', '20|10'],
    'current false row filter bit' => ['currentWindows.3.currentFilterPassed', false],
    'current false row filtered frame' => ['currentWindows.3.filteredFrameRowids', [2, 5]],
    'current false row sum' => ['currentWindows.3.sum', 50],
    'current false row first value ignores filter for value function' => ['currentWindows.3.firstValue', 10],
    'current network null autoload follows site one by numeric partition' => ['currentWindows.4.rowid', 5],
    'current network filtered frame only self' => ['currentWindows.4.filteredFrameRowids', [5]],
    'current string zero filter false' => ['currentWindows.5.currentFilterPassed', false],
    'current string zero frame sees next network row' => ['currentWindows.5.frameRowids', [8, 6]],
    'current string zero sum skips self' => ['currentWindows.5.sum', 12],
    'current nullable bytes count value' => ['currentWindows.6.countValue', 1],
    'current nullable bytes last value remains null' => ['currentWindows.6.lastValue', null],
    'current final next rowid null' => ['currentWindows.7.nextRowid', null],
    'current final group concat skips null bytes' => ['currentWindows.7.groupConcat', '12'],
    'next first frame reflects deleted row removal' => ['nextWindows.0.frameRowids', [4, 2]],
    'next first sum' => ['nextWindows.0.sum', 15],
    'next second rowid is option two' => ['nextWindows.1.rowid', 2],
    'next second group concat' => ['nextWindows.1.groupConcat', '5|10|20'],
    'next third priority changed order key' => ['nextWindows.2.orderKey', ['yes', '20', 'Plugin_A ']],
    'next third frame rowids' => ['nextWindows.2.frameRowids', [2, 1, 5]],
    'next third sum includes moved network row' => ['nextWindows.2.sum', 70],
    'next fourth frame starts at moved plugin row' => ['nextWindows.3.frameRowids', [1, 5]],
    'next fourth sum' => ['nextWindows.3.sum', 60],
    'next fifth false filter bit' => ['nextWindows.4.currentFilterPassed', false],
    'next seventh next row is inserted peer' => ['nextWindows.6.nextRowid', 9],
    'next seventh count value includes inserted row' => ['nextWindows.6.countValue', 2],
    'next seventh group concat includes inserted value' => ['nextWindows.6.groupConcat', '12|18'],
    'next inserted rowid' => ['nextWindows.7.rowid', 9],
    'next inserted first value is prior nullable bytes' => ['nextWindows.7.firstValue', null],
    'next inserted nth value is own bytes' => ['nextWindows.7.nthValue', 18],
    'next inserted final next rowid null' => ['nextWindows.7.nextRowid', null],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['vdbe affinity sorter window current source next ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['vdbe affinity sorter window current source next unchanged source reports unchanged'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan::compareWindowSources(
        $currentRows,
        $currentRows,
        'option_id',
        'bytes',
        ['site'],
        ['autoload', 'priority', 'option_name'],
        'enabled',
        1,
        1,
        'C',
        ['BINARY'],
        'GCG',
        ['NOCASE', 'BINARY', 'RTRIM'],
        [false, false, false],
        ['LAST', 'LAST', 'LAST']
    );

    $t->same(false, $plan['changed']);
    $t->same([], $plan['inserted']);
    $t->same([], $plan['deleted']);
};

$tests['vdbe affinity sorter window current source next rejects missing rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan::compareWindowSources(
        [['bytes' => 1, 'ord' => 1]],
        [],
        'option_id',
        'bytes',
        [],
        ['ord']
    ));
};

$tests['vdbe affinity sorter window current source next rejects empty source token'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan::compareWindowSources(
        [],
        [],
        'option_id',
        'bytes',
        [],
        ['ord'],
        null,
        0,
        0,
        [],
        [],
        [],
        [],
        [],
        [],
        'ROWS',
        'NO OTHERS',
        ''
    ));
};

return $tests;
