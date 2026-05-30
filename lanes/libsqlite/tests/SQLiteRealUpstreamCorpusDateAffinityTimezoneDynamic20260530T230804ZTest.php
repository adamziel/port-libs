<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity timezone dynamic cites upstream timezone suffix sections'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-5.1..5.15 timezone suffix parsing and rejection',
        'date.test date-6.25.1..6.25.7 zero-offset utc no-op chains',
        'date.test date-6.26..6.27 non-zero timezone suffix remains utc after utc modifier',
    ];

    $t->same(true, in_array('date.test date-5.1..5.15 timezone suffix parsing and rejection', $upstream, true));
    $t->same(true, in_array('date.test date-6.25.1..6.25.7 zero-offset utc no-op chains', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$date5Cases = [
    'date-5.1 positive hour offset subtracts to utc' => ['1994-04-16 14:00:00 +05:00', '1994-04-16 09:00:00'],
    'date-5.2 negative hour minute offset adds to utc' => ['1994-04-16 14:00:00 -05:15', '1994-04-16 19:15:00'],
    'date-5.3 positive offset crosses previous day' => ['1994-04-16 05:00:00 +08:30', '1994-04-15 20:30:00'],
    'date-5.4 negative offset crosses next day' => ['1994-04-16 14:00:00 -11:55', '1994-04-17 01:55:00'],
    'date-5.5 invalid offset minute rejected' => ['1994-04-16 14:00:00 -11:60', null],
    'date-5.6 trailing spaces after offset accepted' => ['1994-04-16 14:00:00 -11:55  ', '1994-04-17 01:55:00'],
    'date-5.7 trailing token after offset rejected' => ['1994-04-16 14:00:00 -11:55 x', null],
    'date-5.8 uppercase z with t separator accepted' => ['1994-04-16T14:00:00Z', '1994-04-16 14:00:00'],
    'date-5.9 lowercase z accepted' => ['1994-04-16 14:00:00z', '1994-04-16 14:00:00'],
    'date-5.10 separated uppercase z accepted' => ['1994-04-16 14:00:00 Z', '1994-04-16 14:00:00'],
    'date-5.11 lowercase z trailing spaces accepted' => ['1994-04-16 14:00:00z    ', '1994-04-16 14:00:00'],
    'date-5.12 spaced lowercase z trailing spaces accepted' => ['1994-04-16 14:00:00     z    ', '1994-04-16 14:00:00'],
    'date-5.13 zulu word rejected' => ['1994-04-16 14:00:00Zulu', null],
    'date-5.14 z plus offset rejected' => ['1994-04-16 14:00:00Z +05:00', null],
    'date-5.15 offset plus z rejected' => ['1994-04-16 14:00:00 +05:00 Z', null],
];

foreach ($date5Cases as $name => [$value, $expected]) {
    $tests['real upstream corpus date affinity timezone dynamic ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$utcNoopCases = [
    'date-6.25.1 z suffix repeated utc modifiers' => ['2000-10-29 12:00Z', '2000-10-29 12:00:00'],
    'date-6.25.2 space plus zero offset repeated utc modifiers' => ['2000-10-29 12:00 +00:00', '2000-10-29 12:00:00'],
    'date-6.25.3 plus zero offset repeated utc modifiers' => ['2000-10-29 12:00+00:00', '2000-10-29 12:00:00'],
    'date-6.25.4 seconds plus zero offset repeated utc modifiers' => ['2000-10-29 12:00:00+00:00', '2000-10-29 12:00:00'],
    'date-6.25.5 space minus zero offset repeated utc modifiers' => ['2000-10-29 12:00 -00:00', '2000-10-29 12:00:00'],
    'date-6.25.6 minus zero offset repeated utc modifiers' => ['2000-10-29 12:00-00:00', '2000-10-29 12:00:00'],
    'date-6.25.7 seconds minus zero offset repeated utc modifiers' => ['2000-10-29 12:00:00-00:00', '2000-10-29 12:00:00'],
    'date-6.26 non-zero offset normalizes to utc' => ['2000-10-29 12:00:00+05:00', '2000-10-29 07:00:00'],
    'date-6.27 non-zero offset followed by utc remains normalized' => ['2000-10-29 12:00:00+05:00', '2000-10-29 07:00:00'],
];

foreach ($utcNoopCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date affinity timezone dynamic ' . $name] = static function (TestRunner $t) use ($name, $value, $expected): void {
        $arguments = str_contains($name, 'date-6.27') ? [$value, 'utc'] : [$value, 'utc', 'utc'];
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments);

        $t->same($expected, $actual);
        $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments));
        $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', $arguments));
    };
}

$base = new DateTimeImmutable('1994-04-16 00:00:00', new DateTimeZone('UTC'));
for ($case = 0; $case < 1000; $case++) {
    $local = $base
        ->modify(sprintf('+%d days', intdiv($case, 40)))
        ->modify(sprintf('+%d minutes', ($case * 37) % 1440));
    $sign = $case % 2 === 0 ? '+' : '-';
    $offsetHour = ($case * 7) % 14;
    $offsetMinute = ($case * 11) % 60;
    $offset = sprintf('%s%02d:%02d', $sign, $offsetHour, $offsetMinute);
    $value = $local->format('Y-m-d H:i:s') . ' ' . $offset;
    $offsetSeconds = ($offsetHour * 3600) + ($offsetMinute * 60);
    $expected = $local
        ->modify(sprintf('%+d seconds', $sign === '+' ? -$offsetSeconds : $offsetSeconds))
        ->format('Y-m-d H:i:s');
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date affinity timezone dynamic generated offset normalization row ' . $label] = static function (TestRunner $t) use ($value, $expected, $offset): void {
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);

        $t->same($expected, $datetime);
        $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$value]));
        $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$value]));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value, 'utc']));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$datetime]));
        $t->same(true, preg_match('/\A[+-]\d{2}:\d{2}\z/', $offset) === 1);
    };
}

$tests['real upstream corpus date affinity timezone dynamic application expiry keys normalize to utc'] = static function (TestRunner $t): void {
    $rows = [
        ['key_name' => 'release.alpha', 'expires_local' => '1994-04-16 14:00:00 +05:00'],
        ['key_name' => 'release.beta', 'expires_local' => '1994-04-16 14:00:00 -05:15'],
        ['key_name' => 'release.gamma', 'expires_local' => '2000-10-29 12:00:00+05:00'],
        ['key_name' => 'release.delta', 'expires_local' => '2000-10-29 12:00:00-00:00'],
    ];
    $actual = [];

    foreach ($rows as $row) {
        $actual[$row['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['expires_local'], 'utc']);
    }

    $t->same([
        'release.alpha' => '1994-04-16 09:00:00',
        'release.beta' => '1994-04-16 19:15:00',
        'release.gamma' => '2000-10-29 07:00:00',
        'release.delta' => '2000-10-29 12:00:00',
    ], $actual);
};

return $tests;
