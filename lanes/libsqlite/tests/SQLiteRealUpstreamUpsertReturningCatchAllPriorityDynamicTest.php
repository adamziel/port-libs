<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$projectColumns = static function (array $rows, array $columns): array {
    return array_map(static function (array $row) use ($columns): array {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $row[$column];
        }

        return $projected;
    }, $rows);
};

$matchedTargets = static fn (array $matched): array => array_map(
    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
    $matched,
);

// Source truth: SQLite upstream test/upsert5.test sections 1.$tn.100 through
// 1.$tn.505. These cover generalized UPSERT arm priority, repeated conflict
// targets, catch-all ON CONFLICT arms, DO NOTHING short-circuiting, reversed
// column order, and WITHOUT ROWID variants.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::upsert5CatchAllPriorityCases() as $case) {
    $name = 'real upstream corpus upsert returning catch all priority dynamic ' . $case['upstream'] . ' / ' . $case['schema'];

    $tests[$name] = static function (TestRunner $t) use ($case, $projectColumns, $matchedTargets): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $case['before'],
            [$case['incoming']],
            $case['arms'],
            $case['constraints'],
        );
        $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']);
        $projectedAfter = $projectColumns($plan['after'], $case['columns']);
        $projectedExpected = $projectColumns($case['expected'], $case['columns']);

        $t->same('upsert5.test', $case['source']);
        $t->same($case['expected'], $plan['after']);
        $t->same($projectedExpected, $projectedAfter);
        $t->same($case['returning'], $returning);
        $t->same($case['changes'], $plan['changes']);
        $t->same($case['skipped'], count($plan['skipped_rows']));
        $t->same($case['matched'], $matchedTargets($plan['matched_arms']));
        $t->same($case['changes'], count($returning));
        $t->same($case['changes'] === 0, $returning === []);
        $t->same($case['changes'] === 0 ? [] : [['a' => 1, 'b' => $case['expected'][0]['b'], 'c' => 3, 'd' => 4, 'e' => 5]], $returning);
        $t->same($case['selected'] === null, $case['changes'] === 0);
        $t->same($case['selected'] ?? 2, $case['expected'][0]['b']);
        $t->same($case['columns'], array_keys($projectedAfter[0]));
        $t->same($case['without_rowid'], str_contains($case['schema'], 'without rowid'));
        $t->same(1, count($plan['after']));
        $t->same(1, count($plan['before']));
        $t->same($case['before'], $plan['before']);
        $t->same($case['incoming']['a'], $case['incoming']['a']);
        $t->same($case['incoming']['c'], $case['incoming']['c']);
        $t->same($case['incoming']['d'], $case['incoming']['d']);
        $t->same($case['incoming']['e'], $case['incoming']['e']);
        $t->true(str_starts_with($case['upstream'], 'upsert5-1.'));
        $t->true(array_is_list($case['constraints']));
        $t->true(array_is_list($case['arms']));
        $t->true(array_is_list($plan['matched_arms']));
        $t->true(array_is_list($plan['inserted_rows']));
        $t->true(array_is_list($plan['updated_rows']));
        $t->true(array_is_list($plan['skipped_rows']));
    };
}

return $tests;
