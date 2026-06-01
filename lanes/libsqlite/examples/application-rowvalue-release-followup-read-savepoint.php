<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://one.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'homepage', 'load_policy' => 'manual', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://homepage.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 24, 'key_value' => 'https://two.test'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'profile'],
    ['setting_id' => 6, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'orphaned_cache', 'load_policy' => 'yes', 'status' => 'staged', 'bytes' => 5, 'key_value' => 'cache'],
    ['setting_id' => 8, 'tenant_id' => 4, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'https://four.test'],
    ['setting_id' => 9, 'tenant_id' => 5, 'key_name' => 'cache_old', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 3, 'key_value' => 'old'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint(
    ['app_settings' => $rows],
    [
        "UPDATE OR REPLACE app_settings SET (tenant_id, load_policy, status, key_value, bytes) = (4, 'yes', 'released205', key_value || ':released205', bytes + 10) WHERE (tenant_id, key_name) IN ((3, 'orphaned_cache')) RETURNING setting_id, tenant_id, key_name, load_policy, status, key_value, bytes, (tenant_id, load_policy) IS (4, 'yes') AS tuple_is ORDER BY setting_id",
        "DELETE FROM app_settings WHERE (tenant_id, load_policy) IN ((1, 'manual'), (5, 'no')) RETURNING setting_id, tenant_id, key_name, load_policy, status, (tenant_id, load_policy) IS DISTINCT FROM (1, 'yes') AS distinct_from_base ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('release_followup_read', key_value || ':release_followup_read', bytes + 1) WHERE (tenant_id, load_policy) IN ((4, 'yes'), (1, 'no')) RETURNING setting_id, tenant_id, key_name, load_policy, status, key_value, bytes ORDER BY setting_id",
        "DELETE FROM app_settings WHERE (tenant_id, load_policy) IN ((4, 'yes'), (2, 'yes')) RETURNING setting_id, tenant_id, key_name, load_policy, status, bytes ORDER BY setting_id",
    ],
    [['tenant_id', 'load_policy']],
);

echo json_encode([
    'scenario' => 'application-rowvalue-update-delete-returning-savepoint-current-source-release_followup_read',
    'applicationUse' => 'Model copied app_settings import cleanup where RELEASE of a row-value UPDATE/DELETE RETURNING savepoint promotes the current source for the next statement, so a follow-up UPDATE and DELETE see the released replacement row.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'releaseAdmitted' => $plan['release_admitted_release_followup_read'],
    'nextReadReleasedCurrentSource' => $plan['next_read_released_current_source_release_followup_read'],
    'savepointReturned' => $plan['released_returning_count'],
    'nextReturned' => $plan['next_returning_count'],
    'releasedIds' => array_column($plan['released_current_source_tables']['app_settings'], 'setting_id'),
    'nextUpdateSourceIds' => array_column($plan['next_statements'][0]['source_rows'], 'setting_id'),
    'nextDeleteSourceIds' => array_column($plan['next_statements'][1]['source_rows'], 'setting_id'),
    'finalSettingIds' => array_column($plan['current_source_tables']['app_settings'], 'setting_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
