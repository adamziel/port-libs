<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
        ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
        ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ],
    'wp_optionmeta' => [
        ['meta_id' => 201, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'blog_id' => 2],
        ['meta_id' => 202, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'blog_id' => 3],
        ['meta_id' => 203, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'blog_id' => 1],
        ['meta_id' => 204, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'blog_id' => 1],
        ['meta_id' => 205, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'blog_id' => 4],
    ],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint(
    $tables,
    ["UPDATE wp_options SET (status, option_value, bytes) = ('pre230', option_value || ':pre230', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id"],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('inner230', option_value || ':inner230', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_id DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id",
        "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY meta_id) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    ["DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop') RETURNING option_id, blog_id, option_name, status ORDER BY option_id"],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry230', option_value || ':retry230', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_id) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id",
        "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY meta_id LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

echo json_encode([
    'wordpressUse' => 'Preview a copied multisite wp_options migration that releases an inner savepoint, then rolls back the outer savepoint so inner UPDATE/DELETE RETURNING rows are discarded and retry reads the restored current source.',
    'status' => $plan['status'],
    'discardedInnerReleaseReturningCount' => $plan['discarded_inner_release_returning_count'],
    'yieldedAfterRetryCount' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'finalStatuses' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id'),
    'dependencyClosure' => $plan['dependency_closure_next230'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
