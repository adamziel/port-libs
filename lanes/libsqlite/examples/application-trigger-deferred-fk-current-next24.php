<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredForeignKeyPlan;

$tables = [
    'wp_posts' => [
        ['id' => 1, 'post_title' => 'Imported plugin settings'],
    ],
    'wp_postmeta' => [
        ['meta_id' => 10, 'post_id' => 1, 'meta_key' => '_source'],
    ],
    'wp_import_audit' => [],
];
$foreignKeys = [
    [
        'name' => 'audit_post',
        'parent_table' => 'wp_posts',
        'parent_key' => 'id',
        'child_table' => 'wp_import_audit',
        'child_key' => 'post_id',
        'on_delete' => 'NO ACTION',
        'deferred' => true,
    ],
];

$plan = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [
    [
        'operation' => 'insert',
        'table' => 'wp_import_audit',
        'trigger' => 'after_post_import',
        'row' => ['audit_id' => 100, 'post_id' => 2, 'message' => 'audit row waits for imported post'],
    ],
    [
        'operation' => 'insert',
        'table' => 'wp_posts',
        'row' => ['id' => 2, 'post_title' => 'Deferred imported page'],
    ],
], $foreignKeys);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['commit_status'] !== 'commit-ok' || $plan['violations'] !== [] || count($plan['deferred']) !== 1) {
        fwrite(STDERR, "application-trigger-deferred-fk-current-next24 self-test failed\n");
        exit(1);
    }
    echo "application-trigger-deferred-fk-current-next24 self-test passed\n";
    exit(0);
}

echo json_encode([
    'commit_status' => $plan['commit_status'],
    'trigger_events' => array_column($plan['events'], 'action'),
    'deferred_checks' => count($plan['deferred']),
    'violations' => count($plan['violations']),
    'changes' => $plan['changes'],
], JSON_PRETTY_PRINT) . "\n";
