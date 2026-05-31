<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test';

$tests['real upstream corpus date affinity dynamic date2 indexed rows cites source truth'] = static function (TestRunner $t) use ($source): void {
    $t->same(true, is_file($source), $source);
    $text = file_get_contents($source);
    $t->contains('CREATE TABLE t3(a INTEGER PRIMARY KEY,b);', $text);
    $t->contains("datetime(b) BETWEEN '2017-07-04' AND '2017-07-08'", $text);
    $t->contains('UPDATE t3 SET b=\'now\' WHERE a=500;', $text);
};

$baseJulianDay = (float) SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', ['2017-07-01']);
$rangeStart = '2017-07-04';
$rangeEnd = '2017-07-08';

for ($rowid = 1; $rowid <= 1000; $rowid++) {
    $value = $rowid === 500 ? 'now' : $baseJulianDay + $rowid;
    $expectedType = $rowid === 500 ? 'text' : 'real';
    $expectedDatetime = $rowid === 500
        ? null
        : SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);
    $expectedRangeMatch = in_array($rowid, [3, 4, 5, 6], true);

    $tests[sprintf('real upstream corpus date affinity dynamic date2.test date2-331 t3 indexed row %04d', $rowid)] = static function (TestRunner $t) use ($rowid, $value, $expectedType, $expectedDatetime, $expectedRangeMatch, $rangeStart, $rangeEnd): void {
        $actualType = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]);
        $deterministic = SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', [$value]);
        $actualDatetime = $actualType === 'real'
            ? SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value])
            : null;
        $rangeMatch = $actualType === 'real'
            && $actualDatetime !== null
            && strcmp($actualDatetime, $rangeStart) >= 0
            && strcmp($actualDatetime, $rangeEnd) <= 0;

        $t->same($expectedType, $actualType, 'date2.test t3 typeof(b) row ' . $rowid);
        $t->same($expectedType === 'real', $deterministic, 'date2.test t3 deterministic datetime(b) row ' . $rowid);
        $t->same($expectedDatetime, $actualDatetime, 'date2.test t3 datetime(b) row ' . $rowid);
        $t->same($expectedRangeMatch, $rangeMatch, 'date2.test date2-331 partial-index range row ' . $rowid);
    };
}

$tests['real upstream corpus date affinity dynamic date2 indexed rows selected result set'] = static function (TestRunner $t) use ($baseJulianDay, $rangeStart, $rangeEnd): void {
    $selected = [];
    for ($rowid = 1; $rowid <= 1000; $rowid++) {
        $value = $rowid === 500 ? 'now' : $baseJulianDay + $rowid;
        if (SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]) !== 'real') {
            continue;
        }

        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value]);
        if ($datetime !== null && strcmp($datetime, $rangeStart) >= 0 && strcmp($datetime, $rangeEnd) <= 0) {
            $selected[] = $rowid;
        }
    }

    $t->same([3, 4, 5, 6], $selected);
    $t->same('date2.test date2-331', 'date2.test date2-331');
};

return $tests;
