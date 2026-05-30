<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerBeforeCascadeSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'no'],
];
$optionMeta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'autoload'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'checksum'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'autoload'],
    ['meta_id' => 13, 'option_id' => null, 'meta_key' => 'loose'],
    ['meta_id' => 14, 'option_id' => 3, 'meta_key' => 'theme'],
];
$details = [
    ['detail_id' => 100, 'meta_id' => 10, 'label' => 'autoload-before'],
    ['detail_id' => 101, 'meta_id' => 10, 'label' => 'autoload-after'],
    ['detail_id' => 102, 'meta_id' => 11, 'label' => 'checksum-before'],
    ['detail_id' => 103, 'meta_id' => 12, 'label' => 'rewrite-before'],
    ['detail_id' => 104, 'meta_id' => null, 'label' => 'loose'],
    ['detail_id' => 105, 'meta_id' => 14, 'label' => 'theme'],
];

$optionToMeta = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'CASCADE'];
$metaToDetail = ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_delete' => 'CASCADE'];

$auditThenMove = [
    [
        'name' => 'wp_options_bd_audit',
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'insert-audit',
        'audit' => [
            'phase' => 'before',
            'option' => 'old.option_name',
            'child_count' => 'child_count',
            'detail_count' => 'grandchild_count',
        ],
    ],
    [
        'name' => 'wp_options_bd_rehome_meta',
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'update-child-key',
        'match' => 'old.parent_key',
        'set_child_key' => 2,
    ],
];

$guard = [[
    'name' => 'wp_options_bd_guard_required',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'raise',
    'raise' => 'rollback',
    'reason' => 'protected-option-before-cascade',
    'when' => ['old.option_name', '=', 'active_plugins'],
]];

$moved = SQLiteTriggerBeforeCascadeSavepointPlan::deleteParents(
    'wp_plugin_cleanup',
    $options,
    $optionMeta,
    $details,
    [['option_id' => 1]],
    $optionToMeta,
    $auditThenMove,
    $metaToDetail,
);

$rolledBack = SQLiteTriggerBeforeCascadeSavepointPlan::deleteParents(
    'wp_plugin_cleanup',
    $options,
    $optionMeta,
    $details,
    [['option_id' => 1]],
    $optionToMeta,
    $guard,
    $metaToDetail,
);

$report = [
    'movedBeforeCascade' => [
        'remainingOptions' => array_column($moved['current_parent'], 'option_name'),
        'remainingMetaKeys' => array_column($moved['current_child'], 'option_id'),
        'cascadeActions' => array_column($moved['cascade_actions'], 'action'),
        'audit' => $moved['audit'],
        'changes' => $moved['changes'],
    ],
    'rollbackBeforeCascade' => [
        'rolledBack' => $rolledBack['rolled_back'],
        'rollbackScope' => $rolledBack['rollback_scope'],
        'rollbackReason' => $rolledBack['rollback_reason'],
        'currentOptions' => array_column($rolledBack['current_parent'], 'option_name'),
        'savepointPreserved' => $rolledBack['savepoint_preserved'],
        'changes' => $rolledBack['changes'],
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($report['movedBeforeCascade']['remainingOptions'] === ['rewrite_rules', 'theme_mods']);
    assert($report['movedBeforeCascade']['remainingMetaKeys'] === [2, 2, 2, null, 3]);
    assert($report['movedBeforeCascade']['cascadeActions'] === []);
    assert($report['movedBeforeCascade']['audit'][0]['child_count'] === 5);
    assert($report['rollbackBeforeCascade']['rolledBack'] === true);
    assert($report['rollbackBeforeCascade']['rollbackScope'] === 'savepoint');
    assert($report['rollbackBeforeCascade']['currentOptions'] === ['active_plugins', 'rewrite_rules', 'theme_mods']);
    assert($report['rollbackBeforeCascade']['savepointPreserved'] === true);
    echo "application-trigger-before-cascade-savepoint-current-next35 self-test passed\n";
    return;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
