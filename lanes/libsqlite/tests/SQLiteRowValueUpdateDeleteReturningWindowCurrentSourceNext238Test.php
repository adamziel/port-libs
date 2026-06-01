<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows238 = [
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

$meta238 = [
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

$tables238 = ['wp_options' => $rows238, 'wp_optionmeta' => $meta238];
$unique238 = [['blog_id', 'option_name']];

$attemptUpdate238 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt238', option_value || ':attempt238', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete238 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate238 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry238', option_value || ':retry238', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete238 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$attemptUpdateResult238 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate238, $tables238, 'option_id', $unique238);
$retryUpdateResult238 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate238, $tables238, 'option_id', $unique238);
$plan238 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow(
    $tables238,
    [$attemptUpdate238, $attemptDelete238],
    [$retryUpdate238, $retryDelete238],
    $unique238,
    rowIdColumn: 'option_id',
);
$customPlan238 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow(
    $tables238,
    [$attemptUpdate238],
    [$retryUpdate238],
    $unique238,
    'app_custom_returning_window_next238',
    'option_id',
);

$cases238 = [
    'parser attempt update row value subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate238)['where'] ?? '', 'attempt_update'), true],
    'parser retry delete order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete238)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult238()['plan']->selectedIds, [9, 8, 7]],
    'direct attempt update mutation ids' => [static fn (): mixed => $attemptUpdateResult238()['plan']->mutationIds, [7, 8, 9]],
    'direct attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult238()['returning'], 'option_id'), [7, 8, 9]],
    'direct retry update selected ids' => [static fn (): mixed => $retryUpdateResult238()['plan']->selectedIds, [10, 9, 8]],
    'direct retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult238()['returning'], 'option_id'), [8, 9, 10]],
    'direct retry update row ten value' => [static fn (): mixed => array_column($retryUpdateResult238()['tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry238'],

    'plan status' => [static fn (): mixed => $plan238()['status'], 'rowvalue-update-delete-returning-window-current-source-next238'],
    'plan savepoint' => [static fn (): mixed => $plan238()['savepoint'], 'app_settings_rowvalue_returning_window_next238'],
    'plan inherited next235 flag' => [static fn (): mixed => $plan238()['returning_window_current_source_next235'], true],
    'plan next238 flag' => [static fn (): mixed => $plan238()['returning_window_current_source_next238'], true],
    'plan rollback flags' => [static fn (): mixed => [$plan238()['rolled_back_to_savepoint'], $plan238()['retry_reads_savepoint_image'], $plan238()['savepoint_released_after_retry']], [true, true, true]],
    'plan discarded count' => [static fn (): mixed => $plan238()['discarded_attempt_window_count_next235'], 5],
    'plan yielded count' => [static fn (): mixed => $plan238()['yielded_retry_window_count_next235'], 6],
    'plan pair count' => [static fn (): mixed => $plan238()['window_pair_count_next238'], 9],
    'plan current source ids' => [static fn (): mixed => $plan238()['window_current_source_ids_next238'], [3, 4, 7, 8, 9]],
    'plan next source ids' => [static fn (): mixed => $plan238()['window_next_source_ids_next238'], [2, 5, 11, 8, 9, 10]],
    'plan replayed ids' => [static fn (): mixed => $plan238()['window_replayed_rowids_next238'], [8, 9]],
    'plan restart only ids' => [static fn (): mixed => $plan238()['window_restart_only_rowids_next238'], [2, 5, 11, 10]],
    'plan discarded only ids' => [static fn (): mixed => $plan238()['window_discarded_only_rowids_next238'], [3, 4, 7]],
    'plan pair keys' => [static fn (): mixed => array_column($plan238()['window_pair_rows_next238'], 'pair_key_next238'), ['delete:2', 'delete:3', 'delete:4', 'delete:5', 'delete:11', 'update:7', 'update:8', 'update:9', 'update:10']],
    'plan summary replay count' => [static fn (): mixed => $plan238()['window_pair_summary_next238']['replayed-after-rollback'], 2],
    'plan summary restart count' => [static fn (): mixed => $plan238()['window_pair_summary_next238']['restart-only'], 4],
    'plan summary discarded count' => [static fn (): mixed => $plan238()['window_pair_summary_next238']['discarded-only'], 3],
    'plan summary update count' => [static fn (): mixed => $plan238()['window_pair_summary_next238']['update'], 4],
    'plan summary delete count' => [static fn (): mixed => $plan238()['window_pair_summary_next238']['delete'], 5],
    'plan first pair restart delete' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][0]['pair_class_next238'], 'restart-only'],
    'plan second pair discarded delete' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][1]['pair_class_next238'], 'discarded-only'],
    'plan replay row eight current status' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][6]['current_status_next238'], 'attempt238'],
    'plan replay row eight next status' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][6]['next_status_next238'], 'retry238'],
    'plan replay row nine current value' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][7]['current_option_value_next238'], 'plugin:attempt238'],
    'plan replay row nine next value' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][7]['next_option_value_next238'], 'plugin:retry238'],
    'plan restart row ten next value' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][8]['next_option_value_next238'], 'network:retry238'],
    'plan discarded row seven current value' => [static fn (): mixed => $plan238()['window_pair_rows_next238'][5]['current_option_value_next238'], 'theme:attempt238'],
    'plan replay booleans' => [static fn (): mixed => [$plan238()['window_pair_rows_next238'][6]['current_present_next238'], $plan238()['window_pair_rows_next238'][6]['next_present_next238'], $plan238()['window_pair_rows_next238'][6]['retry_replayed_next238']], [true, true, true]],
    'plan restart booleans' => [static fn (): mixed => [$plan238()['window_pair_rows_next238'][0]['current_present_next238'], $plan238()['window_pair_rows_next238'][0]['next_present_next238'], $plan238()['window_pair_rows_next238'][0]['retry_restart_only_next238']], [false, true, true]],
    'plan discarded booleans' => [static fn (): mixed => [$plan238()['window_pair_rows_next238'][1]['current_present_next238'], $plan238()['window_pair_rows_next238'][1]['next_present_next238'], $plan238()['window_pair_rows_next238'][1]['rollback_preserved_current_next238']], [true, false, true]],
    'plan current row tags' => [static fn (): mixed => array_unique(array_column($plan238()['current_source_window_rows_next238'], 'window_source_next238')), ['discarded-current-source-next238']],
    'plan next row tags' => [static fn (): mixed => array_unique(array_column($plan238()['next_source_window_rows_next238'], 'window_source_next238')), ['yielded-next-source-next238']],
    'plan current row candidate flags' => [static fn (): mixed => array_unique(array_column($plan238()['current_source_window_rows_next238'], 'window_current_source_candidate_next238')), [true]],
    'plan next row yielded flags' => [static fn (): mixed => array_unique(array_column($plan238()['next_source_window_rows_next238'], 'window_yielded_after_retry_next238')), [true]],
    'plan current source keys' => [static fn (): mixed => array_column($plan238()['current_source_window_rows_next238'], 'window_source_key_next238'), ['delete:3', 'delete:4', 'update:7', 'update:8', 'update:9']],
    'plan next source keys' => [static fn (): mixed => array_column($plan238()['next_source_window_rows_next238'], 'window_source_key_next238'), ['delete:2', 'delete:5', 'delete:11', 'update:8', 'update:9', 'update:10']],
    'plan source fence savepoint' => [static fn (): mixed => $plan238()['window_source_fence_next238']['savepoint'], 'app_settings_rowvalue_returning_window_next238'],
    'plan source fence rollback' => [static fn (): mixed => $plan238()['window_source_fence_next238']['rolled_back_to_savepoint'], true],
    'plan source fence retry image' => [static fn (): mixed => $plan238()['window_source_fence_next238']['retry_reads_savepoint_image'], true],
    'plan source fence digests length' => [static fn (): mixed => [strlen($plan238()['window_source_fence_next238']['current_source_digest']), strlen($plan238()['window_source_fence_next238']['next_source_digest']), strlen($plan238()['window_source_fence_next238']['pair_digest'])], [64, 64, 64]],
    'plan source fence digests differ' => [static fn (): mixed => $plan238()['window_source_fence_next238']['current_source_digest'] !== $plan238()['window_source_fence_next238']['next_source_digest'], true],
    'plan attempt current row seven discarded' => [static fn (): mixed => array_column($plan238()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt238'],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan238()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan238()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry238'],
    'plan final row ten retry' => [static fn (): mixed => array_column($plan238()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry238'],
    'plan final row two deleted' => [static fn (): mixed => in_array(2, array_column($plan238()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row five deleted' => [static fn (): mixed => in_array(5, array_column($plan238()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row eleven deleted' => [static fn (): mixed => in_array(11, array_column($plan238()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row three restored' => [static fn (): mixed => in_array(3, array_column($plan238()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan row counts' => [static fn (): mixed => $plan238()['row_counts'], ['wp_optionmeta' => 11, 'wp_options' => 8]],
    'plan changed tables' => [static fn (): mixed => $plan238()['changed_tables_after_retry'], ['wp_options']],
    'plan attempt changes count' => [static fn (): mixed => $plan238()['attempt_changes_before_rollback'], 5],
    'plan retry changes count' => [static fn (): mixed => $plan238()['retry_changes_after_rollback'], 6],
    'plan dependencies' => [static fn (): mixed => $plan238()['dependencies'], ['sqlite-rowvalue-returning-window-current-source-fence-next238', 'sqlite-rowvalue-update-returning-window-replay-next238', 'sqlite-rowvalue-delete-returning-window-restart-next238']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan238()['dependency_closure_next238'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan238()['non_overlap_next238'], 'avoids accepted nullable row-value'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan238()['savepoint'], 'app_custom_returning_window_next238'],
    'custom pair count' => [static fn (): mixed => $customPlan238()['window_pair_count_next238'], 4],
    'custom replay ids' => [static fn (): mixed => $customPlan238()['window_replayed_rowids_next238'], [8, 9]],
    'custom discarded only ids' => [static fn (): mixed => $customPlan238()['window_discarded_only_rowids_next238'], [7]],
    'custom restart only ids' => [static fn (): mixed => $customPlan238()['window_restart_only_rowids_next238'], [10]],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow($tables238, [], [$retryUpdate238], $unique238), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow($tables238, [$attemptUpdate238], [], $unique238), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow($tables238, [$attemptUpdate238], [$retryUpdate238], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow($tables238, [$attemptUpdate238], [$retryUpdate238], $unique238, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow(['wp_options' => ['bad']], [$attemptUpdate238], [$retryUpdate238], $unique238), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases238 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next238 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
