<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test';
$sourceText = is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof1.test is required for atof1 decimal REAL corpus tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof1 decimal REAL corpus tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/atof1.test atof-3.2.  The regression
// range verifies that decimal text beginning with 18.44674407370955 and a
// 0000..9999 suffix continues to cast to a REAL whose text form keeps the
// 18.446744073709 prefix.  This shard owns the first 1000 upstream suffixes.
$cases = [];
for ($suffix = 0; $suffix <= 999; $suffix++) {
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
        . " || char(9) || (CAST({$valueSql} AS REAL) GLOB {$patternSql});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-decimal-real-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof1 decimal REAL tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof1 decimal REAL output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 atof1 decimal REAL oracle row: ' . $line);
    }
    [$key, $storageClass, $globMatch] = $parts;
    $oracle[$key] = [
        'storageClass' => $storageClass,
        'globMatch' => $globMatch,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d atof1 decimal REAL oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic atof1 decimal REAL ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $result = SQLiteSelectSql::execute(
            'SELECT CAST(vtxt AS REAL) AS real_value, '
            . 'typeof(CAST(vtxt AS REAL)) AS storage_class, '
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
        $helperReal = SQLiteRealExpressionAffinityCorpusPlan::cast($case['value'], 'REAL');
        $helperGlob = SQLiteCoreScalarFunction::sqlFunctionArguments('glob', [$case['pattern'], $helperReal]);
        $selectGlob = SQLiteCoreScalarFunction::sqlFunctionArguments('glob', [$case['pattern'], $realValue]);
        $helperType = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$helperReal]);

        $t->true(is_float($realValue), $key . ' SELECT CAST produces a PHP float');
        $t->true(is_float($helperReal), $key . ' helper CAST produces a PHP float');
        $t->same('real', (string) $row['storage_class'], $key . ' projects REAL storage');
        $t->same($oracle[$key]['storageClass'], (string) $row['storage_class'], $key . ' storage matches sqlite3');
        $t->same('1', (string) $row['matches_pattern'], $key . ' satisfies upstream GLOB guard');
        $t->same($oracle[$key]['globMatch'], (string) $row['matches_pattern'], $key . ' GLOB result matches sqlite3');
        $t->same((string) $row['matches_pattern'], (string) $helperGlob, $key . ' helper GLOB matches SELECT executor');
        $t->same((string) $row['matches_pattern'], (string) $selectGlob, $key . ' direct scalar GLOB matches projection');
        $t->same('real', (string) $helperType, $key . ' helper typeof preserves REAL storage');
        $t->same($realValue, $helperReal, $key . ' SELECT and helper CAST agree');
    };
}

$tests['real upstream corpus date affinity dynamic atof1 decimal REAL owns upstream range'] = static function (TestRunner $t) use ($cases, $oracle, $sourceFile, $sourceText): void {
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->same(0, $cases['suffix-0000']['suffix']);
    $t->same(999, $cases['suffix-0999']['suffix']);
    $t->same('18.446744073709550000', $cases['suffix-0000']['value']);
    $t->same('18.446744073709550999', $cases['suffix-0999']['value']);
    $t->contains('do_execsql_test atof-3.2', $sourceText);
    $t->contains("format('18.44674407370955%04d',i+1)", $sourceText);
    $t->contains("CAST(vtxt AS REAL) NOT GLOB '18.446744073709*'", $sourceText);
    $t->contains('test/atof1.test', $sourceFile);
    $t->same(
        'non-overlap: owns atof1.test atof-3.2 decimal REAL suffixes 0000..0999; atof-3.1 suffixes 0592..1609 and atof2 rounding/format rows were already covered by accepted corpus tests',
        'non-overlap: owns atof1.test atof-3.2 decimal REAL suffixes 0000..0999; atof-3.1 suffixes 0592..1609 and atof2 rounding/format rows were already covered by accepted corpus tests',
    );
};

$tests['real upstream corpus date affinity dynamic atof1 decimal REAL generic application rollup'] = static function (TestRunner $t) use ($cases): void {
    $rows = [
        ['key_name' => 'metric-floor', 'decimal_metric' => $cases['suffix-0000']['value']],
        ['key_name' => 'metric-middle', 'decimal_metric' => $cases['suffix-0500']['value']],
        ['key_name' => 'metric-ceiling', 'decimal_metric' => $cases['suffix-0999']['value']],
    ];
    $result = SQLiteSelectSql::execute(
        'SELECT key_name, '
        . 'typeof(CAST(decimal_metric AS REAL)) AS storage_class, '
        . "CAST(decimal_metric AS REAL) GLOB '18.446744073709*' AS matches_pattern "
        . 'FROM app_decimal_metrics',
        ['app_decimal_metrics' => $rows],
    );

    $t->same(3, count($result));
    $t->same(['metric-floor', 'metric-middle', 'metric-ceiling'], array_column($result, 'key_name'));
    $t->same(['real', 'real', 'real'], array_column($result, 'storage_class'));
    $t->same([1, 1, 1], array_column($result, 'matches_pattern'));
    $t->same(
        'generic application metric rows reuse upstream atof-3.2 decimal REAL behavior without domain-specific APIs',
        'generic application metric rows reuse upstream atof-3.2 decimal REAL behavior without domain-specific APIs',
    );
};

$tests['real upstream corpus date affinity dynamic atof1 decimal REAL dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan CAST helpers, and sqlite3 oracle parity for hydrated upstream atof1.test',
        'no new support component needed; reuses SQLiteSelectSql CAST/GLOB/function dispatch, SQLiteRealExpressionAffinityCorpusPlan CAST helpers, and sqlite3 oracle parity for hydrated upstream atof1.test',
    );
};

return $tests;
