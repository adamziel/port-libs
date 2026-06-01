<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows236 = [
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
];
$tables236 = ['wp_options' => $rows236];
$unique236 = [['blog_id', 'option_name']];

$yieldUpdate236 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield236', option_value || ':yield236', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) AS tuple_hit ORDER BY option_id";
$yieldDelete236 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) IN ((1, '_transient_feed')) AS tuple_hit ORDER BY option_id";
$attemptUpdate236 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt236', option_value || ':attempt236', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes, (status, option_name) = ('attempt236', 'rewrite_rules') AS tuple_hit ORDER BY option_id";
$attemptDelete236 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS tuple_hit ORDER BY option_id";
$retryUpdate236 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry236', option_value || ':retry236', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes, (status, option_name) = ('retry236', 'rewrite_rules') AS tuple_hit ORDER BY option_id";
$retryDelete236 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT IN ((4, 'home')) AS tuple_hit ORDER BY option_id";

$retryUpdateResult236 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate236, $tables236, 'option_id', $unique236);
$retryDeleteResult236 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete236, $retryUpdateResult236()['tables'], 'option_id', $unique236);
$plan236 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames(
    $tables236,
    [$yieldUpdate236, $yieldDelete236],
    [$attemptUpdate236, $attemptDelete236],
    [$retryUpdate236, $retryDelete236],
    $unique236,
    rowIdColumn: 'option_id',
);
$customPlan236 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames(
    $tables236,
    [$yieldUpdate236],
    [$attemptUpdate236],
    [$retryUpdate236],
    $unique236,
    'custom_window_236',
    'option_id',
);

