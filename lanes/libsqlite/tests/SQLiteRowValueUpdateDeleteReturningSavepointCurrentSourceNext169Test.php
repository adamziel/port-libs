<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows169 = [
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

$tables169 = ['wp_options' => $rows169];
$unique169 = [['blog_id', 'option_name']];

$stageSql169 = "UPDATE wp_options SET (status, option_value, bytes) = ('staged', option_value || ':stage', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$deleteSql169 = "DELETE FROM wp_options WHERE (blog_id, option_name) = (1, '_transient_feed') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$abortSql169 = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', 'duplicate', option_value || ':bad') WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value, (blog_id, option_name) = (1, 'siteurl') AS duplicate_key ORDER BY option_id";
$retrySql169 = "UPDATE wp_options SET (status, option_value, bytes) = ('retried', option_value || ':retry', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$cleanupSql169 = "DELETE FROM wp_options WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$cleanSql169 = "UPDATE wp_options SET (status, option_value, bytes) = ('clean', option_value || ':clean', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";

$parsedAbort169 = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($abortSql169);
$afterStageDelete169 = static function () use ($stageSql169, $deleteSql169, $tables169, $unique169): array {
    $staged = SQLiteUpdateDeleteReturningSql::execute($stageSql169, $tables169, 'option_id', $unique169);

    return SQLiteUpdateDeleteReturningSql::execute($deleteSql169, $staged['tables'], 'option_id', $unique169);
};
$abortOnly169 = static function () use ($abortSql169, $afterStageDelete169, $unique169): mixed {
    return SQLiteUpdateDeleteReturningSql::execute($abortSql169, $afterStageDelete169()['tables'], 'option_id', $unique169);
};
$plan169 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(
    $tables169,
    [$stageSql169, $deleteSql169, $abortSql169],
    [$retrySql169, $cleanupSql169],
    $unique169,
);
$cleanPlan169 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(
    $tables169,
    [$cleanSql169],
    [$deleteSql169],
    $unique169,
    'wp_options_rowvalue_abort_clean_batch',
);

$cases169 = [
    'parser conflict action abort' => [static fn (): mixed => $parsedAbort169()['conflict_action'], 'abort'],
    'parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedAbort169()['assignments']), ['blog_id', 'option_name', 'status', 'option_value']],
    'parser returning duplicate expression retained' => [static fn (): mixed => $parsedAbort169()['returning'], "option_id, blog_id, option_name, status, option_value, (blog_id, option_name) = (1, 'siteurl') AS duplicate_key"],
    'parser order by option id' => [static fn (): mixed => $parsedAbort169()['order_by'][0]['column'], 'option_id'],
    'stage delete selected transient id' => [static fn (): mixed => $afterStageDelete169()['plan']->selectedIds, [3]],
    'stage delete leaves staged row seven' => [static fn (): mixed => array_column($afterStageDelete169()['tables']['wp_options'], 'status', 'option_id')[7], 'staged'],
    'stage delete leaves staged row eight' => [static fn (): mixed => array_column($afterStageDelete169()['tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'stage delete removes feed row' => [static fn (): mixed => array_intersect([3], array_column($afterStageDelete169()['tables']['wp_options'], 'option_id')), []],
    'abort direct throws unique constraint' => [$abortOnly169, InvalidArgumentException::class],

    'plan status statement aborted preserved' => [static fn (): mixed => $plan169()['status'], 'statement-aborted-savepoint-preserved-retried-current-source-next169'],
    'plan savepoint name' => [static fn (): mixed => $plan169()['savepoint'], 'wp_options_rowvalue_abort_batch'],
    'plan statement aborted' => [static fn (): mixed => $plan169()['statement_aborted'], true],
    'plan transaction not rolled back' => [static fn (): mixed => $plan169()['transaction_rolled_back'], false],
    'plan did not rollback to savepoint' => [static fn (): mixed => $plan169()['rolled_back_to_savepoint'], false],
    'plan savepoint preserved after abort' => [static fn (): mixed => $plan169()['savepoint_preserved_after_abort'], true],
    'plan released after retry' => [static fn (): mixed => $plan169()['released_after_retry'], true],
    'plan abort ordinal third statement' => [static fn (): mixed => $plan169()['abort_statement_ordinal'], 2],
    'plan abort reason names abort action' => [static fn (): mixed => str_contains($plan169()['abort_reason'], 'using OR ABORT'), true],
    'plan attempt actions before abort' => [static fn (): mixed => array_column($plan169()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan attempt phases before abort' => [static fn (): mixed => array_column($plan169()['attempt_statements'], 'phase'), ['before-abort', 'before-abort']],
    'plan attempt selected ids' => [static fn (): mixed => array_column($plan169()['attempt_statements'], 'selected_ids'), [[7, 8], [3]]],
    'plan attempt mutation ids' => [static fn (): mixed => array_column($plan169()['attempt_statements'], 'mutation_ids'), [[7, 8], [3]]],
    'plan yielded before abort streams' => [static fn (): mixed => count($plan169()['yielded_before_abort']), 2],
    'plan yielded before abort row count' => [static fn (): mixed => $plan169()['yielded_before_abort_count'], 3],
    'plan first yielded ids' => [static fn (): mixed => array_column($plan169()['yielded_before_abort'][0]['rows'], 'option_id'), [7, 8]],
    'plan delete yielded feed id' => [static fn (): mixed => array_column($plan169()['yielded_before_abort'][1]['rows'], 'option_id'), [3]],
    'plan aborted statement yields no returning rows' => [static fn (): mixed => $plan169()['aborted_statement_returning'], []],
    'plan aborted statement returning count zero' => [static fn (): mixed => $plan169()['aborted_statement_returning_count'], 0],
    'plan attempted current source preserves stage row seven' => [static fn (): mixed => array_column($plan169()['attempted_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'staged'],
    'plan attempted current source preserves stage row eight' => [static fn (): mixed => array_column($plan169()['attempted_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'plan abort current source equals attempted current source' => [static fn (): mixed => $plan169()['abort_current_source_tables'], $plan169()['attempted_current_source_tables']],
    'plan retry base equals attempted current source' => [static fn (): mixed => $plan169()['retry_base_current_source_tables'], $plan169()['attempted_current_source_tables']],
    'plan retry actions' => [static fn (): mixed => array_column($plan169()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry phases' => [static fn (): mixed => array_column($plan169()['retry_statements'], 'phase'), ['after-abort', 'after-abort']],
    'plan retry update selected ids' => [static fn (): mixed => $plan169()['retry_statements'][0]['selected_ids'], [7, 9]],
    'plan retry update source sees staged row seven' => [static fn (): mixed => array_column($plan169()['retry_statements'][0]['source_rows'], 'status'), ['staged', 'staged']],
    'plan retry delete selected rewrite row' => [static fn (): mixed => $plan169()['retry_statements'][1]['selected_ids'], [8]],
    'plan retry delete source sees staged row eight' => [static fn (): mixed => array_column($plan169()['retry_statements'][1]['source_rows'], 'status'), ['staged']],
    'plan yielded retry count' => [static fn (): mixed => $plan169()['yielded_returning_count'], 3],
    'plan retry update yielded ids' => [static fn (): mixed => array_column($plan169()['yielded_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan retry cleanup yielded id' => [static fn (): mixed => array_column($plan169()['yielded_returning'][1]['rows'], 'option_id'), [8]],
    'plan changes before abort' => [static fn (): mixed => $plan169()['changes_before_abort'], 3],
    'plan changes after retry' => [static fn (): mixed => $plan169()['changes_after_retry'], 3],
    'plan total changes after release' => [static fn (): mixed => $plan169()['total_changes_after_release'], 6],
    'plan final row seven retried from staged value' => [static fn (): mixed => array_column($plan169()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:stage:retry'],
    'plan final row seven status retried' => [static fn (): mixed => array_column($plan169()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retried'],
    'plan final row nine retried' => [static fn (): mixed => array_column($plan169()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:retry'],
    'plan final deleted rows are absent' => [static fn (): mixed => array_intersect([3, 8], array_column($plan169()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan final siteurl unique row unchanged' => [static fn (): mixed => array_column($plan169()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test'],
    'plan next source equals current source' => [static fn (): mixed => $plan169()['next_source_tables'], $plan169()['current_source_tables']],
    'plan savepoint image original row seven value' => [static fn (): mixed => array_column($plan169()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan savepoint image original row eight status' => [static fn (): mixed => array_column($plan169()['savepoint_image_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan changed table wp options' => [static fn (): mixed => $plan169()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after deletes' => [static fn (): mixed => $plan169()['row_counts']['wp_options'], 7],
    'plan dependency abort statement only' => [static fn (): mixed => in_array('sqlite-update-or-abort-rowvalue-conflict-rolls-back-current-statement-only', $plan169()['dependencies'], true), true],
    'plan dependency savepoint preserved' => [static fn (): mixed => in_array('sqlite-abort-conflict-preserves-savepoint-and-prior-returning-streams', $plan169()['dependencies'], true), true],
    'plan dependency retry current source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-retry-continues-from-abort-current-source-next169', $plan169()['dependencies'], true), true],

    'clean plan custom savepoint' => [static fn (): mixed => $cleanPlan169()['savepoint'], 'wp_options_rowvalue_abort_clean_batch'],
    'clean plan no statement abort' => [static fn (): mixed => $cleanPlan169()['statement_aborted'], false],
    'clean plan no abort ordinal' => [static fn (): mixed => $cleanPlan169()['abort_statement_ordinal'], null],
    'clean plan yielded before abort ids' => [static fn (): mixed => array_column($cleanPlan169()['yielded_before_abort'][0]['rows'], 'option_id'), [7, 8]],
    'clean plan retry delete id' => [static fn (): mixed => array_column($cleanPlan169()['yielded_returning'][0]['rows'], 'option_id'), [3]],
    'clean plan changes before abort two' => [static fn (): mixed => $cleanPlan169()['changes_before_abort'], 2],
    'clean plan changes after retry one' => [static fn (): mixed => $cleanPlan169()['changes_after_retry'], 1],
    'clean plan final row seven clean' => [static fn (): mixed => array_column($cleanPlan169()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'clean'],

    'malformed empty attempt statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($tables169, [], [$retrySql169], $unique169), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($tables169, [$stageSql169], [], $unique169), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($tables169, [$stageSql169], [$retrySql169], []), InvalidArgumentException::class],
    'malformed non abort conflict rethrows' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint($tables169, [$stageSql169, str_replace('OR ABORT', 'OR ROLLBACK', $abortSql169)], [$retrySql169], $unique169), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortRollbackRetrySavepoint(['wp_options' => ['bad']], [$stageSql169], [$retrySql169], $unique169), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases169 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next169 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
