<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$plan = SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, option_name",
        "UPDATE wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate') WHERE (blog_id, option_name) = (2, 'pending_theme') RETURNING option_id, blog_id, option_name, status",
    ],
    [['table' => 'wp_options', 'columns' => ['blog_id', 'option_name']]],
    'app_settings_cleanup',
    'option_id',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'rolled-back-to-savepoint');
    assert($plan['rollback_reason'] === 'unique-constraint:wp_options:blog_id,option_name:1|home');
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5, 6, 7]);
    assert(array_column($plan['next_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 5, 6, 7]);
    assert($plan['yielded_returning'][0]['rows'] === [['option_id' => 4, 'option_name' => '_transient_timeout_feed']]);
    echo "application-rowvalue-update-delete-savepoint-current-source-next126 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-rowvalue-update-delete-savepoint-current-source-next126',
    'status' => $plan['status'],
    'rollback_reason' => $plan['rollback_reason'],
    'current_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'attempted_option_ids' => array_column($plan['next_source_tables']['wp_options'], 'option_id'),
    'yielded_returning' => $plan['yielded_returning'],
    'attempted_returning' => $plan['attempted_returning'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
