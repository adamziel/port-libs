<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows241 = [
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

$meta241 = [
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

$tables241 = ['wp_options' => $rows241, 'wp_optionmeta' => $meta241];
$unique241 = [['blog_id', 'option_name']];

$attemptUpdate241 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt241', option_value || ':attempt241', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$attemptDelete241 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate241 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry241', option_value || ':retry241', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_update') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete241 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_delete') RETURNING option_id, blog_id, option_name, status ORDER BY option_id DESC";

$attemptUpdateResult241 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate241, $tables241, 'option_id', $unique241);
$retryUpdateResult241 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate241, $tables241, 'option_id', $unique241);
$plan241 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241(
    $tables241,
    [$attemptUpdate241, $attemptDelete241],
    [$retryUpdate241, $retryDelete241],
    $unique241,
);
$customPlan241 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241(
    $tables241,
    [$attemptUpdate241],
    [$retryUpdate241],
    $unique241,
    'wp_custom_returning_window_next241',
);

$cases241 = [
    'parser attempt update row value subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate241)['where'] ?? '', 'attempt_update'), true],
    'parser retry delete order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete241)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult241()['plan']->selectedIds, [9, 8, 7]],
    'direct retry update selected ids' => [static fn (): mixed => $retryUpdateResult241()['plan']->selectedIds, [10, 9, 8]],
    'direct retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult241()['returning'], 'option_id'), [8, 9, 10]],
    'direct retry update row ten value' => [static fn (): mixed => array_column($retryUpdateResult241()['tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry241'],

    'plan status' => [static fn (): mixed => $plan241()['status'], 'rowvalue-update-delete-returning-window-current-source-next241'],
    'plan savepoint' => [static fn (): mixed => $plan241()['savepoint'], 'wp_options_rowvalue_returning_window_next241'],
    'plan inherited next238 flag' => [static fn (): mixed => $plan241()['returning_window_current_source_next238'], true],
    'plan next241 flag' => [static fn (): mixed => $plan241()['returning_window_current_source_next241'], true],
    'plan frame mode' => [static fn (): mixed => $plan241()['window_current_row_fence_next241']['frame_mode'], 'ROWS BETWEEN CURRENT ROW AND CURRENT ROW'],
    'plan pair count' => [static fn (): mixed => $plan241()['window_pair_count_next238'], 9],
    'plan frame count' => [static fn (): mixed => $plan241()['window_current_row_frame_count_next241'], 9],
    'plan frame fence pair count' => [static fn (): mixed => $plan241()['window_current_row_fence_next241']['pair_count'], 9],
    'plan frame fence frame count' => [static fn (): mixed => $plan241()['window_current_row_fence_next241']['frame_count'], 9],
    'plan current row frame counts' => [static fn (): mixed => array_unique(array_column($plan241()['window_current_row_frames_next241'], 'frame_count_next241')), [1]],
    'plan current row frame boundaries' => [static fn (): mixed => array_unique(array_column($plan241()['window_current_row_frames_next241'], 'frame_current_row_boundary_next241')), ['current-row-only']],
    'plan source isolated flags' => [static fn (): mixed => array_unique(array_column($plan241()['window_current_row_frames_next241'], 'frame_source_isolated_next241')), [true]],
    'plan frame rowids' => [static fn (): mixed => array_column($plan241()['window_current_row_frames_next241'], 'frame_rowid_next241'), [2, 3, 4, 5, 11, 7, 8, 9, 10]],
    'plan frame keys' => [static fn (): mixed => array_column($plan241()['window_current_row_frames_next241'], 'frame_key_next241'), ['delete:2:restart-only', 'delete:3:discarded-only', 'delete:4:discarded-only', 'delete:5:restart-only', 'delete:11:restart-only', 'update:7:discarded-only', 'update:8:replayed-after-rollback', 'update:9:replayed-after-rollback', 'update:10:restart-only']],
    'plan frame pair keys' => [static fn (): mixed => array_column($plan241()['window_current_row_frames_next241'], 'frame_pair_key_next241'), ['delete:2', 'delete:3', 'delete:4', 'delete:5', 'delete:11', 'update:7', 'update:8', 'update:9', 'update:10']],
    'plan frame action ordinals' => [static fn (): mixed => array_column($plan241()['window_current_row_frames_next241'], 'frame_action_ordinal_next241'), [1, 2, 3, 4, 5, 1, 2, 3, 4]],
    'plan frame actions' => [static fn (): mixed => $plan241()['window_current_row_actions_next241'], ['delete', 'update']],
    'plan frame classes' => [static fn (): mixed => $plan241()['window_current_row_classes_next241'], ['restart-only', 'discarded-only', 'replayed-after-rollback']],
    'plan replayed ids' => [static fn (): mixed => $plan241()['window_current_row_replayed_ids_next241'], [8, 9]],
    'plan restart ids' => [static fn (): mixed => $plan241()['window_current_row_restart_ids_next241'], [2, 5, 11, 10]],
    'plan discarded ids' => [static fn (): mixed => $plan241()['window_current_row_discarded_ids_next241'], [3, 4, 7]],
    'plan summary frame count' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['frame_count'], 9],
    'plan summary current row only' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['current_row_only_frames'], 9],
    'plan summary replay count' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['replayed-after-rollback'], 2],
    'plan summary restart count' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['restart-only'], 4],
    'plan summary discarded count' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['discarded-only'], 3],
    'plan summary update count' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['update'], 4],
    'plan summary delete count' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['delete'], 5],
    'plan summary delete rowids' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['rowids_by_action']['delete'], [2, 3, 4, 5, 11]],
    'plan summary update rowids' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['rowids_by_action']['update'], [7, 8, 9, 10]],
    'plan summary delete classes' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['classes_by_action']['delete'], ['restart-only', 'discarded-only', 'discarded-only', 'restart-only', 'restart-only']],
    'plan summary update classes' => [static fn (): mixed => $plan241()['window_current_row_summary_next241']['classes_by_action']['update'], ['discarded-only', 'replayed-after-rollback', 'replayed-after-rollback', 'restart-only']],
    'plan first frame restart delete booleans' => [static fn (): mixed => [$plan241()['window_current_row_frames_next241'][0]['frame_current_present_next241'], $plan241()['window_current_row_frames_next241'][0]['frame_next_present_next241'], $plan241()['window_current_row_frames_next241'][0]['frame_restart_only_next241']], [false, true, true]],
    'plan discarded frame booleans' => [static fn (): mixed => [$plan241()['window_current_row_frames_next241'][1]['frame_current_present_next241'], $plan241()['window_current_row_frames_next241'][1]['frame_next_present_next241'], $plan241()['window_current_row_frames_next241'][1]['frame_discarded_only_next241']], [true, false, true]],
    'plan replay frame booleans' => [static fn (): mixed => [$plan241()['window_current_row_frames_next241'][6]['frame_current_present_next241'], $plan241()['window_current_row_frames_next241'][6]['frame_next_present_next241'], $plan241()['window_current_row_frames_next241'][6]['frame_replayed_next241']], [true, true, true]],
    'plan replay frame current status' => [static fn (): mixed => $plan241()['window_current_row_frames_next241'][6]['frame_current_status_next241'], 'attempt241'],
    'plan replay frame next status' => [static fn (): mixed => $plan241()['window_current_row_frames_next241'][6]['frame_next_status_next241'], 'retry241'],
    'plan replay frame current value' => [static fn (): mixed => $plan241()['window_current_row_frames_next241'][7]['frame_current_value_next241'], 'plugin:attempt241'],
    'plan replay frame next value' => [static fn (): mixed => $plan241()['window_current_row_frames_next241'][7]['frame_next_value_next241'], 'plugin:retry241'],
    'plan restart frame next value' => [static fn (): mixed => $plan241()['window_current_row_frames_next241'][8]['frame_next_value_next241'], 'network:retry241'],
    'plan discarded frame current value' => [static fn (): mixed => $plan241()['window_current_row_frames_next241'][5]['frame_current_value_next241'], 'theme:attempt241'],
    'plan frame rowids are singleton' => [static fn (): mixed => array_column($plan241()['window_current_row_frames_next241'], 'frame_rowids_next241'), [[2], [3], [4], [5], [11], [7], [8], [9], [10]]],
    'plan frame classes are singleton' => [static fn (): mixed => array_column($plan241()['window_current_row_frames_next241'], 'frame_classes_next241'), [['restart-only'], ['discarded-only'], ['discarded-only'], ['restart-only'], ['restart-only'], ['discarded-only'], ['replayed-after-rollback'], ['replayed-after-rollback'], ['restart-only']]],
    'plan fence digest lengths' => [static fn (): mixed => [strlen($plan241()['window_current_row_fence_next241']['frame_digest']), strlen($plan241()['window_current_row_fence_next241']['source_pair_digest']), strlen($plan241()['window_current_row_fence_next241']['current_source_digest']), strlen($plan241()['window_current_row_fence_next241']['next_source_digest'])], [64, 64, 64, 64]],
    'plan fence source pair digest equals next238 pair digest' => [static fn (): mixed => $plan241()['window_current_row_fence_next241']['source_pair_digest'], $plan241()['window_source_fence_next238']['pair_digest']],
    'plan fence frame digest differs from source pair digest' => [static fn (): mixed => $plan241()['window_current_row_fence_next241']['frame_digest'] !== $plan241()['window_current_row_fence_next241']['source_pair_digest'], true],
    'plan fence rollback flags' => [static fn (): mixed => [$plan241()['window_current_row_fence_next241']['rolled_back_to_savepoint'], $plan241()['window_current_row_fence_next241']['retry_reads_savepoint_image']], [true, true]],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan241()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry241'],
    'plan final row ten retry' => [static fn (): mixed => array_column($plan241()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'network:retry241'],
    'plan final row two deleted' => [static fn (): mixed => in_array(2, array_column($plan241()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row three restored' => [static fn (): mixed => in_array(3, array_column($plan241()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan row counts' => [static fn (): mixed => $plan241()['row_counts'], ['wp_optionmeta' => 11, 'wp_options' => 8]],
    'plan changed tables' => [static fn (): mixed => $plan241()['changed_tables_after_retry'], ['wp_options']],
    'plan dependencies' => [static fn (): mixed => $plan241()['dependencies'], ['sqlite-rowvalue-returning-window-current-row-frame-next241', 'sqlite-rowvalue-update-delete-returning-current-source-fence-next241', 'wordpress-rowvalue-returning-window-current-source-next241']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan241()['dependency_closure_next241'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan241()['non_overlap_next241'], 'CURRENT ROW frame isolation'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan241()['savepoint'], 'wp_custom_returning_window_next241'],
    'custom frame count' => [static fn (): mixed => $customPlan241()['window_current_row_frame_count_next241'], 4],
    'custom replay ids' => [static fn (): mixed => $customPlan241()['window_current_row_replayed_ids_next241'], [8, 9]],
    'custom discarded ids' => [static fn (): mixed => $customPlan241()['window_current_row_discarded_ids_next241'], [7]],
    'custom restart ids' => [static fn (): mixed => $customPlan241()['window_current_row_restart_ids_next241'], [10]],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241($tables241, [], [$retryUpdate241], $unique241), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241($tables241, [$attemptUpdate241], [], $unique241), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241($tables241, [$attemptUpdate241], [$retryUpdate241], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241($tables241, [$attemptUpdate241], [$retryUpdate241], $unique241, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext241(['wp_options' => ['bad']], [$attemptUpdate241], [$retryUpdate241], $unique241), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases241 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next241 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
