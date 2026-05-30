<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$julianCases = [
    'date.test date-1.1 julianday 2000 epoch noon offset' => ['2000-01-01', 2451544.5],
    'date.test date-1.2 julianday unix epoch' => ['1970-01-01', 2440587.5],
    'date.test date-1.3 julianday early twentieth century' => ['1910-04-20', 2418781.5],
    'date.test date-1.4 julianday leap boundary' => ['1986-02-09', 2446470.5],
    'date.test date-1.5 julianday standalone noon time' => ['12:00:00', 2451545.0],
    'date.test date-1.6 julianday date and noon time' => ['2000-01-01 12:00:00', 2451545.0],
    'date.test date-1.7 julianday date and hour minute' => ['2000-01-01 12:00', 2451545.0],
    'date.test date-1.9 julianday day before 2000' => ['1999-12-31', 2451543.5],
    'date.test date-1.12 julianday normalized overflow day' => ['2003-02-31', 2452701.5],
    'date.test date-1.13 julianday normalized comparison day' => ['2003-03-03', 2452701.5],
    'date.test date-1.18.1 julianday repeated space separator' => ['2000-01-01     12:00:00', 2451545.0],
    'date.test date-1.18.2 julianday T separator' => ['2000-01-01T12:00:00', 2451545.0],
    'date.test date-1.18.3 julianday space T separator' => ['2000-01-01 T12:00:00', 2451545.0],
    'date.test date-1.18.4 julianday T space separator' => ['2000-01-01T 12:00:00', 2451545.0],
    'date.test date-1.18.5 julianday space T space separator' => ['2000-01-01 T 12:00:00', 2451545.0],
    'date.test date-1.19 julianday tenths fraction' => ['2000-01-01 12:00:00.1', 2451545.000001],
    'date.test date-1.20 julianday hundredths fraction' => ['2000-01-01 12:00:00.01', 2451545.0],
    'date.test date-1.21 julianday milliseconds fraction' => ['2000-01-01 12:00:00.001', 2451545.0],
    'date.test date-1.23 julianday numeric affinity keeps julian day' => [12345.6, 12345.6],
    'date.test date-1.23b julianday numeric lower bound' => [1721059.5, 1721059.5],
];

foreach ($julianCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date affinity dynamic ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$value]);
        $t->same(round($expected, 6), round((float) $actual, 6));
    };
}

