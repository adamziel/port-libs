<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':draft', 'draft', option_value || ':draft', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
        "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':rollback', option_value || ':rollback', bytes + 100) WHERE option_id IN (9, 6) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
    ],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'transaction-rolled-back');
    assert($plan['transaction_rolled_back'] === true);
    assert($plan['discarded_returning_count'] === 4);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5, 6, 7, 8, 9]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id')[7] === 'pending_theme');
    assert($plan['yielded_returning'] === []);
    echo "application-rowvalue-rollback-returning-current-source-next146 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'discarded_returning_count' => $plan['discarded_returning_count'],
    'rollback_statement' => $plan['rollback_statement']['reason'] ?? null,
    'restored_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
