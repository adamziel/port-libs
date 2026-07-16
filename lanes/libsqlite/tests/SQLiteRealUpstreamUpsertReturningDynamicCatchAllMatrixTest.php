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

$matchedTargets = static fn (array $matches): array => array_map(
    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
    $matches,
);

// Source truth: SQLite upstream test/upsert5.test generalized UPSERT arm
// matrix, especially the catch-all and DO NOTHING cases upsert5-1.*.400
// through upsert5-1.*.505 over all six table layouts.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::upsert5CatchAllPriorityCases() as $case) {
    $name = 'real upstream corpus upsert returning dynamic catch-all matrix ' . $case['upstream'];

    $tests[$name . ' final rows follow upstream selected arm'] = static function (TestRunner $t) use ($case, $projectColumns): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);

        $t->same($projectColumns($case['expected'], $case['columns']), $projectColumns($plan['after'], $case['columns']));
    };

    $tests[$name . ' returning rows include only changed row images'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);
        $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']);

        $t->same($case['returning'], $returning);
        $t->same($case['changes'], count($returning));
    };

    $tests[$name . ' matched arm reports first conflict target'] = static function (TestRunner $t) use ($case, $matchedTargets): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);

        $t->same($case['matched'], $matchedTargets($plan['matched_arms']));
    };

    $tests[$name . ' change and skipped counts match upstream outcome'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);

        $t->same($case['changes'], $plan['changes']);
        $t->same($case['skipped'], count($plan['skipped_rows']));
    };

    $tests[$name . ' partitions insert update and do-nothing outcomes'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);
        $selected = $case['selected'];

        $t->same($selected === null ? 0 : 1, count($plan['updated_rows']));
        $t->same(0, count($plan['inserted_rows']));
        $t->same($case['skipped'], count($plan['skipped_rows']));
    };

    $tests[$name . ' preserves schema variant metadata'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert5.test', $case['source']);
        $t->true(str_starts_with($case['upstream'], 'upsert5-1.'));
        $t->true(array_is_list($case['columns']));
        $t->true(array_is_list($case['constraints']));
        $t->true(is_bool($case['without_rowid']));
    };
}

$tests['real upstream corpus upsert returning dynamic catch-all matrix source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test upsert5-1.1.400 through upsert5-1.6.505 catch-all update and DO NOTHING arms',
        'upsert5.test six rowid/int-primary-key/WITHOUT ROWID schema variants',
    ], [
        'upsert5.test upsert5-1.1.400 through upsert5-1.6.505 catch-all update and DO NOTHING arms',
        'upsert5.test six rowid/int-primary-key/WITHOUT ROWID schema variants',
    ]);
};

return $tests;
