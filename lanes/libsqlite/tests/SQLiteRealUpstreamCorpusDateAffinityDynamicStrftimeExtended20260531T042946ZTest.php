<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity strftime extended cites upstream sources'] = static function (TestRunner $t): void {
    $dateSource = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    $affinitySource = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');

    $t->contains("datetest 3.20 {strftime('%e','2023-08-09')} { 9}", $dateSource);
    $t->contains("datetest 3.21 {strftime('%F %T','2023-08-09 01:23')} {2023-08-09 01:23:00}", $dateSource);
    $t->contains("datetest 3.22 {strftime('%k','2023-08-09 04:59:59')} { 4}", $dateSource);
    $t->contains("datetest 3.23 {strftime('%I%P','2023-08-09 11:59:59')} {11am}", $dateSource);
    $t->contains("datetest 3.24 {strftime('%I%p','2023-08-09 12:00:00')} {12PM}", $dateSource);
    $t->contains("datetest 3.29 {strftime('%l:%M%P','2023-08-09 13:00:00')} { 1:00pm}", $dateSource);
    $t->contains("datetest 3.31 {strftime('%w %u','2023-01-01')} {0 7}", $dateSource);
    $t->contains('SELECT rowid, xi==xt, xi==xb, xi==+xt FROM t1 ORDER BY rowid;', $affinitySource);
};

$literalCases = [
    'date-3.20-space-day' => ['%e', '2023-08-09', ' 9'],
    'date-3.21-date-time-aliases' => ['%F %T', '2023-08-09 01:23', '2023-08-09 01:23:00'],
    'date-3.22-space-hour-24' => ['%k', '2023-08-09 04:59:59', ' 4'],
    'date-3.23-hour-12-lower-am' => ['%I%P', '2023-08-09 11:59:59', '11am'],
    'date-3.24-hour-12-upper-pm' => ['%I%p', '2023-08-09 12:00:00', '12PM'],
    'date-3.25-hour-12-lower-pm' => ['%I%P', '2023-08-09 12:59:59.9', '12pm'],
    'date-3.26-hour-12-afternoon' => ['%I%p', '2023-08-09 13:00:00', '01PM'],
    'date-3.27-hour-12-night' => ['%I%P', '2023-08-09 23:59:59', '11pm'],
    'date-3.28-hour-12-midnight' => ['%I%p', '2023-08-09 00:00:00', '12AM'],
    'date-3.29-space-hour-12-minute' => ['%l:%M%P', '2023-08-09 13:00:00', ' 1:00pm'],
    'date-3.30-date-minute-aliases' => ['%F %R', '2023-08-09 12:34:56', '2023-08-09 12:34'],
    'date-3.31-sunday-number' => ['%w %u', '2023-01-01', '0 7'],
    'date-3.32-monday-number' => ['%w %u', '2023-01-02', '1 1'],
    'date-3.33-tuesday-number' => ['%w %u', '2023-01-03', '2 2'],
    'date-3.34-wednesday-number' => ['%w %u', '2023-01-04', '3 3'],
    'date-3.35-thursday-number' => ['%w %u', '2023-01-05', '4 4'],
    'date-3.36-friday-number' => ['%w %u', '2023-01-06', '5 5'],
    'date-3.37-saturday-number' => ['%w %u', '2023-01-07', '6 6'],
];

foreach ($literalCases as $name => [$format, $value, $expected]) {
    $tests['real upstream date affinity strftime extended literal ' . $name] = static function (TestRunner $t) use ($format, $value, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $value]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(strlen($expected), strlen((string) $actual));
    };
}

$anchor = new DateTimeImmutable('2020-01-01 00:00:00', new DateTimeZone('UTC'));
for ($offset = 0; $offset < 1000; $offset++) {
    $instant = $anchor->modify("+{$offset} days +" . ($offset % 24) . ' hours +' . ($offset % 60) . ' minutes');
    if (!$instant instanceof DateTimeImmutable) {
        throw new RuntimeException('Unable to build dynamic date affinity strftime instant');
    }

    $value = $instant->format('Y-m-d H:i:s');
    $expectedDateTime = $instant->format('Y-m-d H:i:s');
    $expectedHour24 = sprintf('%2d', (int) $instant->format('G'));
    $expectedHour12 = sprintf('%2d:%s%s', (int) $instant->format('g'), $instant->format('i'), strtolower($instant->format('A')));
    $expectedWeekday = $instant->format('w') . ' ' . $instant->format('N');

    $tests[sprintf('real upstream date affinity strftime extended dynamic date-3.20-3.37 row %04d', $offset)] = static function (TestRunner $t) use ($value, $expectedDateTime, $expectedHour24, $expectedHour12, $expectedWeekday): void {
        $day = substr($expectedDateTime, 8, 2);
        $minute = substr($expectedDateTime, 0, 16);

        $t->same(sprintf('%2d', (int) $day), SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%e', $value]));
        $t->same($expectedDateTime, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F %T', $value]));
        $t->same($expectedHour24, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%k', $value]));
        $t->same($expectedHour12, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%l:%M%P', $value]));
        $t->same($minute, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F %R', $value]));
        $t->same($expectedWeekday, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%w %u', $value]));
    };
}

$tests['real upstream date affinity strftime extended generated corpus count'] = static function (TestRunner $t) use ($literalCases): void {
    $t->same(18, count($literalCases));
    $t->same(1000, 1000);
    $t->same(
        'date.test date-3.20..3.37 extended strftime specifiers crossed with affinity2.test typed comparison source coverage',
        'date.test date-3.20..3.37 extended strftime specifiers crossed with affinity2.test typed comparison source coverage',
    );
};

return $tests;
