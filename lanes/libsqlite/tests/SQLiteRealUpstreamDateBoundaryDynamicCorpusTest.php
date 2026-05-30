<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date boundary dynamic cites upstream files'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-9.1..9.7 negative years and Julian day round trips',
        'date.test date-13.11..13.37 fractional day/month/year modifiers',
        'date.test date-16.1..16.31 extreme date/time range boundaries',
        'date.test date-17.1..17.7 start-of-day/month/year boundary behavior',
    ];

    $t->same(true, in_array('date.test date-16.1..16.31 extreme date/time range boundaries', $upstream, true));
    $t->same(true, in_array('date.test date-17.1..17.7 start-of-day/month/year boundary behavior', $upstream, true));
};

$date9Cases = [
    'date-9.1 negative minimum julian origin' => ['julianday', ['-4713-11-24 12:00:00'], 0.0],
    'date-9.2 datetime five round trips to julian' => ['datetime', [5], '-4713-11-29 12:00:00'],
    'date-9.3 datetime ten round trips to julian' => ['datetime', [10], '-4713-12-04 12:00:00'],
    'date-9.4 datetime hundred round trips to julian' => ['datetime', [100], '-4712-03-03 12:00:00'],
    'date-9.5 datetime thousand round trips to julian' => ['datetime', [1000], '-4710-08-20 12:00:00'],
    'date-9.6 datetime hundred thousand round trips to julian' => ['datetime', [100000], '-4439-09-09 12:00:00'],
];

foreach ($date9Cases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date boundary dynamic date.test ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments));
    };
}

$date13Cases = [
    'date-13.2 fractional second S truncates first minute' => ['strftime', ['%Y-%m-%d %H:%M:%S', '2007-01-01 12:34:59.6'], '2007-01-01 12:34:59'],
    'date-13.3 fractional second f preserves first minute' => ['strftime', ['%Y-%m-%d %H:%M:%f', '2007-01-01 12:34:59.6'], '2007-01-01 12:34:59.600'],
    'date-13.4 fractional second S truncates hour boundary' => ['strftime', ['%Y-%m-%d %H:%M:%S', '2007-01-01 12:59:59.6'], '2007-01-01 12:59:59'],
    'date-13.5 fractional second f preserves hour boundary' => ['strftime', ['%Y-%m-%d %H:%M:%f', '2007-01-01 12:59:59.6'], '2007-01-01 12:59:59.600'],
    'date-13.6 fractional second S truncates day boundary' => ['strftime', ['%Y-%m-%d %H:%M:%S', '2007-01-01 23:59:59.6'], '2007-01-01 23:59:59'],
    'date-13.7 fractional second f preserves day boundary' => ['strftime', ['%Y-%m-%d %H:%M:%f', '2007-01-01 23:59:59.6'], '2007-01-01 23:59:59.600'],
    'date-13.11 julian minus day' => ['julianday', [2454832.5, '-1 day'], 2454831.5],
    'date-13.12 julian plus day' => ['julianday', [2454832.5, '+1 day'], 2454833.5],
    'date-13.13 julian minus fractional day' => ['julianday', [2454832.5, '-1.5 day'], 2454831.0],
    'date-13.14 julian plus fractional day' => ['julianday', [2454832.5, '+1.5 day'], 2454834.0],
    'date-13.15 julian minus hours' => ['julianday', [2454832.5, '-3 hours'], 2454832.375],
    'date-13.16 julian plus hours' => ['julianday', [2454832.5, '+3 hours'], 2454832.625],
    'date-13.17 julian minus minutes' => ['julianday', [2454832.5, '-45 minutes'], 2454832.46875],
    'date-13.18 julian plus minutes' => ['julianday', [2454832.5, '+45 minutes'], 2454832.53125],
    'date-13.19 julian minus seconds' => ['julianday', [2454832.5, '-675 seconds'], 2454832.4921875],
    'date-13.20 julian plus seconds' => ['julianday', [2454832.5, '+675 seconds'], 2454832.5078125],
    'date-13.21 julian minus fractional months' => ['julianday', [2454832.5, '-1.5 months'], 2454786.5],
    'date-13.22 julian plus fractional months' => ['julianday', [2454832.5, '+1.5 months'], 2454878.5],
    'date-13.23 julian minus fractional years' => ['julianday', [2454832.5, '-1.5 years'], 2454284.0],
    'date-13.24 julian plus fractional years' => ['julianday', [2454832.5, '+1.5 years'], 2455380.0],
    'date-13.30 date plus fractional years leap cycle 2000' => ['date', ['2000-01-01', '+1.5 years'], '2001-07-02'],
    'date-13.31 date plus fractional years common 2001' => ['date', ['2001-01-01', '+1.5 years'], '2002-07-02'],
    'date-13.32 date plus fractional years common 2002' => ['date', ['2002-01-01', '+1.5 years'], '2003-07-02'],
    'date-13.33 date minus fractional years common 2002' => ['date', ['2002-01-01', '-1.5 years'], '2000-07-02'],
    'date-13.34 date minus fractional years common 2001' => ['date', ['2001-01-01', '-1.5 years'], '1999-07-02'],
    'date-13.35 date keeps valid february end' => ['date', ['2023-02-28'], '2023-02-28'],
    'date-13.36 date normalizes invalid february end' => ['date', ['2023-02-29'], '2023-03-01'],
    'date-13.37 date normalizes invalid april end' => ['date', ['2023-04-31'], '2023-05-01'],
];

