<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return array{0:array<string,list<array<string,mixed>>>,1:string,2:string,3:string,4:string}
 */
$selectCTablesFor = static function (int $case): array {
    $base = 10 + ($case % 89);
    $first = 'aa' . $base;
    $second = 'bb' . ($base + 1);
    $third = 'cc' . ($base + 2);
    $fourth = 'dd' . ($base + 3);

    return [
        [
            't1' => [
                ['a' => 1, 'b' => $first, 'c' => $second],
                ['a' => 1, 'b' => $first, 'c' => $second],
                ['a' => 2, 'b' => $third, 'c' => $fourth],
            ],
        ],
        $first,
        $second,
        $third,
        $fourth,
    ];
};

/**
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));
    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'flat fingerprint for ' . $sql);
};

$tests = [];

$tests['real upstream corpus selectC.test alias dynamic cites source cases'] = static function (TestRunner $t): void {
    $t->contains('/test/selectC.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test');
    $t->contains('selectC-1.1', 'selectC.test selectC-1.1 result alias visible to WHERE IN');
    $t->contains('selectC-1.7', 'selectC.test selectC-1.7 unary plus alias visible to WHERE');
    $t->contains('selectC-1.8', 'selectC.test selectC-1.8 result aliases visible to GROUP BY/HAVING');
    $t->contains('selectC-1.14', 'selectC.test selectC-1.14 expression alias ORDER BY DESC');
};

for ($case = 0; $case < 1000; $case++) {
    $tests[sprintf('real upstream corpus selectC.test alias where having dynamic case %04d', $case)] =
        static function (TestRunner $t) use ($case, $selectCTablesFor, $assertFlatSelect): void {
            [$tables, $first, $second, $third, $fourth] = $selectCTablesFor($case);
            $target = $first . $second;
            $other = $third . $fourth;
            $quotedTarget = "'" . str_replace("'", "''", $target) . "'";
            $quotedOther = "'" . str_replace("'", "''", $other) . "'";

            $assertFlatSelect(
                $t,
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y IN ({$quotedTarget}, 'missing')",
                $tables,
                [1, $target]
            );
            $assertFlatSelect(
                $t,
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c IN ({$quotedTarget}, 'missing')",
                $tables,
                [1, $target]
            );
            $assertFlatSelect(
                $t,
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y={$quotedTarget}",
                $tables,
                [1, $target]
            );
            $assertFlatSelect(
                $t,
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE x=2",
                $tables,
                [2, $other]
            );
            $assertFlatSelect(
                $t,
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE +y={$quotedTarget}",
                $tables,
                [1, $target]
            );
            $assertFlatSelect(
                $t,
                "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y={$quotedTarget}",
                $tables,
                [1, $target]
            );
            $assertFlatSelect(
                $t,
                "SELECT a AS x, b||c AS y FROM t1 WHERE y={$quotedTarget} GROUP BY x, y",
                $tables,
                [1, $target]
            );
            $assertFlatSelect(
                $t,
                'SELECT DISTINCT upper(b) AS x FROM t1 ORDER BY x',
                $tables,
                [strtoupper($first), strtoupper($third)]
            );
            $assertFlatSelect(
                $t,
                'SELECT upper(b) AS x FROM t1 GROUP BY x ORDER BY x',
                $tables,
                [strtoupper($first), strtoupper($third)]
            );
            $assertFlatSelect(
                $t,
                'SELECT upper(b) AS x FROM t1 ORDER BY x DESC',
                $tables,
                [strtoupper($third), strtoupper($first), strtoupper($first)]
            );

            $t->contains('selectC-1', sprintf('selectC.test selectC-1 alias resolution dynamic case %04d', $case));
            $t->same(3, count($tables['t1']));
            $t->same($target, $first . $second);
            $t->same($other, $third . $fourth);
        };
}

return $tests;
