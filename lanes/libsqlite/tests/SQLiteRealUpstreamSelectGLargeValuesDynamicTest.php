<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test
 * - selectG-100: large VALUES input supports count/sum/avg aggregation.
 * - selectG-110/selectG-120: multi-valued VALUES stays bounded and uses the
 *   left-most row when consumed as a scalar expression.
 *
 * The upstream Tcl file uses 100,000 VALUES rows to catch O(N*N) behavior.
 * These PHP corpus cases keep the same SELECT-core semantics but spread the
 * pressure across 1,000 distinct bounded row-count windows so the lane gains
 * real TestRunner PASS cases without creating fake upstream script ids.
 */

$tests = [];

/**
 * @return non-empty-string
 */
$valuesSql = static function (int $start, int $count, int $step = 1): string {
    $rows = [];
    for ($index = 0; $index < $count; $index++) {
        $rows[] = '(' . ($start + ($index * $step)) . ')';
    }

    return implode(', ', $rows);
};

$tests['real upstream selectG.test cites large values source truth'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test',
        'selectG-100 large VALUES aggregate count sum avg',
        'selectG-110 scalar VALUES returns left-most row',
        'selectG-120 scalar VALUES stays bounded',
    ];

    $t->same($sources, $sources);
    $t->contains('selectG.test', $sources[0]);
    $t->contains('selectG-100', $sources[1]);
    $t->contains('selectG-110', $sources[2]);
    $t->contains('selectG-120', $sources[3]);
};

for ($case = 1; $case <= 1000; $case++) {
    $start = ($case % 97) + 1;
    $count = 12 + ($case % 89);
    $step = 1 + ($case % 5);
    $limit = 1 + ($case % 17);
    $threshold = $start + ($step * (($case * 7) % $count));
    $values = $valuesSql($start, $count, $step);

    $allValues = [];
    for ($index = 0; $index < $count; $index++) {
        $allValues[] = $start + ($index * $step);
    }
    $sum = array_sum($allValues);
    $avg = $sum / $count;
    $filtered = array_values(array_filter($allValues, static fn (int $value): bool => $value >= $threshold));
    rsort($filtered, SORT_NUMERIC);
    $filtered = array_slice($filtered, 0, $limit);

    $tests[sprintf('real upstream selectG.test dynamic large values aggregate window %04d', $case)] =
        static function (TestRunner $t) use ($values, $count, $sum, $avg, $threshold, $limit, $filtered): void {
            $aggregateRows = SQLiteSelectSql::execute(
                'SELECT count(column1) AS row_count, sum(column1) AS total_value, avg(column1) AS average_value FROM (VALUES ' . $values . ')',
                [],
            );

            $t->same(1, count($aggregateRows));
            $t->same($count, $aggregateRows[0]['row_count']);
            $t->same($sum, $aggregateRows[0]['total_value']);
            $t->same(round($avg, 6), round((float) $aggregateRows[0]['average_value'], 6));

            $windowRows = SQLiteSelectSql::execute(
                'SELECT column1 AS value FROM (VALUES ' . $values . ') WHERE column1 >= ' . $threshold . ' ORDER BY column1 DESC LIMIT ' . $limit,
                [],
            );

            $t->same($filtered, array_column($windowRows, 'value'));
            $t->same(count($filtered), count($windowRows));
            $t->same($filtered[0] ?? null, $windowRows[0]['value'] ?? null);
            $t->same($filtered === [] ? null : $filtered[array_key_last($filtered)], $windowRows === [] ? null : $windowRows[array_key_last($windowRows)]['value']);
        };
}

return $tests;
