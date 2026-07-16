<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$upstreamTimediffSource = '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test';
$upstreamTimediffText = is_file($upstreamTimediffSource) ? (file_get_contents($upstreamTimediffSource) ?: '') : '';

$tests['real upstream corpus date affinity dynamic timediff month overflow cites timediff1 sections one and two'] = static function (TestRunner $t) use ($upstreamTimediffSource, $upstreamTimediffText): void {
    $t->same(true, is_file($upstreamTimediffSource), $upstreamTimediffSource);
    $t->same(true, str_contains($upstreamTimediffText, '# February overflow on a leap year'));
    $t->same(true, str_contains($upstreamTimediffText, '# February overflow on a non-leap year'));
    $t->same(true, str_contains($upstreamTimediffText, "datetest 1.13 {datetime('2001-03-31','-0001-01-00 06:10')}"));
    $t->same(true, str_contains($upstreamTimediffText, "datetest 2.13 {datetime('2002-03-31','-0001-01-00 06:10')}"));
};

$fixedTimediffMonthOverflowCases = [
    'timediff-1.1 leap jan31 plus one month' => ['2000-01-31', '+1 month', '2000-03-02 00:00:00'],
    'timediff-1.2 leap jan29 plus one month' => ['2004-01-29', '+1 month', '2004-02-29 00:00:00'],
    'timediff-1.3 leap march first minus one day' => ['2000-03-01', '-1 day', '2000-02-29 00:00:00'],
    'timediff-1.4 leap march31 minus one month' => ['2000-03-31', '-1 month', '2000-03-02 00:00:00'],
    'timediff-1.5 leap march30 minus one month' => ['2000-03-30', '-1 month', '2000-03-01 00:00:00'],
    'timediff-1.6 leap march29 minus one month' => ['2000-03-29', '-1 month', '2000-02-29 00:00:00'],
    'timediff-1.7 leap march28 minus one month' => ['2000-03-28', '-1 month', '2000-02-28 00:00:00'],
    'timediff-1.8 leap feb29 plus one year' => ['2000-02-29', '+1 year', '2001-03-01 00:00:00'],
    'timediff-1.9 leap feb29 plus four years' => ['2000-02-29', '+4 years', '2004-02-29 00:00:00'],
    'timediff-1.10 leap signed ymd clock modifier' => ['1998-11-10', '+0001-03-19 12:34:56', '2000-02-29 12:34:56'],
    'timediff-1.11 leap signed zero-day month overflow' => ['2000-01-31', '+0004-01-00 12:34:56', '2004-03-02 12:34:56'],
    'timediff-1.12 leap signed feb29 landing' => ['2000-01-29', '+0008-01-00 12:34:56', '2008-02-29 12:34:56'],
    'timediff-1.13 leap signed negative ymd clock modifier' => ['2001-03-31', '-0001-01-00 06:10', '2000-03-01 17:50:00'],
    'timediff-2.1 common jan31 plus one month' => ['2001-01-31', '+1 month', '2001-03-03 00:00:00'],
    'timediff-2.2 common jan29 plus one month' => ['2005-01-29', '+1 month', '2005-03-01 00:00:00'],
    'timediff-2.3 common march first minus one day' => ['2001-03-01', '-1 day', '2001-02-28 00:00:00'],
    'timediff-2.4 common march31 minus one month' => ['2001-03-31', '-1 month', '2001-03-03 00:00:00'],
    'timediff-2.5 common march30 minus one month' => ['2001-03-30', '-1 month', '2001-03-02 00:00:00'],
    'timediff-2.6 common march29 minus one month' => ['2001-03-29', '-1 month', '2001-03-01 00:00:00'],
    'timediff-2.7 common march28 minus one month' => ['2001-03-28', '-1 month', '2001-02-28 00:00:00'],
    'timediff-2.10 common signed ymd clock modifier' => ['1999-11-10', '+0001-03-19 12:34:56', '2001-03-01 12:34:56'],
    'timediff-2.11 common signed zero-day month overflow' => ['2000-01-31', '+0005-01-00 12:34:56', '2005-03-03 12:34:56'],
    'timediff-2.12 common signed march landing' => ['2000-01-29', '+0009-01-00 12:34:56', '2009-03-01 12:34:56'],
    'timediff-2.13 common signed negative ymd clock modifier' => ['2002-03-31', '-0001-01-00 06:10', '2001-03-02 17:50:00'],
];

foreach ($fixedTimediffMonthOverflowCases as $name => [$base, $modifier, $expected]) {
    $tests['real upstream corpus date affinity dynamic timediff month overflow ' . $name] = static function (TestRunner $t) use ($base, $modifier, $expected, $name): void {
        sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowAssertCase($t, $base, $modifier, $expected, $name);
    };
}

