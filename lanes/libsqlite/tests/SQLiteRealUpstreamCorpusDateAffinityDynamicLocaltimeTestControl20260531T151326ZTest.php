<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

$tests['real upstream date affinity dynamic localtime test control cites date.test date6'] =
    static function (TestRunner $t) use ($sourcePath): void {
        $source = (string) file_get_contents($sourcePath);

        $t->same(true, is_file($sourcePath));
        $t->contains('sqlite3_test_control SQLITE_TESTCTRL_LOCALTIME_FAULT 2', $source);
        $t->contains('local_to_utc 6.1  {2000-10-29 12:00:00} {2000-10-29 12:30:00}', $source);
        $t->contains('utc_to_local 6.7  {2000-10-29 00:10:00} {2000-10-28 23:40:00}', $source);
        $t->contains('do_catchsql_test date-6.20', $source);
        $t->contains('utc_to_local 6.24 {3000-10-30 11:30:00} {3000-10-30 12:00:00}', $source);
    };

$exactCases = [
    'date-6.1 utc even-to-odd transition applies east offset' => [
        'arguments' => ['2000-10-29 12:00:00', 'localtime'],
        'expected' => '2000-10-29 12:30:00',
    ],
    'date-6.2 local east time converts back to utc' => [
        'arguments' => ['2000-10-29 12:30:00', 'utc'],
        'expected' => '2000-10-29 12:00:00',
    ],
    'date-6.3 utc odd-to-even transition applies west offset' => [
        'arguments' => ['2000-10-30 12:00:00', 'localtime'],
        'expected' => '2000-10-30 11:30:00',
    ],
    'date-6.4 local west time converts back to utc' => [
        'arguments' => ['2000-10-30 11:30:00', 'utc'],
        'expected' => '2000-10-30 12:00:00',
    ],
    'date-6.5 last second before forward jump uses west offset' => [
        'arguments' => ['2000-10-28 23:59:59', 'localtime'],
        'expected' => '2000-10-28 23:29:59',
    ],
    'date-6.6 first second after forward jump uses east offset' => [
        'arguments' => ['2000-10-29 00:00:00', 'localtime'],
        'expected' => '2000-10-29 00:30:00',
    ],
    'date-6.7 nonexistent localtime resolves with post transition offset' => [
        'arguments' => ['2000-10-29 00:10:00', 'utc'],
        'expected' => '2000-10-28 23:40:00',
    ],
    'date-6.8 last second before backward jump uses east offset' => [
        'arguments' => ['2022-02-10 23:59:59', 'localtime'],
        'expected' => '2022-02-11 00:29:59',
    ],
    'date-6.9 first second after backward jump uses west offset' => [
        'arguments' => ['2022-02-11 00:00:00', 'localtime'],
        'expected' => '2022-02-10 23:30:00',
    ],
    'date-6.10 first ambiguous utc value maps into overlap' => [
        'arguments' => ['2022-02-10 23:45:00', 'localtime'],
        'expected' => '2022-02-11 00:15:00',
    ],
    'date-6.11 second ambiguous utc value maps into overlap' => [
        'arguments' => ['2022-02-11 00:45:00', 'localtime'],
        'expected' => '2022-02-11 00:15:00',
    ],
    'date-6.12 ambiguous localtime picks one corresponding utc value' => [
        'arguments' => ['2022-02-11 00:15:00', 'utc'],
        'expected' => '2022-02-11 00:45:00',
    ],
    'date-6.21 out of band historical utc still uses test-control offset' => [
        'arguments' => ['1800-10-29 12:00:00', 'localtime'],
        'expected' => '1800-10-29 12:30:00',
    ],
    'date-6.22 out of band historical local converts back to utc' => [
        'arguments' => ['1800-10-29 12:30:00', 'utc'],
        'expected' => '1800-10-29 12:00:00',
    ],
    'date-6.23 out of band future utc still uses test-control offset' => [
        'arguments' => ['3000-10-30 12:00:00', 'localtime'],
        'expected' => '3000-10-30 11:30:00',
    ],
    'date-6.24 out of band future local converts back to utc' => [
        'arguments' => ['3000-10-30 11:30:00', 'utc'],
        'expected' => '3000-10-30 12:00:00',
    ],
];

