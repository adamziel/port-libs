<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpsertTriggerForeignKeyYieldPlan.php';
require_once __DIR__ . '/../src/SQLiteUpsertReturningSavepointPlan.php';

use PortLibs\LibSqlite\SQLiteUpsertReturningSavepointPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$plan = SQLiteUpsertReturningSavepointPlan::execute(
    $parents,
    $children,
    [
        ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'],
        ['option_id' => 102, 'option_name' => 'home', 'option_value' => 'https://bad.test', 'autoload' => 'yes'],
    ],
    ['option_name'],
    [
        'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
        'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
        'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    ],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => false],
    [[
        'name' => 'wp_options_bu_bad_home_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'when' => ['new.option_name', '=', 'home'],
        'match' => 'old.option_id',
        'set_child_key' => 999,
    ]],
    null,
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_id', 'as' => 'id'],
        ['expr' => 'old.option_id', 'as' => 'old_id'],
    ],
    'wp_import_current',
);

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'rolled_back' => $plan['rolled_back'],
    'rollback_ordinal' => $plan['rolled_back_at_ordinal'],
    'returning_rows' => $plan['returning_rows'],
    'parent_ids_after' => array_column($plan['parent'], 'option_id'),
], JSON_PRETTY_PRINT) . PHP_EOL;