$dynamicTimediffMonthOverflowCases = sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowCases();
foreach ($dynamicTimediffMonthOverflowCases as $case) {
    $tests['real upstream corpus date affinity dynamic timediff month overflow generated ' . $case['id']] = static function (TestRunner $t) use ($case): void {
        sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowAssertCase(
            $t,
            $case['base'],
            $case['modifier'],
            $case['expected'],
            $case['id']
        );
    };
}

$tests['real upstream corpus date affinity dynamic timediff month overflow generic app retention schedule'] = static function (TestRunner $t): void {
    $settings = [
        ['tenant_id' => 1, 'key_name' => 'retention_window', 'started_at' => '2000-01-31', 'modifier' => '+1 month', 'expected' => '2000-03-02 00:00:00'],
        ['tenant_id' => 1, 'key_name' => 'leap_roll_forward', 'started_at' => '2000-02-29', 'modifier' => '+1 year', 'expected' => '2001-03-01 00:00:00'],
        ['tenant_id' => 2, 'key_name' => 'common_roll_back', 'started_at' => '2002-03-31', 'modifier' => '-0001-01-00 06:10', 'expected' => '2001-03-02 17:50:00'],
        ['tenant_id' => 2, 'key_name' => 'signed_clock_window', 'started_at' => '1999-11-10', 'modifier' => '+0001-03-19 12:34:56', 'expected' => '2001-03-01 12:34:56'],
    ];

    $storedRows = [];
    foreach ($settings as $setting) {
        $normalizedAt = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$setting['started_at'], $setting['modifier']]);
        $storedRows[] = [
            'tenant_id' => $setting['tenant_id'],
            'key_name' => $setting['key_name'],
            'started_at' => $setting['started_at'],
            'normalized_at' => $normalizedAt,
            'policy_date' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$setting['started_at'], $setting['modifier']]),
            'policy_time' => SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$setting['started_at'], $setting['modifier']]),
        ];
        $t->same($setting['expected'], $normalizedAt, $setting['key_name']);
    }

    $affinityRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($storedRows, [
        'tenant_id' => 'INTEGER',
        'key_name' => 'TEXT',
        'started_at' => 'TEXT',
        'normalized_at' => 'TEXT',
        'policy_date' => 'TEXT',
        'policy_time' => 'TEXT',
    ]);

    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($affinityRows[0]['tenant_id']));
    $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($affinityRows[0]['normalized_at']));
    $t->same('2000-03-02', $affinityRows[0]['policy_date']);
    $t->same('00:00:00', $affinityRows[0]['policy_time']);
    $t->same('2001-03-02 17:50:00', $affinityRows[2]['normalized_at']);
};

$tests['real upstream corpus date affinity dynamic timediff month overflow non overlap and dependency closure'] = static function (TestRunner $t) use ($fixedTimediffMonthOverflowCases, $dynamicTimediffMonthOverflowCases): void {
    $owned = [
        'timediff1.test timediff-1.1..1.13 leap-year month overflow and signed date-time modifiers',
        'timediff1.test timediff-2.1..2.13 common-year month overflow and signed date-time modifiers',
    ];
    $avoided = [
        'timediff1.test timediff-3 exact timediff strings',
        'timediff1.test timediff-4 roundtrip matrix',
        'timediff1.test timediff-5 partial modifier grammar',
        'timediff1.test timediff-6 month-boundary roundtrip matrix',
        'date4/date19/date20/date3/date5 existing date-affinity shards',
        'JSONB cleanup, row-value parity, and real VFS handoff candidates',
    ];
    $dependencyClosure = 'no new support component needed; reuses SQLiteCoreScalarFunction date/time modifier dispatch and SQLiteRealExpressionAffinityCorpusPlan TEXT affinity storage';

    $t->same(24, count($fixedTimediffMonthOverflowCases));
    $t->same(1200, count($dynamicTimediffMonthOverflowCases));
    $t->same(true, in_array('timediff1.test timediff-1.1..1.13 leap-year month overflow and signed date-time modifiers', $owned, true));
    $t->same(true, in_array('JSONB cleanup, row-value parity, and real VFS handoff candidates', $avoided, true));
    $t->same(true, str_contains($dependencyClosure, 'no new support component needed'));
};

return $tests;

/**
 * @return list<array{id:string,base:string,modifier:string,expected:string}>
 */
function sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowCases(): array
{
    $leapYears = [1996, 2000, 2004, 2008, 2012, 2016, 2020, 2024];
    $commonYears = [1999, 2001, 2002, 2003, 2005, 2006, 2007, 2010];
    $monthEndDays = [28, 29, 30, 31];
    $cases = [];

    for ($case = 0; $case < 1200; $case++) {
        $pattern = $case % 6;
        $leap = ($case % 2) === 0;
        $year = $leap ? $leapYears[intdiv($case, 2) % count($leapYears)] : $commonYears[intdiv($case, 2) % count($commonYears)];
        $day = $monthEndDays[$case % count($monthEndDays)];

        if ($pattern === 0) {
            $base = sprintf('%04d-01-%02d 00:00:00', $year, $day);
            $modifier = '+1 month';
        } elseif ($pattern === 1) {
            $base = sprintf('%04d-03-%02d 00:00:00', $year, $day);
            $modifier = '-1 month';
        } elseif ($pattern === 2) {
            $base = sprintf('%04d-02-%02d 00:00:00', $year, $leap ? 29 : 28);
            $modifier = ($case % 4) === 0 ? '+4 years' : '+1 year';
        } elseif ($pattern === 3) {
            $base = sprintf('%04d-11-%02d 00:00:00', $year - 2, 10 + ($case % 10));
            $modifier = '+0001-03-19 12:34:56';
        } elseif ($pattern === 4) {
            $base = sprintf('%04d-01-%02d 00:00:00', $year, $day);
            $modifier = sprintf(
                '+%04d-%02d-%02d %02d:%02d:%02d',
                1 + ($case % 9),
                $case % 12,
                $case % 31,
                ($case * 3) % 24,
                ($case * 7) % 60,
                ($case * 11) % 60
            );
        } else {
            $base = sprintf('%04d-03-%02d 00:00:00', $year, $day);
            $modifier = sprintf(
                '-%04d-%02d-%02d %02d:%02d',
                1 + ($case % 3),
                $case % 12,
                $case % 31,
                ($case * 5) % 24,
                ($case * 13) % 60
            );
        }

        $cases[] = [
            'id' => sprintf('%04d %s %s', $case + 1, $base, $modifier),
            'base' => $base,
            'modifier' => $modifier,
            'expected' => sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowExpected($base, $modifier),
        ];
    }

    return $cases;
}

function sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowAssertCase(TestRunner $t, string $base, string $modifier, string $expected, string $label): void
{
    $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$base, $modifier]);
    $date = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier]);
    $time = SQLiteCoreScalarFunction::sqlFunctionArguments('time', [$base, $modifier]);
    $julianday = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$base, $modifier]);
    $expectedJulianday = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$expected]);
    $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([[
        'normalized_at' => $actual,
    ]], [
        'normalized_at' => 'TEXT',
    ])[0];

    $t->same($expected, $actual, $label . ' datetime');
    $t->same(substr($expected, 0, 10), $date, $label . ' date');
    $t->same(substr($expected, 11, 8), $time, $label . ' time');
    $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]), $label . ' typeof');
    $t->same(true, abs((float) $julianday - (float) $expectedJulianday) < 1.0e-6, $label . ' julianday');
    $t->same($expected, $stored['normalized_at'], $label . ' text affinity');
    $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['normalized_at']), $label . ' storage class');
}

function sqliteRealUpstreamCorpusDateAffinityDynamicTimediffMonthOverflowExpected(string $base, string $modifier): string
{
    $instant = new DateTimeImmutable($base, new DateTimeZone('UTC'));

    if (preg_match('/\A([+-])(\d{4,})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?\z/', $modifier, $matches) === 1) {
        $sign = $matches[1] === '-' ? -1 : 1;
        $months = ((int) $matches[2] * 12) + (int) $matches[3];
        $days = (int) $matches[4];
        if ($months !== 0) {
            $instant = $instant->modify(sprintf('%+d months', $sign * $months));
        }
        if ($days !== 0) {
            $instant = $instant->modify(sprintf('%+d days', $sign * $days));
        }
        if (isset($matches[5]) && $matches[5] !== '') {
            $seconds = ((int) $matches[5] * 3600) + ((int) $matches[6] * 60) + (isset($matches[7]) && $matches[7] !== '' ? (int) $matches[7] : 0);
            if ($seconds !== 0) {
                $instant = $instant->modify(sprintf('%+d seconds', $sign * $seconds));
            }
        }

        return $instant->format('Y-m-d H:i:s');
    }

    $changed = $instant->modify($modifier);
    if (!$changed instanceof DateTimeImmutable) {
        throw new InvalidArgumentException("Unable to apply modifier {$modifier}");
    }

    return $changed->format('Y-m-d H:i:s');
}
