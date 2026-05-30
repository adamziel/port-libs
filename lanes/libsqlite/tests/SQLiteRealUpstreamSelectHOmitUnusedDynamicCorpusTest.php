<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,int>>>
 */
$wideSelectHTables = static function (int $seed): array {
    $row = [];
    for ($column = 0; $column <= 65; $column++) {
        $row['c' . $column] = $seed + $column;
    }

    return ['t1' => [$row]];
};

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
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $name) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $name . ' flat result');
    $t->same(count($expected), count($actual), $name . ' result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $name . ' result fingerprint',
    );
    $t->contains('SELECT', $sql, $name . ' executes SELECT SQL');
};

$tests = [];

$tests['real upstream corpus selectH.test cites omit-unused-subquery source'] = static function (TestRunner $t): void {
    $t->contains('/test/selectH.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test');
    $t->contains('omit-unused-subquery-column', 'selectH.test omit-unused-subquery-column optimization');
};

$tests['real upstream corpus selectH.test base wide row has upstream c0 through c65 shape'] = static function (TestRunner $t) use ($wideSelectHTables): void {
    $row = $wideSelectHTables(100)['t1'][0];

    $t->same(66, count($row), 'wide table column count from selectH.test');
    $t->same(100, $row['c0']);
    $t->same(165, $row['c65']);
};

for ($seed = 0; $seed < 250; $seed++) {
    $base = 1000 + ($seed * 10);
    $tables = $wideSelectHTables($base);
    $filter = $base + 60;

    $tests[sprintf('real upstream corpus selectH.test 1.2 dynamic distinct wildcard filter seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $filter, $base, $seed): void {
            $sql = <<<SQL
SELECT DISTINCT c44 FROM (
  SELECT c0 AS a, *, c60 AS filter_marker FROM t1
  UNION ALL
  SELECT c1 AS a, *, c61 AS filter_marker FROM t1
) WHERE c60={$filter}
SQL;
            $assertFlatSelect($t, $sql, $tables, [$base + 44], 'selectH-1.2 seed ' . $seed);
        };

    $tests[sprintf('real upstream corpus selectH.test 2.1 dynamic compound order omits counter seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $base, $seed): void {
            $sql = <<<SQL
SELECT a FROM (
  SELECT c15 AS a, c62 AS b FROM t1
  UNION ALL
  SELECT c16 AS a, c61 AS b FROM t1
  ORDER BY b
)
SQL;
            $assertFlatSelect($t, $sql, $tables, [$base + 16, $base + 15], 'selectH-2.1 seed ' . $seed);
        };

    $tests[sprintf('real upstream corpus selectH.test 3.4 dynamic four-arm union filter seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $filter, $base, $seed): void {
            $sql = <<<SQL
SELECT a FROM (
  SELECT c16 AS a, *, c60 AS filter_marker FROM t1
  UNION ALL
  SELECT c17 AS a, *, c60 AS filter_marker FROM t1
  UNION ALL
  SELECT c18 AS a, *, c60 AS filter_marker FROM t1
  UNION ALL
  SELECT c19 AS a, *, c60 AS filter_marker FROM t1
) WHERE c60={$filter}
SQL;
            $assertFlatSelect($t, $sql, $tables, [$base + 16, $base + 17, $base + 18, $base + 19], 'selectH-3.4 seed ' . $seed);
        };

    $tests[sprintf('real upstream corpus selectH.test 5.2 dynamic count over distinct union empty seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $base, $seed): void {
            $tables = [
                't1' => [
                    ['val1' => $base + 4],
                    ['val1' => $base + 5],
                    ['val1' => $base + 5],
                ],
                't2' => [],
            ];
            $sql = 'SELECT count(1234) AS n FROM (SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2)';

            $assertFlatSelect($t, $sql, $tables, [2], 'selectH-5.2 seed ' . $seed);
        };
}

return $tests;
