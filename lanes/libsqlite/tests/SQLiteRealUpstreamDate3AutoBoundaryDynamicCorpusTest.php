<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date3 auto boundary cites source loops'] = static function (TestRunner $t): void {
    $upstream = [
        'date3.test date3-1.7.1..100 unixepoch identity loop',
        'date3.test date3-2.1..2.30 auto modifier Julian-day/unixepoch boundary',
        'date3.test date3-5.0 first 63 days of 1970 auto ambiguity count',
        'date.test date-14.2.0..255 floating point boundary never renders 24:00:00',
    ];

    $t->same(true, in_array('date3.test date3-1.7.1..100 unixepoch identity loop', $upstream, true));
    $t->same(true, in_array('date.test date-14.2.0..255 floating point boundary never renders 24:00:00', $upstream, true));
};

for ($i = 1; $i <= 100; $i++) {
    $timestamp = -4294967295 + ($i * 85899345);
    $expectedDate = (new DateTimeImmutable('@' . (string) $timestamp))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $tests['real upstream corpus date3 date3-1.7.' . $i . ' unixepoch identity sample'] = static function (TestRunner $t) use ($timestamp, $expectedDate): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$timestamp, 'unixepoch']);
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timestamp, 'unixepoch']);

        $t->same($timestamp, $actual);
        $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($expectedDate, $datetime);
        $t->same($timestamp, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$datetime]));
        $t->same($expectedDate, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$datetime]));
        $t->same($expectedDate, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$actual, 'unixepoch']));
        $t->same(substr($expectedDate, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$actual, 'unixepoch']));
        $t->same(substr($expectedDate, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$actual, 'unixepoch']));
        $t->same($timestamp < 0 || $timestamp > 5373484 ? $expectedDate : SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timestamp]), SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timestamp, 'auto']));
        $t->same(true, is_int($actual));
        $t->same(true, is_string($datetime));
        $t->same(19, strlen((string) $datetime));
        $t->same($timestamp, (int) SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%s', $datetime]));
    };
}

$autoBoundaryCases = [
    'date3-2.1 julian zero lower bound' => [0.0, '-4713-11-24 12:00:00', true],
    'date3-2.2 julian upper bound' => [5373484.4999999, '9999-12-31 23:59:59', true],
    'date3-2.3 unix epoch as julian day' => [2440587.5, '1970-01-01 00:00:00', true],
    'date3-2.4 unix epoch prior second as julian day' => [2440587.49998843, '1969-12-31 23:59:59', true],
    'date3-2.5 mixed julian day precision' => [2440615.7475463, '1970-01-29 05:56:28', true],
    'date3-2.10 negative unix timestamp' => [-1, '1969-12-31 23:59:59', false],
    'date3-2.11 first unix timestamp after julian range' => [5373485, '1970-03-04 04:38:05', false],
    'date3-2.12 minimum unix timestamp' => [-210866760000, '-4713-11-24 12:00:00', false],
    'date3-2.13 maximum unix timestamp' => [253402300799, '9999-12-31 23:59:59', false],
    'date3-2.20 below minimum unix timestamp' => [-210866760001, null, false],
    'date3-2.21 above maximum unix timestamp' => [253402300800, null, false],
];

foreach ($autoBoundaryCases as $name => [$value, $expected, $julianRange]) {
    $tests['real upstream corpus date3 auto modifier ' . $name] = static function (TestRunner $t) use ($value, $expected, $julianRange): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value, 'auto']);

        $t->same($expected, $actual);
        $t->same($expected === null, $actual === null);
        $t->same($julianRange, is_numeric($value) && (float) $value >= 0.0 && (float) $value <= 5373484.4999999);
        $t->same('auto', strtolower('auto'));
        $literal = is_float($value) ? rtrim(rtrim(sprintf('%.8F', $value), '0'), '.') : (string) $value;
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$literal, 'auto']));
        if ($expected !== null) {
            [$date, $time] = sqliteRealUpstreamDate3AutoBoundarySplitDateTime($expected);
            $t->same($date, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$value, 'auto']));
            $t->same($time, SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$value, 'auto']));
            $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $value, 'auto']));
            $t->same(strlen($expected), strlen((string) $actual));
        } else {
            $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$value, 'auto']));
            $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$value, 'auto']));
            $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $value, 'auto']));
            $t->same(true, $actual === null);
        }
    };
}

$tests['real upstream corpus date3 date3-2.30 auto text modifier no-op'] = static function (TestRunner $t): void {
    $auto = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29', 'auto']);
    $plain = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29']);

    $t->same($plain, $auto);
    $t->same('2022-01-29', $auto);
    $t->same('2022-01-29 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2022-01-29', 'auto']));
    $t->same('00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('time', ['2022-01-29', 'auto']));
    $t->same(1, $auto === $plain ? 1 : 0);
};

$tests['real upstream corpus date3 date3-2.40 mixed auto row values'] = static function (TestRunner $t): void {
    $rows = [
        ['timeval' => '2022-01-27 13:15:44', 'datetime' => '2022-01-27 13:15:44'],
        ['timeval' => 2459607.05260275, 'datetime' => '2022-01-27 13:15:44'],
        ['timeval' => 1643289344, 'datetime' => '2022-01-27 13:15:44'],
    ];

    foreach ($rows as $row) {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['timeval'], 'auto']);
        $t->same($row['datetime'], $actual);
        $t->same(1, $actual === $row['datetime'] ? 1 : 0);
        $t->same(substr($row['datetime'], 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$row['timeval'], 'auto']));
        $t->same(substr($row['datetime'], 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$row['timeval'], 'auto']));
    }
};

