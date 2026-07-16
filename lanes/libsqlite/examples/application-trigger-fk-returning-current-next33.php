<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerForeignKeyReturningPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 2],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'plugin_cache', 'option_value' => 'stale', 'autoload' => 'no', 'revision' => 5],
];

$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'plugin'],
];

$plan = SQLiteTriggerForeignKeyReturningPlan::updateParents(
    $parents,
    $meta,
    [
        'option_id' => static fn (array $old): int => (int) $old['option_id'] + 100,
        'option_value' => static fn (array $old): string => $old['option_name'] . ':migrated',
        'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
    ],
    static fn (array $row): bool => $row['autoload'] === 'yes',
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'cascade'],
    [
        [
            'name' => 'wp_options_au_returning_audit',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'insert-child',
            'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'returning_seen', 'meta_value' => 'new.option_value'],
            'values' => ['key' => 'new.option_id', 'value' => 'new.option_value'],
        ],
    ],
    ['option_id', 'option_name', 'option_value', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options UPDATE RETURNING rows while trigger audit rows and foreign-key cascades update copied wp_optionmeta child keys without requiring ext/sqlite.',
    'returning' => $plan['yielded'],
    'childKeys' => array_column($plan['child'], 'option_id'),
    'triggerEffects' => array_column($plan['trigger_effects'], 'trigger'),
    'foreignKeyActions' => $plan['foreign_key_actions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
