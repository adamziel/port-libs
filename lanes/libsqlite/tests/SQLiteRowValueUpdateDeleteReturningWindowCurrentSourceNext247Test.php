<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows247 = [
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

$meta247 = [
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

$tables247 = ['wp_options' => $rows247, 'wp_optionmeta' => $meta247];
$unique247 = [['blog_id', 'option_name']];

$attemptUpdate247 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt247', option_value || ':attempt247', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete247 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate247 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry247', option_value || ':retry247', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete247 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$attemptUpdateResult247 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate247, $tables247, 'option_id', $unique247);
$retryUpdateResult247 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate247, $tables247, 'option_id', $unique247);
$plan247 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute(
    $tables247,
    [$attemptUpdate247, $attemptDelete247],
    [$retryUpdate247, $retryDelete247],
    $unique247,
);
$customPlan247 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute(
    $tables247,
    [$attemptUpdate247],
    [$retryUpdate247],
    $unique247,
    'wp_custom_returning_window_next247',
);

$cases247 = [
    'parser attempt update row value subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate247)['where'] ?? '', 'attempt_update'), true],
    'parser retry delete order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete247)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult247()['plan']->selectedIds, [9, 8, 7]],
    'direct retry update selected ids' => [static fn (): mixed => $retryUpdateResult247()['plan']->selectedIds, [10, 9, 8]],
    'direct retry update row ten value' => [static fn (): mixed => array_column($retryUpdateResult247()['tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry247'],

    'plan status' => [static fn (): mixed => $plan247()['status'], 'rowvalue-update-delete-returning-window-current-source-next247'],
    'plan savepoint' => [static fn (): mixed => $plan247()['savepoint'], 'wp_options_rowvalue_returning_window_next247'],
    'plan inherited next244 flag' => [static fn (): mixed => $plan247()['returning_window_current_source_next244'], true],
    'plan next247 flag' => [static fn (): mixed => $plan247()['returning_window_current_source_next247'], true],
    'plan transition count' => [static fn (): mixed => $plan247()['window_transition_chain_count_next244'], 9],
    'plan exclude group count' => [static fn (): mixed => $plan247()['window_exclude_group_count_next247'], 9],
    'plan exclude group rowids' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_rowid_next247'), [2, 3, 4, 5, 11, 7, 8, 9, 10]],
    'plan exclude group classes' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_class_next247'), ['restart-only', 'discarded-only', 'discarded-only', 'restart-only', 'restart-only', 'discarded-only', 'replayed-after-rollback', 'replayed-after-rollback', 'restart-only']],
    'plan exclude group partitions' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_partition_next247'), ['delete', 'delete', 'delete', 'delete', 'delete', 'update', 'update', 'update', 'update']],
    'plan exclude group keys' => [static fn (): mixed => $plan247()['window_exclude_group_keys_next247'], ['delete:restart-only:2', 'delete:discarded-only:3', 'delete:discarded-only:4', 'delete:restart-only:5', 'delete:restart-only:11', 'update:discarded-only:7', 'update:replayed-after-rollback:8', 'update:replayed-after-rollback:9', 'update:restart-only:10']],
    'plan peer counts' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_peer_count_next247'), [3, 2, 2, 3, 3, 1, 2, 2, 1]],
    'plan frame counts' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_frame_count_next247'), [2, 3, 3, 2, 2, 3, 2, 2, 3]],
    'plan current class removed' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_current_class_removed_next247'), [true, true, true, true, true, true, true, true, true]],
    'plan delete restart peer rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][0]['exclude_group_peer_rowids_next247'], [2, 5, 11]],
    'plan delete restart frame rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][0]['exclude_group_frame_rowids_next247'], [3, 4]],
    'plan delete discarded peer rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][1]['exclude_group_peer_rowids_next247'], [3, 4]],
    'plan delete discarded frame rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][1]['exclude_group_frame_rowids_next247'], [2, 5, 11]],
    'plan update discarded peer rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][5]['exclude_group_peer_rowids_next247'], [7]],
    'plan update discarded frame rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][5]['exclude_group_frame_rowids_next247'], [8, 9, 10]],
    'plan update replay peer rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][6]['exclude_group_peer_rowids_next247'], [8, 9]],
    'plan update replay frame rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][6]['exclude_group_frame_rowids_next247'], [7, 10]],
    'plan update restart peer rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][8]['exclude_group_peer_rowids_next247'], [10]],
    'plan update restart frame rowids' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][8]['exclude_group_frame_rowids_next247'], [7, 8, 9]],
    'plan replay frame counts' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_replayed_frame_count_next247'), [0, 0, 0, 0, 0, 2, 0, 0, 2]],
    'plan restart frame counts' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_restart_frame_count_next247'), [0, 3, 3, 0, 0, 1, 1, 1, 0]],
    'plan discarded frame counts' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_discarded_frame_count_next247'), [2, 0, 0, 2, 2, 0, 1, 1, 1]],
    'plan replay ids' => [static fn (): mixed => $plan247()['window_exclude_group_replayed_ids_next247'], [8, 9]],
    'plan restart ids' => [static fn (): mixed => $plan247()['window_exclude_group_restart_ids_next247'], [2, 5, 11, 10]],
    'plan discarded ids' => [static fn (): mixed => $plan247()['window_exclude_group_discarded_ids_next247'], [3, 4, 7]],
    'plan summary count' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['exclude_group_count'], 9],
    'plan summary empty frames' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['empty_frames'], 0],
    'plan summary non empty frames' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['non_empty_frames'], 9],
    'plan summary replay count' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['replayed-after-rollback'], 2],
    'plan summary restart count' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['restart-only'], 4],
    'plan summary discarded count' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['discarded-only'], 3],
    'plan summary delete rowids' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['rowids_by_partition']['delete'], [2, 3, 4, 5, 11]],
    'plan summary update rowids' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['rowids_by_partition']['update'], [7, 8, 9, 10]],
    'plan summary delete frame counts' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['frame_counts_by_partition']['delete'], [2, 3, 3, 2, 2]],
    'plan summary update frame counts' => [static fn (): mixed => $plan247()['window_exclude_group_summary_next247']['frame_counts_by_partition']['update'], [3, 2, 2, 3]],
    'plan first current value absent' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][0]['exclude_group_current_value_next247'], null],
    'plan replay current value' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][7]['exclude_group_current_value_next247'], 'plugin:attempt247'],
    'plan replay next value' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][7]['exclude_group_next_value_next247'], 'plugin:retry247'],
    'plan restart next value' => [static fn (): mixed => $plan247()['window_exclude_group_rows_next247'][8]['exclude_group_next_value_next247'], 'network:retry247'],
    'plan boundaries' => [static fn (): mixed => array_column($plan247()['window_exclude_group_rows_next247'], 'exclude_group_boundary_next247'), ['first-row', 'middle-row', 'middle-row', 'middle-row', 'last-row', 'first-row', 'middle-row', 'middle-row', 'last-row']],
    'plan fence frame mode' => [static fn (): mixed => $plan247()['window_exclude_group_fence_next247']['frame_mode'], 'GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE GROUP'],
    'plan fence source count' => [static fn (): mixed => $plan247()['window_exclude_group_fence_next247']['source_transition_count'], 9],
    'plan fence group count' => [static fn (): mixed => $plan247()['window_exclude_group_fence_next247']['exclude_group_count'], 9],
    'plan fence digest lengths' => [static fn (): mixed => [strlen($plan247()['window_exclude_group_fence_next247']['excluded_group_digest']), strlen($plan247()['window_exclude_group_fence_next247']['transition_digest'])], [64, 64]],
    'plan fence digest differs' => [static fn (): mixed => $plan247()['window_exclude_group_fence_next247']['excluded_group_digest'] !== $plan247()['window_exclude_group_fence_next247']['transition_digest'], true],
    'plan fence rollback flags' => [static fn (): mixed => [$plan247()['window_exclude_group_fence_next247']['rolled_back_to_savepoint'], $plan247()['window_exclude_group_fence_next247']['retry_reads_savepoint_image']], [true, true]],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan247()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry247'],
    'plan final row ten retry' => [static fn (): mixed => array_column($plan247()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry247'],
    'plan final row two deleted' => [static fn (): mixed => in_array(2, array_column($plan247()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row three restored' => [static fn (): mixed => in_array(3, array_column($plan247()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan dependencies' => [static fn (): mixed => $plan247()['dependencies'], ['sqlite-rowvalue-returning-window-exclude-group-next247', 'sqlite-rowvalue-returning-transition-peer-groups-next247', 'wordpress-rowvalue-returning-window-current-source-next247']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan247()['dependency_closure_next247'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan247()['non_overlap_next247'], 'GROUPS EXCLUDE GROUP'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan247()['savepoint'], 'wp_custom_returning_window_next247'],
    'custom group count' => [static fn (): mixed => $customPlan247()['window_exclude_group_count_next247'], 4],
    'custom replay ids' => [static fn (): mixed => $customPlan247()['window_exclude_group_replayed_ids_next247'], [8, 9]],
    'custom discarded ids' => [static fn (): mixed => $customPlan247()['window_exclude_group_discarded_ids_next247'], [7]],
    'custom restart ids' => [static fn (): mixed => $customPlan247()['window_exclude_group_restart_ids_next247'], [10]],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute($tables247, [], [$retryUpdate247], $unique247), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute($tables247, [$attemptUpdate247], [], $unique247), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute($tables247, [$attemptUpdate247], [$retryUpdate247], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute($tables247, [$attemptUpdate247], [$retryUpdate247], $unique247, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute(['wp_options' => ['bad']], [$attemptUpdate247], [$retryUpdate247], $unique247), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases247 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next247 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
