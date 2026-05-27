<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadePlan;

$optionGroups = [
    ['id' => 1, 'name' => 'autoloaded'],
    ['id' => 2, 'name' => 'manual'],
];
$optionRows = [
    ['option_id' => 101, 'group_id' => 1, 'option_name' => 'siteurl'],
    ['option_id' => 102, 'group_id' => 1, 'option_name' => 'home'],
    ['option_id' => 103, 'group_id' => 2, 'option_name' => 'blogname'],
];

$result = SQLiteForeignKeyDeferredCascadePlan::deleteParents(
    $optionGroups,
    $optionRows,
    [['id' => 1]],
    ['parent_key' => 'id', 'child_key' => 'group_id', 'on_delete' => 'CASCADE', 'deferred' => true],
);

echo json_encode([
    'remaining_groups' => array_column($result['parent'], 'name'),
    'remaining_options' => array_column($result['child'], 'option_name'),
    'deferred_actions' => count($result['deferred']),
    'commit_actions' => array_column($result['commit_actions'], 'action'),
    'changes' => $result['changes'],
], JSON_PRETTY_PRINT) . "\n";
