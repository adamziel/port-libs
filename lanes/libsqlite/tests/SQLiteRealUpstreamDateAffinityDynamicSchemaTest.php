<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealDateAffinityDynamicCorpusPlan;

$tests = [];

foreach (range(0, 127) as $millisecond) {
    $tests[sprintf('real upstream date dynamic unixepoch fractional millisecond %03d', $millisecond)] = static function (TestRunner $t) use ($millisecond): void {
        $case = SQLiteRealDateAffinityDynamicCorpusPlan::unixepochFractionCase($millisecond);

        $t->same(sprintf('date.test date-2.2c-%d', $millisecond), $case['upstream']);
        $t->same(sprintf("strftime('%%H:%%M:%%f',1237962480.%03d,'unixepoch')", $millisecond), $case['expr']);
        $t->same(sprintf('06:28:00.%03d', $millisecond), $case['result']);
        $t->same(12, strlen($case['result']));
        $t->same(true, str_starts_with($case['result'], '06:28:00.'));
    };
}

$modifierCases = [
    ['2003-10-22', 'weekday 0', '2003-10-26', 'date.test date-2.3'],
    ['2003-10-22', 'weekday 1', '2003-10-27', 'date.test date-2.4'],
    ['2003-10-22', 'weekday 2', '2003-10-28', 'date.test date-2.5'],
    ['2003-10-22', 'weekday 3', '2003-10-22', 'date.test date-2.6'],
    ['2003-10-22', 'weekday 4', '2003-10-23', 'date.test date-2.7'],
    ['2003-10-22', 'weekday 5', '2003-10-24', 'date.test date-2.8'],
    ['2003-10-22', 'weekday 6', '2003-10-25', 'date.test date-2.9'],
    ['2003-10-22', 'weekday 7', null, 'date.test date-2.10'],
    ['2003-10-22', 'weekday 5.5', null, 'date.test date-2.11'],
    ['2003-10-22', 'start of month', '2003-10-01', 'date.test date-2.13'],
    ['2003-10-22', 'start of year', '2003-01-01', 'date.test date-2.14'],
    ['2003-10-22', 'start of day', '2003-10-22', 'date.test date-2.15'],
    ['2003-10-22', 'start of', null, 'date.test date-2.15a'],
    ['2003-10-22', 'start of bogus', null, 'date.test date-2.15b'],
    ['2003-10-22', '1 day', '2003-10-23', 'date.test date-2.17'],
    ['2003-10-22', '+1 day', '2003-10-23', 'date.test date-2.18'],
    ['2003-10-22', '-1 day', '2003-10-21', 'date.test date-2.20'],
    ['2003-10-22', '+60 seconds', '2003-10-22', 'date.test date-2.45'],
    ['2003-10-22', 'nonsense', null, 'date.test date-2.51'],
];

foreach ($modifierCases as [$base, $modifier, $expected, $upstream]) {
    $tests['real upstream date modifier ' . $upstream] = static function (TestRunner $t) use ($base, $modifier, $expected, $upstream): void {
        $case = SQLiteRealDateAffinityDynamicCorpusPlan::dateModifierCase($base, $modifier, $upstream);

        $t->same($upstream, $case['upstream']);
        $t->same("date('{$base}','{$modifier}')", $case['expr']);
        $t->same($expected, $case['result']);
        $t->same($expected !== null, $case['deterministic']);
    };
}

$schemaCases = [
    ['date', ['2017-07-20'], 'check', true, null, 'date2.test date2-100'],
    ['date', ['now'], 'check', false, 'non-deterministic use of date() in a CHECK constraint', 'date2.test date2-110'],
    ['date', ['2017-08-01'], 'check', true, null, 'date2.test date2-130'],
    ['date', [], 'generated', false, 'non-deterministic use of date() in a generated column', 'date2.test date2-140'],
    ['date', ['2017-07-20'], 'index', true, null, 'date2.test date2-200'],
    ['date', ['now'], 'index', false, 'non-deterministic use of date() in an index', 'date2.test date2-210'],
    ['datetime', [2457936.5], 'index', true, null, 'date2.test date2-320'],
    ['datetime', ['now'], 'index', false, 'non-deterministic use of datetime() in an index', 'date2.test date2-310'],
    ['datetime', ['2017-07-20', '+10 days'], 'index', true, null, 'date2.test date2-500'],
    ['datetime', ['2017-07-20', 'localtime'], 'index', false, 'non-deterministic use of datetime() in an index', 'date2.test date2-510'],
    ['datetime', ['2017-07-20', 'utc'], 'index', false, 'non-deterministic use of datetime() in an index', 'date2.test date2-520'],
    ['julianday', ['now'], 'check', false, 'non-deterministic use of julianday() in a CHECK constraint', 'date2.test date2-600'],
    ['julianday', ['1970-01-01'], 'check', true, null, 'date2.test date2-601'],
    ['julianday', ['now'], 'index', false, 'non-deterministic use of julianday() in an index', 'date2.test date2-610'],
    ['julianday', ['1970-01-01'], 'index', true, null, 'date2.test date2-611'],
    ['julianday', ['now'], 'generated', false, 'non-deterministic use of julianday() in a generated column', 'date2.test date3-620'],
];

