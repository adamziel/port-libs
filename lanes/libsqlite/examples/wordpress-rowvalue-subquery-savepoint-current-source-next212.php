<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
        ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
        ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ],
    'wp_optionmeta' => [
        ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme'],
        ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules'],
        ['meta_id' => 103, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl'],
    ],
];

$attempt = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt212', option_value || ':attempt212', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, option_name, status ORDER BY option_id",
];
$retry = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry212', option_value || ':retry212', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, option_name, status ORDER BY option_id",
    "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop') RETURNING option_id, option_name, status ORDER BY option_id",
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry(
    $tables,
    $attempt,
    $retry,
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-subquery-savepoint-current-source-next212');
    assert($plan['discarded_attempt_returning_count'] === 2);
    assert($plan['yielded_after_retry_count'] === 3);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 7, 8]);
    echo "wordpress-rowvalue-subquery-savepoint-current-source-next212 self-test passed\n";
    return;
}

echo 'status: ' . $plan['status'] . "\n";
echo 'savepoint: ' . $plan['savepoint'] . "\n";
echo 'discarded_attempt_returning_count: ' . $plan['discarded_attempt_returning_count'] . "\n";
echo 'yielded_after_retry_count: ' . $plan['yielded_after_retry_count'] . "\n";
echo 'final_option_ids: ' . implode(',', array_column($plan['current_source_tables']['wp_options'], 'option_id')) . "\n";
