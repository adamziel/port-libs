<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows240 = [
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
$tables240 = ['wp_options' => $rows240];
$unique240 = [['blog_id', 'option_name']];

$yieldUpdate240 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield240', option_value || ':yield240', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) AS tuple_hit ORDER BY option_id";
$yieldDelete240 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) IN ((1, '_transient_feed')) AS tuple_hit ORDER BY option_id";
$attemptUpdate240 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt240', option_value || ':attempt240', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes, (status, option_name) = ('attempt240', 'rewrite_rules') AS tuple_hit ORDER BY option_id";
$attemptDelete240 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS tuple_hit ORDER BY option_id";
$retryUpdate240 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry240', option_value || ':retry240', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes, (status, option_name) = ('retry240', 'rewrite_rules') AS tuple_hit ORDER BY option_id";
$retryDelete240 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT IN ((4, 'home')) AS tuple_hit ORDER BY option_id";

$retryUpdateResult240 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate240, $tables240, 'option_id', $unique240);
$retryDeleteResult240 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete240, $retryUpdateResult240()['tables'], 'option_id', $unique240);
$plan240 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt(
    $tables240,
    [$yieldUpdate240, $yieldDelete240],
    [$attemptUpdate240, $attemptDelete240],
    [$retryUpdate240, $retryDelete240],
    $unique240,
);
$customPlan240 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt(
    $tables240,
    [$yieldUpdate240],
    [$attemptUpdate240],
    [$retryUpdate240],
    $unique240,
    'custom_window_240',
);

