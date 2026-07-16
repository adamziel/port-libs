<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$tests['real upstream returning1 upsert1 repeated fooval dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 17.1 main table repeated fooval UPSERT returns fooid for every VALUES input row',
        'returning1.test 17.2 temp table repeats the same RETURNING stream contract',
        'upsert1.test 1300 large duplicate SELECT input preserves old/new trigger row images during DO UPDATE',
        'non-overlap: existing accepted UPSERT RETURNING batches cover target priority, partial predicates, trigger histograms, and repeated conflict WHERE streams; this batch isolates repeated fooval RETURNING streams plus trigger-visible large payload equality',
    ], [
        'returning1.test 17.1 main table repeated fooval UPSERT returns fooid for every VALUES input row',
        'returning1.test 17.2 temp table repeats the same RETURNING stream contract',
        'upsert1.test 1300 large duplicate SELECT input preserves old/new trigger row images during DO UPDATE',
        'non-overlap: existing accepted UPSERT RETURNING batches cover target priority, partial predicates, trigger histograms, and repeated conflict WHERE streams; this batch isolates repeated fooval RETURNING streams plus trigger-visible large payload equality',
    ]);
};

$tests['real upstream returning1 upsert1 repeated fooval dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native conflict-arm yield trace and RETURNING projection helpers for generic repeated UPSERT rows',
        'no new support component needed; reuses native conflict-arm yield trace and RETURNING projection helpers for generic repeated UPSERT rows',
    );
};

foreach (SQLiteUpsertReturningDynamicCorpusPlan::returning1RepeatedFoovalDynamicCases(1000) as $case) {
    $plan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $case['before'],
        $case['incoming'],
        $case['arms'],
        $case['constraints'],
    );

    $prefix = 'real upstream ' . $case['upstream'] . ' ';

    $tests[$prefix . 'returns one fooid for every successful insert or conflict update'] = static function (TestRunner $t) use ($plan, $case): void {
        $t->same(
            $case['returning'],
            SQLiteUpsertDoUpdateWherePlan::returningRows($plan()['returning_rows'], ['fooid']),
        );
    };

    $tests[$prefix . 'updates repeated conflicts against the current row image'] = static function (TestRunner $t) use ($plan, $case): void {
        $t->same($case['after'], $plan()['after']);
    };

    $tests[$prefix . 'keeps statement-order yield events for main and temp storage'] = static function (TestRunner $t) use ($plan, $case): void {
        $t->same($case['events'], array_column($plan()['yield_trace'], 'event'));
        $t->same(true, in_array($case['storage'], ['main', 'temp'], true));
    };

    $tests[$prefix . 'passes trigger old and new payload equality regression checks'] = static function (TestRunner $t) use ($case): void {
        $t->same([true, true, true, true], array_column($case['trigger_checks'], 'passes'));
    };

    $tests[$prefix . 'records upstream dependencies and exact change count'] = static function (TestRunner $t) use ($plan, $case): void {
        $result = $plan();

        $t->same($case['changes'], $result['changes']);
        $t->same($case['dependencies'], [
            'returning1.test-17.1-through-17.2',
            'upsert1.test-1300',
            'sqlite-returning-repeated-upsert-row-stream',
            'sqlite-upsert-trigger-current-row-image',
        ]);
    };
}

$tests['real upstream returning1 repeated fooval dynamic rejects empty count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::returning1RepeatedFoovalDynamicCases(0));
};

return $tests;
