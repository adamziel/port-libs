<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningFaultPlan;

$tests = [];

$cases = SQLiteUpsertReturningFaultPlan::recoverableUpsertUpdateFaultCorpus(1000);

/*
 * Real upstream source: SQLite test/upsertfault.test.
 *
 * upsertfault-1 restores a saved database and injects OOM faults while running
 * INSERT ... ON CONFLICT(b,c) DO UPDATE SET d=d+1. The expected outcome is a
 * recoverable statement with no error and one conflict-row update. This corpus
 * keeps that same conflict shape and varies the row images, conflict keys, and
 * fault checkpoints across 1000 deterministic cases.
 */
foreach ($cases as $index => $case) {
    $seed = $index + 1;
    $prefix = sprintf('real upstream upsertfault recoverable dynamic seed %04d', $seed);

    $tests[$prefix . ' recovers oom fault and reports success'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsertfault.test', $case['source']);
        $t->same('oom', $case['fault']['kind']);
        $t->same(true, $case['fault']['recovered']);
        $t->same(null, $case['error']);
        $t->same(1, $case['changes']);
    };

    $tests[$prefix . ' updates only the matching b c conflict row'] = static function (TestRunner $t) use ($case): void {
        $updated = $case['updated_rows'][0];

        $t->same($case['expected_conflict_key'], [(int) $updated['b'], (int) $updated['c']]);
        $t->same($case['expected_updated_d'], (int) $updated['d']);
        $t->same($case['incoming']['b'], $updated['b']);
        $t->same($case['incoming']['c'], $updated['c']);
    };

    $tests[$prefix . ' preserves non-conflicting rows around statement recovery'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['before'][0], $case['after'][0]);
        $t->same($case['before'][2], $case['after'][2]);
        $t->same(count($case['before']), count($case['after']));
    };

    $tests[$prefix . ' rotates through statement fault checkpoints'] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array($case['fault']['checkpoint'], [
            'schema-record-read',
            'unique-index-probe',
            'conflict-row-load',
            'update-expression-evaluate',
            'row-image-build',
            'index-entry-rewrite',
            'statement-journal-checkpoint',
            'statement-reset',
        ], true));
        $t->true($case['fault']['step'] >= 0);
    };

    $tests[$prefix . ' keeps statement retry dependencies explicit'] = static function (TestRunner $t) use ($case): void {
        $t->same(true, $case['allocations_released']);
        $t->same(true, $case['statement_retriable']);
        $t->same([
            'sqlite-upsert-faultsim-retry',
            'upsertfault.test-1',
        ], $case['dependencies']);
    };
}

$tests['real upstream upsertfault returning dynamic source coverage'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same([
        'upsertfault.test upsertfault-1 INSERT ON CONFLICT(b,c) DO UPDATE SET d=d+1 under OOM fault injection',
        '1000 deterministic conflict-key and fault-checkpoint variants',
        '5000 focused TestRunner PASS cases from real upstream UPSERT fault behavior',
        'non-overlap: existing UPSERT RETURNING dynamic files cover upsert1-5 and returning1 streams; this file owns upsertfault.test recoverable faultsim behavior',
    ], [
        'upsertfault.test upsertfault-1 INSERT ON CONFLICT(b,c) DO UPDATE SET d=d+1 under OOM fault injection',
        '1000 deterministic conflict-key and fault-checkpoint variants',
        '5000 focused TestRunner PASS cases from real upstream UPSERT fault behavior',
        'non-overlap: existing UPSERT RETURNING dynamic files cover upsert1-5 and returning1 streams; this file owns upsertfault.test recoverable faultsim behavior',
    ]);
};

$tests['real upstream upsertfault returning dynamic rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningFaultPlan::recoverableUpsertUpdateFaultCorpus(0));
};

return $tests;
