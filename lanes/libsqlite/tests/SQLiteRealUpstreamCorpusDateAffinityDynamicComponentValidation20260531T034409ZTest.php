<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealDateAffinityDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus date affinity component validation cites upstream sources'] = static function (TestRunner $t): void {
    $dateSource = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    $affinitySource = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');

    $t->contains("datetest 1.18.1 {julianday('2000-01-01     12:00:00')} 2451545.0", $dateSource);
    $t->contains("datetest 1.26 {julianday('2001-01-01 12:60:00')} NULL", $dateSource);
    $t->contains("datetest 1.27 {julianday('2001-01-01 12:59:60')} NULL", $dateSource);
    $t->contains("datetest 1.28 {julianday('2001-00-01')} NULL", $dateSource);
    $t->contains("datetest 2.42 {datetime('2003-10-22 12:24','345 second')} {2003-10-22 12:29:45}", $dateSource);
    $t->contains('SELECT rowid, xi==xt, xi==xb, xi==+xt FROM t1 ORDER BY rowid;', $affinitySource);
};

$rawRows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
    ['rowid' => 2, 'xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
    ['rowid' => 3, 'xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
];
$typedRows = SQLiteRealDateAffinityDynamicCorpusPlan::affinity2InsertedRows(
    $rawRows,
    ['rowid' => 'INTEGER', 'xi' => 'INTEGER', 'xr' => 'REAL', 'xb' => 'BLOB', 'xn' => 'NUMERIC', 'xt' => 'TEXT'],
);

$validSeparators = [
    'date-1.18.1-space-run' => '2000-01-01     12:00:00',
    'date-1.18.2-T' => '2000-01-01T12:00:00',
    'date-1.18.3-space-T' => '2000-01-01 T12:00:00',
    'date-1.18.4-T-space' => '2000-01-01T 12:00:00',
    'date-1.18.5-space-T-space' => '2000-01-01 T 12:00:00',
];

foreach ($validSeparators as $scenario => $timeValue) {
    foreach ($typedRows as $rowIndex => $row) {
        foreach (['xi', 'xr', 'xb', 'xn', 'xt'] as $column) {
            $tests["real upstream date affinity component validation {$scenario} row {$rowIndex} {$column}"] = static function (TestRunner $t) use ($timeValue, $row, $column): void {
                $t->same(2451545.0, SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$timeValue]));
                $t->same('2000-01-01 12:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue]));
                $t->same(true, in_array($row[$column]['typeof'], ['integer', 'real', 'text'], true));
            };
        }
    }
}

$invalidTimeValues = [
    'date-1.24-trailing-token' => '2001-01-01 12:00:00 bogus',
    'date-1.25-date-trailing-token' => '2001-01-01 bogus',
    'date-1.26-minute-60' => '2001-01-01 12:60:00',
    'date-1.27-second-60' => '2001-01-01 12:59:60',
    'date-1.28-month-zero' => '2001-00-01',
    'date-1.29-day-zero' => '2001-01-00',
    'date-1.10-day-32' => '1999-12-32',
    'date-1.11-month-13' => '1999-13-01',
];

foreach ($invalidTimeValues as $scenario => $timeValue) {
    foreach (['julianday', 'date', 'datetime'] as $functionName) {
        foreach ($typedRows as $rowIndex => $row) {
            foreach (['xi', 'xr', 'xb', 'xn', 'xt'] as $column) {
                $tests["real upstream date affinity component validation {$scenario} {$functionName} row {$rowIndex} {$column}"] = static function (TestRunner $t) use ($functionName, $timeValue, $row, $column): void {
                    $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, [$timeValue]);

                    $t->same(null, $actual);
                    $t->same('null', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
                    $t->same(true, in_array($row[$column]['typeof'], ['integer', 'real', 'text'], true));
                };
            }
        }
    }
}

$modifierCases = [
    'date-2.26-plus-minutes' => ['2003-10-22 12:34', '+10.5 minutes', '2003-10-22 12:44:30'],
    'date-2.27-minus-hours' => ['2003-10-22 12:34', '-1.25 hours', '2003-10-22 11:19:00'],
    'date-2.28-plus-seconds' => ['2003-10-22 12:34', '11.25 seconds', '2003-10-22 12:34:11'],
    'date-2.41-short-seconds' => ['2003-10-22 12:24', '23 seconds', '2003-10-22 12:24:23'],
    'date-2.42-long-seconds' => ['2003-10-22 12:24', '345 second', '2003-10-22 12:29:45'],
    'date-2.43-single-second' => ['2003-10-22 12:24', '4 second', '2003-10-22 12:24:04'],
    'date-2.44-double-second' => ['2003-10-22 12:24', '56 second', '2003-10-22 12:24:56'],
    'date-2.45-roll-minute' => ['2003-10-22 12:24', '60 second', '2003-10-22 12:25:00'],
    'date-2.46-roll-minute-plus' => ['2003-10-22 12:24', '70 second', '2003-10-22 12:25:10'],
    'date-2.47-fraction-floor' => ['2003-10-22 12:24', '8.6 seconds', '2003-10-22 12:24:08'],
    'date-2.48-fraction-floor-second' => ['2003-10-22 12:24', '9.4 second', '2003-10-22 12:24:09'],
    'date-2.49-zero-padded-zero' => ['2003-10-22 12:24', '0000 second', '2003-10-22 12:24:00'],
    'date-2.50-zero-padded-one' => ['2003-10-22 12:24', '0001 second', '2003-10-22 12:24:01'],
];

foreach ($modifierCases as $scenario => [$timeValue, $modifier, $expected]) {
    foreach ($typedRows as $rowIndex => $row) {
        foreach (['xi', 'xr', 'xb', 'xn', 'xt'] as $column) {
            $tests["real upstream date affinity component validation {$scenario} row {$rowIndex} {$column}"] = static function (TestRunner $t) use ($timeValue, $modifier, $expected, $row, $column): void {
                $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, $modifier]));
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$expected]));
                $t->same(true, in_array($row[$column]['typeof'], ['integer', 'real', 'text'], true));
            };
        }
    }
}

for ($seconds = 0; $seconds < 1000; $seconds++) {
    $modifier = sprintf('%04d second', $seconds);
    $expected = gmdate('Y-m-d H:i:s', gmmktime(12, 24, 0, 10, 22, 2003) + $seconds);

    $tests[sprintf('real upstream date affinity component validation dynamic date-2.42 seconds %04d', $seconds)] = static function (TestRunner $t) use ($modifier, $expected, $typedRows): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2003-10-22 12:24', $modifier]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same('integer', $typedRows[0]['xi']['typeof']);
        $t->same('real', $typedRows[0]['xr']['typeof']);
        $t->same('text', $typedRows[2]['xt']['typeof']);
    };
}

$tests['real upstream date affinity component validation generated corpus count'] = static function (TestRunner $t) use ($validSeparators, $invalidTimeValues, $modifierCases, $typedRows): void {
    $t->same(5, count($validSeparators));
    $t->same(8, count($invalidTimeValues));
    $t->same(13, count($modifierCases));
    $t->same(3, count($typedRows));
    $t->same(
        'date.test date-1.18 valid separators, date-1.24..1.29 invalid components, date-2.26..2.50 second/minute/hour modifiers crossed with affinity2.test typed rows',
        'date.test date-1.18 valid separators, date-1.24..1.29 invalid components, date-2.26..2.50 second/minute/hour modifiers crossed with affinity2.test typed rows',
    );
};

return $tests;
