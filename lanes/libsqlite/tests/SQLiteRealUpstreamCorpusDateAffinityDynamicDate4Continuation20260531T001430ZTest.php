<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$date4Format = '%Y-%m-%d %H:%M:%S %j %w %U %W';
$date4Start = 1300;
$date4Count = 1000;

$weekNumber = static function (int $timestamp, int $firstWeekday): string {
    $dayOfYear = (int) gmdate('z', $timestamp);
    $janFirst = gmmktime(0, 0, 0, 1, 1, (int) gmdate('Y', $timestamp));
    $janFirstWeekday = (int) gmdate('w', $janFirst);
    $daysUntilFirstWeekday = ($firstWeekday - $janFirstWeekday + 7) % 7;
    if ($dayOfYear < $daysUntilFirstWeekday) {
        return '00';
    }

    return sprintf('%02d', intdiv($dayOfYear - $daysUntilFirstWeekday, 7) + 1);
};

$tests['real upstream corpus date affinity dynamic date4 continuation 001430 cites upstream date4 loop'] = static function (TestRunner $t) use ($date4Start, $date4Count, $date4Format): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test';
    $source = file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('for {set i 0} {$i<=24858} {incr i}', (string) $source);
    $t->contains('SELECT strftime($::FMT,$::TS,', (string) $source);
    $t->same(1300, $date4Start);
    $t->same(1000, $date4Count);
    $t->same('%Y-%m-%d %H:%M:%S %j %w %U %W', $date4Format);
};

for ($i = $date4Start; $i < $date4Start + $date4Count; $i++) {
    $timestamp = $i * 86390;
    $expected = gmdate('Y-m-d H:i:s ', $timestamp)
        . str_pad((string) ((int) gmdate('z', $timestamp) + 1), 3, '0', STR_PAD_LEFT)
        . ' '
        . gmdate('w', $timestamp)
        . ' '
        . $weekNumber($timestamp, 0)
        . ' '
        . $weekNumber($timestamp, 1);

    $tests[sprintf('real upstream corpus date affinity dynamic date4.test date4-%05d strftime unixepoch week continuation 001430', $i)] = static function (TestRunner $t) use ($date4Format, $timestamp, $expected, $i): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);

        $t->same($expected, $actual, 'date4.test date4-' . $i);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(19, strlen(substr($actual, 0, 19)));
    };
}

return $tests;
