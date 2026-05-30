<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyCascadeUpdateTriggerPlan;

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
$detail = [
    ['detail_id' => 100, 'option_id' => 1, 'label' => 'autoload'],
    ['detail_id' => 101, 'option_id' => 1, 'label' => 'checksum'],
    ['detail_id' => 102, 'option_id' => 2, 'label' => 'rewrite'],
];

$result = SQLiteForeignKeyCascadeUpdateTriggerPlan::updateParentKeys(
    $options,
    $meta,
    $detail,
    [['option_id' => 1, 'new_option_id' => 20]],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'CASCADE'],
    [[
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-audit',
        'audit' => ['phase' => 'after-meta-cascade', 'meta_id' => 'new.meta_id', 'old_option_id' => 'old.option_id', 'new_option_id' => 'new.option_id'],
    ]],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'CASCADE'],
);

$summary = [
    'scenario' => 'application foreign-key cascade update child-trigger current-next29 preview',
    'updated_options' => array_column($result['parent'], 'option_id'),
    'updated_meta_option_ids' => array_column($result['child'], 'option_id'),
    'updated_detail_option_ids' => array_column($result['grandchild'], 'option_id'),
    'cascade_actions' => array_column($result['cascade_actions'], 'action'),
    'audit_new_option_ids' => array_column($result['audit'], 'new_option_id'),
    'changes' => $result['changes'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

return $summary;
