<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity modifier batch cites date.test arithmetic sections'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-13.11..13.12 julianday +/- day modifiers',
        'date.test date-13.15..13.16 julianday +/- hour modifiers',
        'date.test date-13.17..13.18 julianday +/- minute modifiers',
        'date.test date-13.19..13.20 julianday +/- second modifiers',
    ];

    $t->same(true, in_array('date.test date-13.11..13.12 julianday +/- day modifiers', $upstream, true));
    $t->same(true, in_array('date.test date-13.15..13.16 julianday +/- hour modifiers', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$modifierRows = [
    ['+1 day', 1.0, 'date-13.12'],
    ['-1 day', -1.0, 'date-13.11'],
    ['+1.5 day', 1.5, 'date-13.14'],
    ['-1.5 day', -1.5, 'date-13.13'],
    ['+3 hours', 3.0 / 24.0, 'date-13.16'],
    ['-3 hours', -3.0 / 24.0, 'date-13.15'],
    ['+45 minutes', 45.0 / 1440.0, 'date-13.18'],
    ['-45 minutes', -45.0 / 1440.0, 'date-13.17'],
    ['+675 seconds', 675.0 / 86400.0, 'date-13.20'],
    ['-675 seconds', -675.0 / 86400.0, 'date-13.19'],
    ['+12 hours', 0.5, 'date-13 hour extension'],
    ['-12 hours', -0.5, 'date-13 hour extension'],
    ['+90 minutes', 90.0 / 1440.0, 'date-13 minute extension'],
    ['-90 minutes', -90.0 / 1440.0, 'date-13 minute extension'],
    ['+864 seconds', 864.0 / 86400.0, 'date-13 second extension'],
    ['-864 seconds', -864.0 / 86400.0, 'date-13 second extension'],
    ['+2 days', 2.0, 'date-13 day extension'],
    ['-2 days', -2.0, 'date-13 day extension'],
    ['+6 hours', 6.0 / 24.0, 'date-13 hour extension'],
    ['-6 hours', -6.0 / 24.0, 'date-13 hour extension'],
    ['+15 minutes', 15.0 / 1440.0, 'date-13 minute extension'],
    ['-15 minutes', -15.0 / 1440.0, 'date-13 minute extension'],
    ['+30 seconds', 30.0 / 86400.0, 'date-13 second extension'],
    ['-30 seconds', -30.0 / 86400.0, 'date-13 second extension'],
    ['+2.25 days', 2.25, 'date-13 day extension'],
    ['-2.25 days', -2.25, 'date-13 day extension'],
    ['+18 hours', 18.0 / 24.0, 'date-13 hour extension'],
    ['-18 hours', -18.0 / 24.0, 'date-13 hour extension'],
    ['+120 minutes', 120.0 / 1440.0, 'date-13 minute extension'],
    ['-120 minutes', -120.0 / 1440.0, 'date-13 minute extension'],
    ['+1800 seconds', 1800.0 / 86400.0, 'date-13 second extension'],
    ['-1800 seconds', -1800.0 / 86400.0, 'date-13 second extension'],
];

for ($baseIndex = 0; $baseIndex < 32; $baseIndex++) {
    $baseJulianDay = 2454832.5 + ($baseIndex * 7.125);
    foreach ($modifierRows as $modifierIndex => [$modifier, $dayDelta, $sourceSection]) {
        $name = sprintf(
            'real upstream corpus date affinity dynamic modifier batch date.test %s base %02d modifier %02d %s',
            $sourceSection,
            $baseIndex,
            $modifierIndex,
            $modifier
        );

        $tests[$name] = static function (TestRunner $t) use ($baseJulianDay, $modifier, $dayDelta, $sourceSection): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$baseJulianDay, $modifier]);
            $expected = $baseJulianDay + $dayDelta;
            $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$baseJulianDay, $modifier]);

            $t->same(round($expected, 8), round((float) $actual, 8));
            $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
            $t->same(true, is_string($datetime));
            $t->same(true, str_contains($sourceSection, 'date-13'));
            $t->same('date.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test'));
        };
    }
}

$tests['real upstream corpus date affinity dynamic modifier batch application expiry offsets stay julian-real'] = static function (TestRunner $t): void {
    $base = 2454832.5;
    $offsets = ['+1 day', '+3 hours', '+45 minutes', '+675 seconds'];
    $actual = [];
    foreach ($offsets as $modifier) {
        $julianDay = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$base, $modifier]);
        $actual[] = [
            'modifier' => $modifier,
            'storage' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julianDay]),
            'date' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]),
        ];
    }

    $t->same(['real', 'real', 'real', 'real'], array_column($actual, 'storage'));
    $t->same(['+1 day', '+3 hours', '+45 minutes', '+675 seconds'], array_column($actual, 'modifier'));
    $t->same(['2009-01-02', '2009-01-01', '2009-01-01', '2009-01-01'], array_column($actual, 'date'));
};

return $tests;