$cases236 = [
    'parser retry update row value predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate236)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'))"],
    'parser retry delete returning tuple expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete236)['returning'], 'tuple_hit'), true],
    'retry update selected ids from original image' => [static fn (): mixed => $retryUpdateResult236()['plan']->selectedIds, [5, 7, 9]],
    'retry update bytes' => [static fn (): mixed => array_column($retryUpdateResult236()['returning'], 'bytes', 'option_id'), [5 => 27, 7 => 29, 9 => 31]],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult236()['plan']->selectedIds, [4, 10]],
    'retry delete tuple flags' => [static fn (): mixed => array_column($retryDeleteResult236()['returning'], 'tuple_hit'), [1, 0]],
    'retry final ids' => [static fn (): mixed => array_column($retryDeleteResult236()['tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7, 8, 9]],
    'plan status' => [static fn (): mixed => $plan236()['status'], 'rowvalue-update-delete-returning-window-current-source-next236'],
    'plan savepoint' => [static fn (): mixed => $plan236()['savepoint'], 'app_settings_rowvalue_window_current_next236'],
    'plan inherits next233 retry window ids' => [static fn (): mixed => array_column($plan236()['retry_window'], 'option_id'), [9, 10, 7, 5, 4]],
    'plan current row frame flag' => [static fn (): mixed => $plan236()['window_current_row_frame_next236'], true],
    'yield frame ids' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'option_id'), [7, 5, 3]],
    'yield frame current values' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'current_row_value'), [39, 37, 12]],
    'yield frame running bytes' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'running_bytes'), [39, 76, 88]],
    'yield frame following bytes' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'following_bytes'), [49, 12, 0]],
    'yield frame lag ids' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'lag_id'), [null, 7, 5]],
    'yield frame lead ids' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'lead_id'), [5, 3, null]],
    'yield frame tokens' => [static fn (): mixed => array_column($plan236()['yield_current_row_frames_next236'], 'frame_token'), ['rewrite_rules:39:39', 'pending_theme:37:76', '_transient_feed:12:88']],
    'suppressed frame ids' => [static fn (): mixed => array_column($plan236()['suppressed_current_row_frames_next236'], 'option_id'), [7, 5, 8]],
    'suppressed frame values' => [static fn (): mixed => array_column($plan236()['suppressed_current_row_frames_next236'], 'current_row_value'), [44, 42, 5]],
    'suppressed frame running bytes' => [static fn (): mixed => array_column($plan236()['suppressed_current_row_frames_next236'], 'running_bytes'), [44, 86, 91]],
    'suppressed frame following bytes' => [static fn (): mixed => array_column($plan236()['suppressed_current_row_frames_next236'], 'following_bytes'), [47, 5, 0]],
    'suppressed frame lead names' => [static fn (): mixed => array_column($plan236()['suppressed_current_row_frames_next236'], 'lead_name'), ['pending_theme', 'orphaned_cache', null]],
    'retry frame ids' => [static fn (): mixed => $plan236()['retry_current_row_frame_ids_next236'], [9, 10, 7, 5, 4]],
    'retry frame values' => [static fn (): mixed => $plan236()['retry_current_row_frame_values_next236'], [31, 31, 29, 27, 13]],
    'retry running bytes' => [static fn (): mixed => $plan236()['retry_running_bytes_next236'], [31, 62, 91, 118, 131]],
    'retry following bytes' => [static fn (): mixed => $plan236()['retry_following_bytes_next236'], [100, 69, 40, 13, 0]],
    'retry lag ids' => [static fn (): mixed => array_column($plan236()['retry_current_row_frames_next236'], 'lag_id'), [null, 9, 10, 7, 5]],
    'retry lead ids' => [static fn (): mixed => array_column($plan236()['retry_current_row_frames_next236'], 'lead_id'), [10, 7, 5, 4, null]],
    'retry neighbor names' => [static fn (): mixed => $plan236()['retry_neighbor_names_next236'], [[null, 'plugin_batch', 'home'], ['plugin_batch', 'home', 'rewrite_rules'], ['home', 'rewrite_rules', 'pending_theme'], ['rewrite_rules', 'pending_theme', '_transient_timeout_feed'], ['pending_theme', '_transient_timeout_feed', null]]],
    'retry row current count is one' => [static fn (): mixed => array_column($plan236()['retry_current_row_frames_next236'], 'current_row_count'), [1, 1, 1, 1, 1]],
    'retry row numbers preserved' => [static fn (): mixed => array_column($plan236()['retry_current_row_frames_next236'], 'row_number'), [1, 2, 3, 4, 5]],
    'retry dense rank preserved' => [static fn (): mixed => array_column($plan236()['retry_current_row_frames_next236'], 'dense_rank'), [1, 1, 2, 3, 4]],
    'retry frame tokens' => [static fn (): mixed => array_column($plan236()['retry_current_row_frames_next236'], 'frame_token'), ['plugin_batch:31:31', 'home:31:62', 'rewrite_rules:29:91', 'pending_theme:27:118', '_transient_timeout_feed:13:131']],
    'receipt savepoint' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['savepoint'], 'app_settings_rowvalue_window_current_next236'],
    'receipt retry ids' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['retry_ids'], [9, 10, 7, 5, 4]],
    'receipt retry tokens' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['retry_frame_tokens'], ['plugin_batch:31:31', 'home:31:62', 'rewrite_rules:29:91', 'pending_theme:27:118', '_transient_timeout_feed:13:131']],
    'receipt running final' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['retry_running_final'], 131],
    'receipt following final' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['retry_following_final'], 0],
    'receipt released table count' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['released_table_count'], 8],
    'receipt next source matches current' => [static fn (): mixed => $plan236()['current_source_receipt_next236']['next_source_matches_current'], true],
    'yield rollback restores row three for retry source' => [static fn (): mixed => in_array(3, array_column($plan236()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'attempt delete remains suppressed after rollback' => [static fn (): mixed => in_array(8, array_column($plan236()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'retry release deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan236()['current_source_tables']['wp_options'], 'option_id'))), []],
    'retry row five has retry only status' => [static fn (): mixed => array_column($plan236()['current_source_tables']['wp_options'], 'status', 'option_id')[5], 'retry236'],
    'retry row seven has retry only status' => [static fn (): mixed => array_column($plan236()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry236'],
    'retry row nine has retry only status' => [static fn (): mixed => array_column($plan236()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry236'],
    'yield count' => [static fn (): mixed => $plan236()['yielded_returning_count'], 3],
    'suppressed count' => [static fn (): mixed => $plan236()['suppressed_returning_count'], 3],
    'retry count' => [static fn (): mixed => $plan236()['retry_returning_count'], 5],
    'change counts' => [static fn (): mixed => [$plan236()['yield_change_count'], $plan236()['attempt_change_count'], $plan236()['retry_change_count']], [3, 3, 5]],
    'changed tables' => [static fn (): mixed => $plan236()['changed_tables_after_release'], ['wp_options']],
    'row counts' => [static fn (): mixed => $plan236()['row_counts']['wp_options'], 8],
    'dependency current row' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-window-current-row-next236', $plan236()['dependencies_next236'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-rowvalue-returning-current-row-window-next236', $plan236()['dependencies_next236'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan236()['dependency_closure_next236'], 'no new support component needed'), true],
    'non overlap mentions next233' => [static fn (): mixed => str_contains($plan236()['non_overlap_next236'], 'next233'), true],
    'non overlap mentions row value upsert' => [static fn (): mixed => str_contains($plan236()['non_overlap_next236'], 'row-value UPSERT'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan236()['savepoint'], 'custom_window_236'],
    'custom retry frame ids' => [static fn (): mixed => $customPlan236()['retry_current_row_frame_ids_next236'], [9, 7, 5]],
    'custom retry frame values' => [static fn (): mixed => $customPlan236()['retry_current_row_frame_values_next236'], [31, 29, 27]],
    'custom retry running bytes' => [static fn (): mixed => $customPlan236()['retry_running_bytes_next236'], [31, 60, 87]],
    'custom retry following bytes' => [static fn (): mixed => $customPlan236()['retry_following_bytes_next236'], [56, 27, 0]],
    'custom receipt final' => [static fn (): mixed => $customPlan236()['current_source_receipt_next236']['retry_running_final'], 87],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames($tables236, [], [$attemptUpdate236], [$retryUpdate236], $unique236), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames($tables236, [$yieldUpdate236], [], [$retryUpdate236], $unique236), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames($tables236, [$yieldUpdate236], [$attemptUpdate236], [], $unique236), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames($tables236, [$yieldUpdate236], [$attemptUpdate236], [$retryUpdate236], $unique236, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases236 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next236 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
