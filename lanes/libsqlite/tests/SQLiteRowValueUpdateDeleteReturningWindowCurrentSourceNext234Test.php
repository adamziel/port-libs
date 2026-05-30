<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows234 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => '_transient_cache', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'cache'],
    ['option_id' => 12, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta234 = [
    ['meta_id' => 201, 'meta_option_id' => 7, 'meta_key' => 'retry_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 202, 'meta_option_id' => 8, 'meta_key' => 'retry_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 203, 'meta_option_id' => 9, 'meta_key' => 'retry_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 204, 'meta_option_id' => 11, 'meta_key' => 'retry_batch', 'meta_value' => '_transient_cache', 'priority' => 40],
    ['meta_id' => 205, 'meta_option_id' => 3, 'meta_key' => 'attempt_cleanup', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 206, 'meta_option_id' => 4, 'meta_key' => 'attempt_cleanup', 'meta_value' => '_transient_timeout_feed', 'priority' => 15],
    ['meta_id' => 207, 'meta_option_id' => 12, 'meta_key' => 'retry_cleanup', 'meta_value' => 'network_plugin', 'priority' => 25],
];

$tables234 = ['wp_options' => $rows234, 'wp_optionmeta' => $meta234];
$unique234 = [['blog_id', 'option_name']];

$attemptUpdate234 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt234', option_value || ':attempt234', bytes + 1) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$attemptDelete234 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_cleanup' ORDER BY priority ASC) RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";
$retryUpdate234 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry234', option_value || ':retry234', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT -1) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry234', 'pending_theme') AS retry_pending ORDER BY option_id";
$retryDelete234 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_cleanup' ORDER BY priority ASC) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate234, $tables234, 'option_id', $unique234);
$attemptDeleteResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete234, $attemptUpdateResult234()['tables'], 'option_id', $unique234);
$retryUpdateResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate234, $tables234, 'option_id', $unique234);
$retryDeleteResult234 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete234, $retryUpdateResult234()['tables'], 'option_id', $unique234);
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
    'option_name',
    'option_id',
    'wp_custom_returning_window_next234',
);

