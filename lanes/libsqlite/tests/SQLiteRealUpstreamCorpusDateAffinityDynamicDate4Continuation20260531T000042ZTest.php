<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$date4Format = '%Y-%m-%d %H:%M:%S %j %w';
$date4Start = 300;
$date4Count = 1000;

$tests['real upstream corpus date affinity dynamic date4 continuation cites upstream date4 loop'] = static function (TestRunner $t) use ($date4Start, $date4Count): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test';
    $t->same(true, is_file($upstream));
    $t->contains('for {set i 0} {$i<=24858} {incr i}', (string) file_get_contents($upstream));
    $t->same(300, $date4Start);
    $t->same(1000, $date4Count);
};

for ($i = $date4Start; $i < $date4Start + $date4Count; $i++) {
    $timestamp = $i * 86390;
    $expected = gmdate('Y-m-d H:i:s ', $timestamp)
        . str_pad((string) ((int) gmdate('z', $timestamp) + 1), 3, '0', STR_PAD_LEFT)
        . ' '
        . gmdate('w', $timestamp);

    $tests[sprintf('real upstream corpus date affinity dynamic date4.test date4-%05d strftime unixepoch continuation', $i)] = static function (TestRunner $t) use ($date4Format, $timestamp, $expected, $i): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']);

        $t->same($expected, $actual, 'date4.test date4-' . $i);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

return $tests;
