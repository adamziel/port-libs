<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183(
    ['wp_options' => $rows],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_siteurl ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('inner183', option_value || ':inner183', bytes + 3) WHERE (blog_id, option_name) BETWEEN (2, 'pending_theme') AND (3, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, status) IS (3, 'inner183') AS blog_three_inner ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry183', option_value || ':retry183', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IS ('retry183', 'rewrite_rules') AS rewrite_retry ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id LIMIT 1",
    ],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'outer-delete-preserved-inner-rowvalue-rollback-retry-next183');
    assert($plan['outer_yielded_returning_count'] === 2);
    assert($plan['inner_suppressed_by_rollback_count'] === 6);
    assert($plan['inner_yielded_after_retry_count'] === 4);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 5, 7, 8, 9, 10]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id')[8] === 'retry183');

    echo "wordpress-rowvalue-delete-savepoint-current-source-next183 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'outerDeleted' => array_column($plan['outer_yielded_returning'][0]['rows'], 'option_id'),
    'innerSuppressed' => $plan['inner_suppressed_by_rollback_count'],
    'retryYielded' => $plan['inner_yielded_after_retry_count'],
    'finalIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . "\n";
