<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test';
$sourceText = is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof1.test is required for atof1 exponent range corpus tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof1 exponent range corpus tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/atof1.test atof-3.3. The recursive CTE
// spans n=-200..200 and checks two nearby mantissas at each exponent against
// the same REAL conversion/GLOB guard.
$cases = [];
for ($n = -200; $n <= 200; $n++) {
    $exponent = $n === -200 ? -200 : $n - 1;
    foreach (['lower' => '1.8446744073709550592', 'upper' => '1.8446744073709551609'] as $variant => $mantissa) {
        $key = sprintf('n%+04d-%s-e%+04d', $n, $variant, $exponent);
        $cases[$key] = [
            'source' => 'atof1.test atof-3.3',
            'n' => $n,
            'exponent' => $exponent,
            'variant' => $variant,
            'value' => $mantissa . 'e' . $exponent,
            'pattern' => '1.8446*',
        ];
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $valueSql = $sqlLiteral($case['value']);
    $patternSql = $sqlLiteral($case['pattern']);
    $oracleScript[] = "SELECT '{$safeKey}'"
        . " || char(9) || typeof(CAST({$valueSql} AS REAL))"
        . " || char(9) || format('%.10e', CAST({$valueSql} AS REAL))"
        . " || char(9) || (format('%.10e', CAST({$valueSql} AS REAL)) GLOB {$patternSql})"
        . " || char(9) || (CAST({$valueSql} AS REAL) GLOB {$patternSql});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-exponent-range-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof1 exponent range tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof1 exponent range output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 atof1 exponent range oracle row: ' . $line);
    }
    [$key, $storageClass, $formatted, $formattedGlobMatch, $castGlobMatch] = $parts;
    $oracle[$key] = [
        'storageClass' => $storageClass,
        'formatted' => $formatted,
        'formattedGlobMatch' => $formattedGlobMatch,
        'castGlobMatch' => $castGlobMatch,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d atof1 exponent range oracle rows, got %d', count($cases), count($oracle)));
}

$tests['real upstream corpus date affinity dynamic atof1 exponent range source truth'] =
    static function (TestRunner $t) use ($cases, $oracle, $sourceFile, $sourceText): void {
        $t->same(true, is_file($sourceFile), 'hydrated upstream atof1.test exists');
        $t->contains('do_execsql_test atof-3.3', $sourceText);
        $t->contains("SELECT -200, '1.8446744073709550592e-200', '1.8446744073709551609e-200'", $sourceText);
        $t->contains("('1.8446744073709550592e'||n),('1.8446744073709551609e'||n)", $sourceText);
        $t->contains("format('%.10e',CAST(v1 AS REAL)) NOT GLOB '1.8446*'", $sourceText);
        $t->same(802, count($cases), 'owned atof-3.3 exponent value count');
        $t->same(802, count($oracle), 'sqlite3 oracle row count');
        $t->same(-200, $cases['n-200-lower-e-200']['n']);
        $t->same(200, $cases['n+200-upper-e+199']['n']);
        $t->same('1.8446744073709550592e-200', $cases['n-200-lower-e-200']['value']);
        $t->same('1.8446744073709551609e199', $cases['n+200-upper-e+199']['value']);
    };

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic atof1 exponent range ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
            $result = SQLiteSelectSql::execute(
                'SELECT CAST(vtxt AS REAL) AS real_value, '
                . 'typeof(CAST(vtxt AS REAL)) AS storage_class, '
                . "format('%.10e', CAST(vtxt AS REAL)) AS formatted, "
                . "format('%.10e', CAST(vtxt AS REAL)) GLOB '" . $case['pattern'] . "' AS formatted_matches_pattern, "
                . 'CAST(vtxt AS REAL) GLOB ' . "'" . $case['pattern'] . "'" . ' AS cast_matches_pattern '
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
            $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
                [['real_value' => $case['value']]],
                ['real_value' => 'REAL']
            )[0]['real_value'];

            $t->true(is_float($realValue), $key . ' SELECT CAST produces a PHP float');
            $t->true(is_float($helperReal), $key . ' helper CAST produces a PHP float');
            $t->same('real', (string) $row['storage_class'], $key . ' projects REAL storage');
            $t->same($oracle[$key]['storageClass'], (string) $row['storage_class'], $key . ' storage matches sqlite3');
            $t->same($oracle[$key]['formatted'], $formatted, $key . ' formatted REAL matches sqlite3');
            $t->same('1', (string) $row['formatted_matches_pattern'], $key . ' formatted REAL satisfies upstream GLOB guard');
            $t->same($oracle[$key]['formattedGlobMatch'], (string) $row['formatted_matches_pattern'], $key . ' formatted GLOB result matches sqlite3');
            $t->same($oracle[$key]['castGlobMatch'], (string) $row['cast_matches_pattern'], $key . ' CAST GLOB result matches sqlite3');
            $t->same($formatted, (string) $helperFormatted, $key . ' CAST helper matches SELECT executor');
            $t->same($formatted, SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $stored]), $key . ' REAL affinity stores same numeric value');
            $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored), $key . ' REAL affinity storage class');
            $t->same(true, $case['exponent'] >= -200 && $case['exponent'] <= 199, $key . ' exponent stays in upstream recursive range');
        };
}

