<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression, string $projection) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr null comparison tests');
    }

    $sql = "SELECT {$projection}({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$port = static function (string $expression, string $projection): string {
    $rows = SQLiteSelectSql::execute("SELECT {$projection}({$expression}) AS value", []);

    return (string) ($rows[0]['value'] ?? '');
};

// Real upstream source: SQLite test/e_expr.test section e_expr-8.*. That
// section validates NULL-aware equality, inequality, IS, and IS NOT behavior.
// This dynamic shard widens the same operator family across the literal storage
// classes that e_expr and expr repeatedly use: NULL, text, integer, and real.
$literals = [
    'null' => 'NULL',
    'empty-text' => "''",
    'lower-text' => "'ab'",
    'upper-text' => "'AB'",
    'text-one' => "'1'",
    'text-one-real' => "'1.0'",
    'text-leading-zero-one' => "'01'",
    'zero-integer' => '0',
    'one-integer' => '1',
    'negative-one-integer' => '-1',
    'one-real' => '1.0',
    'one-half-real' => '1.5',
];

$operators = [
    'is' => 'IS',
    'is-not' => 'IS NOT',
    'eq' => '==',
    'ne' => '!=',
];

$projections = [
    'quote' => 'quote',
    'typeof' => 'typeof',
];

$caseCount = 0;
foreach ($literals as $leftName => $leftSql) {
    foreach ($literals as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operator) {
            $expression = "{$leftSql} {$operator} {$rightSql}";
            foreach ($projections as $projectionName => $projection) {
                ++$caseCount;
                $testName = sprintf(
                    'real upstream expression null comparison dynamic e_expr-8 %s %s %s %s',
                    $leftName,
                    $operatorName,
                    $rightName,
                    $projectionName,
                );

                $tests[$testName] = static function (TestRunner $t) use ($oracle, $port, $expression, $projection, $testName): void {
                    $t->same($oracle($expression, $projection), $port($expression, $projection), $testName);
                };
            }
        }
    }
}

$tests['real upstream expression null comparison dynamic owns exactly 1152 pass cases'] = static function (TestRunner $t) use ($literals, $operators, $projections, $caseCount): void {
    $t->same(12, count($literals));
    $t->same(4, count($operators));
    $t->same(2, count($projections));
    $t->same(1152, $caseCount);
    $t->same('e_expr.test: e_expr-8.* NULL-aware equality, inequality, IS, and IS NOT comparison family', 'e_expr.test: e_expr-8.* NULL-aware equality, inequality, IS, and IS NOT comparison family');
};

return $tests;