foreach ($schemaCases as [$function, $arguments, $context, $ok, $error, $upstream]) {
    $tests['real upstream date2 schema determinism ' . $upstream] = static function (TestRunner $t) use ($function, $arguments, $context, $ok, $error, $upstream): void {
        $case = SQLiteRealDateAffinityDynamicCorpusPlan::dateSchemaUse($function, $arguments, $context, $upstream);

        $t->same($upstream, $case['upstream']);
        $t->same($function, $case['function']);
        $t->same($context, $case['context']);
        $t->same($arguments, $case['arguments']);
        $t->same($ok, $case['ok']);
        $t->same($error, $case['error']);
    };
}

$tests['real upstream affinity2 inserted storage classes'] = static function (TestRunner $t): void {
    $typed = SQLiteRealDateAffinityDynamicCorpusPlan::affinity2InsertedRows(
        [
            ['xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
            ['xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
            ['xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
        ],
        ['xi' => 'INTEGER', 'xr' => 'REAL', 'xb' => 'NONE', 'xn' => 'NUMERIC', 'xt' => 'TEXT']
    );

    $t->same(['integer', 'integer', 'integer'], array_column(array_column($typed, 'xi'), 'typeof'));
    $t->same(['real', 'real', 'real'], array_column(array_column($typed, 'xr'), 'typeof'));
    $t->same(['integer', 'text', 'text'], array_column(array_column($typed, 'xb'), 'typeof'));
    $t->same(['integer', 'integer', 'integer'], array_column(array_column($typed, 'xn'), 'typeof'));
    $t->same(['text', 'text', 'text'], array_column(array_column($typed, 'xt'), 'typeof'));
    $t->same([1, 2, 3], array_column(array_column($typed, 'xi'), 'value'));
    $t->same([1.0, 2.0, 3.0], array_column(array_column($typed, 'xr'), 'value'));
    $t->same([1, '2', '03'], array_column(array_column($typed, 'xb'), 'value'));
    $t->same([1, 2, 3], array_column(array_column($typed, 'xn'), 'value'));
    $t->same(['1', '2', '03'], array_column(array_column($typed, 'xt'), 'value'));
};

$comparisonCases = [
    [1, '1', '==', 'INTEGER', 'TEXT', true, 'integer', 'integer', 'affinity2.test affinity2-200 row1 xi==xt'],
    [2, '2', '==', 'INTEGER', 'NONE', true, 'integer', 'integer', 'affinity2.test affinity2-200 row2 xi==xb'],
    [3, '03', '==', 'NUMERIC', 'TEXT', true, 'integer', 'integer', 'affinity2.test affinity2-220 row3 xn==xt'],
    ['03', 3, '==', 'NONE', 'NONE', false, 'text', 'integer', 'affinity2.test affinity2-300 row3 xt==+xi'],
    ['03', 3, '==', 'TEXT', 'INTEGER', true, 'integer', 'integer', 'affinity2.test affinity2-300 row3 xt==xi'],
    ['03', '03', '==', 'TEXT', 'NONE', true, 'text', 'text', 'affinity2.test affinity2-300 row3 xt==xb'],
    [0, '-1', '>', 'NUMERIC', 'NONE', true, 'integer', 'integer', 'affinity2.test affinity2-410 cast numeric > c1'],
    [-1, '-1', '==', 'NONE', 'TEXT', true, 'text', 'text', 'affinity2.test affinity2-500 unique text unary comparison'],
];

foreach ($comparisonCases as [$left, $right, $operator, $leftAffinity, $rightAffinity, $expected, $leftStorage, $rightStorage, $upstream]) {
    $tests['real upstream affinity2 comparison ' . $upstream] = static function (TestRunner $t) use ($left, $right, $operator, $leftAffinity, $rightAffinity, $expected, $leftStorage, $rightStorage, $upstream): void {
        $case = SQLiteRealDateAffinityDynamicCorpusPlan::affinity2Comparison($left, $right, $operator, $leftAffinity, $rightAffinity, $upstream);

        $t->same($upstream, $case['upstream']);
        $t->same($expected, $case['result']);
        $t->same($leftStorage, $case['leftStorageClass']);
        $t->same($rightStorage, $case['rightStorageClass']);
    };
}

$guardCases = [
    'rejects negative millisecond' => static fn (): array => SQLiteRealDateAffinityDynamicCorpusPlan::unixepochFractionCase(-1),
    'rejects overflow millisecond' => static fn (): array => SQLiteRealDateAffinityDynamicCorpusPlan::unixepochFractionCase(1000),
    'rejects unsupported date schema function' => static fn (): array => SQLiteRealDateAffinityDynamicCorpusPlan::dateSchemaUse('random', [], 'check', 'guard'),
    'rejects unsupported date schema context' => static fn (): array => SQLiteRealDateAffinityDynamicCorpusPlan::dateSchemaUse('date', [], 'view', 'guard'),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream date affinity dynamic guard ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
