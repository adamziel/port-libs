<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows192 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables192 = ['wp_options' => $rows192];
$unique192 = [['blog_id', 'option_name']];

$outerUpdate192 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer192', option_value || ':outer192', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerDelete192 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$innerUpdate192 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner192', option_value || ':inner192', bytes + 3) WHERE option_id = 9 RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner192', 'orphaned_cache') AS rowvalue_match ORDER BY option_id";
$abortUpdate192 = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'abort192', option_value || ':abort192', bytes + 11) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdate192 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry192', option_value || ':retry192', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete192 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$outerResult192 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate192, $tables192, 'option_id', $unique192);
$innerDeleteResult192 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete192, $outerResult192()['tables'], 'option_id', $unique192);
$innerUpdateResult192 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdate192, $innerDeleteResult192()['tables'], 'option_id', $unique192);
$retryUpdateResult192 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate192, $innerUpdateResult192()['tables'], 'option_id', $unique192);
$retryDeleteResult192 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete192, $retryUpdateResult192()['tables'], 'option_id', $unique192);
$plan192 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint(
    $tables192,
    [$outerUpdate192],
    [$innerDelete192, $innerUpdate192],
    $abortUpdate192,
    [$retryUpdate192, $retryDelete192],
    $unique192,
);
$customPlan192 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint(
    $tables192,
    [$outerUpdate192],
    [$innerUpdate192],
    $abortUpdate192,
    [$retryUpdate192],
    $unique192,
    'app_outer_custom192',
    'app_inner_custom192',
);

