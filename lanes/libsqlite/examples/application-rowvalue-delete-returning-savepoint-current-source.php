<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 40, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 15, 'option_value' => 'network-feed'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 44, 'option_value' => 'https://network.test'],
];

$plan = SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (2, '_transient_feed') AND (2, 'siteurl') RETURNING option_id, option_name ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2)) RETURNING option_id",
    ],
    [['blog_id', 'option_name']],
);

if ($plan['status'] !== 'rollback-savepoint-rolled-back') {
    throw new RuntimeException('Expected inner DELETE RETURNING savepoint to roll back');
}
if (array_column($plan['current_source_tables']['wp_options'], 'option_id') !== [1, 4, 5]) {
    throw new RuntimeException('Expected released transient cleanup to survive and inner network cleanup to roll back');
}
if (array_column($plan['yielded_returning'][0]['rows'], 'option_id') !== [2, 3]) {
    throw new RuntimeException('Expected only released DELETE RETURNING rows to be yielded after rollback');
}

echo json_encode([
    'status' => $plan['status'],
    'changes' => $plan['changes'],
    'attempted_changes' => $plan['attempted_changes'],
    'remaining_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'yielded_option_ids' => array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
], JSON_PRETTY_PRINT) . PHP_EOL;
