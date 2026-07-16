<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'yes', 'bytes' => 10, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'no', 'bytes' => 10, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'beta_cache', 'autoload' => 'yes', 'bytes' => 10, 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 20, 'include' => '0'],
    ['rowid' => 5, 'site' => 1, 'option_name' => 'cron_lock', 'autoload' => 'yes', 'bytes' => 20, 'include' => true],
    ['rowid' => 6, 'site' => 1, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 30, 'include' => '0.5'],
    ['rowid' => 7, 'site' => 1, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 30, 'include' => ''],
    ['rowid' => 8, 'site' => 2, 'option_name' => 'network_home', 'autoload' => 'yes', 'bytes' => 10, 'include' => 1],
    ['rowid' => 9, 'site' => 2, 'option_name' => 'network_plugins', 'autoload' => 'no', 'bytes' => 20, 'include' => null],
    ['rowid' => 10, 'site' => 2, 'option_name' => 'network_plugins', 'autoload' => 'yes', 'bytes' => 20, 'include' => 2],
];

$makeCursor = static function (
    string $unit = 'GROUPS',
    string $exclude = 'CURRENT ROW',
    ?string $filter = 'include',
    int|float $preceding = 0,
    int|float $following = 1,
    array $orderColumns = ['bytes', 'option_name'],
    array|string $orderAffinities = ['NUMERIC', 'TEXT'],
    array $orderCollations = ['BINARY', 'BINARY'],
) use ($rows): SQLiteVdbeWindowAggregateCursor {
    return new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'bytes',
        ['site'],
        $orderColumns,
        $filter,
        $preceding,
        $following,
        ['INTEGER'],
        ['BINARY'],
        $orderAffinities,
        $orderCollations,
        [],
        [],
        $unit,
        $exclude,
    );
};

$summaryAt = static function (SQLiteVdbeWindowAggregateCursor $cursor, int $position, bool $valueFilter = false): array {
    $cursor->rewind();
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor->currentNextAggregateSummary('rowid', '|', 2, $valueFilter);
};

$column = static function (SQLiteVdbeWindowAggregateCursor $cursor, string $side, string $field, bool $valueFilter = false): array {
    $actual = [];
    while (!$cursor->eof()) {
        $snapshot = $cursor->currentNextAggregateSummary('rowid', '|', 2, $valueFilter);
        $actual[] = $snapshot[$side][$field] ?? null;
        $cursor->next();
    }

    return $actual;
};

$tests = [];

