<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows246 = [
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
$tables246 = ['wp_options' => $rows246];
$unique246 = [['blog_id', 'option_name']];

$yieldUpdate246 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield246', option_value || ':yield246', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete246 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate246 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt246', option_value || ':attempt246', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete246 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate246 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry246', option_value || ':retry246', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'), (5, 'network_plugin')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete246 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan246 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext246(
    $tables246,
    [$yieldUpdate246, $yieldDelete246],
    [$attemptUpdate246, $attemptDelete246],
    [$retryUpdate246, $retryDelete246],
    $unique246,
);
$customPlan246 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext246(
    $tables246,
    [$yieldUpdate246],
    [$attemptUpdate246],
    [$retryUpdate246],
    $unique246,
    'custom_window_246',
);

$retryUpdateKey246 = 'retry-window-after-rollback-release-next233#0#update';
$retryDeleteKey246 = 'retry-window-after-rollback-release-next233#1#delete';
$suppressedUpdateKey246 = 'attempt-window-after-yield-before-rollback-to-next233#0#update';
$suppressedDeleteKey246 = 'attempt-window-after-yield-before-rollback-to-next233#1#delete';
$yieldUpdateKey246 = 'yield-window-before-rollback-to-next233#0#update';
$yieldDeleteKey246 = 'yield-window-before-rollback-to-next233#1#delete';

