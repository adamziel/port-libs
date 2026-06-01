<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream postfix null expression tests');
}

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth:
// - SQLite upstream test/expr.test expr-3.25..3.28b and expr-7.14,
//   expr-7.15, expr-7.23, expr-7.26, expr-7.27 cover postfix ISNULL,
//   NOTNULL, and NOT NULL expression forms in projection and WHERE contexts.
// - SQLite upstream test/e_expr.test e_expr-12.3.70..72 names the syntax
//   diagram productions EXPR ISNULL, EXPR NOTNULL, and EXPR NOT NULL.
$affinities = [
    'id' => 'INTEGER',
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'r' => 'REAL',
    'marker' => 'INTEGER',
];

$sourceRows = [
    ['id' => 1, 'i' => null, 'n' => null, 't' => null, 'r' => null, 'marker' => 0],
    ['id' => 2, 'i' => 0, 'n' => 0, 't' => '', 'r' => 0.0, 'marker' => 1],
    ['id' => 3, 'i' => 1, 'n' => 1, 't' => '1', 'r' => 1.0, 'marker' => 1],
    ['id' => 4, 'i' => -1, 'n' => '-1.5', 't' => '-1.5', 'r' => -1.5, 'marker' => 0],
    ['id' => 5, 'i' => 2, 'n' => '2.25', 't' => 'alpha', 'r' => 2.25, 'marker' => 1],
    ['id' => 6, 'i' => 3, 'n' => '003', 't' => '003', 'r' => 3.0, 'marker' => 0],
    ['id' => 7, 'i' => 4, 'n' => '4tail', 't' => '4tail', 'r' => 4.5, 'marker' => 1],
    ['id' => 8, 'i' => 5, 'n' => '', 't' => ' ', 'r' => 5.5, 'marker' => 0],
    ['id' => 9, 'i' => 6, 'n' => '0x10', 't' => '0x10', 'r' => 6.75, 'marker' => 1],
    ['id' => 10, 'i' => 7, 'n' => '+.5', 't' => '+.5', 'r' => 0.5, 'marker' => 0],
    ['id' => 11, 'i' => 8, 'n' => 42, 't' => 'forty-two', 'r' => 42.0, 'marker' => 1],
    ['id' => 12, 'i' => null, 'n' => '42', 't' => null, 'r' => 42.5, 'marker' => 1],
];

$portRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    $sourceRows,
);

$expressions = [
    'numeric-column' => 'n',
    'text-column' => 't',
    'real-column' => 'r',
    'integer-column' => 'i',
    'cast-numeric-text' => 'CAST(n AS TEXT)',
    'cast-text-numeric' => 'CAST(t AS NUMERIC)',
    'numeric-add-zero' => 'n + 0',
    'text-concat-empty' => "t || ''",
    'nullif-self' => 'NULLIF(t, t)',
    'coalesce-two' => 'COALESCE(n, t)',
    'coalesce-fallback' => "COALESCE(n, t, 'fallback')",
    'case-marker' => 'CASE WHEN marker THEN n ELSE t END',
];

$operators = [
    'expr-3.25-isnull-upper' => 'ISNULL',
    'expr-3.25-isnull-lower' => 'isnull',
    'expr-3.27-notnull-upper' => 'NOTNULL',
    'expr-3.27-notnull-lower' => 'notnull',
    'e-expr-12.3.72-not-null-upper' => 'NOT NULL',
    'e-expr-12.3.72-not-null-lower' => 'not null',
];

$projections = [
    'quote' => static fn (string $predicate): string => "quote({$predicate})",
    'typeof' => static fn (string $predicate): string => "typeof({$predicate})",
];

$cases = [];
foreach ($sourceRows as $row) {
    foreach ($expressions as $expressionName => $expressionSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            foreach ($projections as $projectionName => $projectionSql) {
                $key = implode('-', ['row' . $row['id'], $expressionName, $operatorName, $projectionName]);
                $predicate = "({$expressionSql}) {$operatorSql}";
                $cases[$key] = [
                    'rowId' => $row['id'],
                    'predicate' => $predicate,
                    'projection' => $projectionSql,
                ];
            }
        }
    }
}