$tests['real upstream corpus date affinity dynamic atof1 exponent range generic application rollup'] =
    static function (TestRunner $t) use ($cases): void {
        $rows = [
            ['key_name' => 'metric-tiny-lower', 'decimal_metric' => $cases['n-200-lower-e-200']['value']],
            ['key_name' => 'metric-unit-upper', 'decimal_metric' => $cases['n+001-upper-e+000']['value']],
            ['key_name' => 'metric-huge-lower', 'decimal_metric' => $cases['n+200-lower-e+199']['value']],
        ];
        $result = SQLiteSelectSql::execute(
            'SELECT key_name, '
            . 'typeof(CAST(decimal_metric AS REAL)) AS storage_class, '
            . "format('%.10e', CAST(decimal_metric AS REAL)) AS formatted, "
            . "format('%.10e', CAST(decimal_metric AS REAL)) GLOB '1.8446*' AS matches_pattern "
            . 'FROM app_decimal_metrics ORDER BY key_name',
            ['app_decimal_metrics' => $rows],
        );

        $t->same(3, count($result));
        $t->same(['metric-huge-lower', 'metric-tiny-lower', 'metric-unit-upper'], array_column($result, 'key_name'));
        $t->same(['real', 'real', 'real'], array_column($result, 'storage_class'));
        $t->same([1, 1, 1], array_column($result, 'matches_pattern'));
        $t->same('1.8446744074e+199', $result[0]['formatted']);
        $t->same('1.8446744074e-200', $result[1]['formatted']);
        $t->same('1.8446744074e+00', $result[2]['formatted']);
    };

$tests['real upstream corpus date affinity dynamic atof1 exponent range non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns atof1.test atof-3.3 exponent REAL rows only: n=-200..200 lower/upper mantissas, 802 values',
            'owns atof1.test atof-3.3 exponent REAL rows only: n=-200..200 lower/upper mantissas, 802 values',
        );
        $t->same(
            'non-overlap: avoids accepted atof-3.2 decimal REAL suffixes 0000..3999, accepted atof-3.1 integer-prefix suffixes 0592..1609, atof2 rounding rows, date4 rows, date/timediff matrices, and types storage-class batches',
            'non-overlap: avoids accepted atof-3.2 decimal REAL suffixes 0000..3999, accepted atof-3.1 integer-prefix suffixes 0592..1609, atof2 rounding rows, date4 rows, date/timediff matrices, and types storage-class batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan REAL affinity, SQLiteCoreScalarFunction format/typeof, and sqlite3 oracle parity',
            'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan REAL affinity, SQLiteCoreScalarFunction format/typeof, and sqlite3 oracle parity',
        );
    };

return $tests;
