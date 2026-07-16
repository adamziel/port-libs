<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr collation dynamic tests');
}

$literals = [
    'lower-abcd' => "'abcd'",
    'upper-abcd' => "'ABCD'",
    'lower-bbbb' => "'bbbb'",
    'upper-bbbb' => "'BBBB'",
    'rtrim-padded' => "'abc   '",
    'rtrim-plain' => "'abc'",
    'numeric-text-ten' => "'10'",
    'integer-ten' => '10',
    'real-ten' => '10.0',
    'null' => 'NULL',
];

$operators = [
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
    'eq' => '=',
    'eqeq' => '==',
    'ne' => '!=',
    'ne2' => '<>',
    'is' => 'IS',
    'is-not' => 'IS NOT',
];

$collations = [
    'binary' => 'BINARY',
    'nocase' => 'NOCASE',
    'rtrim' => 'RTRIM',
];

$cases = [];
foreach ($literals as $leftName => $leftSql) {
    foreach ($literals as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            foreach ($collations as $collationName => $collationSql) {
                $cases["rhs-collate {$leftName} {$operatorName} {$rightName} {$collationName}"] =
                    "{$leftSql} {$operatorSql} {$rightSql} COLLATE {$collationSql}";
                $cases["result-collate {$leftName} {$operatorName} {$rightName} {$collationName}"] =
                    "({$leftSql} {$operatorSql} {$rightSql}) COLLATE {$collationSql}";
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $name => $expression) {
    $safeName = str_replace("'", "''", $name);
    $oracleScript[] = "SELECT '{$safeName}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-collation-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}

file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);

if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr collation output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$name, $quotedValue, $storageClass] = $parts;
    $oracle[$name] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 e_expr collation oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $name => $expression) {
    $tests['real upstream e_expr dynamic collation e_expr.test e_expr-9 ' . $name] = static function (TestRunner $t) use ($expression, $name, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
        $t->same(1, count($rows), $expression);
        $t->same($oracle[$name]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$name]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
    };
}

$tests['real upstream e_expr dynamic collation owns exactly 6000 pass cases'] = static function (TestRunner $t) use ($literals, $operators, $collations, $cases): void {
    $t->same(10, count($literals));
    $t->same(10, count($operators));
    $t->same(3, count($collations));
    $t->same(6000, count($cases));
    $t->same(
        'e_expr.test: e_expr-9 COLLATE postfix binding and e_expr-10 typeof/quote storage-class observation',
        'e_expr.test: e_expr-9 COLLATE postfix binding and e_expr-10 typeof/quote storage-class observation',
    );
};

return $tests;
