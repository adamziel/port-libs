<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyInteractionPlan;

$options = [
    ['option_id' => 1, 'option_name' => 'active_plugins'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules'],
];
$optionMeta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'plugin_a'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'plugin_b'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'rewrite_cache'],
];

$result = SQLiteTriggerForeignKeyInteractionPlan::deleteParents(
    $options,
    $optionMeta,
    [['option_id' => 1]],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'CASCADE'],
    [[
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'insert-audit',
        'audit' => ['phase' => 'before', 'name' => 'old.option_name', 'remaining' => 'child_count'],
    ], [
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'insert-audit',
        'audit' => ['phase' => 'after', 'name' => 'old.option_name', 'remaining' => 'child_count'],
    ]],
);

echo json_encode([
    'remaining_options' => array_column($result['parent'], 'option_name'),
    'remaining_meta' => array_column($result['child'], 'meta_key'),
    'audit' => $result['audit'],
    'foreign_key_actions' => array_column($result['foreign_key_actions'], 'action'),
], JSON_PRETTY_PRINT) . PHP_EOL;