$cases192 = [
    'parser abort conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($abortUpdate192)['conflict_action'], 'abort'],
    'parser abort row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($abortUpdate192)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser abort row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($abortUpdate192)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'))"],
    'parser retry delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete192)['returning'], 'option_id, blog_id, option_name, status'],
    'direct abort statement throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($abortUpdate192, $innerUpdateResult192()['tables'], 'option_id', $unique192), InvalidArgumentException::class],
    'outer selected ids' => [static fn (): mixed => $outerResult192()['plan']->selectedIds, [7, 8]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult192()['returning'], 'option_id'), [7, 8]],
    'outer row seven value' => [static fn (): mixed => array_column($outerResult192()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer192'],
    'inner delete selected transient ids' => [static fn (): mixed => $innerDeleteResult192()['plan']->selectedIds, [3, 4]],
    'inner delete removes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($innerDeleteResult192()['tables']['wp_options'], 'option_id')), []],
    'inner update selected orphaned cache' => [static fn (): mixed => $innerUpdateResult192()['plan']->selectedIds, [9]],
    'inner update returning rowvalue predicate' => [static fn (): mixed => $innerUpdateResult192()['returning'][0]['rowvalue_match'], 1],
    'inner update current row nine value' => [static fn (): mixed => array_column($innerUpdateResult192()['tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:inner192'],
    'retry update selected current source rows' => [static fn (): mixed => $retryUpdateResult192()['plan']->selectedIds, [7, 9]],
    'retry update row seven keeps outer prefix' => [static fn (): mixed => $retryUpdateResult192()['returning'][0]['option_value'], 'theme:outer192:retry192'],
    'retry update row nine keeps inner prefix' => [static fn (): mixed => $retryUpdateResult192()['returning'][1]['option_value'], 'cache:inner192:retry192'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult192()['plan']->selectedIds, [2, 10]],
    'retry delete removes home and site four' => [static fn (): mixed => array_intersect([2, 10], array_column($retryDeleteResult192()['tables']['wp_options'], 'option_id')), []],

    'plan status' => [static fn (): mixed => $plan192()['status'], 'rowvalue-abort-statement-current-source-retry-next192'],
    'plan savepoint names' => [static fn (): mixed => [$plan192()['outer_savepoint'], $plan192()['inner_savepoint']], ['app_settings_rowvalue_abort_outer_next192', 'app_settings_rowvalue_abort_inner_next192']],
    'plan abort rolled back statement' => [static fn (): mixed => $plan192()['inner_abort_statement_rolled_back'], true],
    'plan savepoints preserved' => [static fn (): mixed => [$plan192()['outer_savepoint_preserved_after_abort'], $plan192()['inner_savepoint_preserved_after_abort']], [true, true]],
    'plan inner pre abort changes preserved' => [static fn (): mixed => $plan192()['inner_pre_abort_changes_preserved'], true],
    'plan savepoints released' => [static fn (): mixed => [$plan192()['inner_released_after_retry'], $plan192()['outer_released_after_inner_retry']], [true, true]],
    'plan outer image original' => [static fn (): mixed => $plan192()['outer_savepoint_image_tables'], $tables192],
    'plan outer current row seven' => [static fn (): mixed => array_column($plan192()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer192'],
    'plan inner image equals outer current' => [static fn (): mixed => $plan192()['inner_savepoint_image_tables'], $plan192()['outer_current_source_tables']],
    'plan inner pre abort deletes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan192()['inner_pre_abort_current_source_tables']['wp_options'], 'option_id')), []],
    'plan inner pre abort row nine inner' => [static fn (): mixed => array_column($plan192()['inner_pre_abort_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'inner192'],
    'plan abort rollback equals pre abort current' => [static fn (): mixed => $plan192()['abort_statement_rollback_current_source_tables'], $plan192()['inner_pre_abort_current_source_tables']],
    'plan abort did not rewrite row seven key' => [static fn (): mixed => array_column($plan192()['abort_statement_rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan abort did not restore deleted transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan192()['abort_statement_rollback_current_source_tables']['wp_options'], 'option_id')), []],
    'plan current final ids' => [static fn (): mixed => array_column($plan192()['current_source_tables']['wp_options'], 'option_id'), [1, 5, 6, 7, 8, 9]],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan192()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer192:retry192'],
    'plan final row nine retry after inner' => [static fn (): mixed => array_column($plan192()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:inner192:retry192'],
    'plan final row eight outer preserved' => [static fn (): mixed => array_column($plan192()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'outer192'],
    'plan next source equals current' => [static fn (): mixed => $plan192()['next_source_tables'], $plan192()['current_source_tables']],
    'plan outer phases' => [static fn (): mixed => array_column($plan192()['outer_statements'], 'phase'), ['outer-before-abort-inner']],
    'plan inner phases' => [static fn (): mixed => array_column($plan192()['inner_pre_abort_statements'], 'phase'), ['inner-before-abort', 'inner-before-abort']],
    'plan abort phase' => [static fn (): mixed => $plan192()['abort_statement']['phase'], 'inner-abort-statement'],
    'plan abort flag' => [static fn (): mixed => $plan192()['abort_statement']['aborted'], true],
    'plan abort rollback flag' => [static fn (): mixed => $plan192()['abort_statement']['rolled_back_to_statement_start'], true],
    'plan abort selected ids' => [static fn (): mixed => $plan192()['abort_statement']['selected_ids'], [7, 9]],
    'plan abort mutation ids' => [static fn (): mixed => $plan192()['abort_statement']['mutation_ids'], [7, 9]],
    'plan abort conflict action' => [static fn (): mixed => $plan192()['abort_statement']['conflict_action'], 'abort'],
    'plan abort error names unique columns' => [static fn (): mixed => str_contains($plan192()['abort_statement']['error'], 'blog_id,option_name'), true],
    'plan abort probe returning suppressed ids' => [static fn (): mixed => array_column($plan192()['abort_statement']['returning_rows'], 'option_id'), [7, 9]],
    'plan suppressed by abort ids' => [static fn (): mixed => array_column($plan192()['suppressed_by_abort_returning'], 'option_id'), [7, 9]],
    'plan retry phases' => [static fn (): mixed => array_column($plan192()['retry_statements'], 'phase'), ['retry-after-abort-statement', 'retry-after-abort-statement']],
    'plan retry source rows include preserved inner' => [static fn (): mixed => array_column($plan192()['retry_statements'][0]['source_rows'], 'status'), ['outer192', 'inner192']],
    'plan retry delete source rows' => [static fn (): mixed => array_column($plan192()['retry_statements'][1]['source_rows'], 'option_id'), [2, 10]],
    'plan outer returning count' => [static fn (): mixed => $plan192()['outer_yielded_returning_count'], 2],
    'plan inner pre abort returning count' => [static fn (): mixed => $plan192()['inner_pre_abort_returning_count'], 3],
    'plan suppressed by abort count' => [static fn (): mixed => $plan192()['suppressed_by_abort_count'], 2],
    'plan retry count' => [static fn (): mixed => $plan192()['yielded_after_retry_count'], 4],
    'plan outer changes preserved' => [static fn (): mixed => $plan192()['outer_changes_preserved'], 2],
    'plan inner changes preserved before abort' => [static fn (): mixed => $plan192()['inner_changes_preserved_before_abort'], 3],
    'plan retry changes after abort' => [static fn (): mixed => $plan192()['retry_changes_after_abort'], 4],
    'plan changed tables' => [static fn (): mixed => $plan192()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan192()['row_counts']['wp_options'], 6],
    'plan dependency abort statement' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-abort-statement-rollback-next192', $plan192()['dependencies'], true), true],
    'plan dependency preserves prior changes' => [static fn (): mixed => in_array('sqlite-rowvalue-abort-preserves-prior-savepoint-current-source-next192', $plan192()['dependencies'], true), true],
    'plan dependency retry delete' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-retry-after-abort-next192', $plan192()['dependencies'], true), true],
    'custom plan savepoints' => [static fn (): mixed => [$customPlan192()['outer_savepoint'], $customPlan192()['inner_savepoint']], ['app_outer_custom192', 'app_inner_custom192']],
    'custom plan inner pre abort count' => [static fn (): mixed => $customPlan192()['inner_pre_abort_returning_count'], 1],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint($tables192, [], [$innerUpdate192], $abortUpdate192, [$retryUpdate192], $unique192), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint($tables192, [$outerUpdate192], [], $abortUpdate192, [$retryUpdate192], $unique192), InvalidArgumentException::class],
    'malformed empty abort rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint($tables192, [$outerUpdate192], [$innerUpdate192], '', [$retryUpdate192], $unique192), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint($tables192, [$outerUpdate192], [$innerUpdate192], $abortUpdate192, [], $unique192), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint($tables192, [$outerUpdate192], [$innerUpdate192], $abortUpdate192, [$retryUpdate192], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint($tables192, [$outerUpdate192], [$innerUpdate192], $abortUpdate192, [$retryUpdate192], $unique192, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedAbortRollbackRetrySavepoint(['wp_options' => ['bad']], [$outerUpdate192], [$innerUpdate192], $abortUpdate192, [$retryUpdate192], $unique192), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases192 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next192 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
