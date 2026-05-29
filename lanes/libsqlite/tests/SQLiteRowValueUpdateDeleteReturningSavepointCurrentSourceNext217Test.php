<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows217 = [
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

$tables217 = ['wp_options' => $rows217];
$unique217 = [['blog_id', 'option_name']];

$preUpdate217 = "UPDATE wp_options SET (status, option_value, bytes) = ('pre217', option_value || ':pre217', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preDelete217 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (3, 'plugin_batch')) AS plugin_deleted ORDER BY option_id";
$rollbackUpdate217 = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'rollback217', option_value || ':rollback217', bytes + 9) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (1, 'siteurl') AS siteurl_conflict ORDER BY option_id";
$retryUpdate217 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry217', option_value || ':retry217', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete217 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$preUpdateResult217 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdate217, $tables217, 'option_id', $unique217);
$preDeleteResult217 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDelete217, $preUpdateResult217()['tables'], 'option_id', $unique217);
$rollbackProbe217 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($rollbackUpdate217, $preDeleteResult217()['tables'], 'option_id', [], true);
$retryUpdateResult217 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate217, $tables217, 'option_id', $unique217);
$retryDeleteResult217 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete217, $retryUpdateResult217()['tables'], 'option_id', $unique217);
$plan217 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict(
    $tables217,
    [$preUpdate217, $preDelete217],
    $rollbackUpdate217,
    [$retryUpdate217, $retryDelete217],
    $unique217,
);
$customPlan217 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict(
    $tables217,
    [$preUpdate217],
    $rollbackUpdate217,
    [$retryUpdate217],
    $unique217,
    'wp_tx_custom217',
    'wp_savepoint_custom217',
    'wp_retry_custom217',
);