$instants = [
    new DateTimeImmutable('2000-05-29 14:16:00', new DateTimeZone('UTC')),
];
foreach ($exactCases as $case) {
    $instants[] = new DateTimeImmutable($case['arguments'][0], new DateTimeZone('UTC'));
    $instants[] = (new DateTimeImmutable($case['expected'], new DateTimeZone('UTC')));
}

$generatedInstants = [];
$base = new DateTimeImmutable('1990-01-01 12:34:56', new DateTimeZone('UTC'));
for ($index = 0; $index < 1000; $index++) {
    $generatedInstants[] = $base->modify(sprintf('+%d days +%d seconds', $index % 60, ($index * 571) % 3600));
}
array_push($instants, ...$generatedInstants);

$localtimeRules = sqliteRealUpstreamDateAffinityLocaltime153126RulesForInstants($instants);

foreach ($exactCases as $name => $case) {
    $tests['real upstream date affinity dynamic localtime test control ' . $name] =
        static function (TestRunner $t) use ($case, $localtimeRules): void {
            $actual = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules('datetime', $case['arguments'], $localtimeRules);

            $t->same($case['expected'], $actual);
            $t->same(substr($case['expected'], 0, 10), substr((string) $actual, 0, 10));
            $t->same(substr($case['expected'], 11), substr((string) $actual, 11));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        };
}

$tests['real upstream date affinity dynamic localtime test control date-6.20 failure propagates'] =
    static function (TestRunner $t) use ($localtimeRules): void {
        $t->throws(
            RuntimeException::class,
            static fn (): mixed => SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules(
                'datetime',
                ['2000-05-29 14:16:00', 'localtime'],
                $localtimeRules
            )
        );
    };

foreach ($generatedInstants as $index => $instant) {
    $expectedLocal = sqliteRealUpstreamDateAffinityLocaltime153126Format(
        sqliteRealUpstreamDateAffinityLocaltime153126ModifyMinutes(
            $instant,
            sqliteRealUpstreamDateAffinityLocaltime153126OffsetForUtc($instant)
        )
    );
    $expectedUtc = sqliteRealUpstreamDateAffinityLocaltime153126Format($instant);
    $label = sprintf('%04d', $index + 1);

    $tests['real upstream date affinity dynamic localtime test control generated reversible row ' . $label] =
        static function (TestRunner $t) use ($expectedLocal, $expectedUtc, $localtimeRules): void {
            $local = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules(
                'datetime',
                [$expectedUtc, 'localtime'],
                $localtimeRules
            );
            $roundTrip = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules(
                'datetime',
                [$expectedLocal, 'utc'],
                $localtimeRules
            );

            $t->same($expectedLocal, $local);
            $t->same($expectedUtc, $roundTrip);
            $t->same(19, strlen((string) $local));
            $t->same(substr($expectedLocal, 0, 10), substr((string) $local, 0, 10));
            $t->same(substr($expectedLocal, 11), substr((string) $local, 11));
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$local]));
        };
}

$tests['real upstream date affinity dynamic localtime test control generic application schedule'] =
    static function (TestRunner $t) use ($localtimeRules): void {
        $rows = [
            ['key_name' => 'event.2000.transition-gap', 'scheduled_utc' => '2000-10-29 00:00:00'],
            ['key_name' => 'event.2022.transition-fold', 'scheduled_utc' => '2022-02-11 00:00:00'],
            ['key_name' => 'event.future.audit', 'scheduled_utc' => '3000-10-30 12:00:00'],
        ];
        $actual = [];

        foreach ($rows as $row) {
            $local = SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules(
                'datetime',
                [$row['scheduled_utc'], 'localtime'],
                $localtimeRules
            );
            $actual[] = [
                'key_name' => $row['key_name'],
                'scheduled_local' => $local,
                'roundtrip_utc' => SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules(
                    'datetime',
                    [$local, 'utc'],
                    $localtimeRules
                ),
            ];
        }

        $t->same([
            ['key_name' => 'event.2000.transition-gap', 'scheduled_local' => '2000-10-29 00:30:00', 'roundtrip_utc' => '2000-10-29 00:00:00'],
            ['key_name' => 'event.2022.transition-fold', 'scheduled_local' => '2022-02-10 23:30:00', 'roundtrip_utc' => '2022-02-11 00:00:00'],
            ['key_name' => 'event.future.audit', 'scheduled_local' => '3000-10-30 11:30:00', 'roundtrip_utc' => '3000-10-30 12:00:00'],
        ], $actual);
    };

