<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteForeignKeyOnUpdateCascadePlan;

$optionGroups = [
    ['id' => 1, 'name' => 'autoloaded'],
    ['id' => 2, 'name' => 'manual'],
];
$optionRows = [
    ['option_id' => 101, 'group_id' => 1, 'option_name' => 'siteurl'],
    ['option_id' => 102, 'group_id' => 1, 'option_name' => 'home'],
    ['option_id' => 103, 'group_id' => 2, 'option_name' => 'blogname'],
];

$result = SQLiteForeignKeyOnUpdateCascadePlan::updateParents(
    $optionGroups,
    $optionRows,
    [['old' => 1, 'new' => 10]],
    ['parent_key' => 'id', 'child_key' => 'group_id', 'on_update' => 'CASCADE', 'deferred' => true],
);

echo json_encode([
    'groups' => array_column($result['parent'], 'id'),
    'option_group_ids' => array_column($result['child'], 'group_id'),
    'deferred_actions' => count($result['deferred']),
    'commit_actions' => array_column($result['commit_actions'], 'action'),
    'changes' => $result['changes'],
], JSON_PRETTY_PRINT) . "\n";
