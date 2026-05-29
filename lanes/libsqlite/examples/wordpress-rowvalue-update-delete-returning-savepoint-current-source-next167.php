<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
        ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
        ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ],
];

$preRollback = [
    "UPDATE wp_options SET (status, option_value) = ('draft WHERE literal', option_value || ' RETURNING literal') WHERE option_id IN (7, 8) RETURNING option_id, status, option_value || ' ORDER BY literal' AS marker ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name || ' LIMIT literal' AS marker ORDER BY option_id LIMIT 1",
];
$retry = [
    "UPDATE wp_options SET (status, option_value) = ('retry LIMIT literal', option_value || ' WHERE retry') WHERE option_id IN (8, 9) RETURNING option_id, status, option_value || ' RETURNING retry' AS marker ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name || ' ORDER BY retry' AS marker ORDER BY option_id",
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161(
    $tables,
    $preRollback,
    $retry,
    [['blog_id', 'option_name']],
    'wp_options_rowvalue_literal_clause_retry_next167',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'released-after-clean-retry');
    assert($plan['discarded_returning_count'] === 3);
    assert($plan['yielded_returning_count'] === 4);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 7, 8, 9]);
    assert($plan['yielded_returning'][0]['rows'][1]['marker'] === 'plugin WHERE retry RETURNING retry');
    echo "wordpress-rowvalue-update-delete-returning-savepoint-current-source-next167 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'discardedReturning' => $plan['discarded_returning_count'],
    'yieldedReturning' => $plan['yielded_returning_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'retryMarkers' => array_column($plan['yielded_returning'][0]['rows'], 'marker'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