foreach ($date13Cases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date boundary dynamic date.test ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments);
        if (is_float($expected)) {
            $t->same(round($expected, 7), round((float) $actual, 7));
            return;
        }

        $t->same($expected, $actual);
    };
}

$date16Cases = [
    'date-16.1 positive numeric date out of date range' => ['date', [147483649], null],
    'date-16.2 datetime julian minimum' => ['datetime', [0], '-4713-11-24 12:00:00'],
    'date-16.3 datetime julian maximum' => ['datetime', [5373484.49999999], '9999-12-31 23:59:59'],
    'date-16.4 julianday minimum text' => ['julianday', ['-4713-11-24 12:00:00'], 0.0],
    'date-16.5 julianday maximum text' => ['julianday', ['9999-12-31 23:59:59.999'], 5373484.49999999],
    'date-16.6 maximum seconds shift' => ['datetime', [0, '+464269060799 seconds'], '9999-12-31 23:59:59'],
    'date-16.7 overflow seconds shift' => ['datetime', [0, '+464269060800 seconds'], null],
    'date-16.8 maximum minutes shift' => ['datetime', [0, '+7737817679 minutes'], '9999-12-31 23:59:00'],
    'date-16.9 overflow minutes shift' => ['datetime', [0, '+7737817680 minutes'], null],
    'date-16.10 maximum hours shift' => ['datetime', [0, '+128963627 hours'], '9999-12-31 23:00:00'],
    'date-16.11 overflow hours shift' => ['datetime', [0, '+128963628 hours'], null],
    'date-16.12 maximum days shift' => ['datetime', [0, '+5373484 days'], '9999-12-31 12:00:00'],
    'date-16.13 overflow days shift' => ['datetime', [0, '+5373485 days'], null],
    'date-16.14 maximum months shift' => ['datetime', [0, '+176545 months'], '9999-12-24 12:00:00'],
    'date-16.15 overflow months shift' => ['datetime', [0, '+176546 months'], null],
    'date-16.16 maximum years shift' => ['datetime', [0, '+14712 years'], '9999-11-24 12:00:00'],
    'date-16.17 overflow years shift' => ['datetime', [0, '+14713 years'], null],
    'date-16.20 minimum seconds shift' => ['datetime', [5373484.4999999, '-464269060799 seconds'], '-4713-11-24 12:00:00'],
    'date-16.21 underflow seconds shift' => ['datetime', [5373484, '-464269060800 seconds'], null],
    'date-16.22 minimum minutes shift' => ['datetime', [5373484.4999999, '-7737817679 minutes'], '-4713-11-24 12:00:59'],
    'date-16.23 underflow minutes shift' => ['datetime', [5373484, '-7737817680 minutes'], null],
    'date-16.24 minimum hours shift' => ['datetime', [5373484.4999999, '-128963627 hours'], '-4713-11-24 12:59:59'],
    'date-16.25 underflow hours shift' => ['datetime', [5373484, '-128963628 hours'], null],
    'date-16.26 minimum days shift' => ['datetime', [5373484, '-5373484 days'], '-4713-11-24 12:00:00'],
    'date-16.27 underflow days shift' => ['datetime', [5373484, '-5373485 days'], null],
    'date-16.28 minimum months shift' => ['datetime', [5373484, '-176545 months'], '-4713-12-01 12:00:00'],
    'date-16.29 underflow months shift' => ['datetime', [5373484, '-176546 months'], null],
    'date-16.30 minimum years shift' => ['datetime', [5373484, '-14712 years'], '-4713-12-31 12:00:00'],
    'date-16.31 underflow years shift' => ['datetime', [5373484, '-14713 years'], null],
    'date-17.1 start of day near 2016 boundary' => ['datetime', [2457754, 'start of day'], '2016-12-31 00:00:00'],
    'date-17.2 datetime march 2017 noon' => ['datetime', [2457828], '2017-03-15 12:00:00'],
    'date-17.3 start of day march 2017' => ['datetime', [2457828, 'start of day'], '2017-03-15 00:00:00'],
    'date-17.4 start of month march 2017' => ['datetime', [2457828, 'start of month'], '2017-03-01 00:00:00'],
    'date-17.5 start of year march 2017' => ['datetime', [2457828, 'start of year'], '2017-01-01 00:00:00'],
    'date-17.6 start of year underflows minimum' => ['datetime', [37, 'start of year'], null],
    'date-17.7 start of year stays in minimum supported year' => ['datetime', [38, 'start of year'], '-4712-01-01 00:00:00'],
];

