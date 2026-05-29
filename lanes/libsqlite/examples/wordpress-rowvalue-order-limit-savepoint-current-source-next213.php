<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
        ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
        ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ],
    'wp_optionmeta' => [
        ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
        ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 30],
        ['meta_id' => 103, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 20],
        ['meta_id' => 104, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 40],
    ],
];

$attempt = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt213', option_value || ':attempt213', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, option_name, status ORDER BY option_id",
];
$retry = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry213', option_value || ':retry213', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, option_name, status ORDER BY option_id DESC",
    "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 1) RETURNING option_id, option_name, status ORDER BY option_id",
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedLimitSubquerySavepoint(
    $tables,
    $attempt,
    $retry,
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-order-limit-subquery-savepoint-current-source-next213');
    assert($plan['discarded_attempt_returning_count'] === 2);
    assert($plan['yielded_after_retry_count'] === 3);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [7, 8, 9]);
    echo "wordpress-rowvalue-order-limit-savepoint-current-source-next213 self-test passed\n";
    return;
}

echo 'status: ' . $plan['status'] . "\n";
echo 'savepoint: ' . $plan['savepoint'] . "\n";
echo 'discarded_attempt_returning_count: ' . $plan['discarded_attempt_returning_count'] . "\n";
echo 'yielded_after_retry_count: ' . $plan['yielded_after_retry_count'] . "\n";
echo 'final_option_ids: ' . implode(',', array_column($plan['current_source_tables']['wp_options'], 'option_id')) . "\n";