$dateTimeCases = [
    'date.test date-2.1 datetime zero unixepoch' => ['datetime', [0, 'unixepoch'], '1970-01-01 00:00:00'],
    'date.test date-2.2 datetime y2k unixepoch' => ['datetime', [946684800, 'unixepoch'], '2000-01-01 00:00:00'],
    'date.test date-2.2b datetime text unixepoch numeric affinity' => ['datetime', ['946684800', 'unixepoch'], '2000-01-01 00:00:00'],
    'date.test date-2.3 date weekday sunday' => ['date', ['2003-10-22', 'weekday 0'], '2003-10-26'],
    'date.test date-2.4 date weekday monday' => ['date', ['2003-10-22', 'weekday 1'], '2003-10-27'],
    'date.test date-2.5 date weekday tuesday' => ['date', ['2003-10-22', 'weekday 2'], '2003-10-28'],
    'date.test date-2.6 date weekday same day' => ['date', ['2003-10-22', 'weekday 3'], '2003-10-22'],
    'date.test date-2.7 date weekday thursday' => ['date', ['2003-10-22', 'weekday 4'], '2003-10-23'],
    'date.test date-2.8 date weekday friday' => ['date', ['2003-10-22', 'weekday 5'], '2003-10-24'],
    'date.test date-2.9 date weekday saturday' => ['date', ['2003-10-22', 'weekday 6'], '2003-10-25'],
    'date.test date-2.12 datetime weekday preserves clock' => ['datetime', ['2003-10-22 12:34', 'weekday 0'], '2003-10-26 12:34:00'],
    'date.test date-2.13 datetime start of month' => ['datetime', ['2003-10-22 12:34', 'start of month'], '2003-10-01 00:00:00'],
    'date.test date-2.14 datetime start of year' => ['datetime', ['2003-10-22 12:34', 'start of year'], '2003-01-01 00:00:00'],
    'date.test date-2.15 datetime start of day' => ['datetime', ['2003-10-22 12:34', 'start of day'], '2003-10-22 00:00:00'],
    'date.test date-2.16 time truncates fractional second' => ['time', ['12:34:56.43'], '12:34:56'],
    'date.test date-2.17 datetime one day' => ['datetime', ['2003-10-22 12:34', '1 day'], '2003-10-23 12:34:00'],
    'date.test date-2.18 datetime signed day' => ['datetime', ['2003-10-22 12:34', '+1 day'], '2003-10-23 12:34:00'],
    'date.test date-2.19 datetime fractional day' => ['datetime', ['2003-10-22 12:34', '+1.25 day'], '2003-10-23 18:34:00'],
    'date.test date-2.20 datetime negative day' => ['datetime', ['2003-10-22 12:34', '-1.0 day'], '2003-10-21 12:34:00'],
    'date.test date-2.21 datetime month' => ['datetime', ['2003-10-22 12:34', '1 month'], '2003-11-22 12:34:00'],
    'date.test date-2.22 datetime eleven months' => ['datetime', ['2003-10-22 12:34', '11 month'], '2004-09-22 12:34:00'],
    'date.test date-2.23 datetime negative months' => ['datetime', ['2003-10-22 12:34', '-13 month'], '2002-09-22 12:34:00'],
    'date.test date-2.24 datetime fractional months' => ['datetime', ['2003-10-22 12:34', '1.5 months'], '2003-12-07 12:34:00'],
    'date.test date-2.25 datetime negative years' => ['datetime', ['2003-10-22 12:34', '-5 years'], '1998-10-22 12:34:00'],
    'date.test date-2.26 datetime fractional minutes' => ['datetime', ['2003-10-22 12:34', '+10.5 minutes'], '2003-10-22 12:44:30'],
    'date.test date-2.27 datetime fractional hours' => ['datetime', ['2003-10-22 12:34', '-1.25 hours'], '2003-10-22 11:19:00'],
    'date.test date-2.28 datetime fractional seconds truncates display' => ['datetime', ['2003-10-22 12:34', '11.25 seconds'], '2003-10-22 12:34:11'],
    'date.test date-2.41 datetime seconds modifier' => ['datetime', ['2003-10-22 12:24', '23 seconds'], '2003-10-22 12:24:23'],
    'date.test date-2.45 datetime sixty seconds rolls minute' => ['datetime', ['2003-10-22 12:24', '60 second'], '2003-10-22 12:25:00'],
    'date.test date-2.60 datetime normalizes overflow day' => ['datetime', ['2023-02-31'], '2023-03-03 00:00:00'],
    'date.test date-11.1 datetime negative hh mm ss modifier' => ['datetime', ['2004-02-28 20:00:00', '-01:20:30'], '2004-02-28 18:39:30'],
    'date.test date-11.2 datetime positive hh mm ss modifier crosses leap day' => ['datetime', ['2004-02-28 20:00:00', '+12:30:00'], '2004-02-29 08:30:00'],
    'date.test date-11.3 datetime positive hh mm modifier crosses leap day' => ['datetime', ['2004-02-28 20:00:00', '+12:30'], '2004-02-29 08:30:00'],
    'date.test date-11.4 datetime unsigned hh mm modifier is positive' => ['datetime', ['2004-02-28 20:00:00', '12:30'], '2004-02-29 08:30:00'],
    'date.test date-11.5 datetime negative twelve hours' => ['datetime', ['2004-02-28 20:00:00', '-12:00'], '2004-02-28 08:00:00'],
    'date.test date-11.6 datetime negative twelve hours one minute' => ['datetime', ['2004-02-28 20:00:00', '-12:01'], '2004-02-28 07:59:00'],
    'date.test date-11.7 datetime negative eleven hours fifty nine' => ['datetime', ['2004-02-28 20:00:00', '-11:59'], '2004-02-28 08:01:00'],
    'date.test date-11.8 datetime unsigned eleven hours fifty nine' => ['datetime', ['2004-02-28 20:00:00', '11:59'], '2004-02-29 07:59:00'],
    'date.test date-11.9 datetime unsigned twelve hours one minute' => ['datetime', ['2004-02-28 20:00:00', '12:01'], '2004-02-29 08:01:00'],
    'date.test date-5.1 timezone positive offset' => ['datetime', ['1994-04-16 14:00:00 +05:00'], '1994-04-16 09:00:00'],
    'date.test date-5.2 timezone negative offset' => ['datetime', ['1994-04-16 14:00:00 -05:15'], '1994-04-16 19:15:00'],
    'date.test date-5.3 timezone half-hour positive offset' => ['datetime', ['1994-04-16 05:00:00 +08:30'], '1994-04-15 20:30:00'],
    'date.test date-5.4 timezone large negative offset' => ['datetime', ['1994-04-16 14:00:00 -11:55'], '1994-04-17 01:55:00'],
    'date.test date-5.6 timezone trailing spaces' => ['datetime', ['1994-04-16 14:00:00 -11:55  '], '1994-04-17 01:55:00'],
    'date.test date-5.8 zulu T separator' => ['datetime', ['1994-04-16T14:00:00Z'], '1994-04-16 14:00:00'],
    'date.test date-5.9 lowercase zulu suffix' => ['datetime', ['1994-04-16 14:00:00z'], '1994-04-16 14:00:00'],
    'date.test date-5.10 separated zulu suffix' => ['datetime', ['1994-04-16 14:00:00 Z'], '1994-04-16 14:00:00'],
    'date.test date-5.11 zulu suffix trailing spaces' => ['datetime', ['1994-04-16 14:00:00z    '], '1994-04-16 14:00:00'],
    'date.test date-5.12 spaced zulu suffix trailing spaces' => ['datetime', ['1994-04-16 14:00:00     z    '], '1994-04-16 14:00:00'],
    'date.test date-6.25.1 utc zulu suffix no-op' => ['datetime', ['2000-10-29 12:00Z'], '2000-10-29 12:00:00'],
    'date.test date-6.25.2 utc separated zero offset' => ['datetime', ['2000-10-29 12:00 +00:00'], '2000-10-29 12:00:00'],
    'date.test date-6.25.3 utc compact zero offset' => ['datetime', ['2000-10-29 12:00+00:00'], '2000-10-29 12:00:00'],
    'date.test date-6.25.4 utc compact seconds zero offset' => ['datetime', ['2000-10-29 12:00:00+00:00'], '2000-10-29 12:00:00'],
    'date.test date-6.25.5 utc separated negative zero offset' => ['datetime', ['2000-10-29 12:00 -00:00'], '2000-10-29 12:00:00'],
    'date.test date-6.25.6 utc compact negative zero offset' => ['datetime', ['2000-10-29 12:00-00:00'], '2000-10-29 12:00:00'],
    'date.test date-6.25.7 utc seconds negative zero offset' => ['datetime', ['2000-10-29 12:00:00-00:00'], '2000-10-29 12:00:00'],
    'date.test date-6.26 positive offset without utc modifier' => ['datetime', ['2000-10-29 12:00:00+05:00'], '2000-10-29 07:00:00'],
    'date.test date-10.1 standalone time datetime default date' => ['datetime', ['01:02:03'], '2000-01-01 01:02:03'],
    'date.test date-10.2 standalone time date default date' => ['date', ['01:02:03'], '2000-01-01'],
    'date.test date-10.3 standalone time strftime default date' => ['strftime', ['%Y-%m-%d %H:%M', '01:02:03'], '2000-01-01 01:02'],
];

