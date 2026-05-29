<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 30, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 31, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 10, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 11, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'queued', 'bucket' => 'cache', 'bytes' => 7, 'option_value' => 'orphan'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeUpdateDeleteReturningSavepointBatch(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value) = ('prepared', option_value || ':prepared') WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value ORDER BY option_id"],
    [
        "UPDATE wp_options SET (status, bucket, option_value) = ('protected', 'cache', option_value || ':protected') WHERE (status, bucket) IS NOT DISTINCT FROM ('stale', 'cache') RETURNING option_id, option_name, status, bucket ORDER BY option_id",
        "DELETE FROM wp_options WHERE (status, bucket) IS DISTINCT FROM ('live', 'core') RETURNING option_id, option_name, status, bucket ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value) = ('after', option_value || ':after') WHERE (status, bucket) IS NOT DISTINCT FROM ('stale', 'cache') RETURNING option_id, option_name, status, option_value ORDER BY option_id LIMIT 2",
        "DELETE FROM wp_options WHERE (status, option_name) IN (('prepared', 'orphaned_cache')) RETURNING option_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
    'wp_import_option_cleanup_savepoint',
    1,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['rolled_back_to_savepoint'] === true);
    assert($plan['discarded_returning_count'] === 6);
    assert(array_column($plan['yielded_returning'], 'phase') === ['before', 'after', 'after']);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5]);
    echo "wordpress-rowvalue-update-delete-returning-savepoint-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-current-source',
    'status' => $plan['status'],
    'yielded_phases' => array_column($plan['yielded_returning'], 'phase'),
    'discarded_returning_count' => $plan['discarded_returning_count'],
    'current_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . "\n";
