<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://site.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'a:1:{}'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'template', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'twentysixteen'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => null, 'bytes' => 32, 'option_value' => 'mods'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 15, 'option_value' => 'updates'],
];

$yieldUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('yielded', option_value || ':yielded', bytes + 10) WHERE (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS yielded_range ORDER BY option_id";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) AS delete_tuple_match ORDER BY option_id";
$discardUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('discarded', option_value || ':discarded', bytes + 20) WHERE (blog_id, option_name) NOT BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS outside_yield_range ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) AS retry_delete_match ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 5) WHERE (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS retry_range ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeYieldCheckpointSavepointBatch(
    ['wp_options' => $rows],
    [$yieldUpdateSql],
    [$discardDeleteSql, $discardUpdateSql],
    [$retryDeleteSql, $retryUpdateSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    $finalIds = array_column($plan['current_source_tables']['wp_options'], 'option_id');
    $finalStatuses = array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id');
    $deliveredIds = array_column($plan['delivered_before_rollback_returning'][0]['rows'], 'option_id');

    assert($plan['status'] === 'yielded-rowvalue-returning-stream-rolled-back-and-retried');
    assert($plan['delivered_before_rollback_count'] === 3);
    assert($plan['suppressed_by_rollback_count'] === 9);
    assert($plan['yielded_after_retry_count'] === 6);
    assert($deliveredIds === [5, 6, 7]);
    assert($finalIds === [1, 2, 5, 6, 7, 8]);
    assert($finalStatuses[7] === 'retry');

    echo "application-rowvalue-yield-savepoint-current-source-next172 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'deliveredBeforeRollback' => $plan['delivered_before_rollback_count'],
    'suppressedByRollback' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
