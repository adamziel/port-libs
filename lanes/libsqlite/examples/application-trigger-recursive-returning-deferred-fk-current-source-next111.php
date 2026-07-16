<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'next_id' => 2, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1],
    ['setting_id' => 2, 'next_id' => null, 'key_name' => 'public_url', 'key_value' => 'https://old.test/public_url', 'revision' => 1],
];
$meta = [
    ['meta_id' => 1, 'setting_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 2, 'setting_id' => 2, 'meta_key' => '_origin'],
];

$plan = SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($rows, $meta, [
    'parent_key' => 'setting_id',
    'child_key' => 'setting_id',
    'on_update' => 'no action',
    'deferred' => true,
], [
    'savepoint' => 'app_settings_import',
    'where' => static fn (array $row): bool => $row['key_name'] === 'base_url',
    'assignments' => [
        'setting_id' => static fn (array $row, int $depth): int => (int) $row['setting_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth): int => (int) $row['revision'] + 1 + $depth,
    ],
    'returning' => [['expr' => 'old.setting_id', 'as' => 'old_id'], ['expr' => 'new.setting_id', 'as' => 'new_id'], 'key_name'],
    'trigger' => ['name' => 'app_settings_au_recursive_rekey', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
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
