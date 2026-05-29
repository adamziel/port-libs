<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$stageSql = "UPDATE wp_options SET (status, option_value, bytes) = ('draft', option_value || ':draft', bytes + 1) WHERE (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') RETURNING option_id, option_name, (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') AS in_range ORDER BY option_id";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') RETURNING option_id, option_name ORDER BY option_id";
$retrySql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 5) WHERE (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') RETURNING option_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') AS retry_range ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') RETURNING option_id, option_name ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [$stageSql, $discardDeleteSql],
    [$retrySql, $cleanupSql],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-between-returning-savepoint-current-source-next163',
    'wordpressUse' => 'Copied wp_options import retry uses row-value BETWEEN in RETURNING projections while ROLLBACK TO discards attempted draft rows and the retry reads the restored current source.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'yieldedReturningCount' => $plan['yielded_returning_count'],
    'retryReturning' => $plan['yielded_returning'],
    'currentIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this reuses native PHP UPDATE/DELETE RETURNING row-value expression and savepoint current-source primitives',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'released-after-rowvalue-between-retry');
    assert($summary['discardedReturningCount'] === 4);
    assert($summary['yieldedReturningCount'] === 4);
    assert($summary['retryReturning'][0]['rows'][0]['retry_range'] === 1);
    assert($summary['currentIds'] === [1, 4, 5]);
    echo "wordpress-rowvalue-between-returning-savepoint-current-source-next163 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
