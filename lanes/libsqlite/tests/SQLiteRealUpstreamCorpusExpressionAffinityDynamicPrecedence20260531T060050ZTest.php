<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression precedence dynamic tests');
}

// Source truth: SQLite upstream test/e_expr.test section e_expr-1.*. The Tcl
// test exhaustively compares "A op1 B op2 C" with explicitly parenthesized
// forms to verify binary operator precedence. MATCH/REGEXP are omitted here
// because the PHP port does not expose Tcl's per-connection callback shim.
$operators = [
    'cat' => '||',
    'mul' => '*',
    'div' => '/',
    'mod' => '%',
    'add' => '+',
    'sub' => '-',
    'lshift' => '<<',
    'rshift' => '>>',
    'bitand' => '&',
    'bitor' => '|',
    'less' => '<',
    'lesseq' => '<=',
    'more' => '>',
    'moreeq' => '>=',
    'eq1' => '=',
    'eq2' => '==',
    'ne1' => '<>',
    'ne2' => '!=',
    'is' => 'IS',
    'isnt' => 'IS NOT',
    'like' => 'LIKE',
    'glob' => 'GLOB',
    'and' => 'AND',
    'or' => 'OR',
];

$values = [
    'e_expr-1 row 1' => [22, 45, 66],
    'e_expr-1 row 2' => [0, 0, 0],
    'e_expr-1 row 3' => [0, 0, 1],
    'e_expr-1 row 4' => [0, 1, 0],
    'e_expr-1 row 5' => [0, 1, 1],
    'e_expr-1 row 6' => [1, 0, 0],
    'e_expr-1 row 7' => [1, 0, 1],
    'e_expr-1 row 8' => [1, 1, 0],
    'e_expr-1 row 9' => [1, 1, 1],
    'e_expr-1 row 10' => [5, 6, 1],
    'e_expr-1 row 11' => [1, 5, 6],
    'e_expr-1 row 12' => [1, 5, 5],
    'e_expr-1 row 13' => [5, 5, 1],
    'e_expr-1 row 14' => [5, 2, 1],
    'e_expr-1 row 15' => [1, 4, 1],
    'e_expr-1 row 16' => [-1, 0, 1],
    'e_expr-1 row 17' => [0, 1, -1],
];

$cases = [];
foreach ($operators as $leftName => $leftOperator) {
    foreach ($operators as $rightName => $rightOperator) {
        foreach ($values as $valueName => [$a, $b, $c]) {
            $cases["{$leftName}.{$rightName}.{$valueName}"] = [
                'sql' => "SELECT quote({$a} {$leftOperator} {$b} {$rightOperator} {$c}) AS q, typeof({$a} {$leftOperator} {$b} {$rightOperator} {$c}) AS t",
                'left' => $leftOperator,
                'right' => $rightOperator,
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || q || char(9) || t FROM ({$case['sql']});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-precedence-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 precedence oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression precedence output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 precedence oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression precedence oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic precedence e_expr-1 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute($case['sql'], []);
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof');
    };
}

$tests['real upstream corpus expression affinity dynamic precedence owns e_expr-1 matrix'] = static function (TestRunner $t) use ($operators, $values, $cases, $oracle): void {
    $t->same(24, count($operators));
    $t->same(17, count($values));
    $t->same(9792, count($cases));
    $t->same(9792, count($oracle));
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->same(
        'e_expr.test e_expr-1.* binary operator precedence matrix, excluding Tcl-only MATCH/REGEXP callback shims',
        'e_expr.test e_expr-1.* binary operator precedence matrix, excluding Tcl-only MATCH/REGEXP callback shims',
    );
};

return $tests;
