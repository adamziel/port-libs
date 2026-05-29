<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows250 = [
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
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => '_transient_cache', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'cache'],
];

$meta250 = [
    ['meta_id' => 501, 'meta_option_id' => 7, 'meta_key' => 'attempt_update', 'meta_value' => 'pending_theme'],
    ['meta_id' => 502, 'meta_option_id' => 8, 'meta_key' => 'attempt_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 503, 'meta_option_id' => 9, 'meta_key' => 'attempt_update', 'meta_value' => 'plugin_batch'],
    ['meta_id' => 504, 'meta_option_id' => 3, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_feed'],
    ['meta_id' => 505, 'meta_option_id' => 4, 'meta_key' => 'attempt_delete', 'meta_value' => '_transient_timeout_feed'],
    ['meta_id' => 506, 'meta_option_id' => 8, 'meta_key' => 'retry_update', 'meta_value' => 'rewrite_rules'],
    ['meta_id' => 507, 'meta_option_id' => 9, 'meta_key' => 'retry_update', 'meta_value' => 'plugin_batch'],
    ['meta_id' => 508, 'meta_option_id' => 10, 'meta_key' => 'retry_update', 'meta_value' => 'network_plugin'],
    ['meta_id' => 509, 'meta_option_id' => 2, 'meta_key' => 'retry_delete', 'meta_value' => 'home'],
    ['meta_id' => 510, 'meta_option_id' => 5, 'meta_key' => 'retry_delete', 'meta_value' => 'siteurl'],
    ['meta_id' => 511, 'meta_option_id' => 11, 'meta_key' => 'retry_delete', 'meta_value' => '_transient_cache'],
];

$tables250 = ['wp_options' => $rows250, 'wp_optionmeta' => $meta250];
$unique250 = [['blog_id', 'option_name']];

$attemptUpdate250 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt250', option_value || ':attempt250', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete250 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate250 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry250', option_value || ':retry250', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete250 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$attemptUpdateResult250 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate250, $tables250, 'option_id', $unique250);
$retryUpdateResult250 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate250, $tables250, 'option_id', $unique250);
$plan250 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250(
    $tables250,
    [$attemptUpdate250, $attemptDelete250],
    [$retryUpdate250, $retryDelete250],
    $unique250,
);
$customPlan250 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250(
    $tables250,
    [$attemptUpdate250],
    [$retryUpdate250],
    $unique250,
    'wp_custom_returning_window_next250',
);