$cases246 = [
    'parser retry update row value retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate246)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch'), (5, 'network_plugin'))"],
    'parser retry delete order retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete246)['order_by'], [['column' => 'option_id']]],
    'direct retry update ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($retryUpdate246, $tables246, 'option_id', $unique246)['plan']->selectedIds, [5, 7, 9, 11]],
    'direct retry delete ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($retryDelete246, $tables246, 'option_id', $unique246)['plan']->selectedIds, [4, 10]],
    'plan status' => [static fn (): mixed => $plan246()['status'], 'rowvalue-update-delete-returning-window-current-source-next246'],
    'plan savepoint' => [static fn (): mixed => $plan246()['savepoint'], 'wp_options_rowvalue_window_current_next246'],
    'inherits next242 flag' => [static fn (): mixed => $plan246()['returning_window_current_source_next242'], true],
    'next246 flag' => [static fn (): mixed => $plan246()['returning_window_current_source_next246'], true],
    'retry partition keys' => [static fn (): mixed => array_keys($plan246()['retry_filter_windows_next246']), [$retryUpdateKey246, $retryDeleteKey246]],
    'suppressed partition keys' => [static fn (): mixed => array_keys($plan246()['suppressed_filter_windows_next246']), [$suppressedUpdateKey246, $suppressedDeleteKey246]],
    'yield partition keys' => [static fn (): mixed => array_keys($plan246()['yield_filter_windows_next246']), [$yieldUpdateKey246, $yieldDeleteKey246]],
    'retry update ids' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryUpdateKey246], 'option_id'), [9, 11, 7, 5]],
    'retry delete ids' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryDeleteKey246], 'option_id'), [10, 4]],
    'suppressed update ids' => [static fn (): mixed => array_column($plan246()['suppressed_filter_windows_next246'][$suppressedUpdateKey246], 'option_id'), [7, 5]],
    'suppressed delete ids' => [static fn (): mixed => array_column($plan246()['suppressed_filter_windows_next246'][$suppressedDeleteKey246], 'option_id'), [8]],
    'yield update ids' => [static fn (): mixed => array_column($plan246()['yield_filter_windows_next246'][$yieldUpdateKey246], 'option_id'), [7, 5]],
    'yield delete ids' => [static fn (): mixed => array_column($plan246()['yield_filter_windows_next246'][$yieldDeleteKey246], 'option_id'), [3]],
    'retry update filter counts' => [static fn (): mixed => [$plan246()['retry_filter_windows_next246'][$retryUpdateKey246][0]['filter_update_count_next246'], $plan246()['retry_filter_windows_next246'][$retryUpdateKey246][0]['filter_delete_count_next246'], $plan246()['retry_filter_windows_next246'][$retryUpdateKey246][0]['filter_total_count_next246']], [4, 0, 4]],
    'retry delete filter counts' => [static fn (): mixed => [$plan246()['retry_filter_windows_next246'][$retryDeleteKey246][0]['filter_update_count_next246'], $plan246()['retry_filter_windows_next246'][$retryDeleteKey246][0]['filter_delete_count_next246'], $plan246()['retry_filter_windows_next246'][$retryDeleteKey246][0]['filter_total_count_next246']], [0, 2, 2]],
    'suppressed update filter counts' => [static fn (): mixed => [$plan246()['suppressed_filter_windows_next246'][$suppressedUpdateKey246][0]['filter_update_count_next246'], $plan246()['suppressed_filter_windows_next246'][$suppressedUpdateKey246][0]['filter_delete_count_next246']], [2, 0]],
    'suppressed delete filter counts' => [static fn (): mixed => [$plan246()['suppressed_filter_windows_next246'][$suppressedDeleteKey246][0]['filter_update_count_next246'], $plan246()['suppressed_filter_windows_next246'][$suppressedDeleteKey246][0]['filter_delete_count_next246']], [0, 1]],
    'yield update filter counts' => [static fn (): mixed => [$plan246()['yield_filter_windows_next246'][$yieldUpdateKey246][0]['filter_update_count_next246'], $plan246()['yield_filter_windows_next246'][$yieldUpdateKey246][0]['filter_delete_count_next246']], [2, 0]],
    'yield delete filter counts' => [static fn (): mixed => [$plan246()['yield_filter_windows_next246'][$yieldDeleteKey246][0]['filter_update_count_next246'], $plan246()['yield_filter_windows_next246'][$yieldDeleteKey246][0]['filter_delete_count_next246']], [0, 1]],
    'retry update bytes' => [static fn (): mixed => $plan246()['retry_filter_summary_next246'][$retryUpdateKey246]['total_bytes'], 118],
    'retry delete bytes' => [static fn (): mixed => $plan246()['retry_filter_summary_next246'][$retryDeleteKey246]['total_bytes'], 44],
    'suppressed update bytes' => [static fn (): mixed => $plan246()['suppressed_filter_summary_next246'][$suppressedUpdateKey246]['total_bytes'], 86],
    'suppressed delete bytes' => [static fn (): mixed => $plan246()['suppressed_filter_summary_next246'][$suppressedDeleteKey246]['total_bytes'], 5],
    'yield update bytes' => [static fn (): mixed => $plan246()['yield_filter_summary_next246'][$yieldUpdateKey246]['total_bytes'], 76],
    'yield delete bytes' => [static fn (): mixed => $plan246()['yield_filter_summary_next246'][$yieldDeleteKey246]['total_bytes'], 12],
    'retry update summary ids' => [static fn (): mixed => $plan246()['retry_filter_summary_next246'][$retryUpdateKey246]['update_ids'], [9, 11, 7, 5]],
    'retry delete summary ids' => [static fn (): mixed => $plan246()['retry_filter_summary_next246'][$retryDeleteKey246]['delete_ids'], [10, 4]],
    'retry update action flags' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryUpdateKey246], 'filter_update_match_next246'), [true, true, true, true]],
    'retry update delete flags' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryUpdateKey246], 'filter_delete_match_next246'), [false, false, false, false]],
    'retry delete action flags' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryDeleteKey246], 'filter_delete_match_next246'), [true, true]],
    'retry delete update flags' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryDeleteKey246], 'filter_update_match_next246'), [false, false]],
    'retry update peer counts' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryUpdateKey246], 'filter_peer_count_next246'), [2, 2, 1, 1]],
    'retry update frame counts' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryUpdateKey246], 'filter_frame_count_next246'), [2, 3, 3, 2]],
    'retry delete frame counts' => [static fn (): mixed => array_column($plan246()['retry_filter_windows_next246'][$retryDeleteKey246], 'filter_frame_count_next246'), [2, 2]],
    'retry update receipts' => [static fn (): mixed => $plan246()['retry_filter_summary_next246'][$retryUpdateKey246]['receipts'], ['retry-release-current-source-next246:' . $retryUpdateKey246 . ':9:update:4:0:118', 'retry-release-current-source-next246:' . $retryUpdateKey246 . ':11:update:4:0:118', 'retry-release-current-source-next246:' . $retryUpdateKey246 . ':7:update:4:0:118', 'retry-release-current-source-next246:' . $retryUpdateKey246 . ':5:update:4:0:118']],
    'retry delete receipts' => [static fn (): mixed => $plan246()['retry_filter_summary_next246'][$retryDeleteKey246]['receipts'], ['retry-release-current-source-next246:' . $retryDeleteKey246 . ':10:delete:0:2:44', 'retry-release-current-source-next246:' . $retryDeleteKey246 . ':4:delete:0:2:44']],
    'audit retry ids' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['retry_ids'], [9, 11, 7, 5, 10, 4]],
    'audit retry update ids' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['retry_update_ids'], [9, 11, 7, 5]],
    'audit retry delete ids' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['retry_delete_ids'], [4, 10]],
    'audit suppressed ids' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['suppressed_ids'], [7, 5, 8]],
    'audit yield ids' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['yield_ids'], [7, 5, 3]],
    'audit final ids' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['final_ids'], [1, 2, 3, 5, 6, 7, 8, 9, 11]],
    'audit retry updates visible' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['retry_updates_visible_after_release'], true],
    'audit retry deletes absent' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['retry_deletes_absent_after_release'], true],
    'audit suppressed visible' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['suppressed_only_visible_after_release'], true],
    'audit yield delete restored' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['yield_delete_restored_by_rollback'], true],
    'audit digest lengths' => [static fn (): mixed => [strlen($plan246()['release_filter_audit_next246']['retry_filter_digest']), strlen($plan246()['release_filter_audit_next246']['suppressed_filter_digest']), strlen($plan246()['release_filter_audit_next246']['yield_filter_digest'])], [64, 64, 64]],
    'audit digests isolated' => [static fn (): mixed => $plan246()['release_filter_audit_next246']['digests_are_isolated'], true],
    'current source retry statuses' => [static fn (): mixed => [array_column($plan246()['current_source_tables']['wp_options'], 'status', 'option_id')[5], array_column($plan246()['current_source_tables']['wp_options'], 'status', 'option_id')[7], array_column($plan246()['current_source_tables']['wp_options'], 'status', 'option_id')[9], array_column($plan246()['current_source_tables']['wp_options'], 'status', 'option_id')[11]], ['retry246', 'retry246', 'retry246', 'retry246']],
    'current source retry deletes absent' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan246()['current_source_tables']['wp_options'], 'option_id'))), []],
    'current source suppressed orphan preserved' => [static fn (): mixed => array_column($plan246()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned'],
    'custom savepoint' => [static fn (): mixed => $customPlan246()['savepoint'], 'custom_window_246'],
    'custom retry keys' => [static fn (): mixed => array_keys($customPlan246()['retry_filter_windows_next246']), [$retryUpdateKey246]],
    'custom retry summary bytes' => [static fn (): mixed => $customPlan246()['retry_filter_summary_next246'][$retryUpdateKey246]['total_bytes'], 118],
    'custom audit retry ids' => [static fn (): mixed => $customPlan246()['release_filter_audit_next246']['retry_ids'], [9, 11, 7, 5]],
    'dependencies include filter' => [static fn (): mixed => in_array('sqlite-returning-window-filter-release-current-source-next246', $plan246()['dependencies_next246'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan246()['dependency_closure_next246'], 'no new support component needed'), true],
    'non overlap mentions next242' => [static fn (): mixed => str_contains($plan246()['non_overlap_next246'], 'next242 lag/lead'), true],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext246($tables246, [], [$attemptUpdate246], [$retryUpdate246], $unique246), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext246($tables246, [$yieldUpdate246], [], [$retryUpdate246], $unique246), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext246($tables246, [$yieldUpdate246], [$attemptUpdate246], [], $unique246), InvalidArgumentException::class],
    'malformed rowid rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext246($tables246, [$yieldUpdate246], [$attemptUpdate246], [$retryUpdate246], $unique246, 'sp246', 'missing_rowid'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases246 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next246 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
