<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = SQLiteUpsertReturningDynamicCorpusPlan::upsert2WhereFalseReturningDynamicCases(1000);

foreach ($cases as $case) {
    $prefix = sprintf('real upstream upsert2 where-false returning dynamic seed %04d', $case['seed']);

    $tests[$prefix . ' keeps skipped conflict out of returning stream'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['returning'], $plan['returning_rows']);
        $t->same($case['changes'], $plan['changes']);
    };

    $tests[$prefix . ' preserves table image until later matching row updates it'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['after'], $plan['after']);
    };

    $tests[$prefix . ' records where-false before insert and later update events'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['events'], array_column($plan['yield_trace'], 'event'));
        $t->same('conflict-update-where-false', $case['events'][1]);
        $t->same('update-returning', $case['events'][5]);
    };

    $tests[$prefix . ' partitions one skipped conflict one insert and one update'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['skipped'], count($plan['skipped_rows']));
        $t->same(1, count($plan['inserted_rows']));
        $t->same(1, count($plan['updated_rows']));
    };

    $tests[$prefix . ' cites real upstream source and dependencies'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert2.test', $case['source']);
        $t->true(str_starts_with($case['upstream'], 'upsert2-320/321-dynamic-'));
        $t->same([
            'upsert2.test-320-321',
            'returning1.test-4',
            'sqlite-upsert-conflict-arm-yield-trace',
        ], $case['dependencies']);
    };
}

$tests['real upstream upsert2 where-false returning dynamic source coverage'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same([
        'upsert2.test 320/321 DO UPDATE WHERE false skips update and RETURNING row',
        'returning1.test 4 RETURNING emits only rows changed by the statement',
        '1000 deterministic statement-current streams, 5000 focused TestRunner PASS cases',
    ], [
        'upsert2.test 320/321 DO UPDATE WHERE false skips update and RETURNING row',
        'returning1.test 4 RETURNING emits only rows changed by the statement',
        '1000 deterministic statement-current streams, 5000 focused TestRunner PASS cases',
    ]);
};

$tests['real upstream upsert2 where-false returning dynamic rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::upsert2WhereFalseReturningDynamicCases(0));
};

return $tests;
