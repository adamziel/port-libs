<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueNestedSavepointReturningPlan;

$rows = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://site.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'home', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://home.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 1, 'key_name' => 'cache_feed_timeout', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 13, 'key_value' => 'timeout'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'active_modules', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'a:1:{}'],
    ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'template', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 31, 'key_value' => 'twentysixteen'],
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'profile_mods', 'load_policy' => 'yes', 'status' => null, 'bytes' => 32, 'key_value' => 'mods'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'yes', 'status' => 'queued', 'bytes' => 14, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 15, 'key_value' => 'module'],
];

$innerUpdateSql = "UPDATE app_settings SET (status, key_value, bytes) = ('inner-release', key_value || ':inner', bytes + 100) WHERE (tenant_id, key_name) BETWEEN (2, 'active_modules') AND (2, 'zzzz') RETURNING setting_id, tenant_id, key_name, status, key_value, bytes, (tenant_id, key_name) BETWEEN (2, 'active_modules') AND (2, 'zzzz') AS inner_range ORDER BY setting_id";
$innerDeleteSql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((1, 'cache_feed'), (1, 'cache_feed_timeout')) RETURNING setting_id, tenant_id, key_name, (tenant_id, key_name) IN ((1, 'cache_feed'), (1, 'cache_feed_timeout')) AS inner_delete_match ORDER BY setting_id";
$outerDeleteSql = "DELETE FROM app_settings WHERE (tenant_id, key_name) = (3, 'route_rules') RETURNING setting_id, tenant_id, key_name, (tenant_id, key_name) IS (3, 'route_rules') AS outer_delete_match ORDER BY setting_id";
$retryUpdateSql = "UPDATE app_settings SET (status, key_value, bytes) = ('retry-after-outer', key_value || ':retry', bytes + 1) WHERE (tenant_id, status) IS NOT DISTINCT FROM (3, 'queued') RETURNING setting_id, tenant_id, key_name, status, key_value, bytes, (tenant_id, status) IS (3, 'retry-after-outer') AS retry_tuple ORDER BY setting_id";
$retryDeleteSql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((1, 'cache_feed'), (1, 'cache_feed_timeout')) RETURNING setting_id, key_name, (tenant_id, key_name) IS DISTINCT FROM (1, 'base_url') AS retry_delete_match ORDER BY setting_id LIMIT 1";

$plan = SQLiteRowValueNestedSavepointReturningPlan::execute(
    ['app_settings' => $rows],
    [$innerUpdateSql, $innerDeleteSql],
    [$outerDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    [['tenant_id', 'key_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'nested-release-rolled-back-retried-current-source');
    assert($plan['inner_released_returning_count'] === 5);
    assert($plan['discarded_by_outer_rollback_count'] === 6);
    assert($plan['yielded_after_retry_count'] === 3);
    assert(array_column($plan['yielded_after_retry_returning'][0]['rows'], 'setting_id') === [8, 9]);
    assert(array_column($plan['current_source_tables']['app_settings'], 'setting_id') === [1, 2, 4, 5, 6, 7, 8, 9]);
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
    'retryReturnedSettingIds' => array_column($plan['yielded_after_retry_returning'][0]['rows'], 'setting_id'),
    'finalSettingIds' => array_column($plan['current_source_tables']['app_settings'], 'setting_id'),
], JSON_PRETTY_PRINT) . PHP_EOL;
