<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$tests = [];

$coercionCases = [
    'max int literal remains integer' => ['9223372036854775807', 'integer', 9223372036854775807],
    'max int plus one literal becomes real' => ['9223372036854775808', 'real', 9.223372036854776E+18],
    'max int plus padded one literal becomes real' => ['0009223372036854775808', 'real', 9.223372036854776E+18],
    'min int literal remains integer' => ['-9223372036854775808', 'integer', PHP_INT_MIN],
    'min int minus one literal becomes real' => ['-9223372036854775809', 'real', -9.223372036854776E+18],
    'positive overflow blob literal becomes real' => [new SQLiteBlobValue('9223372036854775808'), 'real', 9.223372036854776E+18],
    'negative overflow blob literal becomes real' => [new SQLiteBlobValue('-9223372036854775809'), 'real', -9.223372036854776E+18],
    'overflow decimal integer-looking value stays real' => ['9223372036854775808.0', 'real', 9.223372036854776E+18],
    'overflow exponent integer-looking value stays real' => ['9.223372036854776e18', 'real', 9.223372036854776E+18],
    'in range exponent integer-looking value becomes integer' => ['9.0e2', 'integer', 900],
    'in range decimal integer-looking value becomes integer' => ['900.0', 'integer', 900],
    'overflow text with trailing spaces becomes real' => [" 9223372036854775808 \n", 'real', 9.223372036854776E+18],
    'malformed overflow-looking text stays text' => ['9223372036854775808x', 'text', '9223372036854775808x'],
    'hex-looking overflow text stays text' => ['0x9223372036854775808', 'text', '0x9223372036854775808'],
];

foreach ($coercionCases as $name => [$value, $storage, $coerced]) {
    $tests['vdbe numeric overflow coercion ' . $name] = static function (TestRunner $t) use ($value, $storage, $coerced): void {
        $pair = SQLiteAffinityComparison::coercedPair(0, $value, 'NUMERIC', 'NONE');
        $t->same($storage, $pair['rightStorageClass']);
        $t->same($coerced, $pair['right']);
    };
}

$compareCases = [
    'overflow text orders above max integer' => [['9223372036854775808'], [9223372036854775807], 'C', 1],
    'max integer orders below overflow text' => [[9223372036854775807], ['9223372036854775808'], 'C', -1],
    'overflow blob orders above max integer' => [[new SQLiteBlobValue('9223372036854775808')], [9223372036854775807], 'C', 1],
    'max integer equals max integer text' => [['9223372036854775807'], [9223372036854775807], 'C', 0],
    'min integer equals min integer text' => [['-9223372036854775808'], [PHP_INT_MIN], 'C', 0],
    'negative overflow text compares equal to min integer like sqlite double rounding' => [['-9223372036854775809'], [PHP_INT_MIN], 'C', 0],
    'positive overflow decimal orders above max integer' => [['9223372036854775808.0'], [9223372036854775807], 'C', 1],
    'positive overflow exponent orders above max integer' => [['9.223372036854776e18'], [9223372036854775807], 'C', 1],
    'larger overflow exponent orders above max integer' => [['1.0e19'], [9223372036854775807], 'C', 1],
    'negative larger overflow exponent orders below max integer' => [['-1.0e19'], [9223372036854775807], 'C', -1],
    'overflow text remains text without affinity' => [['9223372036854775808'], [9223372036854775807], 'A', 1],
    'overflow text remains text with blob affinity' => [['9223372036854775808'], [9223372036854775807], 'B', 1],
    'overflow text becomes text with text affinity' => [['9223372036854775808'], ['9223372036854775807'], 'G', 1],
    'numeric overflow second key breaks first key tie' => [['autoload', '9223372036854775808'], ['autoload', 9223372036854775807], 'GC', 1],
    'numeric overflow descending reverses second key' => [['autoload', '9223372036854775808'], ['autoload', 9223372036854775807], 'GC', -1, [false, true]],
    'padded overflow orders above max integer' => [['0009223372036854775808'], [9223372036854775807], 'C', 1],
    'trimmed overflow orders above max integer' => [["\t9223372036854775808 "], [9223372036854775807], 'C', 1],
    'malformed overflow text orders by storage class above integer' => [['9223372036854775808x'], [9223372036854775807], 'C', 1],
    'overflow real compares equal to same overflow text' => [[9.223372036854776E+18], ['9223372036854775808'], 'C', 0],
    'overflow real compares above max integer' => [[9.223372036854776E+18], [9223372036854775807], 'C', 1],
];

foreach ($compareCases as $name => $case) {
    $tests['vdbe numeric overflow compare ' . $name] = static function (TestRunner $t) use ($case): void {
        [$left, $right, $affinity, $expected] = $case;
        $descending = $case[4] ?? [];
        $comparison = SQLiteVdbeSortCompare::compareRecords($left, $right, $affinity, [], $descending);
        $t->same($expected, $comparison <=> 0);
    };
}

$tests['vdbe numeric overflow sort orders copied option priorities'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'siteurl', 'priority' => '9223372036854775808'],
        ['option_id' => 2, 'option_name' => 'home', 'priority' => 9223372036854775807],
        ['option_id' => 3, 'option_name' => 'blogname', 'priority' => '900.0'],
        ['option_id' => 4, 'option_name' => 'stylesheet', 'priority' => new SQLiteBlobValue('9223372036854775808')],
        ['option_id' => 5, 'option_name' => 'template', 'priority' => null],
    ];

    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['priority', 'option_id'], 'CD');
    $t->same([5, 3, 2, 1, 4], array_column($ordered, 'option_id'));
};

$tests['vdbe numeric overflow sort preserves stable ties for equal overflow reals'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'priority' => '9223372036854775808'],
        ['option_id' => 2, 'priority' => '9223372036854775809'],
        ['option_id' => 3, 'priority' => 9223372036854775807],
    ];

    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['priority'], 'C');
    $t->same([3, 1, 2], array_column($ordered, 'option_id'));
};

$tests['vdbe numeric overflow sort applies descending after real integer boundary comparison'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'priority' => '9223372036854775808'],
        ['option_id' => 2, 'priority' => 9223372036854775807],
        ['option_id' => 3, 'priority' => '900'],
    ];

    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['priority'], 'C', [], [true]);
    $t->same([1, 2, 3], array_column($ordered, 'option_id'));
};

$tests['vdbe numeric overflow sort keeps text affinity lexical at boundary'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'priority' => '9223372036854775808'],
        ['option_id' => 2, 'priority' => 9223372036854775807],
        ['option_id' => 3, 'priority' => '10'],
    ];

    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['priority'], 'G');
    $t->same([3, 2, 1], array_column($ordered, 'option_id'));
};

$tests['vdbe numeric overflow compare applies nocase only after text affinity'] = static function (TestRunner $t): void {
    $comparison = SQLiteVdbeSortCompare::compareRecords(['PLUGIN_9223372036854775808'], ['plugin_9223372036854775808'], 'G', ['NOCASE']);
    $t->same(0, $comparison);
};

$tests['vdbe numeric overflow comparison still preserves null ordering'] = static function (TestRunner $t): void {
    $comparison = SQLiteVdbeSortCompare::compareRecords([null], ['9223372036854775808'], 'C');
    $t->same(-1, $comparison);
};

$tests['vdbe numeric overflow comparison rejects unsupported wide arrays'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAffinityComparison::coercedPair([], '9223372036854775808', 'NUMERIC', 'NONE'));
};

return $tests;
