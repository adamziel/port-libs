<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows239 = [
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
$tables239 = ['wp_options' => $rows239];
$unique239 = [['blog_id', 'option_name']];

$yieldUpdate239 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield239', option_value || ':yield239', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete239 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate239 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt239', option_value || ':attempt239', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete239 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate239 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry239', option_value || ':retry239', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete239 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan239 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics(
    $tables239,
    [$yieldUpdate239, $yieldDelete239],
    [$attemptUpdate239, $attemptDelete239],
    [$retryUpdate239, $retryDelete239],
    $unique239,
    rowIdColumn: 'option_id',
);
$customPlan239 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics(
    $tables239,
    [$yieldUpdate239],
    [$attemptUpdate239],
    [$retryUpdate239],
    $unique239,
    'custom_window_239',
    'option_id',
);

$retryUpdateKey239 = 'retry-window-after-rollback-release-next233#0#update';
$retryDeleteKey239 = 'retry-window-after-rollback-release-next233#1#delete';
$attemptDeleteKey239 = 'attempt-window-after-yield-before-rollback-to-next233#1#delete';
$yieldUpdateKey239 = 'yield-window-before-rollback-to-next233#0#update';

$cases239 = [
    'parser retry update row value predicate retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate239)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'))"],
    'parser retry delete returning retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete239)['returning'], 'option_id, blog_id, option_name, status, bytes'],
    'plan status' => [static fn (): mixed => $plan239()['status'], 'rowvalue-update-delete-returning-window-current-source-next239'],
    'plan savepoint' => [static fn (): mixed => $plan239()['savepoint'], 'app_settings_rowvalue_window_current_next239'],
    'plan inherits current row frame flag' => [static fn (): mixed => $plan239()['window_current_row_frame_next236'], true],
    'plan statement partition flag' => [static fn (): mixed => $plan239()['statement_partition_window_next239'], true],
    'retry partition keys' => [static fn (): mixed => array_keys($plan239()['retry_statement_windows_next239']), [$retryUpdateKey239, $retryDeleteKey239]],
    'suppressed partition keys' => [static fn (): mixed => array_keys($plan239()['suppressed_statement_windows_next239']), ['attempt-window-after-yield-before-rollback-to-next233#0#update', $attemptDeleteKey239]],
    'yield partition keys' => [static fn (): mixed => array_keys($plan239()['yield_statement_windows_next239']), [$yieldUpdateKey239, 'yield-window-before-rollback-to-next233#1#delete']],
    'retry update ids sorted by bytes' => [static fn (): mixed => $plan239()['retry_statement_window_ids_next239'][$retryUpdateKey239], [9, 7, 5]],
    'retry delete ids sorted by bytes' => [static fn (): mixed => $plan239()['retry_statement_window_ids_next239'][$retryDeleteKey239], [10, 4]],
    'retry update tiles' => [static fn (): mixed => $plan239()['retry_statement_window_tiles_next239'][$retryUpdateKey239], [1, 1, 2]],
    'retry delete tiles' => [static fn (): mixed => $plan239()['retry_statement_window_tiles_next239'][$retryDeleteKey239], [1, 2]],
    'retry update percent rank' => [static fn (): mixed => $plan239()['retry_statement_window_percent_rank_next239'][$retryUpdateKey239], [0, 500, 1000]],
    'retry delete percent rank' => [static fn (): mixed => $plan239()['retry_statement_window_percent_rank_next239'][$retryDeleteKey239], [0, 1000]],
    'retry update cume dist' => [static fn (): mixed => $plan239()['retry_statement_window_cume_dist_next239'][$retryUpdateKey239], [333, 667, 1000]],
    'retry delete cume dist' => [static fn (): mixed => $plan239()['retry_statement_window_cume_dist_next239'][$retryDeleteKey239], [500, 1000]],
    'retry update exclude current neighbor ids' => [static fn (): mixed => $plan239()['retry_statement_window_exclude_ids_next239'][$retryUpdateKey239], [[7], [9, 5], [7]]],
    'retry delete exclude current neighbor ids' => [static fn (): mixed => $plan239()['retry_statement_window_exclude_ids_next239'][$retryDeleteKey239], [[4], [10]]],
    'retry update first values' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'first_value_name'), ['plugin_batch', 'plugin_batch', 'plugin_batch']],
    'retry update last values' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'last_value_name'), ['pending_theme', 'pending_theme', 'pending_theme']],
    'retry delete first values' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'first_value_name'), ['home', 'home']],
    'retry delete last values' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'last_value_name'), ['_transient_timeout_feed', '_transient_timeout_feed']],
    'retry update row numbers' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'row_number'), [1, 2, 3]],
    'retry delete row numbers' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'row_number'), [1, 2]],
    'retry update ranks' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'rank'), [1, 2, 3]],
    'retry delete ranks' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'rank'), [1, 2]],
    'retry update dense ranks' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'dense_rank'), [1, 2, 3]],
    'retry delete dense ranks' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'dense_rank'), [1, 2]],
    'retry update partition count' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'partition_count'), [3, 3, 3]],
    'retry delete partition count' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'partition_count'), [2, 2]],
    'retry update partition sum' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'partition_sum'), [87, 87, 87]],
    'retry delete partition sum' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'partition_sum'), [44, 44]],
    'retry update bytes' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'bytes'), [31, 29, 27]],
    'retry delete bytes' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'bytes'), [31, 13]],
    'retry update tokens' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryUpdateKey239], 'window_token'), [$retryUpdateKey239 . ':9:31:87', $retryUpdateKey239 . ':7:29:87', $retryUpdateKey239 . ':5:27:87']],
    'retry delete tokens' => [static fn (): mixed => array_column($plan239()['retry_statement_windows_next239'][$retryDeleteKey239], 'window_token'), [$retryDeleteKey239 . ':10:31:44', $retryDeleteKey239 . ':4:13:44']],
    'retry edges update' => [static fn (): mixed => $plan239()['retry_statement_window_edges_next239'][$retryUpdateKey239], ['first' => 'plugin_batch', 'last' => 'pending_theme', 'count' => 3, 'sum' => 87]],
    'retry edges delete' => [static fn (): mixed => $plan239()['retry_statement_window_edges_next239'][$retryDeleteKey239], ['first' => 'home', 'last' => '_transient_timeout_feed', 'count' => 2, 'sum' => 44]],
    'suppressed attempt delete ids' => [static fn (): mixed => array_column($plan239()['suppressed_statement_windows_next239'][$attemptDeleteKey239], 'option_id'), [8]],
    'suppressed attempt delete excluded neighbor frame empty' => [static fn (): mixed => array_column($plan239()['suppressed_statement_windows_next239'][$attemptDeleteKey239], 'exclude_current_neighbor_ids'), [[]]],
    'yield update ids' => [static fn (): mixed => array_column($plan239()['yield_statement_windows_next239'][$yieldUpdateKey239], 'option_id'), [7, 5]],
    'yield update cume dist' => [static fn (): mixed => array_column($plan239()['yield_statement_windows_next239'][$yieldUpdateKey239], 'cume_dist_milli'), [500, 1000]],
    'release seal savepoint' => [static fn (): mixed => $plan239()['release_window_seal_next239']['savepoint'], 'app_settings_rowvalue_window_current_next239'],
    'release seal retry partitions' => [static fn (): mixed => $plan239()['release_window_seal_next239']['retry_partition_keys'], [$retryUpdateKey239, $retryDeleteKey239]],
    'release seal suppressed partitions' => [static fn (): mixed => $plan239()['release_window_seal_next239']['suppressed_partition_keys'], ['attempt-window-after-yield-before-rollback-to-next233#0#update', $attemptDeleteKey239]],
    'release seal retry ids' => [static fn (): mixed => $plan239()['release_window_seal_next239']['retry_ids'], [9, 7, 5, 10, 4]],
    'release seal suppressed ids' => [static fn (): mixed => $plan239()['release_window_seal_next239']['suppressed_ids'], [7, 5, 8]],
    'release seal excludes orphaned suppressed id' => [static fn (): mixed => $plan239()['release_window_seal_next239']['suppressed_ids_excluded_from_release'], [8]],
    'release seal next source matches current' => [static fn (): mixed => $plan239()['release_window_seal_next239']['next_source_matches_current'], true],
    'release seal attempt tables suppressed' => [static fn (): mixed => $plan239()['release_window_seal_next239']['attempt_tables_suppressed'], true],
    'release seal rollback restored' => [static fn (): mixed => $plan239()['release_window_seal_next239']['rollback_source_restored'], true],
    'release seal token count update' => [static fn (): mixed => count($plan239()['release_window_seal_next239']['retry_window_tokens'][$retryUpdateKey239]), 3],
    'release seal token count delete' => [static fn (): mixed => count($plan239()['release_window_seal_next239']['retry_window_tokens'][$retryDeleteKey239]), 2],
    'current source keeps orphaned attempt delete' => [static fn (): mixed => in_array(8, array_column($plan239()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'current source retry deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan239()['current_source_tables']['wp_options'], 'option_id'))), []],
    'current source retry statuses' => [static fn (): mixed => [array_column($plan239()['current_source_tables']['wp_options'], 'status', 'option_id')[5], array_column($plan239()['current_source_tables']['wp_options'], 'status', 'option_id')[7], array_column($plan239()['current_source_tables']['wp_options'], 'status', 'option_id')[9]], ['retry239', 'retry239', 'retry239']],
    'retry current row frame ids inherited' => [static fn (): mixed => $plan239()['retry_current_row_frame_ids_next236'], [9, 10, 7, 5, 4]],
    'custom savepoint' => [static fn (): mixed => $customPlan239()['savepoint'], 'custom_window_239'],
    'custom retry partitions' => [static fn (): mixed => array_keys($customPlan239()['retry_statement_windows_next239']), [$retryUpdateKey239]],
    'custom retry ids' => [static fn (): mixed => $customPlan239()['retry_statement_window_ids_next239'][$retryUpdateKey239], [9, 7, 5]],
    'custom seal retry ids' => [static fn (): mixed => $customPlan239()['release_window_seal_next239']['retry_ids'], [9, 7, 5]],
    'dependencies include statement window' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-statement-window-next239', $plan239()['dependencies_next239'], true), true],
    'dependencies include exclude current' => [static fn (): mixed => in_array('sqlite-returning-window-exclude-current-after-rollback-next239', $plan239()['dependencies_next239'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan239()['dependency_closure_next239'], 'no new support component needed'), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan239()['non_overlap_next239'], 'next236 current-row frames'), true],
    'non overlap mentions row value upsert' => [static fn (): mixed => str_contains($plan239()['non_overlap_next239'], 'row-value UPSERT'), true],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics($tables239, [], [$attemptUpdate239], [$retryUpdate239], $unique239), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics($tables239, [$yieldUpdate239], [], [$retryUpdate239], $unique239), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics($tables239, [$yieldUpdate239], [$attemptUpdate239], [], $unique239), InvalidArgumentException::class],
    'malformed rowid rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics($tables239, [$yieldUpdate239], [$attemptUpdate239], [$retryUpdate239], $unique239, 'sp239', 'missing_rowid'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases239 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next239 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
