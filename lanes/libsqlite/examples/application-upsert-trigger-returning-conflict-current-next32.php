<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteUpsertTriggerForeignKeyYieldPlan.php';

use PortLibs\LibSqlite\SQLiteUpsertTriggerForeignKeyYieldPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'slug' => 'core-site', 'slot' => 'primary', 'autoload' => 'yes', 'option_value' => 'https://old.test', 'revision' => 5],
    ['option_id' => 2, 'option_name' => 'home', 'slug' => 'core-home', 'slot' => 'secondary', 'autoload' => 'yes', 'option_value' => 'https://home.test', 'revision' => 3],
];

$optionMeta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'slug' => static fn (array $old, array $incoming): mixed => $incoming['slug'],
    'slot' => static fn (array $old, array $incoming): mixed => $incoming['slot'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
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
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'slug', 'meta_value' => 'new.slug'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];

$plan = SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
    $options,
    $optionMeta,
    [
        ['option_id' => 101, 'option_name' => 'siteurl', 'slug' => 'core-site-next', 'slot' => 'primary-next', 'autoload' => 'manual', 'option_value' => 'https://new.test', 'revision' => 4],
        ['option_id' => 102, 'option_name' => 'fresh_plugin', 'slug' => 'plugin-fresh', 'slot' => 'plugin-slot', 'autoload' => 'plugin', 'option_value' => 'enabled', 'revision' => 1],
    ],
    ['option_name'],
    $assignments,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $triggers,
    null,
    [
        'option_name',
        'slug',
        'slot',
        ['expr' => 'new.revision', 'as' => 'statement_revision'],
    ],
    [['option_name'], ['slug'], ['slot'], ['autoload', 'slot']],
);

$conflict = null;
try {
    SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
        $options,
        $optionMeta,
        [
            ['option_id' => 103, 'option_name' => 'fresh_bad_slug', 'slug' => 'core-home', 'slot' => 'safe-slot', 'autoload' => 'safe-auto', 'option_value' => 'bad', 'revision' => 1],
        ],
        ['option_name'],
        $assignments,
        ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
        $triggers,
        null,
        ['option_name', 'slug'],
        [['option_name'], ['slug'], ['slot']],
    );
} catch (InvalidArgumentException $exception) {
    $conflict = $exception->getMessage();
}

echo json_encode([
    'changes' => $plan['changes'],
    'returning' => array_column($plan['yielded'], 'returning'),
    'finalOptionNames' => array_column($plan['parent'], 'option_name'),
    'finalSlugs' => array_column($plan['parent'], 'slug'),
    'childKeys' => array_column($plan['child'], 'option_id'),
    'currentUniqueConflict' => $conflict,
    'applicationUse' => 'Preview copied wp_options UPSERT RETURNING rows where trigger-updated rows are checked against statement-current UNIQUE option_name, slug, and slot values without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
