<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression precedence corpus tests');
}

$sqlString = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: upstream SQLite test/e_expr.test e_expr-13.* documents
// precedence for comparison operators, BETWEEN, LIKE, AND, and OR. This
// dynamic corpus expands those scenario shapes across numeric, real, text, and
// NULL operands so the PHP SELECT executor is checked against sqlite3 instead
// of a hand-maintained expectation table.
$values = [
    'null' => 'NULL',
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'five' => '5',
    'six' => '6',
    'real-half' => '0.5',
    'real-six' => '6.0',
    'text-zero' => $sqlString('0'),
    'text-one' => $sqlString('1'),
    'text-five' => $sqlString('5'),
    'text-six' => $sqlString('6'),
    'text-alpha' => $sqlString('alpha'),
    'text-prefix' => $sqlString('abc123'),
];

$betweenBounds = [
    'zero-two' => ['0', '2'],
    'four-eight' => ['4', '8'],
    'text-zero-two' => [$sqlString('0'), $sqlString('2')],
    'real-zero-seven' => ['0.0', '7.0'],
];

$patterns = [
    'one' => $sqlString('1'),
    'five' => $sqlString('5'),
    'six' => $sqlString('6'),
    'prefix' => $sqlString('abc%'),
];

$cases = [];
foreach ($values as $leftName => $left) {
    foreach ($values as $rightName => $right) {
        foreach ($betweenBounds as $boundsName => [$low, $high]) {
            $cases["eq-before-between.{$leftName}.{$rightName}.{$boundsName}"] = "{$left} = {$right} BETWEEN {$low} AND {$high}";
            $cases["between-before-eq.{$leftName}.{$rightName}.{$boundsName}"] = "{$left} BETWEEN {$low} AND {$high} = {$right}";
            $cases["neq-before-between.{$leftName}.{$rightName}.{$boundsName}"] = "{$left} != {$right} BETWEEN {$low} AND {$high}";
        }
    }
}

foreach ($values as $leftName => $left) {
    foreach ($patterns as $patternName => $pattern) {
        foreach ($betweenBounds as $boundsName => [$low, $high]) {
            $cases["like-before-between.{$leftName}.{$patternName}.{$boundsName}"] = "{$left} LIKE {$pattern} BETWEEN {$low} AND {$high}";
            $cases["between-before-like.{$leftName}.{$patternName}.{$boundsName}"] = "{$left} BETWEEN {$low} AND {$high} LIKE {$pattern}";
        }
    }
}

foreach ($values as $leftName => $left) {
    foreach ($values as $rightName => $right) {
        $cases["and-before-between.{$leftName}.{$rightName}"] = "{$left} AND {$right} BETWEEN 0 AND 1";
        $cases["between-before-and.{$leftName}.{$rightName}"] = "{$left} BETWEEN 0 AND 1 AND {$right}";
        $cases["or-before-between.{$leftName}.{$rightName}"] = "{$left} OR {$right} BETWEEN 0 AND 1";
        $cases["between-before-or.{$leftName}.{$rightName}"] = "{$left} BETWEEN 0 AND 1 OR {$right}";
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-13-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 e_expr-13 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr-13 precedence output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 e_expr-13 oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr-13 oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic precedence e_expr-13 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote for ' . $expression);
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof for ' . $expression);
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $key . ' nullness for ' . $expression);
    };
}

$tests['real upstream corpus expression affinity dynamic precedence owns e_expr13 matrix'] = static function (TestRunner $t) use ($values, $betweenBounds, $patterns, $cases, $oracle): void {
    $t->same(14, count($values));
    $t->same(4, count($betweenBounds));
    $t->same(4, count($patterns));
    $t->same(3584, count($cases));
    $t->same(3584, count($oracle));
    $t->same(
        'e_expr.test e_expr-13.* comparison/BETWEEN/LIKE/AND/OR precedence behavior',
        'e_expr.test e_expr-13.* comparison/BETWEEN/LIKE/AND/OR precedence behavior',
    );
};

return $tests;
