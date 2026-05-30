<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteUpsertTriggerForeignKeyYieldPlan.php';

use PortLibs\LibSqlite\SQLiteUpsertTriggerForeignKeyYieldPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 5],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 3],
];

$optionMeta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
];

$triggers = [
    [
        'name' => 'wp_options_bu_meta_rekey',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_au_touch',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['option_value' => 'after-trigger-touch', 'revision' => 999],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_bi_plugin_alias',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'fresh_plugin'],
        'set' => ['option_id' => 20],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
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
        ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 4],
        ['option_id' => 102, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 1],
        ['option_id' => 103, 'option_name' => 'home', 'option_value' => 'skip', 'autoload' => 'skip', 'revision' => 1],
    ],
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $triggers,
    static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip',
    [
        'option_id',
        'option_name',
        'option_value',
        ['expr' => 'excluded.option_id', 'as' => 'excluded_option_id'],
        ['expr' => 'new.revision', 'as' => 'statement_revision'],
    ],
);

echo json_encode([
    'changes' => $plan['changes'],
    'returning' => array_column($plan['yielded'], 'returning'),
    'yieldedStatuses' => array_column($plan['yielded'], 'status'),
    'finalOptionValues' => array_column($plan['parent'], 'option_value'),
    'childKeys' => array_column($plan['child'], 'option_id'),
    'triggerEffects' => array_column($plan['trigger_effects'], 'trigger'),
    'applicationUse' => 'Preview copied wp_options UPSERT RETURNING rows captured before AFTER-trigger target mutations while triggers maintain wp_optionmeta child keys without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
