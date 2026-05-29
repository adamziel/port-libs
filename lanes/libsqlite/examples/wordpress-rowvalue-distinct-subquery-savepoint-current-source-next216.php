<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];
$meta = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme'],
    ['meta_id' => 102, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme'],
    ['meta_id' => 103, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 104, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 105, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl'],
    ['meta_id' => 106, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl'],
];

$update = "UPDATE wp_options SET (status, option_value, bytes) = ('retry216', option_value || ':retry216', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$delete = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop') RETURNING option_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint(
    ['wp_options' => $options, 'wp_optionmeta' => $meta],
    [$update],
    [$update, $delete],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source');
    assert($plan['yielded_after_retry_count'] === 3);
    assert($plan['retry_statements'][0]['selected_ids'] === [7, 8]);
    assert($plan['retry_statements'][1]['selected_ids'] === [10]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [7, 8]);
    echo "wordpress rowvalue distinct subquery savepoint current-source self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retrySelected' => array_column($plan['retry_statements'], 'selected_ids'),
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'remainingOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . "\n";
