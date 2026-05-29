<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no-cache', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailConflictPreserveRetrySavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('outer196', option_value || ':outer196', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, autoload, status ORDER BY option_id",
    ],
    "UPDATE OR FAIL wp_options SET (blog_id, status, option_value, bytes) = (1, 'fail196', option_value || ':fail196', bytes + 4) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) = (1, 'no') AS fail_prefix_tuple ORDER BY option_id",
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry196', option_value || ':retry196', bytes + 5) WHERE (blog_id, option_name) IN ((1, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'autoload']],
);

echo json_encode([
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-current-source-next196',
    'wordpressUse' => 'Preserve SQLite UPDATE OR FAIL prefix changes during copied wp_options cleanup so a retry UPDATE/DELETE RETURNING reads the correct current source without ext/sqlite.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'failPrefixIds' => array_column($plan['yielded_by_fail_before_conflict'], 'option_id'),
    'failedConflict' => $plan['fail_statement']['failed_conflict'],
    'retryReturnedIds' => array_merge(
        array_column($plan['retry_statements'][0]['returning_rows'], 'option_id'),
        array_column($plan['retry_statements'][1]['returning_rows'], 'option_id'),
    ),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
