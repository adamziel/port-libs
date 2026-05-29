<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 40, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rewrite', 'bytes' => 20, 'option_value' => 'rules'],
    ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bucket' => 'cache', 'bytes' => 8, 'option_value' => 'orphan'],
];

$tables = ['wp_options' => $rows];
$statements = [
    "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') RETURNING option_id, option_name, (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') AS in_cleanup_range ORDER BY option_id LIMIT 1",
    "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'reviewed', option_name || ':reviewed', bytes + 5) WHERE (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'siteurl') RETURNING option_id, option_name, status, bytes, (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'siteurl') AS still_outside_range ORDER BY option_id",
    "DELETE FROM wp_options WHERE (status, bucket) BETWEEN ('reviewed', 'cache') AND ('reviewed', 'rewrite') RETURNING option_id, option_name, (status, bucket) BETWEEN ('reviewed', 'cache') AND ('reviewed', 'rewrite') AS reviewed_bucket ORDER BY option_id",
];

$plan = SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeNext141(
    $tables,
    $statements,
    [['blog_id', 'option_name']],
);

if ($plan['status'] !== 'released') {
    throw new RuntimeException('Expected row-value cleanup savepoint to release');
}
if (array_column($plan['current_source_tables']['wp_options'], 'option_id') !== [1, 3]) {
    throw new RuntimeException('Expected current source to retain the canonical siteurl and in-range rewrite row');
}
if (array_column(array_column($plan['deleted_rows'], 'row'), 'option_id') !== [2, 4]) {
    throw new RuntimeException('Expected row-value cleanup to delete stale and reviewed outside-range option rows');
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'changes' => $plan['changes'],
    'remaining_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . PHP_EOL;
