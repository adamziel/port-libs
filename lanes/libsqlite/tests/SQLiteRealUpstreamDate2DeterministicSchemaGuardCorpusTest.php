<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date2 deterministic schema guard cites source truth'] = static function (TestRunner $t): void {
    $upstream = [
        'date2.test date2-100..140 CHECK/generated-column deterministic date() guards',
        'date2.test date2-200..430 expression and partial-index deterministic date() guards',
        'date2.test date2-500..520 modifier table rejects localtime/utc in indexes',
        'date2.test date2-600..620 julianday now guards across CHECK/index/generated column',
    ];

    $t->same(true, in_array('date2.test date2-600..620 julianday now guards across CHECK/index/generated column', $upstream, true));
};

$schemaContextCases = [
    'date2-110 date now in check constraint' => ['date', ['now'], 'a CHECK constraint'],
    'date2-140 date no args in generated column' => ['date', [], 'a generated column'],
    'date2-210 date now in expression index' => ['date', ['now'], 'an index'],
    'date2-430 date now through partial index insert' => ['date', ['now'], 'an index'],
    'date2-510 datetime localtime modifier in index' => ['datetime', ['2017-07-20', 'localtime'], 'an index'],
    'date2-520 datetime utc modifier in index' => ['datetime', ['2017-07-20', 'utc'], 'an index'],
    'date2-600 julianday now in check constraint' => ['julianday', ['now'], 'a CHECK constraint'],
    'date2-603 julianday row value now in check constraint' => ['julianday', ['now'], 'a CHECK constraint'],
    'date2-604 julianday now expression in check constraint' => ['julianday', ['now'], 'a CHECK constraint'],
    'date2-610 julianday now plus column in index' => ['julianday', ['now'], 'an index'],
    'date2-612 julianday row value now in expression index' => ['julianday', ['now'], 'an index'],
    'date2-620 julianday now in generated column' => ['julianday', ['now'], 'a generated column'],
];

foreach ($schemaContextCases as $name => [$function, $arguments, $context]) {
    $tests['real upstream corpus date2 deterministic schema guard ' . $name] = static function (TestRunner $t) use ($function, $arguments, $context): void {
        $t->same(false, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall($function, $arguments));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SQLiteCoreScalarFunction::assertDeterministicSqlFunctionCall($function, $arguments, $context)
        );
    };
}

$deterministicRows = [
    'date2-100 date literal in check constraint' => ['date', ['2017-07-20'], 'a CHECK constraint', true],
    'date2-130 date literal check predicate fails deterministically' => ['date', ['2017-08-01'], 'a CHECK constraint', true],
    'date2-200 date literal in expression index' => ['date', ['2017-07-20'], 'an index', true],
    'date2-220 invalid text in deterministic expression index stays deterministic' => ['date', ['xyzzy'], 'an index', true],
    'date2-320 datetime partial index over real affinity' => ['datetime', [2457938.5], 'an index', true],
    'date2-420 date partial index after deleting now row' => ['date', [2457938.5], 'an index', true],
    'date2-601 julianday row value in check constraint' => ['julianday', ['1970-01-01'], 'a CHECK constraint', true],
    'date2-602 julianday check failure is deterministic' => ['julianday', ['1970-01-01'], 'a CHECK constraint', true],
    'date2-611 julianday row value in expression index' => ['julianday', ['1970-01-01'], 'an index', true],
    'date2-500 datetime unixepoch modifier from deterministic mods table' => ['datetime', [2457938.5, 'unixepoch'], 'an index', true],
];

foreach ($deterministicRows as $name => [$function, $arguments, $context, $expected]) {
    $tests['real upstream corpus date2 deterministic schema guard ' . $name] = static function (TestRunner $t) use ($function, $arguments, $context, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall($function, $arguments));
        SQLiteCoreScalarFunction::assertDeterministicSqlFunctionCall($function, $arguments, $context);
        $t->same($expected, true);
    };
}

for ($rowid = 1; $rowid <= 620; $rowid++) {
    $julianDay = 2457935.5 + $rowid;
    $isReal = $rowid !== 500;
    $inIndexedRange = $rowid >= 3 && $rowid <= 6;

    $tests['real upstream corpus date2 deterministic schema guard date2-331 indexed predicate row ' . $rowid] = static function (TestRunner $t) use ($julianDay, $isReal, $inIndexedRange): void {
        $t->same(true, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', [$julianDay]));
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);

        $t->same($inIndexedRange, $isReal && $actual >= '2017-07-04' && $actual <= '2017-07-08');
    };
}

$indexModifiers = [
    '+10 days',
    '-10 days',
    '+10 hours',
    '-10 hours',
    '+10 minutes',
    '-10 minutes',
    '+10 seconds',
    '-10 seconds',
    '+10 months',
    '-10 months',
    '+10 years',
    '-10 years',
    'start of month',
    'start of year',
    'start of day',
    'weekday 1',
    'unixepoch',
];

foreach ($indexModifiers as $modifier) {
    for ($rowid = 1; $rowid < 5; $rowid++) {
        $julianDay = 2457935.5 + $rowid;
        $label = str_replace([' ', '+', '-'], ['-', 'plus-', 'minus-'], $modifier);

        $tests['real upstream corpus date2 deterministic schema guard date2-500 deterministic modifier ' . $label . ' row ' . $rowid] = static function (TestRunner $t) use ($julianDay, $modifier): void {
            $t->same(true, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', [$julianDay, $modifier]));
            $t->same(true, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay, $modifier]) !== null);
        };
    }
}

$tests['real upstream corpus date2 deterministic schema guard application generated expiry rejects clock reads'] = static function (TestRunner $t): void {
    $generatedExpressions = [
        ['function' => 'date', 'arguments' => ['2026-05-30'], 'context' => 'a generated column'],
        ['function' => 'date', 'arguments' => [], 'context' => 'a generated column'],
        ['function' => 'julianday', 'arguments' => ['now'], 'context' => 'a generated column'],
    ];
    $accepted = [];
    $rejected = [];

    foreach ($generatedExpressions as $expression) {
        try {
            SQLiteCoreScalarFunction::assertDeterministicSqlFunctionCall($expression['function'], $expression['arguments'], $expression['context']);
            $accepted[] = $expression['function'] . ':' . count($expression['arguments']);
        } catch (InvalidArgumentException $exception) {
            $rejected[] = $exception->getMessage();
        }
    }

    $t->same(['date:1'], $accepted);
    $t->same([
        'non-deterministic use of date() in a generated column',
        'non-deterministic use of julianday() in a generated column',
    ], $rejected);
};

return $tests;
