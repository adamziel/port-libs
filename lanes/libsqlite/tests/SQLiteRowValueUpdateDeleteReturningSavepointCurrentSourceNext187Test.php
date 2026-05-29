<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 9, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 27, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 28, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 11, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => null, 'bytes' => 6, 'option_value' => 'cache'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$outerUpdate = "UPDATE wp_options SET (status, option_value) = ('outer187', option_value || ':outer187') WHERE (blog_id, option_name) IN (VALUES (3, 'rewrite_rules')) RETURNING option_id, option_name, status, option_value";
$outerDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name";
$savepointDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (2, '_transient_feed')) RETURNING option_id, blog_id, option_name";
$savepointUpdate = "UPDATE wp_options SET (status, option_value) = ('savepoint187', option_value || ':savepoint187') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value";
$abortUpdate = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate187') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status";
$retryUpdate = "UPDATE wp_options SET (status, option_value) = ('retry187', option_value || ':retry187') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (2, '_transient_feed')) RETURNING option_id, blog_id, option_name";

$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortSavepointRetry(
    $tables,
    [$outerUpdate, $outerDelete],
    [$savepointDelete, $savepointUpdate, $abortUpdate],
    [$retryUpdate, $retryDelete],
    $unique,
    'wp_options_abort_txn_next187',
    'wp_options_abort_savepoint_next187',
);

$abortOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($abortUpdate, $tables, 'option_id', $unique);

$cases = [
    'standalone abort update raises unique conflict' => [static fn (): mixed => $abortOnly(), InvalidArgumentException::class],
    'status marks savepoint retry' => [static fn (): mixed => $plan()['status'], 'savepoint-rolled-back-retried-current-source-next187'],
    'transaction name recorded' => [static fn (): mixed => $plan()['transaction'], 'wp_options_abort_txn_next187'],
    'savepoint name recorded' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_abort_savepoint_next187'],
    'transaction not rolled back' => [static fn (): mixed => $plan()['rolled_back_transaction'], false],
    'savepoint rolled back' => [static fn (): mixed => $plan()['rolled_back_savepoint'], true],
    'savepoint remains usable after rollback to' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback'], true],
    'abort ordinal after savepoint work' => [static fn (): mixed => $plan()['rollback_statement_ordinal'], 2],
    'abort reason names constraint' => [static fn (): mixed => $plan()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|home using OR ABORT'],
    'outer update selected rewrite rules' => [static fn (): mixed => $plan()['outer_statements'][0]['selected_ids'], [8]],
    'outer update returning status' => [static fn (): mixed => $plan()['outer_statements'][0]['returning_rows'][0]['status'], 'outer187'],
    'outer delete selected timeout transient' => [static fn (): mixed => $plan()['outer_statements'][1]['selected_ids'], [4]],
    'outer current removed row four' => [static fn (): mixed => array_column($plan()['outer_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7, 8, 9]],
    'outer current preserves row eight update' => [static fn (): mixed => array_column($plan()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'outer187'],
    'outer returning count two' => [static fn (): mixed => $plan()['outer_returning_count'], 2],
    'outer returning phases' => [static fn (): mixed => array_column($plan()['outer_returning'], 'phase'), ['outer', 'outer']],
    'savepoint image includes outer delete' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7, 8, 9]],
    'savepoint image keeps outer rewrite status' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'status', 'option_id')[8], 'outer187'],
    'savepoint delete selected network transient' => [static fn (): mixed => $plan()['savepoint_statements'][0]['selected_ids'], [7]],
    'savepoint update selected orphan' => [static fn (): mixed => $plan()['savepoint_statements'][1]['selected_ids'], [9]],
    'savepoint update returning is discarded later' => [static fn (): mixed => $plan()['savepoint_statements'][1]['returning_rows'][0]['status'], 'savepoint187'],
    'abort statement summary action update' => [static fn (): mixed => $plan()['savepoint_statements'][2]['action'], 'update'],
    'abort statement summary conflict action' => [static fn (): mixed => $plan()['savepoint_statements'][2]['conflict_action'], 'abort'],
    'abort statement summary failed message' => [static fn (): mixed => $plan()['savepoint_statements'][2]['failed_conflict']['message'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|home using OR ABORT'],
    'failed source contains savepoint delete' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 8, 9]],
    'failed source contains savepoint orphan mutation' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'savepoint187'],
    'rollback to source restores savepoint deleted row' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7, 8, 9]],
    'rollback to source preserves outer deleted row four' => [static fn (): mixed => in_array(4, array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback to source preserves outer row eight status' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'outer187'],
    'rollback to source restores orphan status null' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[9], null],
    'discarded returning phases are savepoint only' => [static fn (): mixed => array_column($plan()['discarded_returning'], 'phase'), ['savepoint', 'savepoint']],
    'discarded returning count two' => [static fn (): mixed => $plan()['discarded_returning_count'], 2],
    'discarded delete returning id seven' => [static fn (): mixed => $plan()['discarded_returning'][0]['rows'][0]['option_id'], 7],
    'discarded update returning id nine' => [static fn (): mixed => $plan()['discarded_returning'][1]['rows'][0]['option_id'], 9],
    'attempted changes before rollback excludes outer' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 2],
    'outer changes preserved count' => [static fn (): mixed => $plan()['outer_changes_preserved'], 2],
    'retry update selected orphan from savepoint image' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [9]],
    'retry update source row restored before retry' => [static fn (): mixed => $plan()['retry_statements'][0]['source_rows'][0]['option_value'], 'cache'],
    'retry update returning status' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['status'], 'retry187'],
    'retry update returning value' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['option_value'], 'cache:retry187'],
    'retry delete selected network transient again' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [7]],
    'retry delete returning id seven' => [static fn (): mixed => $plan()['yielded_returning'][1]['rows'][0]['option_id'], 7],
    'yielded returning phases are retry' => [static fn (): mixed => array_column($plan()['yielded_returning'], 'phase'), ['retry', 'retry']],
    'yielded returning count two' => [static fn (): mixed => $plan()['yielded_returning_count'], 2],
    'changes after retry two' => [static fn (): mixed => $plan()['changes_after_retry'], 2],
    'final ids preserve outer delete and retry delete' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 8, 9]],
    'final row eight keeps outer status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'outer187'],
    'final orphan has retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry187'],
    'final row seven removed by retry delete' => [static fn (): mixed => in_array(7, array_column($plan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'changed tables after retry' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'row count final seven' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'dependency marks abort preservation' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-abort-preserves-outer-transaction-next187', $plan()['dependencies'], true), true],
    'dependency marks discarded returning' => [static fn (): mixed => in_array('sqlite-rowvalue-abort-savepoint-discards-attempted-returning-next187', $plan()['dependencies'], true), true],
    'dependency marks retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-abort-retry-reads-savepoint-image-next187', $plan()['dependencies'], true), true],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next187 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
