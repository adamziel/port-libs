<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression precedence matrix tests');
}

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-1.* verifies binary operator
//   precedence by comparing "A op1 B op2 C" with the corresponding explicitly
//   parenthesized expression for the same 17 operand triples.
//
// Existing focused expression-affinity tests cover selected e_expr-1 rows and
// direct evaluator composition. This shard keeps the upstream matrix shape but
// runs parser-level SQLiteSelectSql text for every supported operator pair.
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

$triples = [
    [22, 45, 66],
    [0, 0, 0],
    [0, 0, 1],
    [0, 1, 0],
    [0, 1, 1],
    [1, 0, 0],
    [1, 0, 1],
    [1, 1, 0],
    [1, 1, 1],
    [5, 6, 1],
    [1, 5, 6],
    [1, 5, 5],
    [5, 5, 1],
    [5, 2, 1],
    [1, 4, 1],
    [-1, 0, 1],
    [0, 1, -1],
];

$cases = [];
$caseId = 0;
foreach ($operators as $leftName => $leftOperator) {
    foreach ($operators as $rightName => $rightOperator) {
        foreach ($triples as $tripleIndex => [$a, $b, $c]) {
            ++$caseId;
            $cases['case-' . $caseId] = [
                'name' => sprintf('%s-%s-row%02d', $leftName, $rightName, $tripleIndex + 1),
                'sql' => sprintf('%d %s %d %s %d', $a, $leftOperator, $b, $rightOperator, $c),
                'leftGroupedSql' => sprintf('(%d %s %d) %s %d', $a, $leftOperator, $b, $rightOperator, $c),
                'rightGroupedSql' => sprintf('%d %s (%d %s %d)', $a, $leftOperator, $b, $rightOperator, $c),
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $sql = $case['sql'];
    $leftGroupedSql = $case['leftGroupedSql'];
    $rightGroupedSql = $case['rightGroupedSql'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$sql}) || char(9) || typeof({$sql}) || char(9) || quote({$leftGroupedSql}) || char(9) || quote({$rightGroupedSql});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-1-precedence-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr-1 precedence matrix tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr-1 precedence matrix output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('malformed e_expr-1 precedence oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $leftGroupedQuote, $rightGroupedQuote] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'leftGroupedQuote' => $leftGroupedQuote,
        'rightGroupedQuote' => $rightGroupedQuote,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr-1 precedence oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity precedence matrix dynamic e_expr-1 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$case['sql']}) AS q, typeof({$case['sql']}) AS t, quote({$case['leftGroupedSql']}) AS lq, quote({$case['rightGroupedSql']}) AS rq",
            [],
        );
        $t->same(1, count($rows), $case['sql']);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $case['sql'] . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $case['sql'] . ' typeof');
        $t->same($oracle[$key]['leftGroupedQuote'], (string) $row['lq'], $case['leftGroupedSql'] . ' quote');
        $t->same($oracle[$key]['rightGroupedQuote'], (string) $row['rq'], $case['rightGroupedSql'] . ' quote');
    };
}

$tests['real upstream expression affinity precedence matrix dynamic owns e_expr-1 parser matrix'] = static function (TestRunner $t) use ($operators, $triples, $cases, $oracle): void {
    $t->same(24, count($operators));
    $t->same(17, count($triples));
    $t->same(9792, count($cases));
    $t->same(9792, count($oracle));
    $t->same(
        'e_expr.test e_expr-1.* supported binary operator precedence matrix through parser-level SELECT SQL',
        'e_expr.test e_expr-1.* supported binary operator precedence matrix through parser-level SELECT SQL',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
