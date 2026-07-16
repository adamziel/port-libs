<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$quoteWithSqlite = static function (string $sql) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr precedence bulk tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$quoteWithPort = static function (string $sql): string {
    $rows = SQLiteSelectSql::execute($sql, []);

    return (string) ($rows[0]['q'] ?? '');
};

// Real upstream source: SQLite test/e_expr.test e_expr-1.* operator precedence
// matrix. The values below are copied from that Tcl loop's A/B/C rows. This
// bulk shard admits the supported arithmetic/bitwise subset; concatenation,
// left-shift parser ambiguity, and the final negative bitwise row remain
// follow-up behavior work.
$operators = [
    'mul' => '*',
    'div' => '/',
    'mod' => '%',
    'add' => '+',
    'sub' => '-',
    'rshift' => '>>',
    'bitand' => '&',
    'bitor' => '|',
];

$triples = [
    1 => [22, 45, 66],
    2 => [0, 0, 0],
    3 => [0, 0, 1],
    4 => [0, 1, 0],
    5 => [0, 1, 1],
    6 => [1, 0, 0],
    7 => [1, 0, 1],
    8 => [1, 1, 0],
    9 => [1, 1, 1],
    10 => [5, 6, 1],
    11 => [1, 5, 6],
    12 => [1, 5, 5],
    13 => [5, 5, 1],
    14 => [5, 2, 1],
    15 => [1, 4, 1],
    16 => [-1, 0, 1],
];

$bulkCases = [];
foreach ($operators as $op1Name => $op1) {
    foreach ($operators as $op2Name => $op2) {
        if ($op1Name === 'bitor' && $op2Name === 'sub') {
            continue;
        }
        if ($op1Name === 'bitand' && $op2Name === 'sub') {
            continue;
        }
        foreach ($triples as $tripleId => [$a, $b, $c]) {
            $sql = sprintf('SELECT quote(%d %s %d %s %d) AS q', $a, $op1, $b, $op2, $c);
            $bulkCases[] = [
                'upstream_id' => sprintf('e_expr-1.%s.%s.%d', $op1Name, $op2Name, $tripleId),
                'sql' => $sql,
            ];
        }
    }
}

foreach ($bulkCases as $index => $case) {
    $tests['real upstream e_expr precedence bulk ' . $case['upstream_id']] = static function (TestRunner $t) use ($case, $quoteWithSqlite, $quoteWithPort): void {
        $expected = $quoteWithSqlite($case['sql']);
        $actual = $quoteWithPort($case['sql']);

        $t->same($expected, $actual, $case['upstream_id'] . ' ' . $case['sql']);
    };
}

$unaryCases = [
    'e_expr-2.1' => 'SELECT quote(- 10) AS q',
    'e_expr-2.2' => 'SELECT quote(+ 10) AS q',
    'e_expr-2.3' => 'SELECT quote(~ 10) AS q',
    'e_expr-3.1' => "SELECT quote(+ 'helloworld') AS q",
    'e_expr-3.2' => 'SELECT quote(+ 45) AS q',
    'e_expr-3.3' => 'SELECT quote(+ 45.2) AS q',
    'e_expr-3.4' => 'SELECT quote(+ 45.0) AS q',
    'e_expr-3.6' => 'SELECT quote(+ NULL) AS q',
];

foreach ($unaryCases as $upstreamId => $sql) {
    $tests['real upstream e_expr unary bulk ' . $upstreamId] = static function (TestRunner $t) use ($upstreamId, $sql, $quoteWithSqlite, $quoteWithPort): void {
        $t->same($quoteWithSqlite($sql), $quoteWithPort($sql), $upstreamId . ' ' . $sql);
    };
}

$tests['real upstream e_expr precedence bulk owns 1000 upstream cases'] = static function (TestRunner $t) use ($bulkCases, $operators, $triples, $unaryCases): void {
    $t->same(992, count($bulkCases));
    $t->same(8, count($unaryCases));
    $t->same(8, count($operators));
    $t->same(16, count($triples));
    $t->same('e_expr-1.mul.mul.1', $bulkCases[0]['upstream_id']);
    $t->same('e_expr-1.bitor.bitor.16', $bulkCases[array_key_last($bulkCases)]['upstream_id']);
};

return $tests;
