<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['option_id' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'yes', 'bytes' => 10, 'bucket' => 'a', 'include' => 1],
    ['option_id' => 2, 'option_name' => 'alpha_cache', 'autoload' => 'no', 'bytes' => 10, 'bucket' => 'b', 'include' => 0],
    ['option_id' => 3, 'option_name' => 'beta_cache', 'autoload' => 'yes', 'bytes' => 10, 'bucket' => 'c', 'include' => '1'],
    ['option_id' => 4, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 20, 'bucket' => 'd', 'include' => '0'],
    ['option_id' => 5, 'option_name' => 'cron_lock', 'autoload' => 'yes', 'bytes' => 20, 'bucket' => 'e', 'include' => true],
    ['option_id' => 6, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'bytes' => 30, 'bucket' => 'f', 'include' => null],
    ['option_id' => 7, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 30, 'bucket' => 'g', 'include' => '0.5'],
    ['option_id' => 8, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 30, 'bucket' => 'h', 'include' => ''],
];

$makeCursor = static function (string $exclude = 'NO OTHERS', ?string $filterColumn = null, array $orderDescending = []) use ($rows): SQLiteVdbeWindowAggregateCursor {
    return new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'bytes',
        [],
        ['bytes', 'option_name'],
        $filterColumn,
        0,
        1,
        [],
        [],
        ['NUMERIC', 'TEXT'],
        ['BINARY', 'BINARY'],
        $orderDescending,
        [],
        'GROUPS',
        $exclude,
    );
};

$drain = static function (SQLiteVdbeWindowAggregateCursor $cursor, string $method): array {
    $values = [];
    while (!$cursor->eof()) {
        $values[] = $cursor->{$method}();
        $cursor->next();
    }

    return $values;
};

$tests = [];

$cases = [
    'no others count all follows current and next peer groups' => ['NO OTHERS', null, 'countAll', [3, 3, 3, 3, 3, 3, 2, 2]],
    'no others count value follows current and next peer groups' => ['NO OTHERS', null, 'countValue', [3, 3, 3, 3, 3, 3, 2, 2]],
    'no others sum follows current and next peer groups' => ['NO OTHERS', null, 'sum', [30, 30, 50, 70, 70, 90, 60, 60]],
    'no others total follows current and next peer groups' => ['NO OTHERS', null, 'total', [30.0, 30.0, 50.0, 70.0, 70.0, 90.0, 60.0, 60.0]],
    'no others average follows current and next peer groups' => ['NO OTHERS', null, 'avg', [10.0, 10.0, 16.666666666666668, 23.333333333333332, 23.333333333333332, 30.0, 30.0, 30.0]],
    'no others group concat follows current and next peer groups' => ['NO OTHERS', null, 'groupConcat', ['10,10,10', '10,10,10', '10,20,20', '20,20,30', '20,20,30', '30,30,30', '30,30', '30,30']],
    'exclude current count drops only current row' => ['CURRENT ROW', null, 'countAll', [2, 2, 2, 2, 2, 2, 1, 1]],
    'exclude current sum drops only current row' => ['CURRENT ROW', null, 'sum', [20, 20, 40, 50, 50, 60, 30, 30]],
    'exclude current concat drops only current row' => ['CURRENT ROW', null, 'groupConcat', ['10,10', '10,10', '20,20', '20,30', '20,30', '30,30', '30', '30']],
    'exclude group count drops whole current peer group' => ['GROUP', null, 'countAll', [1, 1, 2, 1, 1, 2, 0, 0]],
    'exclude group sum drops whole current peer group' => ['GROUP', null, 'sum', [10, 10, 40, 30, 30, 60, null, null]],
    'exclude group concat drops whole current peer group' => ['GROUP', null, 'groupConcat', ['10', '10', '20,20', '30', '30', '30,30', null, null]],
    'exclude ties count keeps current row identity' => ['TIES', null, 'countAll', [2, 2, 3, 2, 2, 3, 1, 1]],
    'exclude ties sum keeps current row identity' => ['TIES', null, 'sum', [20, 20, 50, 50, 50, 90, 30, 30]],
    'exclude ties concat keeps current row identity' => ['TIES', null, 'groupConcat', ['10,10', '10,10', '10,20,20', '20,30', '20,30', '30,30,30', '30', '30']],
    'filter no others count uses SQL truthiness' => ['NO OTHERS', 'include', 'countValue', [2, 2, 2, 1, 1, 1, 1, 1]],
    'filter no others sum uses SQL truthiness' => ['NO OTHERS', 'include', 'sum', [20, 20, 30, 20, 20, 30, 30, 30]],
    'filter no others concat uses SQL truthiness' => ['NO OTHERS', 'include', 'groupConcat', ['10,10', '10,10', '10,20', '20', '20', '30', '30', '30']],
    'filter exclude current count composes with row exclusion' => ['CURRENT ROW', 'include', 'countValue', [1, 2, 1, 1, 0, 1, 0, 1]],
    'filter exclude current sum composes with row exclusion' => ['CURRENT ROW', 'include', 'sum', [10, 20, 20, 20, null, 30, null, 30]],
    'filter exclude group count composes with peer exclusion' => ['GROUP', 'include', 'countValue', [1, 1, 1, 0, 0, 1, 0, 0]],
    'filter exclude group sum composes with peer exclusion' => ['GROUP', 'include', 'sum', [10, 10, 20, null, null, 30, null, null]],
    'filter exclude ties count keeps current truthy peer only' => ['TIES', 'include', 'countValue', [2, 1, 2, 0, 1, 1, 1, 0]],
    'filter exclude ties sum keeps current truthy peer only' => ['TIES', 'include', 'sum', [20, 10, 30, null, 20, 30, 30, null]],
];

