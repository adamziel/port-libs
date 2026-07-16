<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sameCases = [
    'abs null propagates' => ['abs', [null], null],
    'abs text integer coerces' => ['abs', ['-12'], 12],
    'abs real text coerces' => ['abs', ['-3.5'], 3.5],
    'round default half away from zero positive' => ['round', [2.5], 3.0],
    'round default half away from zero negative' => ['round', [-2.5], -3.0],
    'round positive precision' => ['round', [12.345, 2], 12.35],
    'round negative precision clamps to zero' => ['round', [12.345, -2], 12.0],
    'sign negative integer' => ['sign', [-10], -1],
    'sign zero integer' => ['sign', [0], 0],
    'sign positive real' => ['sign', [4.25], 1],
    'sign non numeric text returns null' => ['sign', ['cache'], null],
    'ceil positive real' => ['ceil', [1.2], 2.0],
    'ceil negative real' => ['ceil', [-1.8], -1.0],
    'ceiling alias' => ['ceiling', [2.1], 3.0],
    'floor positive real' => ['floor', [1.8], 1.0],
    'floor negative real' => ['floor', [-1.2], -2.0],
    'trunc positive real' => ['trunc', [1.8], 1.0],
    'trunc negative real' => ['trunc', [-1.8], -1.0],
    'sqrt perfect square' => ['sqrt', [81], 9.0],
    'sqrt negative returns null' => ['sqrt', [-1], null],
    'ln one' => ['ln', [1], 0.0],
    'ln nonpositive returns null' => ['ln', [0], null],
    'log10 thousand' => ['log10', [1000], 3.0],
    'log single argument is base ten' => ['log', [100], 2.0],
    'log explicit base' => ['log', [2, 8], 3.0],
    'log invalid base one returns null' => ['log', [1, 8], null],
    'log invalid value returns null' => ['log', [2, -8], null],
    'log2 power' => ['log2', [8], 3.0],
    'pow integer exponent' => ['pow', [2, 5], 32.0],
    'power alias' => ['power', [9, 0.5], 3.0],
    'mod positive' => ['mod', [7, 3], 1.0],
    'mod negative dividend' => ['mod', [-7, 3], -1.0],
    'mod zero divisor returns null' => ['mod', [7, 0], null],
    'acos one' => ['acos', [1], 0.0],
    'acos out of range returns null' => ['acos', [2], null],
    'asin zero' => ['asin', [0], 0.0],
    'asin out of range returns null' => ['asin', [-2], null],
    'atan zero' => ['atan', [0], 0.0],
    'atan2 zero pair' => ['atan2', [0, 1], 0.0],
    'cos zero' => ['cos', [0], 1.0],
    'sin zero' => ['sin', [0], 0.0],
    'tan zero' => ['tan', [0], 0.0],
    'date unixepoch' => ['date', [0, 'unixepoch'], '1970-01-01'],
    'time unixepoch' => ['time', [3661, 'unixepoch'], '01:01:01'],
    'datetime unixepoch' => ['datetime', [0, 'unixepoch'], '1970-01-01 00:00:00'],
    'unixepoch text timestamp' => ['unixepoch', ['1970-01-02 00:00:00'], 86400],
    'strftime year month day' => ['strftime', ['%Y-%m-%d', '1970-01-02 03:04:05'], '1970-01-02'],
    'strftime hour minute second' => ['strftime', ['%H:%M:%S', '1970-01-02 03:04:05'], '03:04:05'],
    'date start of month' => ['date', ['2024-02-29 12:34:56', 'start of month'], '2024-02-01'],
    'date start of year' => ['date', ['2024-02-29 12:34:56', 'start of year'], '2024-01-01'],
    'date weekday already same' => ['date', ['2024-03-03', 'weekday 0'], '2024-03-03'],
    'date weekday advances' => ['date', ['2024-03-04', 'weekday 0'], '2024-03-10'],
    'datetime adds day' => ['datetime', ['2024-01-31 10:00:00', '+1 day'], '2024-02-01 10:00:00'],
    'datetime subtracts hours' => ['datetime', ['2024-01-31 10:00:00', '-2 hours'], '2024-01-31 08:00:00'],
    'datetime null modifier returns null' => ['datetime', ['2024-01-31 10:00:00', null], null],
    'date null input returns null' => ['date', [null], null],
    'strftime null format returns null' => ['strftime', [null, '2024-01-01'], null],
    'sqlite version current target' => ['sqlite_version', [], '3.50.0'],
    'compile option get first' => ['sqlite_compileoption_get', [0], 'ATOMIC_INTRINSICS=1'],
    'compile option get unknown returns null' => ['sqlite_compileoption_get', [999], null],
    'compile option used accepts SQLITE prefix' => ['sqlite_compileoption_used', ['SQLITE_ENABLE_FTS5'], 1],
    'compile option used accepts assignment name' => ['sqlite_compileoption_used', ['MAX_VARIABLE_NUMBER=123'], 1],
    'compile option used rejects absent option' => ['sqlite_compileoption_used', ['ENABLE_JSON1'], 0],
    'planner likely preserves value' => ['likely', ['cache'], 'cache'],
    'planner unlikely preserves null' => ['unlikely', [null], null],
    'planner likelihood preserves integer' => ['likelihood', [7, 0.75], 7],
];

foreach ($sameCases as $name => [$function, $arguments, $expected]) {
    $tests['release suite parity current next18 scalar ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments));
    };
}

$tests['release suite parity current next18 pi is sqlite math constant'] = static function (TestRunner $t): void {
    $t->same(M_PI, SQLiteCoreScalarFunction::sqlFunctionArguments('pi', []));
};

$tests['release suite parity current next18 randomblob length is coerced'] = static function (TestRunner $t): void {
    $blob = SQLiteCoreScalarFunction::sqlFunctionArguments('randomblob', ['4']);

    $t->same(4, strlen($blob->bytes));
};

return $tests;
