<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream date2 real affinity full table cites source setup'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test';

    $t->same(true, is_file($source), $source);
    $t->contains('date2-300', file_get_contents($source));
    $t->contains('date2-331', file_get_contents($source));
    $t->contains("julianday('2017-07-01')+x", file_get_contents($source));
};

for ($rowid = 1; $rowid <= 1000; $rowid++) {
    $tests[sprintf('real upstream date2 date2-300 real affinity t3 row %04d datetime predicate', $rowid)] = static function (TestRunner $t) use ($rowid): void {
        if ($rowid === 500) {
            $storedValue = 'now';
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$storedValue]));
            $t->same(false, SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$storedValue]) === 'real');
            $t->same(false, '2017-07-04' <= $storedValue && $storedValue <= '2017-07-08');
            return;
        }

        $julianDay = 2457935.5 + $rowid;
        $expected = (new DateTimeImmutable('2017-07-01 00:00:00', new DateTimeZone('UTC')))
            ->modify('+' . $rowid . ' days')
            ->format('Y-m-d H:i:s');
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);
        $matchesDate2331Range = $rowid >= 3 && $rowid <= 6;

        $t->same($expected, $actual);
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$julianDay]));
        $t->same($matchesDate2331Range, $actual >= '2017-07-04' && $actual <= '2017-07-08');
    };
}

$tests['real upstream date2 date2-331 selected rowids remain 3 through 6'] = static function (TestRunner $t): void {
    $selected = [];

    for ($rowid = 1; $rowid <= 1000; $rowid++) {
        if ($rowid === 500) {
            continue;
        }

        $julianDay = 2457935.5 + $rowid;
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);
        if ($actual >= '2017-07-04' && $actual <= '2017-07-08') {
            $selected[] = $rowid;
        }
    }

    $t->same([3, 4, 5, 6], $selected);
};

return $tests;
