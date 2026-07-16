<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan;

$parents133 = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
    ['option_id' => 3, 'next_id' => null, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'revision' => 1],
];
$children133 = [
    ['meta_id' => 11, 'option_id' => 1],
    ['meta_id' => 12, 'option_id' => 2],
    ['meta_id' => 13, 'option_id' => 3],
];
$fk133 = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$currentStatement133 = [
    'savepoint' => 'wp_options_current_refresh',
    'current_source' => 'main@cookie-20',
    'next_source' => 'main@cookie-21',
    'where' => static fn (array $row): bool => $row['option_id'] === 1,
    'assignments' => [
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 10 + $depth,
        'option_value' => static fn (array $row, int $depth, string $source): string => (string) $row['option_value'] . '#' . $source . ':' . $depth,
    ],
    'returning' => [
        ['expr' => 'new.option_id', 'as' => 'id'],
        'option_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
        ['expr' => 'context.trigger_depth', 'as' => 'depth'],
        'revision',
    ],
    'trigger' => ['name' => 'wp_options_recursive_current_133', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];
$nextStatement133 = [
    'savepoint' => 'wp_options_next_refresh',
    'current_source' => 'main@cookie-21',
    'next_source' => 'main@cookie-22',
    'where' => static fn (array $row): bool => $row['option_id'] === 2,
    'assignments' => [
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 20 + $depth,
        'option_value' => static fn (array $row, int $depth, string $source): string => (string) $row['option_value'] . '#next:' . $depth,
    ],
    'returning' => [
        ['expr' => 'new.option_id', 'as' => 'id'],
        'option_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
        ['expr' => 'context.trigger_depth', 'as' => 'depth'],
        'revision',
    ],
    'trigger' => ['name' => 'wp_options_recursive_next_133', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];
$nextViolating133 = array_replace($nextStatement133, [
    'assignments' => [
        'option_id' => static fn (array $row, int $depth, string $source): int => (int) $row['option_id'] + 200 + $depth,
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 20 + $depth,
    ],
]);
$currentViolating133 = array_replace($currentStatement133, [
    'assignments' => [
        'option_id' => static fn (array $row, int $depth, string $source): int => (int) $row['option_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 10 + $depth,
    ],
]);

$commitPlan133 = static fn (): array => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, $currentStatement133, $nextStatement133);
$currentBlocked133 = static fn (): array => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, $currentViolating133, $nextStatement133);
$nextBlocked133 = static fn (): array => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, $currentStatement133, $nextViolating133);
$recursiveOff133 = static fn (): array => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, $currentStatement133 + ['recursive_triggers' => false], $nextStatement133 + ['recursive_triggers' => false]);

