<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$options = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 6, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 7, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];
$targets = [
    ['target_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'yield'],
    ['target_id' => 2, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'action' => 'yield'],
    ['target_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'action' => 'delete_yield'],
    ['target_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'attempt'],
    ['target_id' => 5, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'action' => 'attempt'],
    ['target_id' => 6, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'action' => 'delete_attempt'],
    ['target_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'retry'],
    ['target_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'action' => 'retry'],
    ['target_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'action' => 'retry'],
    ['target_id' => 10, 'blog_id' => 1, 'option_name' => '_transient_feed', 'action' => 'delete_retry'],
    ['target_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'action' => 'delete_retry'],
];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield229', option_value || ':yield229', bytes + 3) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY target_id LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$yieldDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_yield' ORDER BY target_id LIMIT 1) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt229', option_value || ':attempt229', bytes + 5) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'attempt' ORDER BY target_id) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_attempt' ORDER BY target_id) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry229', option_value || ':retry229', bytes + 1) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry' ORDER BY target_id LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_retry' ORDER BY target_id LIMIT 2) RETURNING option_id, blog_id, option_name ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext229(
    ['wp_options' => $options, 'wp_import_targets' => $targets],
    [$yieldUpdate, $yieldDelete],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-select-savepoint-release-current-source-next229',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'yielded_ids' => $plan['release_receipt_next229']['yielded_ids'],
    'suppressed_ids' => $plan['release_receipt_next229']['suppressed_ids'],
    'retry_ids' => $plan['release_receipt_next229']['retry_ids'],
    'changed_tables' => $plan['changed_tables_after_release'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'wordpressUse' => 'A copied WordPress options import can select row-value UPDATE/DELETE RETURNING targets from a staging table, roll back a failed savepoint attempt, retry from the savepoint image, and RELEASE only the retry rows.',
    'dependencyClosure' => $plan['dependency_closure_next229'],
];

if (
    $summary['status'] !== 'rowvalue-update-delete-returning-subquery-savepoint-release-current-source-next229'
    || $summary['yielded_ids'] !== [3, 4, 2]
    || $summary['suppressed_ids'] !== [3, 4, 5]
    || $summary['retry_ids'] !== [3, 4, 6, 2, 7]
    || $summary['final_option_ids'] !== [1, 3, 4, 5, 6]
) {
    fwrite(STDERR, "wordpress-rowvalue-select-savepoint-release-current-source-next229 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
