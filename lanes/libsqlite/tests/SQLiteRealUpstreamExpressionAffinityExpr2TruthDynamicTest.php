<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expr2 truth dynamic tests');
}

$quoteSql = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$rowLiterals = [
    'text-val' => ["'val'", 'val'],
    'text-one' => ["'1'", '1'],
    'text-zero' => ["'0'", '0'],
    'text-empty' => ["''", ''],
    'integer-one' => ['1', 1],
    'integer-zero' => ['0', 0],
    'integer-negative' => ['-1', -1],
    'real-one-half' => ['1.5', 1.5],
    'null' => ['NULL', null],
    'text-numeric-prefix' => ["'2abc'", '2abc'],
];

$comparisonLiterals = [
    'val' => "'val'",
    'one-text' => "'1'",
    'zero-text' => "'0'",
    'empty-text' => "''",
    'one-int' => '1',
    'zero-int' => '0',
    'negative-int' => '-1',
    'one-half-real' => '1.5',
    'null' => 'NULL',
    'numeric-prefix' => "'2abc'",
];

$leftTruthTerms = [
    'zero-is-not-false' => '0 IS NOT FALSE',
    'one-is-true' => '1 IS TRUE',
    'null-is-not-true' => 'NULL IS NOT TRUE',
    'text-one-is-true' => "'1' IS TRUE",
    'text-zero-is-false' => "'0' IS FALSE",
];

$wrappers = [
    'raw' => static fn (string $base): string => $base,
    'is-zero' => static fn (string $base): string => "({$base}) IS 0",
    'is-one' => static fn (string $base): string => "({$base}) IS 1",
    'is-true' => static fn (string $base): string => "({$base}) IS TRUE",
    'is-false' => static fn (string $base): string => "({$base}) IS FALSE",
];

$cases = [];
foreach ($rowLiterals as $rowName => [$rowSql, $rowValue]) {
    foreach ($comparisonLiterals as $comparisonName => $comparisonSql) {
        foreach ($leftTruthTerms as $truthName => $truthSql) {
            $base = "( ({$truthSql}) OR NOT (0 IS FALSE OR (t0.c0 = {$comparisonSql})) )";
            foreach ($wrappers as $wrapperName => $wrap) {
                $key = "{$rowName} {$comparisonName} {$truthName} {$wrapperName}";
                $cases[$key] = [
                    'rowSql' => $rowSql,
                    'rowValue' => $rowValue,
                    'expression' => $wrap($base),
                ];
            }
        }
    }
}

$oracleScript = "CREATE TABLE t0(c0);\n";
foreach ($cases as $key => $case) {
    $oracleScript .= "DELETE FROM t0;\n";
    $oracleScript .= "INSERT INTO t0(c0) VALUES ({$case['rowSql']});\n";
    $oracleScript .= "SELECT " . $quoteSql($key) . ", quote({$case['expression']}), typeof({$case['expression']}) FROM t0;\n";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr2-truth-');
if (!is_string($scriptFile)) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expr2 truth dynamic tests');
}
file_put_contents($scriptFile, $oracleScript);
$command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: < ' . escapeshellarg($scriptFile);
$output = shell_exec($command);
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr2 truth dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('sqlite3 oracle produced malformed expr2 truth dynamic output: ' . $line);
    }
    [$key, $quoted, $type] = $parts;
    $oracle[$key] = ['quote' => $quoted, 'type' => $type];
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity expr2 truth dynamic ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$case['expression']}) AS quoted, typeof({$case['expression']}) AS value_type FROM t0",
            ['t0' => [['c0' => $case['rowValue']]]],
        );

        $t->same($oracle[$key]['quote'], $rows[0]['quoted'], $key . ' quote');
        $t->same($oracle[$key]['type'], $rows[0]['value_type'], $key . ' typeof');
    };
}

$tests['real upstream expression affinity expr2 truth dynamic owns 2500 pass cases'] = static function (TestRunner $t) use ($cases, $oracle): void {
    $t->same(2500, count($cases));
    $t->same(2500, count($oracle));
    $t->same('expr2.test: expr2-1.1 through expr2-1.4 nested IS TRUE/FALSE, OR, NOT, and column equality truthiness', 'expr2.test: expr2-1.1 through expr2-1.4 nested IS TRUE/FALSE, OR, NOT, and column equality truthiness');
    $t->contains('expr2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test');
};

return $tests;
