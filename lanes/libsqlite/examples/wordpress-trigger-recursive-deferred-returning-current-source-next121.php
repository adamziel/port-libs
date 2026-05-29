<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => null, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
];
$meta = [
    ['meta_id' => 1, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 2, 'option_id' => 2, 'meta_key' => '_origin'],
];

$plan = SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($rows, $meta, [
    'parent_key' => 'option_id',
    'child_key' => 'option_id',
    'deferred' => true,
], [
    'savepoint' => 'wp_options_refresh',
    'current_source' => 'main@schema-cookie-9',
    'next_source' => 'main@schema-cookie-10',
    'where' => static fn (array $row): bool => $row['option_name'] === 'siteurl',
    'assignments' => [
        'option_id' => static fn (array $row, int $depth, string $source): int => (int) $row['option_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 1 + $depth,
    ],
    'returning' => [
        ['expr' => 'old.option_id', 'as' => 'old_id'],
        ['expr' => 'new.option_id', 'as' => 'new_id'],
        ['expr' => 'context.source', 'as' => 'source_token'],
        'option_name',
    ],
    'trigger' => ['name' => 'wp_options_recursive_rekey', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rolled-back');
    assert($plan['current_returning_rows'][0]['source_token'] === 'main@schema-cookie-9');
    assert($plan['next_returning_rows'] === []);
    assert($plan['next_rowids'] === [1, 2]);
    echo "wordpress-trigger-recursive-deferred-returning-current-source-next121 self-test passed\n";
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
