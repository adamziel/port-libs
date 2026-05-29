<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 5],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 3],
];

$triggers = [
    [
        'name' => 'wp_options_bi_siteurl_alias',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'siteurl_alias'],
        'set' => ['option_name' => 'siteurl', 'option_id' => 101],
        'values' => ['name' => 'new.option_name', 'id' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_bd_replace_audit',
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'delete-side',
        'values' => ['deleted_name' => 'old.option_name', 'deleted_id' => 'old.option_id'],
    ],
    [
        'name' => 'wp_options_ai_touch',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'set-new',
        'set' => ['option_value' => 'after-trigger-touch'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
];

$plan = SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan::insertRows(
    $options,
    [
        ['option_id' => 10, 'option_name' => 'siteurl_alias', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 1],
        ['option_id' => 20, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 1],
        ['option_id' => 21, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled-2', 'autoload' => 'yes', 'revision' => 2],
    ],
    ['option_name'],
    $triggers,
    'replace',
    ['option_id', 'option_name', 'option_value'],
);

if (
    array_column($plan['returning_rows'], 'option_name') !== ['siteurl', 'fresh_plugin', 'fresh_plugin']
    || array_column($plan['deleted'], 'option_name') !== ['siteurl', 'fresh_plugin']
) {
    fwrite(STDERR, "wordpress-dml-trigger-returning-conflict-current-source-next106 self-test failed\n");
    exit(1);
}

echo "wordpress-dml-trigger-returning-conflict-current-source-next106 self-test passed\n";
echo json_encode([
    'scenario' => 'wordpress-dml-trigger-returning-conflict-current-source-next106',
    'wordpressUse' => 'Preview copied wp_options INSERT OR REPLACE RETURNING where BEFORE triggers retarget incoming rows, current-source conflicts delete old rows before insert, and RETURNING preserves the statement insert image before AFTER-trigger touches.',
    'changes' => $plan['changes'],
    'returningNames' => array_column($plan['returning_rows'], 'option_name'),
    'returningValues' => array_column($plan['returning_rows'], 'option_value'),
    'finalValues' => array_column($plan['rows'], 'option_value'),
    'deletedNames' => array_column($plan['deleted'], 'option_name'),
    'triggerEffects' => array_column($plan['trigger_effects'], 'trigger'),
    'dependency' => $plan['dependencies'][0],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
