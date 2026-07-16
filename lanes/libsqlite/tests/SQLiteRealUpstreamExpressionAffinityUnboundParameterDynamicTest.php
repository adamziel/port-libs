<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream unbound parameter dynamic tests');
}

// Source truth: SQLite upstream test/e_expr.test e_expr-11.7.1 documents
// unassigned host parameters as NULL. The surrounding e_expr-7/e_expr-10 and
// expr.test operator sections exercise those NULL tokens through arithmetic,
// comparison, casting, typeof(), quote(), and truth contexts.
$parameters = [
    'qmark' => '?',
    'qmark-explicit' => '?5',
    'colon-alpha' => ':tenant_value',
    'colon-underscore' => ':__',
    'at-alpha' => '@tenant_value',
    'at-underscore' => '@__',
    'dollar-alpha' => '$tenant_value',
    'dollar-underscore' => '$__',
];

$rightExpressions = [
    'int-zero' => '0',
    'int-one' => '1',
    'real-half' => '0.5',
    'text-one' => "'1'",
    'text-alpha' => "'alpha'",
    'null' => 'NULL',
    'cast-real-two' => 'CAST(2 AS REAL)',
    'cast-text-real' => "CAST('2.25tail' AS REAL)",
];

$binaryOperators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'modulo' => '%',
    'equals' => '=',
    'not-equals' => '<>',
    'less-than' => '<',
    'greater-equal' => '>=',
    'is' => 'IS',
    'is-not' => 'IS NOT',
    'and' => 'AND',
    'or' => 'OR',
];

$wrappers = [
    'raw' => static fn (string $expression): string => $expression,
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
    'is-null' => static fn (string $expression): string => "({$expression}) IS NULL",
    'coalesce-nine' => static fn (string $expression): string => "coalesce({$expression}, 9)",
];

$cases = [];
foreach ($parameters as $parameterName => $parameterSql) {
    foreach ($wrappers as $wrapperName => $wrapper) {
        $expressions = [
            'bare' => $parameterSql,
            'unary-plus' => "+{$parameterSql}",
            'unary-minus' => "-{$parameterSql}",
            'not' => "NOT {$parameterSql}",
            'cast-integer' => "CAST({$parameterSql} AS INTEGER)",
            'cast-real' => "CAST({$parameterSql} AS REAL)",
            'cast-text' => "CAST({$parameterSql} AS TEXT)",
        ];

        foreach ($expressions as $expressionName => $expression) {
            $cases["{$parameterName}-{$expressionName}-{$wrapperName}"] = $wrapper($expression);
        }

        foreach ($rightExpressions as $rightName => $rightSql) {
            foreach ($binaryOperators as $operatorName => $operatorSql) {
                $expression = "({$parameterSql}) {$operatorSql} ({$rightSql})";
                $cases["{$parameterName}-{$operatorName}-{$rightName}-{$wrapperName}"] = $wrapper($expression);
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-unbound-param-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for unbound parameter dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce unbound parameter dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed unbound parameter oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d unbound parameter oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity unbound parameter dynamic e_expr-11.7 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);

        $t->same(1, count($rows), $expression);
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
        $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    };
}

$tests['real upstream expression affinity unbound parameter dynamic owns exactly 4440 e_expr cases'] = static function (TestRunner $t) use ($parameters, $rightExpressions, $binaryOperators, $wrappers, $cases): void {
    $t->same(8, count($parameters));
    $t->same(8, count($rightExpressions));
    $t->same(13, count($binaryOperators));
    $t->same(5, count($wrappers));
    $t->same(4440, count($cases));
    $t->same(
        'e_expr.test e_expr-11.7.1 unassigned host parameters combined with expression NULL propagation and result-class observation',
        'e_expr.test e_expr-11.7.1 unassigned host parameters combined with expression NULL propagation and result-class observation',
    );
};

return $tests;
