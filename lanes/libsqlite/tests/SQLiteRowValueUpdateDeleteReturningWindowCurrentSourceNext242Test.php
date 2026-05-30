<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows242 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 19, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
    ['option_id' => 11, 'blog_id' => 5, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'network'],
];
$tables242 = ['wp_options' => $rows242];
$unique242 = [['blog_id', 'option_name']];

$yieldUpdate242 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield242', option_value || ':yield242', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete242 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate242 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt242', option_value || ':attempt242', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete242 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate242 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry242', option_value || ':retry242', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'), (5, 'network_plugin')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete242 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan242 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows(
    $tables242,
    [$yieldUpdate242, $yieldDelete242],
    [$attemptUpdate242, $attemptDelete242],
    [$retryUpdate242, $retryDelete242],
    $unique242,
);
$customPlan242 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows(
    $tables242,
    [$yieldUpdate242],
    [$attemptUpdate242],
    [$retryUpdate242],
    $unique242,
    'custom_window_242',
);

$retryUpdateKey242 = 'retry-window-after-rollback-release-next233#0#update';
$retryDeleteKey242 = 'retry-window-after-rollback-release-next233#1#delete';
$attemptDeleteKey242 = 'attempt-window-after-yield-before-rollback-to-next233#1#delete';
$yieldUpdateKey242 = 'yield-window-before-rollback-to-next233#0#update';

