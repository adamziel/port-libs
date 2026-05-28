<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Plan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 19, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 7, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 8, 'blog_id' => 5, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'network'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield246', option_value || ':yield246', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt246', option_value || ':attempt246', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry246', option_value || ':retry246', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'), (5, 'network_plugin')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Plan::execute(
    ['wp_options' => $rows],
    [$yieldUpdate, $yieldDelete],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

$retryKey = 'retry-window-after-rollback-release-next233#0#update';

assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next246');
assert($plan['retry_filter_summary_next246'][$retryKey]['update_ids'] === [7, 8, 6, 5]);
assert($plan['retry_filter_summary_next246'][$retryKey]['total_bytes'] === 116);
assert($plan['release_filter_audit_next246']['retry_updates_visible_after_release'] === true);
assert($plan['release_filter_audit_next246']['suppressed_only_visible_after_release'] === true);

return [
    'status' => $plan['status'],
    'retryUpdateIds' => $plan['retry_filter_summary_next246'][$retryKey]['update_ids'],
    'retryUpdateBytes' => $plan['retry_filter_summary_next246'][$retryKey]['total_bytes'],
    'suppressedOnlyVisible' => $plan['release_filter_audit_next246']['suppressed_only_visible_after_release'],
    'wordpressUse' => 'Copied wp_options imports can compute FILTER-style UPDATE/DELETE RETURNING window receipts across a rolled-back attempt and a released retry without admitting suppressed rows into the retry release.',
    'dependencyClosure' => $plan['dependency_closure_next246'],
];
