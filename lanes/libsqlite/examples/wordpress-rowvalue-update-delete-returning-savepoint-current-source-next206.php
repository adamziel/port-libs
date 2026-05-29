<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext206(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('outer206', option_value || ':outer206', bytes + 1) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('inner206', option_value || ':inner206', bytes + 2) WHERE (blog_id, option_name) IN (VALUES (2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry206', option_value || ':retry206', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

echo json_encode([
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-current-source-next206',
    'wordpressUse' => 'Models a copied wp_options import where a released inner savepoint produced UPDATE/DELETE RETURNING rows, then an outer ROLLBACK TO discards those rows and retries from the outer savepoint image.',
    'status' => $plan['status'],
    'outerSavepoint' => $plan['outer_savepoint'],
    'innerSavepoint' => $plan['inner_savepoint'],
    'discardedReturningCount' => $plan['discarded_by_outer_rollback_count'],
    'retryReturningCount' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
