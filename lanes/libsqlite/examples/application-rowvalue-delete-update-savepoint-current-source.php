<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'network_siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$statements = [
    "DELETE FROM wp_options WHERE (blog_id, status) = (1, 'stale') RETURNING option_id, option_name ORDER BY option_id LIMIT 1",
    "UPDATE OR REPLACE wp_options SET (option_name, status, bytes, option_value) = ('siteurl', option_name || ':promoted', bytes + 10, option_value || ':next') WHERE (blog_id, option_name) IN ((1, 'home'), (1, '_transient_feed'), (2, '_transient_feed')) RETURNING option_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN ((2, 'siteurl'), (2, 'network_siteurl'), (2, 'pending_theme')) RETURNING option_id, option_name ORDER BY option_id",
];

$plan = SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint(
    ['wp_options' => $rows],
    $statements,
    [['option_name']],
    'wp_options_cleanup_batch',
);

if (($argv[1] ?? null) === '--self-test') {
    $ids = array_column($plan['current_source_tables']['wp_options'], 'option_id');
    $names = array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id');
    if ($plan['status'] !== 'released' || $ids !== [5, 6, 7] || $names[5] !== 'siteurl') {
        fwrite(STDERR, "application-rowvalue-delete-update-savepoint-current-source self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-rowvalue-delete-update-savepoint-current-source self-test passed\n");
    exit(0);
}

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'status' => $plan['status'],
    'statement_actions' => array_column($plan['executed_statements'], 'action'),
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'deleted_option_ids' => array_column(array_column($plan['deleted_rows'], 'row'), 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
