<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';
$localtimeRules = [
    ['utcStart' => '0000-01-01 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '2000-10-29 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '2000-10-30 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '2022-02-11 00:00:00', 'offsetMinutes' => 30],
    ['utcStart' => '2022-02-12 00:00:00', 'offsetMinutes' => -30],
    ['utcStart' => '3000-10-30 00:00:00', 'offsetMinutes' => -30],
];

$tests['real upstream date affinity dynamic localtime chain cites upstream date sections'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->same(true, is_file($upstreamFile));
    $t->contains("do_execsql_test date-6.28", $source);
    $t->contains("SELECT datetime('2000-10-29 12:00:00Z', 'localtime');", $source);
    $t->contains("do_execsql_test date-6.32", $source);
    $t->contains("SELECT datetime('2000-10-29 12:00:00Z', 'localtime','localtime');", $source);
    $t->contains("do_catchsql_test date-6.20", $source);
    $t->contains("SELECT datetime('2000-05-29 14:16:00','localtime');", $source);
};

$chainCases = [
    'date-6.28 zulu to localtime' => [
        ['2000-10-29 12:00:00Z', 'localtime'],
        '2000-10-29 12:30:00',
    ],
    'date-6.29 zulu utc then localtime' => [
        ['2000-10-29 12:00:00Z', 'utc', 'localtime'],
        '2000-10-29 12:30:00',
    ],
    'date-6.30 zulu utc localtime utc round trip' => [
        ['2000-10-29 12:00:00Z', 'utc', 'localtime', 'utc'],
        '2000-10-29 12:00:00',
    ],
    'date-6.31 zulu utc localtime utc localtime round trip' => [
        ['2000-10-29 12:00:00Z', 'utc', 'localtime', 'utc', 'localtime'],
        '2000-10-29 12:30:00',
    ],
    'date-6.32 repeated localtime is idempotent after first application' => [
        ['2000-10-29 12:00:00Z', 'localtime', 'localtime'],
        '2000-10-29 12:30:00',
    ],
];

foreach ($chainCases as $name => [$arguments, $expected]) {
    $tests['real upstream date affinity dynamic localtime chain ' . $name] = static function (TestRunner $t) use ($arguments, $expected, $localtimeRules): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('datetime', $arguments, $localtimeRules);

        $t->same($expected, $actual);
        $t->same(substr($expected, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('date', $arguments, $localtimeRules));
        $t->same(substr($expected, 11), SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('time', $arguments, $localtimeRules));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream date affinity dynamic localtime chain propagates localtime failure'] = static function (TestRunner $t): void {
    $rules = [
        [
            'utcStart' => '0000-01-01 00:00:00',
            'offsetMinutes' => 30,
            'failAtUtc' => '2000-05-29 14:16:00',
        ],
    ];

    $t->throws(
        RuntimeException::class,
        static fn (): mixed => SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('datetime', ['2000-05-29 14:16:00', 'localtime'], $rules)
    );
};

$dynamicInstants = [];
$base = new DateTimeImmutable('2000-10-30 00:00:00', new DateTimeZone('UTC'));
for ($index = 0; $index < 500; $index++) {
    $dynamicInstants[] = $base->modify(sprintf('+%d minutes', $index * 17));
}

foreach ($dynamicInstants as $index => $instant) {
    $label = sprintf('%03d', $index);
    $zulu = $instant->format('Y-m-d H:i:s') . 'Z';
    $expectedLocal = $instant->modify('-30 minutes')->format('Y-m-d H:i:s');
    $expectedUtc = $instant->format('Y-m-d H:i:s');

    $tests['real upstream date affinity dynamic localtime chain generated zulu local row ' . $label] = static function (TestRunner $t) use ($zulu, $expectedLocal, $expectedUtc, $localtimeRules): void {
        $local = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('datetime', [$zulu, 'localtime'], $localtimeRules);
        $roundTrip = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('datetime', [$zulu, 'localtime', 'utc'], $localtimeRules);
        $idempotent = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('datetime', [$zulu, 'localtime', 'localtime'], $localtimeRules);

        $t->same($expectedLocal, $local);
        $t->same($expectedUtc, $roundTrip);
        $t->same($expectedLocal, $idempotent);
        $t->same(substr($expectedLocal, 0, 10), SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('date', [$zulu, 'localtime'], $localtimeRules));
        $t->same(substr($expectedLocal, 11), SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('time', [$zulu, 'localtime'], $localtimeRules));
    };
}

$tests['real upstream date affinity dynamic localtime chain generic application expiry schedule'] = static function (TestRunner $t) use ($localtimeRules): void {
    $rows = [
        ['key_name' => 'alpha', 'expires_at_utc' => '2000-10-29 12:00:00Z'],
        ['key_name' => 'beta', 'expires_at_utc' => '2000-10-29 12:17:00Z'],
        ['key_name' => 'gamma', 'expires_at_utc' => '2000-10-29 23:42:00Z'],
    ];
    $actual = [];

    foreach ($rows as $row) {
        $actual[$row['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules(
            'datetime',
            [$row['expires_at_utc'], 'localtime', 'utc'],
            $localtimeRules
        );
    }

    $t->same([
        'alpha' => '2000-10-29 12:00:00',
        'beta' => '2000-10-29 12:17:00',
        'gamma' => '2000-10-30 00:42:00',
    ], $actual);
};

return $tests;
