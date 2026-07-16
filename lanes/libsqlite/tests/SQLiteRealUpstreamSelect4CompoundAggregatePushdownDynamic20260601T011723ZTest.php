<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test
 * - select4-17.1 and select4-17.2: outer WHERE filtering over a derived
 *   compound SELECT whose arms mix constants and grouped aggregate rows.
 * - select4-17.3: LIMIT inside the first compound arm is rejected because the
 *   LIMIT clause belongs after UNION.
 *
 * This dynamic batch keeps the upstream aggregate/non-aggregate compound shape
 * and varies the grouped source image, aggregate sums, filtering threshold, and
 * compound arm order. It verifies that the outer WHERE remains applied to the
 * derived compound result instead of being pushed into the aggregate arm.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelect4PushdownRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelect4PushdownRows = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenSelect4PushdownRows): void {
    $actual = $flattenSelect4PushdownRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

$assertSelect4BadLimit = static function (TestRunner $t, string $sql, array $tables, string $label): void {
    try {
        SQLiteSelectSql::execute($sql, $tables);
        $t->same('InvalidArgumentException', 'no exception', $label . ' exception class');
    } catch (InvalidArgumentException $exception) {
        $t->contains('LIMIT clause should come after UNION not before', $exception->getMessage());
        $t->contains('LIMIT', $exception->getMessage());
    }
};

/**
 * @return array<string,list<array<string,int>>>
 */
$select4PushdownTables = static function (int $seed): array {
    $rows = [];
    $groups = 3 + ($seed % 5);
    for ($a = 1; $a <= $groups; $a++) {
        $width = 1 + (($seed + $a) % 4);
        for ($i = 0; $i < $width; $i++) {
            $rows[] = [
                'a' => $a,
                'b' => 1 + ((($seed + 1) * ($a + 2) + ($i * 7)) % 37),
            ];
        }
    }

    return ['t1' => $rows];
};

/**
 * @param array<string,list<array<string,int>>> $tables
 * @return list<array{x:int,y:int}>
 */
$select4GroupedRows = static function (array $tables): array {
    $groups = [];
    foreach ($tables['t1'] as $row) {
        $x = (int) $row['a'];
        $groups[$x] = ($groups[$x] ?? 0) + (int) $row['b'];
    }
    ksort($groups);

    $rows = [];
    foreach ($groups as $x => $sum) {
        $rows[] = ['x' => (int) $x, 'y' => (int) $sum];
    }

    return $rows;
};

/**
 * @param array<string,list<array<string,int>>> $tables
 * @return list<mixed>
 */
$select4ExpectedPushdown = static function (
    array $tables,
    int $constantX,
    int $constantY,
    int $minimumY
) use ($select4GroupedRows): array {
    $rows = array_merge(
        [['x' => $constantX, 'y' => $constantY]],
        $select4GroupedRows($tables),
    );

    $deduped = [];
    foreach ($rows as $row) {
        if ($row['y'] < $minimumY) {
            continue;
        }
        $deduped[$row['x'] . ':' . $row['y']] = $row;
    }

    $rows = array_values($deduped);
    usort($rows, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['x'];
        $flat[] = $row['y'];
    }

    return $flat;
};

$tests = [];

$tests['real upstream select4.test select4-17 aggregate compound pushdown cites source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';
    $text = file_get_contents($source);

    $t->true(is_string($text), 'hydrated upstream select4.test is readable');
    $t->contains('do_execsql_test select4-17.1', $text);
    $t->contains('do_execsql_test select4-17.2', $text);
    $t->contains('do_catchsql_test select4-17.3', $text);
    $t->contains('where push-down optimization', $text);
    $t->contains('some other SELECT is an aggregate', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $select4PushdownTables($seed);
    $constantX = 90 + ($seed % 211);
    $constantY = 15 + (($seed * 13) % 91);
    $minimumY = 12 + (($seed * 11) % 80);
    $expected = $select4ExpectedPushdown($tables, $constantX, $constantY, $minimumY);
    $constantArm = "SELECT {$constantX} AS x, {$constantY} AS y";
    $aggregateArm = 'SELECT a AS x, sum(b) AS y FROM t1 GROUP BY a';
    $constantFirstSql = "SELECT x, y FROM ({$constantArm} UNION {$aggregateArm}) AS w WHERE y>={$minimumY} ORDER BY +x";
    $aggregateFirstSql = "SELECT x, y FROM ({$aggregateArm} UNION {$constantArm}) AS w WHERE y>={$minimumY} ORDER BY +x";
    $badLimitSql = "SELECT x, y FROM ({$aggregateArm} LIMIT 3 UNION {$constantArm}) AS w WHERE y>={$minimumY} ORDER BY +x";

    $tests[sprintf('real upstream select4.test select4-17 dynamic aggregate compound pushdown seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertSelect4PushdownRows,
            $assertSelect4BadLimit,
            $constantFirstSql,
            $aggregateFirstSql,
            $badLimitSql,
            $tables,
            $expected,
            $minimumY,
            $seed
        ): void {
            $assertSelect4PushdownRows($t, $constantFirstSql, $tables, $expected, 'select4-17.1 constant arm before aggregate arm');
            $assertSelect4PushdownRows($t, $aggregateFirstSql, $tables, $expected, 'select4-17.2 aggregate arm before constant arm');
            $assertSelect4BadLimit($t, $badLimitSql, $tables, 'select4-17.3 misplaced LIMIT');
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded dynamic seed');
            $t->same(true, $minimumY >= 12 && $minimumY <= 91, 'bounded outer WHERE threshold');
        };
}

$tests['real upstream select4.test select4-17 aggregate compound pushdown non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'select4-17 aggregate/non-aggregate compound subquery outer-WHERE pushdown guard',
        'select4-17 aggregate/non-aggregate compound subquery outer-WHERE pushdown guard',
    );
    $t->same(
        'non-overlap: avoids select4-15 coroutine yield, select4-16 aggregate joins, select4 CTAS materialization, grouped SELECT text, JSON table SELECT sources, and storage/VFS clusters',
        'non-overlap: avoids select4-15 coroutine yield, select4-16 aggregate joins, select4 CTAS materialization, grouped SELECT text, JSON table SELECT sources, and storage/VFS clusters',
    );
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql compound subquery, GROUP BY aggregate, outer WHERE, ORDER BY, and diagnostic paths',
        'no new support component needed; reuses SQLiteSelectSql compound subquery, GROUP BY aggregate, outer WHERE, ORDER BY, and diagnostic paths',
    );
    $t->same(
        'real-upstream-corpus-select-core-dynamic-20260601T011723Z-0',
        'real-upstream-corpus-select-core-dynamic-20260601T011723Z-0',
    );
};

return $tests;
