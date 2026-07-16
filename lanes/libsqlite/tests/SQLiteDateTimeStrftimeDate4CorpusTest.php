<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';

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

for ($index = 0; $index < 1000; $index++) {
    $timestamp = $index * 86390;
    $tests['upstream date4 strftime libc parity date4-' . $index] = static function (TestRunner $t) use ($format, $timestamp, $expectedDate4): void {
        $t->same($expectedDate4($timestamp), SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch']));
    };
}

$tests['upstream date4 strftime application rotating audit sample'] = static function (TestRunner $t) use ($format, $expectedDate4): void {
    $timestamps = [0, 86390 * 31, 86390 * 366, 86390 * 999];
    $summary = [];
    foreach ($timestamps as $timestamp) {
        $summary[] = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch']);
    }

    $t->same(array_map($expectedDate4, $timestamps), $summary);
};

return $tests;