foreach ($dateTimeCases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date affinity dynamic ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments));
    };
}

// Source truth: SQLite upstream test/date.test date-2.2c-0..999.
// The Tcl corpus formats one thousand millisecond unixepoch values and expects
// strftime('%H:%M:%f', value, 'unixepoch') to preserve the fractional second.
for ($millisecond = 0; $millisecond < 1000; $millisecond++) {
    $value = sprintf('1237962480.%03d', $millisecond);
    $expected = sprintf('06:28:00.%03d', $millisecond);

    $tests['real upstream corpus date affinity dynamic date.test date-2.2c-' . $millisecond . ' strftime fractional unixepoch'] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H:%M:%f', $value, 'unixepoch']));
    };
}

$strftimeCases = [
    'date.test date-3.23 twelve hour lowercase am' => ['%I%P', '2023-08-09 11:59:59', '11am'],
    'date.test date-3.24 twelve hour uppercase pm' => ['%I%p', '2023-08-09 12:00:00', '12PM'],
    'date.test date-3.25 fractional twelve hour lowercase pm' => ['%I%P', '2023-08-09 12:59:59.9', '12pm'],
    'date.test date-3.26 afternoon one pm' => ['%I%p', '2023-08-09 13:00:00', '01PM'],
    'date.test date-3.27 late evening lowercase pm' => ['%I%P', '2023-08-09 23:59:59', '11pm'],
    'date.test date-3.28 midnight uppercase am' => ['%I%p', '2023-08-09 00:00:00', '12AM'],
    'date.test date-3.29 space-padded hour and minute' => ['%l:%M%P', '2023-08-09 13:00:00', ' 1:00pm'],
    'date.test date-3.30 date and hour-minute composite' => ['%F %R', '2023-08-09 12:34:56', '2023-08-09 12:34'],
    'date.test date-3.31 sunday weekday numbering' => ['%w %u', '2023-01-01', '0 7'],
    'date.test date-3.32 monday weekday numbering' => ['%w %u', '2023-01-02', '1 1'],
    'date.test date-3.33 tuesday weekday numbering' => ['%w %u', '2023-01-03', '2 2'],
    'date.test date-3.34 wednesday weekday numbering' => ['%w %u', '2023-01-04', '3 3'],
    'date.test date-3.35 thursday weekday numbering' => ['%w %u', '2023-01-05', '4 4'],
    'date.test date-3.36 friday weekday numbering' => ['%w %u', '2023-01-06', '5 5'],
    'date.test date-3.37 saturday weekday numbering' => ['%w %u', '2023-01-07', '6 6'],
    'date.test date-3.40 leading zero formatting' => ['%d/%f/%H/%W/%j/%m/%M/%S/%Y', '0421-01-02 03:04:05.006', '02/05.006/03/00/002/01/04/05/0421'],
];

