<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows220 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables220 = ['wp_options' => $rows220];
$unique220 = [['blog_id', 'option_name']];

$preUpdate220 = "UPDATE wp_options SET (status, option_value, bytes) = ('pre220', option_value || ':pre220', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preDelete220 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN ((3, 'plugin_batch')) AS plugin_deleted ORDER BY option_id";
$abortUpdate220 = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'abort220', option_value || ':abort220', bytes + 9) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (1, 'siteurl') AS siteurl_conflict ORDER BY option_id";
$retryUpdate220 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry220', option_value || ':retry220', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete220 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$preUpdateResult220 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdate220, $tables220, 'option_id', $unique220);
$preDeleteResult220 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDelete220, $preUpdateResult220()['tables'], 'option_id', $unique220);
$abortProbe220 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($abortUpdate220, $preDeleteResult220()['tables'], 'option_id', [], true);
$retryUpdateResult220 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate220, $preDeleteResult220()['tables'], 'option_id', $unique220);
$retryDeleteResult220 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete220, $retryUpdateResult220()['tables'], 'option_id', $unique220);
$plan220 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry(
    $tables220,
    [$preUpdate220, $preDelete220],
    $abortUpdate220,
    [$retryUpdate220, $retryDelete220],
    $unique220,
);
$customPlan220 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry(
    $tables220,
    [$preUpdate220],
    $abortUpdate220,
    [$retryUpdate220],
    $unique220,
    'wp_custom_abort220',
);

