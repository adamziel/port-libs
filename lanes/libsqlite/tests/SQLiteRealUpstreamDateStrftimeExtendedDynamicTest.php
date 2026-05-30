<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date strftime extended cites upstream files'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-3.20..3.37 extended strftime day hour meridiem and ISO weekday specifiers',
        'date.test date-3.40 leading zero preservation for early years',
    ];

    $t->same(true, in_array('date.test date-3.20..3.37 extended strftime day hour meridiem and ISO weekday specifiers', $upstream, true));
    $t->same(true, in_array('date.test date-3.40 leading zero preservation for early years', $upstream, true));
};

$date320337Cases = [
    'date-3.20 space padded day of month' => ['%e', '2023-08-09', ' 9'],
    'date-3.21 ISO date and time aliases' => ['%F %T', '2023-08-09 01:23', '2023-08-09 01:23:00'],
    'date-3.22 space padded 24 hour' => ['%k', '2023-08-09 04:59:59', ' 4'],
    'date-3.23 12 hour lower meridiem before noon' => ['%I%P', '2023-08-09 11:59:59', '11am'],
    'date-3.24 noon upper meridiem' => ['%I%p', '2023-08-09 12:00:00', '12PM'],
    'date-3.25 noon lower meridiem with fraction' => ['%I%P', '2023-08-09 12:59:59.9', '12pm'],
    'date-3.26 afternoon upper meridiem' => ['%I%p', '2023-08-09 13:00:00', '01PM'],
    'date-3.27 evening lower meridiem' => ['%I%P', '2023-08-09 23:59:59', '11pm'],
    'date-3.28 midnight upper meridiem' => ['%I%p', '2023-08-09 00:00:00', '12AM'],
    'date-3.29 space padded 12 hour alias' => ['%l:%M%P', '2023-08-09 13:00:00', ' 1:00pm'],
    'date-3.30 ISO date plus hour minute alias' => ['%F %R', '2023-08-09 12:34:56', '2023-08-09 12:34'],
    'date-3.31 Sunday ISO weekday' => ['%w %u', '2023-01-01', '0 7'],
    'date-3.32 Monday ISO weekday' => ['%w %u', '2023-01-02', '1 1'],
    'date-3.33 Tuesday ISO weekday' => ['%w %u', '2023-01-03', '2 2'],
    'date-3.34 Wednesday ISO weekday' => ['%w %u', '2023-01-04', '3 3'],
    'date-3.35 Thursday ISO weekday' => ['%w %u', '2023-01-05', '4 4'],
    'date-3.36 Friday ISO weekday' => ['%w %u', '2023-01-06', '5 5'],
    'date-3.37 Saturday ISO weekday' => ['%w %u', '2023-01-07', '6 6'],
    'date-3.40 early year leading zero preservation' => ['%d/%f/%H/%W/%j/%m/%M/%S/%Y', '0421-01-02 03:04:05.006', '02/05.006/03/00/002/01/04/05/0421'],
];

foreach ($date320337Cases as $name => [$format, $value, $expected]) {
    $tests['real upstream corpus date strftime extended ' . $name] = static function (TestRunner $t) use ($format, $value, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $value]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(strlen($expected), strlen((string) $actual));
        $t->same($expected[0], ((string) $actual)[0]);
        $t->same(substr($expected, -1), substr((string) $actual, -1));
    };
}

for ($month = 1; $month <= 12; $month++) {
    for ($day = 1; $day <= 28; $day++) {
        $value = sprintf('2024-%02d-%02d %02d:%02d:%02d', $month, $day, $day % 24, ($month * 3 + $day) % 60, ($month * 5 + $day) % 60);
        $expectedDay = sprintf('%2d', $day);
        $expectedHour = sprintf('%2d', $day % 24);
        $expectedIsoWeekday = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('N');
        $expectedWeekday = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('w');
        $expected = "{$expectedDay}|{$expectedHour}|{$expectedWeekday}|{$expectedIsoWeekday}";

        $tests['real upstream corpus date strftime extended dynamic date-3.20-3.37 calendar row ' . $month . '-' . $day] = static function (TestRunner $t) use ($value, $expected, $expectedDay, $expectedHour, $expectedWeekday, $expectedIsoWeekday): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%e|%k|%w|%u', $value]);

            $t->same($expected, $actual);
            $t->same($expectedDay, substr((string) $actual, 0, 2));
            $t->same($expectedHour, substr((string) $actual, 3, 2));
            $t->same($expectedWeekday, substr((string) $actual, 6, 1));
            $t->same($expectedIsoWeekday, substr((string) $actual, 8, 1));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        };
    }
}

for ($hour = 0; $hour < 24; $hour++) {
    for ($minute = 0; $minute < 60; $minute++) {
        $value = sprintf('2023-08-09 %02d:%02d:59', $hour, $minute);
        $instant = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $expected = $instant->format('hA') . sprintf(' %2d:%02d%s ', (int) $instant->format('g'), $minute, strtolower($instant->format('A'))) . $instant->format('H:i');

        $tests['real upstream corpus date strftime extended dynamic date-3.23-3.30 meridiem row ' . $hour . '-' . $minute] = static function (TestRunner $t) use ($value, $expected, $hour): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%I%p %l:%M%P %R', $value]);

            $t->same($expected, $actual);
            $t->same($hour < 12 ? 'AM' : 'PM', substr((string) $actual, 2, 2));
            $t->same(strlen($expected), strlen((string) $actual));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
            $t->same(substr($expected, -5), substr((string) $actual, -5));
        };
    }
}

$tests['real upstream corpus date strftime extended application generated calendar sort keys'] = static function (TestRunner $t): void {
    $rows = [];
    foreach (['2024-01-01 00:00:00', '2024-02-09 04:05:06', '2024-12-28 23:59:59'] as $stamp) {
        $rows[] = [
            'key_name' => 'event.' . SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', $stamp]),
            'sort_key' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F %R %u', $stamp]),
            'display_key' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%e %l:%M%P', $stamp]),
        ];
    }

    $t->same([
        ['key_name' => 'event.2024-01-01', 'sort_key' => '2024-01-01 00:00 1', 'display_key' => ' 1 12:00am'],
        ['key_name' => 'event.2024-02-09', 'sort_key' => '2024-02-09 04:05 5', 'display_key' => ' 9  4:05am'],
        ['key_name' => 'event.2024-12-28', 'sort_key' => '2024-12-28 23:59 6', 'display_key' => '28 11:59pm'],
    ], $rows);
};

return $tests;
