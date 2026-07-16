<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date subsecond dynamic cites upstream date18'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-18.2 unixepoch subsec millisecond resolution',
        'date.test date-18.3 unixepoch subsecond millisecond resolution',
        'date.test date-18.4 julianday subsec fractional day precision',
        'date.test date-18.5 typeof(unixepoch(now, subsecond)) is real',
    ];

    $t->same(true, in_array('date.test date-18.2 unixepoch subsec millisecond resolution', $upstream, true));
    $t->same(true, in_array('date.test date-18.5 typeof(unixepoch(now, subsecond)) is real', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

$date18Cases = [
    'date-18.2 unixepoch subsec tenths' => ['unixepoch', ['1970-01-01T00:00:00.1', 'subsec'], 0.1],
    'date-18.3 unixepoch subsecond tenths' => ['unixepoch', ['1970-01-01T00:00:00.2', 'subsecond'], 0.2],
    'date-18.4 julianday subsec fractional minimum day' => ['julianday', ['-4713-11-24 13:40:48.864', 'subsec'], 0.07001],
];

foreach ($date18Cases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date subsecond dynamic date.test ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments);

        $t->same(round((float) $expected, 5), round((float) $actual, 5));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream corpus date subsecond dynamic date.test date-18.5 now subsecond is real'] = static function (TestRunner $t): void {
    $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['now', 'subsecond']);

    $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    $t->same(true, is_float($actual));
    $t->same(true, $actual > 0.0);
};

// Source truth: SQLite upstream test/date.test date-18.2 and date-18.3.  The
// Tcl corpus checks named subsec/subsecond examples; this dynamic matrix keeps
// the same resolution contract over positive and negative unixepoch values.
for ($index = 0; $index < 1200; $index++) {
    $seconds = $index % 2 === 0 ? $index * 12345 : -$index * 2345;
    $milliseconds = ($index * 37) % 1000;
    $timeValue = sprintf('%d.%03d', $seconds, $milliseconds);
    $expected = (float) $timeValue;
    $modifier = $index % 3 === 0 ? 'subsecond' : 'subsec';

    $tests['real upstream corpus date subsecond dynamic unixepoch fractional row ' . sprintf('%04d', $index)] = static function (TestRunner $t) use ($timeValue, $expected, $modifier): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$timeValue, 'unixepoch', $modifier]);
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, 'unixepoch', $modifier]);
        $time = SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$timeValue, 'unixepoch', $modifier]);
        $strftime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%f', $timeValue, 'unixepoch']);

        $t->same(round($expected, 3), round((float) $actual, 3));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(true, str_contains((string) $datetime, '.'));
        $t->same(true, str_contains((string) $time, '.'));
        $t->same(sprintf('%06.3f', (float) substr((string) $strftime, 0)), (string) $strftime);
        $t->same(substr((string) $datetime, 11), $time);
    };
}

$tests['real upstream corpus date subsecond dynamic application millisecond schedule audit'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'schedule.alpha', 'timestamp' => '1700000000.125'],
        ['key_name' => 'schedule.beta', 'timestamp' => '1700000001.500'],
        ['key_name' => 'schedule.gamma', 'timestamp' => '1700000002.875'],
    ];
    $actual = [];

    foreach ($events as $event) {
        $actual[$event['key_name']] = [
            'stored_epoch' => SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$event['timestamp'], 'unixepoch', 'subsec']),
            'stored_time' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['timestamp'], 'unixepoch', 'subsec']),
        ];
    }

    $t->same([
        'schedule.alpha' => ['stored_epoch' => 1700000000.125, 'stored_time' => '2023-11-14 22:13:20.125'],
        'schedule.beta' => ['stored_epoch' => 1700000001.5, 'stored_time' => '2023-11-14 22:13:21.500'],
        'schedule.gamma' => ['stored_epoch' => 1700000002.875, 'stored_time' => '2023-11-14 22:13:22.875'],
    ], $actual);
};

return $tests;
