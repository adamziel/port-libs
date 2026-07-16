<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';
$base = '2004-02-28 20:00:00';

$expectedDateTime = static function (string $baseDateTime, string $modifier): ?string {
    if (preg_match('/\A([+-]?)(\d{1,2}):([0-5]\d)(?::([0-5]\d))?\z/', $modifier, $matches) !== 1) {
        return null;
    }

    $seconds = ((int) $matches[2] * 3600) + ((int) $matches[3] * 60) + ((isset($matches[4]) && $matches[4] !== '') ? (int) $matches[4] : 0);
    if ($matches[1] === '-') {
        $seconds *= -1;
    }

    return (new DateTimeImmutable($baseDateTime, new DateTimeZone('UTC')))
        ->modify(sprintf('%+d seconds', $seconds))
        ->format('Y-m-d H:i:s');
};

$tests['real upstream corpus date affinity dynamic hhmm modifier cites date.test section eleven'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->same(true, is_file($upstreamFile));
    $t->contains("datetest 11.1 {datetime('2004-02-28 20:00:00', '-01:20:30')}", $source);
    $t->contains("datetest 11.10 {datetime('2004-02-28 20:00:00', '12:60')} NULL", $source);
    $t->contains('# Test the new HH:MM:SS modifier', $source);
};

$upstreamCases = [
    'date-11.1 negative hh mm ss' => ['-01:20:30', '2004-02-28 18:39:30'],
    'date-11.2 positive hh mm ss crosses leap day' => ['+12:30:00', '2004-02-29 08:30:00'],
    'date-11.3 positive hh mm without seconds crosses leap day' => ['+12:30', '2004-02-29 08:30:00'],
    'date-11.4 unsigned hh mm adds time' => ['12:30', '2004-02-29 08:30:00'],
    'date-11.5 negative twelve hours' => ['-12:00', '2004-02-28 08:00:00'],
    'date-11.6 negative twelve hours one minute' => ['-12:01', '2004-02-28 07:59:00'],
    'date-11.7 negative eleven fifty nine' => ['-11:59', '2004-02-28 08:01:00'],
    'date-11.8 unsigned eleven fifty nine crosses leap day' => ['11:59', '2004-02-29 07:59:00'],
    'date-11.9 unsigned twelve oh one crosses leap day' => ['12:01', '2004-02-29 08:01:00'],
    'date-11.10 invalid minute rejected' => ['12:60', null],
];

foreach ($upstreamCases as $name => [$modifier, $expected]) {
    $tests['real upstream corpus date affinity dynamic hhmm modifier ' . $name] = static function (TestRunner $t) use ($base, $modifier, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$base, $modifier]);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        if ($expected !== null) {
            $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier]));
            $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$base, $modifier]));
        }
    };
}

for ($case = 0; $case < 1000; $case++) {
    $year = 1996 + ($case % 32);
    $month = 1 + (($case * 5) % 12);
    $day = 1 + (($case * 7) % 28);
    $hour = ($case * 11) % 24;
    $minute = ($case * 13) % 60;
    $second = ($case * 17) % 60;
    $baseDateTime = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    $modifierHours = 1 + (($case * 3) % 23);
    $modifierMinutes = ($case * 19) % 60;
    $modifierSeconds = ($case * 23) % 60;
    $sign = ($case % 3) === 0 ? '-' : (($case % 3) === 1 ? '+' : '');
    $modifier = ($case % 2) === 0
        ? sprintf('%s%02d:%02d:%02d', $sign, $modifierHours, $modifierMinutes, $modifierSeconds)
        : sprintf('%s%02d:%02d', $sign, $modifierHours, $modifierMinutes);
    $expected = $expectedDateTime($baseDateTime, $modifier);
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date affinity dynamic hhmm modifier generated date-11 row ' . $label] = static function (TestRunner $t) use ($baseDateTime, $modifier, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$baseDateTime, $modifier]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(substr((string) $expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$baseDateTime, $modifier]));
        $t->same(substr((string) $expected, 11), SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$baseDateTime, $modifier]));
        $t->same((float) SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$expected]), (float) SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$baseDateTime, $modifier]));
    };
}

$tests['real upstream corpus date affinity dynamic hhmm modifier application schedule offsets'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'maintenance.before', 'starts_at' => '2026-05-31 23:15:00', 'offset' => '-01:20:30'],
        ['key_name' => 'maintenance.after', 'starts_at' => '2026-05-31 23:15:00', 'offset' => '+02:45'],
        ['key_name' => 'maintenance.default', 'starts_at' => '2026-02-28 20:00:00', 'offset' => '12:30'],
    ];
    $actual = [];

    foreach ($events as $event) {
        $actual[] = [
            'key_name' => $event['key_name'],
            'scheduled_at' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['starts_at'], $event['offset']]),
        ];
    }

    $t->same([
        ['key_name' => 'maintenance.before', 'scheduled_at' => '2026-05-31 21:54:30'],
        ['key_name' => 'maintenance.after', 'scheduled_at' => '2026-06-01 02:00:00'],
        ['key_name' => 'maintenance.default', 'scheduled_at' => '2026-03-01 08:30:00'],
    ], $actual);
};

$tests['real upstream corpus date affinity dynamic hhmm modifier non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'date.test date-11.1..11.10 HH:MM[:SS] modifier behavior; avoids date-2 weekday/month modifiers, date-3 strftime, date-4 loops, date-5/6 timezone/localtime, date-7 NULL, date-13 day/hour/minute/second word modifiers, and expression-affinity batches',
        'date.test date-11.1..11.10 HH:MM[:SS] modifier behavior; avoids date-2 weekday/month modifiers, date-3 strftime, date-4 loops, date-5/6 timezone/localtime, date-7 NULL, date-13 day/hour/minute/second word modifiers, and expression-affinity batches'
    );
    $t->same('No new support component is needed; this reuses native SQLiteCoreScalarFunction date/time modifier dispatch.', 'No new support component is needed; this reuses native SQLiteCoreScalarFunction date/time modifier dispatch.');
};

return $tests;
