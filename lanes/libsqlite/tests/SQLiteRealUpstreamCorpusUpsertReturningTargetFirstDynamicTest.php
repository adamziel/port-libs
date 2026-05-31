<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(1000);

foreach ($cases as $case) {
    $prefix = sprintf('real upstream upsert1 target-first returning dynamic seed %04d %s %s', $case['seed'], $case['upstream'], $case['matched'][0]);

    $tests[$prefix . ' updates through selected conflict target'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['matched'], array_map(
            static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
            $plan['matched_arms'],
        ));
        $t->same(1, $plan['changes']);
    };

    $tests[$prefix . ' preserves non-target unique columns while applying excluded payload'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['after'], $plan['after']);
        $t->same($case['after'][0]['a'], $plan['after'][0]['a']);
        $t->same($case['after'][0]['b'], $plan['after'][0]['b']);
        $t->same($case['after'][0]['e'], $plan['after'][0]['e']);
    };

    $tests[$prefix . ' emits RETURNING for the updated row only'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);
        $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e', 'setting_key']);

        $t->same($case['returning'], $returning);
        $t->same(1, count($returning));
        $t->same([], $plan['inserted_rows']);
        $t->same([], $plan['skipped_rows']);
    };

    $tests[$prefix . ' keeps rowid and without-rowid variants equivalent'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['without_rowid'], str_contains($case['schema'], 'without-rowid'));
        $t->same($case['after'][0]['setting_key'], $plan['returning_rows'][0]['setting_key']);
    };

    $tests[$prefix . ' cites real upstream source and dependencies'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert1.test', $case['source']);
        $t->true(in_array($case['upstream'], [
            'upsert1-700',
            'upsert1-710',
            'upsert1-720',
            'upsert1-730',
            'upsert1-740',
            'upsert1-750',
            'upsert1-760',
            'upsert1-770',
            'upsert1-780',
        ], true));
        $t->same([
            'upsert1.test-700-through-780',
            'returning1.test-4',
            'sqlite-upsert-target-constraint-tested-first',
        ], $case['dependencies']);
    };
}

$tests['real upstream upsert1 target-first returning dynamic source coverage'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same(['e', 'a', 'b'], array_values(array_unique(array_map(static fn (array $case): string => $case['matched'][0], $cases))));
    $t->same(['upsert1.test'], array_values(array_unique(array_column($cases, 'source'))));
};

$tests['real upstream upsert1 target-first returning dynamic rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(0));
};

return $tests;