$cases250 = [
    'parser attempt update row value source' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate250)['where'] ?? '', 'attempt_update'), true],
    'parser retry delete order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete250)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult250()['plan']->selectedIds, [9, 8, 7]],
    'direct retry update selected ids' => [static fn (): mixed => $retryUpdateResult250()['plan']->selectedIds, [10, 9, 8]],
    'direct retry update row ten value' => [static fn (): mixed => array_column($retryUpdateResult250()['tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry250'],

    'plan status' => [static fn (): mixed => $plan250()['status'], 'rowvalue-update-delete-returning-window-current-source-next250'],
    'plan savepoint' => [static fn (): mixed => $plan250()['savepoint'], 'wp_options_rowvalue_returning_window_next250'],
    'plan inherited next247 flag' => [static fn (): mixed => $plan250()['returning_window_current_source_next247'], true],
    'plan next250 flag' => [static fn (): mixed => $plan250()['returning_window_current_source_next250'], true],
    'plan transition count' => [static fn (): mixed => $plan250()['window_transition_chain_count_next244'], 9],
    'plan exclude group count' => [static fn (): mixed => $plan250()['window_exclude_group_count_next247'], 9],
    'plan exclude ties count' => [static fn (): mixed => $plan250()['window_exclude_ties_count_next250'], 9],
    'plan exclude ties rowids' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_rowid_next250'), [2, 3, 4, 5, 11, 7, 8, 9, 10]],
    'plan exclude ties classes' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_class_next250'), ['restart-only', 'discarded-only', 'discarded-only', 'restart-only', 'restart-only', 'discarded-only', 'replayed-after-rollback', 'replayed-after-rollback', 'restart-only']],
    'plan exclude ties partitions' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_partition_next250'), ['delete', 'delete', 'delete', 'delete', 'delete', 'update', 'update', 'update', 'update']],
    'plan current rows preserved' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_current_row_preserved_next250'), [true, true, true, true, true, true, true, true, true]],
    'plan removed peer counts' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_removed_peer_count_next250'), [2, 1, 1, 2, 2, 0, 1, 1, 0]],
    'plan frame counts' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_frame_count_next250'), [3, 4, 4, 3, 3, 4, 3, 3, 4]],
    'plan added current row counts' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_added_current_row_next250'), [1, 1, 1, 1, 1, 1, 1, 1, 1]],
    'plan delete restart removed ties' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][0]['exclude_ties_removed_peer_rowids_next250'], [5, 11]],
    'plan delete restart frame rowids keep current' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][0]['exclude_ties_frame_rowids_next250'], [2, 3, 4]],
    'plan delete discarded removed ties' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][1]['exclude_ties_removed_peer_rowids_next250'], [4]],
    'plan delete discarded frame rowids keep current' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][1]['exclude_ties_frame_rowids_next250'], [2, 3, 5, 11]],
    'plan update discarded removed ties empty' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][5]['exclude_ties_removed_peer_rowids_next250'], []],
    'plan update discarded frame rowids all rows' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][5]['exclude_ties_frame_rowids_next250'], [7, 8, 9, 10]],
    'plan update replay removed tie eight' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][6]['exclude_ties_removed_peer_rowids_next250'], [9]],
    'plan update replay frame rowids keep current' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][6]['exclude_ties_frame_rowids_next250'], [7, 8, 10]],
    'plan update replay removed tie nine' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][7]['exclude_ties_removed_peer_rowids_next250'], [8]],
    'plan update replay frame rowids second peer' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][7]['exclude_ties_frame_rowids_next250'], [7, 9, 10]],
    'plan update restart removed ties empty' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][8]['exclude_ties_removed_peer_rowids_next250'], []],
    'plan update restart frame rowids all rows' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][8]['exclude_ties_frame_rowids_next250'], [7, 8, 9, 10]],
    'plan replay ids' => [static fn (): mixed => $plan250()['window_exclude_ties_replayed_ids_next250'], [8, 9]],
    'plan restart ids' => [static fn (): mixed => $plan250()['window_exclude_ties_restart_ids_next250'], [2, 5, 11, 10]],
    'plan discarded ids' => [static fn (): mixed => $plan250()['window_exclude_ties_discarded_ids_next250'], [3, 4, 7]],
    'plan summary count' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['exclude_ties_count'], 9],
    'plan summary preserved count' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['current_rows_preserved'], 9],
    'plan summary rows with ties' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['rows_with_removed_ties'], 7],
    'plan summary removed tie count' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['removed_tie_count'], 10],
    'plan summary replay count' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['replayed-after-rollback'], 2],
    'plan summary restart count' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['restart-only'], 4],
    'plan summary discarded count' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['discarded-only'], 3],
    'plan summary delete rowids' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['rowids_by_partition']['delete'], [2, 3, 4, 5, 11]],
    'plan summary update rowids' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['rowids_by_partition']['update'], [7, 8, 9, 10]],
    'plan summary delete frame counts' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['frame_counts_by_partition']['delete'], [3, 4, 4, 3, 3]],
    'plan summary update frame counts' => [static fn (): mixed => $plan250()['window_exclude_ties_summary_next250']['frame_counts_by_partition']['update'], [4, 3, 3, 4]],
    'plan first current value absent' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][0]['exclude_ties_current_value_next250'], null],
    'plan replay current value' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][7]['exclude_ties_current_value_next250'], 'plugin:attempt250'],
    'plan replay next value' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][7]['exclude_ties_next_value_next250'], 'plugin:retry250'],
    'plan restart next value' => [static fn (): mixed => $plan250()['window_exclude_ties_rows_next250'][8]['exclude_ties_next_value_next250'], 'network:retry250'],
    'plan boundaries' => [static fn (): mixed => array_column($plan250()['window_exclude_ties_rows_next250'], 'exclude_ties_boundary_next250'), ['first-row', 'middle-row', 'middle-row', 'middle-row', 'last-row', 'first-row', 'middle-row', 'middle-row', 'last-row']],
    'plan receipts' => [static fn (): mixed => $plan250()['window_exclude_ties_receipts_next250'], ['delete:restart-only:2:2:3', 'delete:discarded-only:3:1:4', 'delete:discarded-only:4:1:4', 'delete:restart-only:5:2:3', 'delete:restart-only:11:2:3', 'update:discarded-only:7:0:4', 'update:replayed-after-rollback:8:1:3', 'update:replayed-after-rollback:9:1:3', 'update:restart-only:10:0:4']],
    'plan fence frame mode' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['frame_mode'], 'GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE TIES'],
    'plan fence source count' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['source_transition_count'], 9],
    'plan fence group count' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['exclude_group_count'], 9],
    'plan fence ties count' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['exclude_ties_count'], 9],
    'plan fence current row preserved' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['current_row_preserved'], true],
    'plan fence peer ties removed' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['peer_ties_removed'], true],
    'plan fence digest lengths' => [static fn (): mixed => [strlen($plan250()['window_exclude_ties_fence_next250']['exclude_ties_digest']), strlen($plan250()['window_exclude_ties_fence_next250']['exclude_group_digest'])], [64, 64]],
    'plan fence digest differs from group' => [static fn (): mixed => $plan250()['window_exclude_ties_fence_next250']['exclude_ties_digest'] !== $plan250()['window_exclude_ties_fence_next250']['exclude_group_digest'], true],
    'plan fence rollback flags' => [static fn (): mixed => [$plan250()['window_exclude_ties_fence_next250']['rolled_back_to_savepoint'], $plan250()['window_exclude_ties_fence_next250']['retry_reads_savepoint_image']], [true, true]],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan250()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry250'],
    'plan final row ten retry' => [static fn (): mixed => array_column($plan250()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry250'],
    'plan final row two deleted' => [static fn (): mixed => in_array(2, array_column($plan250()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row three restored' => [static fn (): mixed => in_array(3, array_column($plan250()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan dependencies' => [static fn (): mixed => $plan250()['dependencies'], ['sqlite-rowvalue-returning-window-exclude-ties-next250', 'sqlite-rowvalue-returning-current-row-preserved-next250', 'wordpress-rowvalue-returning-window-current-source-next250']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan250()['dependency_closure_next250'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan250()['non_overlap_next250'], 'EXCLUDE TIES'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan250()['savepoint'], 'wp_custom_returning_window_next250'],
    'custom ties count' => [static fn (): mixed => $customPlan250()['window_exclude_ties_count_next250'], 4],
    'custom replay ids' => [static fn (): mixed => $customPlan250()['window_exclude_ties_replayed_ids_next250'], [8, 9]],
    'custom discarded ids' => [static fn (): mixed => $customPlan250()['window_exclude_ties_discarded_ids_next250'], [7]],
    'custom restart ids' => [static fn (): mixed => $customPlan250()['window_exclude_ties_restart_ids_next250'], [10]],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250($tables250, [], [$retryUpdate250], $unique250), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250($tables250, [$attemptUpdate250], [], $unique250), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250($tables250, [$attemptUpdate250], [$retryUpdate250], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250($tables250, [$attemptUpdate250], [$retryUpdate250], $unique250, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext250(['wp_options' => ['bad']], [$attemptUpdate250], [$retryUpdate250], $unique250), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases250 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next250 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
