<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$meta = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 30],
    ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 50],
    ['meta_id' => 103, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 40],
    ['meta_id' => 108, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 90],
    ['meta_id' => 109, 'meta_option_id' => 11, 'meta_key' => 'network_drop', 'meta_value' => 'home', 'priority' => 70],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedSubquerySavepointRetry(
    ['wp_options' => $rows, 'wp_optionmeta' => $meta],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('attempt214', option_value || ':attempt214', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry214', option_value || ':retry214', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 1, 2) RETURNING option_id, option_name, status ORDER BY option_id",
        "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 1) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

echo json_encode([
    'status' => $plan['status'],
    'discardedAttemptIds' => array_column($plan['discarded_attempt_returning'][0]['rows'], 'option_id'),
    'retrySelectedIds' => $plan['retry_statements'][0]['selected_ids'],
    'deletedNetworkIds' => array_column($plan['yielded_after_retry_returning'][1]['rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'finalValues' => array_column($plan['current_source_tables']['wp_options'], 'option_value', 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
