<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$outerSql = "UPDATE wp_options SET (status, option_value, bytes) = ('outer182', option_value || ':outer182', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$innerReplaceSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'released182', option_value || ':released182', bytes + 40) WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value, bytes";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry182', option_value || ':retry182', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleasedInnerSavepointRollback(
    ['wp_options' => $rows],
    [$outerSql],
    [$innerDeleteSql, $innerReplaceSql],
    [$retryUpdateSql, $retryDeleteSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'released-inner-returning-suppressed-by-outer-rollback-next182');
    assert($plan['suppressed_by_outer_rollback_count'] === 5);
    assert($plan['yielded_after_retry_count'] === 4);
    assert(array_column($plan['yielded_after_retry_returning'][0]['rows'], 'option_value') === ['theme:retry182', 'cache:retry182']);
    assert(array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id')[8] === 'queued');
    echo "wordpress-rowvalue-release-inner-rollback-current-source-next182 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'wordpressUse' => 'Copied wp_options imports can RELEASE an inner savepoint after UPDATE/DELETE RETURNING work, then ROLLBACK TO the outer savepoint and suppress both the outer and released-inner RETURNING streams before retrying from the outer current source.',
    'suppressedReturningRows' => $plan['suppressed_by_outer_rollback_count'],
    'retryReturnedOptionIds' => array_column($plan['yielded_after_retry_returning'][0]['rows'], 'option_id'),
    'retryDeletedTransientIds' => array_column($plan['yielded_after_retry_returning'][1]['rows'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes native PHP row-value UPDATE RETURNING, DELETE RETURNING, unique REPLACE conflict handling, and savepoint current-source retry behavior',
], JSON_PRETTY_PRINT) . PHP_EOL;
