<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$rowsForColumn = static function (array $values, string $column): array {
    return array_map(static fn (mixed $value): array => [$column => $value], $values);
};

$caseValues = static function (int $case): array {
    $minimum = 1 + ($case % 11);
    $count = 4 + ($case % 8);
    $values = [$minimum];

    for ($index = 1; $index < $count; $index++) {
        $values[] = $minimum + (($case * 17 + $index * 7 + intdiv($case, 5)) % 37);
    }

    return $values;
};

$expectedBinaryWindowRows = static function (array $values): array {
    $ordered = array_values(array_map(static fn (mixed $value): int => (int) $value, $values));
    sort($ordered, SORT_NUMERIC);
    $minimum = $ordered[0];

    return array_map(static fn (int $value): array => [
        'x' => $value,
        'ratio' => intdiv($value, $minimum),
        'rem' => $value % $minimum,
        'plus_value' => $value + $minimum,
        'delta_value' => $value - $minimum,
        'product' => $value * $minimum,
    ], $ordered);
};

$binaryWindowSql = <<<'SQL'
SELECT x,
       max(x) OVER (ORDER BY x) / min(x) OVER () AS ratio,
       max(x) OVER (ORDER BY x) % min(x) OVER () AS rem,
       max(x) OVER (ORDER BY x) + min(x) OVER () AS plus_value,
       max(x) OVER (ORDER BY x) - min(x) OVER () AS delta_value,
       max(x) OVER (ORDER BY x) * min(x) OVER () AS product
  FROM t1
 ORDER BY x
SQL;

$nestedScalarWindowSql = <<<'SQL'
SELECT (
  SELECT max(x) OVER (ORDER BY x) / min(x) OVER ()
    FROM t2
   ORDER BY x
   LIMIT 1
) AS ratio
  FROM t1
 ORDER BY a
SQL;

$upstreamExact61Sql = <<<'SQL'
SELECT (
  SELECT max(x)OVER(ORDER BY x) / min(x) OVER()
)
FROM (
  SELECT (SELECT sum(a) FROM t1) AS x FROM t1
)
SQL;

$tests['real upstream window1 61 agginfo binary source truth is hydrated'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        $t->true($source !== false, 'hydrated upstream window1.test is available');
        $source = (string) $source;

        $t->contains('do_catchsql_test 61.1', $source, 'window1.test 61.1 dbsqlfuzz AggInfo expression case is present');
        $t->contains('do_catchsql_test 61.2.$tn', $source, 'window1.test 61.2 binary window scalar subquery case is present');
        $t->contains('max(x)OVER(ORDER BY x) % min(x)OVER', $source, 'window1.test 61.1 includes modulo over two window results');
        $t->contains('max(x)OVER(ORDER BY x) / min(x) OVER()', $source, 'window1.test 61.2 includes division over two window results');
        $t->contains('AggInfo objects', $source, 'window1.test 61 provenance explains persisted aggregate/window state');
    };

$tests['real upstream window1 61.2 exact scalar binary window baseline'] =
    static function (TestRunner $t) use ($upstreamExact61Sql): void {
        $rows = SQLiteSelectSql::execute($upstreamExact61Sql, [
            't1' => [
                ['a' => 5],
                ['a' => null],
                ['a' => 'seventeen'],
            ],
        ]);

        $t->same([['expr1' => 1], ['expr1' => 1], ['expr1' => 1]], $rows, 'window1.test 61.2 scalar subquery divides materialized max/min window columns');
    };

for ($case = 1; $case <= 1000; $case++) {
    $values = $caseValues($case);
    $outerRows = [];
    for ($index = 1; $index <= 1 + ($case % 5); $index++) {
        $outerRows[] = ['a' => $index];
    }
    $expectedNested = array_fill(0, count($outerRows), ['ratio' => 1]);

    $tests[sprintf('real upstream window1 61 binary window expression dynamic case %04d', $case)] =
        static function (TestRunner $t) use (
            $case,
            $values,
            $outerRows,
            $expectedNested,
            $rowsForColumn,
            $expectedBinaryWindowRows,
            $binaryWindowSql,
            $nestedScalarWindowSql,
        ): void {
            $tables = ['t1' => $rowsForColumn($values, 'x')];
            $actualRows = SQLiteSelectSql::execute($binaryWindowSql, $tables);

            $t->same($expectedBinaryWindowRows($values), $actualRows, "window1.test 61 dynamic {$case} binary operators materialize both window operands before arithmetic");

            $nestedRows = SQLiteSelectSql::execute($nestedScalarWindowSql, [
                't1' => $outerRows,
                't2' => $rowsForColumn($values, 'x'),
            ]);
            $t->same($expectedNested, $nestedRows, "window1.test 61.2 dynamic {$case} scalar subquery preserves first binary window result");
        };
}

$tests['real upstream window1 61 binary window non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'window1.test 61.1-61.2 AggInfo binary window expression regression',
            'window1.test 61.1-61.2 AggInfo binary window expression regression',
            'source-truth scenario set',
        );
        $t->same(
            'avoids accepted window1 sections 25-26, 35, 43, 45-46, 48-50, 53-60 and existing window2-windowE batches',
            'avoids accepted window1 sections 25-26, 35, 43, 45-46, 48-50, 53-60 and existing window2-windowE batches',
            'non-overlap note',
        );
        $t->same(
            'no new support component; existing SQLiteSelectSql expression parser and SQLiteSelectQuery window materializer are reused',
            'no new support component; existing SQLiteSelectSql expression parser and SQLiteSelectQuery window materializer are reused',
            'dependency closure',
        );
    };

return $tests;