$modifierPlacementCases = [
    'date3-3.1 unixepoch after arithmetic rejected' => [2459607.05, ['+1 hour', 'unixepoch'], null],
    'date3-3.2 unixepoch immediately after numeric accepted' => [2459607.05, ['unixepoch', '+1 hour'], '1970-01-29 12:13:27'],
    'date3-4.1 julianday immediately after numeric accepted' => [2459607, ['julianday'], '2022-01-27 12:00:00'],
    'date3-4.2 julianday after arithmetic rejected' => [2459607, ['+1 hour', 'julianday'], null],
    'date3-4.3 julianday after text rejected' => ['2022-01-27', ['julianday'], null],
];

foreach ($modifierPlacementCases as $name => [$value, $modifiers, $expected]) {
    $tests['real upstream corpus date3 modifier placement ' . $name] = static function (TestRunner $t) use ($value, $modifiers, $expected): void {
        $args = array_merge([$value], $modifiers);
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $args);

        $t->same($expected, $actual);
        $t->same($expected === null, $actual === null);
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', array_merge(['%Y-%m-%d %H:%M:%S'], $args)));
        $t->same($expected === null ? null : substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', $args));
        $t->same($expected === null ? null : substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', $args));
    };
}

$tests['real upstream corpus date3 date3-5.0 auto first 1970 days ambiguity count'] = static function (TestRunner $t): void {
    $mismatchDays = [];
    for ($day = -10; $day <= 100; $day++) {
        $calendar = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', sprintf('%+d days', $day)]);
        $unixSeconds = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['1970-01-01', sprintf('%+d days', $day)]);
        $auto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$unixSeconds, 'auto']);
        if ($calendar !== $auto) {
            $mismatchDays[] = $day;
        }
    }

    $t->same(63, count($mismatchDays));
    $t->same(range(0, 62), $mismatchDays);
    $t->same('1969-12-22 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', '-10 days']));
    $t->same('1970-01-01 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [0, 'unixepoch']));
    $t->same('-4713-11-24 12:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [0, 'auto']));
    $t->same('1970-03-04 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', '+62 days']));
    $t->same('1970-03-05 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', '+63 days']));
    $t->same(SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [5443200, 'unixepoch']), SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [5443200, 'auto']));
};

for ($byte = 0; $byte <= 255; $byte++) {
    $hex = '4142ba32bfffff' . sprintf('%02x', $byte);
    $julianDay = unpack('E', hex2bin($hex))[1];

    $tests['real upstream corpus date boundary date.test date-14.2.' . $byte . ' avoids twenty four hour render'] = static function (TestRunner $t) use ($byte, $hex, $julianDay): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);

        $t->same(true, $actual === '2008-06-12 00:00:00' || $actual === '2008-06-11 23:59:59');
        $t->same(false, str_contains((string) $actual, '24:00:00'));
        $t->same(19, strlen((string) $actual));
        $t->same(true, in_array(substr((string) $actual, 0, 10), ['2008-06-11', '2008-06-12'], true));
        $t->same(true, $julianDay > 2454629.49999988 && $julianDay < 2454629.50000012);
        $t->same($hex, '4142ba32bfffff' . sprintf('%02x', $byte));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julianDay]));
        $t->same(substr((string) $actual, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]));
        $t->same(substr((string) $actual, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$julianDay]));
        $t->same((string) $actual, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $julianDay]));
        $t->same(true, $byte >= 0 && $byte <= 255);
        $t->same(2454629, (int) floor($julianDay));
        $t->same(true, is_float($julianDay));
        $t->same(1, preg_match('/\A2008-06-(?:11 23:59:59|12 00:00:00)\z/', (string) $actual));
    };
}

$tests['real upstream corpus date affinity dynamic application auto expiry boundaries'] = static function (TestRunner $t): void {
    $rows = [
        ['key_name' => 'early.epoch', 'raw_time' => 0],
        ['key_name' => 'safe.epoch', 'raw_time' => 5443200],
        ['key_name' => 'calendar.jd', 'raw_time' => 2459607.05260275],
        ['key_name' => 'far.future', 'raw_time' => 253402300799],
    ];

    $actual = [];
    foreach ($rows as $row) {
        $actual[$row['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['raw_time'], 'auto']);
    }

    $t->same([
        'early.epoch' => '-4713-11-24 12:00:00',
        'safe.epoch' => '1970-03-05 00:00:00',
        'calendar.jd' => '2022-01-27 13:15:44',
        'far.future' => '9999-12-31 23:59:59',
    ], $actual);
    $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$rows[2]['raw_time']]));
    $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$rows[1]['raw_time']]));
};

return $tests;

/**
 * @return array{0:string,1:string}
 */
function sqliteRealUpstreamDate3AutoBoundarySplitDateTime(string $dateTime): array
{
    $separator = strrpos($dateTime, ' ');
    if ($separator === false) {
        throw new InvalidArgumentException('SQLite date/time value does not contain a time separator');
    }

    return [substr($dateTime, 0, $separator), substr($dateTime, $separator + 1)];
}
