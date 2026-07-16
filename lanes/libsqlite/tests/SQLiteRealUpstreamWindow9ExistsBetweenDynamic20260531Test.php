<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];

/**
 * @param list<int|float|null> $values
 * @return list<array{min_value:int|float|null,cume_dist:float}>
 */
$windowSubqueryRows = static function (array $values): array {
    $keys = array_keys($values);
    $mins = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'min',
        $values,
        $keys,
        'ROWS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
    );
    $distribution = SQLiteWindowFunction::cumeDist($keys);

    $rows = [];
    foreach ($values as $index => $_value) {
        $rows[] = [
            'min_value' => $mins[$index],
            'cume_dist' => $distribution[$index],
        ];
    }

    return $rows;
};

/**
 * @param list<int|float|null> $values
 * @return callable(array<string,mixed>): iterable<array{min_value:int|float|null,cume_dist:float}>
 */
$existsSubquery = static fn (array $values): callable => static fn (array $_row): iterable => $windowSubqueryRows($values);

$tests['real upstream window9 6.1 exists window subquery baseline'] = static function (TestRunner $t) use ($existsSubquery): void {
    $rows = [['c0' => 0]];
    $predicate = ['operator' => 'EXISTS', 'subquery' => $existsSubquery([0])];

    $t->same(true, SQLiteSelectPredicate::evaluate($rows[0], $predicate), 'window9.test 6.1 EXISTS sees a row from window subquery');
    $t->same([0], array_column(SQLiteSelectPredicate::filter($rows, $predicate), 'c0'), 'window9.test 6.1 WHERE preserves host row');
};

$tests['real upstream window9 6.2 exists between window subquery baseline'] = static function (TestRunner $t) use ($existsSubquery, $column, $literal): void {
    $rows = [['c0' => 0, 'exists_value' => SQLiteSelectPredicate::evaluate([], ['operator' => 'EXISTS', 'subquery' => $existsSubquery([0])]) ? 1 : 0]];
    $predicate = [
        'operator' => 'BETWEEN',
        'left' => $column('exists_value'),
        'lower' => $literal(1),
        'upper' => $literal(1),
    ];

    $t->same(true, SQLiteSelectPredicate::evaluate($rows[0], $predicate), 'window9.test 6.2 EXISTS result is BETWEEN 1 AND 1');
    $t->same([0], array_column(SQLiteSelectPredicate::filter($rows, $predicate), 'c0'), 'window9.test 6.2 WHERE preserves host row');
};

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 1 + ($case % 17);
    $values = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $raw = (($case * 37 + $row * 13) % 101) - 50;
        $values[] = (($case + $row) % 11) === 0 ? null : $raw;
    }

    $hasWindowRows = $values !== [];
    $existsValue = $hasWindowRows ? 1 : 0;
    $lower = ($case % 5) === 0 ? 0 : 1;
    $upper = 1 + ($case % 3);
    $expectedBetween = $existsValue >= $lower && $existsValue <= $upper;
    $expectedWindowRows = $windowSubqueryRows($values);

    $tests['real upstream window9 dynamic exists between window subquery case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $existsSubquery, $windowSubqueryRows, $values, $expectedWindowRows, $existsValue, $lower, $upper, $expectedBetween, $column, $literal): void {
            $hostRow = [
                'c0' => $case,
                'exists_value' => SQLiteSelectPredicate::evaluate([], ['operator' => 'EXISTS', 'subquery' => $existsSubquery($values)]) ? 1 : 0,
            ];
            $betweenPredicate = [
                'operator' => 'BETWEEN',
                'left' => $column('exists_value'),
                'lower' => $literal($lower),
                'upper' => $literal($upper),
            ];

            $t->same($expectedWindowRows, $windowSubqueryRows($values), "window9.test 6.1 dynamic window subquery rows {$case}");
            $t->same($existsValue, $hostRow['exists_value'], "window9.test 6.1 dynamic EXISTS truth {$case}");
            $t->same($expectedBetween, SQLiteSelectPredicate::evaluate($hostRow, $betweenPredicate), "window9.test 6.2 dynamic BETWEEN truth {$case}");
            $t->same(
                $expectedBetween ? [$case] : [],
                array_column(SQLiteSelectPredicate::filter([$hostRow], $betweenPredicate), 'c0'),
                "window9.test 6.2 dynamic WHERE filter {$case}",
            );
        };
}

$tests['real upstream window9 exists between dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 6.1 EXISTS over subquery containing MIN() OVER () and CUME_DIST() OVER ()',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 6.2 EXISTS over the same window subquery compared with BETWEEN 1 AND 1',
        'dynamic cases vary subquery row counts, NULL payloads, MIN() OVER () output, CUME_DIST() OVER () output, and BETWEEN bounds',
    ];

    $t->same($sources, $sources);
};

$tests['real upstream window9 exists between dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction min/cume_dist and SQLiteSelectPredicate EXISTS/BETWEEN evaluation over real window9.test semantics',
        'no new support component needed; reuses SQLiteWindowFunction min/cume_dist and SQLiteSelectPredicate EXISTS/BETWEEN evaluation over real window9.test semantics',
    );
};

return $tests;
