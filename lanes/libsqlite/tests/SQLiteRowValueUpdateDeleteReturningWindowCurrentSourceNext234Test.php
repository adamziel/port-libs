<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows234 = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://one.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://landing_url.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'stale_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 1, 'key_name' => 'stale_timeout_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 13, 'key_value' => 'timeout'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 25, 'key_value' => 'https://two.test'],
    ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 26, 'key_value' => 'https://two-landing_url.test'],
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_layout', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'theme'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'routing_rules', 'load_policy' => 'yes', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 11, 'key_value' => 'module'],
    ['setting_id' => 10, 'tenant_id' => 4, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'https://four.test'],
    ['setting_id' => 11, 'tenant_id' => 4, 'key_name' => 'stale_cache', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 14, 'key_value' => 'cache'],
    ['setting_id' => 12, 'tenant_id' => 4, 'key_name' => 'tenant_module', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 16, 'key_value' => 'network'],
];

$meta234 = [
    ['meta_id' => 201, 'meta_setting_id' => 7, 'meta_key' => 'retry_batch', 'meta_value' => 'pending_layout', 'priority' => 10],
    ['meta_id' => 202, 'meta_setting_id' => 8, 'meta_key' => 'retry_batch', 'meta_value' => 'routing_rules', 'priority' => 20],
    ['meta_id' => 203, 'meta_setting_id' => 9, 'meta_key' => 'retry_batch', 'meta_value' => 'module_batch', 'priority' => 30],
    ['meta_id' => 204, 'meta_setting_id' => 11, 'meta_key' => 'retry_batch', 'meta_value' => 'stale_cache', 'priority' => 40],
    ['meta_id' => 205, 'meta_setting_id' => 3, 'meta_key' => 'attempt_cleanup', 'meta_value' => 'stale_feed', 'priority' => 5],
    ['meta_id' => 206, 'meta_setting_id' => 4, 'meta_key' => 'attempt_cleanup', 'meta_value' => 'stale_timeout_feed', 'priority' => 15],
    ['meta_id' => 207, 'meta_setting_id' => 12, 'meta_key' => 'retry_cleanup', 'meta_value' => 'tenant_module', 'priority' => 25],
];

$tables234 = ['app_settings' => $rows234, 'app_setting_targets' => $meta234];
$unique234 = [['tenant_id', 'key_name']];

$attemptUpdate234 = "UPDATE app_settings SET (status, key_value, bytes) = ('attempt234', key_value || ':attempt234', bytes + 1) WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT 3) RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id";
$attemptDelete234 = "DELETE FROM app_settings WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'attempt_cleanup' ORDER BY priority ASC) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id DESC";
$retryUpdate234 = "UPDATE app_settings SET (status, key_value, bytes) = ('retry234', key_value || ':retry234', bytes + 2) WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT -1) RETURNING setting_id, tenant_id, key_name, status, key_value, bytes, (status, key_name) = ('retry234', 'pending_layout') AS retry_pending ORDER BY setting_id";
$retryDelete234 = "DELETE FROM app_settings WHERE (setting_id, key_name) IN (SELECT meta_setting_id, meta_value FROM app_setting_targets WHERE meta_key = 'retry_cleanup' ORDER BY priority ASC) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";

$attemptUpdateResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate234, $tables234, 'setting_id', $unique234);
$attemptDeleteResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete234, $attemptUpdateResult234()['tables'], 'setting_id', $unique234);
$retryUpdateResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate234, $tables234, 'setting_id', $unique234);
$retryDeleteResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete234, $retryUpdateResult234()['tables'], 'setting_id', $unique234);
$plan234 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow(
    $tables234,
    [$attemptUpdate234, $attemptDelete234],
    [$retryUpdate234, $retryDelete234],
    $unique234,
);
$customPlan234 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow(
    $tables234,
    [$attemptUpdate234],
    [$retryUpdate234],
    $unique234,
    'status',
    'key_name',
    'setting_id',
    'app_custom_returning_window_next234',
);