$cases242 = [
    'parser row value retry predicate retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate242)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'), (5, 'network_plugin'))"],
    'direct retry update selects four row values' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($retryUpdate242, $tables242, 'option_id', $unique242)['plan']->selectedIds, [5, 7, 9, 11]],
    'direct retry delete selected ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($retryDelete242, $tables242, 'option_id', $unique242)['plan']->selectedIds, [4, 10]],
    'plan status' => [static fn (): mixed => $plan242()['status'], 'rowvalue-update-delete-returning-window-current-source-next242'],
    'plan savepoint' => [static fn (): mixed => $plan242()['savepoint'], 'app_settings_rowvalue_window_current_next242'],
    'inherits next239 partition flag' => [static fn (): mixed => $plan242()['statement_partition_window_next239'], true],
    'next242 flag' => [static fn (): mixed => $plan242()['returning_window_current_source_next242'], true],
    'retry partition keys' => [static fn (): mixed => array_keys($plan242()['retry_chained_windows_next242']), [$retryUpdateKey242, $retryDeleteKey242]],
    'suppressed partition keys' => [static fn (): mixed => array_keys($plan242()['suppressed_chained_windows_next242']), ['attempt-window-after-yield-before-rollback-to-next233#0#update', $attemptDeleteKey242]],
    'yield partition keys' => [static fn (): mixed => array_keys($plan242()['yield_chained_windows_next242']), [$yieldUpdateKey242, 'yield-window-before-rollback-to-next233#1#delete']],
    'retry update ids sorted by next239 bytes' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryUpdateKey242], 'option_id'), [9, 11, 7, 5]],
    'retry delete ids sorted by next239 bytes' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryDeleteKey242], 'option_id'), [10, 4]],
    'retry update lag ids' => [static fn (): mixed => $plan242()['retry_lag_ids_next242'][$retryUpdateKey242], [null, 9, 11, 7]],
    'retry update lead ids' => [static fn (): mixed => $plan242()['retry_lead_ids_next242'][$retryUpdateKey242], [11, 7, 5, null]],
    'retry delete lag ids' => [static fn (): mixed => $plan242()['retry_lag_ids_next242'][$retryDeleteKey242], [null, 10]],
    'retry delete lead ids' => [static fn (): mixed => $plan242()['retry_lead_ids_next242'][$retryDeleteKey242], [4, null]],
    'retry update rows frames' => [static fn (): mixed => $plan242()['retry_rows_frame_ids_next242'][$retryUpdateKey242], [[9, 11], [9, 11, 7], [11, 7, 5], [7, 5]]],
    'retry delete rows frames' => [static fn (): mixed => $plan242()['retry_rows_frame_ids_next242'][$retryDeleteKey242], [[10, 4], [10, 4]]],
    'retry update groups frames include byte peers' => [static fn (): mixed => $plan242()['retry_groups_frame_ids_next242'][$retryUpdateKey242], [[9, 11], [9, 11], [7], [5]]],
    'retry delete groups frames' => [static fn (): mixed => $plan242()['retry_groups_frame_ids_next242'][$retryDeleteKey242], [[10], [4]]],
    'retry update frame sums' => [static fn (): mixed => $plan242()['retry_frame_sums_next242'][$retryUpdateKey242], [62, 91, 87, 56]],
    'retry delete frame sums' => [static fn (): mixed => $plan242()['retry_frame_sums_next242'][$retryDeleteKey242], [44, 44]],
    'retry update group sums' => [static fn (): mixed => $plan242()['retry_group_sums_next242'][$retryUpdateKey242], [62, 62, 29, 27]],
    'retry delete group sums' => [static fn (): mixed => $plan242()['retry_group_sums_next242'][$retryDeleteKey242], [31, 13]],
    'retry update source ordinals' => [static fn (): mixed => $plan242()['retry_source_ordinals_next242'][$retryUpdateKey242], [0, 1, 2, 3]],
    'retry delete source ordinals' => [static fn (): mixed => $plan242()['retry_source_ordinals_next242'][$retryDeleteKey242], [0, 1]],
    'retry update source counts' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryUpdateKey242], 'source_count'), [4, 4, 4, 4]],
    'retry delete source counts' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryDeleteKey242], 'source_count'), [2, 2]],
    'retry update first values' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryUpdateKey242], 'first_value_name'), ['plugin_batch', 'plugin_batch', 'plugin_batch', 'plugin_batch']],
    'retry update last values' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryUpdateKey242], 'last_value_name'), ['pending_theme', 'pending_theme', 'pending_theme', 'pending_theme']],
    'retry delete first values' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryDeleteKey242], 'first_value_name'), ['home', 'home']],
    'retry delete last values' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryDeleteKey242], 'last_value_name'), ['_transient_timeout_feed', '_transient_timeout_feed']],
    'retry update tokens' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryUpdateKey242], 'window_token_next242'), [$retryUpdateKey242 . ':9:62:62', $retryUpdateKey242 . ':11:91:62', $retryUpdateKey242 . ':7:87:29', $retryUpdateKey242 . ':5:56:27']],
    'retry delete tokens' => [static fn (): mixed => array_column($plan242()['retry_chained_windows_next242'][$retryDeleteKey242], 'window_token_next242'), [$retryDeleteKey242 . ':10:44:31', $retryDeleteKey242 . ':4:44:13']],
    'suppressed attempt delete ids' => [static fn (): mixed => array_column($plan242()['suppressed_chained_windows_next242'][$attemptDeleteKey242], 'option_id'), [8]],
    'suppressed attempt delete frame ids' => [static fn (): mixed => $plan242()['suppressed_chained_windows_next242'][$attemptDeleteKey242][0]['rows_frame_ids'], [8]],
    'yield update ids' => [static fn (): mixed => array_column($plan242()['yield_chained_windows_next242'][$yieldUpdateKey242], 'option_id'), [7, 5]],
    'yield update lag ids' => [static fn (): mixed => array_column($plan242()['yield_chained_windows_next242'][$yieldUpdateKey242], 'lag_id'), [null, 7]],
    'yield update lead ids' => [static fn (): mixed => array_column($plan242()['yield_chained_windows_next242'][$yieldUpdateKey242], 'lead_id'), [5, null]],
    'source seal savepoint' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['savepoint'], 'app_settings_rowvalue_window_current_next242'],
    'source seal retry ids' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['retry_ids'], [9, 11, 7, 5, 10, 4]],
    'source seal suppressed ids' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['suppressed_ids'], [7, 5, 8]],
    'source seal yield ids' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['yield_ids'], [7, 5, 3]],
    'source seal suppressed only ids' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['suppressed_only_ids'], [8]],
    'source seal retry replayed yield ids' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['retry_replayed_yield_ids'], [7, 5]],
    'source seal final ids' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['final_source_ids'], [1, 2, 3, 5, 6, 7, 8, 9, 11]],
    'source seal final contains retry updates' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['final_contains_retry_ids'], true],
    'source seal final excludes retry deletes' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['final_excludes_retry_delete_ids'], true],
    'source seal final contains suppressed only' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['final_contains_suppressed_only_ids'], true],
    'source seal rollback restored' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['rollback_restored_savepoint_image'], true],
    'source seal attempt discarded' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['attempt_source_discarded'], true],
    'source seal digest lengths' => [static fn (): mixed => [strlen($plan242()['source_generation_seal_next242']['retry_window_digest']), strlen($plan242()['source_generation_seal_next242']['suppressed_window_digest']), strlen($plan242()['source_generation_seal_next242']['yield_window_digest'])], [64, 64, 64]],
    'source seal digests differ' => [static fn (): mixed => $plan242()['source_generation_seal_next242']['retry_window_digest'] !== $plan242()['source_generation_seal_next242']['suppressed_window_digest'], true],
    'current source retry statuses' => [static fn (): mixed => [array_column($plan242()['current_source_tables']['wp_options'], 'status', 'option_id')[5], array_column($plan242()['current_source_tables']['wp_options'], 'status', 'option_id')[7], array_column($plan242()['current_source_tables']['wp_options'], 'status', 'option_id')[9], array_column($plan242()['current_source_tables']['wp_options'], 'status', 'option_id')[11]], ['retry242', 'retry242', 'retry242', 'retry242']],
    'current source deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan242()['current_source_tables']['wp_options'], 'option_id'))), []],
    'current source preserves suppressed orphan' => [static fn (): mixed => array_column($plan242()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned'],
    'custom savepoint' => [static fn (): mixed => $customPlan242()['savepoint'], 'custom_window_242'],
    'custom retry partition keys' => [static fn (): mixed => array_keys($customPlan242()['retry_chained_windows_next242']), [$retryUpdateKey242]],
    'custom retry frame ids' => [static fn (): mixed => $customPlan242()['retry_rows_frame_ids_next242'][$retryUpdateKey242], [[9, 11], [9, 11, 7], [11, 7, 5], [7, 5]]],
    'custom seal retry ids' => [static fn (): mixed => $customPlan242()['source_generation_seal_next242']['retry_ids'], [9, 11, 7, 5]],
    'dependencies include lag lead' => [static fn (): mixed => in_array('sqlite-returning-window-lag-lead-current-source-next242', $plan242()['dependencies_next242'], true), true],
    'dependencies include groups frame' => [static fn (): mixed => in_array('sqlite-returning-window-groups-frame-current-source-next242', $plan242()['dependencies_next242'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan242()['dependency_closure_next242'], 'no new support component needed'), true],
    'non overlap mentions next239' => [static fn (): mixed => str_contains($plan242()['non_overlap_next242'], 'next239 ntile'), true],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows($tables242, [], [$attemptUpdate242], [$retryUpdate242], $unique242), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows($tables242, [$yieldUpdate242], [], [$retryUpdate242], $unique242), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows($tables242, [$yieldUpdate242], [$attemptUpdate242], [], $unique242), InvalidArgumentException::class],
    'malformed rowid rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows($tables242, [$yieldUpdate242], [$attemptUpdate242], [$retryUpdate242], $unique242, 'sp242', 'missing_rowid'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases242 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next242 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
