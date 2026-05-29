<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows243 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 18, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 18, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];
$tables243 = ['wp_options' => $rows243];
$unique243 = [['blog_id', 'option_name']];

$yieldUpdate243 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield243', option_value || ':yield243', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete243 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate243 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt243', option_value || ':attempt243', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete243 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate243 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry243', option_value || ':retry243', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete243 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan243 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext243(
    $tables243,
    [$yieldUpdate243, $yieldDelete243],
    [$attemptUpdate243, $attemptDelete243],
    [$retryUpdate243, $retryDelete243],
    $unique243,
);
$customPlan243 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext243(
    $tables243,
    [$yieldUpdate243],
    [$attemptUpdate243],
    [$retryUpdate243],
    $unique243,
    'custom_tuple_window_243',
);

$retryUpdateKey243 = 'retry-window-after-rollback-release-next233#0#update';
$retryDeleteKey243 = 'retry-window-after-rollback-release-next233#1#delete';

$cases243 = [
    'parser retry update row value predicate retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate243)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'))"],
    'parser retry delete returning retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete243)['returning'], 'option_id, blog_id, option_name, status, bytes'],
    'plan status' => [static fn (): mixed => $plan243()['status'], 'rowvalue-update-delete-returning-window-current-source-next243'],
    'plan savepoint' => [static fn (): mixed => $plan243()['savepoint'], 'wp_options_rowvalue_window_current_next243'],
    'inherits next239 status flag' => [static fn (): mixed => $plan243()['statement_partition_window_next239'], true],
    'tuple window flag' => [static fn (): mixed => $plan243()['rowvalue_tuple_window_current_source_next243'], true],
    'retry partitions' => [static fn (): mixed => array_keys($plan243()['retry_tuple_window_frames_next243']), [$retryUpdateKey243, $retryDeleteKey243]],
    'retry update tuple keys' => [static fn (): mixed => $plan243()['retry_tuple_keys_next243'][$retryUpdateKey243], [[31, 7], [31, 9], [27, 5]]],
    'retry delete tuple keys' => [static fn (): mixed => $plan243()['retry_tuple_keys_next243'][$retryDeleteKey243], [[31, 10], [13, 4]]],
    'retry update frame ids' => [static fn (): mixed => $plan243()['retry_tuple_frame_ids_next243'][$retryUpdateKey243], [[7, 9], [7, 9, 5], [9, 5]]],
    'retry delete frame ids' => [static fn (): mixed => $plan243()['retry_tuple_frame_ids_next243'][$retryDeleteKey243], [[10, 4], [10, 4]]],
    'retry update peer ids' => [static fn (): mixed => $plan243()['retry_tuple_peer_ids_next243'][$retryUpdateKey243], [[7, 9], [7, 9], [5]]],
    'retry delete peer ids' => [static fn (): mixed => $plan243()['retry_tuple_peer_ids_next243'][$retryDeleteKey243], [[10], [4]]],
    'retry update tuple key sql' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'tuple_key_sql'), ['(31,7)', '(31,9)', '(27,5)']],
    'retry delete tuple key sql' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'tuple_key_sql'), ['(31,10)', '(13,4)']],
    'retry update lag tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'lag_tuple_key'), [null, [31, 7], [31, 9]]],
    'retry update lead tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'lead_tuple_key'), [[31, 9], [27, 5], null]],
    'retry delete lag tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'lag_tuple_key'), [null, [31, 10]]],
    'retry delete lead tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'lead_tuple_key'), [[13, 4], null]],
    'retry update frame tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'frame_tuple_keys'), [[[31, 7], [31, 9]], [[31, 7], [31, 9], [27, 5]], [[31, 9], [27, 5]]]],
    'retry delete frame tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'frame_tuple_keys'), [[[31, 10], [13, 4]], [[31, 10], [13, 4]]]],
    'retry update frame sums' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'frame_sum'), [62, 89, 58]],
    'retry delete frame sums' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'frame_sum'), [44, 44]],
    'retry update peer counts' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'peer_count'), [2, 2, 1]],
    'retry delete peer counts' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'peer_count'), [1, 1]],
    'retry update peer tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'peer_tuple_key'), [[31, '*'], [31, '*'], [27, '*']]],
    'retry delete peer tuple keys' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'peer_tuple_key'), [[31, '*'], [13, '*']]],
    'retry update row numbers' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'row_number'), [1, 2, 3]],
    'retry delete row numbers' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'row_number'), [1, 2]],
    'retry update partition counts' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'partition_count'), [3, 3, 3]],
    'retry delete partition counts' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'partition_count'), [2, 2]],
    'retry update current source visible' => [static fn (): mixed => array_unique(array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'current_source_visible')), [true]],
    'retry delete release after retry' => [static fn (): mixed => array_unique(array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'release_after_retry')), [true]],
    'retry update tokens' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryUpdateKey243], 'tuple_window_token'), [$retryUpdateKey243 . ':31:7:2', $retryUpdateKey243 . ':31:9:3', $retryUpdateKey243 . ':27:5:2']],
    'retry delete tokens' => [static fn (): mixed => array_column($plan243()['retry_tuple_window_frames_next243'][$retryDeleteKey243], 'tuple_window_token'), [$retryDeleteKey243 . ':31:10:2', $retryDeleteKey243 . ':13:4:2']],
    'release savepoint' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['savepoint'], 'wp_options_rowvalue_window_current_next243'],
    'release tuple ids' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['tuple_window_ids'], [7, 9, 5, 10, 4]],
    'release tuple count' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['tuple_window_count'], 5],
    'release partitions' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['retry_partitions'], [$retryUpdateKey243, $retryDeleteKey243]],
    'release rollback restored' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['rollback_source_restored'], true],
    'release next source matches current' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['next_source_matches_current'], true],
    'release digest length' => [static fn (): mixed => strlen($plan243()['retry_tuple_release_boundary_next243']['current_source_digest']), 64],
    'release token first' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['tuple_window_tokens'][0], $retryUpdateKey243 . ':31:7:2'],
    'release token last' => [static fn (): mixed => $plan243()['retry_tuple_release_boundary_next243']['tuple_window_tokens'][4], $retryDeleteKey243 . ':13:4:2'],
    'current source retry statuses' => [static fn (): mixed => [array_column($plan243()['current_source_tables']['wp_options'], 'status', 'option_id')[5], array_column($plan243()['current_source_tables']['wp_options'], 'status', 'option_id')[7], array_column($plan243()['current_source_tables']['wp_options'], 'status', 'option_id')[9]], ['retry243', 'retry243', 'retry243']],
    'current source retry deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan243()['current_source_tables']['wp_options'], 'option_id'))), []],
    'current source keeps orphaned attempt delete' => [static fn (): mixed => in_array(8, array_column($plan243()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan243()['savepoint'], 'custom_tuple_window_243'],
    'custom partitions' => [static fn (): mixed => array_keys($customPlan243()['retry_tuple_window_frames_next243']), [$retryUpdateKey243]],
    'custom tuple ids' => [static fn (): mixed => $customPlan243()['retry_tuple_release_boundary_next243']['tuple_window_ids'], [7, 9, 5]],
    'custom tuple count' => [static fn (): mixed => $customPlan243()['retry_tuple_release_boundary_next243']['tuple_window_count'], 3],
    'dependencies include tuple frame' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-tuple-frame-next243', $plan243()['dependencies_next243'], true), true],
    'dependencies include current source release' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-current-source-release-next243', $plan243()['dependencies_next243'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan243()['dependency_closure_next243'], 'no new support component needed'), true],
    'non overlap mentions next239' => [static fn (): mixed => str_contains($plan243()['non_overlap_next243'], 'next239 statement partitions'), true],
    'non overlap mentions JSON table' => [static fn (): mixed => str_contains($plan243()['non_overlap_next243'], 'JSON table'), true],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext243($tables243, [], [$attemptUpdate243], [$retryUpdate243], $unique243), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext243($tables243, [$yieldUpdate243], [], [$retryUpdate243], $unique243), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext243($tables243, [$yieldUpdate243], [$attemptUpdate243], [], $unique243), InvalidArgumentException::class],
    'malformed rowid rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext243($tables243, [$yieldUpdate243], [$attemptUpdate243], [$retryUpdate243], $unique243, 'sp243', 'missing_rowid'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases243 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next243 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
