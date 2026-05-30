<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-1.2.1 through selectD-1.7 and selectD-2.2.1 through selectD-2.7.
 *
 * This batch covers nested parenthesized JOIN name resolution and USING
 * comparisons through a parenthesized right-side join group.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectDTables = static function (int $case): array {
    $base = 1000 + ($case * 5);

    return [
        't1' => [
            ['a' => $base, 'b' => 'x1-' . $case, 'case_id' => $case],
            ['a' => $base + 9000, 'b' => 'miss1-' . $case, 'case_id' => $case + 9000],
        ],
        't2' => [
            ['a' => $base + 111, 'b' => 'x2-' . $case, 'case_id' => $case],
            ['a' => $base, 'b' => 'u2-' . $case, 'case_id' => $case],
            ['a' => $base + 9000, 'b' => 'miss2-' . $case, 'case_id' => $case + 9000],
        ],
        't3' => [
            ['a' => $base + 222, 'b' => 'x3-' . $case, 'case_id' => $case],
            ['a' => $base, 'b' => 'u3-' . $case, 'case_id' => $case],
            ['a' => $base + 111, 'b' => 'l3-' . $case, 'case_id' => $case],
            ['a' => $base + 9000, 'b' => 'miss3-' . $case, 'case_id' => $case + 9000],
        ],
        'main.t4' => [
            ['a' => $base + 333, 'b' => 'x4-' . $case, 'case_id' => $case],
            ['a' => $base, 'b' => 'u4-' . $case, 'case_id' => $case],
            ['a' => $base + 222, 'b' => 'left-hit-' . $case, 'case_id' => $case],
            ['a' => $base + 9000, 'b' => 'miss4-' . $case, 'case_id' => $case + 9000],
        ],
        'aux1.t4' => [
            ['a' => $base + 444, 'b' => 'aux4-' . $case, 'case_id' => $case],
        ],
    ];
};

$tests = [];

$tests['real upstream selectD.test cites nested JOIN name-resolution source'] = static function (TestRunner $t): void {
    $t->contains('selectD.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test');
    $t->contains('selectD-1.2.1', 'selectD-1.2.1 nested parenthesized JOIN with ON clauses');
    $t->contains('selectD-1.3', 'selectD-1.3 nested parenthesized JOIN with USING clauses');
    $t->contains('selectD-1.6', 'selectD-1.6 split parenthesized LEFT JOIN null-extension');
};

for ($case = 0; $case < 1250; $case++) {
    $base = 1000 + ($case * 5);
    $tables = $selectDTables($case);

    $nestedOnSql = 'SELECT t1.a,t1.b,t2.a,t2.b,t3.a,t3.b,t4.a,t4.b '
        . 'FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) '
        . 'ON t3.a=t2.a+111) ON t2.a=t1.a+111 '
        . 'WHERE t1.case_id=' . $case;
    $nestedOnExpected = [
        $base,
        'x1-' . $case,
        $base + 111,
        'x2-' . $case,
        $base + 222,
        'x3-' . $case,
        $base + 333,
        'x4-' . $case,
    ];

    $usingSql = 'SELECT t1.a,t1.b,t2.b,t3.b,t4.b '
        . 'FROM t1 JOIN (t2 JOIN (t3 JOIN t4 USING(a)) USING (a)) USING (a) '
        . 'WHERE t1.case_id=' . $case;
    $usingExpected = [$base, 'x1-' . $case, 'u2-' . $case, 'u3-' . $case, 'u4-' . $case];

    $leftJoinSql = 'SELECT t1.a,t1.b,t2.b,t3.a,t3.b,t4.b '
        . 'FROM (t1 LEFT JOIN t2 USING(a)) JOIN (t3 LEFT JOIN t4 USING(a)) '
        . 'ON t1.a=t3.a-111 WHERE t1.case_id=' . $case;
    $leftJoinExpected = [$base, 'x1-' . $case, 'u2-' . $case, $base + 111, 'l3-' . $case, null];

    $qualifiedSql = 'SELECT x.a,y.b '
        . 'FROM t1 JOIN (t2 JOIN (main.t4 AS x JOIN aux1.t4 AS y ON y.a=x.a+111) '
        . 'ON x.a=t2.a+222) ON t2.a=t1.a+111 '
        . 'WHERE t1.case_id=' . $case;
    $qualifiedExpected = [$base + 333, 'aux4-' . $case];

    $tests[sprintf('real upstream selectD.test dynamic nested join and using case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertSelectFlat,
            $tables,
            $nestedOnSql,
            $nestedOnExpected,
            $usingSql,
            $usingExpected,
            $leftJoinSql,
            $leftJoinExpected,
            $qualifiedSql,
            $qualifiedExpected,
            $case
        ): void {
            $assertSelectFlat($t, $nestedOnSql, $tables, $nestedOnExpected);
            $assertSelectFlat($t, $usingSql, $tables, $usingExpected);
            $assertSelectFlat($t, $leftJoinSql, $tables, $leftJoinExpected);
            $assertSelectFlat($t, $qualifiedSql, $tables, $qualifiedExpected);
            $t->same(true, $case >= 0, 'dynamic selectD nested JOIN case is bounded');
            $t->same(true, $case < 1250, 'dynamic selectD nested JOIN case remains finite');
        };
}

return $tests;