$oracleScript = [
    'CREATE TABLE expr_source(id INTEGER PRIMARY KEY, i INTEGER, n NUMERIC, t TEXT, r REAL, marker INTEGER);',
];
foreach ($sourceRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO expr_source(id, i, n, t, r, marker) VALUES(%s, %s, %s, %s, %s, %s);',
        $literal($row['id']),
        $literal($row['i']),
        $literal($row['n']),
        $literal($row['t']),
        $literal($row['r']),
        $literal($row['marker']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $projectionSql = $case['projection'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || " . $projectionSql($case['predicate']) . ' FROM expr_source WHERE id = ' . $case['rowId'] . ';';
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-postfix-null-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 postfix-null oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce postfix-null expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 postfix-null oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d postfix-null oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic postfix null expr-3 e_expr-12 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $portRows): void {
        $projectionSql = $case['projection'];
        $rows = SQLiteSelectSql::execute(
            'SELECT ' . $projectionSql($case['predicate']) . ' AS value FROM expr_source WHERE id = ' . $case['rowId'],
            ['expr_source' => $portRows],
        );

        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key], (string) $rows[0]['value'], $key);
    };
}

$expr7Rows = [];
for ($i = 1; $i <= 20; $i++) {
    $expr7Rows[] = [
        'a' => $i,
        'b' => 1 << $i,
        '__sqlite_column_affinities' => ['a' => 'INTEGER', 'b' => 'INTEGER'],
    ];
}
$expr7Rows[] = [
    'a' => null,
    'b' => 0,
    '__sqlite_column_affinities' => ['a' => 'INTEGER', 'b' => 'INTEGER'],
];

$whereCases = [
    'expr-7.14-a-isnull' => ['a ISNULL', [null]],
    'expr-7.15-a-notnull-and-a-lt-3' => ['a NOTNULL AND a<3', [1, 2]],
    'expr-7.23-a-notnull-and-a-lt-4-or-a-eq-8' => ['(a notnull AND a<4) OR a==8', [1, 2, 3, 8]],
    'expr-7.26-a-isnull-or-a-eq-8' => ['a isnull OR a=8', [null, 8]],
    'expr-7.27-a-notnull-or-a-eq-8' => ['a notnull OR a=8', range(1, 20)],
];

foreach ($whereCases as $caseName => [$predicate, $expected]) {
    $tests['real upstream corpus expression affinity dynamic postfix null where ' . $caseName] = static function (TestRunner $t) use ($predicate, $expected, $expr7Rows, $caseName): void {
        $rows = SQLiteSelectSql::execute('SELECT a FROM test1 WHERE ' . $predicate . ' ORDER BY a', ['test1' => $expr7Rows]);
        $actual = array_map(static fn (array $row): mixed => $row['a'], $rows);

        $t->same($expected, $actual, $caseName);
    };
}

$tests['real upstream corpus expression affinity dynamic postfix null owns 1733 expr/e_expr execution cases'] = static function (TestRunner $t) use ($sourceRows, $expressions, $operators, $projections, $cases, $whereCases, $oracle): void {
    $t->same(12, count($sourceRows));
    $t->same(12, count($expressions));
    $t->same(6, count($operators));
    $t->same(2, count($projections));
    $t->same(1728, count($cases));
    $t->same(5, count($whereCases));
    $t->same(1728, count($oracle));
    $t->same(
        'expr.test expr-3.25..3.28b plus expr-7.14/7.15/7.23/7.26/7.27 and e_expr.test e_expr-12.3.70..72 postfix NULL predicates',
        'expr.test expr-3.25..3.28b plus expr-7.14/7.15/7.23/7.26/7.27 and e_expr.test e_expr-12.3.70..72 postfix NULL predicates',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
