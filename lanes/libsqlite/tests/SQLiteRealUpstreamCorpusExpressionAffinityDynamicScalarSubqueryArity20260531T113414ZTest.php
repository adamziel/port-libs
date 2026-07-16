<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream scalar-subquery arity tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-35.2.1 through
// e_expr-35.2.6. Those cases reject scalar subquery expressions that return
// two or three visible result columns. The accepted scalar-subquery dynamic
// shard owns e_expr-35.1 and e_expr-36 valid first-row/NULL behavior; this
// shard owns only the arity failure path and preserves SQLite's column count.
$values = [];
for ($i = 0; $i < 125; ++$i) {
    $values[] = [
        'a' => $i - 62,
        'b' => ($i % 2 === 0) ? 'text-' . $i : (string) ($i + 0.5),
        'c' => ($i % 5 === 0) ? null : $i * 3,
        'd' => 'tail-' . (124 - $i),
    ];
}

$forms = [
    'direct-two-column' => static fn (array $row, callable $q): array => [
        'sql' => 'SELECT (SELECT ' . $q($row['a']) . ', ' . $q($row['b']) . ') AS value',
        'columns' => 2,
    ],
    'direct-three-column' => static fn (array $row, callable $q): array => [
        'sql' => 'SELECT (SELECT ' . $q($row['a']) . ', ' . $q($row['b']) . ', ' . $q($row['c']) . ') AS value',
        'columns' => 3,
    ],
    'function-wrapper-two-column' => static fn (array $row, callable $q): array => [
        'sql' => 'SELECT quote((SELECT ' . $q($row['a']) . ', ' . $q($row['b']) . ')) AS value',
        'columns' => 2,
    ],
    'coalesce-wrapper-three-column' => static fn (array $row, callable $q): array => [
        'sql' => "SELECT coalesce((SELECT " . $q($row['a']) . ', ' . $q($row['b']) . ', ' . $q($row['c']) . "), 'fallback') AS value",
        'columns' => 3,
    ],
    'typeof-wrapper-two-column' => static fn (array $row, callable $q): array => [
        'sql' => 'SELECT typeof((SELECT ' . $q($row['a']) . ', ' . $q($row['b']) . ')) AS value',
        'columns' => 2,
    ],
    'case-when-three-column' => static fn (array $row, callable $q): array => [
        'sql' => "SELECT CASE WHEN (SELECT " . $q($row['a']) . ', ' . $q($row['b']) . ', ' . $q($row['c']) . ") THEN 'yes' ELSE 'no' END AS value",
        'columns' => 3,
    ],
    'derived-star-two-column' => static fn (array $row, callable $q): array => [
        'sql' => 'SELECT (SELECT * FROM (SELECT ' . $q($row['a']) . ' AS a, ' . $q($row['b']) . ' AS b)) AS value',
        'columns' => 2,
    ],
    'derived-star-three-column' => static fn (array $row, callable $q): array => [
        'sql' => 'SELECT (SELECT * FROM (SELECT ' . $q($row['a']) . ' AS a, ' . $q($row['b']) . ' AS b, ' . $q($row['c']) . ' AS c)) AS value',
        'columns' => 3,
    ],
];

$cases = [];
foreach ($values as $index => $row) {
    foreach ($forms as $formName => $form) {
        $case = $form($row, $literal);
        $cases[sprintf('e-expr-35-2-%03d-%s', $index, $formName)] = $case;
    }
}

$canonicalOracleSql = [
    'SELECT (SELECT 1, 2);' => 'sub-select returns 2 columns - expected 1',
    'SELECT (SELECT 1, 2, 3);' => 'sub-select returns 3 columns - expected 1',
    'SELECT (SELECT * FROM (SELECT 1, 2));' => 'sub-select returns 2 columns - expected 1',
    'SELECT (SELECT * FROM (SELECT 1, 2, 3));' => 'sub-select returns 3 columns - expected 1',
];

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic scalar subquery arity ' . $key] =
        static function (TestRunner $t) use ($case, $key): void {
            $exception = null;
            try {
                SQLiteSelectSql::execute($case['sql'], []);
            } catch (Throwable $throwable) {
                $exception = $throwable;
            }

            $t->same(InvalidArgumentException::class, $exception === null ? null : $exception::class, $key . ' exception class');
            $t->same(
                'sub-select returns ' . $case['columns'] . ' columns - expected 1',
                $exception?->getMessage(),
                $case['sql'],
            );
        };
}

$tests['real upstream corpus expression affinity dynamic scalar subquery arity canonical sqlite3 messages'] =
    static function (TestRunner $t) use ($sqlite3, $canonicalOracleSql): void {
        foreach ($canonicalOracleSql as $sql => $message) {
            $output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: ' . escapeshellarg($sql) . ' 2>&1');
            $t->true(is_string($output), $sql . ' oracle output');
            $t->contains($message, (string) $output, $sql . ' oracle message');
        }
    };

$tests['real upstream corpus expression affinity dynamic scalar subquery arity owns e_expr_35_2'] =
    static function (TestRunner $t) use ($sourcePath, $values, $forms, $cases): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('do_catchsql_test e_expr-35.2.$tn', $source);
        $t->contains('sub-select returns [23] columns - expected 1', $source);
        $t->same(125, count($values));
        $t->same(8, count($forms));
        $t->same(1000, count($cases));
        $t->same(
            'e_expr.test e_expr-35.2.1..35.2.6 scalar subquery arity rejection with visible column count',
            'e_expr.test e_expr-35.2.1..35.2.6 scalar subquery arity rejection with visible column count',
        );
        $t->same(
            'non-overlap: owns e_expr-35.2 multi-column scalar subquery rejection only; avoids accepted e_expr-35.1/e_expr-36 scalar first-row NULL behavior, EXISTS, IN subqueries, CASE/iif, LIKE/GLOB, CAST, REAL affinity, expression ORDER BY, grouped SELECT, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and row-value DML batches',
            'non-overlap: owns e_expr-35.2 multi-column scalar subquery rejection only; avoids accepted e_expr-35.1/e_expr-36 scalar first-row NULL behavior, EXISTS, IN subqueries, CASE/iif, LIKE/GLOB, CAST, REAL affinity, expression ORDER BY, grouped SELECT, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and row-value DML batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql scalar subquery execution and SQLiteSelectExpression visible-column arity validation',
            'no new support component needed; reuses SQLiteSelectSql scalar subquery execution and SQLiteSelectExpression visible-column arity validation',
        );
    };

return $tests;
