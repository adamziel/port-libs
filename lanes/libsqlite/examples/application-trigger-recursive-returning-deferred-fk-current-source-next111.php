<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => null, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
];
$meta = [
    ['meta_id' => 1, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 2, 'option_id' => 2, 'meta_key' => '_origin'],
];

$plan = SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($rows, $meta, [
    'parent_key' => 'option_id',
    'child_key' => 'option_id',
    'on_update' => 'no action',
    'deferred' => true,
], [
    'savepoint' => 'wp_options_import',
    'where' => static fn (array $row): bool => $row['option_name'] === 'siteurl',
    'assignments' => [
        'option_id' => static fn (array $row, int $depth): int => (int) $row['option_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth): int => (int) $row['revision'] + 1 + $depth,
    ],
    'returning' => [['expr' => 'old.option_id', 'as' => 'old_id'], ['expr' => 'new.option_id', 'as' => 'new_id'], 'option_name'],
    'trigger' => ['name' => 'wp_options_au_recursive_rekey', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
]);

echo json_encode([
    'status' => $plan['status'],
    'currentRowids' => $plan['current_rowids'],
    'nextRowids' => $plan['next_rowids'],
    'currentReturningRows' => $plan['current_returning_rows'],
    'nextReturningRows' => $plan['next_returning_rows'],
    'recursiveEffects' => count($plan['trigger_effects']),
], JSON_PRETTY_PRINT) . PHP_EOL;
