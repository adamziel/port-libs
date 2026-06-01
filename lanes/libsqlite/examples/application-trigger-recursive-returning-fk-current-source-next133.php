<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'next_id' => 2, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1],
    ['setting_id' => 2, 'next_id' => null, 'key_name' => 'landing_page', 'key_value' => 'https://old.test/landing_page', 'revision' => 1],
];
$meta = [
    ['detail_id' => 1, 'setting_id' => 1],
    ['detail_id' => 2, 'setting_id' => 2],
];
$fk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true];

$current = [
    'savepoint' => 'app_settings_current_refresh',
    'current_source' => 'main@cookie-20',
    'next_source' => 'main@cookie-21',
    'where' => static fn (array $row): bool => $row['setting_id'] === 1,
    'assignments' => [
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 10 + $depth,
    ],
    'returning' => [
        ['expr' => 'new.setting_id', 'as' => 'id'],
        'key_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
    ],
    'trigger' => ['name' => 'app_settings_recursive_current_133', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];
$next = [
    'savepoint' => 'app_settings_next_refresh',
    'where' => static fn (array $row): bool => $row['setting_id'] === 2,
    'assignments' => [
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 20 + $depth,
    ],
    'returning' => [
        ['expr' => 'new.setting_id', 'as' => 'id'],
        'key_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
    ],
    'trigger' => ['name' => 'app_settings_recursive_next_133', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
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
