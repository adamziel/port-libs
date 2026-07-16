<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadePlan;

$optionParents = [
    ['site_id' => 1, 'option_name' => 'active_plugins', 'label' => 'site plugins'],
    ['site_id' => 1, 'option_name' => 'theme_mods', 'label' => 'site theme'],
    ['site_id' => 2, 'option_name' => 'active_plugins', 'label' => 'network plugins'],
];
$optionMeta = [
    ['meta_id' => 10, 'child_site_id' => 1, 'child_option_name' => 'active_plugins', 'payload' => 'plugin-a'],
    ['meta_id' => 11, 'child_site_id' => 1, 'child_option_name' => 'active_plugins', 'payload' => 'plugin-b'],
    ['meta_id' => 12, 'child_site_id' => 1, 'child_option_name' => 'theme_mods', 'payload' => 'theme'],
    ['meta_id' => 13, 'child_site_id' => null, 'child_option_name' => 'active_plugins', 'payload' => 'partial-null'],
];
$foreignKey = [
    'parent_key' => ['site_id', 'option_name'],
    'child_key' => ['child_site_id', 'child_option_name'],
    'on_delete' => 'RESTRICT',
    'deferred' => true,
];

$restrict = 'allowed';
try {
    SQLiteForeignKeyDeferredCascadePlan::deleteParents(
        $optionParents,
        $optionMeta,
        [['site_id' => 1, 'option_name' => 'active_plugins']],
        $foreignKey,
    );
} catch (InvalidArgumentException $exception) {
    $restrict = 'blocked-before-deferred-commit';
}

$cascadeForeignKey = $foreignKey;
$cascadeForeignKey['on_delete'] = 'CASCADE';
$cascade = SQLiteForeignKeyDeferredCascadePlan::deleteParents(
    $optionParents,
    $optionMeta,
    [['site_id' => 1, 'option_name' => 'active_plugins']],
    $cascadeForeignKey,
);

echo json_encode([
    'restrict_delete' => $restrict,
    'cascade_remaining_meta' => array_column($cascade['child'], 'meta_id'),
    'partial_null_child_preserved' => in_array(13, array_column($cascade['child'], 'meta_id'), true),
    'deferred_parent_key' => $cascade['deferred'][0]['parent_key'],
    'cascade_child_keys' => array_column($cascade['commit_actions'], 'child_key'),
    'changes' => $cascade['changes'],
], JSON_PRETTY_PRINT) . "\n";
