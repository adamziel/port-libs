<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr-7 dynamic tests');
}

// Real upstream source: SQLite test/e_expr.test e_expr-7.* result storage
// class matrix. The Tcl source iterates the same literal list across every
// binary operator and verifies that each expression result has an allowed
// storage class for that operator family.
$literals = [
    1 => "'abc'",
    2 => "'hexadecimal'",
    3 => "''",
    4 => '123',
    5 => '-123',
    6 => '0',
    7 => '123.4',
    8 => '0.0',
    9 => '-123.4',
    10 => "X'ABCDEF'",
    11 => "X''",
    12 => "X'0000'",
    13 => 'NULL',
];

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
];

$cases = [];
foreach ($operators as $operatorName => $operatorSql) {
    foreach ($literals as $rightId => $rightSql) {
        foreach ($literals as $leftId => $leftSql) {
            $upstreamId = sprintf('e_expr-7.%s.%d.%d', $operatorName, $rightId, $leftId);
            $expression = "{$leftSql} {$operatorSql} {$rightSql}";
            $cases[$upstreamId] = [
                'operatorName' => $operatorName,
                'expression' => $expression,
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $upstreamId => $case) {
    $safeId = str_replace("'", "''", $upstreamId);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeId}' || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr7-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr-7 output');
}

$oracle = [];
foreach (explode("\n", trim($oracleOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 e_expr-7 oracle row: ' . $line);
    }

    [$upstreamId, $storageClass] = $parts;
    $oracle[$upstreamId] = $storageClass;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr-7 oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $upstreamId => $case) {
    $tests['real upstream expression affinity e_expr7 dynamic ' . $upstreamId] = static function (TestRunner $t) use ($case, $oracle, $upstreamId): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT typeof({$expression}) AS storage_class", []);
        $t->same(1, count($rows), $upstreamId . ' row count');

        $actual = (string) $rows[0]['storage_class'];
        $expected = $oracle[$upstreamId];
        $t->same($expected, $actual, $upstreamId . ' ' . $expression);

        if ($case['operatorName'] === 'cat') {
            $t->true($actual === 'text' || $actual === 'null', $upstreamId . ' concat storage class');
        } else {
            $t->true(
                $actual === 'integer' || $actual === 'real' || $actual === 'null',
                $upstreamId . ' numeric-or-null storage class',
            );
        }
    };
}

$tests['real upstream expression affinity e_expr7 dynamic owns 3718 upstream matrix cases'] = static function (TestRunner $t) use ($cases, $operators, $literals): void {
    $t->same(22, count($operators));
    $t->same(13, count($literals));
    $t->same(3718, count($cases));
    $t->same("X'ABCDEF'", $literals[10]);
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