return [
    'trigger recursive returning fk current source next133 committed status' => static fn (TestRunner $t) => $t->same('next-source-committed', $commitPlan133()['status']),
    'trigger recursive returning fk current source next133 current source' => static fn (TestRunner $t) => $t->same('main@cookie-20', $commitPlan133()['current_source']),
    'trigger recursive returning fk current source next133 next source' => static fn (TestRunner $t) => $t->same('main@cookie-21', $commitPlan133()['next_source']),
    'trigger recursive returning fk current source next133 final source' => static fn (TestRunner $t) => $t->same('main@cookie-22', $commitPlan133()['final_source']),
    'trigger recursive returning fk current source next133 boundary' => static fn (TestRunner $t) => $t->same('current-fk-valid-next-source-returning-commit', $commitPlan133()['yield_boundary']),
    'trigger recursive returning fk current source next133 admits next source' => static fn (TestRunner $t) => $t->same(true, $commitPlan133()['next_source_admitted']),
    'trigger recursive returning fk current source next133 commits next source' => static fn (TestRunner $t) => $t->same(true, $commitPlan133()['next_source_committed']),
    'trigger recursive returning fk current source next133 unblocked marker' => static fn (TestRunner $t) => $t->same('none', $commitPlan133()['blocked_by']),
    'trigger recursive returning fk current source next133 current rowids stable' => static fn (TestRunner $t) => $t->same([1, 2, 3], $commitPlan133()['current_rowids']),
    'trigger recursive returning fk current source next133 next rowids stable' => static fn (TestRunner $t) => $t->same([1, 2, 3], $commitPlan133()['next_rowids']),
    'trigger recursive returning fk current source next133 final rowids stable' => static fn (TestRunner $t) => $t->same([1, 2, 3], $commitPlan133()['final_rowids']),
    'trigger recursive returning fk current source next133 current returning count' => static fn (TestRunner $t) => $t->same(1, count($commitPlan133()['current_returning_rows'])),
    'trigger recursive returning fk current source next133 current returning id' => static fn (TestRunner $t) => $t->same(1, $commitPlan133()['current_returning_rows'][0]['id']),
    'trigger recursive returning fk current source next133 current returning source token' => static fn (TestRunner $t) => $t->same('main@cookie-20', $commitPlan133()['current_returning_rows'][0]['source_token']),
    'trigger recursive returning fk current source next133 current returning depth' => static fn (TestRunner $t) => $t->same(0, $commitPlan133()['current_returning_rows'][0]['depth']),
    'trigger recursive returning fk current source next133 current returning revision' => static fn (TestRunner $t) => $t->same(11, $commitPlan133()['current_returning_rows'][0]['revision']),
    'trigger recursive returning fk current source next133 current trigger rows' => static fn (TestRunner $t) => $t->same(2, count($commitPlan133()['current_trigger_returning_rows'])),
    'trigger recursive returning fk current source next133 current trigger depths' => static fn (TestRunner $t) => $t->same([1, 2], array_column($commitPlan133()['current_trigger_returning_rows'], 'trigger_depth')),
    'trigger recursive returning fk current source next133 current trigger source names' => static fn (TestRunner $t) => $t->same(['wp_options_recursive_current_133', 'wp_options_recursive_current_133'], array_column($commitPlan133()['current_trigger_returning_rows'], 'trigger')),
    'trigger recursive returning fk current source next133 next attempted count' => static fn (TestRunner $t) => $t->same(1, count($commitPlan133()['attempted_next_returning_rows'])),
    'trigger recursive returning fk current source next133 next visible count' => static fn (TestRunner $t) => $t->same(1, count($commitPlan133()['next_returning_rows'])),
    'trigger recursive returning fk current source next133 next returning id' => static fn (TestRunner $t) => $t->same(2, $commitPlan133()['next_returning_rows'][0]['id']),
    'trigger recursive returning fk current source next133 next returning source token' => static fn (TestRunner $t) => $t->same('main@cookie-21', $commitPlan133()['next_returning_rows'][0]['source_token']),
    'trigger recursive returning fk current source next133 next returning depth' => static fn (TestRunner $t) => $t->same(0, $commitPlan133()['next_returning_rows'][0]['depth']),
    'trigger recursive returning fk current source next133 next returning revision' => static fn (TestRunner $t) => $t->same(32, $commitPlan133()['next_returning_rows'][0]['revision']),
    'trigger recursive returning fk current source next133 no current violations' => static fn (TestRunner $t) => $t->same([], $commitPlan133()['current_foreign_key_violations']),
    'trigger recursive returning fk current source next133 no next violations' => static fn (TestRunner $t) => $t->same([], $commitPlan133()['next_foreign_key_violations']),
    'trigger recursive returning fk current source next133 combined changes' => static fn (TestRunner $t) => $t->same(5, $commitPlan133()['combined_changes']),
    'trigger recursive returning fk current source next133 combined returning count' => static fn (TestRunner $t) => $t->same(2, $commitPlan133()['combined_returning_count']),
    'trigger recursive returning fk current source next133 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-current-source-fk-admission-before-next-source', $commitPlan133()['dependencies'], true)),
    'trigger recursive returning fk current source next133 current plan status' => static fn (TestRunner $t) => $t->same('commit-ok', $commitPlan133()['current_plan']['status']),
    'trigger recursive returning fk current source next133 next plan status' => static fn (TestRunner $t) => $t->same('commit-ok', $commitPlan133()['next_plan']['status']),
    'trigger recursive returning fk current source next133 current plan next source' => static fn (TestRunner $t) => $t->same('main@cookie-21', $commitPlan133()['current_plan']['next_source']),
    'trigger recursive returning fk current source next133 next plan final source' => static fn (TestRunner $t) => $t->same('main@cookie-22', $commitPlan133()['next_plan']['next_source']),
    'trigger recursive returning fk current source next133 current blocked status' => static fn (TestRunner $t) => $t->same('next-source-blocked', $currentBlocked133()['status']),
    'trigger recursive returning fk current source next133 current blocked cause' => static fn (TestRunner $t) => $t->same('current-deferred-fk-rollback', $currentBlocked133()['blocked_by']),
    'trigger recursive returning fk current source next133 current blocked no next plan' => static fn (TestRunner $t) => $t->same(null, $currentBlocked133()['next_plan']),
    'trigger recursive returning fk current source next133 current blocked admission false' => static fn (TestRunner $t) => $t->same(false, $currentBlocked133()['next_source_admitted']),
    'trigger recursive returning fk current source next133 current blocked commit false' => static fn (TestRunner $t) => $t->same(false, $currentBlocked133()['next_source_committed']),
    'trigger recursive returning fk current source next133 current blocked final source stays next' => static fn (TestRunner $t) => $t->same('main@cookie-21', $currentBlocked133()['final_source']),
    'trigger recursive returning fk current source next133 current blocked next returning empty' => static fn (TestRunner $t) => $t->same([], $currentBlocked133()['next_returning_rows']),
    'trigger recursive returning fk current source next133 current blocked attempted next empty' => static fn (TestRunner $t) => $t->same([], $currentBlocked133()['attempted_next_returning_rows']),
    'trigger recursive returning fk current source next133 current blocked violations count' => static fn (TestRunner $t) => $t->same(3, count($currentBlocked133()['current_foreign_key_violations'])),
    'trigger recursive returning fk current source next133 current blocked final rowids restored' => static fn (TestRunner $t) => $t->same([1, 2, 3], $currentBlocked133()['final_rowids']),
    'trigger recursive returning fk current source next133 current blocked changes zero' => static fn (TestRunner $t) => $t->same(0, $currentBlocked133()['combined_changes']),
    'trigger recursive returning fk current source next133 next blocked status' => static fn (TestRunner $t) => $t->same('next-source-blocked', $nextBlocked133()['status']),
    'trigger recursive returning fk current source next133 next blocked cause' => static fn (TestRunner $t) => $t->same('next-deferred-fk-rollback', $nextBlocked133()['blocked_by']),
    'trigger recursive returning fk current source next133 next blocked admission true' => static fn (TestRunner $t) => $t->same(true, $nextBlocked133()['next_source_admitted']),
    'trigger recursive returning fk current source next133 next blocked commit false' => static fn (TestRunner $t) => $t->same(false, $nextBlocked133()['next_source_committed']),
    'trigger recursive returning fk current source next133 next blocked attempted next visible before rollback' => static fn (TestRunner $t) => $t->same(1, count($nextBlocked133()['attempted_next_returning_rows'])),
    'trigger recursive returning fk current source next133 next blocked next returning suppressed' => static fn (TestRunner $t) => $t->same([], $nextBlocked133()['next_returning_rows']),
    'trigger recursive returning fk current source next133 next blocked violations count' => static fn (TestRunner $t) => $t->same(2, count($nextBlocked133()['next_foreign_key_violations'])),
    'trigger recursive returning fk current source next133 next blocked final rowids current commit' => static fn (TestRunner $t) => $t->same([1, 2, 3], $nextBlocked133()['final_rowids']),
    'trigger recursive returning fk current source next133 recursive off current trigger empty' => static fn (TestRunner $t) => $t->same([], $recursiveOff133()['current_trigger_returning_rows']),
    'trigger recursive returning fk current source next133 recursive off combined changes' => static fn (TestRunner $t) => $t->same(2, $recursiveOff133()['combined_changes']),
    'trigger recursive returning fk current source next133 bad source throws' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, array_replace($currentStatement133, ['current_source' => 'bad source']), $nextStatement133)),
    'trigger recursive returning fk current source next133 missing current where throws' => static function (TestRunner $t) use ($parents133, $children133, $fk133, $currentStatement133, $nextStatement133): void {
        $bad = $currentStatement133;
        unset($bad['where']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, $bad, $nextStatement133));
    },
    'trigger recursive returning fk current source next133 malformed next assignment throws after current admission' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($parents133, $children133, $fk133, $currentStatement133, array_replace($nextStatement133, ['assignments' => []]))),
];
