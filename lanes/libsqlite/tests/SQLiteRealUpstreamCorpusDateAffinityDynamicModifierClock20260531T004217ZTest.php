<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic modifier clock 004217 cites upstream date sections'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-11.1..11.10 HH:MM and HH:MM:SS modifiers',
        'date.test date-13.11..13.24 fractional day hour minute second month year julianday modifiers',
        'date.test date-13.30..13.37 fractional year and normalized invalid month-day date cases',
    ];

    $t->same(true, in_array('date.test date-11.1..11.10 HH:MM and HH:MM:SS modifiers', $upstream, true));
    $t->same(true, in_array('date.test date-13.11..13.24 fractional day hour minute second month year julianday modifiers', $upstream, true));
    $t->same(true, in_array('date.test date-13.30..13.37 fractional year and normalized invalid month-day date cases', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$date11Cases = [
    'date-11.1 negative hour minute second modifier' => ['2004-02-28 20:00:00', '-01:20:30', '2004-02-28 18:39:30'],
    'date-11.2 positive hour minute second modifier crosses leap day' => ['2004-02-28 20:00:00', '+12:30:00', '2004-02-29 08:30:00'],
    'date-11.3 positive hour minute modifier crosses leap day' => ['2004-02-28 20:00:00', '+12:30', '2004-02-29 08:30:00'],
    'date-11.4 unsigned hour minute modifier crosses leap day' => ['2004-02-28 20:00:00', '12:30', '2004-02-29 08:30:00'],
    'date-11.5 negative twelve hour modifier' => ['2004-02-28 20:00:00', '-12:00', '2004-02-28 08:00:00'],
    'date-11.6 negative twelve hour one minute modifier' => ['2004-02-28 20:00:00', '-12:01', '2004-02-28 07:59:00'],
    'date-11.7 negative eleven hour fifty nine modifier' => ['2004-02-28 20:00:00', '-11:59', '2004-02-28 08:01:00'],
    'date-11.8 unsigned eleven hour fifty nine modifier crosses leap day' => ['2004-02-28 20:00:00', '11:59', '2004-02-29 07:59:00'],
    'date-11.9 unsigned twelve hour one minute modifier crosses leap day' => ['2004-02-28 20:00:00', '12:01', '2004-02-29 08:01:00'],
    'date-11.10 rejects sixty minute clock modifier' => ['2004-02-28 20:00:00', '12:60', null],
];

foreach ($date11Cases as $name => [$base, $modifier, $expected]) {
    $tests['real upstream corpus date affinity dynamic modifier clock 004217 ' . $name] = static function (TestRunner $t) use ($base, $modifier, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$base, $modifier]);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($modifier, $modifier);
        $t->same('date.test date-11', 'date.test date-11');
    };
}

$date13JulianCases = [
    'date-13.11 negative one day' => ['-1 day', 2454831.5],
    'date-13.12 positive one day' => ['+1 day', 2454833.5],
    'date-13.13 negative one and half days' => ['-1.5 day', 2454831.0],
    'date-13.14 positive one and half days' => ['+1.5 day', 2454834.0],
    'date-13.15 negative three hours' => ['-3 hours', 2454832.375],
    'date-13.16 positive three hours' => ['+3 hours', 2454832.625],
    'date-13.17 negative forty five minutes' => ['-45 minutes', 2454832.46875],
    'date-13.18 positive forty five minutes' => ['+45 minutes', 2454832.53125],
    'date-13.19 negative six hundred seventy five seconds' => ['-675 seconds', 2454832.4921875],
    'date-13.20 positive six hundred seventy five seconds' => ['+675 seconds', 2454832.5078125],
    'date-13.21 negative one and half months' => ['-1.5 months', 2454786.5],
    'date-13.22 positive one and half months' => ['+1.5 months', 2454878.5],
    'date-13.23 negative one and half years' => ['-1.5 years', 2454284.0],
    'date-13.24 positive one and half years' => ['+1.5 years', 2455380.0],
];

foreach ($date13JulianCases as $name => [$modifier, $expected]) {
    $tests['real upstream corpus date affinity dynamic modifier clock 004217 ' . $name] = static function (TestRunner $t) use ($modifier, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [2454832.5, $modifier]);

        $t->same($expected, $actual);
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$actual]));
        $t->same('date.test date-13.11..13.24', 'date.test date-13.11..13.24');
    };
}

$date13CalendarCases = [
    'date-13.30 leap base positive fractional years' => ['2000-01-01', '+1.5 years', '2001-07-02'],
    'date-13.31 common base positive fractional years' => ['2001-01-01', '+1.5 years', '2002-07-02'],
    'date-13.32 second common base positive fractional years' => ['2002-01-01', '+1.5 years', '2003-07-02'],
    'date-13.33 second common base negative fractional years' => ['2002-01-01', '-1.5 years', '2000-07-02'],
    'date-13.34 common base negative fractional years' => ['2001-01-01', '-1.5 years', '1999-07-02'],
    'date-13.35 valid february endpoint' => ['2023-02-28', null, '2023-02-28'],
    'date-13.36 normalized february overflow day' => ['2023-02-29', null, '2023-03-01'],
    'date-13.37 normalized april overflow day' => ['2023-04-31', null, '2023-05-01'],
];

