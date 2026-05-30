<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyCascadeTriggerPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'active_plugins'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules'],
];
$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'autoload'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'checksum'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'rewrite'],
];
$details = [
    ['detail_id' => 100, 'meta_id' => 10],
    ['detail_id' => 101, 'meta_id' => 11],
    ['detail_id' => 102, 'meta_id' => 12],
];

$result = SQLiteForeignKeyCascadeTriggerPlan::deleteParents(
    $options,
    $meta,
    $details,
    [['option_id' => 1]],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'CASCADE'],
    [[
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'insert-audit',
        'audit' => ['phase' => 'after-meta-cascade', 'meta_id' => 'old.meta_id', 'remaining_detail' => 'grandchild_count'],
    ]],
    ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_delete' => 'CASCADE'],
);

$summary = [
    'scenario' => 'application foreign-key cascade child-trigger current-source preview',
    'remaining_options' => array_column($result['parent'], 'option_name'),
    'remaining_meta_ids' => array_column($result['child'], 'meta_id'),
    'remaining_detail_ids' => array_column($result['grandchild'], 'detail_id'),
    'cascade_actions' => array_column($result['cascade_actions'], 'action'),
    'audit_meta_ids' => array_column($result['audit'], 'meta_id'),
    'changes' => $result['changes'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

return $summary;