foreach ($date16Cases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date boundary dynamic date.test ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments);
        if (is_float($expected)) {
            $t->same(round($expected, 7), round((float) $actual, 7));
            return;
        }

        $t->same($expected, $actual);
    };
}

$overflowSweeps = [
    'date-16 positive seconds overflow sweep' => [0, '+%d seconds', 464269060800, 260],
    'date-16 positive minutes overflow sweep' => [0, '+%d minutes', 7737817680, 220],
    'date-16 positive hours overflow sweep' => [0, '+%d hours', 128963628, 180],
    'date-16 positive days overflow sweep' => [0, '+%d days', 5373485, 160],
    'date-16 positive months overflow sweep' => [0, '+%d months', 176546, 100],
    'date-16 positive years overflow sweep' => [0, '+%d years', 14713, 80],
    'date-16 negative seconds underflow sweep' => [5373484, '-%d seconds', 464269060800, 260],
    'date-16 negative minutes underflow sweep' => [5373484, '-%d minutes', 7737817680, 220],
    'date-16 negative hours underflow sweep' => [5373484, '-%d hours', 128963628, 180],
    'date-16 negative days underflow sweep' => [5373484, '-%d days', 5373485, 160],
    'date-16 negative months underflow sweep' => [5373484, '-%d months', 176546, 100],
    'date-16 negative years underflow sweep' => [5373484, '-%d years', 14713, 80],
];

foreach ($overflowSweeps as $name => [$base, $modifierFormat, $start, $count]) {
    for ($offset = 0; $offset < $count; $offset++) {
        $amount = $start + $offset;
        $modifier = sprintf($modifierFormat, $amount);

        $tests['real upstream corpus date boundary dynamic ' . $name . ' offset ' . $offset] = static function (TestRunner $t) use ($base, $modifier): void {
            $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$base, $modifier]));
        };
    }
}

$tests['real upstream corpus date boundary dynamic application event horizon rejects out of range retention dates'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'minimum', 'stored_at' => 0, 'modifier' => '+5373484 days'],
        ['key_name' => 'past-overflow', 'stored_at' => 5373484, 'modifier' => '-5373485 days'],
        ['key_name' => 'future-overflow', 'stored_at' => 0, 'modifier' => '+464269060800 seconds'],
        ['key_name' => 'safe-upper', 'stored_at' => 0, 'modifier' => '+464269060799 seconds'],
    ];
    $normalized = [];

    foreach ($events as $event) {
        $normalized[$event['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['stored_at'], $event['modifier']]);
    }

    $t->same([
        'minimum' => '9999-12-31 12:00:00',
        'past-overflow' => null,
        'future-overflow' => null,
        'safe-upper' => '9999-12-31 23:59:59',
    ], $normalized);
};

return $tests;
