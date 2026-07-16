<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$tests = [];

$compareCases = [
    'ascending default keeps null before text' => [[null], ['alpha'], 'G', ['BINARY'], [], [], -1],
    'descending default reverses null after text' => [[null], ['alpha'], 'G', ['BINARY'], [true], [], 1],
    'ascending nulls last puts null after text' => [[null], ['alpha'], 'G', ['BINARY'], [], ['LAST'], 1],
    'descending nulls first puts null before text' => [[null], ['alpha'], 'G', ['BINARY'], [true], ['FIRST'], -1],
    'descending nulls last keeps null after text' => [[null], ['alpha'], 'G', ['BINARY'], [true], ['LAST'], 1],
    'ascending nulls first keeps null before text' => [[null], ['alpha'], 'G', ['BINARY'], [], ['FIRST'], -1],
    'right null ascending nulls last puts value before null' => [['alpha'], [null], 'G', ['BINARY'], [], ['LAST'], -1],
    'right null descending nulls first puts value after null' => [['alpha'], [null], 'G', ['BINARY'], [true], ['FIRST'], 1],
    'both nulls still equal with nulls last' => [[null], [null], 'G', ['BINARY'], [], ['LAST'], 0],
    'nocase comparison follows null placement after non-null' => [['Plugin'], ['plugin'], 'G', ['NOCASE'], [], ['LAST'], 0],
    'rtrim comparison follows null placement after non-null' => [['cache  '], ['cache'], 'G', ['RTRIM'], [], ['FIRST'], 0],
    'binary comparison still differs after null placement' => [['Plugin'], ['plugin'], 'G', ['BINARY'], [], ['LAST'], -1],
    'second key nulls last after first nocase equality' => [['Plugin', null], ['plugin', 'active'], 'GG', ['NOCASE', 'BINARY'], [], [null, 'LAST'], 1],
    'second key nulls first after first nocase equality' => [['Plugin', null], ['plugin', 'active'], 'GG', ['NOCASE', 'BINARY'], [], [null, 'FIRST'], -1],
    'second key descending nulls first is not reversed' => [['Plugin', null], ['plugin', 'active'], 'GG', ['NOCASE', 'BINARY'], [false, true], [null, 'FIRST'], -1],
    'second key descending default reverses null ordering' => [['Plugin', null], ['plugin', 'active'], 'GG', ['NOCASE', 'BINARY'], [false, true], [], 1],
    'later key decides after both nulls with nulls last' => [[null, 2], [null, 1], 'GC', ['BINARY', 'BINARY'], [], ['LAST', null], 1],
    'later key desc decides after both nulls with nulls first' => [[null, 2], [null, 1], 'GC', ['BINARY', 'BINARY'], [false, true], ['FIRST', null], -1],
    'numeric affinity nulls last after text number conversion' => [[null], ['10'], 'C', ['BINARY'], [], ['LAST'], 1],
    'numeric affinity non-null comparison unchanged' => [['2'], ['10'], 'C', ['BINARY'], [], ['LAST'], -1],
    'text affinity non-null comparison unchanged' => [[2], ['10'], 'G', ['BINARY'], [], ['LAST'], 1],
    'third key nulls last after rtrim equality' => [['cache ', 'on', null], ['cache', 'on', 7], 'GGC', ['RTRIM', 'BINARY', 'BINARY'], [], [null, null, 'LAST'], 1],
    'third key nulls first after rtrim equality' => [['cache ', 'on', null], ['cache', 'on', 7], 'GGC', ['RTRIM', 'BINARY', 'BINARY'], [], [null, null, 'FIRST'], -1],
    'first key binary difference stops before later null' => [['A', null], ['a', 'value'], 'GG', ['BINARY', 'BINARY'], [], [null, 'FIRST'], -1],
    'first key nocase equality reaches later null' => [['A', null], ['a', 'value'], 'GG', ['NOCASE', 'BINARY'], [], [null, 'LAST'], 1],
    'empty null placement behaves like default ascending' => [[null], ['x'], 'G', ['BINARY'], [], [''], -1],
];