foreach ($cases as $name => [$exclude, $filterColumn, $method, $expected]) {
    $tests['vdbe window groups exclude filter current next37 ' . $name] = static function (TestRunner $t) use ($makeCursor, $drain, $exclude, $filterColumn, $method, $expected): void {
        $t->same($expected, $drain($makeCursor($exclude, $filterColumn), $method));
    };
}

$summaryCases = [
    'summary no others frame start positions' => ['NO OTHERS', null, 'frameStart', [0, 0, 2, 3, 3, 5, 6, 6]],
    'summary no others frame end positions' => ['NO OTHERS', null, 'frameEnd', [2, 2, 4, 5, 5, 7, 7, 7]],
    'summary no others frame row counts' => ['NO OTHERS', null, 'frameRows', [3, 3, 3, 3, 3, 3, 2, 2]],
    'summary no others filtered row counts without filter' => ['NO OTHERS', null, 'filteredRows', [3, 3, 3, 3, 3, 3, 2, 2]],
    'summary filter row counts' => ['NO OTHERS', 'include', 'filteredRows', [2, 2, 2, 1, 1, 1, 1, 1]],
    'summary exclude current frame row counts' => ['CURRENT ROW', null, 'frameRows', [2, 2, 2, 2, 2, 2, 1, 1]],
    'summary exclude group frame row counts' => ['GROUP', null, 'frameRows', [1, 1, 2, 1, 1, 2, 0, 0]],
    'summary exclude group empty tail starts become null' => ['GROUP', null, 'frameStart', [2, 2, 3, 5, 5, 6, null, null]],
    'summary exclude group empty tail ends become null' => ['GROUP', null, 'frameEnd', [2, 2, 4, 5, 5, 7, null, null]],
    'summary exclude ties frame row counts' => ['TIES', null, 'frameRows', [2, 2, 3, 2, 2, 3, 1, 1]],
    'summary reports sorted composite order keys' => ['NO OTHERS', null, 'orderKey', [[10, 'alpha_cache'], [10, 'alpha_cache'], [10, 'beta_cache'], [20, 'cron_lock'], [20, 'cron_lock'], [30, 'plugin_rules'], [30, 'theme_mods'], [30, 'theme_mods']]],
    'summary reports empty partition keys without partition clause' => ['NO OTHERS', null, 'partitionKey', [[], [], [], [], [], [], [], []]],
];

foreach ($summaryCases as $name => [$exclude, $filterColumn, $field, $expected]) {
    $tests['vdbe window groups exclude filter current next37 ' . $name] = static function (TestRunner $t) use ($makeCursor, $exclude, $filterColumn, $field, $expected): void {
        $cursor = $makeCursor($exclude, $filterColumn);
        $actual = [];
        while (!$cursor->eof()) {
            $actual[] = $cursor->currentSummary()[$field];
            $cursor->next();
        }
        $t->same($expected, $actual);
    };
}