foreach ($strftimeCases as $name => [$format, $value, $expected]) {
    $tests['real upstream corpus date affinity dynamic ' . $name] = static function (TestRunner $t) use ($format, $value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $value]));
    };
}

// Source truth: SQLite upstream test/date5.test.  The Tcl file derives a
// 400-year leap-cycle matrix from twelve fixed JD/calendar pairs and checks
// both directions for every generated year in range.
$date5Data = [
    [1, 2024, 2, 29, 2460369.5],
    [2, 2024, 3, 1, 2460370.5],
    [3, 2023, 2, 28, 2460003.5],
    [4, 2023, 3, 1, 2460004.5],
    [5, 2000, 2, 29, 2451603.5],
    [6, 2000, 3, 1, 2451604.5],
    [7, 1900, 2, 28, 2415078.5],
    [8, 1900, 3, 1, 2415079.5],
    [9, 1712, 2, 29, 2346413.5],
    [10, 1712, 3, 1, 2346414.5],
    [11, 1977, 4, 26, 2443259.5],
    [12, 2013, 1, 1, 2456293.5],
];

$addDate5Case = static function (int $sourceId, int $year, int $month, int $day, float $julianDay) use (&$tests): void {
    $date = sqliteRealUpstreamDateAffinityDynamicFormatDate5Year($year, $month, $day);
    $jdLabel = str_replace(['-', '.'], ['m', 'p'], (string) $julianDay);
    $dateLabel = str_replace('-', 'm', $date);

    $tests["real upstream corpus date affinity dynamic date5.test date5-jd{$jdLabel} source {$sourceId}"] = static function (TestRunner $t) use ($julianDay, $date): void {
        $t->same($date, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]));
    };
    $tests["real upstream corpus date affinity dynamic date5.test date5-cal {$dateLabel} source {$sourceId}"] = static function (TestRunner $t) use ($date, $julianDay): void {
        $t->same($julianDay, SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$date]));
    };
};

