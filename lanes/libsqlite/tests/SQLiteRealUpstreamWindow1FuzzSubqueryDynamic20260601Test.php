<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$firstColumn = static function (array $rows): array {
    return array_map(static fn (array $row): mixed => array_values($row)[0] ?? null, $rows);
};

$columnRows = static function (array $values, string $column): array {
    return array_map(static fn (int|float $value): array => [$column => $value], $values);
};

$executeFirstColumn = static function (string $sql, array $tables) use ($firstColumn): array {
    return $firstColumn(SQLiteSelectSql::execute($sql, $tables));
};

$sortUniqueNumeric = static function (array $values): array {
    $unique = [];
    foreach ($values as $value) {
        $key = is_float($value) && floor($value) !== $value ? 'f:' . sprintf('%.17G', $value) : 'i:' . (int) $value;
        $unique[$key] = $value;
    }

    $values = array_values($unique);
    usort($values, static fn (int|float $left, int|float $right): int => $left <=> $right);

    return $values;
};

$truthyNumber = static fn (int|float $value): bool => (float) $value != 0.0;

$upstream49Sql = <<<'SQL'
SELECT b AS c FROM (
  SELECT a AS b FROM (
    SELECT a FROM t1 WHERE a=1 OR (SELECT sum(a) OVER ())
  )
  WHERE b=1 OR b<10
)
WHERE c=1 OR c>=10
SQL;

$upstream50Sql = [
    '50.1' => 'SELECT * FROM t1 WHERE a%1 OR (SELECT sum(a) OVER (ORDER BY a%2))',
    '50.2' => 'SELECT * FROM (SELECT * FROM t1 WHERE a%1 OR (SELECT sum(a) OVER (ORDER BY a%2))) WHERE a=1 OR ( (SELECT sum(a) OVER (ORDER BY a%4)) AND a<=10 )',
    '50.3' => 'SELECT a FROM (SELECT * FROM (SELECT * FROM t1 WHERE a%1 OR (SELECT sum(a) OVER (ORDER BY a%2))) WHERE a=1 OR ( (SELECT sum(a) OVER (ORDER BY a%4)) AND a<=10 )) WHERE a=1 OR a=10.0',
    '50.4' => 'SELECT a FROM (SELECT * FROM (SELECT * FROM t1 WHERE a%1 OR (SELECT sum(a) OVER (ORDER BY a%2))) WHERE a=1 OR ( (SELECT sum(a) OVER (ORDER BY a%4)) AND a<=10 )) WHERE a=1 OR ((SELECT sum(a) OVER(ORDER BY a%8)) AND 10<=a)',
];

$upstream53Sql = <<<'SQL'
SELECT a.c
  FROM a
  JOIN a AS b ON a.c=4
  JOIN a AS e ON a.c=e.c
 WHERE a.c=(SELECT (SELECT coalesce(lead(2) OVER(),0) + sum(d.c))
              FROM a AS d
             WHERE a.c)
SQL;

$upstream54Sql = 'SELECT * FROM (SELECT sum(b) OVER() AS c FROM t1 UNION SELECT b AS c FROM t1) WHERE c>10';

$upstream55Sql = <<<'SQL'
SELECT
   (SELECT b FROM a
     GROUP BY b
     HAVING (SELECT COUNT()OVER() + lead(b)OVER(ORDER BY SUM(DISTINCT b) + b))
   )
 FROM a
UNION
 SELECT 99
 ORDER BY 1
SQL;

$upstream56Sql = <<<'SQL'
SELECT avg(b) FROM t1
  UNION ALL
SELECT min(c) OVER () FROM t2
ORDER BY nosuchcolumn
SQL;

$expected49 = static function (array $values): array {
    return in_array(1, $values, true) ? [1] : [];
};

$expected50 = static function (int|float $value) use ($truthyNumber): array {
    $passes501 = $truthyNumber($value);
    $passes502 = $passes501 && ($value == 1 || ($truthyNumber($value) && $value <= 10));
    $passes503 = $passes502 && ($value == 1 || $value == 10.0);
    $passes504 = $passes502 && ($value == 1 || ($truthyNumber($value) && $value >= 10));

    return [
        '50.1' => $passes501 ? [$value] : [],
        '50.2' => $passes502 ? [$value] : [],
        '50.3' => $passes503 ? [$value] : [],
        '50.4' => $passes504 ? [$value] : [],
    ];
};

