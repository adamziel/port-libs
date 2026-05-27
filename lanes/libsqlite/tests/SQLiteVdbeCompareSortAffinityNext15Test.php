<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$tests = [];

$compareCases = [
    'numeric text equals integer from C affinity' => [['7'], [7], 'C', [], [], 0],
    'integer text equals integer from D affinity' => [['7'], [7], 'D', [], [], 0],
    'real text equals real from E affinity' => [['7.5'], [7.5], 'E', [], [], 0],
    'text affinity keeps lexical order' => [[2], ['10'], 'G', [], [], 1],
    'blob affinity leaves numeric text above integer' => [['7'], [7], 'B', [], [], 1],
    'none affinity leaves numeric text above integer' => [['7'], [7], '', [], [], 1],
    'numeric affinity compares exponent integer' => [['9e1'], [90], 'C', [], [], 0],
    'numeric affinity compares exponent real' => [['9.5e1'], [95.0], 'C', [], [], 0],
    'numeric affinity leaves malformed text above number' => [['9x'], [9], 'C', [], [], 1],
    'numeric affinity converts blob bytes' => [[new SQLiteBlobValue('12')], [12], 'C', [], [], 0],
    'text collation nocase folds after text affinity' => [['Plugin'], ['plugin'], 'G', ['NOCASE'], [], 0],
    'text collation binary preserves case after text affinity' => [['Plugin'], ['plugin'], 'G', ['BINARY'], [], -1],
    'text collation rtrim ignores trailing spaces' => [['autoload  '], ['autoload'], 'G', ['RTRIM'], [], 0],
    'blob bytes compare after storage rank' => [[new SQLiteBlobValue("a\x00")], [new SQLiteBlobValue("a\x01")], 'B', [], [], -1],
    'null sorts before integer in ascending compare' => [[null], [0], 'C', [], [], -1],
    'null sorts after integer when descending flag reverses' => [[null], [0], 'C', [], [true], 1],
    'descending reverses numeric comparison' => [[1], [2], 'C', [], [true], 1],
    'descending reverses text comparison' => [['a'], ['b'], 'G', [], [true], 1],
    'second key decides after first equality' => [['cache', '2'], ['cache', 10], 'GC', ['BINARY', 'BINARY'], [], -1],
    'second key numeric affinity beats lexical text' => [['cache', '10'], ['cache', 2], 'GC', ['BINARY', 'BINARY'], [], 1],
    'second key descending reverses numeric affinity' => [['cache', '10'], ['cache', 2], 'GC', ['BINARY', 'BINARY'], [false, true], -1],
    'third key decides with nocase prefix equality' => [['cache', 'Plugin', 2], ['cache', 'plugin', 3], 'GGC', ['BINARY', 'NOCASE', 'BINARY'], [], -1],
    'third key descending decides with nocase prefix equality' => [['cache', 'Plugin', 2], ['cache', 'plugin', 3], 'GGC', ['BINARY', 'NOCASE', 'BINARY'], [false, false, true], 1],
    'per slot list affinities compare numeric then text' => [['02', 2], [2, '2'], ['NUMERIC', 'TEXT'], [], [], 0],
    'per slot list affinities preserve blob when none' => [[new SQLiteBlobValue('2')], ['2'], ['NONE'], [], [], 1],
    'per slot list affinities convert lhs integer to text' => [[2], ['10'], ['TEXT'], [], [], 1],
    'left null equals right null' => [[null], [null], 'C', [], [], 0],
    'later null decides before text' => [['same', null], ['same', 'x'], 'GG', [], [], -1],
    'later null descending decides after text' => [['same', null], ['same', 'x'], 'GG', [], [false, true], 1],
    'rtrim applies only to selected slot' => [['a ', 'b '], ['a', 'b'], 'GG', ['RTRIM', 'BINARY'], [], 1],
    'nocase applies only to selected slot' => [['A', 'B'], ['a', 'b'], 'GG', ['NOCASE', 'BINARY'], [], -1],
    'real affinity exact decimal compares equal to integer' => [['12.0'], [12], 'E', [], [], 0],
    'numeric affinity trims whitespace' => [[" \t12\n"], [12], 'C', [], [], 0],
    'numeric affinity signed decimal orders below' => [['-1.5'], [-1], 'C', [], [], -1],
    'text affinity boolean true compares as one' => [[true], ['1'], 'G', [], [], 0],
    'text affinity boolean false compares as zero' => [[false], ['0'], 'G', [], [], 0],
    'mixed numeric storage compares integer and real' => [[7], [7.0], 'C', [], [], 0],
    'mixed numeric storage orders integer and real' => [[7], [7.5], 'C', [], [], -1],
    'blob affinity keeps blob after text' => [[new SQLiteBlobValue('a')], ['z'], 'B', [], [], 1],
    'numeric affinity empty string stays text' => [[''], [0], 'C', [], [], 1],
    'numeric affinity whitespace string stays text' => [['   '], [0], 'C', [], [], 1],
    'numeric affinity hex-looking string stays text' => [['0x10'], [16], 'C', [], [], 1],
    'multi slot stops at first difference' => [['a', 99], ['b', 1], 'GC', [], [], -1],
    'multi slot first descending stops before later value' => [['a', 99], ['b', 1], 'GC', [], [true, false], 1],
    'multi slot exact equality returns zero' => [['a', '2', null], ['a', 2, null], 'GCG', [], [], 0],
    'float affinity code F is accepted' => [['2.25'], [2.25], 'F', [], [], 0],
    'integer affinity code D handles plus sign' => [['+8'], [8], 'D', [], [], 0],
    'numeric affinity code C handles negative exponent' => [['-8e0'], [-8], 'C', [], [], 0],
    'text affinity code G compares converted real lexically' => [[2.5], ['10'], 'G', [], [], 1],
    'none affinity code A preserves storage rank' => [[2.5], ['10'], 'A', [], [], -1],
];

