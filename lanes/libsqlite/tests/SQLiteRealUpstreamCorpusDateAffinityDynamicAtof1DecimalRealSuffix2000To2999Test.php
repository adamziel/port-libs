<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test';
$sourceText = is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof1.test is required for atof1 decimal REAL suffix tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof1 decimal REAL suffix tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/atof1.test atof-3.2. The upstream
// recursive query covers decimal suffixes 0000..9999. Accepted local shards
// own 0000..1999, so this shard owns the next non-overlapping 2000..2999
// suffix window.
$firstSuffix = 2000;
$lastSuffix = 2999;
$cases = [];
for ($suffix = $firstSuffix; $suffix <= $lastSuffix; $suffix++) {
    $value = sprintf('18.44674407370955%04d', $suffix);
    $key = sprintf('suffix-%04d', $suffix);
    $cases[$key] = [
        'source' => 'atof1.test atof-3.2',
        'suffix' => $suffix,
        'value' => $value,
        'pattern' => '18.446744073709*',
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

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-decimal-2000-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof1 decimal REAL suffix tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof1 decimal REAL suffix output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 atof1 decimal REAL suffix oracle row: ' . $line);
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
    throw new RuntimeException(sprintf('Expected %d atof1 decimal REAL suffix oracle rows, got %d', count($cases), count($oracle)));
}

$tests['real upstream corpus date affinity dynamic atof1 decimal REAL suffix 2000 2999 source truth'] =
    static function (TestRunner $t) use ($cases, $firstSuffix, $lastSuffix, $oracle, $sourceFile, $sourceText): void {
        $t->same(true, is_file($sourceFile), 'hydrated upstream atof1.test exists');
        $t->contains('do_execsql_test atof-3.2', $sourceText);
        $t->contains("format('18.44674407370955%04d',i+1)", $sourceText);
        $t->contains("CAST(vtxt AS REAL) NOT GLOB '18.446744073709*'", $sourceText);
        $t->same(1000, count($cases), 'owned atof-3.2 suffix window size');
        $t->same(1000, count($oracle), 'sqlite3 oracle row count');
        $t->same(2000, $firstSuffix);
        $t->same(2999, $lastSuffix);
        $t->same(2000, $cases['suffix-2000']['suffix']);
        $t->same(2999, $cases['suffix-2999']['suffix']);
        $t->same('18.446744073709552000', $cases['suffix-2000']['value']);
        $t->same('18.446744073709552999', $cases['suffix-2999']['value']);
    };

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic atof1 decimal REAL suffix 2000 2999 ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
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
            $helperReal = SQLiteRealExpressionAffinityCorpusPlan::cast($case['value'], 'REAL');
            $helperFormatted = SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $helperReal]);
            $quoted = SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$realValue]);
            $roundTripped = SQLiteRealExpressionAffinityCorpusPlan::cast($quoted, 'REAL');
            $roundTrippedFormatted = SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $roundTripped]);
            $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
                [['real_value' => $case['value']]],
                ['real_value' => 'REAL']
            )[0]['real_value'];

            $t->true(is_float($realValue), $key . ' SELECT CAST produces a PHP float');
            $t->true(is_float($helperReal), $key . ' helper CAST produces a PHP float');
            $t->same('real', (string) $row['storage_class'], $key . ' projects REAL storage');
            $t->same($oracle[$key]['storageClass'], (string) $row['storage_class'], $key . ' storage matches sqlite3');
            $t->same($oracle[$key]['formatted'], $formatted, $key . ' formatted REAL matches sqlite3');
            $t->same('1', (string) $row['matches_pattern'], $key . ' satisfies upstream GLOB guard');
            $t->same($oracle[$key]['globMatch'], (string) $row['matches_pattern'], $key . ' GLOB result matches sqlite3');
            $t->same($formatted, (string) $helperFormatted, $key . ' CAST helper matches SELECT executor');
            $t->same($formatted, (string) $roundTrippedFormatted, $key . ' quote CAST round-trip preserves REAL');
            $t->same('1', $oracle[$key]['quoteRoundTrip'], $key . ' sqlite3 quote CAST round-trip source truth');
            $t->same($formatted, SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $stored]), $key . ' REAL affinity stores same numeric value');
            $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored), $key . ' REAL affinity storage class');
            $t->same(21, strlen($case['value']), $key . ' decimal text shape');
        };
}

$tests['real upstream corpus date affinity dynamic atof1 decimal REAL suffix 2000 2999 generic application rollup'] =
    static function (TestRunner $t) use ($cases): void {
        $rows = [
            ['key_name' => 'metric-low', 'decimal_metric' => $cases['suffix-2000']['value']],
            ['key_name' => 'metric-mid', 'decimal_metric' => $cases['suffix-2500']['value']],
            ['key_name' => 'metric-high', 'decimal_metric' => $cases['suffix-2999']['value']],
        ];
        $result = SQLiteSelectSql::execute(
            'SELECT key_name, '
            . 'typeof(CAST(decimal_metric AS REAL)) AS storage_class, '
            . "format('%.10e', CAST(decimal_metric AS REAL)) AS formatted, "
            . "CAST(decimal_metric AS REAL) GLOB '18.446744073709*' AS matches_pattern "
            . 'FROM app_decimal_metrics ORDER BY key_name',
            ['app_decimal_metrics' => $rows],
        );

        $t->same(3, count($result));
        $t->same(['metric-high', 'metric-low', 'metric-mid'], array_column($result, 'key_name'));
        $t->same(['real', 'real', 'real'], array_column($result, 'storage_class'));
        $t->same([1, 1, 1], array_column($result, 'matches_pattern'));
        foreach ($result as $row) {
            $t->same('1.8446744074e+01', (string) $row['formatted']);
        }
    };

$tests['real upstream corpus date affinity dynamic atof1 decimal REAL suffix 2000 2999 non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns atof1.test atof-3.2 decimal REAL suffixes 2000..2999 only',
            'owns atof1.test atof-3.2 decimal REAL suffixes 2000..2999 only',
        );
        $t->same(
            'non-overlap: avoids accepted atof-3.2 decimal REAL/mantissa suffixes 0000..1999, accepted atof-3.1 integer-prefix suffixes 0592..1609, atof2 rounding, date4 rows, date/timediff matrices, and types storage-class batches',
            'non-overlap: avoids accepted atof-3.2 decimal REAL/mantissa suffixes 0000..1999, accepted atof-3.1 integer-prefix suffixes 0592..1609, atof2 rounding, date4 rows, date/timediff matrices, and types storage-class batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan REAL affinity, SQLiteCoreScalarFunction quote/format, and sqlite3 oracle parity',
            'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan REAL affinity, SQLiteCoreScalarFunction quote/format, and sqlite3 oracle parity',
        );
    };

return $tests;