foreach ($date5Data as [$sourceId, $year, $month, $day, $julianDay]) {
    $addDate5Case($sourceId, $year, $month, $day, $julianDay);
    for ($cycle = 1; $year + 400 * $cycle <= 9999; $cycle++) {
        $addDate5Case($sourceId, $year + 400 * $cycle, $month, $day, $julianDay + 146097 * $cycle);
    }
    for ($cycle = 1; $year - 400 * $cycle >= -4712; $cycle++) {
        $addDate5Case($sourceId, $year - 400 * $cycle, $month, $day, $julianDay - 146097 * $cycle);
    }
}

for ($upstreamIndex = 0; $upstreamIndex <= 511; $upstreamIndex++) {
    $timestamp = $upstreamIndex * 86390;
    $instant = (new DateTimeImmutable('@' . (string) $timestamp))->setTimezone(new DateTimeZone('UTC'));
    $expected = implode(',', [
        $instant->format('d'),
        sprintf('%2d', (int) $instant->format('j')),
        $instant->format('Y-m-d'),
        $instant->format('H'),
        sprintf('%2d', (int) $instant->format('G')),
        $instant->format('h'),
        sprintf('%2d', (int) $instant->format('g')),
        sprintf('%03d', (int) $instant->format('z') + 1),
        $instant->format('m'),
        $instant->format('i'),
        $instant->format('N'),
        $instant->format('w'),
        sqliteRealUpstreamDateAffinityDynamicWeekNumber($instant, 1),
        $instant->format('Y'),
        '%',
        strtolower($instant->format('A')),
        $instant->format('A'),
        sqliteRealUpstreamDateAffinityDynamicWeekNumber($instant, 0),
        $instant->format('W'),
        $instant->format('o'),
        substr($instant->format('o'), -2),
    ]);

    $tests['real upstream corpus date affinity dynamic date4.test date4-' . $upstreamIndex . ' strftime libc parity'] = static function (TestRunner $t) use ($timestamp, $expected): void {
        $format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';

        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch']));
    };
}

for ($upstreamIndex = 512; $upstreamIndex <= 1023; $upstreamIndex++) {
    $timestamp = $upstreamIndex * 86390;
    $instant = (new DateTimeImmutable('@' . (string) $timestamp))->setTimezone(new DateTimeZone('UTC'));
    $expected = implode(',', [
        $instant->format('d'),
        sprintf('%2d', (int) $instant->format('j')),
        $instant->format('Y-m-d'),
        $instant->format('H'),
        sprintf('%2d', (int) $instant->format('G')),
        $instant->format('h'),
        sprintf('%2d', (int) $instant->format('g')),
        sprintf('%03d', (int) $instant->format('z') + 1),
        $instant->format('m'),
        $instant->format('i'),
        $instant->format('N'),
        $instant->format('w'),
        sqliteRealUpstreamDateAffinityDynamicWeekNumber($instant, 1),
        $instant->format('Y'),
        '%',
        strtolower($instant->format('A')),
        $instant->format('A'),
        sqliteRealUpstreamDateAffinityDynamicWeekNumber($instant, 0),
        $instant->format('W'),
        $instant->format('o'),
        substr($instant->format('o'), -2),
    ]);

    $tests['real upstream corpus date affinity dynamic date4.test date4-' . $upstreamIndex . ' strftime libc parity extended'] = static function (TestRunner $t) use ($timestamp, $expected): void {
        $format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';

        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch']));
    };
}

