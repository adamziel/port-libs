<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows233 = [
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
$tables233 = ['wp_options' => $rows233];
$unique233 = [['blog_id', 'option_name']];

$yieldUpdate233 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield233', option_value || ':yield233', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) AS tuple_hit ORDER BY option_id";
$yieldDelete233 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) IN ((1, '_transient_feed')) AS tuple_hit ORDER BY option_id";
$attemptUpdate233 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt233', option_value || ':attempt233', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes, (status, option_name) = ('attempt233', 'rewrite_rules') AS tuple_hit ORDER BY option_id";
$attemptDelete233 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS tuple_hit ORDER BY option_id";
$retryUpdate233 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry233', option_value || ':retry233', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes, (status, option_name) = ('retry233', 'rewrite_rules') AS tuple_hit ORDER BY option_id";
$retryDelete233 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT IN ((4, 'home')) AS tuple_hit ORDER BY option_id";

$yieldUpdateResult233 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldUpdate233, $tables233, 'option_id', $unique233);
$yieldDeleteResult233 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldDelete233, $yieldUpdateResult233()['tables'], 'option_id', $unique233);
$attemptUpdateResult233 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate233, $yieldDeleteResult233()['tables'], 'option_id', $unique233);
$attemptDeleteResult233 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete233, $attemptUpdateResult233()['tables'], 'option_id', $unique233);
$retryUpdateResult233 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate233, $tables233, 'option_id', $unique233);
$retryDeleteResult233 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete233, $retryUpdateResult233()['tables'], 'option_id', $unique233);
$plan233 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry(
    $tables233,
    [$yieldUpdate233, $yieldDelete233],
    [$attemptUpdate233, $attemptDelete233],
    [$retryUpdate233, $retryDelete233],
    $unique233,
);
$customPlan233 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry(
    $tables233,
    [$yieldUpdate233],
    [$attemptUpdate233],
    [$retryUpdate233],
    $unique233,
    'custom_window_233',
);

