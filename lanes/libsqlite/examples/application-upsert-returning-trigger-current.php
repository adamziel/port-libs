<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpsertDoUpdateWherePlan.php';
require_once __DIR__ . '/../src/SQLiteUpsertReturningTriggerPlan.php';

use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 5, 'touched' => 'old'],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 2, 'touched' => 'old'],
];

$assignments = [
    'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
    'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
];

$triggers = [[
    'name' => 'audit_option_update',
    'timing' => 'after',
    'event' => 'update',
    'table' => 'wp_options',
    'values' => ['name' => 'new.option_name', 'old_value' => 'old.option_value', 'new_value' => 'new.option_value'],
    'mutate_target' => true,
    'set' => ['touched' => 'after-trigger'],
], [
    'name' => 'audit_option_insert',
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'wp_options',
    'values' => ['name' => 'new.option_name', 'new_value' => 'new.option_value'],
]];

$result = SQLiteUpsertReturningTriggerPlan::execute(
    $rows,
    [
        ['option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 1, 'touched' => 'statement'],
        ['option_name' => 'new_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 1, 'touched' => 'statement'],
    ],
    ['option_name'],
    $assignments,
    $triggers,
    null,
    [['option_name']],
);

$payload = [
    'scenario' => 'application-upsert-returning-trigger-current',
    'applicationUse' => 'Preview copied wp_options UPSERT RETURNING rows alongside INSERT/UPDATE trigger audit effects, preserving SQLite current-row RETURNING values before AFTER-trigger target mutations without requiring ext/sqlite.',
    'returningNames' => array_column($result['returning_rows'], 'option_name'),
    'returningTouched' => array_column($result['returning_rows'], 'touched'),
    'afterTouched' => array_column($result['after'], 'touched'),
    'triggerNames' => array_column($result['trigger_effects'], 'trigger'),
    'changes' => $result['changes'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['returningNames'] === ['siteurl', 'new_plugin']);
    assert($payload['returningTouched'] === ['statement', 'statement']);
    assert($payload['afterTouched'][0] === 'after-trigger');
    assert($payload['triggerNames'] === ['audit_option_update', 'audit_option_insert']);
    assert($payload['changes'] === 2);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