foreach ($compareCases as $name => [$left, $right, $affinities, $collations, $descending, $nulls, $expected]) {
    $tests['vdbe sorter null collate compare ' . $name] = static function (TestRunner $t) use ($left, $right, $affinities, $collations, $descending, $nulls, $expected): void {
        $comparison = SQLiteVdbeSortCompare::compareRecords($left, $right, $affinities, $collations, $descending, $nulls);
        $t->same($expected, $comparison <=> 0);
    };
}

$rows = [
    ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_2', 'priority' => null],
    ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10'],
    ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => null],
    ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => '1'],
    ['option_id' => 5, 'autoload' => null, 'option_name' => 'network', 'priority' => '3'],
    ['option_id' => 6, 'autoload' => 'yes', 'option_name' => null, 'priority' => '2'],
    ['option_id' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => null],
    ['option_id' => 8, 'autoload' => 'YES', 'option_name' => 'Plugin_2 ', 'priority' => '2'],
];

$tests['vdbe sorter rows apply nulls last to ascending current key'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['autoload', 'priority', 'option_id'], 'GCD', ['NOCASE', 'BINARY', 'BINARY'], [false, false, false], ['LAST', 'LAST', null]);
    $t->same([4, 3, 6, 8, 2, 1, 7, 5], array_column($ordered, 'option_id'));
};

$tests['vdbe sorter rows apply nulls first to descending current key'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['autoload', 'priority', 'option_id'], 'GCD', ['NOCASE', 'BINARY', 'BINARY'], [false, true, false], ['LAST', 'FIRST', null]);
    $t->same([3, 4, 1, 7, 2, 6, 8, 5], array_column($ordered, 'option_id'));
};

$tests['vdbe sorter rows combine nocase and rtrim before stable current sequence'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['option_name', 'option_id'], 'GD', ['RTRIM', 'BINARY'], [false, false], ['LAST', null]);
    $t->same([1, 8, 3, 4, 5, 2, 7, 6], array_column($ordered, 'option_id'));
};

$tests['vdbe sorter cursor current starts at first sorted row'] = static function (TestRunner $t) use ($rows): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['autoload', 'priority', 'option_id'], 'GCD', ['NOCASE', 'BINARY', 'BINARY'], [false, false, false], ['LAST', 'LAST', null]);
    $current = $cursor->current();

    $t->same(0, $cursor->position());
    $t->true($current !== null);
    $t->same(4, $current['option_id']);
    $t->true(!$cursor->eof());
};

$tests['vdbe sorter cursor next advances through null-collated rows'] = static function (TestRunner $t) use ($rows): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['autoload', 'priority', 'option_id'], 'GCD', ['NOCASE', 'BINARY', 'BINARY'], [false, false, false], ['LAST', 'LAST', null]);
    $seen = [];
    while (!$cursor->eof()) {
        $current = $cursor->current();
        $seen[] = $current['option_id'];
        $cursor->next();
    }

    $t->same([4, 3, 6, 8, 2, 1, 7, 5], $seen);
    $t->same(8, $cursor->position());
    $t->same(null, $cursor->current());
    $t->true($cursor->eof());
};

$tests['vdbe sorter cursor remaining rows report current suffix'] = static function (TestRunner $t) use ($rows): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['option_name'], 'G', ['NOCASE'], [], ['LAST']);
    $cursor->next();
    $cursor->next();
    $remaining = $cursor->remainingRows();

    $t->same(2, $cursor->position());
    $t->same([5, 2, 1, 7, 8, 6], array_column($remaining, 'option_id'));
};

$tests['vdbe sorter cursor next remains eof after final row'] = static function (TestRunner $t) use ($rows): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['priority'], 'C', [], [], ['LAST']);
    for ($i = 0; $i < 12; $i++) {
        $cursor->next();
    }

    $t->true($cursor->eof());
    $t->same(8, $cursor->position());
    $t->same([], $cursor->remainingRows());
};

$tests['vdbe sorter rows reject invalid null placement'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::sortRows($rows, ['priority'], 'C', [], [], ['MIDDLE']));
};

$tests['vdbe sorter compare rejects invalid null placement'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::compareRecords([null], [1], 'C', [], [], ['SIDEWAYS']));
};

return $tests;