$cases = [
    'current filtered rowids exclude current' => ['current', 'filteredFrameRowids', [[3], [1, 3], [5], [5, 6], [6], [], [6], [10], [10], []]],
    'next filtered rowids exclude current' => ['next', 'filteredFrameRowids', [[1, 3], [5], [5, 6], [6], [], [6], [10], [10], [], null]],
    'current physical rowids exclude current' => ['current', 'frameRowids', [[2, 3], [1, 3], [4, 5], [5, 6, 7], [4, 6, 7], [7], [6], [9, 10], [10], [9]]],
    'next physical rowids exclude current' => ['next', 'frameRowids', [[1, 3], [4, 5], [5, 6, 7], [4, 6, 7], [7], [6], [9, 10], [10], [9], null]],
    'current count all exclude current' => ['current', 'countAll', [2, 2, 2, 3, 3, 1, 1, 2, 1, 1]],
    'next count all exclude current' => ['next', 'countAll', [2, 2, 3, 3, 1, 1, 2, 1, 1, null]],
    'current count value filter exclude current' => ['current', 'countValue', [1, 2, 1, 2, 1, 0, 1, 1, 1, 0]],
    'next count value filter exclude current' => ['next', 'countValue', [2, 1, 2, 1, 0, 1, 1, 1, 0, null]],
    'current sum filter exclude current' => ['current', 'sum', [10, 20, 20, 50, 30, null, 30, 20, 20, null]],
    'next sum filter exclude current' => ['next', 'sum', [20, 20, 50, 30, null, 30, 20, 20, null, null]],
    'current total filter exclude current' => ['current', 'total', [10.0, 20.0, 20.0, 50.0, 30.0, 0.0, 30.0, 20.0, 20.0, 0.0]],
    'next total filter exclude current' => ['next', 'total', [20.0, 20.0, 50.0, 30.0, 0.0, 30.0, 20.0, 20.0, 0.0, null]],
    'current avg filter exclude current' => ['current', 'avg', [10.0, 10.0, 20.0, 25.0, 30.0, null, 30.0, 20.0, 20.0, null]],
    'next avg filter exclude current' => ['next', 'avg', [10.0, 20.0, 25.0, 30.0, null, 30.0, 20.0, 20.0, null, null]],
    'current min filter exclude current' => ['current', 'min', [10, 10, 20, 20, 30, null, 30, 20, 20, null]],
    'next min filter exclude current' => ['next', 'min', [10, 20, 20, 30, null, 30, 20, 20, null, null]],
    'current max filter exclude current' => ['current', 'max', [10, 10, 20, 30, 30, null, 30, 20, 20, null]],
    'next max filter exclude current' => ['next', 'max', [10, 20, 30, 30, null, 30, 20, 20, null, null]],
    'current concat filter exclude current' => ['current', 'groupConcat', ['10', '10|10', '20', '20|30', '30', null, '30', '20', '20', null]],
    'next concat filter exclude current' => ['next', 'groupConcat', ['10|10', '20', '20|30', '30', null, '30', '20', '20', null, null]],
    'current first value unfiltered exclude current' => ['current', 'firstValue', [10, 10, 20, 20, 20, 30, 30, 20, 20, 20]],
    'next first value unfiltered exclude current' => ['next', 'firstValue', [10, 20, 20, 20, 30, 30, 20, 20, 20, null]],
    'current last value unfiltered exclude current' => ['current', 'lastValue', [10, 10, 20, 30, 30, 30, 30, 20, 20, 20]],
    'next last value unfiltered exclude current' => ['next', 'lastValue', [10, 20, 30, 30, 30, 30, 20, 20, 20, null]],
    'current nth value unfiltered exclude current' => ['current', 'nthValue', [10, 10, 20, 30, 30, null, null, 20, null, null]],
    'next nth value unfiltered exclude current' => ['next', 'nthValue', [10, 20, 30, 30, null, null, 20, null, null, null]],
];

foreach ($cases as $name => [$side, $field, $expected]) {
    $tests['vdbe window filter exclude frame current next55 ' . $name] = static function (TestRunner $t) use ($makeCursor, $column, $side, $field, $expected): void {
        $t->same($expected, $column($makeCursor(), $side, $field));
    };
}

$excludeCases = [
    'exclude group current filtered rowids' => ['GROUP', 'current', 'filteredFrameRowids', [[3], [3], [5], [6], [6], [], [], [10], [], []]],
    'exclude group next filtered rowids' => ['GROUP', 'next', 'filteredFrameRowids', [[3], [5], [6], [6], [], [], [10], [], [], null]],
    'exclude group current sum' => ['GROUP', 'current', 'sum', [10, 10, 20, 30, 30, null, null, 20, null, null]],
    'exclude group next sum' => ['GROUP', 'next', 'sum', [10, 20, 30, 30, null, null, 20, null, null, null]],
    'exclude group current count all' => ['GROUP', 'current', 'countAll', [1, 1, 2, 2, 2, 0, 0, 2, 0, 0]],
    'exclude group next count all' => ['GROUP', 'next', 'countAll', [1, 2, 2, 2, 0, 0, 2, 0, 0, null]],
    'exclude ties current filtered rowids' => ['TIES', 'current', 'filteredFrameRowids', [[1, 3], [3], [3, 5], [6], [5, 6], [6], [], [8, 10], [], [10]]],
    'exclude ties next filtered rowids' => ['TIES', 'next', 'filteredFrameRowids', [[3], [3, 5], [6], [5, 6], [6], [], [8, 10], [], [10], null]],
    'exclude ties current sum' => ['TIES', 'current', 'sum', [20, 10, 30, 30, 50, 30, null, 30, null, 20]],
    'exclude ties next sum' => ['TIES', 'next', 'sum', [10, 30, 30, 50, 30, null, 30, null, 20, null]],
    'exclude ties current count all' => ['TIES', 'current', 'countAll', [2, 2, 3, 3, 3, 1, 1, 3, 1, 1]],
    'exclude ties next count all' => ['TIES', 'next', 'countAll', [2, 3, 3, 3, 1, 1, 3, 1, 1, null]],
];

