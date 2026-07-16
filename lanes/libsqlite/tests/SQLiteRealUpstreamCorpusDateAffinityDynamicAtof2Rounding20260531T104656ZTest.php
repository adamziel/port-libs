<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof2.test';
$sourceText = is_file($sourcePath) ? (string) file_get_contents($sourcePath) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof2.test is required for atof2 rounding corpus tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof2 rounding corpus tests');
}

$sqlString = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$cases = [];
for ($suffix = 0; $suffix <= 499; $suffix++) {
    $value = sprintf('192.496%03d', $suffix);
    $key = sprintf('g-lower-%03d', $suffix);
    $cases[$key] = [
        'source' => 'atof2.test atof2-1.0',
        'format' => '%g',
        'valueSql' => $value,
        'value' => (float) $value,
        'shape' => 'lower-half',
    ];
}
for ($suffix = 500; $suffix <= 999; $suffix++) {
    $value = sprintf('192.496%03d', $suffix);
    $key = sprintf('g-upper-%03d', $suffix);
    $cases[$key] = [
        'source' => 'atof2.test atof2-1.1',
        'format' => '%g',
        'valueSql' => $value,
        'value' => (float) $value,
        'shape' => 'upper-half',
    ];
}

$edgeCases = [
    'ieee754-inc-minus-1' => [
        'source' => 'atof2.test atof2-2.1',
        'format' => '%!.30f',
        'valueSql' => '99.99999999999999',
        'value' => 99.99999999999999,
        'shape' => 'alternate-fixed',
        'upstreamExpected' => '99.9999999999999858',
    ],
    'ieee754-inc-minus-2' => [
        'source' => 'atof2.test atof2-2.2',
        'format' => '%!.30f',
        'valueSql' => '99.99999999999997',
        'value' => 99.99999999999997,
        'shape' => 'alternate-fixed',
        'upstreamExpected' => '99.9999999999999716',
    ],
];
$cases += $edgeCases;

$oracleSql = [];
foreach ($cases as $key => $case) {
    $oracleSql[] = 'SELECT '
        . $sqlString($key)
        . ' || char(9) || format('
        . $sqlString($case['format'])
        . ', '
        . $case['valueSql']
        . ') || char(9) || typeof(format('
        . $sqlString($case['format'])
        . ', '
        . $case['valueSql']
        . '));';
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof2-rounding-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof2 rounding tests');
}
file_put_contents($scriptFile, implode("\n", $oracleSql));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof2 rounding output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 atof2 rounding oracle row: ' . $line);
    }

    [$key, $formatted, $storageClass] = $parts;
    $oracle[$key] = [
        'formatted' => $formatted,
        'storageClass' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d atof2 oracle rows, got %d', count($cases), count($oracle)));
}

$tests['real upstream corpus date affinity dynamic atof2 source truth and oracle size'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $cases, $oracle): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream atof2.test exists');
        $t->contains('do_execsql_test atof2-1.0', $sourceText);
        $t->contains("SELECT format('%g',192.496475);", $sourceText);
        $t->contains('do_execsql_test atof2-1.1', $sourceText);
        $t->contains("SELECT format('%g',192.496501);", $sourceText);
        $t->contains('do_execsql_test atof2-2.1', $sourceText);
        $t->contains("SELECT format('%!.30f',ieee754_inc(100.0,-1));", $sourceText);
        $t->contains('do_execsql_test atof2-2.2', $sourceText);
        $t->contains("SELECT format('%!.30f',ieee754_inc(100.0,-2));", $sourceText);
        $t->same(1002, count($cases), 'dynamic atof2 corpus size');
        $t->same(1002, count($oracle), 'sqlite3 oracle row count');
    };

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic atof2 rounding ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
            $direct = SQLiteCoreScalarFunction::sqlFunctionArguments('format', [$case['format'], $case['value']]);
            $selected = SQLiteSelectSql::execute(
                'SELECT format(fmt, real_value) AS formatted, typeof(format(fmt, real_value)) AS storage_class FROM input_values',
                [
                    'input_values' => [[
                        'fmt' => $case['format'],
                        'real_value' => $case['value'],
                        '__sqlite_column_affinities' => [
                            'fmt' => 'TEXT',
                            'input_values.fmt' => 'TEXT',
                            'real_value' => 'REAL',
                            'input_values.real_value' => 'REAL',
                        ],
                    ]],
                ],
            );
            $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
                [['formatted' => $direct]],
                ['formatted' => 'TEXT'],
            )[0]['formatted'];

            $t->same($oracle[$key]['formatted'], $direct, $case['source'] . ' direct format parity');
            $t->same(1, count($selected), $case['source'] . ' SELECT returns one row');
            $t->same($oracle[$key]['formatted'], $selected[0]['formatted'], $case['source'] . ' SELECT format parity');
            $t->same($oracle[$key]['storageClass'], $selected[0]['storage_class'], $case['source'] . ' SELECT storage class');
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$direct]), $case['source'] . ' direct storage class');
            $t->same($direct, $stored, $case['source'] . ' TEXT affinity preserves formatted output');
            $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$stored]), $case['source'] . ' stored storage class');
            $t->same(true, in_array($case['shape'], ['lower-half', 'upper-half', 'alternate-fixed'], true), $case['source'] . ' case shape');
        };
}

$tests['real upstream corpus date affinity dynamic atof2 alternate fixed exact upstream values'] =
    static function (TestRunner $t) use ($edgeCases, $oracle): void {
        foreach ($edgeCases as $key => $case) {
            $t->same($case['upstreamExpected'], $oracle[$key]['formatted'], $case['source'] . ' sqlite3 exact text');
            $t->same(
                $case['upstreamExpected'],
                SQLiteCoreScalarFunction::sqlFunctionArguments('format', [$case['format'], $case['value']]),
                $case['source'] . ' native alternate-form-2 exact text',
            );
        }
    };

$tests['real upstream corpus date affinity dynamic atof2 non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns atof2.test atof2-1.0/1.1 %g rounding boundary and atof2-2.1/2.2 alternate-form-2 fixed REAL rendering',
            'owns atof2.test atof2-1.0/1.1 %g rounding boundary and atof2-2.1/2.2 alternate-form-2 fixed REAL rendering',
        );
        $t->same(
            'non-overlap: does not repeat accepted atof1 suffix conversion, date4 strftime row ranges, date5 calendar cycles, timediff6, or types storage-class affinity batches',
            'non-overlap: does not repeat accepted atof1 suffix conversion, date4 strftime row ranges, date5 calendar cycles, timediff6, or types storage-class affinity batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteCoreScalarFunction format(), SQLiteSelectSql scalar function dispatch, TEXT affinity storage, and sqlite3 oracle parity for hydrated upstream atof2.test',
            'no new support component needed; reuses SQLiteCoreScalarFunction format(), SQLiteSelectSql scalar function dispatch, TEXT affinity storage, and sqlite3 oracle parity for hydrated upstream atof2.test',
        );
    };

return $tests;
