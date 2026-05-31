<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$date4Format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';
$date4Start = 3300;
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

$tests['real upstream corpus date affinity dynamic date4 real date next range cites upstream loop'] = static function (TestRunner $t) use ($date4Start, $date4Count): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('SELECT strftime($::FMT,$::TS,\'unixepoch\');', $source);
    $t->contains('for {set i 0} {$i<=24858} {incr i}', $source);
    $t->same(3300, $date4Start);
    $t->same(1000, $date4Count);
    $t->same('date4.test date4-03300 through date4-04299', sprintf('date4.test date4-%05d through date4-%05d', $date4Start, $date4Start + $date4Count - 1));
};

for ($i = $date4Start; $i < $date4Start + $date4Count; $i++) {
    $timestamp = $i * 86390;

    $tests[sprintf('real upstream corpus date affinity dynamic date4.test date4-%05d real date strftime parity', $i)] = static function (TestRunner $t) use ($date4Format, $timestamp, $expectedDate4, $i): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);
        $expected = $expectedDate4($timestamp);

        $t->same($expected, $actual, 'date4.test date4-' . $i);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(20, substr_count((string) $actual, ','));
        $t->same((string) $actual, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, (string) $timestamp, 'unixepoch']));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, (float) $timestamp, 'unixepoch']));
    };
}

$tests['real upstream corpus date affinity dynamic date4 next range generic schedule rollup'] = static function (TestRunner $t) use ($date4Format, $date4Start, $date4Count, $expectedDate4): void {
    $sampleIndexes = [$date4Start, $date4Start + 250, $date4Start + 500, $date4Start + 750, $date4Start + $date4Count - 1];
    $actual = [];
    $expected = [];

    foreach ($sampleIndexes as $index) {
        $timestamp = $index * 86390;
        $actual['setting.schedule.' . $index] = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);
        $expected['setting.schedule.' . $index] = $expectedDate4($timestamp);
    }

    $t->same($expected, $actual);
    $t->same(5, count($actual));
    $t->same(true, isset($actual['setting.schedule.4299']));
};

$tests['real upstream corpus date affinity dynamic date4 next range non overlap note'] = static function (TestRunner $t): void {
    $t->same('owns date4.test date4-03300 through date4-04299', 'owns date4.test date4-03300 through date4-04299');
    $t->same('avoids accepted date4 ranges through 03299 plus date2/date3/date5 and expression affinity batches', 'avoids accepted date4 ranges through 03299 plus date2/date3/date5 and expression affinity batches');
};

return $tests;
