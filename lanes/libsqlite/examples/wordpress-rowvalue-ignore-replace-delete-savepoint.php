<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://two.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReplaceDeleteSavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, autoload, status, option_value, bytes) = (1, 'yes', 'ignored203', option_value || ':ignored203', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) = (1, 'no') AS tuple_match ORDER BY option_id",
    ],
    [
        "UPDATE OR REPLACE wp_options SET (blog_id, autoload, status, option_value, bytes) = (4, 'yes', 'replace203', option_value || ':replace203', bytes + 4) WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) IS (4, 'yes') AS tuple_is ORDER BY option_id",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, autoload) IN ((4, 'yes'), (1, 'manual')) RETURNING option_id, blog_id, option_name, autoload, status, (blog_id, autoload) IS DISTINCT FROM (1, 'yes') AS distinct_from_site ORDER BY option_id",
    ],
    [['blog_id', 'autoload']],
);

echo json_encode([
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-current-source-ignore_replace_delete',
    'wordpressUse' => 'Model copied wp_options cleanup where OR IGNORE suppresses conflicting RETURNING rows, OR REPLACE deletes the conflicting current row, and a follow-up DELETE RETURNING reads that current source inside the same savepoint.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'ignoredIds' => array_column($plan['ignored_rows'], 'option_id'),
    'replaceDeletedConflictIds' => array_column($plan['replace_deleted_conflict_rows'], 'option_id'),
    'replaceReturnedIds' => array_column($plan['replace_statements'][0]['returning_rows'], 'option_id'),
    'deleteReturnedIds' => array_column($plan['delete_statements'][0]['returning_rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
