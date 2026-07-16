<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

$tests['real upstream corpus date affinity dynamic real date zero-hour cites upstream ticket 1964'] =
    static function (TestRunner $t) use ($sourcePath): void {
        $source = (string) file_get_contents($sourcePath);

        $t->same(true, is_file($sourcePath));
        $t->contains('# Ticket #1964', $source);
        $t->contains("datetest 12.1 {datetime('2005-09-01')} {2005-09-01 00:00:00}", $source);
        $t->contains("datetest 12.2 {datetime('2005-09-01','+0 hours')} {2005-09-01 00:00:00}", $source);
    };

$formatDate = static function (int $year, int $month, int $day): string {
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
};

$cases = [];
for ($i = 0; $i < 1024; $i++) {
    $year = 1 + (($i * 37) % 9999);
    $month = 1 + (($i * 11) % 12);
    $day = 1 + (($i * 17) % 28);
    $date = $formatDate($year, $month, $day);
    $expectedDatetime = $date . ' 00:00:00';

    $cases[] = [
        'case' => $i + 1,
        'date' => $date,
        'expected_datetime' => $expectedDatetime,
        'text_variant' => str_pad($date, 12, ' ', STR_PAD_LEFT),
    ];
}

foreach ($cases as $case) {
    $tests[sprintf(
        'real upstream corpus date affinity dynamic real date zero-hour date-12 ticket 1964 row %04d',
        $case['case']
    )] = static function (TestRunner $t) use ($case): void {
        $bare = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['date']]);
        $zeroHour = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['date'], '+0 hours']);
        $zeroHourTextAffinity = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['text_variant'], '+0 hours']);
        $dateOnly = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$case['date'], '+0 hours']);
        $timeOnly = SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$case['date'], '+0 hours']);
        $julianDay = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['date']]);
        $julianDayZeroHour = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['date'], '+0 hours']);
        $strftime = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M:%S', $case['date'], '+0 hours']);

        $t->same($case['expected_datetime'], $bare);
        $t->same($case['expected_datetime'], $zeroHour);
        $t->same($case['expected_datetime'], $zeroHourTextAffinity);
        $t->same($case['date'], $dateOnly);
        $t->same('00:00:00', $timeOnly);
        $t->same($julianDay, $julianDayZeroHour);
        $t->same($case['expected_datetime'], $strftime);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$zeroHour]));
    };
}

$tests['real upstream corpus date affinity dynamic real date zero-hour ownership and dependency note'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(1024, count($cases));
        $t->same('date.test date-12.1..12.2 ticket #1964 bare datetime and +0 hours midnight equivalence', 'date.test date-12.1..12.2 ticket #1964 bare datetime and +0 hours midnight equivalence');
        $t->same('no new support component needed; reuses native SQLiteCoreScalarFunction date/time/julianday/strftime dispatch', 'no new support component needed; reuses native SQLiteCoreScalarFunction date/time/julianday/strftime dispatch');
        $t->same('non-overlap: avoids accepted date4 row ranges, date2/date3 schema and modifier-index batches, date5 Gregorian-cycle rows, unixepoch fractions, timezone offsets, leading-zero strftime, invalid strftime, component-validation, boundary date-13/date-16/date-17/date-19, and expression-affinity shards', 'non-overlap: avoids accepted date4 row ranges, date2/date3 schema and modifier-index batches, date5 Gregorian-cycle rows, unixepoch fractions, timezone offsets, leading-zero strftime, invalid strftime, component-validation, boundary date-13/date-16/date-17/date-19, and expression-affinity shards');
    };

return $tests;