$cases234 = [
    'parser attempt subquery retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate234)['where'] ?? '', "meta_key = 'retry_batch'"), true],
    'parser retry negative limit retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate234)['where'] ?? '', 'LIMIT -1'), true],
    'parser retry returning row-value flag retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate234)['returning'], 'retry_pending'), true],
    'attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult234()['plan']->selectedIds, [7, 8, 9]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult234()['returning'], 'setting_id'), [7, 8, 9]],
    'attempt update row seven mutated' => [static fn (): mixed => array_column($attemptUpdateResult234()['tables']['app_settings'], 'key_value', 'setting_id')[7], 'theme:attempt234'],
    'attempt delete selected ids' => [static fn (): mixed => $attemptDeleteResult234()['plan']->selectedIds, [4, 3]],
    'attempt delete returning mutation order' => [static fn (): mixed => array_column($attemptDeleteResult234()['returning'], 'setting_id'), [3, 4]],
    'attempt delete removes cleanup rows' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult234()['tables']['app_settings'], 'setting_id'))), []],
    'retry update selected ids' => [static fn (): mixed => $retryUpdateResult234()['plan']->selectedIds, [7, 8, 9, 11]],
    'retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult234()['returning'], 'setting_id'), [7, 8, 9, 11]],
    'retry update pending flag' => [static fn (): mixed => array_column($retryUpdateResult234()['returning'], 'retry_pending'), [1, 0, 0, 0]],
    'retry update row eleven from original' => [static fn (): mixed => array_column($retryUpdateResult234()['tables']['app_settings'], 'key_value', 'setting_id')[11], 'cache:retry234'],
    'retry delete selected id' => [static fn (): mixed => $retryDeleteResult234()['plan']->selectedIds, [12]],
    'retry delete removes tenant module' => [static fn (): mixed => in_array(12, array_column($retryDeleteResult234()['tables']['app_settings'], 'setting_id'), true), false],

    'plan status' => [static fn (): mixed => $plan234()['status'], 'rowvalue-update-delete-returning-window-current-source-next234'],
    'plan savepoint' => [static fn (): mixed => $plan234()['savepoint'], 'app_settings_rowvalue_returning_window_next234'],
    'plan partition column' => [static fn (): mixed => $plan234()['partition_column'], 'tenant_id'],
    'plan order column' => [static fn (): mixed => $plan234()['order_column'], 'setting_id'],
    'plan rollback flags' => [static fn (): mixed => [$plan234()['rolled_back_to_savepoint'], $plan234()['retry_reads_savepoint_image'], $plan234()['savepoint_released_after_retry']], [true, true, true]],
    'plan attempt discarded count' => [static fn (): mixed => $plan234()['discarded_attempt_returning_count'], 5],
    'plan yielded count' => [static fn (): mixed => $plan234()['yielded_returning_count'], 5],
    'plan window row count' => [static fn (): mixed => $plan234()['window_row_count'], 5],
    'plan attempt current row seven mutated' => [static fn (): mixed => array_column($plan234()['attempt_current_source_tables']['app_settings'], 'key_value', 'setting_id')[7], 'theme:attempt234'],
    'plan attempt cleanup deleted' => [static fn (): mixed => in_array(3, array_column($plan234()['attempt_current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan234()['rollback_current_source_tables']['app_settings'], 'key_value', 'setting_id')[7], 'theme'],
    'plan rollback restores cleanup row three' => [static fn (): mixed => in_array(3, array_column($plan234()['rollback_current_source_tables']['app_settings'], 'setting_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[7], 'theme:retry234'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules:retry234'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[9], 'module:retry234'],
    'plan final row eleven retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[11], 'cache:retry234'],
    'plan final row twelve deleted' => [static fn (): mixed => in_array(12, array_column($plan234()['current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan final cleanup rows restored' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($plan234()['current_source_tables']['app_settings'], 'setting_id'))), [3, 4]],
    'plan next source equals current' => [static fn (): mixed => $plan234()['next_source_tables'], $plan234()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan234()['changed_tables_after_retry'], ['app_settings']],
    'plan row counts' => [static fn (): mixed => $plan234()['row_counts'], ['app_setting_targets' => 7, 'app_settings' => 11]],
    'plan attempt statement actions' => [static fn (): mixed => array_column($plan234()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan234()['retry_statements'], 'action'), ['update', 'delete']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan234()['attempt_statements'][0]['selected_ids'], $plan234()['attempt_statements'][1]['selected_ids']], [[7, 8, 9], [4, 3]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan234()['retry_statements'][0]['selected_ids'], $plan234()['retry_statements'][1]['selected_ids']], [[7, 8, 9, 11], [12]]],
    'plan retry update returning count' => [static fn (): mixed => $plan234()['retry_statements'][0]['returning_count'], 4],
    'plan retry delete returning count' => [static fn (): mixed => $plan234()['retry_statements'][1]['returning_count'], 1],
    'plan retry source rows original values' => [static fn (): mixed => array_column($plan234()['retry_statements'][0]['source_rows'], 'key_value'), ['theme', 'rules', 'module', 'cache']],
    'plan yielded ids' => [static fn (): mixed => array_column($plan234()['yielded_returning'], 'setting_id'), [7, 8, 9, 11, 12]],
    'plan yielded phases' => [static fn (): mixed => array_values(array_unique(array_column($plan234()['yielded_returning'], 'returning_phase'))), ['retry-next234']],
    'plan yielded statement ordinals' => [static fn (): mixed => array_column($plan234()['yielded_returning'], 'statement_ordinal'), [0, 0, 0, 0, 1]],
    'plan window ids sorted by partition' => [static fn (): mixed => array_column($plan234()['window_rows'], 'setting_id'), [7, 8, 9, 11, 12]],
    'plan window row numbers' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_row_number'), [1, 1, 2, 1, 2]],
    'plan window dense ranks' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_dense_rank'), [1, 1, 2, 1, 2]],
    'plan window partition sizes' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_partition_size'), [1, 2, 2, 2, 2]],
    'plan window lag names' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_lag_key_name'), [null, null, 'routing_rules', null, 'stale_cache']],
    'plan window lead names' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_lead_key_name'), [null, 'module_batch', null, 'tenant_module', null]],
    'plan window frame row_ids' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_frame_row_ids'), [[7], [8, 9], [8, 9], [11, 12], [11, 12]]],
    'plan partition two summary' => [static fn (): mixed => $plan234()['window_partition_summary']['2'], ['count' => 1, 'row_numbers' => [1], 'row_ids' => [7]]],
    'plan partition three summary' => [static fn (): mixed => $plan234()['window_partition_summary']['3'], ['count' => 2, 'row_numbers' => [1, 2], 'row_ids' => [8, 9]]],
    'plan partition four summary' => [static fn (): mixed => $plan234()['window_partition_summary']['4'], ['count' => 2, 'row_numbers' => [1, 2], 'row_ids' => [11, 12]]],
    'plan token lengths' => [static fn (): mixed => [strlen($plan234()['current_source_token']), strlen($plan234()['window_token'])], [64, 64]],
    'plan tokens differ' => [static fn (): mixed => $plan234()['current_source_token'] !== $plan234()['window_token'], true],
    'plan dependencies' => [static fn (): mixed => $plan234()['dependencies'], ['sqlite-rowvalue-update-returning-current-source-window-next234', 'sqlite-rowvalue-delete-returning-current-source-window-next234', 'sqlite-returning-stream-window-partition-retry-next234']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan234()['dependency_closure_next234'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan234()['non_overlap_next234'], 'avoids accepted next230-next231'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan234()['savepoint'], 'app_custom_returning_window_next234'],
    'custom partition column' => [static fn (): mixed => $customPlan234()['partition_column'], 'status'],
    'custom order column' => [static fn (): mixed => $customPlan234()['order_column'], 'key_name'],
    'custom window count' => [static fn (): mixed => $customPlan234()['window_row_count'], 4],
    'custom status partition summary' => [static fn (): mixed => $customPlan234()['window_partition_summary']['retry234']['count'], 4],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [], [$retryUpdate234], $unique234), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [], $unique234), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], $unique234, 'tenant_id', 'setting_id', 'setting_id', 'bad-name'), InvalidArgumentException::class],
    'malformed partition rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], $unique234, 'bad column'), InvalidArgumentException::class],
    'malformed missing partition column rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], $unique234, 'missing_column'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow(['app_settings' => ['bad']], [$attemptUpdate234], [$retryUpdate234], $unique234), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases234 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next234 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
