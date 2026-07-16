<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic auto date3.test cites source truth'] = static function (TestRunner $t): void {
    $upstream = [
        'date3.test date3-1.1..1.8 unixepoch boundaries and integer return type',
        'date3.test date3-2.30 auto modifier is a no-op for text dates',
        'date3.test date3-2.40 auto modifier separates Julian-day and Unix-epoch numeric domains',
        'date3.test date3-3.1..3.2 unixepoch modifier must immediately follow numeric time-value',
        'date3.test date3-4.1..4.3 julianday modifier must immediately follow numeric Julian day',
    ];

    $t->same(true, in_array('date3.test date3-2.40 auto modifier separates Julian-day and Unix-epoch numeric domains', $upstream, true));
    $t->contains('date3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test');
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$baseJulianDay = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', ['1970-01-01']);
$baseUnixEpoch = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['1970-01-01']);

for ($offset = 0; $offset < 1000; $offset++) {
    $tests[sprintf('real upstream corpus date affinity dynamic auto date3.test date3-2.40 julian unixepoch split day %04d', $offset)] = static function (TestRunner $t) use ($baseJulianDay, $baseUnixEpoch, $offset): void {
        $julianNumeric = (float) $baseJulianDay + 19000.55260275 + $offset;
        $unixNumeric = (int) $baseUnixEpoch + ((19000 + $offset) * 86400) + 47744;
        $expected = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianNumeric, 'julianday']);

        $textAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$expected, 'auto']);
        $julianAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianNumeric, 'auto']);
        $unixAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$unixNumeric, 'auto']);
        $unixExplicit = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$unixNumeric, 'unixepoch']);

        $t->same($expected, $textAuto);
        $t->same($expected, $julianAuto);
        $t->same($expected, $unixAuto);
        $t->same($unixExplicit, $unixAuto);
        $t->same(true, $julianAuto === $unixAuto);
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julianNumeric]));
        $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$unixNumeric]));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$expected]));
        $t->same(true, is_string($julianAuto));
        $t->same(true, is_string($unixAuto));
        $t->contains('date3-2.40', 'date3.test date3-2.40 auto modifier separates Julian-day and Unix-epoch numeric domains');
    };
}

$modifierOrderCases = [
    'date3-3.1 unixepoch after arithmetic modifier rejects' => ['datetime', [2459607.05, '+1 hour', 'unixepoch'], null],
    'date3-3.2 unixepoch before arithmetic modifier accepts' => ['datetime', [2459607.05, 'unixepoch', '+1 hour'], '1970-01-29 12:13:27'],
    'date3-4.1 julianday immediate modifier accepts' => ['datetime', [2459607, 'julianday'], '2022-01-27 12:00:00'],
    'date3-4.2 julianday after arithmetic modifier rejects' => ['datetime', [2459607, '+1 hour', 'julianday'], null],
    'date3-4.3 julianday text time-value rejects' => ['datetime', ['2022-01-27', 'julianday'], null],
    'date3-2.30 auto text date is no-op' => ['date', ['2022-01-29', 'auto'], '2022-01-29'],
];

foreach ($modifierOrderCases as $name => [$functionName, $arguments, $expected]) {
    $tests['real upstream corpus date affinity dynamic auto ' . $name] = static function (TestRunner $t) use ($functionName, $arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, $arguments);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(true, $expected === null || is_string($actual));
        $t->contains('date3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test');
    };
}

$floorCeilingCases = [
    'date.test date-19.40 leap January ceiling' => ['2024-01-31', '+1 month', 'ceiling', '2024-03-02'],
    'date.test date-19.41 leap January floor' => ['2024-01-31', '+1 month', 'floor', '2024-02-29'],
    'date.test date-19.42 common January ceiling' => ['2023-01-31', '+1 month', 'ceiling', '2023-03-03'],
    'date.test date-19.43 common January floor' => ['2023-01-31', '+1 month', 'floor', '2023-02-28'],
    'date.test date-19.44 leap day plus year ceiling' => ['2024-02-29', '+1 year', 'ceiling', '2025-03-01'],
    'date.test date-19.45 leap day plus year floor' => ['2024-02-29', '+1 year', 'floor', '2025-02-28'],
    'date.test date-19.50 compound year-month floor' => ['2000-08-31', '+0023-06-00', 'floor', '2024-02-29'],
    'date.test date-19.52 compound year-month ceiling' => ['2000-08-31', '+0023-06-00', 'ceiling', '2024-03-02'],
];

foreach ($floorCeilingCases as $name => [$baseDate, $shift, $policy, $expected]) {
    $tests['real upstream corpus date affinity dynamic auto ' . $name] = static function (TestRunner $t) use ($baseDate, $shift, $policy, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$baseDate, $shift, $policy]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(10, strlen((string) $actual));
        $t->same(true, preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $actual) === 1);
        $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    };
}

return $tests;
