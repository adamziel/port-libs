<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta = [
    ['meta_id' => 401, 'meta_option_id' => 7, 'meta_key' => 'attempt_update', 'meta_value' => 'pending_theme'],
    ['meta_id' => 402, 'meta_option_id' => 8, 'meta_key' => 'attempt_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 403, 'meta_option_id' => 3, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_feed'],
    ['meta_id' => 404, 'meta_option_id' => 4, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_timeout_feed'],
    ['meta_id' => 405, 'meta_option_id' => 8, 'meta_key' => 'retry_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 406, 'meta_option_id' => 10, 'meta_key' => 'retry_update', 'meta_value' => 'network_plugin'],
    ['meta_id' => 407, 'meta_option_id' => 2, 'meta_key' => 'retry_delete', 'meta_value' => 'home'],
];

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext235(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('attempt235', option_value || ':attempt235', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry235', option_value || ':retry235', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC",
    ],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next235');
    assert($plan['discarded_attempt_window_count_next235'] === 4);
    assert($plan['yielded_retry_window_count_next235'] === 3);
    assert($plan['discarded_attempt_window_ids_next235'] === [3, 4, 7, 8]);
    assert($plan['yielded_retry_window_ids_next235'] === [2, 8, 10]);
    assert($plan['current_source_tables']['wp_options'][5]['option_value'] === 'rules:retry235');
    echo "wordpress-rowvalue-returning-window-current-source-next235 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'discarded_attempt_window_ids' => $plan['discarded_attempt_window_ids_next235'],
    'yielded_retry_window_ids' => $plan['yielded_retry_window_ids_next235'],
    'yield_boundary' => $plan['window_yield_boundary_next235'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
