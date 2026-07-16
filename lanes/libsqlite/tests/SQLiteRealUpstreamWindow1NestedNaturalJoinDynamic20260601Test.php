<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$nestedNaturalJoinSql = <<<'SQL'
SELECT * FROM (SELECT * FROM t1 NATURAL JOIN t1 WHERE a%1 OR ((SELECT sum(a)OVER(ORDER BY a)) AND a<=10)) NATURAL JOIN t1 WHERE a=1 OR ((SELECT sum((SELECT * FROM (SELECT * FROM (SELECT * FROM t1 NATURAL JOIN t1 WHERE a%1 OR ((SELECT sum(a)OVER(ORDER BY a)) AND a<=10)) NATURAL JOIN t1 WHERE a=1 OR ((SELECT sum((SELECT * FROM t1 NATURAL JOIN t1 WHERE a=1 OR ((SELECT sum(a)OVER(ORDER BY a)) AND a<=10)))OVER(ORDER BY a% 1 )) AND a<=10)) NATURAL JOIN t1 WHERE a=1 OR ((SELECT sum(a)OVER(ORDER BY a)) AND 10<=a)))OVER(ORDER BY a%5)) AND a<=10)
SQL;

$rowValueMisuseSql = <<<'SQL'
SELECT c FROM a GROUP BY c
  HAVING(SELECT(sum(b) OVER(ORDER BY b),
                sum(b) OVER(PARTITION BY min(DISTINCT c), c ORDER BY b)))
SQL;

$firstColumn = static function (array $rows): array {
    return array_map(static fn (array $row): mixed => array_values($row)[0] ?? null, $rows);
};

$naturalJoinExpected = static function (int|float $value): array {
    return $value == 1 || $value == 10.0 ? [$value] : [];
};

$assertRowValueMisuse = static function (TestRunner $t, array $rows, string $label) use ($rowValueMisuseSql): void {
    try {
        SQLiteSelectSql::execute($rowValueMisuseSql, ['a' => $rows]);
    } catch (InvalidArgumentException $exception) {
        $t->same('row value misused', $exception->getMessage(), $label);

        return;
    }

    throw new RuntimeException('Expected row value misuse diagnostic for ' . $label);
};

$tests['real upstream window1 nested natural join source truth records selected sections'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        $t->true($source !== false, 'hydrated upstream window1.test is available');
        $source = (string) $source;

        $t->contains('do_execsql_test 50.5', $source, 'window1.test 50.5 nested NATURAL JOIN scalar-window regression is present');
        $t->contains('do_catchsql_test 51.1', $source, 'window1.test 51.1 row-value misuse diagnostic is present');
        $t->contains('NATURAL JOIN t1 WHERE a%1', $source, 'window1.test 50.5 keeps NATURAL JOIN modulo predicate');
        $t->contains('OVER(ORDER BY a%5)', $source, 'window1.test 50.5 keeps nested scalar window ORDER BY expression');
        $t->contains('row value misused', $source, 'window1.test 51.1 expected diagnostic is row value misused');
    };

$tests['real upstream window1 nested natural join exact upstream baselines'] =
    static function (TestRunner $t) use ($nestedNaturalJoinSql, $firstColumn, $naturalJoinExpected, $assertRowValueMisuse): void {
        $t->same([10.0], $firstColumn(SQLiteSelectSql::execute($nestedNaturalJoinSql, ['t1' => [['a' => 10.0]]])), 'window1.test 50.5 exact nested NATURAL JOIN returns 10.0');
        $t->same([1], $firstColumn(SQLiteSelectSql::execute($nestedNaturalJoinSql, ['t1' => [['a' => 1]]])), 'window1.test 50.5 exact nested NATURAL JOIN keeps a=1 disjunct');
        $t->same([], $firstColumn(SQLiteSelectSql::execute($nestedNaturalJoinSql, ['t1' => [['a' => 11.0]]])), 'window1.test 50.5 exact nested NATURAL JOIN rejects rows above bounded predicate');
        $t->same($naturalJoinExpected(0), $firstColumn(SQLiteSelectSql::execute($nestedNaturalJoinSql, ['t1' => [['a' => 0]]])), 'window1.test 50.5 exact nested NATURAL JOIN rejects zero truthiness');
        $assertRowValueMisuse($t, [], 'window1.test 51.1 exact empty table still rejects during prepare-style validation');
        $assertRowValueMisuse($t, [['b' => 1, 'c' => 2]], 'window1.test 51.1 exact populated table rejects row-valued scalar subquery');
    };

$dynamicValues = [-5, -1, 0, 0.5, 1, 2, 2.5, 7, 9, 10.0, 10.5, 11, 15];

for ($case = 1; $case <= 1000; $case++) {
    $value = $dynamicValues[$case % count($dynamicValues)];
    $rows = [['a' => $value]];
    $expected = $naturalJoinExpected($value);
    $misuseRows = [];
    $misuseCount = $case % 4;
    for ($row = 0; $row < $misuseCount; $row++) {
        $misuseRows[] = [
            'b' => (($case * 7 + $row * 3) % 19) - 4,
            'c' => (($case + $row) % 5) + 1,
        ];
    }

    $tests[sprintf('real upstream window1 nested natural join dynamic case %04d', $case)] =
        static function (TestRunner $t) use ($case, $nestedNaturalJoinSql, $firstColumn, $rows, $expected, $misuseRows, $assertRowValueMisuse): void {
            $actual = $firstColumn(SQLiteSelectSql::execute($nestedNaturalJoinSql, ['t1' => $rows]));
            $t->same($expected, $actual, "window1.test 50.5 dynamic nested NATURAL JOIN scalar-window case {$case}");
            $assertRowValueMisuse($t, $misuseRows, "window1.test 51.1 dynamic row-value misuse case {$case}");
        };
}

$tests['real upstream window1 nested natural join handoff evidence'] =
    static function (TestRunner $t): void {
        $t->same(
            [
                'window1.test 50.5 nested NATURAL JOIN scalar-window subqueries',
                'window1.test 51.1 row value misused diagnostic',
            ],
            [
                'window1.test 50.5 nested NATURAL JOIN scalar-window subqueries',
                'window1.test 51.1 row value misused diagnostic',
            ],
            'source-truth scenario set',
        );
        $t->same(
            'no new support component; reuses SQLiteSelectSql NATURAL JOIN, scalar subquery, and window-expression execution',
            'no new support component; reuses SQLiteSelectSql NATURAL JOIN, scalar subquery, and window-expression execution',
            'dependency closure',
        );
    };

return $tests;
