<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/TestRunner.php';
require_once dirname(__DIR__) . '/src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

$options = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
];

$meta = [
    ['meta_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'meta_key' => 'import_touch', 'priority' => 10],
    ['meta_id' => 2, 'blog_id' => 2, 'option_name' => 'pending_theme', 'meta_key' => 'import_touch', 'priority' => 20],
    ['meta_id' => 3, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'meta_key' => 'import_touch', 'priority' => 30],
    ['meta_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'meta_key' => 'import_touch', 'priority' => 40],
    ['meta_id' => 5, 'blog_id' => 1, 'option_name' => '_transient_feed', 'meta_key' => 'delete_touch', 'priority' => 50],
    ['meta_id' => 6, 'blog_id' => 1, 'option_name' => '_transient_feed', 'meta_key' => 'delete_touch', 'priority' => 60],
    ['meta_id' => 7, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'meta_key' => 'retry_touch', 'priority' => 70],
    ['meta_id' => 8, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'meta_key' => 'retry_touch', 'priority' => 80],
];

$tables = ['wp_options' => $options, 'wp_optionmeta' => $meta];
$unique = [['blog_id', 'option_name']];
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt227', option_value || ':attempt227', bytes + 2) WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'import_touch' ORDER BY priority LIMIT -1 OFFSET 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'delete_touch' ORDER BY priority) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry227', option_value || ':retry227', bytes + 1) WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'import_touch' ORDER BY priority) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'retry_touch' ORDER BY priority) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext227(
    $tables,
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    $unique,
);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['suppressed_returning_count'] !== 2) {
        fwrite(STDERR, "Expected two suppressed attempt rows\n");
        exit(1);
    }
    if ($plan['retry_returning_count'] !== 3) {
        fwrite(STDERR, "Expected three retry rows\n");
        exit(1);
    }
    if (array_column($plan['retry_rows'], 'option_id') !== [7, 8, 9]) {
        fwrite(STDERR, "Unexpected retry row ids\n");
        exit(1);
    }
    if ($plan['rollback_current_source_tables'] !== $plan['savepoint_image_tables']) {
        fwrite(STDERR, "Rollback did not restore the savepoint image\n");
        exit(1);
    }
}

echo json_encode([
    'status' => $plan['status'],
    'suppressed_returning_count' => $plan['suppressed_returning_count'],
    'retry_returning_count' => $plan['retry_returning_count'],
    'retry_ids' => array_column($plan['retry_rows'], 'option_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
