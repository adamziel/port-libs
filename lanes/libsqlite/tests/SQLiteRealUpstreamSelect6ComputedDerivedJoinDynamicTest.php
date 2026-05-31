<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-5.1 and select6-5.2: comma joins over FROM-subqueries where a
 *   computed projection alias from the left subquery is matched against a
 *   projected column alias from the right subquery.
 *
 * @return array<string,list<array<string,int>>>
 */
$select6ComputedJoinTables = static function (int $seed): array {
    $leftRows = [];
    $rightRows = [];

    for ($i = 0; $i < 9; $i++) {
        $leftRows[] = [
            'x' => ($seed * 17) + $i,
            'y' => 3,
        ];
    }

    for ($i = 0; $i < 11; $i++) {
        $rightRows[] = [
            'x' => ($seed * 17) + 2 + $i,
            'y' => 4,
        ];
    }

    return ['app_values' => array_merge($leftRows, $rightRows)];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select6ComputedJoinFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,int>>> $tables
 * @return list<int>
 */
$select6ComputedJoinExpected = static function (array $tables, int $delta): array {
    $left = [];
    $right = [];

    foreach ($tables['app_values'] as $row) {
        if ($row['y'] === 3) {
            $left[] = ['a' => $row['x'] + $delta, 'x' => $row['x']];
        }
        if ($row['y'] === 4) {
            $right[] = ['b' => $row['x']];
        }
    }

    $joined = [];
    foreach ($left as $leftRow) {
        foreach ($right as $rightRow) {
            if ($leftRow['a'] === $rightRow['b']) {
                $joined[] = [$leftRow['a'], $leftRow['x'], $rightRow['b']];
            }
        }
    }

    usort($joined, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

    $flat = [];
    foreach ($joined as $row) {
        array_push($flat, $row[0], $row[1], $row[2]);
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,int>>> $tables
 * @param list<int> $expected
 */
$select6ComputedJoinAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($select6ComputedJoinFlat): void {
    $actual = $select6ComputedJoinFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat SELECT result');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first and last value guard',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
};

$tests = [];

$tests['real upstream select6.test computed derived join cites source section'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';
    $t->true(is_file($source), 'hydrated select6.test exists');
    $contents = file_get_contents($source);

    $t->contains('do_test select6-5.1', $contents);
    $t->contains('do_test select6-5.2', $contents);
    $t->contains("x+3 AS 'a'", $contents);
    $t->contains("x AS 'b'", $contents);
};

for ($seed = 1; $seed <= 500; $seed++) {
    $tables = $select6ComputedJoinTables($seed);
    $delta = 1 + ($seed % 6);
    $expected = $select6ComputedJoinExpected($tables, $delta);

    $tests[sprintf('real upstream select6.test select6-5.1 computed derived join with aliases seed %03d', $seed)] =
        static function (TestRunner $t) use ($select6ComputedJoinAssert, $tables, $delta, $expected): void {
            $sql = <<<SQL
SELECT a,x,b FROM
  (SELECT x+{$delta} AS a, x FROM app_values WHERE y=3) AS p,
  (SELECT x AS b FROM app_values WHERE y=4) AS q
WHERE a=b
ORDER BY a
SQL;

            $select6ComputedJoinAssert($t, $sql, $tables, $expected, 'select6-5.1');
        };

    $tests[sprintf('real upstream select6.test select6-5.2 computed derived join without aliases seed %03d', $seed)] =
        static function (TestRunner $t) use ($select6ComputedJoinAssert, $tables, $delta, $expected): void {
            $sql = <<<SQL
SELECT a,x,b FROM
  (SELECT x+{$delta} AS a, x FROM app_values WHERE y=3),
  (SELECT x AS b FROM app_values WHERE y=4)
WHERE a=b
ORDER BY a
SQL;

            $select6ComputedJoinAssert($t, $sql, $tables, $expected, 'select6-5.2');
        };
}

return $tests;