foreach ($excludeCases as $name => [$exclude, $side, $field, $expected]) {
    $tests['vdbe window filter exclude frame current next55 ' . $name] = static function (TestRunner $t) use ($makeCursor, $column, $exclude, $side, $field, $expected): void {
        $t->same($expected, $column($makeCursor('GROUPS', $exclude), $side, $field));
    };
}

$rowsCases = [
    'rows current filtered rowids' => ['current', 'filteredFrameRowids', [[3], [3], [5], [5, 6], [6], [], [], [10], [10], []]],
    'rows next filtered rowids' => ['next', 'filteredFrameRowids', [[3], [5], [5, 6], [6], [], [], [10], [10], [], null]],
    'rows current frame rowids' => ['current', 'frameRowids', [[2, 3], [3, 4], [4, 5], [5, 6], [6, 7], [7], [], [9, 10], [10], []]],
    'rows next frame rowids' => ['next', 'frameRowids', [[3, 4], [4, 5], [5, 6], [6, 7], [7], [], [9, 10], [10], [], null]],
    'rows current sum' => ['current', 'sum', [10, 10, 20, 50, 30, null, null, 20, 20, null]],
    'rows next sum' => ['next', 'sum', [10, 20, 50, 30, null, null, 20, 20, null, null]],
    'rows current count all' => ['current', 'countAll', [2, 2, 2, 2, 2, 1, 0, 2, 1, 0]],
    'rows next count all' => ['next', 'countAll', [2, 2, 2, 2, 1, 0, 2, 1, 0, null]],
    'rows current first filtered value' => ['current', 'firstValue', [10, 10, 20, 20, 30, null, null, 20, 20, null]],
    'rows next first filtered value' => ['next', 'firstValue', [10, 20, 20, 30, null, null, 20, 20, null, null]],
];

foreach ($rowsCases as $name => [$side, $field, $expected]) {
    $tests['vdbe window filter exclude frame current next55 ' . $name] = static function (TestRunner $t) use ($makeCursor, $column, $side, $field, $expected): void {
        $t->same($expected, $column($makeCursor('ROWS', 'CURRENT ROW', 'include', 0, 2, ['bytes', 'option_name'], ['NUMERIC', 'TEXT']), $side, $field, true));
    };
}

$rangeCases = [
    'range current filtered rowids' => ['current', 'filteredFrameRowids', [[3, 5], [1, 3, 5], [1, 5], [5, 6], [6], [], [6], [10], [10], []]],
    'range next filtered rowids' => ['next', 'filteredFrameRowids', [[1, 3, 5], [1, 5], [5, 6], [6], [], [6], [10], [10], [], null]],
    'range current sum' => ['current', 'sum', [30, 40, 30, 50, 30, null, 30, 20, 20, null]],
    'range next sum' => ['next', 'sum', [40, 30, 50, 30, null, 30, 20, 20, null, null]],
    'range current count all' => ['current', 'countAll', [4, 4, 4, 3, 3, 1, 1, 2, 1, 1]],
    'range next count all' => ['next', 'countAll', [4, 4, 3, 3, 1, 1, 2, 1, 1, null]],
    'range current concat' => ['current', 'groupConcat', ['10|20', '10|10|20', '10|20', '20|30', '30', null, '30', '20', '20', null]],
    'range next concat' => ['next', 'groupConcat', ['10|10|20', '10|20', '20|30', '30', null, '30', '20', '20', null, null]],
];

