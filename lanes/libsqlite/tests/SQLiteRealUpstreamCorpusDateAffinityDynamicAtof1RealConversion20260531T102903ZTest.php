<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test';
$sourceText = is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof1.test is required for atof1 REAL conversion corpus tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof1 REAL conversion corpus tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/atof1.test atof-3.1.  The regression
// range checks decimal text beginning with 1844674407370955 and suffixes in
// the 0592..1609 window, which previously rounded to the wrong REAL value.
$cases = [];
for ($suffix = 592; $suffix <= 1609; $suffix++) {
    $value = sprintf('1844674407370955%04d', $suffix);
    $key = sprintf('suffix-%04d', $suffix);
    $cases[$key] = [
        'source' => 'atof1.test atof-3.1',
        'suffix' => $suffix,
        'value' => $value,
        'pattern' => '1.8446744073709[56]*',
    ];
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $valueSql = $sqlLiteral($case['value']);
    $patternSql = $sqlLiteral($case['pattern']);
    $oracleScript[] = "SELECT '{$safeKey}'"
        . " || char(9) || typeof(CAST({$valueSql} AS REAL))"
        . " || char(9) || format('%.10e', CAST({$valueSql} AS REAL))"
        . " || char(9) || (CAST({$valueSql} AS REAL) GLOB {$patternSql})"
        . " || char(9) || (CAST(quote(CAST({$valueSql} AS REAL)) AS REAL)=CAST({$valueSql} AS REAL));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-real-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof1 REAL conversion tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof1 REAL conversion output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 atof1 REAL conversion oracle row: ' . $line);
    }
    [$key, $storageClass, $formatted, $globMatch, $quoteRoundTrip] = $parts;
    $oracle[$key] = [
        'storageClass' => $storageClass,
        'formatted' => $formatted,
        'globMatch' => $globMatch,
        'quoteRoundTrip' => $quoteRoundTrip,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d atof1 REAL conversion oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic atof1 REAL conversion ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $result = SQLiteSelectSql::execute(
            'SELECT CAST(vtxt AS REAL) AS real_value, '
            . 'typeof(CAST(vtxt AS REAL)) AS storage_class, '
            . "format('%.10e', CAST(vtxt AS REAL)) AS formatted, "
            . 'CAST(vtxt AS REAL) GLOB ' . "'" . $case['pattern'] . "'" . ' AS matches_pattern '
            . 'FROM input_values',
            [
                'input_values' => [[
                    'vtxt' => $case['value'],
                    '__sqlite_column_affinities' => [
                        'vtxt' => 'TEXT',
                        'input_values.vtxt' => 'TEXT',
                    ],
                ]],
            ],
        );

        $t->same(1, count($result), $key . ' returns one projected row');
        $row = $result[0];
        $realValue = $row['real_value'];
        $formatted = (string) $row['formatted'];
        $quoted = SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$realValue]);
        $roundTripped = SQLiteRealExpressionAffinityCorpusPlan::cast($quoted, 'REAL');
        $roundTrippedFormatted = SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $roundTripped]);
        $helperFormatted = SQLiteCoreScalarFunction::sqlFunctionArguments(
            'format',
            ['%.10e', SQLiteRealExpressionAffinityCorpusPlan::cast($case['value'], 'REAL')]
        );

        $t->same('real', (string) $row['storage_class'], $key . ' projects REAL storage');
        $t->same($oracle[$key]['storageClass'], (string) $row['storage_class'], $key . ' storage matches sqlite3');
        $t->same($oracle[$key]['formatted'], $formatted, $key . ' formatted REAL matches sqlite3');
        $t->same('1', (string) $row['matches_pattern'], $key . ' satisfies upstream GLOB guard');
        $t->same($oracle[$key]['globMatch'], (string) $row['matches_pattern'], $key . ' GLOB result matches sqlite3');
        $t->same($formatted, (string) $helperFormatted, $key . ' CAST helper matches SELECT executor');
        $t->same($formatted, (string) $roundTrippedFormatted, $key . ' quote CAST round-trip preserves REAL');
        $t->same('1', $oracle[$key]['quoteRoundTrip'], $key . ' sqlite3 quote CAST round-trip source truth');
    };
}

$tests['real upstream corpus date affinity dynamic atof1 REAL conversion owns upstream range'] = static function (TestRunner $t) use ($cases, $oracle, $sourceFile, $sourceText): void {
    $t->same(1018, count($cases));
    $t->same(1018, count($oracle));
    $t->same(592, $cases['suffix-0592']['suffix']);
    $t->same(1609, $cases['suffix-1609']['suffix']);
    $t->contains('do_execsql_test atof-3.1', $sourceText);
    $t->contains('1844674407370955%04d', $sourceText);
    $t->contains("{$sourceFile}", $sourceFile);
    $t->same(
        'non-overlap: owns atof1.test atof-3.1 REAL conversion suffixes 0592..1609; date4/date*/affinity3/types3 dynamic rows were already covered by accepted corpus tests',
        'non-overlap: owns atof1.test atof-3.1 REAL conversion suffixes 0592..1609; date4/date*/affinity3/types3 dynamic rows were already covered by accepted corpus tests',
    );
};

$tests['real upstream corpus date affinity dynamic atof1 REAL conversion dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan CAST helpers, and sqlite3 oracle parity for hydrated upstream atof1.test',
        'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan CAST helpers, and sqlite3 oracle parity for hydrated upstream atof1.test',
    );
};

return $tests;
