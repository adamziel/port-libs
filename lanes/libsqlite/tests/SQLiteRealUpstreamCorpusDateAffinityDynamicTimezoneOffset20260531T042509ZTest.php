<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic timezone offset cites date.test section five'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

    $t->same(true, is_file($source));
    $contents = (string) file_get_contents($source);
    $t->contains("datetest 5.1 {datetime('1994-04-16 14:00:00 +05:00')}", $contents);
    $t->contains("datetest 5.15 {datetime('1994-04-16 14:00:00 +05:00 Z')} NULL", $contents);
};

$fixedCases = [
    'date-5.1 positive whole-hour offset' => ['1994-04-16 14:00:00 +05:00', '1994-04-16 09:00:00'],
    'date-5.2 negative hour-minute offset' => ['1994-04-16 14:00:00 -05:15', '1994-04-16 19:15:00'],
    'date-5.3 positive half-hour offset crosses previous day' => ['1994-04-16 05:00:00 +08:30', '1994-04-15 20:30:00'],
    'date-5.4 negative offset crosses next day' => ['1994-04-16 14:00:00 -11:55', '1994-04-17 01:55:00'],
    'date-5.5 invalid timezone minute rejected' => ['1994-04-16 14:00:00 -11:60', null],
    'date-5.6 trailing spaces after offset accepted' => ['1994-04-16 14:00:00 -11:55  ', '1994-04-17 01:55:00'],
    'date-5.7 trailing token after offset rejected' => ['1994-04-16 14:00:00 -11:55 x', null],
    'date-5.8 uppercase zulu suffix accepted' => ['1994-04-16T14:00:00Z', '1994-04-16 14:00:00'],
    'date-5.9 lowercase zulu suffix accepted' => ['1994-04-16 14:00:00z', '1994-04-16 14:00:00'],
    'date-5.10 separated uppercase zulu suffix accepted' => ['1994-04-16 14:00:00 Z', '1994-04-16 14:00:00'],
    'date-5.11 zulu suffix with trailing spaces accepted' => ['1994-04-16 14:00:00z    ', '1994-04-16 14:00:00'],
    'date-5.12 separated zulu suffix with extra spaces accepted' => ['1994-04-16 14:00:00     z    ', '1994-04-16 14:00:00'],
    'date-5.13 zulu word rejected' => ['1994-04-16 14:00:00Zulu', null],
    'date-5.14 zulu and numeric offset rejected' => ['1994-04-16 14:00:00Z +05:00', null],
    'date-5.15 numeric offset and zulu rejected' => ['1994-04-16 14:00:00 +05:00 Z', null],
];

foreach ($fixedCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date affinity dynamic timezone offset ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$expectedOffset = static function (string $stamp, int $offsetMinutes): string {
    $sign = $offsetMinutes >= 0 ? '+' : '-';
    $absolute = abs($offsetMinutes);
    $value = sprintf(
        '%s %s%02d:%02d',
        $stamp,
        $sign,
        intdiv($absolute, 60),
        $absolute % 60
    );

    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
};

for ($case = 0; $case < 1000; $case++) {
    $year = 1972 + ($case % 96);
    $month = 1 + (($case * 7) % 12);
    $day = 1 + (($case * 11) % 28);
    $hour = ($case * 5) % 24;
    $minute = ($case * 13) % 60;
    $second = ($case * 17) % 60;
    $offsetMinutes = (($case * 29) % 1439) - 719;
    if ($offsetMinutes === 0) {
        $offsetMinutes = 345;
    }
    $stamp = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    $sign = $offsetMinutes >= 0 ? '+' : '-';
    $absolute = abs($offsetMinutes);
    $value = sprintf('%s %s%02d:%02d', $stamp, $sign, intdiv($absolute, 60), $absolute % 60);
    $expected = $expectedOffset($stamp, $offsetMinutes);
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date affinity dynamic timezone offset date.test date-5 generated nonzero row ' . $label] = static function (TestRunner $t) use ($value, $expected, $offsetMinutes): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(19, strlen((string) $actual));
        $t->same($offsetMinutes !== 0, str_contains($value, '+') || str_contains($value, '-'));
    };
}

$tests['real upstream corpus date affinity dynamic timezone offset application schedule canonicalizes offsets'] = static function (TestRunner $t): void {
    $rows = [
        ['key_name' => 'release.east', 'local_start' => '2026-05-31 09:30:00 +05:45'],
        ['key_name' => 'release.west', 'local_start' => '2026-05-31 09:30:00 -07:30'],
        ['key_name' => 'release.zulu', 'local_start' => '2026-05-31 09:30:00Z'],
    ];
    $actual = [];
    foreach ($rows as $row) {
        $actual[] = [
            'key_name' => $row['key_name'],
            'stored_utc' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['local_start']]),
        ];
    }

    $t->same([
        ['key_name' => 'release.east', 'stored_utc' => '2026-05-31 03:45:00'],
        ['key_name' => 'release.west', 'stored_utc' => '2026-05-31 17:00:00'],
        ['key_name' => 'release.zulu', 'stored_utc' => '2026-05-31 09:30:00'],
    ], $actual);
};

return $tests;