foreach ($rangeCases as $name => [$side, $field, $expected]) {
    $tests['vdbe window filter exclude frame current next55 ' . $name] = static function (TestRunner $t) use ($makeCursor, $column, $side, $field, $expected): void {
        $t->same($expected, $column($makeCursor('RANGE', 'CURRENT ROW', 'include', 0, 10, ['bytes'], ['NUMERIC'], ['BINARY']), $side, $field));
    };
}

$tests['vdbe window filter exclude frame current next55 snapshot does not advance cursor'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $first = $cursor->currentNextAggregateSummary('rowid');
    $second = $cursor->currentNextAggregateSummary('rowid');
    $t->same($first['current']['row']['rowid'], $second['current']['row']['rowid']);
};

$tests['vdbe window filter exclude frame current next55 next side is null at eof tail'] = static function (TestRunner $t) use ($makeCursor): void {
    $snapshot = $makeCursor()->currentNextAggregateSummary('rowid');
    $cursor = $makeCursor();
    while ($cursor->peekNextRow() !== null) {
        $cursor->next();
    }
    $tail = $cursor->currentNextAggregateSummary('rowid');
    $t->same([false, true], [$snapshot['next'] === null, $tail['next'] === null]);
};

$tests['vdbe window filter exclude frame current next55 value filter changes first value'] = static function (TestRunner $t) use ($makeCursor, $summaryAt): void {
    $unfiltered = $summaryAt($makeCursor('GROUPS', 'CURRENT ROW', 'include'), 3, false);
    $filtered = $summaryAt($makeCursor('GROUPS', 'CURRENT ROW', 'include'), 3, true);
    $t->same([20, 20], [$unfiltered['current']['firstValue'], $filtered['current']['firstValue']]);
};

$tests['vdbe window filter exclude frame current next55 row payload is preserved for current and next'] = static function (TestRunner $t) use ($makeCursor): void {
    $snapshot = $makeCursor()->currentNextAggregateSummary('rowid');
    $t->same([1, 2, 'alpha_cache'], [$snapshot['current']['row']['rowid'], $snapshot['next']['row']['rowid'], $snapshot['next']['row']['option_name']]);
};

$tests['vdbe window filter exclude frame current next55 empty frame aggregates are sqlite shaped'] = static function (TestRunner $t) use ($makeCursor, $summaryAt): void {
    $snapshot = $summaryAt($makeCursor('GROUPS', 'GROUP'), 6);
    $t->same([0, 0, null, 0.0, null, null, null, null], [
        $snapshot['current']['countAll'],
        $snapshot['current']['countValue'],
        $snapshot['current']['sum'],
        $snapshot['current']['total'],
        $snapshot['current']['avg'],
        $snapshot['current']['min'],
        $snapshot['current']['max'],
        $snapshot['current']['groupConcat'],
    ]);
};

$tests['vdbe window filter exclude frame current next55 rejects invalid nth'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCursor()->currentNextAggregateSummary('rowid', ',', 0));
};

$tests['vdbe window filter exclude frame current next55 throws at eof'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    while (!$cursor->eof()) {
        $cursor->next();
    }
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentNextAggregateSummary('rowid'));
};

$tests['vdbe window filter exclude frame current next55 missing rowid column yields null identifiers'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor([['ord' => 1, 'v' => 3], ['ord' => 2, 'v' => 4]], 'v', [], ['ord'], null, 0, 1);
    $snapshot = $cursor->currentNextAggregateSummary('missing');
    $t->same([[null, null], [null]], [$snapshot['current']['frameRowids'], $snapshot['next']['frameRowids']]);
};

return $tests;