for ($upstreamIndex = 1024; $upstreamIndex <= 24858; $upstreamIndex++) {
    $timestamp = $upstreamIndex * 86390;
    $instant = (new DateTimeImmutable('@' . (string) $timestamp))->setTimezone(new DateTimeZone('UTC'));
    $expected = implode(',', [
        $instant->format('d'),
        sprintf('%2d', (int) $instant->format('j')),
        $instant->format('Y-m-d'),
        $instant->format('H'),
        sprintf('%2d', (int) $instant->format('G')),
        $instant->format('h'),
        sprintf('%2d', (int) $instant->format('g')),
        sprintf('%03d', (int) $instant->format('z') + 1),
        $instant->format('m'),
        $instant->format('i'),
        $instant->format('N'),
        $instant->format('w'),
        sqliteRealUpstreamDateAffinityDynamicWeekNumber($instant, 1),
        $instant->format('Y'),
        '%',
        strtolower($instant->format('A')),
        $instant->format('A'),
        sqliteRealUpstreamDateAffinityDynamicWeekNumber($instant, 0),
        $instant->format('W'),
        $instant->format('o'),
        substr($instant->format('o'), -2),
    ]);

    $tests['real upstream corpus date affinity dynamic date4.test date4-' . $upstreamIndex . ' strftime libc parity continuation'] = static function (TestRunner $t) use ($timestamp, $expected): void {
        $format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';

        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch']));
    };
}

$tests['real upstream corpus date affinity dynamic application expiry bucket uses numeric unixepoch affinity'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'cache.index', 'expires_at' => '946684800'],
        ['key_name' => 'cache.feed', 'expires_at' => 946771200],
        ['key_name' => 'cache.audit', 'expires_at' => 946857600.0],
    ];

    $buckets = [];
    foreach ($events as $event) {
        $buckets[$event['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments(
            'strftime',
            ['%Y-%m-%d', $event['expires_at'], 'unixepoch']
        );
    }

    $t->same([
        'cache.index' => '2000-01-01',
        'cache.feed' => '2000-01-02',
        'cache.audit' => '2000-01-03',
    ], $buckets);
};

$tests['real upstream corpus date affinity dynamic application elapsed window modifiers'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'retry.backoff', 'base' => '2004-02-28 20:00:00', 'modifier' => '+12:30:00'],
        ['key_name' => 'retry.grace', 'base' => '2004-02-28 20:00:00', 'modifier' => '11:59'],
        ['key_name' => 'retry.rewind', 'base' => '2004-02-28 20:00:00', 'modifier' => '-01:20:30'],
    ];

    $windows = [];
    foreach ($events as $event) {
        $windows[$event['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments(
            'datetime',
            [$event['base'], $event['modifier']]
        );
    }

    $t->same([
        'retry.backoff' => '2004-02-29 08:30:00',
        'retry.grace' => '2004-02-29 07:59:00',
        'retry.rewind' => '2004-02-28 18:39:30',
    ], $windows);
};

function sqliteRealUpstreamDateAffinityDynamicWeekNumber(DateTimeImmutable $instant, int $firstWeekday): string
{
    $dayOfYear = (int) $instant->format('z');
    $janFirst = $instant->setDate((int) $instant->format('Y'), 1, 1);
    $janFirstWeekday = (int) $janFirst->format('w');
    $daysUntilFirstWeekday = ($firstWeekday - $janFirstWeekday + 7) % 7;
    if ($dayOfYear < $daysUntilFirstWeekday) {
        return '00';
    }

    return sprintf('%02d', intdiv($dayOfYear - $daysUntilFirstWeekday, 7) + 1);
}

function sqliteRealUpstreamDateAffinityDynamicFormatDate5Year(int $year, int $month, int $day): string
{
    if ($year < 0) {
        return sprintf('-%04d-%02d-%02d', -$year, $month, $day);
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

return $tests;
