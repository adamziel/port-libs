<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
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

$valuesDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (2, '_transient_feed')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (2, '_transient_feed')) AS feed_pair ORDER BY option_id";
$valuesNotInUpdate = "UPDATE wp_options SET (status, option_value) = ('kept184', option_value || ':kept184') WHERE (blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (1, 'home'), (2, 'siteurl'), (2, 'home')) RETURNING option_id, option_name, status, option_value, (blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'siteurl')) AS not_siteurl ORDER BY option_id";
$outerDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$outerUpdate = "UPDATE wp_options SET (status, option_value) = ('outer184', option_value || ':outer184') WHERE (blog_id, option_name) IN (VALUES (3, 'rewrite_rules')) RETURNING option_id, option_name, status, option_value";
$savepointDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (2, '_transient_feed')) RETURNING option_id, blog_id, option_name";
$rollbackUpdate = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate184') WHERE (blog_id, option_name) IN (VALUES (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status";
$retryUpdate = "UPDATE wp_options SET (status, option_value) = ('retry184', option_value || ':retry184') WHERE (blog_id, option_name) IN (VALUES (3, 'rewrite_rules'), (3, 'orphaned_cache')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (2, '_transient_feed')) RETURNING option_id, blog_id, option_name ORDER BY option_id";

$deleteOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($valuesDelete, $tables, 'option_id', $unique);
$notInUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($valuesNotInUpdate, $tables, 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeValuesRetrySavepointBatch(
    $tables,
    [$outerDelete, $outerUpdate],
    [$savepointDelete, $rollbackUpdate],
    [$retryUpdate, $retryDelete],
    $unique,
    'wp_options_values_rowvalue_txn_next184',
    'wp_options_values_rowvalue_savepoint_next184',
);

$cases = [
    'values delete selected ids' => [static fn (): mixed => $deleteOnly()['plan']->selectedIds, [3, 4, 7]],
    'values delete mutation ids' => [static fn (): mixed => $deleteOnly()['plan']->mutationIds, [3, 4, 7]],
    'values delete returning ids ordered' => [static fn (): mixed => array_column($deleteOnly()['returning'], 'option_id'), [3, 4, 7]],
    'values delete returning names' => [static fn (): mixed => array_column($deleteOnly()['returning'], 'option_name'), ['_transient_feed', '_transient_timeout_feed', '_transient_feed']],
    'values delete returning expression true for feed pairs only' => [static fn (): mixed => array_column($deleteOnly()['returning'], 'feed_pair'), [1, 0, 1]],
    'values delete final ids' => [static fn (): mixed => array_column($deleteOnly()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 8, 9]],
    'values delete parser preserves returning expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($valuesDelete)['returning'], "option_id, blog_id, option_name, (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (2, '_transient_feed')) AS feed_pair"],
    'values delete parser preserves values where clause' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($valuesDelete)['where'], "(blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (2, '_transient_feed'))"],

    'values not in update selected non core ids' => [static fn (): mixed => $notInUpdate()['plan']->selectedIds, [3, 4, 7, 8, 9]],
    'values not in update returning ids' => [static fn (): mixed => array_column($notInUpdate()['returning'], 'option_id'), [3, 4, 7, 8, 9]],
    'values not in update status assigned' => [static fn (): mixed => array_unique(array_column($notInUpdate()['returning'], 'status')), ['kept184']],
    'values not in update option values use source rows' => [static fn (): mixed => array_column($notInUpdate()['returning'], 'option_value'), ['feed:kept184', 'timeout:kept184', 'network-feed:kept184', 'rules:kept184', 'cache:kept184']],
    'values not in returning expression excludes no siteurl rows' => [static fn (): mixed => array_column($notInUpdate()['returning'], 'not_siteurl'), [1, 1, 1, 1, 1]],
    'values not in final keeps siteurl live' => [static fn (): mixed => array_column($notInUpdate()['tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'values not in final updates orphan row' => [static fn (): mixed => array_column($notInUpdate()['tables']['wp_options'], 'status', 'option_id')[9], 'kept184'],
    'empty row value values list rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES) RETURNING option_id", $tables, 'option_id', $unique), InvalidArgumentException::class],

    'savepoint plan status' => [static fn (): mixed => $plan()['status'], 'transaction-rolled-back-retried'],
    'savepoint transaction name' => [static fn (): mixed => $plan()['transaction'], 'wp_options_values_rowvalue_txn_next184'],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_values_rowvalue_savepoint_next184'],
    'savepoint rollback ordinal' => [static fn (): mixed => $plan()['rollback_statement_ordinal'], 1],
    'savepoint rollback reason uses values selected row' => [static fn (): mixed => $plan()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|home using OR ROLLBACK'],
    'outer delete uses values selected ids' => [static fn (): mixed => $plan()['outer_statements'][0]['selected_ids'], [3, 4]],
    'outer update uses values selected ids' => [static fn (): mixed => $plan()['outer_statements'][1]['selected_ids'], [8]],
    'outer update returning status' => [static fn (): mixed => $plan()['outer_statements'][1]['returning_rows'][0]['status'], 'outer184'],
    'outer yielded returning ids before rollback' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'option_id'), [3, 4]],
    'savepoint image includes outer effects' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'savepoint image row eight is outer updated' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'status', 'option_id')[8], 'outer184'],
    'savepoint delete uses values selected ids' => [static fn (): mixed => $plan()['savepoint_statements'][0]['selected_ids'], [7]],
    'failed current source includes savepoint delete' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 8, 9]],
    'savepoint failed update action' => [static fn (): mixed => $plan()['savepoint_statements'][1]['action'], 'update'],
    'savepoint failed update conflict action rollback' => [static fn (): mixed => $plan()['savepoint_statements'][1]['conflict_action'], 'rollback'],
    'discarded returning phases' => [static fn (): mixed => array_column($plan()['discarded_returning'], 'phase'), ['outer', 'outer', 'savepoint']],
    'discarded returning count' => [static fn (): mixed => $plan()['discarded_returning_count'], 4],
    'attempted changes before rollback' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 4],
    'rollback source restores all ids' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), range(1, 9)],
    'rollback source restores row eight status' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'retry update uses values selected ids' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [8, 9]],
    'retry update returning statuses' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'status'), ['retry184', 'retry184']],
    'retry update returning values from rollback source' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_value'), ['rules:retry184', 'cache:retry184']],
    'retry delete uses values selected ids' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [3, 4, 7]],
    'retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4, 7]],
    'yielded returning count after retry' => [static fn (): mixed => $plan()['yielded_returning_count'], 5],
    'changes after retry' => [static fn (): mixed => $plan()['changes_after_retry'], 5],
    'current source final ids' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 8, 9]],
    'current source row eight status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry184'],
    'current source row nine status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry184'],
    'next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'row count after retry' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 6],
    'changed tables after retry' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'dependency marks rollback transaction path' => [static fn (): mixed => in_array('sqlite-update-or-rollback-rowvalue-returning-rolls-back-transaction-next178', $plan()['dependencies'], true), true],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next184 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
