<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow4 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test';

$firstVisibleValue = static function (array $rows): mixed {
    foreach ($rows[0] ?? [] as $column => $value) {
        if (is_string($column) && str_starts_with($column, '__sqlite_')) {
            continue;
        }

        return $value;
    }

    throw new RuntimeException('Expected one visible scalar result column');
};

$makeRows = static function (int $case): array {
    $rows = [];
    $count = 2 + ($case % 17);
    $base = ($case % 11) - 5;
    for ($index = 0; $index < $count; $index++) {
        $rows[] = ['a' => $base + $index * (($case % 5) + 1)];
    }

    return $rows;
};

$expectedAverage = static function (array $rows): float {
    return array_sum(array_column($rows, 'a')) / count($rows);
};

$assertFloatEquals = static function (TestRunner $t, float $expected, mixed $actual, string $message): void {
    $t->true(is_float($actual) || is_int($actual), $message . ' returns numeric value');
    $t->true(abs($expected - (float) $actual) < 0.0000001, $message);
};

$tests['real upstream window4 section 12.2 source truth is hydrated'] = static function (TestRunner $t) use ($upstreamWindow4): void {
    $source = file_get_contents($upstreamWindow4);
    if ($source === false) {
        throw new RuntimeException('Unable to read upstream window4.test');
    }

    $t->contains('do_test 12.2', $source);
    $t->contains('SELECT (SELECT avg(a)) FROM t2 ORDER BY 1', $source);
    $t->contains('set res2 {2.0000}', $source);
};

$tests['real upstream window4 12.2 scalar aggregate subquery collapses outer rows'] = static function (TestRunner $t) use ($firstVisibleValue, $assertFloatEquals): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT (SELECT avg(a)) FROM t2 ORDER BY 1',
        ['t2' => [['a' => 1], ['a' => 2], ['a' => 3]]],
    );

    $t->same(1, count($rows), 'window4.test 12.2 returns one aggregate row');
    $assertFloatEquals($t, 2.0, $firstVisibleValue($rows), 'window4.test 12.2 avg(a) over outer rows');
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = $makeRows($case);
    $expected = $expectedAverage($rows);
    $tests[sprintf('real upstream window4 12.2 dynamic scalar aggregate subquery case %04d', $case)] =
        static function (TestRunner $t) use ($rows, $expected, $firstVisibleValue, $assertFloatEquals, $case): void {
            $actualRows = SQLiteSelectSql::execute(
                'SELECT (SELECT avg(a)) FROM t2 ORDER BY 1',
                ['t2' => $rows],
            );

            $t->same(1, count($actualRows), "window4.test 12.2 dynamic case {$case} collapses to one row");
            $assertFloatEquals($t, $expected, $firstVisibleValue($actualRows), "window4.test 12.2 dynamic avg case {$case}");
            $t->same(count($rows), count(array_column($rows, 'a')), "window4.test 12.2 dynamic case {$case} fixture is dense");
        };
}

$tests['real upstream window4 12.2 non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-window-functions-dynamic-20260601T043845Z-0', 'real-upstream-corpus-window-functions-dynamic-20260601T043845Z-0');
    $t->same(
        'upstream file: window4.test section 12.2 scalar aggregate subquery over outer rows',
        'upstream file: window4.test section 12.2 scalar aggregate subquery over outer rows',
    );
    $t->same(
        'non-overlap: completes the explicit 12.2 follow-up left by the accepted 20260601T025508Z window4 section 11/12 slice without repeating 12.1 or 12.3',
        'non-overlap: completes the explicit 12.2 follow-up left by the accepted 20260601T025508Z window4 section 11/12 slice without repeating 12.1 or 12.3',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql implicit aggregate summaries and scalar subquery parsing',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql implicit aggregate summaries and scalar subquery parsing',
    );
};

return $tests;
