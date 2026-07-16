<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan.php';

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'active_plugins'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules'],
];
$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'autoload'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'checksum'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'rewrite'],
];
$details = [
    ['detail_id' => 100, 'meta_id' => 10, 'label' => 'autoload-before'],
    ['detail_id' => 101, 'meta_id' => 11, 'label' => 'checksum-before'],
    ['detail_id' => 102, 'meta_id' => 12, 'label' => 'rewrite-before'],
];

$summary = SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::updateParents(
    $parents,
    $meta,
    $details,
    [['option_id' => 1, 'new_option_id' => 101, 'option_name' => 'active_plugins_migrated']],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'CASCADE', 'deferred' => true],
    [[
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-audit',
        'audit' => ['phase' => 'after', 'old_option_id' => 'old.option_id', 'new_option_id' => 'new.option_id', 'meta_id' => 'new.meta_id', 'detail_count' => 'grandchild_count'],
    ]],
    ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_update' => 'CASCADE'],
    [[
        'operation' => 'insert',
        'table' => 'child',
        'row' => ['meta_id' => 20, 'option_id' => 1, 'meta_key' => 'late-current'],
    ]],
);

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if (
        array_column($summary['after_statement']['child'], 'option_id') !== [1, 1, 2]
        || array_column($summary['after_commit']['child'], 'option_id') !== [101, 101, 2, 101]
        || count($summary['audit']) !== 3
        || $summary['current_source_actions'][0]['action'] !== 'insert-current-child'
        || $summary['changes'] !== 10
    ) {
        fwrite(STDERR, "application-foreign-key-deferred-cascade-update-trigger-current-source-next116 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-foreign-key-deferred-cascade-update-trigger-current-source-next116 self-test passed\n");
}

return [
    'scenario' => 'application-foreign-key-deferred-cascade-update-trigger-current-source-next116',
    'applicationUse' => 'Preview a copied wp_options option_id migration where deferred ON UPDATE CASCADE children and child update triggers are applied at COMMIT against current-source rows inserted after the parent UPDATE statement.',
    'after_statement_child_option_ids' => array_column($summary['after_statement']['child'], 'option_id'),
    'after_commit_child_option_ids' => array_column($summary['after_commit']['child'], 'option_id'),
    'cascade_actions' => array_column($summary['cascade_actions'], 'action'),
    'audit_rows' => $summary['audit'],
    'dependencies' => $summary['dependencies'],
];