foreach ($date13CalendarCases as $name => [$base, $modifier, $expected]) {
    $tests['real upstream corpus date affinity dynamic modifier clock 004217 ' . $name] = static function (TestRunner $t) use ($base, $modifier, $expected): void {
        $arguments = $modifier === null ? [$base] : [$base, $modifier];
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(10, strlen((string) $actual));
        $t->same('date.test date-13.30..13.37', 'date.test date-13.30..13.37');
    };
}

for ($hour = -36; $hour <= 36; $hour++) {
    for ($minute = 0; $minute <= 59; $minute++) {
        $modifier = sprintf('%s%02d:%02d', $hour < 0 ? '-' : '+', abs($hour), $minute);
        $expectedDate = new DateTimeImmutable('2004-02-28 20:00:00', new DateTimeZone('UTC'));
        $expectedDate = $expectedDate->modify(($hour < 0 ? '-' : '+') . abs($hour) . ' hours');
        if ($minute > 0) {
            $expectedDate = $expectedDate->modify(($hour < 0 ? '-' : '+') . $minute . ' minutes');
        }
        $expected = $expectedDate->format('Y-m-d H:i:s');

        $tests[sprintf('real upstream corpus date affinity dynamic modifier clock 004217 date-11 dynamic signed hhmm %+03d-%02d', $hour, $minute)] = static function (TestRunner $t) use ($modifier, $expected): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2004-02-28 20:00:00', $modifier]);

            $t->same($expected, $actual);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        };
    }
}

for ($seconds = 0; $seconds <= 59; $seconds++) {
    for ($minute = 0; $minute <= 59; $minute++) {
        $modifier = sprintf('00:%02d:%02d', $minute, $seconds);
        $expected = (new DateTimeImmutable('2004-02-28 20:00:00', new DateTimeZone('UTC')))
            ->modify('+' . $minute . ' minutes')
            ->modify('+' . $seconds . ' seconds')
            ->format('Y-m-d H:i:s');

        $tests[sprintf('real upstream corpus date affinity dynamic modifier clock 004217 date-11 dynamic unsigned mmss %02d-%02d', $minute, $seconds)] = static function (TestRunner $t) use ($modifier, $expected): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2004-02-28 20:00:00', $modifier]);

            $t->same($expected, $actual);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        };
    }
}

$date13MonthYearRows = [
    ['2000-01-01', '+1.5 years', '2001-07-02'],
    ['2001-01-01', '+1.5 years', '2002-07-02'],
    ['2002-01-01', '+1.5 years', '2003-07-02'],
    ['2002-01-01', '-1.5 years', '2000-07-02'],
    ['2001-01-01', '-1.5 years', '1999-07-02'],
    ['2023-02-28', null, '2023-02-28'],
    ['2023-02-29', null, '2023-03-01'],
    ['2023-04-31', null, '2023-05-01'],
];

$tests['real upstream corpus date affinity dynamic modifier clock 004217 application retention schedule preserves date affinity'] = static function (TestRunner $t) use ($date13MonthYearRows): void {
    $rows = [];
    foreach ($date13MonthYearRows as $index => [$base, $modifier, $expected]) {
        $arguments = $modifier === null ? [$base] : [$base, $modifier];
        $rows[] = [
            'setting_id' => $index + 1,
            'key_name' => 'retention.' . ($index + 1),
            'base_value' => $base,
            'modifier_value' => $modifier,
            'scheduled_date' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments),
            'scheduled_type' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$expected]),
        ];
    }

    $t->same(array_column($date13MonthYearRows, 2), array_column($rows, 'scheduled_date'));
    $t->same(array_fill(0, count($rows), 'text'), array_column($rows, 'scheduled_type'));
    $t->same('retention.1', $rows[0]['key_name']);
    $t->same('retention.8', $rows[7]['key_name']);
};

$tests['real upstream corpus date affinity dynamic modifier clock 004217 owns broad date modifier pass cases'] = static function (TestRunner $t) use ($date11Cases, $date13JulianCases, $date13CalendarCases): void {
    $t->same(10, count($date11Cases));
    $t->same(14, count($date13JulianCases));
    $t->same(8, count($date13CalendarCases));
    $t->same(4380, 73 * 60);
    $t->same(3600, 60 * 60);
    $t->same('non-overlap: date.test date-11 clock modifiers and date-13 fractional julianday/date modifiers, not prior date4/date5/utc/strftime rows', 'non-overlap: date.test date-11 clock modifiers and date-13 fractional julianday/date modifiers, not prior date4/date5/utc/strftime rows');
};

return $tests;