foreach ($compareCases as $name => [$left, $right, $affinities, $collations, $descending, $expected]) {
    $tests['vdbe compare sort affinity ' . $name] = static function (TestRunner $t) use ($left, $right, $affinities, $collations, $descending, $expected): void {
        $comparison = SQLiteVdbeSortCompare::compareRecords($left, $right, $affinities, $collations, $descending);
        $t->same($expected, $comparison <=> 0);
    };
}

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_10', 'autoload' => 'yes', 'priority' => '10'],
    ['option_id' => 2, 'option_name' => 'Plugin_2', 'autoload' => 'yes', 'priority' => '2'],
    ['option_id' => 3, 'option_name' => 'cache', 'autoload' => 'no', 'priority' => null],
    ['option_id' => 4, 'option_name' => 'cache ', 'autoload' => 'no', 'priority' => '1'],
    ['option_id' => 5, 'option_name' => 'mu_plugin', 'autoload' => 'yes', 'priority' => new SQLiteBlobValue('4')],
];

$tests['vdbe sort rows applies numeric affinity before stable rowid tiebreak'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['autoload', 'priority', 'option_id'], 'GCD', ['BINARY', 'BINARY', 'BINARY']);
    $t->same([3, 4, 2, 5, 1], array_column($ordered, 'option_id'));
};

$tests['vdbe sort rows applies descending numeric key'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['autoload', 'priority', 'option_id'], 'GCD', ['BINARY', 'BINARY', 'BINARY'], [false, true, false]);
    $t->same([4, 3, 1, 5, 2], array_column($ordered, 'option_id'));
};

$tests['vdbe sort rows applies nocase option name ordering'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['option_name'], 'G', ['NOCASE']);
    $t->same(['cache', 'cache ', 'mu_plugin', 'plugin_10', 'Plugin_2'], array_column($ordered, 'option_name'));
};

$tests['vdbe sort rows applies rtrim collation with stable ties'] = static function (TestRunner $t) use ($rows): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['option_name'], 'G', ['RTRIM']);
    $t->same([2, 3, 4, 5, 1], array_column($ordered, 'option_id'));
};

$tests['vdbe sort rows preserves input order for equal keys'] = static function (TestRunner $t): void {
    $ordered = SQLiteVdbeSortCompare::sortRows([
        ['option_id' => 7, 'priority' => '01'],
        ['option_id' => 8, 'priority' => 1],
        ['option_id' => 9, 'priority' => '1.0'],
    ], ['priority'], 'C');
    $t->same([7, 8, 9], array_column($ordered, 'option_id'));
};

$tests['vdbe sort rows rejects missing sort column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::sortRows([['id' => 1]], ['priority'], 'C'));
};

$tests['vdbe compare records rejects unsupported affinity code'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::compareRecords([1], [1], 'Z'));
};

$tests['vdbe compare records rejects arity mismatch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::compareRecords([1], [1, 2], 'C'));
};

return $tests;
