<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta = [
    ['meta_id' => 1, 'meta_option_id' => 7, 'meta_key' => 'migration_primary', 'meta_value' => 'pending_theme'],
    ['meta_id' => 2, 'meta_option_id' => 8, 'meta_key' => 'migration_primary', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 3, 'meta_option_id' => 8, 'meta_key' => 'migration_secondary', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 4, 'meta_option_id' => 9, 'meta_key' => 'migration_secondary', 'meta_value' => 'plugin_batch'],
    ['meta_id' => 5, 'meta_option_id' => 8, 'meta_key' => 'retry_candidate', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 6, 'meta_option_id' => 10, 'meta_key' => 'retry_candidate', 'meta_value' => 'network_plugin'],
    ['meta_id' => 7, 'meta_option_id' => 8, 'meta_key' => 'retry_confirmed', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 8, 'meta_option_id' => 10, 'meta_key' => 'retry_confirmed', 'meta_value' => 'network_plugin'],
];

$attempt = "UPDATE wp_options SET (status, option_value) = ('attempt231', option_value || ':attempt231') WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_primary' UNION SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_secondary') RETURNING option_id, option_name, status ORDER BY option_id";
$retry = "UPDATE wp_options SET (status, option_value) = ('retry231', option_value || ':retry231') WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_candidate' INTERSECT SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_confirmed') RETURNING option_id, option_name, status ORDER BY option_id DESC";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback(
    ['wp_options' => $options, 'wp_optionmeta' => $meta],
    [$attempt],
    [$retry],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-compound-subquery-savepoint-current-source',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'discarded_attempt_returning_count' => $plan['discarded_attempt_returning_count'],
    'yielded_after_retry_count' => $plan['yielded_after_retry_count'],
    'attempt_ids' => $plan['attempt_statements'][0]['selected_ids'],
    'retry_ids' => $plan['retry_statements'][0]['selected_ids'],
    'current_option_values' => array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id'),
    'applicationUse' => 'A copied Application multisite options import can feed row-value UPDATE RETURNING from compound SELECT tuple sources, roll back the first batch to a savepoint, and retry from the restored current source without ext/sqlite.',
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'rowvalue-update-delete-returning-compound-subquery-savepoint-current-source'
        || $summary['discarded_attempt_returning_count'] !== 3
        || $summary['yielded_after_retry_count'] !== 2
        || $summary['attempt_ids'] !== [7, 8, 9]
        || $summary['retry_ids'] !== [10, 8]
        || $summary['current_option_values'][8] !== 'rules:retry231'
    ) {
        fwrite(STDERR, "application-rowvalue-compound-subquery-savepoint-current-source self-test failed\n");
        exit(1);
    }
    fwrite(STDOUT, "application-rowvalue-compound-subquery-savepoint-current-source self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
