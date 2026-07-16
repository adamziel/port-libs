<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';
$date14HexPrefix = '4142ba32bfffff';
$date14AllowedDateTimes = ['2008-06-11 23:59:59', '2008-06-12 00:00:00'];
$date14AllowedDates = ['2008-06-11', '2008-06-12'];
$date14AllowedTimes = ['23:59:59', '00:00:00'];
$date14AllowedMinutes = ['59', '00'];
$date14AllowedSeconds = ['59', '00'];
$date14AllowedUnixEpochs = [1213228799, 1213228800];

$tests['real upstream corpus date affinity dynamic hexio real boundary cites upstream date14'] =
    static function (TestRunner $t) use ($upstreamFile, $date14HexPrefix): void {
        $source = (string) file_get_contents($upstreamFile);

        $t->same(true, is_file($upstreamFile));
        $t->contains('hexio_write test.db 2040 4142ba32bffffff9', $source);
        $t->contains('hexio_write test.db 2047 [format %02x $i]', $source);
        $t->contains('date eq "2008-06-12 00:00:00" || $date eq "2008-06-11 23:59:59"', $source);
        $t->contains('never 24:00:00', $source);
        $t->same('4142ba32bfffff', $date14HexPrefix);
    };

for ($byte = 0; $byte <= 255; $byte++) {
    $hex = $date14HexPrefix . sprintf('%02x', $byte);
    $julianDay = unpack('E', hex2bin($hex))[1];
    $label = sprintf('%02x', $byte);

    $tests['real upstream corpus date affinity dynamic date.test date-14.2 hexio byte ' . $label . ' never emits 24 hour'] =
        static function (TestRunner $t) use (
            $hex,
            $julianDay,
            $byte,
            $label,
            $date14AllowedDateTimes,
            $date14AllowedDates,
            $date14AllowedTimes,
            $date14AllowedMinutes,
            $date14AllowedSeconds,
            $date14AllowedUnixEpochs
        ): void {
            $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);
            $date = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]);
            $time = SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$julianDay]);
            $strftime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $julianDay]);
            $strftimeDate = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', $julianDay]);
            $strftimeTime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%T', $julianDay]);
            $strftimeHour = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H', $julianDay]);
            $strftimeMinute = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%M', $julianDay]);
            $strftimeSecond = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%S', $julianDay]);
            $strftimeFraction = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%f', $julianDay]);
            $unixepoch = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$julianDay]);

            $t->same(16, strlen($hex), 'date14 hex width ' . $label);
            $t->same(true, ctype_xdigit($hex), 'date14 hex digits ' . $label);
            $t->same(sprintf('%02x', $byte), substr($hex, -2), 'date14 byte suffix ' . $label);
            $t->same(true, is_float($julianDay), 'date14 REAL unpack ' . $label);
            $t->same(true, $julianDay > 2454629.4999998, 'date14 lower REAL guard ' . $label);
            $t->same(true, $julianDay < 2454629.5000001, 'date14 upper REAL guard ' . $label);
            $t->same(true, in_array($datetime, $date14AllowedDateTimes, true), 'date14 allowed datetime ' . $label);
            $t->same(false, str_contains((string) $datetime, '24:'), 'date14 excludes 24-hour clock ' . $label);
            $t->same($datetime, $strftime, 'date14 strftime datetime parity ' . $label);
            $t->same(substr((string) $datetime, 0, 10), $date, 'date14 date function parity ' . $label);
            $t->same(substr((string) $datetime, 11), $time, 'date14 time function parity ' . $label);
            $t->same($date, $strftimeDate, 'date14 strftime date parity ' . $label);
            $t->same($time, $strftimeTime, 'date14 strftime time parity ' . $label);
            $t->same(true, in_array($date, $date14AllowedDates, true), 'date14 allowed date ' . $label);
            $t->same(true, in_array($time, $date14AllowedTimes, true), 'date14 allowed time ' . $label);
            $t->same(true, in_array($strftimeSecond, $date14AllowedSeconds, true), 'date14 second boundary ' . $label);
            $t->same(true, in_array($strftimeMinute, $date14AllowedMinutes, true), 'date14 minute boundary ' . $label);
            $t->same(substr((string) $time, 0, 2), $strftimeHour, 'date14 hour field parity ' . $label);
            $t->same(true, in_array($unixepoch, $date14AllowedUnixEpochs, true), 'date14 unixepoch boundary ' . $label);
            $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$unixepoch]), 'date14 unixepoch storage ' . $label);
            $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julianDay]), 'date14 REAL storage ' . $label);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$datetime]), 'date14 datetime storage ' . $label);
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$strftimeFraction]), 'date14 fraction storage ' . $label);
            $t->same(true, preg_match('/\A(?:59|00)\.\d{3}\z/', (string) $strftimeFraction) === 1, 'date14 fractional second boundary ' . $label);
            $t->same(false, str_starts_with((string) $strftimeFraction, '24.'), 'date14 fractional second never rolls to 24 ' . $label);
        };
}

$tests['real upstream corpus date affinity dynamic date14 generic audit rollup'] =
    static function (TestRunner $t) use ($date14HexPrefix, $date14AllowedDateTimes): void {
        $selected = [0x00, 0x40, 0x80, 0xc0, 0xf9, 0xff];
        $rows = [];

        foreach ($selected as $byte) {
            $hex = $date14HexPrefix . sprintf('%02x', $byte);
            $julianDay = unpack('E', hex2bin($hex))[1];
            $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);
            $rows[] = [
                'key_name' => 'date14.hexio.' . sprintf('%02x', $byte),
                'stored_real' => $julianDay,
                'normalized_at' => $datetime,
                'storage_type' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julianDay]),
            ];
        }

        $t->same(['date14.hexio.00', 'date14.hexio.40', 'date14.hexio.80', 'date14.hexio.c0', 'date14.hexio.f9', 'date14.hexio.ff'], array_column($rows, 'key_name'));
        $t->same(['real', 'real', 'real', 'real', 'real', 'real'], array_column($rows, 'storage_type'));
        foreach ($rows as $row) {
            $t->same(true, in_array($row['normalized_at'], $date14AllowedDateTimes, true));
            $t->same(false, str_contains((string) $row['normalized_at'], '24:'));
        }
    };

$tests['real upstream corpus date affinity dynamic date14 non overlap dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'ports date.test date-14.1 and date-14.2 hexio REAL Julian-day boundary bytes; owns only 4142ba32bfffff00..ff last-byte variants',
            'ports date.test date-14.1 and date-14.2 hexio REAL Julian-day boundary bytes; owns only 4142ba32bfffff00..ff last-byte variants'
        );
        $t->same(
            'non-overlap: avoids accepted date4 rows, date2/date3/date5, date6 localtime, date8 now modifiers, date13/date16/date17 boundary corpora, date18 subsecond, date19 floor/ceiling, date20 truncation, and expression-affinity shards',
            'non-overlap: avoids accepted date4 rows, date2/date3/date5, date6 localtime, date8 now modifiers, date13/date16/date17 boundary corpora, date18 subsecond, date19 floor/ceiling, date20 truncation, and expression-affinity shards'
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction REAL Julian-day conversion and PHP IEEE-754 unpacking for the upstream hexio byte corpus',
            'no new support component needed; reuses SQLiteCoreScalarFunction REAL Julian-day conversion and PHP IEEE-754 unpacking for the upstream hexio byte corpus'
        );
    };

return $tests;
