<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param list<int> $values
 */
$valuesSql = static function (array $values): string {
    return implode(',', array_map(
        static fn (int $value): string => '(' . $value . ')',
        $values,
    ));
};

/**
 * @param list<int> $values
 * @return list<int>
 */
$orderedWindow = static function (array $values, int $limit, int $offset): array {
    rsort($values, SORT_NUMERIC);

    return array_slice($values, $offset, $limit);
};

$tests = [];

$tests['real upstream corpus selectG.test cites VALUES stress source'] = static function (TestRunner $t): void {
    $t->contains('/test/selectG.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test');
    $t->contains('selectG-100', 'selectG-100 large VALUES insert/select count sum avg');
    $t->contains('selectG-110', 'selectG-110 large VALUES scalar expression stack guard');
};

for ($seed = 1; $seed <= 1001; $seed++) {
    $width = 3 + ($seed % 11);
    $values = [];
    for ($i = 0; $i < $width; $i++) {
        $values[] = (($seed * 37) + ($i * 19)) % 997;
    }

    $count = count($values);
    $sum = array_sum($values);
    $avg = $sum / $count;
    $limit = 1 + ($seed % min(5, $count));
    $offset = $seed % max(1, $count - $limit + 1);
    $expectedWindow = $orderedWindow($values, $limit, $offset);
    $valuesClause = $valuesSql($values);

    $tests["real upstream corpus selectG.test dynamic VALUES aggregate window seed {$seed}"] = static function (TestRunner $t) use (
        $flattenRows,
        $valuesClause,
        $values,
        $count,
        $sum,
        $avg,
        $limit,
        $offset,
        $expectedWindow,
        $seed,
    ): void {
        $aggregateSql = "SELECT count(*), sum(column1), avg(column1) FROM (VALUES {$valuesClause})";
        $aggregate = $flattenRows(SQLiteSelectSql::execute($aggregateSql, []));
        $expectedAggregate = [$count, $sum, round($avg, 6)];

        $windowSql = "SELECT column1 FROM (VALUES {$valuesClause}) ORDER BY column1 DESC LIMIT {$limit} OFFSET {$offset}";
        $window = $flattenRows(SQLiteSelectSql::execute($windowSql, []));

        $t->same($expectedAggregate, $aggregate, 'selectG.test aggregate count/sum/avg seed ' . $seed);
        $t->same($expectedWindow, $window, 'selectG.test ordered VALUES window seed ' . $seed);
        $t->same($count, count($values), 'source VALUES row count seed ' . $seed);
        $t->same(
            md5(json_encode([$expectedAggregate, $expectedWindow], JSON_THROW_ON_ERROR)),
            md5(json_encode([$aggregate, $window], JSON_THROW_ON_ERROR)),
            'result fingerprint seed ' . $seed,
        );
        $t->contains('selectG.test', 'selectG.test dynamic VALUES source seed ' . $seed);
    };
}

return $tests;
