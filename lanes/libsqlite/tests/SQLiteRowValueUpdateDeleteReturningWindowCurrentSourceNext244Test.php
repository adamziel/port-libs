<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows244 = [
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

$meta244 = [
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

$tables244 = ['wp_options' => $rows244, 'wp_optionmeta' => $meta244];
$unique244 = [['blog_id', 'option_name']];

$attemptUpdate244 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt244', option_value || ':attempt244', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete244 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate244 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry244', option_value || ':retry244', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete244 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$attemptUpdateResult244 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate244, $tables244, 'option_id', $unique244);
$retryUpdateResult244 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate244, $tables244, 'option_id', $unique244);
$plan244 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow(
    $tables244,
    [$attemptUpdate244, $attemptDelete244],
    [$retryUpdate244, $retryDelete244],
    $unique244,
);
$customPlan244 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow(
    $tables244,
    [$attemptUpdate244],
    [$retryUpdate244],
    $unique244,
    'wp_custom_returning_window_next244',
);

$cases244 = [
    'parser attempt update row value subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate244)['where'] ?? '', 'attempt_update'), true],
    'parser retry delete order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete244)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult244()['plan']->selectedIds, [9, 8, 7]],
    'direct retry update selected ids' => [static fn (): mixed => $retryUpdateResult244()['plan']->selectedIds, [10, 9, 8]],
    'direct retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult244()['returning'], 'option_id'), [8, 9, 10]],
    'direct retry update row ten value' => [static fn (): mixed => array_column($retryUpdateResult244()['tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry244'],

    'plan status' => [static fn (): mixed => $plan244()['status'], 'rowvalue-update-delete-returning-window-current-source-next244'],
    'plan savepoint' => [static fn (): mixed => $plan244()['savepoint'], 'wp_options_rowvalue_returning_window_next244'],
    'plan inherited next241 flag' => [static fn (): mixed => $plan244()['returning_window_current_source_next241'], true],
    'plan next244 flag' => [static fn (): mixed => $plan244()['returning_window_current_source_next244'], true],
    'plan transition count' => [static fn (): mixed => $plan244()['window_transition_chain_count_next244'], 9],
    'plan transition summary count' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['transition_count'], 9],
    'plan transition partition keys' => [static fn (): mixed => $plan244()['window_transition_partition_keys_next244'], ['delete', 'update']],
    'plan transition rowids' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_rowid_next244'), [2, 3, 4, 5, 11, 7, 8, 9, 10]],
    'plan transition pair keys' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_pair_key_next244'), ['delete:2', 'delete:3', 'delete:4', 'delete:5', 'delete:11', 'update:7', 'update:8', 'update:9', 'update:10']],
    'plan transition edge keys' => [static fn (): mixed => $plan244()['window_transition_edge_keys_next244'], ['delete:>2>3', 'delete:2>3>4', 'delete:3>4>5', 'delete:4>5>11', 'delete:5>11>', 'update:>7>8', 'update:7>8>9', 'update:8>9>10', 'update:9>10>']],
    'plan transition partition ordinals' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_partition_ordinal_next244'), [1, 2, 3, 4, 5, 1, 2, 3, 4]],
    'plan transition partition counts' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_partition_count_next244'), [5, 5, 5, 5, 5, 4, 4, 4, 4]],
    'plan transition classes' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_class_next244'), ['restart-only', 'discarded-only', 'discarded-only', 'restart-only', 'restart-only', 'discarded-only', 'replayed-after-rollback', 'replayed-after-rollback', 'restart-only']],
    'plan previous classes' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_previous_class_next244'), [null, 'restart-only', 'discarded-only', 'discarded-only', 'restart-only', null, 'discarded-only', 'replayed-after-rollback', 'replayed-after-rollback']],
    'plan next classes' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_next_class_next244'), ['discarded-only', 'discarded-only', 'restart-only', 'restart-only', null, 'replayed-after-rollback', 'replayed-after-rollback', 'restart-only', null]],
    'plan previous rowids' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_previous_rowid_next244'), [null, 2, 3, 4, 5, null, 7, 8, 9]],
    'plan next rowids' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_next_rowid_next244'), [3, 4, 5, 11, null, 8, 9, 10, null]],
    'plan boundaries' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_boundary_next244'), ['first-row', 'middle-row', 'middle-row', 'middle-row', 'last-row', 'first-row', 'middle-row', 'middle-row', 'last-row']],
    'plan lag change flags' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_lag_class_changed_next244'), [false, true, false, true, false, false, true, false, true]],
    'plan lead change flags' => [static fn (): mixed => array_column($plan244()['window_transition_chains_next244'], 'transition_lead_class_changed_next244'), [true, false, true, false, false, true, false, true, false]],
    'plan first frame rowids' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][0]['transition_frame_rowids_next244'], [2, 3]],
    'plan middle frame rowids' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][2]['transition_frame_rowids_next244'], [3, 4, 5]],
    'plan last frame rowids' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][8]['transition_frame_rowids_next244'], [9, 10]],
    'plan first frame classes' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][0]['transition_frame_classes_next244'], ['restart-only', 'discarded-only']],
    'plan middle frame classes' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][6]['transition_frame_classes_next244'], ['discarded-only', 'replayed-after-rollback', 'replayed-after-rollback']],
    'plan last frame classes' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][8]['transition_frame_classes_next244'], ['replayed-after-rollback', 'restart-only']],
    'plan replayed ids' => [static fn (): mixed => $plan244()['window_transition_replayed_ids_next244'], [8, 9]],
    'plan restart ids' => [static fn (): mixed => $plan244()['window_transition_restart_ids_next244'], [2, 5, 11, 10]],
    'plan discarded ids' => [static fn (): mixed => $plan244()['window_transition_discarded_ids_next244'], [3, 4, 7]],
    'plan summary lag changes' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['lag_class_changes'], 4],
    'plan summary lead changes' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['lead_class_changes'], 4],
    'plan summary first rows' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['first_rows'], 2],
    'plan summary last rows' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['last_rows'], 2],
    'plan summary singleton rows' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['singleton_rows'], 0],
    'plan summary replay count' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['replayed-after-rollback'], 2],
    'plan summary restart count' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['restart-only'], 4],
    'plan summary discarded count' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['discarded-only'], 3],
    'plan summary delete rowids' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['rowids_by_partition']['delete'], [2, 3, 4, 5, 11]],
    'plan summary update rowids' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['rowids_by_partition']['update'], [7, 8, 9, 10]],
    'plan summary delete classes' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['classes_by_partition']['delete'], ['restart-only', 'discarded-only', 'discarded-only', 'restart-only', 'restart-only']],
    'plan summary update classes' => [static fn (): mixed => $plan244()['window_transition_summary_next244']['classes_by_partition']['update'], ['discarded-only', 'replayed-after-rollback', 'replayed-after-rollback', 'restart-only']],
    'plan replay booleans' => [static fn (): mixed => [$plan244()['window_transition_chains_next244'][6]['transition_current_present_next244'], $plan244()['window_transition_chains_next244'][6]['transition_next_present_next244'], $plan244()['window_transition_chains_next244'][6]['transition_replayed_next244']], [true, true, true]],
    'plan restart booleans' => [static fn (): mixed => [$plan244()['window_transition_chains_next244'][0]['transition_current_present_next244'], $plan244()['window_transition_chains_next244'][0]['transition_next_present_next244'], $plan244()['window_transition_chains_next244'][0]['transition_restart_only_next244']], [false, true, true]],
    'plan discarded booleans' => [static fn (): mixed => [$plan244()['window_transition_chains_next244'][1]['transition_current_present_next244'], $plan244()['window_transition_chains_next244'][1]['transition_next_present_next244'], $plan244()['window_transition_chains_next244'][1]['transition_discarded_only_next244']], [true, false, true]],
    'plan replay current value' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][7]['transition_current_value_next244'], 'plugin:attempt244'],
    'plan replay next value' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][7]['transition_next_value_next244'], 'plugin:retry244'],
    'plan restart next value' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][8]['transition_next_value_next244'], 'network:retry244'],
    'plan discarded current value' => [static fn (): mixed => $plan244()['window_transition_chains_next244'][5]['transition_current_value_next244'], 'theme:attempt244'],
    'plan fence frame mode' => [static fn (): mixed => $plan244()['window_transition_fence_next244']['frame_mode'], 'ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING'],
    'plan fence source count' => [static fn (): mixed => $plan244()['window_transition_fence_next244']['source_frame_count'], 9],
    'plan fence transition count' => [static fn (): mixed => $plan244()['window_transition_fence_next244']['transition_count'], 9],
    'plan fence digest lengths' => [static fn (): mixed => [strlen($plan244()['window_transition_fence_next244']['transition_digest']), strlen($plan244()['window_transition_fence_next244']['current_row_frame_digest']), strlen($plan244()['window_transition_fence_next244']['pair_digest'])], [64, 64, 64]],
    'plan fence transition digest differs from current frame digest' => [static fn (): mixed => $plan244()['window_transition_fence_next244']['transition_digest'] !== $plan244()['window_transition_fence_next244']['current_row_frame_digest'], true],
    'plan fence rollback flags' => [static fn (): mixed => [$plan244()['window_transition_fence_next244']['rolled_back_to_savepoint'], $plan244()['window_transition_fence_next244']['retry_reads_savepoint_image']], [true, true]],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan244()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry244'],
    'plan final row ten retry' => [static fn (): mixed => array_column($plan244()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry244'],
    'plan final row two deleted' => [static fn (): mixed => in_array(2, array_column($plan244()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row three restored' => [static fn (): mixed => in_array(3, array_column($plan244()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan row counts' => [static fn (): mixed => $plan244()['row_counts'], ['wp_optionmeta' => 11, 'wp_options' => 8]],
    'plan changed tables' => [static fn (): mixed => $plan244()['changed_tables_after_retry'], ['wp_options']],
    'plan dependencies' => [static fn (): mixed => $plan244()['dependencies'], ['sqlite-rowvalue-returning-window-transition-chain-next244', 'sqlite-rowvalue-returning-lag-lead-current-source-next244', 'application-rowvalue-returning-window-current-source-next244']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan244()['dependency_closure_next244'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan244()['non_overlap_next244'], 'lag/lead transition-chain windows'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan244()['savepoint'], 'wp_custom_returning_window_next244'],
    'custom transition count' => [static fn (): mixed => $customPlan244()['window_transition_chain_count_next244'], 4],
    'custom replay ids' => [static fn (): mixed => $customPlan244()['window_transition_replayed_ids_next244'], [8, 9]],
    'custom discarded ids' => [static fn (): mixed => $customPlan244()['window_transition_discarded_ids_next244'], [7]],
    'custom restart ids' => [static fn (): mixed => $customPlan244()['window_transition_restart_ids_next244'], [10]],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow($tables244, [], [$retryUpdate244], $unique244), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow($tables244, [$attemptUpdate244], [], $unique244), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow($tables244, [$attemptUpdate244], [$retryUpdate244], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow($tables244, [$attemptUpdate244], [$retryUpdate244], $unique244, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow(['wp_options' => ['bad']], [$attemptUpdate244], [$retryUpdate244], $unique244), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases244 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next244 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
