<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteUpsertTriggerForeignKeyYieldPlan.php';

use PortLibs\LibSqlite\SQLiteUpsertTriggerForeignKeyYieldPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];

$optionMeta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];

$triggers = [
    [
        'name' => 'wp_options_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];

$plan = SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
    $options,
    $optionMeta,
    [
        ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'],
        ['option_id' => 20, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no'],
    ],
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $triggers,
);

echo json_encode([
    'changes' => $plan['changes'],
    'parentKeys' => array_column($plan['parent'], 'option_id'),
    'childKeys' => array_column($plan['child'], 'option_id'),
    'yielded' => $plan['yielded'],
    'triggerEffects' => array_column($plan['trigger_effects'], 'trigger'),
    'foreignKeyViolations' => $plan['foreign_key_violations'],
    'applicationUse' => 'Preview copied wp_options UPSERT batches that yield after each row while BEFORE/AFTER triggers rekey wp_optionmeta children and deferred foreign-key checks track transient or final violations without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
