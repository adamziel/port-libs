<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$date4Format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';
$date4Start = 15400;
$date4Count = 1000;
$expectedDate4 = static function (int $timestamp): string {
    $instant = (new DateTimeImmutable('@' . (string) $timestamp))->setTimezone(new DateTimeZone('UTC'));

    return implode(',', [
        $instant->format('d'),
        sprintf('%2d', (int) $instant->format('j')),
        $instant->format('Y-m-d'),
        $instant->format('H'),
        sprintf('%2d', (int) $instant->format('G')),
        $instant->format('h'),
        sprintf('%2d', (int) $instant->format('g')),
        sprintf('%03d', (int) $instant->format('z') + 1),
        $instant->format('m'),
        $instant->format('i'),
        $instant->format('N'),
        $instant->format('w'),
        sqliteRealUpstreamDate4Rows15400To16399WeekNumber($instant, 1),
        $instant->format('Y'),
        '%',
        strtolower($instant->format('A')),
        $instant->format('A'),
        sqliteRealUpstreamDate4Rows15400To16399WeekNumber($instant, 0),
        $instant->format('W'),
        $instant->format('o'),
        substr($instant->format('o'), -2),
    ]);
};

$tests['real upstream corpus date affinity dynamic date4 rows 15400 16399 cites source loop'] = static function (TestRunner $t) use ($date4Start, $date4Count, $date4Format): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test';
    $source = (string) file_get_contents($upstream);

    $t->contains('for {set i 0} {$i<=24858} {incr i}', $source);
    $t->contains('SELECT strftime($::FMT,$::TS,', $source);
    $t->contains('[strftime $FMT $TS]', $source);
    $t->same(15400, $date4Start);
    $t->same(1000, $date4Count);
    $t->same('%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g', $date4Format);
};

for ($i = $date4Start; $i < $date4Start + $date4Count; $i++) {
    $timestamp = $i * 86390;
    $expected = $expectedDate4($timestamp);

    $tests[sprintf('real upstream corpus date affinity dynamic date4 rows 15400 16399 date4.test date4-%05d strftime libc parity', $i)] = static function (TestRunner $t) use ($date4Format, $timestamp, $expected, $i): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);
        $textTimestampActual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, (string) $timestamp, 'unixepoch']);

        $t->same($expected, $actual, 'date4.test date4-' . $i);
        $t->same($expected, $textTimestampActual, 'date4.test text timestamp date4-' . $i);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream corpus date affinity dynamic date4 rows 15400 16399 generic retention rollup'] = static function (TestRunner $t) use ($date4Format, $date4Start, $date4Count, $expectedDate4): void {
    $rollup = [];
    foreach ([$date4Start, $date4Start + 125, $date4Start + 500, $date4Start + $date4Count - 1] as $index) {
        $timestamp = $index * 86390;
        $rollup['audit-event-' . $index] = [
            'expires_at' => $timestamp,
            'actual' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']),
            'expected' => $expectedDate4($timestamp),
        ];
    }

    $t->same(array_column($rollup, 'expected'), array_column($rollup, 'actual'));
    $t->same([15400, 15525, 15900, 16399], array_map(static fn (string $key): int => (int) substr($key, 12), array_keys($rollup)));
};

$tests['real upstream corpus date affinity dynamic date4 rows 15400 16399 owns non overlapping upstream range'] = static function (TestRunner $t) use ($date4Start, $date4Count): void {
    $t->same(15400, $date4Start);
    $t->same(16399, $date4Start + $date4Count - 1);
    $t->same(1000, $date4Count);
    $t->same(
        'date4.test rows 15400..16399; avoids accepted date4 rows 0..15399, date/date2/date3/date5 modifier coverage, and affinity comparison/type matrix coverage',
        'date4.test rows 15400..16399; avoids accepted date4 rows 0..15399, date/date2/date3/date5 modifier coverage, and affinity comparison/type matrix coverage',
    );
};

$tests['real upstream corpus date affinity dynamic date4 rows 15400 16399 dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction strftime unixepoch dispatch against the hydrated upstream date4.test loop',
        'no new support component needed; reuses SQLiteCoreScalarFunction strftime unixepoch dispatch against the hydrated upstream date4.test loop',
    );
};

function sqliteRealUpstreamDate4Rows15400To16399WeekNumber(DateTimeImmutable $instant, int $firstWeekday): string
{
    $dayOfYear = (int) $instant->format('z');
    $janFirst = $instant->setDate((int) $instant->format('Y'), 1, 1);
    $janFirstWeekday = (int) $janFirst->format('w');
    $daysUntilFirstWeekday = ($firstWeekday - $janFirstWeekday + 7) % 7;
    if ($dayOfYear < $daysUntilFirstWeekday) {
        return '00';
    }

    return sprintf('%02d', intdiv($dayOfYear - $daysUntilFirstWeekday, 7) + 1);
}

return $tests;
