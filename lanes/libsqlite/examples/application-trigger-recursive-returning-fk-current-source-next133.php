<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => null, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
];
$meta = [
    ['meta_id' => 1, 'option_id' => 1],
    ['meta_id' => 2, 'option_id' => 2],
];
$fk = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];

$current = [
    'savepoint' => 'wp_options_current_refresh',
    'current_source' => 'main@cookie-20',
    'next_source' => 'main@cookie-21',
    'where' => static fn (array $row): bool => $row['option_id'] === 1,
    'assignments' => [
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 10 + $depth,
    ],
    'returning' => [
        ['expr' => 'new.option_id', 'as' => 'id'],
        'option_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
    ],
    'trigger' => ['name' => 'wp_options_recursive_current_133', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];
$next = [
    'savepoint' => 'wp_options_next_refresh',
    'where' => static fn (array $row): bool => $row['option_id'] === 2,
    'assignments' => [
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 20 + $depth,
    ],
    'returning' => [
        ['expr' => 'new.option_id', 'as' => 'id'],
        'option_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
    ],
    'trigger' => ['name' => 'wp_options_recursive_next_133', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];

$plan = SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan::handoff($rows, $meta, $fk, $current, $next);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'next-source-committed');
    assert($plan['current_returning_rows'][0]['source_token'] === 'main@cookie-20');
    assert($plan['next_returning_rows'][0]['source_token'] === 'main@cookie-21');
    assert($plan['combined_changes'] === 3);
    echo "application-trigger-recursive-returning-fk-current-source-next133 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentSource' => $plan['current_source'],
    'nextSource' => $plan['next_source'],
    'finalSource' => $plan['final_source'],
    'currentReturning' => $plan['current_returning_rows'],
    'nextReturning' => $plan['next_returning_rows'],
    'blockedBy' => $plan['blocked_by'],
], JSON_PRETTY_PRINT) . PHP_EOL;