$cases233 = [
    'parser yield update row value predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldUpdate233)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete returning tuple expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete233)['returning'], 'tuple_hit'), true],
    'yield update selected ids' => [static fn (): mixed => $yieldUpdateResult233()['plan']->selectedIds, [5, 7]],
    'yield update returning ids' => [static fn (): mixed => array_column($yieldUpdateResult233()['returning'], 'option_id'), [5, 7]],
    'yield update tuple flags' => [static fn (): mixed => array_column($yieldUpdateResult233()['returning'], 'tuple_hit'), [1, 1]],
    'yield delete selected transient id' => [static fn (): mixed => $yieldDeleteResult233()['plan']->selectedIds, [3]],
    'yield delete returning tuple true' => [static fn (): mixed => array_column($yieldDeleteResult233()['returning'], 'tuple_hit'), [1]],
    'attempt update selected ids after yield' => [static fn (): mixed => $attemptUpdateResult233()['plan']->selectedIds, [5, 7]],
    'attempt update bytes chained' => [static fn (): mixed => array_column($attemptUpdateResult233()['returning'], 'bytes', 'option_id'), [5 => 42, 7 => 44]],
    'attempt delete selected orphan id' => [static fn (): mixed => $attemptDeleteResult233()['plan']->selectedIds, [8]],
    'attempt delete tuple flag false' => [static fn (): mixed => array_column($attemptDeleteResult233()['returning'], 'tuple_hit'), [0]],
    'retry update selected ids from savepoint image' => [static fn (): mixed => $retryUpdateResult233()['plan']->selectedIds, [5, 7, 9]],
    'retry update bytes from original image' => [static fn (): mixed => array_column($retryUpdateResult233()['returning'], 'bytes', 'option_id'), [5 => 27, 7 => 29, 9 => 31]],
    'retry delete selected ids after retry update' => [static fn (): mixed => $retryDeleteResult233()['plan']->selectedIds, [4, 10]],
    'retry delete tuple flags' => [static fn (): mixed => array_column($retryDeleteResult233()['returning'], 'tuple_hit'), [1, 0]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult233()['tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7, 8, 9]],
    'plan status' => [static fn (): mixed => $plan233()['status'], 'rowvalue-update-delete-returning-window-current-source-next233'],
    'plan savepoint' => [static fn (): mixed => $plan233()['savepoint'], 'wp_options_rowvalue_returning_window_next233'],
    'plan yield current row five changed' => [static fn (): mixed => array_column($plan233()['yield_current_source_tables']['wp_options'], 'status', 'option_id')[5], 'yield233'],
    'plan yield current deleted row three' => [static fn (): mixed => in_array(3, array_column($plan233()['yield_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt current row five chained' => [static fn (): mixed => array_column($plan233()['attempt_current_source_tables']['wp_options'], 'bytes', 'option_id')[5], 42],
    'plan attempt current row eight deleted' => [static fn (): mixed => in_array(8, array_column($plan233()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores savepoint image' => [static fn (): mixed => $plan233()['rollback_current_source_tables'], $plan233()['savepoint_image_tables']],
    'plan retry current row five retry only' => [static fn (): mixed => array_column($plan233()['current_source_tables']['wp_options'], 'bytes', 'option_id')[5], 27],
    'plan retry current row seven retry only' => [static fn (): mixed => array_column($plan233()['current_source_tables']['wp_options'], 'bytes', 'option_id')[7], 29],
    'plan retry current row nine retry only' => [static fn (): mixed => array_column($plan233()['current_source_tables']['wp_options'], 'bytes', 'option_id')[9], 31],
    'plan retry restores row three after yielded delete' => [static fn (): mixed => in_array(3, array_column($plan233()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan retry deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan233()['current_source_tables']['wp_options'], 'option_id'))), []],
    'plan next source equals current' => [static fn (): mixed => $plan233()['next_source_tables'], $plan233()['current_source_tables']],
    'plan yield window id order by bytes desc' => [static fn (): mixed => array_column($plan233()['yield_window'], 'option_id'), [7, 5, 3]],
    'plan yield window row numbers' => [static fn (): mixed => array_column($plan233()['yield_window'], 'row_number'), [1, 2, 3]],
    'plan yield window dense ranks' => [static fn (): mixed => array_column($plan233()['yield_window'], 'dense_rank'), [1, 2, 3]],
    'plan yield window partition count' => [static fn (): mixed => array_column($plan233()['yield_window'], 'partition_count'), [3, 3, 3]],
    'plan yield window partition sum' => [static fn (): mixed => array_column($plan233()['yield_window'], 'partition_sum'), [88, 88, 88]],
    'plan suppressed window id order by bytes desc' => [static fn (): mixed => array_column($plan233()['suppressed_attempt_window'], 'option_id'), [7, 5, 8]],
    'plan suppressed window row numbers' => [static fn (): mixed => array_column($plan233()['suppressed_attempt_window'], 'row_number'), [1, 2, 3]],
    'plan suppressed window partition sum' => [static fn (): mixed => array_column($plan233()['suppressed_attempt_window'], 'partition_sum'), [91, 91, 91]],
    'plan retry window id order by bytes desc' => [static fn (): mixed => array_column($plan233()['retry_window'], 'option_id'), [9, 10, 7, 5, 4]],
    'plan retry window row numbers' => [static fn (): mixed => array_column($plan233()['retry_window'], 'row_number'), [1, 2, 3, 4, 5]],
    'plan retry window dense ranks' => [static fn (): mixed => array_column($plan233()['retry_window'], 'dense_rank'), [1, 1, 2, 3, 4]],
    'plan retry window partition count' => [static fn (): mixed => array_column($plan233()['retry_window'], 'partition_count'), [5, 5, 5, 5, 5]],
    'plan retry window partition sum' => [static fn (): mixed => array_column($plan233()['retry_window'], 'partition_sum'), [131, 131, 131, 131, 131]],
    'plan retry window phase marker' => [static fn (): mixed => $plan233()['retry_window'][1]['phase_marker'], 'live#2'],
    'plan all receipt yield ids original returning order' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['yield_ids'], [5, 7, 3]],
    'plan all receipt yield window ids' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['yield_window_ids'], [7, 5, 3]],
    'plan all receipt suppressed ids' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['suppressed_ids'], [5, 7, 8]],
    'plan all receipt suppressed window ids' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['suppressed_window_ids'], [7, 5, 8]],
    'plan all receipt retry ids original returning order' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['retry_ids'], [5, 7, 9, 4, 10]],
    'plan all receipt retry window ids' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['retry_window_ids'], [9, 10, 7, 5, 4]],
    'plan all receipt retry sum' => [static fn (): mixed => $plan233()['all_window_receipt_next233']['retry_sum'], 131],
    'plan yielded count' => [static fn (): mixed => $plan233()['yielded_returning_count'], 3],
    'plan suppressed count' => [static fn (): mixed => $plan233()['suppressed_returning_count'], 3],
    'plan retry count' => [static fn (): mixed => $plan233()['retry_returning_count'], 5],
    'plan yield change count' => [static fn (): mixed => $plan233()['yield_change_count'], 3],
    'plan attempt change count' => [static fn (): mixed => $plan233()['attempt_change_count'], 3],
    'plan retry change count' => [static fn (): mixed => $plan233()['retry_change_count'], 5],
    'plan flags yielded survives' => [static fn (): mixed => $plan233()['window_yield_survives_rollback_next233'], true],
    'plan flags attempt suppressed' => [static fn (): mixed => $plan233()['window_attempt_suppressed_after_rollback_next233'], true],
    'plan flags retry image' => [static fn (): mixed => $plan233()['window_retry_reads_savepoint_image_next233'], true],
    'plan flags release commits retry' => [static fn (): mixed => $plan233()['window_release_commits_retry_next233'], true],
    'plan statement phases' => [static fn (): mixed => array_column($plan233()['yield_statements'], 'phase'), ['yield-window-before-rollback-to-next233', 'yield-window-before-rollback-to-next233']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan233()['attempt_statements'], 'phase'), ['attempt-window-after-yield-before-rollback-to-next233', 'attempt-window-after-yield-before-rollback-to-next233']],
    'plan retry phases' => [static fn (): mixed => array_column($plan233()['retry_statements'], 'phase'), ['retry-window-after-rollback-release-next233', 'retry-window-after-rollback-release-next233']],
    'plan retry source rows came from savepoint image' => [static fn (): mixed => array_column($plan233()['retry_statements'][0]['source_rows'], 'status'), ['queued', 'queued', 'queued']],
    'plan changed tables after release' => [static fn (): mixed => $plan233()['changed_tables_after_release'], ['wp_options']],
    'plan wp options row count after release' => [static fn (): mixed => $plan233()['row_counts']['wp_options'], 8],
    'plan dependency rowvalue window' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-window-next233', $plan233()['dependencies'], true), true],
    'plan dependency release' => [static fn (): mixed => in_array('sqlite-returning-window-rollback-to-release-current-source-next233', $plan233()['dependencies'], true), true],
    'plan dependency application' => [static fn (): mixed => in_array('application-rowvalue-returning-window-current-source-next233', $plan233()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan233()['dependency_closure_next233'], 'no new support component needed'), true],
    'plan non overlap mentions next229' => [static fn (): mixed => str_contains($plan233()['non_overlap_next233'], 'next229'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan233()['savepoint'], 'custom_window_233'],
    'custom yielded count' => [static fn (): mixed => $customPlan233()['yielded_returning_count'], 2],
    'custom suppressed count' => [static fn (): mixed => $customPlan233()['suppressed_returning_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan233()['retry_returning_count'], 3],
    'custom retry window ids' => [static fn (): mixed => array_column($customPlan233()['retry_window'], 'option_id'), [9, 7, 5]],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry($tables233, [], [$attemptUpdate233], [$retryUpdate233], $unique233), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry($tables233, [$yieldUpdate233], [], [$retryUpdate233], $unique233), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry($tables233, [$yieldUpdate233], [$attemptUpdate233], [], $unique233), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry($tables233, [$yieldUpdate233], [$attemptUpdate233], [$retryUpdate233], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry($tables233, [$yieldUpdate233], [$attemptUpdate233], [$retryUpdate233], $unique233, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry(['wp_options' => ['bad']], [$yieldUpdate233], [$attemptUpdate233], [$retryUpdate233], $unique233), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases233 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next233 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
