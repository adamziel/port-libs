<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferrableFkSavepointPlan;

$tables = [
    'wp_posts' => [
        ['id' => 1, 'post_title' => 'Home'],
        ['id' => 2, 'post_title' => 'Plugin settings'],
    ],
    'wp_postmeta' => [
        ['meta_id' => 10, 'post_id' => 1, 'meta_key' => '_source'],
        ['meta_id' => 11, 'post_id' => 2, 'meta_key' => '_source'],
    ],
    'wp_import_audit' => [],
];

$foreignKeys = [
    ['name' => 'meta_post', 'parent_table' => 'wp_posts', 'parent_key' => 'id', 'child_table' => 'wp_postmeta', 'child_key' => 'post_id', 'deferred' => true],
    ['name' => 'audit_post', 'parent_table' => 'wp_posts', 'parent_key' => 'id', 'child_table' => 'wp_import_audit', 'child_key' => 'post_id', 'deferred' => false],
];

$plan = SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'wp_import'],
    ['operation' => 'insert', 'table' => 'wp_postmeta', 'trigger' => 'after_import_meta', 'row' => ['meta_id' => 20, 'post_id' => 99, 'meta_key' => '_import']],
    ['operation' => 'savepoint', 'name' => 'plugin_meta'],
    ['operation' => 'insert', 'table' => 'wp_postmeta', 'trigger' => 'after_plugin_meta', 'row' => ['meta_id' => 21, 'post_id' => 100, 'meta_key' => '_plugin']],
    ['operation' => 'rollback_to', 'name' => 'plugin_meta'],
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 99, 'post_title' => 'Imported page']],
    ['operation' => 'release', 'name' => 'plugin_meta'],
    ['operation' => 'release', 'name' => 'wp_import'],
], $foreignKeys);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'commit-ok');
    assert(array_column($plan['tables']['wp_postmeta'], 'meta_id') === [10, 11, 20]);
    assert(array_column($plan['deferred'], 'parent_key') === [99]);
    echo "application-trigger-deferrable-fk-savepoint-current-next36 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'post_ids' => array_column($plan['tables']['wp_posts'], 'id'),
    'meta_ids' => array_column($plan['tables']['wp_postmeta'], 'meta_id'),
    'deferred_parent_keys' => array_column($plan['deferred'], 'parent_key'),
    'events' => array_column($plan['events'], 'action'),
], JSON_PRETTY_PRINT) . "\n";