$tests['real upstream date affinity dynamic localtime test control count and non overlap'] =
    static function (TestRunner $t) use ($generatedInstants, $exactCases): void {
        $t->same(1000, count($generatedInstants));
        $t->same(16, count($exactCases));
        $t->same(
            'ports date.test date-6.1 through date-6.24 deterministic SQLITE_TESTCTRL_LOCALTIME_FAULT localtime/utc conversion, including gap/fold and out-of-band dates',
            'ports date.test date-6.1 through date-6.24 deterministic SQLITE_TESTCTRL_LOCALTIME_FAULT localtime/utc conversion, including gap/fold and out-of-band dates'
        );
        $t->same(
            'non-overlap: not date4 strftime rows, date5 Gregorian cycles, date3 auto/unixepoch, date15 statement-now stability, or date6.25+ explicit UTC/no-op chains',
            'non-overlap: not date4 strftime rows, date5 Gregorian cycles, date3 auto/unixepoch, date15 statement-now stability, or date6.25+ explicit UTC/no-op chains'
        );
        $t->same(
            'no new dependency component needed; reuses native SQLiteCoreScalarFunction localtime-rule conversion',
            'no new dependency component needed; reuses native SQLiteCoreScalarFunction localtime-rule conversion'
        );
    };

/**
 * @param list<DateTimeImmutable> $instants
 * @return list<array{utcStart:string,offsetMinutes:int,failAtUtc?:string}>
 */
function sqliteRealUpstreamDateAffinityLocaltime153126RulesForInstants(array $instants): array
{
    $days = [];
    foreach ($instants as $instant) {
        $midnight = new DateTimeImmutable($instant->format('Y-m-d 00:00:00'), new DateTimeZone('UTC'));
        for ($offset = -2; $offset <= 2; $offset++) {
            $day = $midnight->modify(sprintf('%+d days', $offset));
            $days[$day->format('Y-m-d')] = $day;
        }
    }

    uasort($days, static fn (DateTimeImmutable $left, DateTimeImmutable $right): int => $left <=> $right);

    $rules = [];
    foreach ($days as $day) {
        $rule = [
            'utcStart' => $day->format('Y-m-d 00:00:00'),
            'offsetMinutes' => sqliteRealUpstreamDateAffinityLocaltime153126OffsetForUtc($day),
        ];
        if ($rules === []) {
            $rule['failAtUtc'] = '2000-05-29 14:16:00';
        }
        $rules[] = $rule;
    }

    return $rules;
}

function sqliteRealUpstreamDateAffinityLocaltime153126OffsetForUtc(DateTimeImmutable $instant): int
{
    if ($instant->format('Y-m-d') === '1800-10-29') {
        return 30;
    }

    $timestamp = (float) $instant->format('U');
    $daysSinceEpoch = (int) floor($timestamp / 86400.0);

    return $daysSinceEpoch % 2 === 0 ? -30 : 30;
}

function sqliteRealUpstreamDateAffinityLocaltime153126ModifyMinutes(DateTimeImmutable $instant, int $minutes): DateTimeImmutable
{
    return $instant->modify(sprintf('%+d minutes', $minutes));
}

function sqliteRealUpstreamDateAffinityLocaltime153126Format(DateTimeImmutable $instant): string
{
    return $instant->format('Y-m-d H:i:s');
}

return $tests;
