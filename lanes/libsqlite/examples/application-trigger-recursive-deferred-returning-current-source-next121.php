<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'next_id' => 2, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1],
    ['setting_id' => 2, 'next_id' => null, 'key_name' => 'landing_page', 'key_value' => 'https://old.test/landing_page', 'revision' => 1],
];
$meta = [
    ['detail_id' => 1, 'setting_id' => 1, 'detail_key' => '_origin'],
    ['detail_id' => 2, 'setting_id' => 2, 'detail_key' => '_origin'],
];

$plan = SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($rows, $meta, [
    'parent_key' => 'setting_id',
    'child_key' => 'setting_id',
    'deferred' => true,
], [
    'savepoint' => 'app_settings_refresh',
    'current_source' => 'main@schema-cookie-9',
    'next_source' => 'main@schema-cookie-10',
    'where' => static fn (array $row): bool => $row['key_name'] === 'base_url',
    'assignments' => [
        'setting_id' => static fn (array $row, int $depth, string $source): int => (int) $row['setting_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 1 + $depth,
    ],
    'returning' => [
        ['expr' => 'old.setting_id', 'as' => 'old_id'],
        ['expr' => 'new.setting_id', 'as' => 'new_id'],
        ['expr' => 'context.source', 'as' => 'source_token'],
        'key_name',
    ],
    'trigger' => ['name' => 'app_settings_recursive_rekey', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rolled-back');
    assert($plan['current_returning_rows'][0]['source_token'] === 'main@schema-cookie-9');
    assert($plan['next_returning_rows'] === []);
    assert($plan['next_rowids'] === [1, 2]);
    echo "application-trigger-recursive-deferred-returning-current-source-next121 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentSource' => $plan['current_source'],
    'nextSource' => $plan['next_source'],
    'currentReturningRows' => $plan['current_returning_rows'],
    'nextReturningRows' => $plan['next_returning_rows'],
    'triggerReturningRows' => count($plan['trigger_returning_rows']),
], JSON_PRETTY_PRINT) . PHP_EOL;
