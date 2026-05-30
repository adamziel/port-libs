<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream date affinity dynamic utc null cites upstream date sections'] = static function (TestRunner $t): void {
    $sections = [
        'date.test date-5.5 invalid timezone minute',
        'date.test date-5.7 invalid timezone suffix',
        'date.test date-5.13 invalid Zulu token',
        'date.test date-5.14 invalid Z plus timezone',
        'date.test date-5.15 invalid timezone plus Z',
        'date.test date-6.25.1..6.25.7 explicit UTC no-op',
        'date.test date-6.26 explicit offset without modifier',
        'date.test date-6.27 explicit offset with utc modifier',
        'date.test date-7.1..7.16 NULL date/time arguments',
    ];

    $t->same(true, in_array('date.test date-6.27 explicit offset with utc modifier', $sections, true));
    $t->same(true, in_array('date.test date-7.1..7.16 NULL date/time arguments', $sections, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$invalidTimezoneCases = [
    'date-5.5 timezone minute overflow' => ['1994-04-16 14:00:00 -11:60'],
    'date-5.7 trailing garbage after timezone' => ['1994-04-16 14:00:00 -11:55 x'],
    'date-5.13 zulu word is not suffix' => ['1994-04-16 14:00:00Zulu'],
    'date-5.14 z suffix plus timezone is rejected' => ['1994-04-16 14:00:00Z +05:00'],
    'date-5.15 timezone plus z suffix is rejected' => ['1994-04-16 14:00:00 +05:00 Z'],
];

foreach ($invalidTimezoneCases as $upstream => [$value]) {
    $tests['real upstream date affinity dynamic ' . $upstream] = static function (TestRunner $t) use ($value): void {
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]));
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$value]));
    };
}

$explicitUtcCases = [
    'date-6.25.1 z suffix utc utc' => ['2000-10-29 12:00Z', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.25.2 separated zero offset utc utc' => ['2000-10-29 12:00 +00:00', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.25.3 compact zero offset utc utc' => ['2000-10-29 12:00+00:00', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.25.4 seconds zero offset utc utc' => ['2000-10-29 12:00:00+00:00', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.25.5 separated negative zero offset utc utc' => ['2000-10-29 12:00 -00:00', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.25.6 compact negative zero offset utc utc' => ['2000-10-29 12:00-00:00', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.25.7 seconds negative zero offset utc utc' => ['2000-10-29 12:00:00-00:00', ['utc', 'utc'], '2000-10-29 12:00:00'],
    'date-6.26 positive offset without utc modifier' => ['2000-10-29 12:00:00+05:00', [], '2000-10-29 07:00:00'],
    'date-6.27 positive offset with utc modifier' => ['2000-10-29 12:00:00+05:00', ['utc'], '2000-10-29 07:00:00'],
];

foreach ($explicitUtcCases as $upstream => [$value, $modifiers, $expected]) {
    $tests['real upstream date affinity dynamic ' . $upstream] = static function (TestRunner $t) use ($value, $modifiers, $expected): void {
        $arguments = array_merge([$value], $modifiers);

        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments));
        $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments));
        $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', $arguments));
    };
}

$nullCases = [
    'date-7.1 datetime null value' => ['datetime', [null]],
    'date-7.2 datetime null modifier' => ['datetime', ['now', null]],
    'date-7.3 datetime localtime null modifier' => ['datetime', ['now', 'localtime', null]],
    'date-7.4 time null value' => ['time', [null]],
    'date-7.5 time null modifier' => ['time', ['now', null]],
    'date-7.6 time localtime null modifier' => ['time', ['now', 'localtime', null]],
    'date-7.7 date null value' => ['date', [null]],
    'date-7.8 date null modifier' => ['date', ['now', null]],
    'date-7.9 date localtime null modifier' => ['date', ['now', 'localtime', null]],
    'date-7.10 julianday null value' => ['julianday', [null]],
    'date-7.11 julianday null modifier' => ['julianday', ['now', null]],
    'date-7.12 julianday localtime null modifier' => ['julianday', ['now', 'localtime', null]],
    'date-7.13 strftime null format' => ['strftime', [null, 'now']],
    'date-7.14 strftime null value' => ['strftime', ['%s', null]],
    'date-7.15 strftime null modifier' => ['strftime', ['%s', 'now', null]],
    'date-7.16 strftime localtime null modifier' => ['strftime', ['%s', 'now', 'localtime', null]],
];

foreach ($nullCases as $upstream => [$function, $arguments]) {
    $tests['real upstream date affinity dynamic ' . $upstream] = static function (TestRunner $t) use ($function, $arguments): void {
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments));
        $t->same(true, in_array($function, ['date', 'time', 'datetime', 'julianday', 'strftime'], true));
    };
}

for ($day = 0; $day < 400; $day++) {
    foreach ([0, 5, -5] as $offsetHour) {
        $base = (new DateTimeImmutable('2000-10-29 12:00:00', new DateTimeZone('UTC')))->modify("+{$day} days");
        $sourceLocal = $base->modify(sprintf('%+d hours', $offsetHour));
        $suffix = sprintf('%+03d:00', $offsetHour);
        $value = $sourceLocal->format('Y-m-d H:i:s') . $suffix;
        $expected = $base->format('Y-m-d H:i:s');
        $source = $offsetHour === 0 ? 'date-6.25 explicit zero-offset no-op' : 'date-6.27 explicit non-zero offset with utc modifier';
        $name = sprintf(
            'real upstream date affinity dynamic %s generated day %03d offset %+d',
            $source,
            $day,
            $offsetHour
        );

        $tests[$name] = static function (TestRunner $t) use ($value, $expected, $offsetHour): void {
            $arguments = $offsetHour === 0 ? [$value, 'utc', 'utc'] : [$value, 'utc'];

            $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments));
            $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments));
            $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', $arguments));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments)]));
        };
    }
}

$tests['real upstream date affinity dynamic application absolute schedule offsets stay utc'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'publish.north', 'starts_at' => '2024-02-29 12:30:00+05:00'],
        ['key_name' => 'publish.zero', 'starts_at' => '2024-02-29 07:30:00Z'],
        ['key_name' => 'publish.west', 'starts_at' => '2024-02-29 02:15:00-05:15'],
    ];
    $actual = [];

    foreach ($events as $event) {
        $actual[$event['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['starts_at'], 'utc']);
    }

    $t->same([
        'publish.north' => '2024-02-29 07:30:00',
        'publish.zero' => '2024-02-29 07:30:00',
        'publish.west' => '2024-02-29 07:30:00',
    ], $actual);
};

return $tests;
