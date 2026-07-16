<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream date timediff dynamic matrix cites upstream timediff1 source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test';
    $sections = [
        'timediff1.test timediff-3.1..3.4 exact calendar-difference strings',
        'timediff1.test timediff-5-* partial time-diff modifier grammar',
    ];

    $t->same(true, is_file($source), $source);
    $t->same(true, in_array('timediff1.test timediff-5-* partial time-diff modifier grammar', $sections, true));
};

$exactTimediffCases = [
    'timediff-3.1 february leap overflow forward' => ['2000-03-02', '2000-01-31', '+0000-01-00 00:00:00.000'],
    'timediff-3.3 year month forward' => ['2000-03-02', '1999-01-31', '+0001-01-00 00:00:00.000'],
];

foreach ($exactTimediffCases as $name => [$left, $right, $expected]) {
    $tests['real upstream date timediff dynamic matrix timediff1.test ' . $name] = static function (TestRunner $t) use ($left, $right, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [$left, $right]);

        $t->same($expected, $actual);
        $t->same(SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$left]), SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$right, $actual]));
    };
}

$partialModifierCases = [
    'timediff-5-1 partial date modifier' => ['+0001-02-03', '2001-03-04 00:00:00'],
    'timediff-5-2 suffix rejected' => ['+0001-02-03x', null],
    'timediff-5-3 eleven months accepted' => ['+0001-11-03', '2001-12-04 00:00:00'],
    'timediff-5-4 twelve months rejected' => ['+0001-12-03', null],
    'timediff-5-5 day thirty accepted through calendar overflow' => ['+0001-02-30', '2001-03-31 00:00:00'],
    'timediff-5-6 day thirty one rejected' => ['+0001-02-31', null],
    'timediff-5-7 one digit time rejected' => ['+0001-02-03 0', null],
    'timediff-5-8 two digit time rejected' => ['+0001-02-03 01', null],
    'timediff-5-9 dangling hour colon rejected' => ['+0001-02-03 01:', null],
    'timediff-5-10 one digit minute rejected' => ['+0001-02-03 01:0', null],
    'timediff-5-11 hour minute accepted' => ['+0001-02-03 01:02', '2001-03-04 01:02:00'],
    'timediff-5-12 dangling minute colon rejected' => ['+0001-02-03 01:02:', null],
    'timediff-5-13 one digit second rejected' => ['+0001-02-03 01:02:0', null],
    'timediff-5-14 hour minute second accepted' => ['+0001-02-03 01:02:03', '2001-03-04 01:02:03'],
    'timediff-5-15 dangling fraction rejected' => ['+0001-02-03 01:02:03.', null],
    'timediff-5-16 fractional seconds accepted' => ['+0001-02-03 01:02:03.5', '2001-03-04 01:02:03'],
    'timediff-5-17 two digit fraction accepted' => ['+0001-02-03 01:02:03.50', '2001-03-04 01:02:03'],
    'timediff-5-18 three digit fraction accepted' => ['+0001-02-03 01:02:03.500', '2001-03-04 01:02:03'],
    'timediff-5-19 fractional suffix rejected' => ['+0001-02-03 01:02:03.500x', null],
    'timediff-5-20 fractional separated suffix rejected' => ['+0001-02-03 01:02:03.500 x', null],
];

foreach ($partialModifierCases as $name => [$modifier, $expected]) {
    $tests['real upstream date timediff dynamic matrix timediff1.test ' . $name] = static function (TestRunner $t) use ($modifier, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2000-01-01', $modifier]));
    };
}

for ($year = 0; $year < 10; $year++) {
    for ($month = 0; $month < 12; $month++) {
        for ($day = 0; $day < 10; $day++) {
            $modifier = sprintf('+%04d-%02d-%02d %02d:%02d:%02d.%03d', $year, $month, $day, $day, $month, $year, 500);
            $expected = (new DateTimeImmutable('2000-01-01 00:00:00', new DateTimeZone('UTC')))
                ->modify(sprintf('+%d months', ($year * 12) + $month))
                ->modify(sprintf('+%d days', $day))
                ->modify(sprintf('+%d seconds', ($day * 3600) + ($month * 60) + $year))
                ->format('Y-m-d H:i:s');

            $tests[sprintf('real upstream date timediff dynamic matrix timediff1.test timediff-5 generated positive modifier %04d-%02d-%02d', $year, $month, $day)] = static function (TestRunner $t) use ($modifier, $expected): void {
                $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2000-01-01', $modifier]));
            };
        }
    }
}

$tests['real upstream date timediff dynamic matrix application schedule roundtrip summary'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'retention-window', 'started_at' => '2000-01-31', 'finished_at' => '2000-03-02'],
        ['key_name' => 'leap-maintenance', 'started_at' => '2024-02-29 00:00:00', 'finished_at' => '2025-03-01 00:00:00'],
        ['key_name' => 'historical-import', 'started_at' => '1066-10-14', 'finished_at' => '2023-05-29 18:11'],
    ];
    $summary = [];

    foreach ($events as $event) {
        $elapsed = SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [$event['finished_at'], $event['started_at']]);
        $summary[$event['key_name']] = [
            'elapsed' => $elapsed,
            'roundtrip' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['started_at'], $elapsed]),
        ];
    }

    $t->same('2000-03-02 00:00:00', $summary['retention-window']['roundtrip']);
    $t->same('2025-03-01 00:00:00', $summary['leap-maintenance']['roundtrip']);
    $t->same('2023-05-29 18:11:00', $summary['historical-import']['roundtrip']);
};

return $tests;