$cases240 = [
    'parser retry update row value predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate240)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'))"],
    'parser retry delete returning tuple expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete240)['returning'], 'tuple_hit'), true],
    'retry update selected ids from original image' => [static fn (): mixed => $retryUpdateResult240()['plan']->selectedIds, [5, 7, 9]],
    'retry update returning bytes' => [static fn (): mixed => array_column($retryUpdateResult240()['returning'], 'bytes', 'option_id'), [5 => 27, 7 => 29, 9 => 31]],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult240()['plan']->selectedIds, [4, 10]],
    'retry delete tuple flags' => [static fn (): mixed => array_column($retryDeleteResult240()['returning'], 'tuple_hit'), [1, 0]],
    'retry final ids' => [static fn (): mixed => array_column($retryDeleteResult240()['tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7, 8, 9]],
    'plan status' => [static fn (): mixed => $plan240()['status'], 'rowvalue-update-delete-returning-window-current-source-next240'],
    'plan savepoint' => [static fn (): mixed => $plan240()['savepoint'], 'wp_options_rowvalue_window_groups_next240'],
    'plan inherits next236 retry frame ids' => [static fn (): mixed => $plan240()['retry_current_row_frame_ids_next236'], [9, 10, 7, 5, 4]],
    'plan peer exclusion flag' => [static fn (): mixed => $plan240()['window_peer_group_exclusion_next240'], true],
    'yield peer ids' => [static fn (): mixed => array_column($plan240()['yield_peer_groups_next240'], 'option_id'), [7, 5, 3]],
    'yield peer keys' => [static fn (): mixed => array_column($plan240()['yield_peer_groups_next240'], 'peer_key'), ['yield240|39', 'yield240|37', 'stale|12']],
    'yield exclude current sums' => [static fn (): mixed => array_column($plan240()['yield_peer_groups_next240'], 'exclude_current_sum'), [49, 51, 76]],
    'yield exclude ties sums' => [static fn (): mixed => array_column($plan240()['yield_peer_groups_next240'], 'exclude_ties_sum'), [88, 88, 88]],
    'yield ntile buckets' => [static fn (): mixed => array_column($plan240()['yield_peer_groups_next240'], 'ntile_2'), [1, 1, 2]],
    'suppressed peer ids' => [static fn (): mixed => array_column($plan240()['suppressed_peer_groups_next240'], 'option_id'), [7, 5, 8]],
    'suppressed peer keys' => [static fn (): mixed => array_column($plan240()['suppressed_peer_groups_next240'], 'peer_key'), ['attempt240|44', 'attempt240|42', 'orphaned|5']],
    'suppressed exclude group sums' => [static fn (): mixed => array_column($plan240()['suppressed_peer_groups_next240'], 'exclude_group_sum'), [47, 49, 86]],
    'suppressed percent ranks' => [static fn (): mixed => array_column($plan240()['suppressed_peer_groups_next240'], 'percent_rank'), [0, 0.5, 1]],
    'retry peer ids' => [static fn (): mixed => $plan240()['retry_peer_group_ids_next240'], [9, 10, 7, 5, 4]],
    'retry peer keys' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'peer_key'), ['retry240|31', 'live|31', 'retry240|29', 'retry240|27', 'stale|13']],
    'retry peer group numbers' => [static fn (): mixed => $plan240()['retry_peer_group_numbers_next240'], [1, 2, 3, 4, 5]],
    'retry peer group sizes' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'peer_group_size'), [1, 1, 1, 1, 1]],
    'retry peer row numbers' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'peer_row_number'), [1, 1, 1, 1, 1]],
    'retry ranks' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'rank'), [1, 2, 3, 4, 5]],
    'retry dense ranks' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'dense_rank'), [1, 2, 3, 4, 5]],
    'retry percent ranks' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'percent_rank'), [0, 0.25, 0.5, 0.75, 1]],
    'retry cume dist' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'cume_dist'), [0.2, 0.4, 0.6, 0.8, 1]],
    'retry ntile buckets' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'ntile_2'), [1, 1, 1, 2, 2]],
    'retry current values' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'current_row_value'), [31, 31, 29, 27, 13]],
    'retry peer group sums' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'peer_group_sum'), [31, 31, 29, 27, 13]],
    'retry exclude current sums' => [static fn (): mixed => $plan240()['retry_exclude_current_sums_next240'], [100, 100, 102, 104, 118]],
    'retry exclude ties sums' => [static fn (): mixed => $plan240()['retry_exclude_ties_sums_next240'], [131, 131, 131, 131, 131]],
    'retry exclude group sums' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'exclude_group_sum'), [100, 100, 102, 104, 118]],
    'retry peer tokens' => [static fn (): mixed => array_column($plan240()['retry_peer_groups_next240'], 'peer_token'), ['retry240|31:9:1:31', 'live|31:10:1:31', 'retry240|29:7:1:29', 'retry240|27:5:1:27', 'stale|13:4:1:13']],
    'receipt savepoint' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['savepoint'], 'wp_options_rowvalue_window_groups_next240'],
    'receipt retry ids' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['retry_ids'], [9, 10, 7, 5, 4]],
    'receipt retry tokens' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['retry_peer_tokens'], ['retry240|31:9:1:31', 'live|31:10:1:31', 'retry240|29:7:1:29', 'retry240|27:5:1:27', 'stale|13:4:1:13']],
    'receipt exclude current total' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['retry_exclude_current_total'], 524],
    'receipt exclude ties total' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['retry_exclude_ties_total'], 655],
    'receipt distinct peer groups' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['retry_distinct_peer_groups'], 5],
    'receipt suppressed ids' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['suppressed_ids'], [7, 5, 8]],
    'receipt next source matches current' => [static fn (): mixed => $plan240()['retry_peer_group_receipt_next240']['next_source_matches_current'], true],
    'retry rollback restored transient feed' => [static fn (): mixed => in_array(3, array_column($plan240()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'suppressed delete restored orphaned cache' => [static fn (): mixed => in_array(8, array_column($plan240()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'retry release deletes timeout and network home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan240()['current_source_tables']['wp_options'], 'option_id'))), []],
    'retry row five status' => [static fn (): mixed => array_column($plan240()['current_source_tables']['wp_options'], 'status', 'option_id')[5], 'retry240'],
    'retry row seven status' => [static fn (): mixed => array_column($plan240()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry240'],
    'retry row nine status' => [static fn (): mixed => array_column($plan240()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry240'],
    'yield count' => [static fn (): mixed => $plan240()['yielded_returning_count'], 3],
    'suppressed count' => [static fn (): mixed => $plan240()['suppressed_returning_count'], 3],
    'retry count' => [static fn (): mixed => $plan240()['retry_returning_count'], 5],
    'change counts' => [static fn (): mixed => [$plan240()['yield_change_count'], $plan240()['attempt_change_count'], $plan240()['retry_change_count']], [3, 3, 5]],
    'changed tables' => [static fn (): mixed => $plan240()['changed_tables_after_release'], ['wp_options']],
    'row counts' => [static fn (): mixed => $plan240()['row_counts']['wp_options'], 8],
    'dependency peer groups' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-peer-groups-next240', $plan240()['dependencies_next240'], true), true],
    'dependency exclude current' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-exclude-current-row-next240', $plan240()['dependencies_next240'], true), true],
    'dependency exclude ties' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-exclude-ties-next240', $plan240()['dependencies_next240'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-rowvalue-returning-window-current-source-next240', $plan240()['dependencies_next240'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan240()['dependency_closure_next240'], 'no new support component needed'), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan240()['non_overlap_next240'], 'next236'), true],
    'non overlap mentions trigger returning' => [static fn (): mixed => str_contains($plan240()['non_overlap_next240'], 'trigger RETURNING'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan240()['savepoint'], 'custom_window_240'],
    'custom retry ids' => [static fn (): mixed => $customPlan240()['retry_peer_group_ids_next240'], [9, 7, 5]],
    'custom retry percent ranks' => [static fn (): mixed => array_column($customPlan240()['retry_peer_groups_next240'], 'percent_rank'), [0, 0.5, 1]],
    'custom retry cume dist' => [static fn (): mixed => array_column($customPlan240()['retry_peer_groups_next240'], 'cume_dist'), [0.3333333333333333, 0.6666666666666666, 1]],
    'custom receipt exclude current total' => [static fn (): mixed => $customPlan240()['retry_peer_group_receipt_next240']['retry_exclude_current_total'], 174],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt($tables240, [], [$attemptUpdate240], [$retryUpdate240], $unique240), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt($tables240, [$yieldUpdate240], [], [$retryUpdate240], $unique240), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt($tables240, [$yieldUpdate240], [$attemptUpdate240], [], $unique240), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt($tables240, [$yieldUpdate240], [$attemptUpdate240], [$retryUpdate240], $unique240, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases240 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next240 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
