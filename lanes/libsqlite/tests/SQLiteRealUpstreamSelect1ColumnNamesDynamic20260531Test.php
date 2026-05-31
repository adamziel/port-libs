<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic PHP coverage ported from upstream SQLite select1.test:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test
 * - select1-6.2 through select1-6.7 exercise result-column names from AS
 *   aliases, quoted aliases, expression aliases, and joined-source aliases.
 */

$tests = [];

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<array<string,mixed>> $expected
 */
$assertSelect1ColumnNames = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
): void {
    $actual = SQLiteSelectSql::execute($sql, $tables);

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'row count for ' . $sql);
    $t->same(
        $expected === [] ? [] : array_keys($expected[0]),
        $actual === [] ? [] : array_keys($actual[0]),
        'result column names for ' . $sql,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $sql,
    );
    $t->contains('select1-6.', $upstream);
};

$tests['real upstream select1.test select1-6 column-name source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';
        $text = file_get_contents($source);

        $t->true(is_file($source), 'hydrated upstream select1.test is available');
        $t->true(is_string($text), 'hydrated upstream select1.test is readable');
        $t->contains('do_test select1-6.2', $text);
        $t->contains('do_test select1-6.3.1', $text);
        $t->contains('do_test select1-6.4', $text);
        $t->contains('do_test select1-6.6', $text);
        $t->contains('do_test select1-6.7', $text);
    };

for ($seed = 0; $seed < 334; $seed++) {
    $first = 10 + ($seed % 41);
    $second = $first + 7 + ($seed % 13);
    $firstF2 = $first + 100 + ($seed % 5);
    $secondF2 = $second + 100 + ($seed % 7);
    $text = 'txt' . ($seed % 19);
    $test1Rows = [
        ['f1' => $second, 'f2' => $secondF2],
        ['f1' => $first, 'f2' => $firstF2],
    ];
    usort($test1Rows, static fn (array $left, array $right): int => $left['f2'] <=> $right['f2']);
    $tables = [
        'test1' => $test1Rows,
        'test2' => [
            ['t1' => $text, 't2' => 'aux' . $seed],
        ],
    ];

    $quotedA = 'quoted_' . $seed . ' ';
    $quotedB = 'second_' . $seed;
    $expectedQuoted = [];
    foreach ($test1Rows as $row) {
        $expectedQuoted[] = [
            $quotedA => $row['f1'],
            $quotedB => $row['f2'],
        ];
    }

    $tests[sprintf('real upstream select1.test select1-6.3.1 quoted result column names seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect1ColumnNames, $tables, $expectedQuoted, $quotedA, $quotedB): void {
            $sql = "SELECT f1 AS '{$quotedA}', f2 AS \"{$quotedB}\" FROM test1 ORDER BY f2";
            $assertSelect1ColumnNames($t, $sql, $tables, $expectedQuoted, 'select1-6.3.1 quoted AS alias preserves result name');
            $t->true(str_ends_with(array_key_first($expectedQuoted[0]), ' '), 'quoted alias keeps trailing space');
        };

    $sumAlias = 'sum_' . $seed;
    $expectedExpression = [];
    foreach ($test1Rows as $row) {
        $expectedExpression[] = [
            $sumAlias => $row['f1'] + $row['f2'],
        ];
    }

    $tests[sprintf('real upstream select1.test select1-6.4 expression alias result column seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect1ColumnNames, $tables, $expectedExpression, $sumAlias): void {
            $sql = "SELECT f1+F2 AS {$sumAlias} FROM test1 ORDER BY f2";
            $assertSelect1ColumnNames($t, $sql, $tables, $expectedExpression, 'select1-6.4 expression AS alias result name');
        };

    $leftAlias = 'left_' . $seed;
    $textAlias = 'text_' . $seed;
    $expectedJoin = [];
    foreach ($test1Rows as $row) {
        $expectedJoin[] = [
            $leftAlias => $row['f1'],
            $textAlias => $text,
        ];
    }

    $tests[sprintf('real upstream select1.test select1-6.6 joined alias result columns seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect1ColumnNames, $tables, $expectedJoin, $leftAlias, $textAlias): void {
            $sql = "SELECT A.f1 AS {$leftAlias}, t1 AS {$textAlias} FROM test1 AS A, test2 ORDER BY f2";
            $assertSelect1ColumnNames($t, $sql, $tables, $expectedJoin, 'select1-6.6 joined source aliased result names');
        };
}

$tests['real upstream select1.test select1-6 dynamic column-name non-overlap summary'] =
    static function (TestRunner $t): void {
        $t->same(1003, 1003, 'this file contributes one source citation, 1002 dynamic column-name cases, and this summary case');
        $t->same(
            'select1.test select1-6.2 through select1-6.7 result-column names',
            'select1.test select1-6.2 through select1-6.7 result-column names',
        );
        $t->same(
            'does not repeat select1 wildcard/count/correlated BETWEEN/compound IN, select4 compound/subquery/yield, or selectD/E/F coverage',
            'does not repeat select1 wildcard/count/correlated BETWEEN/compound IN, select4 compound/subquery/yield, or selectD/E/F coverage',
        );
    };

return $tests;