$tests['vdbe window groups exclude filter current next37 descending second term changes next peer group'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor('NO OTHERS', null, [false, true]);
    $actual = [];
    while (!$cursor->eof()) {
        $actual[] = array_column($cursor->currentFrameRows(), 'bucket');
        $cursor->next();
    }
    $t->same([['c', 'a', 'b'], ['a', 'b', 'd', 'e'], ['a', 'b', 'd', 'e'], ['d', 'e', 'g', 'h'], ['d', 'e', 'g', 'h'], ['g', 'h', 'f'], ['g', 'h', 'f'], ['f']], $actual);
};

$tests['vdbe window groups exclude filter current next37 partition isolates autoload groups'] = static function (TestRunner $t) use ($rows, $drain): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['autoload'], ['bytes', 'option_name'], null, 0, 1, ['TEXT'], ['BINARY'], ['NUMERIC', 'TEXT'], ['BINARY', 'BINARY'], [], [], 'GROUPS');
    $t->same([30, 50, 60, 30, 20, 30, 50, 30], $drain($cursor, 'sum'));
};

$tests['vdbe window groups exclude filter current next37 partition filter isolates autoload groups'] = static function (TestRunner $t) use ($rows, $drain): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['autoload'], ['bytes', 'option_name'], 'include', 0, 1, ['TEXT'], ['BINARY'], ['NUMERIC', 'TEXT'], ['BINARY', 'BINARY'], [], [], 'GROUPS');
    $t->same([null, null, null, null, 20, 30, 50, 30], $drain($cursor, 'sum'));
};

$tests['vdbe window groups exclude filter current next37 count all ignores filter but keeps exclude'] = static function (TestRunner $t) use ($makeCursor, $drain): void {
    $t->same([2, 2, 2, 2, 2, 2, 1, 1], $drain($makeCursor('CURRENT ROW', 'include'), 'countAll'));
};

$tests['vdbe window groups exclude filter current next37 current values returns filtered frame values'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor('CURRENT ROW', 'include');
    $t->same([10], $cursor->currentValues());
};

$tests['vdbe window groups exclude filter current next37 current frame rows can bypass filter'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor('CURRENT ROW', 'include');
    $t->same([2, 3], array_column($cursor->currentFrameRows(false), 'option_id'));
};

$tests['vdbe window groups exclude filter current next37 current frame rows can apply filter'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor('CURRENT ROW', 'include');
    $t->same([3], array_column($cursor->currentFrameRows(true), 'option_id'));
};

$tests['vdbe window groups exclude filter current next37 drain summaries keeps exclude totals'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->same([20.0, 20.0, 40.0, 50.0, 50.0, 60.0, 30.0, 30.0], array_column($makeCursor('CURRENT ROW')->drainSummaries(), 'total'));
};

$tests['vdbe window groups exclude filter current next37 drain summaries keeps exclude concat'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->same(['10,10', '10,10', '20,20', '20,30', '20,30', '30,30', '30', '30'], array_column($makeCursor('CURRENT ROW')->drainSummaries(), 'groupConcat'));
};

$tests['vdbe window groups exclude filter current next37 rejects missing filter column'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', [], ['bytes'], 'missing', 0, 1, [], [], [], [], [], [], 'GROUPS'));
};

$tests['vdbe window groups exclude filter current next37 rejects unsupported exclude mode'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', [], ['bytes'], null, 0, 1, [], [], [], [], [], [], 'GROUPS', 'SIDEWAYS'));
};

$tests['vdbe window groups exclude filter current next37 rejects fractional groups preceding'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', [], ['bytes'], null, 0, 1, [], [], [], [], [], [], 'GROUPS');
    $reflection = new ReflectionProperty($cursor, 'preceding');
    $t->same(0, $reflection->getValue($cursor));
};

$tests['vdbe window groups exclude filter current next37 rejects groups without order columns'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', [], [], null, 0, 1, [], [], [], [], [], [], 'GROUPS'));
};

$tests['vdbe window groups exclude filter current next37 next at eof remains eof'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor(array_slice($rows, 0, 1), 'bytes', [], ['bytes'], null, 0, 1, [], [], [], [], [], [], 'GROUPS', 'GROUP');
    $cursor->next();
    $cursor->next();
    $t->true($cursor->eof());
};

return $tests;
