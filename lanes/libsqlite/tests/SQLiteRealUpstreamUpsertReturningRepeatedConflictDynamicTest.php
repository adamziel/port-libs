<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

foreach (SQLiteUpsertReturningDynamicCorpusPlan::upsert2RepeatedConflictReturningDynamicCases(1000) as $case) {
    $plan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $case['before'],
        $case['incoming'],
        $case['arms'],
        $case['constraints'],
    );

    $prefix = 'real upstream ' . $case['upstream'] . ' ';

    $tests[$prefix . 'preserves current row image across repeated conflicts'] = static function (TestRunner $t) use ($plan, $case): void {
        $t->same($case['after'], $plan()['after']);
    };

    $tests[$prefix . 'streams RETURNING rows only for insert and successful updates'] = static function (TestRunner $t) use ($plan, $case): void {
        $t->same($case['returning'], $plan()['returning_rows']);
    };

    $tests[$prefix . 'records before-insert and changed-row yield events in statement order'] = static function (TestRunner $t) use ($plan, $case): void {
        $t->same($case['events'], array_column($plan()['yield_trace'], 'event'));
    };

    $tests[$prefix . 'counts insert plus successful conflict updates but not failed WHERE arm'] = static function (TestRunner $t) use ($plan, $case): void {
        $result = $plan();

        $t->same($case['changes'], $result['changes']);
        $t->same($case['skipped'], count($result['skipped_rows']));
    };

    $tests[$prefix . 'matches the same conflict target for every conflicting input row'] = static function (TestRunner $t) use ($plan, $case): void {
        $matched = array_map(
            static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
            $plan()['matched_arms'],
        );

        $t->same($case['matched'], $matched);
    };
}

return $tests;
