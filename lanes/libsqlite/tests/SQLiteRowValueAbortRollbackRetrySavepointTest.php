<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$abortRows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$abortTables = ['wp_options' => $abortRows];
$abortUnique = [['blog_id', 'option_name']];

$stageSql = "UPDATE wp_options SET (status, option_value, bytes) = ('staged', option_value || ':stage', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) = (1, '_transient_feed') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$abortSql = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', 'duplicate', option_value || ':bad') WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value, (blog_id, option_name) = (1, 'siteurl') AS duplicate_key ORDER BY option_id";
$retrySql = "UPDATE wp_options SET (status, option_value, bytes) = ('retried', option_value || ':retry', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$cleanSql = "UPDATE wp_options SET (status, option_value, bytes) = ('clean', option_value || ':clean', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";

$parsedAbort = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($abortSql);
$afterStageDelete = static function () use ($stageSql, $deleteSql, $abortTables, $abortUnique): array {
    $staged = SQLiteUpdateDeleteReturningSql::execute($stageSql, $abortTables, 'option_id', $abortUnique);

    return SQLiteUpdateDeleteReturningSql::execute($deleteSql, $staged['tables'], 'option_id', $abortUnique);
};
$abortOnly = static function () use ($abortSql, $afterStageDelete, $abortUnique): mixed {
    return SQLiteUpdateDeleteReturningSql::execute($abortSql, $afterStageDelete()['tables'], 'option_id', $abortUnique);
};
$abortPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(
    $abortTables,
    [$stageSql, $deleteSql, $abortSql],
    [$retrySql, $cleanupSql],
    $abortUnique,
);
$cleanAbortPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(
    $abortTables,
    [$cleanSql],
    [$deleteSql],
    $abortUnique,
    'wp_options_rowvalue_abort_clean_batch',
);

$abortCases = [
    'parser conflict action abort' => [static fn (): mixed => $parsedAbort()['conflict_action'], 'abort'],
    'parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedAbort()['assignments']), ['blog_id', 'option_name', 'status', 'option_value']],
    'parser returning duplicate expression retained' => [static fn (): mixed => $parsedAbort()['returning'], "option_id, blog_id, option_name, status, option_value, (blog_id, option_name) = (1, 'siteurl') AS duplicate_key"],
    'parser order by option id' => [static fn (): mixed => $parsedAbort()['order_by'][0]['column'], 'option_id'],
    'stage delete selected transient id' => [static fn (): mixed => $afterStageDelete()['plan']->selectedIds, [3]],
    'stage delete leaves staged row seven' => [static fn (): mixed => array_column($afterStageDelete()['tables']['wp_options'], 'status', 'option_id')[7], 'staged'],
    'stage delete leaves staged row eight' => [static fn (): mixed => array_column($afterStageDelete()['tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'stage delete removes feed row' => [static fn (): mixed => array_intersect([3], array_column($afterStageDelete()['tables']['wp_options'], 'option_id')), []],
    'abort direct throws unique constraint' => [$abortOnly, InvalidArgumentException::class],

    'plan status statement aborted preserved' => [static fn (): mixed => $abortPlan()['status'], 'statement-aborted-savepoint-preserved-retried-current-source'],
    'plan savepoint name' => [static fn (): mixed => $abortPlan()['savepoint'], 'wp_options_rowvalue_abort_batch'],
    'plan statement aborted' => [static fn (): mixed => $abortPlan()['statement_aborted'], true],
    'plan transaction not rolled back' => [static fn (): mixed => $abortPlan()['transaction_rolled_back'], false],
    'plan did not rollback to savepoint' => [static fn (): mixed => $abortPlan()['rolled_back_to_savepoint'], false],
    'plan savepoint preserved after abort' => [static fn (): mixed => $abortPlan()['savepoint_preserved_after_abort'], true],
    'plan released after retry' => [static fn (): mixed => $abortPlan()['released_after_retry'], true],
    'plan abort ordinal third statement' => [static fn (): mixed => $abortPlan()['abort_statement_ordinal'], 2],
    'plan abort reason names abort action' => [static fn (): mixed => str_contains($abortPlan()['abort_reason'], 'using OR ABORT'), true],
    'plan attempt actions before abort' => [static fn (): mixed => array_column($abortPlan()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan attempt phases before abort' => [static fn (): mixed => array_column($abortPlan()['attempt_statements'], 'phase'), ['before-abort', 'before-abort']],
    'plan attempt selected ids' => [static fn (): mixed => array_column($abortPlan()['attempt_statements'], 'selected_ids'), [[7, 8], [3]]],
    'plan attempt mutation ids' => [static fn (): mixed => array_column($abortPlan()['attempt_statements'], 'mutation_ids'), [[7, 8], [3]]],
    'plan yielded before abort streams' => [static fn (): mixed => count($abortPlan()['yielded_before_abort']), 2],
    'plan yielded before abort row count' => [static fn (): mixed => $abortPlan()['yielded_before_abort_count'], 3],
    'plan first yielded ids' => [static fn (): mixed => array_column($abortPlan()['yielded_before_abort'][0]['rows'], 'option_id'), [7, 8]],
    'plan delete yielded feed id' => [static fn (): mixed => array_column($abortPlan()['yielded_before_abort'][1]['rows'], 'option_id'), [3]],
    'plan aborted statement yields no returning rows' => [static fn (): mixed => $abortPlan()['aborted_statement_returning'], []],
    'plan aborted statement returning count zero' => [static fn (): mixed => $abortPlan()['aborted_statement_returning_count'], 0],
    'plan attempted current source preserves stage row seven' => [static fn (): mixed => array_column($abortPlan()['attempted_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'staged'],
    'plan attempted current source preserves stage row eight' => [static fn (): mixed => array_column($abortPlan()['attempted_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'plan abort current source equals attempted current source' => [static fn (): mixed => $abortPlan()['abort_current_source_tables'], $abortPlan()['attempted_current_source_tables']],
    'plan retry base equals attempted current source' => [static fn (): mixed => $abortPlan()['retry_base_current_source_tables'], $abortPlan()['attempted_current_source_tables']],
    'plan retry actions' => [static fn (): mixed => array_column($abortPlan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry phases' => [static fn (): mixed => array_column($abortPlan()['retry_statements'], 'phase'), ['after-abort', 'after-abort']],
    'plan retry update selected ids' => [static fn (): mixed => $abortPlan()['retry_statements'][0]['selected_ids'], [7, 9]],
    'plan retry update source sees staged row seven' => [static fn (): mixed => array_column($abortPlan()['retry_statements'][0]['source_rows'], 'status'), ['staged', 'staged']],
    'plan retry delete selected rewrite row' => [static fn (): mixed => $abortPlan()['retry_statements'][1]['selected_ids'], [8]],
    'plan retry delete source sees staged row eight' => [static fn (): mixed => array_column($abortPlan()['retry_statements'][1]['source_rows'], 'status'), ['staged']],
    'plan yielded retry count' => [static fn (): mixed => $abortPlan()['yielded_returning_count'], 3],
    'plan retry update yielded ids' => [static fn (): mixed => array_column($abortPlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan retry cleanup yielded id' => [static fn (): mixed => array_column($abortPlan()['yielded_returning'][1]['rows'], 'option_id'), [8]],
    'plan changes before abort' => [static fn (): mixed => $abortPlan()['changes_before_abort'], 3],
    'plan changes after retry' => [static fn (): mixed => $abortPlan()['changes_after_retry'], 3],
    'plan total changes after release' => [static fn (): mixed => $abortPlan()['total_changes_after_release'], 6],
    'plan final row seven retried from staged value' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:stage:retry'],
    'plan final row seven status retried' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retried'],
    'plan final row nine retried' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:retry'],
    'plan final deleted rows are absent' => [static fn (): mixed => array_intersect([3, 8], array_column($abortPlan()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan final siteurl unique row unchanged' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test'],
    'plan next source equals current source' => [static fn (): mixed => $abortPlan()['next_source_tables'], $abortPlan()['current_source_tables']],
    'plan savepoint image original row seven value' => [static fn (): mixed => array_column($abortPlan()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan savepoint image original row eight status' => [static fn (): mixed => array_column($abortPlan()['savepoint_image_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan changed table wp options' => [static fn (): mixed => $abortPlan()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after deletes' => [static fn (): mixed => $abortPlan()['row_counts']['wp_options'], 7],
    'plan dependency abort statement only' => [static fn (): mixed => in_array('sqlite-update-or-abort-rowvalue-conflict-rolls-back-current-statement-only', $abortPlan()['dependencies'], true), true],
    'plan dependency savepoint preserved' => [static fn (): mixed => in_array('sqlite-abort-conflict-preserves-savepoint-and-prior-returning-streams', $abortPlan()['dependencies'], true), true],
    'plan dependency retry current source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-retry-continues-from-abort-current-source-abort-retry', $abortPlan()['dependencies'], true), true],

    'clean plan custom savepoint' => [static fn (): mixed => $cleanAbortPlan()['savepoint'], 'wp_options_rowvalue_abort_clean_batch'],
    'clean plan no statement abort' => [static fn (): mixed => $cleanAbortPlan()['statement_aborted'], false],
    'clean plan no abort ordinal' => [static fn (): mixed => $cleanAbortPlan()['abort_statement_ordinal'], null],
    'clean plan yielded before abort ids' => [static fn (): mixed => array_column($cleanAbortPlan()['yielded_before_abort'][0]['rows'], 'option_id'), [7, 8]],
    'clean plan retry delete id' => [static fn (): mixed => array_column($cleanAbortPlan()['yielded_returning'][0]['rows'], 'option_id'), [3]],
    'clean plan changes before abort two' => [static fn (): mixed => $cleanAbortPlan()['changes_before_abort'], 2],
    'clean plan changes after retry one' => [static fn (): mixed => $cleanAbortPlan()['changes_after_retry'], 1],
    'clean plan final row seven clean' => [static fn (): mixed => array_column($cleanAbortPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'clean'],

    'malformed empty attempt statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($abortTables, [], [$retrySql], $abortUnique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($abortTables, [$stageSql], [], $abortUnique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($abortTables, [$stageSql], [$retrySql], []), InvalidArgumentException::class],
    'malformed non abort conflict rethrows' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($abortTables, [$stageSql, str_replace('OR ABORT', 'OR ROLLBACK', $abortSql)], [$retrySql], $abortUnique), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(['wp_options' => ['bad']], [$stageSql], [$retrySql], $abortUnique), InvalidArgumentException::class],
];

$tests = [];
foreach ($abortCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source abort-retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
