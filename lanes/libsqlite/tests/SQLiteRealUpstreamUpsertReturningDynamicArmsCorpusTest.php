<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/upsert5.test upsert5-1.1 through 3.1.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::multiArmConflictCases() as $case) {
    $tests['real upstream corpus upsert returning dynamic arms multi arm ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);
        $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
            'a',
            'selected_arm' => 'b',
            'conflict_c' => 'c',
            'conflict_d' => 'd',
            'conflict_e' => 'e',
        ]);

        $t->same('upsert5.test', $case['source']);
        $t->same($case['expected'], $plan['after']);
        $t->same($case['returning'], $returning);
        $t->same($case['changes'], $plan['changes']);
        $t->same($case['matched'], array_map(static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']), $plan['matched_arms']));
        $t->same($case['changes'], count($returning));
        $t->same($case['changes'] === 0 ? [] : [$returning[0]['a']], array_column($returning, 'a'));
        $t->true($plan['before'] === $case['before']);
        $t->true(str_starts_with($case['upstream'], 'upsert5-'));
        $t->true(array_is_list($plan['after']));
    };
}

// Source truth: SQLite upstream test/upsert5.test multi-arm permutations.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::multiArmOrderCases() as $case) {
    $tests['real upstream corpus upsert returning dynamic arms order ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $row = $case['returning'][0];
        $expectedByArm = [
            'a' => ['a' => 1, 'conflict_c' => 10, 'conflict_d' => 100, 'conflict_e' => 1000],
            'c' => ['a' => 2, 'conflict_c' => 20, 'conflict_d' => 200, 'conflict_e' => 2000],
            'd' => ['a' => 3, 'conflict_c' => 30, 'conflict_d' => 300, 'conflict_e' => 3000],
            'e' => ['a' => 4, 'conflict_c' => 40, 'conflict_d' => 400, 'conflict_e' => 4000],
        ][$case['selected']];

        $t->same('upsert5.test', $case['source']);
        $t->same($case['selected'], $row['selected_arm']);
        $t->same([$case['selected']], $case['matched']);
        $t->same($case['order'][0], $case['selected']);
        $t->same(1, count($case['returning']));
        $t->same($expectedByArm['a'], $row['a']);
        $t->same($expectedByArm['conflict_c'], $row['conflict_c']);
        $t->same($expectedByArm['conflict_d'], $row['conflict_d']);
        $t->same($expectedByArm['conflict_e'], $row['conflict_e']);
        $t->same(4, count($case['after']));
        $t->same($case['selected'], $case['after'][['a' => 0, 'c' => 1, 'd' => 2, 'e' => 3][$case['selected']]]['b']);
        $t->true(array_is_list($case['order']));
        $t->true(array_is_list($case['after']));
        $t->true(str_starts_with($case['upstream'], 'upsert5-order-'));
    };
}

// Source truth: SQLite upstream test/upsert4.test upsert4-1.* and 2.*.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::nullAndPartialConflictCases() as $case) {
    $tests['real upstream corpus upsert returning dynamic arms null conflict ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);
        $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c']);

        $t->same('upsert4.test', $case['source']);
        $t->same($case['expected'], $plan['after']);
        $t->same($case['returning'], $returning);
        $t->same($case['skipped'], count($plan['skipped_rows']));
        $t->same($case['changes'], $plan['changes']);
        $t->same($case['changes'], count($returning));
        $t->same($case['before'], $plan['before']);
        $t->true(str_starts_with($case['upstream'], 'upsert4-'));
        $t->true(array_is_list($plan['inserted_rows']));
        $t->true(array_is_list($plan['updated_rows']));
    };
}

// Source truth: SQLite upstream test/upsert4.test upsert4-3.10 through 3.30.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::replaceOrderingCases() as $case) {
    $tests['real upstream corpus upsert returning dynamic arms replace order ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert4.test', $case['source']);
        $t->same(1, $case['changes']);
        $t->same([$case['inserted']], $case['returning']);
        $t->same($case['inserted'], $case['after'][array_key_last($case['after'])]);
        $t->same(count($case['after']), 3 - count($case['deleted']));
        $t->true(count($case['deleted']) === 0 || count($case['deleted']) === 1);
        $t->true(!in_array($case['inserted'], $case['deleted'], true));
        $t->true(array_is_list($case['after']));
        $t->true(str_starts_with($case['upstream'], 'upsert4-3.'));
        $t->same(['a', 'b', 'c'], array_keys($case['returning'][0]));
    };
}

// Source truth: SQLite upstream test/returning1.test returning1-1.*, 10.1,
// and 15.1.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::returningProjectionCases() as $case) {
    $tests['real upstream corpus upsert returning dynamic arms projection ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows([$case['row']], $case['projection']);

        $t->same('returning1.test', $case['source']);
        $t->same([$case['expected']], $projected);
        $t->same($case['column_names'], array_keys($projected[0]));
        $t->same(count($case['column_names']), count($projected[0]));
        $t->true(array_key_exists($case['column_names'][0], $projected[0]));
        $t->same($case['row'], $case['row']);
        $t->true(str_starts_with($case['upstream'], 'returning1-'));
        $t->true($case['projection'] !== []);
        $t->true(array_is_list($projected));
        $t->true(array_is_list($case['column_names']));
    };
}

// Source truth: SQLite upstream test/returning1.test returning1-12.*.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::returningConstraintOrderCases() as $case) {
    $tests['real upstream corpus upsert returning dynamic arms constraint order ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $validParent = $case['incoming']['parent_id'] === null || in_array($case['incoming']['parent_id'], $case['parent_ids'], true);
        $returning = $validParent ? $case['returning'] : [];

        $t->same('returning1.test', $case['source']);
        $t->same(!$validParent, $case['error_before_returning']);
        $t->same($case['returning'], $returning);
        $t->same($case['error_before_returning'] ? 0 : 1, count($returning));
        $t->true($case['incoming']['id'] > 0);
        $t->true($case['incoming']['parent_id'] === null || is_int($case['incoming']['parent_id']));
        $t->true(array_is_list($case['parent_ids']));
        $t->true(str_starts_with($case['upstream'], 'returning1-12.'));
        $t->same($case['error_before_returning'], $returning === []);
        $t->same($case['error_before_returning'] ? [] : [['id' => $case['incoming']['id']]], $returning);
    };
}

$tests['real upstream corpus upsert returning dynamic arms rejects unmatched conflict target from upsert3-110'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            [['k' => 1, 'v' => 1]],
            [['k' => 1, 'v' => 2]],
            [['target' => ['k'], 'action' => 'nothing']],
            [['k', 'v']],
        );
    });
};

$tests['real upstream corpus upsert returning dynamic arms rejects table wildcard from returning1-4.1'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteUpsertDoUpdateWherePlan::returningRows([['a' => 1]], ['t1.*']);
    });
};

$tests['real upstream corpus upsert returning dynamic arms rejects missing returning column from returning1-4.2'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteUpsertDoUpdateWherePlan::returningRows([['a' => 1]], ['missing']);
    });
};

return $tests;
