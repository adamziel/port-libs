<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta = [
    ['meta_id' => 501, 'meta_option_id' => 7, 'meta_key' => 'attempt_update', 'meta_value' => 'pending_theme'],
    ['meta_id' => 502, 'meta_option_id' => 8, 'meta_key' => 'attempt_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 503, 'meta_option_id' => 9, 'meta_key' => 'attempt_update', 'meta_value' => 'plugin_batch'],
    ['meta_id' => 504, 'meta_option_id' => 3, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_feed'],
    ['meta_id' => 505, 'meta_option_id' => 4, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_timeout_feed'],
    ['meta_id' => 506, 'meta_option_id' => 8, 'meta_key' => 'retry_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 507, 'meta_option_id' => 9, 'meta_key' => 'retry_update', 'meta_value' => 'plugin_batch'],
    ['meta_id' => 508, 'meta_option_id' => 10, 'meta_key' => 'retry_update', 'meta_value' => 'network_plugin'],
    ['meta_id' => 509, 'meta_option_id' => 2, 'meta_key' => 'retry_delete', 'meta_value' => 'home'],
];

$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt244', option_value || ':attempt244', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry244', option_value || ':retry244', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan::execute(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next244');
    assert($plan['window_transition_chain_count_next244'] === 6);
    assert($plan['window_transition_replayed_ids_next244'] === [8, 9]);
    assert($plan['window_transition_restart_ids_next244'] === [2, 10]);
    assert($plan['window_transition_discarded_ids_next244'] === [3, 4, 7]);
    assert($plan['window_transition_summary_next244']['lag_class_changes'] === 3);
    assert($plan['window_transition_summary_next244']['lead_class_changes'] === 3);
    assert($plan['window_transition_fence_next244']['transition_count'] === 6);
    assert(strlen($plan['window_transition_fence_next244']['transition_digest']) === 64);

    echo "wordpress-rowvalue-returning-window-current-source-next244 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'transitionCount' => $plan['window_transition_chain_count_next244'],
    'replayed' => $plan['window_transition_replayed_ids_next244'],
    'restartOnly' => $plan['window_transition_restart_ids_next244'],
    'discardedOnly' => $plan['window_transition_discarded_ids_next244'],
    'lagChanges' => $plan['window_transition_summary_next244']['lag_class_changes'],
    'leadChanges' => $plan['window_transition_summary_next244']['lead_class_changes'],
], JSON_PRETTY_PRINT) . PHP_EOL;
