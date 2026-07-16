<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$date4Format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';
$date4Start = 2300;
$date4Count = 1000;

$weekNumber = static function (DateTimeImmutable $instant, int $firstWeekday): string {
    $dayOfYear = (int) $instant->format('z');
    $janFirst = $instant->setDate((int) $instant->format('Y'), 1, 1);
    $janFirstWeekday = (int) $janFirst->format('w');
    $daysUntilFirstWeekday = ($firstWeekday - $janFirstWeekday + 7) % 7;
    if ($dayOfYear < $daysUntilFirstWeekday) {
        return '00';
    }

    return sprintf('%02d', intdiv($dayOfYear - $daysUntilFirstWeekday, 7) + 1);
};

$expectedDate4 = static function (int $timestamp) use ($weekNumber): string {
    $instant = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));

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
        $weekNumber($instant, 1),
        $instant->format('Y'),
        '%',
        strtolower($instant->format('A')),
        $instant->format('A'),
        $weekNumber($instant, 0),
        $instant->format('W'),
        $instant->format('o'),
        substr($instant->format('o'), -2),
    ]);
};

$tests['real upstream corpus date affinity dynamic real date4 005223 cites upstream date4 loop'] = static function (TestRunner $t) use ($date4Start, $date4Count, $date4Format): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('SELECT strftime($::FMT,$::TS,\'unixepoch\');', $source);
    $t->contains('for {set i 0} {$i<=24858} {incr i}', $source);
    $t->same('%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g', $date4Format);
    $t->same(2300, $date4Start);
    $t->same(1000, $date4Count);
};

for ($i = $date4Start; $i < $date4Start + $date4Count; $i++) {
    $timestamp = $i * 86390;

    $tests[sprintf('real upstream corpus date affinity dynamic real date4 005223 date4-%05d strftime libc format parity', $i)] = static function (TestRunner $t) use ($date4Format, $timestamp, $expectedDate4, $i): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);
        $expected = $expectedDate4($timestamp);

        $t->same($expected, $actual, 'date4.test date4-' . $i);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(20, substr_count((string) $actual, ','));
        $t->same((string) $actual, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, (string) $timestamp, 'unixepoch']));
        $t->same(substr($expected, 0, 10), substr((string) $actual, 0, 10));
    };
}

$tests['real upstream corpus date affinity dynamic real date4 005223 application audit rollup'] = static function (TestRunner $t) use ($date4Format, $date4Start, $date4Count, $expectedDate4): void {
    $sampleIndexes = [$date4Start, $date4Start + 125, $date4Start + 500, $date4Start + $date4Count - 1];
    $actual = [];
    $expected = [];

    foreach ($sampleIndexes as $index) {
        $timestamp = $index * 86390;
        $key = 'event-' . $index;
        $actual[$key] = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);
        $expected[$key] = $expectedDate4($timestamp);
    }

    $t->same($expected, $actual);
    $t->same(['event-2300', 'event-2425', 'event-2800', 'event-3299'], array_keys($actual));
};

return $tests;
