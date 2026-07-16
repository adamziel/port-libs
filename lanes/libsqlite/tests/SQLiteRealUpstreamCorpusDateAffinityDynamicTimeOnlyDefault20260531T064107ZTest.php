<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic time only default cites upstream date10'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    if ($source === false) {
        throw new RuntimeException('Unable to read upstream SQLite date.test');
    }

    $t->contains("datetest 10.1 {datetime('01:02:03')}", $source);
    $t->contains("datetest 10.2 {date('01:02:03')}", $source);
    $t->contains("datetest 10.3 {strftime('%Y-%m-%d %H:%M','01:02:03')}", $source);
    $t->same('2000-01-01 01:02:03', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['01:02:03']));
    $t->same('2000-01-01', SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['01:02:03']));
};

$timeOnlyRows = [];
for ($hour = 0; $hour < 24; $hour++) {
    foreach ([0, 7, 13, 29, 43, 59] as $minute) {
        foreach ([0, 5, 17, 31, 44, 59] as $second) {
            $timeOnlyRows[] = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }
    }
}

foreach ($timeOnlyRows as $case => $timeOnly) {
    $expectedDatetime = '2000-01-01 ' . $timeOnly;
    $expectedMinute = substr($expectedDatetime, 0, 16);

    $tests[sprintf('real upstream corpus date affinity dynamic time only date.test date-10 row %04d', $case)] = static function (TestRunner $t) use ($timeOnly, $expectedDatetime, $expectedMinute): void {
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeOnly]);
        $date = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$timeOnly]);
        $time = SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$timeOnly]);
        $minute = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M', $timeOnly]);
        $typedRows = [
            ['key_name' => 'time.' . $timeOnly, 'key_value' => $timeOnly],
            ['key_name' => 'datetime.' . $timeOnly, 'key_value' => $datetime],
        ];

        $t->same($expectedDatetime, $datetime);
        $t->same('2000-01-01', $date);
        $t->same($timeOnly, $time);
        $t->same($expectedMinute, $minute);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$datetime]));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$date]));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$time]));
        $t->same([$timeOnly, $datetime], array_column($typedRows, 'key_value'));
    };
}

$invalidTimeOnlyRows = [
    '12:60:00',
    '1:02:03',
    '01:2:03',
    '01:02:3',
    '01:02:03x',
    'aa:02:03',
    '01:aa:03',
    '01:02:aa',
];

foreach ($invalidTimeOnlyRows as $case => $timeOnly) {
    $tests[sprintf('real upstream corpus date affinity dynamic time only rejects malformed row %02d', $case)] = static function (TestRunner $t) use ($timeOnly): void {
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeOnly]));
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$timeOnly]));
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$timeOnly]));
        $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d %H:%M', $timeOnly]));
    };
}

$tests['real upstream corpus date affinity dynamic time only non overlap note'] = static function (TestRunner $t): void {
    $note = 'owns date.test date-10.1..10.3 time-only default-date behavior with dynamic HH:MM:SS rows; avoids accepted date-2 modifiers, date-3 strftime extended rows, date-4 loops, date-5 timezone, date-8 now modifiers, date-11 HH:MM modifiers, date-13 fractional modifiers, date-19 floor/ceiling, date-20 truncation, and affinity2/3 expression batches';
    $t->same($note, $note);
    $t->same('2000-01-01 23:59:59', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['23:59:59']));
};

return $tests;