$cases217 = [
    'parser rollback conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($rollbackUpdate217)['conflict_action'], 'rollback'],
    'parser rollback row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($rollbackUpdate217)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser rollback where row value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($rollbackUpdate217)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser rollback returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($rollbackUpdate217)['returning'], 'siteurl_conflict'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate217)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete217)['returning'], 'option_id, blog_id, option_name, status'],
    'direct pre update selected ids' => [static fn (): mixed => $preUpdateResult217()['plan']->selectedIds, [7, 8]],
    'direct pre update returning ids' => [static fn (): mixed => array_column($preUpdateResult217()['returning'], 'option_id'), [7, 8]],
    'direct pre update row seven value' => [static fn (): mixed => array_column($preUpdateResult217()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:pre217'],
    'direct pre update row eight status' => [static fn (): mixed => array_column($preUpdateResult217()['tables']['wp_options'], 'status', 'option_id')[8], 'pre217'],
    'direct pre delete selected ids' => [static fn (): mixed => $preDeleteResult217()['plan']->selectedIds, [3, 9]],
    'direct pre delete returning ids' => [static fn (): mixed => array_column($preDeleteResult217()['returning'], 'option_id'), [3, 9]],
    'direct pre delete plugin flag' => [static fn (): mixed => array_column($preDeleteResult217()['returning'], 'plugin_deleted'), [0, 1]],
    'direct pre delete removes plugin' => [static fn (): mixed => in_array(9, array_column($preDeleteResult217()['tables']['wp_options'], 'option_id'), true), false],
    'direct rollback statement throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($rollbackUpdate217, $preDeleteResult217()['tables'], 'option_id', $unique217), InvalidArgumentException::class],
    'direct rollback probe selected ids' => [static fn (): mixed => $rollbackProbe217()['plan']->selectedIds, [7, 8]],
    'direct rollback probe returning ids' => [static fn (): mixed => array_column($rollbackProbe217()['returning'], 'option_id'), [7, 8]],
    'direct rollback probe conflict flags' => [static fn (): mixed => array_column($rollbackProbe217()['returning'], 'siteurl_conflict'), [1, 1]],
    'direct retry update selected from transaction image' => [static fn (): mixed => $retryUpdateResult217()['plan']->selectedIds, [9, 8, 7]],
    'direct retry update returning order' => [static fn (): mixed => array_column($retryUpdateResult217()['returning'], 'option_id'), [7, 8, 9]],
    'direct retry row seven original prefix' => [static fn (): mixed => $retryUpdateResult217()['returning'][0]['option_value'], 'theme:retry217'],
    'direct retry row eight original prefix' => [static fn (): mixed => $retryUpdateResult217()['returning'][1]['option_value'], 'rules:retry217'],
    'direct retry row nine exists again' => [static fn (): mixed => $retryUpdateResult217()['returning'][2]['option_value'], 'plugin:retry217'],
    'direct retry delete selected transient ids' => [static fn (): mixed => $retryDeleteResult217()['plan']->selectedIds, [3, 4]],
    'direct retry delete removes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($retryDeleteResult217()['tables']['wp_options'], 'option_id')), []],

    'plan status' => [static fn (): mixed => $plan217()['status'], 'rowvalue-update-delete-returning-or-rollback-current-source-next217'],
    'plan transaction names' => [static fn (): mixed => [$plan217()['transaction'], $plan217()['savepoint'], $plan217()['retry_savepoint']], ['wp_options_rowvalue_transaction_next217', 'wp_options_rowvalue_rollback_next217', 'wp_options_rowvalue_retry_next217']],
    'plan rollback aborted transaction' => [static fn (): mixed => $plan217()['or_rollback_aborted_transaction'], true],
    'plan savepoint closed' => [static fn (): mixed => $plan217()['savepoint_closed_by_rollback'], true],
    'plan pre changes discarded' => [static fn (): mixed => $plan217()['pre_rollback_changes_discarded'], true],
    'plan returning suppressed' => [static fn (): mixed => $plan217()['rollback_statement_returning_suppressed'], true],
    'plan retry opens savepoint' => [static fn (): mixed => $plan217()['retry_opens_new_savepoint'], true],
    'plan retry reads image' => [static fn (): mixed => $plan217()['retry_reads_transaction_image'], true],
    'plan retry released' => [static fn (): mixed => $plan217()['retry_savepoint_released'], true],
    'plan transaction image original' => [static fn (): mixed => $plan217()['transaction_image_tables'], $tables217],
    'plan pre rollback row seven changed' => [static fn (): mixed => array_column($plan217()['pre_rollback_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'pre217'],
    'plan pre rollback row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan217()['pre_rollback_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores image' => [static fn (): mixed => $plan217()['rollback_to_transaction_current_source_tables'], $tables217],
    'plan rollback restores row seven status' => [static fn (): mixed => array_column($plan217()['rollback_to_transaction_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'plan rollback restores deleted plugin' => [static fn (): mixed => in_array(9, array_column($plan217()['rollback_to_transaction_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan current row seven retry' => [static fn (): mixed => array_column($plan217()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry217'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan217()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry217'],
    'plan current row nine retry' => [static fn (): mixed => array_column($plan217()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry217'],
    'plan current transients deleted after retry' => [static fn (): mixed => array_intersect([3, 4], array_column($plan217()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan217()['next_source_tables'], $plan217()['current_source_tables']],
    'plan before phases' => [static fn (): mixed => array_column($plan217()['before_rollback_statements'], 'phase'), ['before-or-rollback-next217', 'before-or-rollback-next217']],
    'plan rollback phase' => [static fn (): mixed => $plan217()['rollback_statement']['phase'], 'or-rollback-next217'],
    'plan rollback aborted flag' => [static fn (): mixed => $plan217()['rollback_statement']['aborted'], true],
    'plan rollback transaction flag' => [static fn (): mixed => $plan217()['rollback_statement']['rolled_back_to_transaction_start'], true],
    'plan rollback closed savepoint flag' => [static fn (): mixed => $plan217()['rollback_statement']['closed_savepoint'], true],
    'plan rollback selected ids' => [static fn (): mixed => $plan217()['rollback_statement']['selected_ids'], [7, 8]],
    'plan rollback mutation ids' => [static fn (): mixed => $plan217()['rollback_statement']['mutation_ids'], [7, 8]],
    'plan rollback conflict action' => [static fn (): mixed => $plan217()['rollback_statement']['conflict_action'], 'rollback'],
    'plan rollback error names unique columns' => [static fn (): mixed => str_contains($plan217()['rollback_statement']['error'], 'blog_id,option_name'), true],
    'plan rollback suppressed ids' => [static fn (): mixed => array_column($plan217()['suppressed_by_transaction_rollback_returning'], 'option_id'), [7, 8]],
    'plan rollback source rows include pre changes' => [static fn (): mixed => array_column($plan217()['rollback_statement']['source_rows'], 'status'), ['pre217', 'pre217']],
    'plan retry phases' => [static fn (): mixed => array_column($plan217()['retry_statements'], 'phase'), ['retry-after-transaction-rollback-next217', 'retry-after-transaction-rollback-next217']],
    'plan retry update source rows original' => [static fn (): mixed => array_column($plan217()['retry_statements'][0]['source_rows'], 'status'), ['queued', 'queued', 'queued']],
    'plan retry delete source rows restored transients' => [static fn (): mixed => array_column($plan217()['retry_statements'][1]['source_rows'], 'option_id'), [3, 4]],
    'plan pre yielded count' => [static fn (): mixed => $plan217()['pre_rollback_yielded_count'], 4],
    'plan pre changes count' => [static fn (): mixed => $plan217()['pre_rollback_changes_count'], 4],
    'plan suppressed count' => [static fn (): mixed => $plan217()['suppressed_by_rollback_count'], 2],
    'plan retry yielded count' => [static fn (): mixed => $plan217()['retry_yielded_count'], 5],
    'plan retry changes count' => [static fn (): mixed => $plan217()['retry_changes_count'], 5],
    'plan changed tables' => [static fn (): mixed => $plan217()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan217()['row_counts']['wp_options'], 8],
    'plan dependency rollback suppresses' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-rollback-suppresses-returning-next217', $plan217()['dependencies'], true), true],
    'plan dependency current source' => [static fn (): mixed => in_array('sqlite-rowvalue-or-rollback-discards-savepoint-current-source-next217', $plan217()['dependencies'], true), true],
    'plan dependency retry' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-retry-after-transaction-rollback-next217', $plan217()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan217()['dependency_closure_next217'], 'no new support component needed'), true],
    'plan non overlap note' => [static fn (): mixed => str_contains($plan217()['non_overlap_next217'], 'avoids accepted next210/next211 OR IGNORE'), true],
    'custom identifiers' => [static fn (): mixed => [$customPlan217()['transaction'], $customPlan217()['savepoint'], $customPlan217()['retry_savepoint']], ['wp_tx_custom217', 'wp_savepoint_custom217', 'wp_retry_custom217']],
    'custom pre count' => [static fn (): mixed => $customPlan217()['pre_rollback_yielded_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan217()['retry_yielded_count'], 3],
    'malformed empty pre rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [], $rollbackUpdate217, [$retryUpdate217], $unique217), InvalidArgumentException::class],
    'malformed empty rollback rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], '', [$retryUpdate217], $unique217), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], $rollbackUpdate217, [], $unique217), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], $rollbackUpdate217, [$retryUpdate217], []), InvalidArgumentException::class],
    'malformed transaction rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], $rollbackUpdate217, [$retryUpdate217], $unique217, 'bad-name'), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], $rollbackUpdate217, [$retryUpdate217], $unique217, 'good_tx', 'bad-name'), InvalidArgumentException::class],
    'malformed retry savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], $rollbackUpdate217, [$retryUpdate217], $unique217, 'good_tx', 'good_savepoint', 'bad-name'), InvalidArgumentException::class],
    'malformed rollback action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict($tables217, [$preUpdate217], str_replace('OR ROLLBACK', 'OR FAIL', $rollbackUpdate217), [$retryUpdate217], $unique217), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToConflict(['wp_options' => ['bad']], [$preUpdate217], $rollbackUpdate217, [$retryUpdate217], $unique217), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases217 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next217 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
