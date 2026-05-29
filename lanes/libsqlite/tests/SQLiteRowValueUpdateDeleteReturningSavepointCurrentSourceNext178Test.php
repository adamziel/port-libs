<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 23, 'option_value' => 'https://home.test'],
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

$outerDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, (blog_id, status) = (1, 'stale') AS stale_pair ORDER BY option_id";
$outerUpdate = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':outer', 'outer', option_value || ':outer', bytes + 1) WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, option_name, status, option_value, bytes";
$savepointDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, '_transient_feed')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((2, '_transient_feed')) AS deleted_pair";
$rollbackUpdate = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate') WHERE (blog_id, option_name) = (3, 'orphaned_cache') RETURNING option_id, blog_id, option_name, status";
$retryUpdate = "UPDATE wp_options SET (option_name, status, option_value) = (option_name || ':retry', 'retry', option_value || ':retry') WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (2, '_transient_feed')) RETURNING option_id, blog_id, option_name ORDER BY option_id";

$outerDeleteOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerDelete, $tables, 'option_id', $unique);
$outerUpdateAfterDelete = static function () use ($outerDelete, $outerUpdate, $tables, $unique): array {
    $afterDelete = SQLiteUpdateDeleteReturningSql::execute($outerDelete, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($outerUpdate, $afterDelete['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch(
    $tables,
    [$outerDelete, $outerUpdate],
    [$savepointDelete, $rollbackUpdate],
    [$retryUpdate, $retryDelete],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch(
    $tables,
    [$outerDelete],
    [$savepointDelete],
    [$retryUpdate],
    $unique,
    'clean_txn',
    'clean_savepoint',
);

$cases = [
    'outer delete selected stale rows' => [static fn (): mixed => $outerDeleteOnly()['plan']->selectedIds, [3, 4]],
    'outer delete returning row-value flags true' => [static fn (): mixed => array_column($outerDeleteOnly()['returning'], 'stale_pair'), [1, 1]],
    'outer delete current source omits stale rows' => [static fn (): mixed => array_column($outerDeleteOnly()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'outer update after delete selects rewrite row' => [static fn (): mixed => $outerUpdateAfterDelete()['plan']->selectedIds, [8]],
    'outer update returning uses post update values' => [static fn (): mixed => $outerUpdateAfterDelete()['returning'][0]['option_name'], 'rewrite_rules:outer'],
    'outer update bytes incremented' => [static fn (): mixed => $outerUpdateAfterDelete()['returning'][0]['bytes'], 15],

    'rollback plan status' => [static fn (): mixed => $plan()['status'], 'transaction-rolled-back-retried'],
    'rollback plan transaction name' => [static fn (): mixed => $plan()['transaction'], 'wp_options_import_txn'],
    'rollback plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_rollback_batch'],
    'rollback plan rolls back transaction' => [static fn (): mixed => $plan()['rolled_back_transaction'], true],
    'rollback plan rolls back savepoint' => [static fn (): mixed => $plan()['rolled_back_savepoint'], true],
    'rollback plan savepoint not preserved after rollback conflict' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback'], false],
    'rollback ordinal is conflicting update' => [static fn (): mixed => $plan()['rollback_statement_ordinal'], 1],
    'rollback reason names unique conflict' => [static fn (): mixed => $plan()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|home using OR ROLLBACK'],
    'transaction image keeps original ids' => [static fn (): mixed => array_column($plan()['transaction_image_tables']['wp_options'], 'option_id'), range(1, 9)],
    'savepoint image includes outer delete and update' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'savepoint image row eight is outer updated' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules:outer'],
    'failed current source includes savepoint delete' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 8, 9]],
    'rollback source restores original ids before retry' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), range(1, 9)],
    'rollback source restores row eight original name' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'outer statements recorded' => [static fn (): mixed => count($plan()['outer_statements']), 2],
    'outer actions delete then update' => [static fn (): mixed => array_column($plan()['outer_statements'], 'action'), ['delete', 'update']],
    'outer delete source rows original stale ids' => [static fn (): mixed => array_column($plan()['outer_statements'][0]['source_rows'], 'option_id'), [3, 4]],
    'outer update source row is original rewrite row after delete' => [static fn (): mixed => array_column($plan()['outer_statements'][1]['source_rows'], 'option_id'), [8]],
    'savepoint statements include delete and failed update' => [static fn (): mixed => array_column($plan()['savepoint_statements'], 'action'), ['delete', 'update']],
    'savepoint failed statement conflict action rollback' => [static fn (): mixed => $plan()['savepoint_statements'][1]['conflict_action'], 'rollback'],
    'savepoint failed statement has no returning rows' => [static fn (): mixed => $plan()['savepoint_statements'][1]['returning_rows'], []],
    'savepoint delete returned network transient' => [static fn (): mixed => array_column($plan()['savepoint_statements'][0]['returning_rows'], 'option_id'), [7]],
    'discarded returning phases include outer and savepoint' => [static fn (): mixed => array_column($plan()['discarded_returning'], 'phase'), ['outer', 'outer', 'savepoint']],
    'discarded returning count includes outer delete update and savepoint delete' => [static fn (): mixed => $plan()['discarded_returning_count'], 4],
    'attempted changes include discarded four rows' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 4],
    'retry statements count' => [static fn (): mixed => count($plan()['retry_statements']), 2],
    'retry actions update delete' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'retry update reads restored rewrite and orphan rows' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [8, 9]],
    'retry update returning names have retry suffix' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_name'), ['rewrite_rules:retry', 'orphaned_cache:retry']],
    'retry update returning values have retry suffix' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_value'), ['rules:retry', 'cache:retry']],
    'retry delete reads retry current source and deletes original stale rows' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [3, 4, 7]],
    'retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4, 7]],
    'yielded returning count after retry' => [static fn (): mixed => $plan()['yielded_returning_count'], 5],
    'changes after retry count' => [static fn (): mixed => $plan()['changes_after_retry'], 5],
    'current source ids after retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 8, 9]],
    'current source row eight retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry'],
    'current source row nine retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry'],
    'current source row seven deleted by retry not by savepoint' => [static fn (): mixed => in_array(7, array_column($plan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'row count after retry' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 6],
    'changed tables after retry' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'dependency marks rollback conflict' => [static fn (): mixed => in_array('sqlite-update-or-rollback-rowvalue-returning-rolls-back-transaction-next178', $plan()['dependencies'], true), true],
    'dependency marks returning discard' => [static fn (): mixed => in_array('sqlite-rollback-conflict-discards-outer-and-savepoint-returning-next178', $plan()['dependencies'], true), true],
    'dependency marks retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-after-rollback-reads-transaction-image-next178', $plan()['dependencies'], true), true],

    'clean plan status when no rollback conflict' => [static fn (): mixed => $cleanPlan()['status'], 'savepoint-released-retried'],
    'clean plan not rolled back' => [static fn (): mixed => $cleanPlan()['rolled_back_transaction'], false],
    'clean plan rollback reason null' => [static fn (): mixed => $cleanPlan()['rollback_reason'], null],
    'clean plan retry source keeps released savepoint delete' => [static fn (): mixed => array_column($cleanPlan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 8, 9]],
    'clean plan discarded returning empty' => [static fn (): mixed => $cleanPlan()['discarded_returning'], []],
    'clean plan retry update sees unreverted rewrite and orphan rows' => [static fn (): mixed => $cleanPlan()['retry_statements'][0]['selected_ids'], [8, 9]],

    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch($tables, [], [$savepointDelete], [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch($tables, [$outerDelete], [], [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch($tables, [$outerDelete], [$savepointDelete], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch($tables, [$outerDelete], [$savepointDelete], [$retryUpdate], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch(['wp_options' => ['bad']], [$outerDelete], [$savepointDelete], [$retryUpdate], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next178 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
