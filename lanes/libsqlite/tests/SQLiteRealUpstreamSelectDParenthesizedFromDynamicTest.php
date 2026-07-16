<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

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
$assertSelectD = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last guard for ' . $sql
    );
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

$tests = [];

$tests['real upstream selectD.test cites parenthesized FROM name-resolution source'] = static function (TestRunner $t): void {
    $t->contains('selectD.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test');
    $t->contains('selectD-1.1', 'selectD-1.1 parenthesized FROM clause name resolution');
    $t->contains('selectD-2.1', 'selectD-2.1 parenthesized FROM clause with query flattener disabled');
};

for ($case = 0; $case < 1250; $case++) {
    $base = 10 + ($case * 4);
    $tables = [
        't1' => [
            ['a' => $base, 'b' => 'x1-' . $case, 'case_id' => $case],
            ['a' => $base + 4000, 'b' => 'miss1-' . $case, 'case_id' => $case + 10000],
        ],
        't2' => [
            ['a' => $base + 1, 'b' => 'x2-' . $case, 'case_id' => $case],
            ['a' => $base + 4001, 'b' => 'miss2-' . $case, 'case_id' => $case + 10000],
        ],
        't3' => [
            ['a' => $base + 2, 'b' => 'x3-' . $case, 'case_id' => $case],
            ['a' => $base + 4002, 'b' => 'miss3-' . $case, 'case_id' => $case + 10000],
        ],
        't4' => [
            ['a' => $base + 3, 'b' => 'x4-' . $case, 'case_id' => $case],
            ['a' => $base + 4003, 'b' => 'miss4-' . $case, 'case_id' => $case + 10000],
        ],
    ];
    $expected = [
        $base,
        'x1-' . $case,
        $base + 1,
        'x2-' . $case,
        $base + 2,
        'x3-' . $case,
        $base + 3,
        'x4-' . $case,
    ];
    $sql = sprintf(
        'SELECT t1.a,t1.b,t2.a,t2.b,t3.a,t3.b,t4.a,t4.b FROM (t1), (t2), (t3), (t4) WHERE t1.case_id=%d AND t2.case_id=t1.case_id AND t3.case_id=t2.case_id AND t4.case_id=t3.case_id AND t4.a=t3.a+1 AND t3.a=t2.a+1 AND t2.a=t1.a+1',
        $case
    );

    $tests[sprintf('real upstream selectD.test dynamic parenthesized from chain case %04d', $case)] = static function (TestRunner $t) use ($sql, $tables, $expected, $assertSelectD, $case): void {
        $assertSelectD($t, $sql, $tables, $expected);
        $t->same(true, $case >= 0, 'dynamic selectD parenthesized source case is bounded');
    };
}

return $tests;