$cases220 = [
    'parser abort conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($abortUpdate220)['conflict_action'], 'abort'],
    'parser abort row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($abortUpdate220)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser abort where row value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($abortUpdate220)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser abort returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($abortUpdate220)['returning'], 'siteurl_conflict'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate220)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete220)['returning'], 'option_id, blog_id, option_name, status'],
    'direct pre update selected ids' => [static fn (): mixed => $preUpdateResult220()['plan']->selectedIds, [7, 8]],
    'direct pre update returning ids' => [static fn (): mixed => array_column($preUpdateResult220()['returning'], 'option_id'), [7, 8]],
    'direct pre update row seven value' => [static fn (): mixed => array_column($preUpdateResult220()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:pre220'],
    'direct pre update row eight status' => [static fn (): mixed => array_column($preUpdateResult220()['tables']['wp_options'], 'status', 'option_id')[8], 'pre220'],
    'direct pre delete selected ids' => [static fn (): mixed => $preDeleteResult220()['plan']->selectedIds, [3, 9]],
    'direct pre delete returning ids' => [static fn (): mixed => array_column($preDeleteResult220()['returning'], 'option_id'), [3, 9]],
    'direct pre delete plugin flag' => [static fn (): mixed => array_column($preDeleteResult220()['returning'], 'plugin_deleted'), [0, 1]],
    'direct pre delete removes plugin' => [static fn (): mixed => in_array(9, array_column($preDeleteResult220()['tables']['wp_options'], 'option_id'), true), false],
    'direct pre delete keeps timeout' => [static fn (): mixed => in_array(4, array_column($preDeleteResult220()['tables']['wp_options'], 'option_id'), true), true],
    'direct abort statement throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($abortUpdate220, $preDeleteResult220()['tables'], 'option_id', $unique220), InvalidArgumentException::class],
    'direct abort probe selected ids' => [static fn (): mixed => $abortProbe220()['plan']->selectedIds, [7, 8]],
    'direct abort probe returning ids' => [static fn (): mixed => array_column($abortProbe220()['returning'], 'option_id'), [7, 8]],
    'direct abort probe conflict flags' => [static fn (): mixed => array_column($abortProbe220()['returning'], 'siteurl_conflict'), [1, 1]],
    'direct abort probe status' => [static fn (): mixed => array_column($abortProbe220()['returning'], 'status'), ['abort220', 'abort220']],
    'direct retry update selected from pre abort source' => [static fn (): mixed => $retryUpdateResult220()['plan']->selectedIds, [8, 7]],
    'direct retry update returning order' => [static fn (): mixed => array_column($retryUpdateResult220()['returning'], 'option_id'), [7, 8]],
    'direct retry row seven preserves pre prefix' => [static fn (): mixed => $retryUpdateResult220()['returning'][0]['option_value'], 'theme:pre220:retry220'],
    'direct retry row eight preserves pre prefix' => [static fn (): mixed => $retryUpdateResult220()['returning'][1]['option_value'], 'rules:pre220:retry220'],
    'direct retry delete selected timeout only' => [static fn (): mixed => $retryDeleteResult220()['plan']->selectedIds, [4]],
    'direct retry delete does not resurrect plugin' => [static fn (): mixed => in_array(9, array_column($retryDeleteResult220()['tables']['wp_options'], 'option_id'), true), false],
    'direct retry delete removes timeout' => [static fn (): mixed => in_array(4, array_column($retryDeleteResult220()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan220()['status'], 'rowvalue-update-delete-returning-or-abort-savepoint-current-source-next220'],
    'plan savepoint' => [static fn (): mixed => $plan220()['savepoint'], 'wp_options_rowvalue_abort_next220'],
    'plan savepoint preserved' => [static fn (): mixed => $plan220()['savepoint_preserved_after_statement_abort'], true],
    'plan pre changes preserved flag' => [static fn (): mixed => $plan220()['pre_abort_changes_preserved'], true],
    'plan abort rolled back flag' => [static fn (): mixed => $plan220()['abort_statement_changes_rolled_back'], true],
    'plan abort returning suppressed flag' => [static fn (): mixed => $plan220()['abort_statement_returning_suppressed'], true],
    'plan retry reads pre abort flag' => [static fn (): mixed => $plan220()['retry_reads_pre_abort_current_source'], true],
    'plan retry released flag' => [static fn (): mixed => $plan220()['savepoint_released_after_retry'], true],
    'plan savepoint image original row seven' => [static fn (): mixed => array_column($plan220()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan pre abort row seven changed' => [static fn (): mixed => array_column($plan220()['pre_abort_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'pre220'],
    'plan pre abort row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan220()['pre_abort_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan abort current equals pre abort' => [static fn (): mixed => $plan220()['abort_current_source_tables'], $plan220()['pre_abort_current_source_tables']],
    'plan abort current row seven not abort' => [static fn (): mixed => array_column($plan220()['abort_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'pre220'],
    'plan abort current row eight not abort' => [static fn (): mixed => array_column($plan220()['abort_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'pre220'],
    'plan abort does not restore deleted plugin' => [static fn (): mixed => in_array(9, array_column($plan220()['abort_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current row seven retry' => [static fn (): mixed => array_column($plan220()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry220'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan220()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry220'],
    'plan current row seven value' => [static fn (): mixed => array_column($plan220()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:pre220:retry220'],
    'plan current row eight value' => [static fn (): mixed => array_column($plan220()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:pre220:retry220'],
    'plan current timeout deleted after retry' => [static fn (): mixed => in_array(4, array_column($plan220()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current plugin still deleted' => [static fn (): mixed => in_array(9, array_column($plan220()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan220()['next_source_tables'], $plan220()['current_source_tables']],
    'plan before phases' => [static fn (): mixed => array_column($plan220()['before_abort_statements'], 'phase'), ['before-or-abort-next220', 'before-or-abort-next220']],
    'plan abort phase' => [static fn (): mixed => $plan220()['abort_statement']['phase'], 'or-abort-next220'],
    'plan abort aborted flag' => [static fn (): mixed => $plan220()['abort_statement']['aborted'], true],
    'plan abort statement rollback flag' => [static fn (): mixed => $plan220()['abort_statement']['rolled_back_statement_only'], true],
    'plan abort savepoint open flag' => [static fn (): mixed => $plan220()['abort_statement']['savepoint_remains_open'], true],
    'plan abort selected ids' => [static fn (): mixed => $plan220()['abort_statement']['selected_ids'], [7, 8]],
    'plan abort mutation ids' => [static fn (): mixed => $plan220()['abort_statement']['mutation_ids'], [7, 8]],
    'plan abort conflict action' => [static fn (): mixed => $plan220()['abort_statement']['conflict_action'], 'abort'],
    'plan abort error names unique columns' => [static fn (): mixed => str_contains($plan220()['abort_statement']['error'], 'blog_id,option_name'), true],
    'plan abort source rows include pre changes' => [static fn (): mixed => array_column($plan220()['abort_statement']['source_rows'], 'status'), ['pre220', 'pre220']],
    'plan suppressed ids' => [static fn (): mixed => array_column($plan220()['suppressed_by_statement_abort_returning'], 'option_id'), [7, 8]],
    'plan suppressed statuses' => [static fn (): mixed => array_column($plan220()['suppressed_by_statement_abort_returning'], 'status'), ['abort220', 'abort220']],
    'plan suppressed conflict flags' => [static fn (): mixed => array_column($plan220()['suppressed_by_statement_abort_returning'], 'siteurl_conflict'), [1, 1]],
    'plan retry phases' => [static fn (): mixed => array_column($plan220()['retry_statements'], 'phase'), ['retry-after-statement-abort-next220', 'retry-after-statement-abort-next220']],
    'plan retry update source rows pre abort' => [static fn (): mixed => array_column($plan220()['retry_statements'][0]['source_rows'], 'status'), ['pre220', 'pre220']],
    'plan retry delete source rows preserved timeout' => [static fn (): mixed => array_column($plan220()['retry_statements'][1]['source_rows'], 'option_id'), [4]],
    'plan pre yielded count' => [static fn (): mixed => $plan220()['pre_abort_yielded_count'], 4],
    'plan pre changes count' => [static fn (): mixed => $plan220()['pre_abort_changes_count'], 4],
    'plan suppressed count' => [static fn (): mixed => $plan220()['suppressed_by_abort_count'], 2],
    'plan retry yielded count' => [static fn (): mixed => $plan220()['retry_yielded_count'], 3],
    'plan retry changes count' => [static fn (): mixed => $plan220()['retry_changes_count'], 3],
    'plan changed tables' => [static fn (): mixed => $plan220()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan220()['row_counts']['wp_options'], 7],
    'plan dependency abort suppresses' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-abort-suppresses-failing-returning-next220', $plan220()['dependencies'], true), true],
    'plan dependency current source' => [static fn (): mixed => in_array('sqlite-rowvalue-or-abort-preserves-savepoint-current-source-next220', $plan220()['dependencies'], true), true],
    'plan dependency retry' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-retry-after-statement-abort-next220', $plan220()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan220()['dependency_closure_next220'], 'no new support component needed'), true],
    'plan non overlap note' => [static fn (): mixed => str_contains($plan220()['non_overlap_next220'], 'avoids accepted next217 transaction OR ROLLBACK'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan220()['savepoint'], 'wp_custom_abort220'],
    'custom pre count' => [static fn (): mixed => $customPlan220()['pre_abort_yielded_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan220()['retry_yielded_count'], 2],
    'malformed empty pre rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry($tables220, [], $abortUpdate220, [$retryUpdate220], $unique220), InvalidArgumentException::class],
    'malformed empty abort rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry($tables220, [$preUpdate220], '', [$retryUpdate220], $unique220), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry($tables220, [$preUpdate220], $abortUpdate220, [], $unique220), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry($tables220, [$preUpdate220], $abortUpdate220, [$retryUpdate220], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry($tables220, [$preUpdate220], $abortUpdate220, [$retryUpdate220], $unique220, 'bad-name'), InvalidArgumentException::class],
    'malformed non abort action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry($tables220, [$preUpdate220], str_replace('OR ABORT', 'OR FAIL', $abortUpdate220), [$retryUpdate220], $unique220), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeAbortConflictRetry(['wp_options' => ['bad']], [$preUpdate220], $abortUpdate220, [$retryUpdate220], $unique220), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases220 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next220 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
