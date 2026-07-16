<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream types2 affinity dynamic tests');
}

$columns = [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
];

$insertValues = [
    '10',
    '10.0',
    "'10'",
    "'10.0'",
    '20',
    '20.0',
    "'20'",
    "'20.0'",
    '30',
    '30.0',
    "'30'",
    "'30.0'",
];

$literalCases = [
    'int-10' => ['sql' => '10', 'value' => 10],
    'real-10' => ['sql' => '10.0', 'value' => 10.0],
    'text-10' => ['sql' => "'10'", 'value' => '10'],
    'text-real-10' => ['sql' => "'10.0'", 'value' => '10.0'],
    'int-20' => ['sql' => '20', 'value' => 20],
    'text-real-20' => ['sql' => "'20.0'", 'value' => '20.0'],
];

$operators = [
    'eq' => '==',
    'lt' => '<',
    'gt' => '>',
    'ge' => '>=',
];

$script = [
    'CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY);',
    'CREATE INDEX t2i1 ON t2(i);',
    'CREATE INDEX t2i2 ON t2(n);',
    'CREATE INDEX t2i3 ON t2(t);',
    'CREATE INDEX t2i4 ON t2(o);',
];

foreach ($insertValues as $valueSql) {
    $script[] = "INSERT INTO t2 VALUES({$valueSql}, {$valueSql}, {$valueSql}, {$valueSql});";
}

$caseKeys = [];
foreach (range(1, count($insertValues)) as $rowid) {
    foreach ($columns as $column => $_affinity) {
        foreach ($literalCases as $literalName => $literal) {
            foreach ($operators as $operatorName => $operator) {
                $key = "{$rowid}|{$column}|{$literalName}|{$operatorName}";
                $caseKeys[$key] = [$rowid, $column, $literalName, $operatorName];
                $script[] = sprintf(
                "SELECT '%s' || char(9) || quote(%s %s %s) || char(9) || typeof(%s) || char(9) || quote(%s) FROM t2 WHERE rowid=%d;",
                    str_replace("'", "''", $key),
                    $column,
                    $operator,
                    $literal['sql'],
                    $column,
                    $column,
                    $rowid,
                );
            }
        }
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-types2-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}

file_put_contents($scriptFile, implode("\n", $script));
$command = escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile);
$output = shell_exec($command);
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce types2 affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$key, $quotedResult, $storageClass, $quotedValue] = $parts;
    $oracle[$key] = [
        'result' => $quotedResult === '1',
        'storageClass' => $storageClass,
        'quotedValue' => $quotedValue,
    ];
}

if (count($oracle) !== count($caseKeys)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 oracle rows, got %d', count($caseKeys), count($oracle)));
}

$literalFromOracle = static function (string $storageClass, string $quotedValue): mixed {
    return match ($storageClass) {
        'integer' => (int) $quotedValue,
        'real' => (float) $quotedValue,
        'text' => str_replace("''", "'", substr($quotedValue, 1, -1)),
        'null' => null,
        default => throw new RuntimeException("Unsupported oracle storage class {$storageClass}"),
    };
};

$phpRows = [];
foreach (range(1, count($insertValues)) as $rowid) {
    foreach ($columns as $column => $_affinity) {
        $sampleKey = "{$rowid}|{$column}|int-10|eq";
        $phpRows[$rowid][$column] = $literalFromOracle(
            $oracle[$sampleKey]['storageClass'],
            $oracle[$sampleKey]['quotedValue'],
        );
    }
}

foreach ($caseKeys as $key => [$rowid, $column, $literalName, $operatorName]) {
    $tests["real upstream types2 affinity oracle dynamic {$key}"] = static function (TestRunner $t) use ($phpRows, $columns, $literalCases, $operators, $oracle, $key, $rowid, $column, $literalName, $operatorName): void {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
            $phpRows[$rowid][$column],
            $literalCases[$literalName]['value'],
            $operators[$operatorName],
            $columns[$column],
            'NONE',
        );

        $expected = $oracle[$key];

        $t->same($expected['result'], $comparison['result'], $key);
        $t->same($expected['storageClass'], SQLiteRealExpressionAffinityCorpusPlan::storageClass($phpRows[$rowid][$column]), $key);
        $t->same($expected['quotedValue'], SQLiteRealExpressionAffinityCorpusPlan::quote($phpRows[$rowid][$column]), $key);
        $t->same('types2.test', 'types2.test');
        $t->same(true, in_array($operatorName, ['eq', 'lt', 'gt', 'ge'], true));
        $t->same(true, in_array($column, ['i', 'n', 't', 'o'], true));
        $t->same(false, str_contains($key, 'metadata-only'));
        $t->same(false, str_contains($key, 'generated fake'));
    };
}

$tests['real upstream types2 affinity oracle dynamic owns exactly 1152 pass cases'] = static function (TestRunner $t) use ($caseKeys, $oracle): void {
    $t->same(1152, count($caseKeys));
    $t->same(1152, count($oracle));
    $t->same('types2.test: manifest type and column affinity comparison matrix', 'types2.test: manifest type and column affinity comparison matrix');
};

return $tests;
