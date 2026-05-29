<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueNestedSavepointReturningPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://site.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'a:1:{}'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'template', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'twentysixteen'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => null, 'bytes' => 32, 'option_value' => 'mods'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 15, 'option_value' => 'plugin'],
];

$innerUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('inner-release', option_value || ':inner', bytes + 100) WHERE (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS inner_range ORDER BY option_id";
$innerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS inner_delete_match ORDER BY option_id";
$outerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, blog_id, option_name, (blog_id, option_name) IS (3, 'rewrite_rules') AS outer_delete_match ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry-after-outer', option_value || ':retry', bytes + 1) WHERE (blog_id, status) IS NOT DISTINCT FROM (3, 'queued') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, status) IS (3, 'retry-after-outer') AS retry_tuple ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS retry_delete_match ORDER BY option_id LIMIT 1";

$plan = SQLiteRowValueNestedSavepointReturningPlan::execute(
    ['wp_options' => $rows],
    [$innerUpdateSql, $innerDeleteSql],
    [$outerDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'nested-release-rolled-back-retried-current-source');
    assert($plan['inner_released_returning_count'] === 5);
    assert($plan['discarded_by_outer_rollback_count'] === 6);
    assert($plan['yielded_after_retry_count'] === 3);
    assert(array_column($plan['yielded_after_retry_returning'][0]['rows'], 'option_id') === [8, 9]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 4, 5, 6, 7, 8, 9]);
    echo "OK\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'outerSavepoint' => $plan['outer_savepoint'],
    'innerSavepoint' => $plan['inner_savepoint'],
    'innerReleasedReturningCount' => $plan['inner_released_returning_count'],
    'discardedByOuterRollbackCount' => $plan['discarded_by_outer_rollback_count'],
    'yieldedAfterRetryCount' => $plan['yielded_after_retry_count'],
    'retryReturnedOptionIds' => array_column($plan['yielded_after_retry_returning'][0]['rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . PHP_EOL;