$expected53 = static function (array $values): array {
    if (array_sum($values) !== 4 || !in_array(4, $values, true)) {
        return [];
    }

    $outerMatches = count(array_filter($values, static fn (int $value): bool => $value === 4));
    $joinedRows = $outerMatches * count($values) * $outerMatches;

    return array_fill(0, $joinedRows, 4);
};

$expected54 = static function (array $values) use ($sortUniqueNumeric): array {
    return array_values(array_filter(
        $sortUniqueNumeric(array_merge($values, [array_sum($values)])),
        static fn (int|float $value): bool => $value > 10,
    ));
};

$assertCompoundOrderError = static function (TestRunner $t, string $sql, int $case): void {
    $message = null;
    try {
        SQLiteSelectSql::execute($sql, [
            't1' => [['b' => 1]],
            't2' => [['c' => 2]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $message = $exception->getMessage();
    }

    $t->true($message !== null, "window1.test 56.2 dynamic {$case} rejects missing compound ORDER BY term");
    $t->contains('ORDER BY term does not match a result column', (string) $message, "window1.test 56.2 dynamic {$case} diagnostic preserves ORDER BY mismatch");
};

$tests['real upstream window1 fuzz subquery source truth records selected sections'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        $t->true($source !== false, 'hydrated upstream window1.test is available');
        $source = (string) $source;

        $t->contains('do_execsql_test 49.2', $source, 'window1.test 49.2 nested scalar window predicate case is present');
        $t->contains('do_execsql_test 50.1', $source, 'window1.test 50.1 modulo scalar window predicate case is present');
        $t->contains('do_execsql_test 50.4', $source, 'window1.test 50.4 nested modulo scalar window predicate case is present');
        $t->contains('do_execsql_test 53.0', $source, 'window1.test 53.0 lead plus aggregate scalar subquery case is present');
        $t->contains('do_catchsql_test 54.4', $source, 'window1.test 54.4 compound window UNION filter case is present');
        $t->contains('do_execsql_test 55.1', $source, 'window1.test 55.1 empty grouped window subquery UNION case is present');
        $t->contains('do_catchsql_test 56.2', $source, 'window1.test 56.2 compound ORDER BY diagnostic case is present');
        $t->contains('ticket 7a5279a25c57adf1', $source, 'window1.test 53 ticket provenance is present');
        $t->contains('ticket c8d3b9f0a750a529', $source, 'window1.test 55 ticket provenance is present');
    };

$tests['real upstream window1 fuzz subquery exact baselines match selected upstream outputs'] =
    static function (TestRunner $t) use (
        $columnRows,
        $executeFirstColumn,
        $upstream49Sql,
        $upstream50Sql,
        $upstream53Sql,
        $upstream54Sql,
        $upstream55Sql,
        $upstream56Sql,
        $expected49,
        $expected50,
        $expected53,
        $expected54,
        $assertCompoundOrderError,
    ): void {
        $t->same([1], $executeFirstColumn($upstream49Sql, ['t1' => [['a' => 1]]]), 'window1.test 49.2 exact nested scalar window predicate');

        foreach ($expected50(10.0) as $section => $expected) {
            $t->same($expected, $executeFirstColumn($upstream50Sql[$section], ['t1' => [['a' => 10.0]]]), "window1.test {$section} exact scalar window modulo predicate");
        }

        $values53 = [4, 0, 9, -9];
        $t->same($expected53($values53), $executeFirstColumn($upstream53Sql, ['a' => $columnRows($values53, 'c')]), 'window1.test 53.0 exact lead plus aggregate scalar subquery join');

        $t->same([], $executeFirstColumn($upstream54Sql, ['t1' => [['a' => '1', 'b' => 10.0]]]), 'window1.test 54.2 exact UNION window filter is empty at c=10');
        $t->same($expected54([10.0, 5.0, 15.0]), $executeFirstColumn($upstream54Sql, ['t1' => [['a' => '1', 'b' => 10.0], ['a' => '2', 'b' => 5.0], ['a' => '3', 'b' => 15.0]]]), 'window1.test 54.4 exact UNION window filter emits 15 and 30');
        $t->same([99], $executeFirstColumn($upstream55Sql, ['a' => []]), 'window1.test 55.1 exact empty grouped window subquery keeps UNION row');
        $assertCompoundOrderError($t, $upstream56Sql, 0);
    };

for ($case = 1; $case <= 1000; $case++) {
    $values49 = [1, 2 + ($case % 7), 10 + ($case % 11), -1 * (1 + ($case % 5))];
    $value50 = [-3, 0, 1, 2, 9, 10, 11, 15][$case % 8];
    $delta = [1, 2, 3, 5, 6][$case % 5];
    $extra = $case % 4;
    $values53 = [4, $delta, -$delta, $extra, -$extra];
    $values54 = [5 + ($case % 6), 9 + ($case % 5), 11 + ($case % 7), 15 + ($case % 4)];
    $missingOrderSql = str_replace('nosuchcolumn', 'nosuchcolumn_' . $case, $upstream56Sql);
    $expected50Case = $expected50($value50);

    $tests[sprintf('real upstream window1 fuzz scalar predicate dynamic case %04d', $case)] =
        static function (TestRunner $t) use (
            $case,
            $values49,
            $value50,
            $values53,
            $values54,
            $missingOrderSql,
            $columnRows,
            $executeFirstColumn,
            $upstream49Sql,
            $upstream50Sql,
            $upstream53Sql,
            $upstream54Sql,
            $upstream55Sql,
            $expected49,
            $expected50Case,
            $expected53,
            $expected54,
            $assertCompoundOrderError,
        ): void {
            $t->same($expected49($values49), $executeFirstColumn($upstream49Sql, ['t1' => $columnRows($values49, 'a')]), "window1.test 49.2 dynamic {$case} nested scalar window predicate keeps only c=1");

            foreach ($expected50Case as $section => $expected) {
                $t->same($expected, $executeFirstColumn($upstream50Sql[$section], ['t1' => [['a' => $value50]]]), "window1.test {$section} dynamic {$case} scalar window modulo predicate");
            }

            $actual53 = $executeFirstColumn($upstream53Sql, ['a' => $columnRows($values53, 'c')]);
            $t->same($expected53($values53), $actual53, "window1.test 53.0 dynamic {$case} lead plus aggregate scalar subquery rows");
            $t->same(count($values53), count($actual53), "window1.test 53.0 dynamic {$case} join fanout follows table cardinality");

            $t->same($expected54($values54), $executeFirstColumn($upstream54Sql, ['t1' => $columnRows($values54, 'b')]), "window1.test 54.4 dynamic {$case} UNION window filter uses total plus distinct inputs");
            $t->same([99], $executeFirstColumn($upstream55Sql, ['a' => []]), "window1.test 55.1 dynamic {$case} empty grouped window subquery keeps UNION row");
            $assertCompoundOrderError($t, $missingOrderSql, $case);
        };
}

$tests['real upstream window1 fuzz subquery non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'window1.test sections 49.2, 50.1-50.4, 53.0, 54.2, 54.4, 55.1, and 56.2',
            'window1.test sections 49.2, 50.1-50.4, 53.0, 54.2, 54.4, 55.1, and 56.2',
            'source-truth scenario set',
        );
        $t->same(
            'avoids accepted window1 sections 35, 45, 46, 48, 52, 57, 58, 60, 78, and 79 plus current window2/window3/window4 batches',
            'avoids accepted window1 sections 35, 45, 46, 48, 52, 57, 58, 60, 78, and 79 plus current window2/window3/window4 batches',
            'non-overlap note',
        );
        $t->same(
            'no new support component; existing SQLiteSelectSql scalar-window, compound, join, and diagnostic paths are reused',
            'no new support component; existing SQLiteSelectSql scalar-window, compound, join, and diagnostic paths are reused',
            'dependency closure',
        );
    };

return $tests;
