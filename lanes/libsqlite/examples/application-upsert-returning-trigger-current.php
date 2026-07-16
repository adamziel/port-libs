<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpsertDoUpdateWherePlan.php';
require_once __DIR__ . '/../src/SQLiteUpsertReturningTriggerPlan.php';

use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerPlan;

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 5, 'touched' => 'old'],
    ['key_name' => 'public_url', 'key_value' => 'https://public.test', 'load_policy' => 'yes', 'revision' => 2, 'touched' => 'old'],
];

$assignments = [
    'key_value' => static fn (array $current, array $excluded): mixed => $excluded['key_value'],
    'load_policy' => static fn (array $current, array $excluded): mixed => $excluded['load_policy'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
    'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
];

$triggers = [[
    'name' => 'audit_setting_update',
    'timing' => 'after',
    'event' => 'update',
    'table' => 'app_settings',
    'values' => ['name' => 'new.key_name', 'old_value' => 'old.key_value', 'new_value' => 'new.key_value'],
    'mutate_target' => true,
    'set' => ['touched' => 'after-trigger'],
], [
    'name' => 'audit_setting_insert',
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'app_settings',
    'values' => ['name' => 'new.key_name', 'new_value' => 'new.key_value'],
]];

$result = SQLiteUpsertReturningTriggerPlan::execute(
    $rows,
    [
        ['key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'revision' => 1, 'touched' => 'statement'],
        ['key_name' => 'new_module', 'key_value' => 'enabled', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'statement'],
    ],
    ['key_name'],
    $assignments,
    $triggers,
    null,
    [['key_name']],
);

$payload = [
    'scenario' => 'application-upsert-returning-trigger-current',
    'applicationUse' => 'Preview copied app_settings UPSERT RETURNING rows alongside INSERT/UPDATE trigger audit effects, preserving SQLite current-row RETURNING values before AFTER-trigger target mutations without requiring ext/sqlite.',
    'returningNames' => array_column($result['returning_rows'], 'key_name'),
    'returningTouched' => array_column($result['returning_rows'], 'touched'),
    'afterTouched' => array_column($result['after'], 'touched'),
    'triggerNames' => array_column($result['trigger_effects'], 'trigger'),
    'changes' => $result['changes'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['returningNames'] === ['base_url', 'new_module']);
    assert($payload['returningTouched'] === ['statement', 'statement']);
    assert($payload['afterTouched'][0] === 'after-trigger');
    assert($payload['triggerNames'] === ['audit_setting_update', 'audit_setting_insert']);
    assert($payload['changes'] === 2);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
