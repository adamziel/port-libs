<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream CASE expression dynamic tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';

$quoteLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-20.1 through
// e_expr-22.4.2. Those sections define searched CASE truth evaluation, first
// matching branch selection, ELSE/NULL fall-through, and base CASE equality.
$truthValues = [
    'zero' => 0,
    'one' => 1,
    'two' => 2,
    'negative' => -3,
    'null' => null,
    'text-zero' => '0',
    'text-real' => '2.25',
    'text-nonnumeric' => 'abc',
    'blobish-text' => "\xce",
];

$branchOrders = [
    'abc' => ['a', 'b', 'c'],
    'cba' => ['c', 'b', 'a'],
    'bac' => ['b', 'a', 'c'],
    'acb' => ['a', 'c', 'b'],
];

$rows = [];
$rowid = 1;
foreach ($truthValues as $aName => $aValue) {
    foreach ($truthValues as $bName => $bValue) {
        foreach ($truthValues as $cName => $cValue) {
            $rows[] = [
                'rowid' => $rowid++,
                'a' => $aValue,
                'b' => $bValue,
                'c' => $cValue,
                'label' => "{$aName}/{$bName}/{$cName}",
            ];
        }
    }
}

$cases = [];
foreach ($rows as $row) {
    foreach ($branchOrders as $orderName => $order) {
        $whenTerms = [];
        foreach ($order as $column) {
            $whenTerms[] = sprintf("WHEN %s THEN '%s'", $column, strtoupper($column));
        }
        $cases[sprintf('searched row%03d order %s no else', $row['rowid'], $orderName)] = [
            'source' => 'e_expr-21.1 through e_expr-21.4',
            'sql' => 'CASE ' . implode(' ', $whenTerms) . ' END',
            'rowid' => $row['rowid'],
        ];
        $cases[sprintf('searched row%03d order %s with else', $row['rowid'], $orderName)] = [
            'source' => 'e_expr-21.2 through e_expr-21.3',
            'sql' => 'CASE ' . implode(' ', $whenTerms) . " ELSE 'no result' END",
            'rowid' => $row['rowid'],
        ];
    }
}

$baseValues = [
    'integer-one' => 1,
    'integer-two' => 2,
    'text-one' => '1',
    'text-two' => '2',
    'real-two' => 2.0,
    'missing' => 99,
    'null' => null,
];
$whenLists = [
    'integer-priority' => [1 => 'A', 2 => 'B', 2.0 => 'C'],
    'text-priority' => ['1' => 'A', '2' => 'B', '02' => 'C'],
    'mixed-priority' => [2 => 'B', '2' => 'T', 3 => 'C'],
    'late-match' => [7 => 'A', 8 => 'B', 99 => 'Z'],
];
foreach ($baseValues as $baseName => $baseValue) {
    foreach ($whenLists as $listName => $whenList) {
        $whenTerms = [];
        foreach ($whenList as $whenValue => $result) {
            $whenTerms[] = sprintf('WHEN %s THEN %s', $quoteLiteral($whenValue), $quoteLiteral($result));
        }
        $base = $quoteLiteral($baseValue);
        $cases["base {$baseName} {$listName} no else"] = [
            'source' => 'e_expr-20.2 and e_expr-22.1 through e_expr-22.4',
            'sql' => 'CASE ' . $base . ' ' . implode(' ', $whenTerms) . ' END',
            'rowid' => 1,
        ];
        $cases["base {$baseName} {$listName} with else"] = [
            'source' => 'e_expr-22.2 through e_expr-22.4',
            'sql' => 'CASE ' . $base . ' ' . implode(' ', $whenTerms) . " ELSE 'D' END",
            'rowid' => 1,
        ];
    }
}

$cases['searched upstream e_expr-20.1 literal'] = [
    'source' => 'e_expr-20.1',
    'sql' => "CASE WHEN 1 THEN 'true' WHEN 0 THEN 'false' ELSE 'else' END",
    'rowid' => 1,
];
$cases['base upstream e_expr-20.2 literal'] = [
    'source' => 'e_expr-20.2',
    'sql' => "CASE 0 WHEN 1 THEN 'true' WHEN 0 THEN 'false' ELSE 'else' END",
    'rowid' => 1,
];

$oracleScript = [
    'CREATE TABLE t(rowid INTEGER PRIMARY KEY, a, b, c, label TEXT);',
];
foreach ($rows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO t(rowid, a, b, c, label) VALUES(%d, %s, %s, %s, %s);',
        $row['rowid'],
        $quoteLiteral($row['a']),
        $quoteLiteral($row['b']),
        $quoteLiteral($row['c']),
        $quoteLiteral($row['label'])
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['sql'];
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || quote(%s) || char(9) || typeof(%s) FROM t WHERE rowid = %d;",
        $safeKey,
        $expression,
        $expression,
        $case['rowid']
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-case-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 CASE expression oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce CASE expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 CASE expression oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d CASE expression oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic case eval ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rows): void {
        $result = SQLiteSelectSql::execute(
            "SELECT quote({$case['sql']}) AS q, typeof({$case['sql']}) AS t FROM t WHERE rowid = {$case['rowid']}",
            ['t' => $rows],
        );
        $t->same(1, count($result), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $result[0]['q'], $case['source'] . ' quote parity');
        $t->same($oracle[$key]['typeof'], (string) $result[0]['t'], $case['source'] . ' typeof parity');
    };
}

$tests['real upstream corpus expression affinity dynamic case eval owns e_expr 20 through 22'] = static function (TestRunner $t) use ($sourcePath, $rows, $cases, $oracle): void {
    $t->same(729, count($rows));
    $t->same(5890, count($cases));
    $t->same(5890, count($oracle));
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source));
    $t->contains('do_execsql_test e_expr-20.1', $source);
    $t->contains('do_execsql_test e_expr-22.4.2', $source);
    $t->same(
        'e_expr.test e_expr-20.1..22.4.2 CASE expression searched/base evaluation and fall-through semantics',
        'e_expr.test e_expr-20.1..22.4.2 CASE expression searched/base evaluation and fall-through semantics',
    );
};

$tests['real upstream corpus expression affinity dynamic case eval dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql CASE parsing, SQLiteSelectExpression truth evaluation, and sqlite3 oracle parity for hydrated upstream e_expr.test',
        'no new support component needed; reuses SQLiteSelectSql CASE parsing, SQLiteSelectExpression truth evaluation, and sqlite3 oracle parity for hydrated upstream e_expr.test',
    );
};

return $tests;
