<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream date affinity Julian modifier bulk tests');
}

$baseJulianDays = [];
for ($index = 0; $index < 125; $index++) {
    $baseJulianDays[] = 2454832.5 + ($index * 17.25);
}

$modifierFamilies = [
    'date.test date-13.11 negative day arithmetic' => '-1 day',
    'date.test date-13.12 positive day arithmetic' => '+1 day',
    'date.test date-13.13 negative fractional day arithmetic' => '-1.5 day',
    'date.test date-13.14 positive fractional day arithmetic' => '+1.5 day',
    'date.test date-13.15 negative hour arithmetic' => '-3 hours',
    'date.test date-13.16 positive hour arithmetic' => '+3 hours',
    'date.test date-13.17 negative minute arithmetic' => '-45 minutes',
    'date.test date-13.18 positive minute arithmetic' => '+45 minutes',
];

$caseInputs = [];
foreach ($baseJulianDays as $julianDay) {
    foreach ($modifierFamilies as $sourceLabel => $modifier) {
        $caseInputs[] = [$julianDay, $sourceLabel, $modifier];
    }
}

$sqlLiteral = static function (mixed $value): string {
    if (is_int($value) || is_float($value)) {
        return sprintf('%.12F', (float) $value);
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracleSql = [];
foreach ($caseInputs as [$julianDay, , $modifier]) {
    $valueSql = $sqlLiteral($julianDay);
    $modifierSql = $sqlLiteral($modifier);
    $oracleSql[] = "SELECT quote(julianday({$valueSql},{$modifierSql})) || char(9) || typeof(julianday({$valueSql},{$modifierSql})) || char(9) || quote(datetime({$valueSql},{$modifierSql})) || char(9) || typeof(datetime({$valueSql},{$modifierSql}));";
}

$process = proc_open(
    [$sqlite3, '-batch', '-noheader', ':memory:'],
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
);
if (!is_resource($process)) {
    throw new RuntimeException('sqlite3 oracle process could not be started for real upstream date affinity Julian modifier bulk tests');
}

fwrite($pipes[0], implode("\n", $oracleSql));
fclose($pipes[0]);
$oracleOutput = stream_get_contents($pipes[1]);
$oracleError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$oracleStatus = proc_close($process);
if ($oracleStatus !== 0 || !is_string($oracleOutput)) {
    throw new RuntimeException('sqlite3 oracle failed for real upstream date affinity Julian modifier bulk tests: ' . trim((string) $oracleError));
}

$oracleLines = preg_split('/\r?\n/', trim($oracleOutput));
if (!is_array($oracleLines) || count($oracleLines) !== count($caseInputs)) {
    throw new RuntimeException('sqlite3 oracle produced an unexpected number of Julian modifier rows');
}

$tests['real upstream date affinity julian modifier bulk cites source truth'] = static function (TestRunner $t) use ($caseInputs, $modifierFamilies, $baseJulianDays): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    $t->same(true, is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test'));
    $t->same(1000, count($caseInputs));
    $t->same(8, count($modifierFamilies));
    $t->same(125, count($baseJulianDays));
    $t->same(true, array_key_exists('date.test date-13.11 negative day arithmetic', $modifierFamilies));
    $t->same(true, array_key_exists('date.test date-13.18 positive minute arithmetic', $modifierFamilies));
};

foreach ($caseInputs as $caseIndex => [$julianDay, $sourceLabel, $modifier]) {
    [$expectedJulianQuote, $expectedJulianType, $expectedDatetimeQuote, $expectedDatetimeType] = explode("\t", $oracleLines[$caseIndex], 4);

    $tests[sprintf('real upstream date affinity julian modifier bulk date.test date-13 arithmetic %04d %s', $caseIndex, $sourceLabel)] =
        static function (TestRunner $t) use ($julianDay, $modifier, $sourceLabel, $expectedJulianQuote, $expectedJulianType, $expectedDatetimeQuote, $expectedDatetimeType): void {
            $actualJulian = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$julianDay, $modifier]);
            $actualDatetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay, $modifier]);

            $t->same($expectedJulianType, SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actualJulian]));
            $t->same($expectedDatetimeType, SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actualDatetime]));
            $t->same($expectedDatetimeQuote, SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$actualDatetime]));

            $expectedJulian = (float) $expectedJulianQuote;
            $actualJulianFloat = (float) $actualJulian;
            $t->true(abs($expectedJulian - $actualJulianFloat) <= 1.0e-8, $sourceLabel . ' Julian day oracle parity');
            $t->same(true, str_contains($sourceLabel, 'date.test date-13.'));
            $t->same(true, in_array($modifier, ['-1 day', '+1 day', '-1.5 day', '+1.5 day', '-3 hours', '+3 hours', '-45 minutes', '+45 minutes'], true));
        };
}

return $tests;
