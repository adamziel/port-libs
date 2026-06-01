<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows235 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta235 = [
    ['meta_id' => 401, 'meta_option_id' => 7, 'meta_key' => 'attempt_update', 'meta_value' => 'pending_theme'],
    ['meta_id' => 402, 'meta_option_id' => 8, 'meta_key' => 'attempt_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 403, 'meta_option_id' => 3, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_feed'],
    ['meta_id' => 404, 'meta_option_id' => 4, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_timeout_feed'],
    ['meta_id' => 405, 'meta_option_id' => 8, 'meta_key' => 'retry_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 406, 'meta_option_id' => 10, 'meta_key' => 'retry_update', 'meta_value' => 'network_plugin'],
    ['meta_id' => 407, 'meta_option_id' => 2, 'meta_key' => 'retry_delete', 'meta_value' => 'home'],
    ['meta_id' => 408, 'meta_option_id' => 5, 'meta_key' => 'retry_delete', 'meta_value' => 'siteurl'],
];

$tables235 = ['wp_options' => $rows235, 'wp_optionmeta' => $meta235];
$unique235 = [['blog_id', 'option_name']];

$attemptUpdate235 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt235', option_value || ':attempt235', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete235 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate235 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry235', option_value || ':retry235', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete235 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$attemptUpdateResult235 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate235, $tables235, 'option_id', $unique235);
$attemptDeleteResult235 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete235, $attemptUpdateResult235()['tables'], 'option_id', $unique235);
$retryUpdateResult235 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate235, $tables235, 'option_id', $unique235);
$retryDeleteResult235 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete235, $retryUpdateResult235()['tables'], 'option_id', $unique235);
$plan235 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests(
    $tables235,
    [$attemptUpdate235, $attemptDelete235],
    [$retryUpdate235, $retryDelete235],
    $unique235,
    rowIdColumn: 'option_id',
);
$customPlan235 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests(
    $tables235,
    [$attemptUpdate235],
    [$retryUpdate235],
    $unique235,
    'app_custom_returning_window235',
    'option_id',
);

