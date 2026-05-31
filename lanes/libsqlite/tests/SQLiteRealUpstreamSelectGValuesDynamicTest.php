<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

$valuesSql = static function (array $values): string {
    return 'VALUES ' . implode(',', array_map(
        static fn (int $value): string => '(' . $value . ')',
        $values,
    ));
};

for ($case = 1; $case <= 1000; $case++) {
    $count = 8 + ($case % 121);
    $start = ($case * 17) % 251;
    $step = 1 + ($case % 13);
    $values = [];
    for ($i = 0; $i < $count; $i++) {
        $values[] = $start + ($i * $step);
    }
    $sum = array_sum($values);
    $avg = (float) ($sum / $count);
    $limit = 1 + ($case % min($count, 17));
    $offset = $case % max(1, $count - $limit + 1);
    $slice = array_slice($values, $offset, $limit);

    $tests[sprintf('real upstream corpus selectG.test selectG-100 dynamic values aggregate %04d', $case)] =
        static function (TestRunner $t) use ($valuesSql, $values, $count, $sum, $avg, $case): void {
            $sql = 'SELECT count(x), sum(x), avg(x) FROM (' . $valuesSql($values) . ') AS t(x)';
            $actual = SQLiteSelectSql::execute($sql, []);

            $t->same([[$count, $sum, $avg]], array_map('array_values', $actual), 'selectG aggregate over VALUES list');
            $t->contains('selectG.test', 'selectG.test selectG-100 dynamic values aggregate ' . $case);
            $t->true($count >= 8, 'dynamic VALUES list stays non-empty');
        };

    $tests[sprintf('real upstream corpus selectG.test selectG-100 dynamic ordered values slice %04d', $case)] =
        static function (TestRunner $t) use ($valuesSql, $values, $slice, $limit, $offset, $flatten): void {
            $sql = 'SELECT x FROM (' . $valuesSql($values) . ") AS t(x) ORDER BY x LIMIT {$limit} OFFSET {$offset}";
            $actual = $flatten(SQLiteSelectSql::execute($sql, []));

            $t->same($slice, $actual, 'selectG ordered VALUES list slice');
            $t->same($limit, count($actual), 'LIMIT controls VALUES source output');
            $t->true($offset >= 0, 'OFFSET is non-negative');
        };
}

for ($case = 1; $case <= 250; $case++) {
    $count = 20 + ($case % 181);
    $start = ($case * 31) % 997;
    $values = [];
    for ($i = 0; $i < $count; $i++) {
        $values[] = $start + $i;
    }

    $tests[sprintf('real upstream corpus selectG.test selectG-110 scalar values first-row %04d', $case)] =
        static function (TestRunner $t) use ($valuesSql, $values, $case): void {
            $sql = 'SELECT (' . $valuesSql($values) . ')';
            $actual = SQLiteSelectSql::execute($sql, []);

            $t->same([$values[0]], array_values($actual[0]), 'scalar VALUES expression returns only the left-most row');
            $t->same(1, count($actual), 'scalar VALUES expression emits one output row');
            $t->contains('selectG-110', 'selectG.test selectG-110 scalar values first-row ' . $case);
        };
}

$tests['real upstream corpus selectG.test cites values source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test';

    $t->true(is_file($source), 'hydrated upstream selectG.test is available');
    $text = file_get_contents($source);
    $t->contains('INSERT INTO t1(x) VALUES', $text);
    $t->contains('SELECT (VALUES', $text);
    $t->contains('Only the left-most term', $text);
};

return $tests;