$cases234 = [
    'parser attempt subquery retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate234)['where'] ?? '', "meta_key = 'retry_batch'"), true],
    'parser retry negative limit retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate234)['where'] ?? '', 'LIMIT -1'), true],
    'parser retry returning row-value flag retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate234)['returning'], 'retry_pending'), true],
    'attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult234()['plan']->selectedIds, [7, 8, 9]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult234()['returning'], 'option_id'), [7, 8, 9]],
    'attempt update row seven mutated' => [static fn (): mixed => array_column($attemptUpdateResult234()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt234'],
    'attempt delete selected ids' => [static fn (): mixed => $attemptDeleteResult234()['plan']->selectedIds, [4, 3]],
    'attempt delete returning mutation order' => [static fn (): mixed => array_column($attemptDeleteResult234()['returning'], 'option_id'), [3, 4]],
    'attempt delete removes cleanup rows' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult234()['tables']['wp_options'], 'option_id'))), []],
    'retry update selected ids' => [static fn (): mixed => $retryUpdateResult234()['plan']->selectedIds, [7, 8, 9, 11]],
    'retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult234()['returning'], 'option_id'), [7, 8, 9, 11]],
    'retry update pending flag' => [static fn (): mixed => array_column($retryUpdateResult234()['returning'], 'retry_pending'), [1, 0, 0, 0]],
    'retry update row eleven from original' => [static fn (): mixed => array_column($retryUpdateResult234()['tables']['wp_options'], 'option_value', 'option_id')[11], 'cache:retry234'],
    'retry delete selected id' => [static fn (): mixed => $retryDeleteResult234()['plan']->selectedIds, [12]],
    'retry delete removes network plugin' => [static fn (): mixed => in_array(12, array_column($retryDeleteResult234()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan234()['status'], 'rowvalue-update-delete-returning-window-current-source-next234'],
    'plan savepoint' => [static fn (): mixed => $plan234()['savepoint'], 'app_settings_rowvalue_returning_window_next234'],
    'plan partition column' => [static fn (): mixed => $plan234()['partition_column'], 'blog_id'],
    'plan order column' => [static fn (): mixed => $plan234()['order_column'], 'option_id'],
    'plan rollback flags' => [static fn (): mixed => [$plan234()['rolled_back_to_savepoint'], $plan234()['retry_reads_savepoint_image'], $plan234()['savepoint_released_after_retry']], [true, true, true]],
    'plan attempt discarded count' => [static fn (): mixed => $plan234()['discarded_attempt_returning_count'], 5],
    'plan yielded count' => [static fn (): mixed => $plan234()['yielded_returning_count'], 5],
    'plan window row count' => [static fn (): mixed => $plan234()['window_row_count'], 5],
    'plan attempt current row seven mutated' => [static fn (): mixed => array_column($plan234()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt234'],
    'plan attempt cleanup deleted' => [static fn (): mixed => in_array(3, array_column($plan234()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan234()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores cleanup row three' => [static fn (): mixed => in_array(3, array_column($plan234()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry234'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry234'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry234'],
    'plan final row eleven retry' => [static fn (): mixed => array_column($plan234()['current_source_tables']['wp_options'], 'option_value', 'option_id')[11], 'cache:retry234'],
    'plan final row twelve deleted' => [static fn (): mixed => in_array(12, array_column($plan234()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final cleanup rows restored' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($plan234()['current_source_tables']['wp_options'], 'option_id'))), [3, 4]],
    'plan next source equals current' => [static fn (): mixed => $plan234()['next_source_tables'], $plan234()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan234()['changed_tables_after_retry'], ['wp_options']],
    'plan row counts' => [static fn (): mixed => $plan234()['row_counts'], ['wp_optionmeta' => 7, 'wp_options' => 11]],
    'plan attempt statement actions' => [static fn (): mixed => array_column($plan234()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan234()['retry_statements'], 'action'), ['update', 'delete']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan234()['attempt_statements'][0]['selected_ids'], $plan234()['attempt_statements'][1]['selected_ids']], [[7, 8, 9], [4, 3]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan234()['retry_statements'][0]['selected_ids'], $plan234()['retry_statements'][1]['selected_ids']], [[7, 8, 9, 11], [12]]],
    'plan retry update returning count' => [static fn (): mixed => $plan234()['retry_statements'][0]['returning_count'], 4],
    'plan retry delete returning count' => [static fn (): mixed => $plan234()['retry_statements'][1]['returning_count'], 1],
    'plan retry source rows original values' => [static fn (): mixed => array_column($plan234()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules', 'plugin', 'cache']],
    'plan yielded ids' => [static fn (): mixed => array_column($plan234()['yielded_returning'], 'option_id'), [7, 8, 9, 11, 12]],
    'plan yielded phases' => [static fn (): mixed => array_values(array_unique(array_column($plan234()['yielded_returning'], 'returning_phase'))), ['retry-next234']],
    'plan yielded statement ordinals' => [static fn (): mixed => array_column($plan234()['yielded_returning'], 'statement_ordinal'), [0, 0, 0, 0, 1]],
    'plan window ids sorted by partition' => [static fn (): mixed => array_column($plan234()['window_rows'], 'option_id'), [7, 8, 9, 11, 12]],
    'plan window row numbers' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_row_number'), [1, 1, 2, 1, 2]],
    'plan window dense ranks' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_dense_rank'), [1, 1, 2, 1, 2]],
    'plan window partition sizes' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_partition_size'), [1, 2, 2, 2, 2]],
    'plan window lag names' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_lag_option_name'), [null, null, 'rewrite_rules', null, '_transient_cache']],
    'plan window lead names' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_lead_option_name'), [null, 'plugin_batch', null, 'network_plugin', null]],
    'plan window frame rowids' => [static fn (): mixed => array_column($plan234()['window_rows'], 'window_frame_rowids'), [[7], [8, 9], [8, 9], [11, 12], [11, 12]]],
    'plan partition two summary' => [static fn (): mixed => $plan234()['window_partition_summary']['2'], ['count' => 1, 'row_numbers' => [1], 'rowids' => [7]]],
    'plan partition three summary' => [static fn (): mixed => $plan234()['window_partition_summary']['3'], ['count' => 2, 'row_numbers' => [1, 2], 'rowids' => [8, 9]]],
    'plan partition four summary' => [static fn (): mixed => $plan234()['window_partition_summary']['4'], ['count' => 2, 'row_numbers' => [1, 2], 'rowids' => [11, 12]]],
    'plan token lengths' => [static fn (): mixed => [strlen($plan234()['current_source_token']), strlen($plan234()['window_token'])], [64, 64]],
    'plan tokens differ' => [static fn (): mixed => $plan234()['current_source_token'] !== $plan234()['window_token'], true],
    'plan dependencies' => [static fn (): mixed => $plan234()['dependencies'], ['sqlite-rowvalue-update-returning-current-source-window-next234', 'sqlite-rowvalue-delete-returning-current-source-window-next234', 'sqlite-returning-stream-window-partition-retry-next234']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan234()['dependency_closure_next234'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan234()['non_overlap_next234'], 'avoids accepted next230-next231'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan234()['savepoint'], 'wp_custom_returning_window_next234'],
    'custom partition column' => [static fn (): mixed => $customPlan234()['partition_column'], 'status'],
    'custom order column' => [static fn (): mixed => $customPlan234()['order_column'], 'option_name'],
    'custom window count' => [static fn (): mixed => $customPlan234()['window_row_count'], 4],
    'custom status partition summary' => [static fn (): mixed => $customPlan234()['window_partition_summary']['retry234']['count'], 4],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [], [$retryUpdate234], $unique234), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [], $unique234), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], $unique234, 'blog_id', 'option_id', 'option_id', 'bad-name'), InvalidArgumentException::class],
    'malformed partition rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], $unique234, 'bad column'), InvalidArgumentException::class],
    'malformed missing partition column rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow($tables234, [$attemptUpdate234], [$retryUpdate234], $unique234, 'missing_column'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow(['wp_options' => ['bad']], [$attemptUpdate234], [$retryUpdate234], $unique234), InvalidArgumentException::class],
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
