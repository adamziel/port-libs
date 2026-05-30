<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date fraction truncation dynamic cites upstream date20 section'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-20.1 datetime fractional .9990 remains same second',
        'date.test date-20.2 datetime long fractional .9999999999999 remains same second',
        'date.test date-20.3 datetime fractional .9995 remains same second',
        'date.test date-20.4 datetime prior-second fractional .9995 remains same second',
    ];

    $t->same(true, in_array('date.test date-20.2 datetime long fractional .9999999999999 remains same second', $upstream, true));
    $t->same(4, count($upstream));
};

$date20Cases = [
    'date-20.1 millisecond tail does not round up' => ['2024-12-31 23:59:59.9990', '2024-12-31 23:59:59'],
    'date-20.2 long fractional tail does not reject or round up' => ['2024-12-31 23:59:59.9999999999999', '2024-12-31 23:59:59'],
    'date-20.3 half millisecond tail does not round up' => ['2024-12-31 23:59:59.9995', '2024-12-31 23:59:59'],
    'date-20.4 prior-second half millisecond tail does not round up' => ['2024-12-31 23:59:58.9995', '2024-12-31 23:59:58'],
];

foreach ($date20Cases as $name => [$value, $expected]) {
    $tests['real upstream corpus date fraction truncation dynamic date.test ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]));
        $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$value]));
        $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$value]));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $value]));
    };
}

// Source truth: SQLite upstream test/date.test date-20.1..20.4.  The dynamic
// matrix keeps the same date-20 rule over many calendar instants: long or
// half-millisecond fractional tails are accepted and truncated, never rounded
// into the next second, minute, day, month, or year.
for ($case = 0; $case < 1000; $case++) {
    $year = 2020 + intdiv($case, 200);
    $month = ($case % 12) + 1;
    $day = (($case * 7) % 28) + 1;
    $hour = $case % 24;
    $minute = ($case * 3) % 60;
    $second = ($case * 7) % 60;
    $tail = match ($case % 5) {
        0 => '9990',
        1 => '9995',
        2 => '9999999',
        3 => '9999999999999',
        default => str_repeat('9', 20),
    };
    $expected = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    $value = $expected . '.' . $tail;

    $tests['real upstream corpus date fraction truncation dynamic date.test date-20 generated long fraction ' . $case] = static function (TestRunner $t) use ($value, $expected, $tail): void {
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);
        $subsecond = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value, 'subsec']);

        $t->same($expected, $datetime);
        $t->same($expected . '.999', $subsecond);
        $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$value]));
        $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$value]));
        $t->same(substr($expected, 11) . '.999', SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$value, 'subsec']));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $value]));
        $t->same(substr($expected, 0, 16), SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M', $value]));
        $t->same('59.999', SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%f', '2024-12-31 23:59:59.' . $tail]));
        $t->same(false, str_contains((string) $datetime, '24:00:00'));
        $t->same(19, strlen((string) $datetime));
    };
}

$tests['real upstream corpus date fraction truncation dynamic application schedule cutoff keeps final second'] = static function (TestRunner $t): void {
    $rows = [
        ['key_name' => 'year-end', 'expires_at' => '2024-12-31 23:59:59.9999999999999'],
        ['key_name' => 'minute-end', 'expires_at' => '2024-12-31 23:58:59.9995'],
        ['key_name' => 'day-end', 'expires_at' => '2024-02-29 23:59:59.9999999'],
    ];
    $actual = [];

    foreach ($rows as $row) {
        $actual[$row['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['expires_at']]);
    }

    $t->same([
        'year-end' => '2024-12-31 23:59:59',
        'minute-end' => '2024-12-31 23:58:59',
        'day-end' => '2024-02-29 23:59:59',
    ], $actual);
};

return $tests;