$cases235 = [
    'parser attempt update row value subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate235)['where'], 'SELECT meta_option_id'), true],
    'parser attempt delete row value subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDelete235)['where'], 'attempt_delete'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate235)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete235)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult235()['plan']->selectedIds, [8, 7]],
    'direct attempt update mutation ids sorted' => [static fn (): mixed => $attemptUpdateResult235()['plan']->mutationIds, [7, 8]],
    'direct attempt update returning order' => [static fn (): mixed => array_column($attemptUpdateResult235()['returning'], 'option_id'), [7, 8]],
    'direct attempt update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult235()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt235'],
    'direct attempt update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult235()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt235'],
    'direct attempt delete selected ids' => [static fn (): mixed => $attemptDeleteResult235()['plan']->selectedIds, [3, 4]],
    'direct attempt delete returning ids' => [static fn (): mixed => array_column($attemptDeleteResult235()['returning'], 'option_id'), [3, 4]],
    'direct attempt delete removes transient feed' => [static fn (): mixed => in_array(3, array_column($attemptDeleteResult235()['tables']['wp_options'], 'option_id'), true), false],
    'direct attempt delete removes transient timeout' => [static fn (): mixed => in_array(4, array_column($attemptDeleteResult235()['tables']['wp_options'], 'option_id'), true), false],
    'direct retry update selected ids' => [static fn (): mixed => $retryUpdateResult235()['plan']->selectedIds, [10, 8]],
    'direct retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult235()['returning'], 'option_id'), [8, 10]],
    'direct retry update row ten value' => [static fn (): mixed => array_column($retryUpdateResult235()['tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry235'],
    'direct retry delete selected ids' => [static fn (): mixed => $retryDeleteResult235()['plan']->selectedIds, [5, 2]],
    'direct retry delete returning ids' => [static fn (): mixed => array_column($retryDeleteResult235()['returning'], 'option_id'), [2, 5]],
    'direct retry delete removes home' => [static fn (): mixed => in_array(2, array_column($retryDeleteResult235()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan235()['status'], 'rowvalue-update-delete-returning-window-current-source-next235'],
    'plan savepoint' => [static fn (): mixed => $plan235()['savepoint'], 'app_settings_rowvalue_returning_window_next235'],
    'plan window flag' => [static fn (): mixed => $plan235()['returning_window_current_source_next235'], true],
    'plan partition keys' => [static fn (): mixed => $plan235()['window_partition_keys_next235'], ['phase', 'action']],
    'plan order keys' => [static fn (): mixed => $plan235()['window_order_keys_next235'], ['option_id']],
    'plan rollback flags' => [static fn (): mixed => [$plan235()['rolled_back_to_savepoint'], $plan235()['retry_reads_savepoint_image'], $plan235()['savepoint_released_after_retry']], [true, true, true]],
    'plan discarded count' => [static fn (): mixed => $plan235()['discarded_attempt_window_count_next235'], 4],
    'plan yielded count' => [static fn (): mixed => $plan235()['yielded_retry_window_count_next235'], 4],
    'plan base discarded count matches' => [static fn (): mixed => $plan235()['discarded_attempt_returning_count'], 4],
    'plan base yielded count matches' => [static fn (): mixed => $plan235()['yielded_after_retry_count'], 4],
    'plan discarded window ids sorted by phase action id' => [static fn (): mixed => $plan235()['discarded_attempt_window_ids_next235'], [3, 4, 7, 8]],
    'plan yielded window ids sorted by phase action id' => [static fn (): mixed => $plan235()['yielded_retry_window_ids_next235'], [2, 5, 8, 10]],
    'plan discarded first partition rows' => [static fn (): mixed => array_column($plan235()['discarded_attempt_window_rows_next235'], 'window_first_in_partition_next235'), [true, false, true, false]],
    'plan yielded first partition rows' => [static fn (): mixed => array_column($plan235()['yielded_retry_window_rows_next235'], 'window_first_in_partition_next235'), [true, false, true, false]],
    'plan discarded row numbers' => [static fn (): mixed => array_column($plan235()['discarded_attempt_window_rows_next235'], 'window_row_number_next235'), [1, 2, 3, 4]],
    'plan yielded row numbers' => [static fn (): mixed => array_column($plan235()['yielded_retry_window_rows_next235'], 'window_row_number_next235'), [1, 2, 3, 4]],
    'plan discarded partition row numbers' => [static fn (): mixed => array_column($plan235()['discarded_attempt_window_rows_next235'], 'window_partition_row_number_next235'), [1, 2, 1, 2]],
    'plan yielded partition row numbers' => [static fn (): mixed => array_column($plan235()['yielded_retry_window_rows_next235'], 'window_partition_row_number_next235'), [1, 2, 1, 2]],
    'plan discarded actions' => [static fn (): mixed => array_column($plan235()['discarded_attempt_window_rows_next235'], 'window_action_next235'), ['delete', 'delete', 'update', 'update']],
    'plan yielded actions' => [static fn (): mixed => array_column($plan235()['yielded_retry_window_rows_next235'], 'window_action_next235'), ['delete', 'delete', 'update', 'update']],
    'plan discarded stream marker' => [static fn (): mixed => array_unique(array_column($plan235()['discarded_attempt_window_rows_next235'], 'window_stream_next235')), ['discarded-attempt-window-next235']],
    'plan yielded stream marker' => [static fn (): mixed => array_unique(array_column($plan235()['yielded_retry_window_rows_next235'], 'window_stream_next235')), ['yielded-retry-window-next235']],
    'plan discarded delete partition' => [static fn (): mixed => $plan235()['discarded_attempt_window_rows_next235'][0]['window_partition_next235'], 'attempt-before-rollback-next212:delete'],
    'plan yielded update partition' => [static fn (): mixed => $plan235()['yielded_retry_window_rows_next235'][2]['window_partition_next235'], 'retry-after-rollback-next212:update'],
    'plan discarded statement ordinals' => [static fn (): mixed => array_column($plan235()['discarded_attempt_window_rows_next235'], 'window_statement_ordinal_next235'), [1, 1, 0, 0]],
    'plan yielded statement ordinals' => [static fn (): mixed => array_column($plan235()['yielded_retry_window_rows_next235'], 'window_statement_ordinal_next235'), [1, 1, 0, 0]],
    'plan boundary counts' => [static fn (): mixed => $plan235()['window_yield_boundary_next235'], ['discarded_attempt_rows' => 4, 'yielded_retry_rows' => 4, 'rollback_to_savepoint' => true, 'retry_reads_savepoint_image' => true]],
    'plan attempt row seven discarded not final' => [static fn (): mixed => array_column($plan235()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan attempt deleted row three restored' => [static fn (): mixed => in_array(3, array_column($plan235()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan retry row eight final' => [static fn (): mixed => array_column($plan235()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry235'],
    'plan retry row ten final' => [static fn (): mixed => array_column($plan235()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry235'],
    'plan retry deleted row two absent' => [static fn (): mixed => in_array(2, array_column($plan235()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan retry deleted row five absent' => [static fn (): mixed => in_array(5, array_column($plan235()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan235()['next_source_tables'], $plan235()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan235()['changed_tables_after_retry'], ['wp_options']],
    'plan row counts' => [static fn (): mixed => $plan235()['row_counts'], ['wp_optionmeta' => 8, 'wp_options' => 8]],
    'plan attempt changes count' => [static fn (): mixed => $plan235()['attempt_changes_before_rollback'], 4],
    'plan retry changes count' => [static fn (): mixed => $plan235()['retry_changes_after_rollback'], 4],
    'plan digests are sha256' => [static fn (): mixed => [strlen($plan235()['discarded_attempt_window_digest_next235']), strlen($plan235()['yielded_retry_window_digest_next235'])], [64, 64]],
    'plan digests differ' => [static fn (): mixed => $plan235()['discarded_attempt_window_digest_next235'] !== $plan235()['yielded_retry_window_digest_next235'], true],
    'plan dependency update window' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-window-current-source-next235', $plan235()['dependencies'], true), true],
    'plan dependency delete window' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-window-current-source-next235', $plan235()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan235()['dependency_closure_next235'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan235()['non_overlap_next235'], 'avoids accepted next231'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan235()['savepoint'], 'app_custom_returning_window235'],
    'custom discarded window count' => [static fn (): mixed => $customPlan235()['discarded_attempt_window_count_next235'], 2],
    'custom yielded window count' => [static fn (): mixed => $customPlan235()['yielded_retry_window_count_next235'], 2],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests($tables235, [], [$retryUpdate235], $unique235), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests($tables235, [$attemptUpdate235], [], $unique235), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests($tables235, [$attemptUpdate235], [$retryUpdate235], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests($tables235, [$attemptUpdate235], [$retryUpdate235], $unique235, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests(['wp_options' => ['bad']], [$attemptUpdate235], [$retryUpdate235], $unique235), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases235 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next235 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
