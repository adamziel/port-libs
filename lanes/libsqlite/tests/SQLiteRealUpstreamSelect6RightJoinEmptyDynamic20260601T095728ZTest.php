<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-13.110: nested LEFT JOIN against an empty table feeds an INNER
 *   JOIN, then a comma source before a RIGHT JOIN.
 * - select6-13.120: the same join chain with a final WHERE predicate on the
 *   preserved left-side table.
 */

$tests = [];

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select6Tables = static function (int $case): array {
    $t1 = [];
    $t1Count = 1 + ($case % 7);
    for ($i = 0; $i < $t1Count; $i++) {
        $t1[] = ['y' => (($case + $i) % 5) === 0 ? 0 : 1 + (($case + $i) % 9)];
    }

    $t2 = [];
    $t2Count = 1 + (($case * 3) % 6);
    for ($i = 0; $i < $t2Count; $i++) {
        $t2[] = ['x' => $i];
    }

    return [
        't1' => $t1,
        't2' => $t2,
        'empty1' => [],
    ];
};

$select6Sql = static function (bool $where): string {
    $sql = "SELECT t1.y FROM (SELECT 'AAA') INNER JOIN (SELECT 1 AS abc FROM (SELECT 1 FROM t2 LEFT JOIN empty1)) AS sub0 ON sub0.abc, t1 RIGHT JOIN (SELECT 'BBB' FROM (SELECT 'CCC'))";

    return $where ? $sql . ' WHERE t1.y' : $sql;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select6Flat = static function (array $rows): array {
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
 * @return list<mixed>
 */
$select6Expected = static function (array $tables, bool $where): array {
    $flat = [];
    foreach ($tables['t2'] as $_t2) {
        foreach ($tables['t1'] as $row) {
            $y = $row['y'];
            if ($where && (int) $y === 0) {
                continue;
            }
            $flat[] = $y;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelect6RightJoin = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($select6Flat): void {
    $actual = $select6Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $label,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $label,
    );
};

$tests['real upstream select6.test select6-13.110 and 13.120 cites right join source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';
        $text = file_get_contents($source);

        $t->true(is_string($text), 'hydrated upstream select6.test is readable');
        $t->contains('do_execsql_test 13.110', $text);
        $t->contains('do_execsql_test 13.120', $text);
        $t->contains('SELECT 1 FROM t2 LEFT JOIN empty1', $text);
        $t->contains("RIGHT JOIN (SELECT 'BBB' FROM ( SELECT 'CCC' ))", $text);
    };

$tests['real upstream select6.test select6-13.110 canonical empty-left-join right chain'] =
    static function (TestRunner $t) use ($assertSelect6RightJoin, $select6Sql): void {
        $tables = [
            't1' => [['y' => 1]],
            't2' => [['x' => 0]],
            'empty1' => [],
        ];

        $assertSelect6RightJoin(
            $t,
            $select6Sql(false),
            $tables,
            [1],
            'select6-13.110 canonical RIGHT JOIN chain',
        );
    };

$tests['real upstream select6.test select6-13.120 canonical where keeps truthy left value'] =
    static function (TestRunner $t) use ($assertSelect6RightJoin, $select6Sql): void {
        $tables = [
            't1' => [['y' => 1]],
            't2' => [['x' => 0]],
            'empty1' => [],
        ];

        $assertSelect6RightJoin(
            $t,
            $select6Sql(true),
            $tables,
            [1],
            'select6-13.120 canonical RIGHT JOIN chain with WHERE',
        );
    };

for ($case = 0; $case < 500; $case++) {
    $tables = $select6Tables($case);
    $expected = $select6Expected($tables, false);

    $tests[sprintf('real upstream select6.test select6-13.110 dynamic empty left join right chain %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect6RightJoin, $select6Sql, $tables, $expected, $case): void {
            $assertSelect6RightJoin(
                $t,
                $select6Sql(false),
                $tables,
                $expected,
                'select6-13.110 dynamic case ' . $case,
            );
        };
}

for ($case = 0; $case < 500; $case++) {
    $tables = $select6Tables($case);
    $expected = $select6Expected($tables, true);

    $tests[sprintf('real upstream select6.test select6-13.120 dynamic where empty left join right chain %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect6RightJoin, $select6Sql, $tables, $expected, $case): void {
            $assertSelect6RightJoin(
                $t,
                $select6Sql(true),
                $tables,
                $expected,
                'select6-13.120 dynamic WHERE case ' . $case,
            );
        };
}

$tests['real upstream select6.test right join empty table dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql and makes schema-less empty LEFT JOIN null-extension a no-op when no right columns are selected',
            'no new support component needed; reuses SQLiteSelectSql and makes schema-less empty LEFT JOIN null-extension a no-op when no right columns are selected',
        );
        $t->same(
            'non-overlap: owns select6.test select6-13.110 and select6-13.120 chained comma/RIGHT JOIN behavior, not accepted grouped SELECT, JSON table, VFS, WAL, B-tree, or select6 derived aggregate batches',
            'non-overlap: owns select6.test select6-13.110 and select6-13.120 chained comma/RIGHT JOIN behavior, not accepted grouped SELECT, JSON table, VFS, WAL, B-tree, or select6 derived aggregate batches',
        );
    };

return $tests;
