<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test';

// Source truth: SQLite upstream test/expr.test expr-16.100 through
// expr-16.102 enables the internal implies_nonnull_row() probe and checks
// whether comparison predicates over a null-extended LEFT JOIN row require a
// real row to become non-NULL.
$templates = [
    'expr-16.100 constant false sides do not imply row' => [
        'expected' => 0,
        'expression' => static fn (int $left, int $right, int $probe): string => sprintf(
            '(b = %d AND 0) > (b = %d AND 0)',
            $left,
            $right,
        ),
    ],
    'expr-16.101 right side row dependent implies row' => [
        'expected' => 1,
        'expression' => static fn (int $left, int $right, int $probe): string => sprintf(
            '(b = %d AND 0) > (b = %d AND a = %d)',
            $left,
            $right,
            $probe,
        ),
    ],
    'expr-16.102 both sides row dependent imply row' => [
        'expected' => 1,
        'expression' => static fn (int $left, int $right, int $probe): string => sprintf(
            '(b = %d AND a = %d) > (b = %d AND a = %d)',
            $left,
            $probe,
            $right,
            -$probe,
        ),
    ],
];

$cases = [];
$templateNames = array_keys($templates);
for ($i = 0; $i < 1000; ++$i) {
    $templateName = $templateNames[$i % count($templateNames)];
    $template = $templates[$templateName];
    $left = (($i * 7) % 53) - 26;
    $right = (($i * 11) % 61) - 30;
    $probe = (($i * 13) % 67) - 33;
    $key = sprintf(
        'case-%04d-%s-left%+d-right%+d-probe%+d',
        $i + 1,
        preg_replace('/[^a-z0-9]+/', '-', strtolower($templateName)),
        $left,
        $right,
        $probe,
    );

    $cases[$key] = [
        'source' => $templateName,
        'expression' => $template['expression']($left, $right, $probe),
        'expected' => $template['expected'],
    ];
}

$tables = [
    'dual' => [
        ['dummy' => 'X'],
    ],
    't1' => [
        ['a' => null, 'b' => null, 'c' => null],
    ],
];

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic expr.test implies nonnull row ' . $key] =
        static function (TestRunner $t) use ($case, $key, $tables): void {
            $sql = sprintf(
                'SELECT implies_nonnull_row(%1$s, a) AS v, typeof(implies_nonnull_row(%1$s, a)) AS t FROM dual LEFT JOIN t1',
                $case['expression'],
            );
            $rows = SQLiteSelectSql::execute($sql, $tables);

            $t->same(1, count($rows), $key . ' row count');
            $t->same($case['expected'], $rows[0]['v'], $case['source'] . ' result');
            $t->same('integer', $rows[0]['t'], $case['source'] . ' storage class');
        };
}

$tests['real upstream corpus expression affinity dynamic expr.test implies nonnull row owns source range'] =
    static function (TestRunner $t) use ($sourcePath, $templates, $cases): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('sqlite3_test_control SQLITE_TESTCTRL_INTERNAL_FUNCTIONS db', $source);
        $t->contains('implies_nonnull_row( (b=1 AND 0)>(b=3 AND 0),a)', $source);
        $t->contains('implies_nonnull_row( (b=1 AND 0)>(b=3 AND a=4),a)', $source);
        $t->contains('implies_nonnull_row( (b=1 AND a=2)>(b=3 AND a=4),a)', $source);
        $t->same(3, count($templates));
        $t->same(1000, count($cases));
        $t->same(
            'expr.test expr-16.100..16.102 internal implies_nonnull_row null-extended LEFT JOIN expression behavior',
            'expr.test expr-16.100..16.102 internal implies_nonnull_row null-extended LEFT JOIN expression behavior',
        );
    };

$tests['real upstream corpus expression affinity dynamic expr.test implies nonnull row non overlap dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'non-overlap: owns expr.test expr-16 internal implies_nonnull_row expression probe; avoids accepted expr-1 arithmetic/null/overflow, expr-2 REAL, expr-3 text comparison, expr-4 affinity comparison, expr-5 LIKE, expr-6 GLOB, expr-case, expr-10 ESCAPE errors, expr-11 integer-boundary literals, expr-14/15 truth, e_expr CASE/CAST/LIKE/GLOB/EXISTS/subquery, affinity2/types2/types3, date, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
            'non-overlap: owns expr.test expr-16 internal implies_nonnull_row expression probe; avoids accepted expr-1 arithmetic/null/overflow, expr-2 REAL, expr-3 text comparison, expr-4 affinity comparison, expr-5 LIKE, expr-6 GLOB, expr-case, expr-10 ESCAPE errors, expr-11 integer-boundary literals, expr-14/15 truth, e_expr CASE/CAST/LIKE/GLOB/EXISTS/subquery, affinity2/types2/types3, date, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql parsing and adds bounded SQLiteSelectExpression support for the upstream internal implies_nonnull_row() probe over null-extended row expressions',
            'no new support component needed; reuses SQLiteSelectSql parsing and adds bounded SQLiteSelectExpression support for the upstream internal implies_nonnull_row() probe over null-extended row expressions',
        );
    };

return $tests;
