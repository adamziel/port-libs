<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows185 = [
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

$tables185 = ['wp_options' => $rows185];
$unique185 = [['blog_id', 'option_name']];

$preDelete185 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS transient_pair ORDER BY option_id";
$preUpdate185 = "UPDATE wp_options SET (status, option_value, bytes) = ('staged185', option_value || ':staged185', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('staged185', 'pending_theme') AS pending_staged ORDER BY option_id";
$failUpdate185 = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'home', 'failed185', option_value || ':failed185', bytes + 10) WHERE option_id IN (5, 7) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdate185 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry185', option_value || ':retry185', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry185', 'pending_theme') AS pending_retry ORDER BY option_id";
$retryDelete185 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$preDeleteResult185 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDelete185, $tables185, 'option_id', $unique185);
$preUpdateResult185 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdate185, $preDeleteResult185()['tables'], 'option_id', $unique185);
$failDefault185 = static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($failUpdate185, $preUpdateResult185()['tables'], 'option_id', $unique185);
$failPartial185 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate185, $preUpdateResult185()['tables'], 'option_id', $unique185, true);
$retryUpdateResult185 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate185, $tables185, 'option_id', $unique185);
$plan185 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint(
    $tables185,
    [$preDelete185, $preUpdate185],
    $failUpdate185,
    [$retryUpdate185, $retryDelete185],
    $unique185,
);

$cases185 = [
    'parser pre delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($preDelete185)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'parser pre update row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($preUpdate185)['assignments']), ['status', 'option_value', 'bytes']],
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate185)['conflict_action'], 'fail'],
    'parser fail row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdate185)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate185)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'))"],
    'pre delete selected stale ids' => [static fn (): mixed => $preDeleteResult185()['plan']->selectedIds, [3, 4]],
    'pre delete returning transient flags' => [static fn (): mixed => array_column($preDeleteResult185()['returning'], 'transient_pair'), [1, 1]],
    'pre delete removes stale ids' => [static fn (): mixed => array_column($preDeleteResult185()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'pre update selected pending and rewrite ids' => [static fn (): mixed => $preUpdateResult185()['plan']->selectedIds, [7, 8]],
    'pre update returning ids' => [static fn (): mixed => array_column($preUpdateResult185()['returning'], 'option_id'), [7, 8]],
    'pre update pending staged flag true' => [static fn (): mixed => $preUpdateResult185()['returning'][0]['pending_staged'], 1],
    'pre update row seven value staged' => [static fn (): mixed => array_column($preUpdateResult185()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:staged185'],
    'pre update row eight status staged' => [static fn (): mixed => array_column($preUpdateResult185()['tables']['wp_options'], 'status', 'option_id')[8], 'staged185'],
    'default fail still throws unique conflict' => [static fn (): mixed => $failDefault185(), InvalidArgumentException::class],
    'partial fail selected ids in table order' => [static fn (): mixed => $failPartial185()['plan']->selectedIds, [5, 7]],
    'partial fail mutates prior non conflicting row five' => [static fn (): mixed => array_column($failPartial185()['tables']['wp_options'], 'option_name', 'option_id')[5], 'home'],
    'partial fail keeps conflicting row seven original staged key' => [static fn (): mixed => array_column($failPartial185()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'partial fail returning only row five before conflict' => [static fn (): mixed => array_column($failPartial185()['returning'], 'option_id'), [5]],
    'partial fail conflict row seven' => [static fn (): mixed => $failPartial185()['failed_conflict']['row_id'], 7],
    'partial fail conflict peer row five' => [static fn (): mixed => $failPartial185()['failed_conflict']['conflicting_row_ids'], [5]],
    'partial fail conflict key' => [static fn (): mixed => $failPartial185()['failed_conflict']['key'], '4|home'],
    'partial fail conflicts recorded for row seven' => [static fn (): mixed => array_column($failPartial185()['conflicts'], 'row_id'), [7]],
    'retry update from original selects row seven and nine' => [static fn (): mixed => $retryUpdateResult185()['plan']->selectedIds, [7, 9]],
    'retry update from original returns retry ids' => [static fn (): mixed => array_column($retryUpdateResult185()['returning'], 'option_id'), [7, 9]],
    'retry update from original value starts without staged suffix' => [static fn (): mixed => $retryUpdateResult185()['returning'][0]['option_value'], 'theme:retry185'],

    'plan status' => [static fn (): mixed => $plan185()['status'], 'or-fail-partial-rowvalue-returning-rolled-back-retried-next185'],
    'plan savepoint name' => [static fn (): mixed => $plan185()['savepoint'], 'wp_options_rowvalue_fail_next185'],
    'plan rolled back' => [static fn (): mixed => $plan185()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback' => [static fn (): mixed => $plan185()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan185()['released_after_retry'], true],
    'plan image original ids' => [static fn (): mixed => array_column($plan185()['savepoint_image_tables']['wp_options'], 'option_id'), range(1, 10)],
    'plan before fail current omits pre deleted ids' => [static fn (): mixed => array_column($plan185()['before_fail_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'plan before fail row seven staged' => [static fn (): mixed => array_column($plan185()['before_fail_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'staged185'],
    'plan partial fail row five changed' => [static fn (): mixed => array_column($plan185()['partial_fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'home'],
    'plan partial fail row seven reverted after conflict' => [static fn (): mixed => array_column($plan185()['partial_fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback restores deleted ids' => [static fn (): mixed => array_column($plan185()['rollback_to_current_source_tables']['wp_options'], 'option_id'), range(1, 10)],
    'plan rollback restores row seven original status' => [static fn (): mixed => array_column($plan185()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback restores row nine original key' => [static fn (): mixed => array_column($plan185()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[9], 'orphaned_cache'],
    'plan pre fail statement phases' => [static fn (): mixed => array_column($plan185()['pre_fail_statements'], 'phase'), ['before-fail', 'before-fail']],
    'plan fail statement phase' => [static fn (): mixed => $plan185()['fail_statement']['phase'], 'or-fail-partial-before-rollback'],
    'plan fail statement selected ids' => [static fn (): mixed => $plan185()['fail_statement']['selected_ids'], [5, 7]],
    'plan fail statement returned only prior success' => [static fn (): mixed => array_column($plan185()['fail_statement']['returning_rows'], 'option_id'), [5]],
    'plan fail statement conflict action' => [static fn (): mixed => $plan185()['fail_statement']['conflict_action'], 'fail'],
    'plan fail statement failed conflict row' => [static fn (): mixed => $plan185()['fail_statement']['failed_conflict']['row_id'], 7],
    'plan failed conflict exposed at top level' => [static fn (): mixed => $plan185()['failed_conflict']['row_id'], 7],
    'plan pre fail returning count' => [static fn (): mixed => $plan185()['pre_fail_returning_count'], 4],
    'plan partial fail returning count' => [static fn (): mixed => $plan185()['partial_fail_returning_count'], 1],
    'plan suppressed by rollback count' => [static fn (): mixed => $plan185()['suppressed_by_rollback_count'], 5],
    'plan attempted changes before rollback' => [static fn (): mixed => $plan185()['attempted_changes_before_rollback_to'], 5],
    'plan partial fail changes before rollback' => [static fn (): mixed => $plan185()['partial_fail_changes_before_rollback_to'], 1],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan185()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry statement phases' => [static fn (): mixed => array_column($plan185()['retry_statements'], 'phase'), ['retry-after-fail-rollback', 'retry-after-fail-rollback']],
    'plan retry update selected restored rows' => [static fn (): mixed => $plan185()['retry_statements'][0]['selected_ids'], [7, 9]],
    'plan retry update source rows original values' => [static fn (): mixed => array_column($plan185()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'cache']],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan185()['yielded_after_retry_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan retry update pending flag' => [static fn (): mixed => $plan185()['yielded_after_retry_returning'][0]['rows'][0]['pending_retry'], 1],
    'plan retry delete selected original stale ids' => [static fn (): mixed => $plan185()['retry_statements'][1]['selected_ids'], [3, 4]],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan185()['yielded_after_retry_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan yielded after retry count' => [static fn (): mixed => $plan185()['yielded_after_retry_count'], 4],
    'plan changes after retry release' => [static fn (): mixed => $plan185()['changes_after_retry_release'], 4],
    'plan final ids omit retry deleted rows' => [static fn (): mixed => array_column($plan185()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'plan final row seven retry value starts from original' => [static fn (): mixed => array_column($plan185()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry185'],
    'plan final row eight stage undone by rollback' => [static fn (): mixed => array_column($plan185()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan final row nine retry key preserved' => [static fn (): mixed => array_column($plan185()['current_source_tables']['wp_options'], 'option_name', 'option_id')[9], 'orphaned_cache'],
    'plan next source equals current source' => [static fn (): mixed => $plan185()['next_source_tables'], $plan185()['current_source_tables']],
    'plan row count after retry' => [static fn (): mixed => $plan185()['row_counts']['wp_options'], 8],
    'plan changed tables after retry' => [static fn (): mixed => $plan185()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency prior row changes' => [static fn (): mixed => in_array('sqlite-update-or-fail-rowvalue-preserves-prior-row-changes-next185', $plan185()['dependencies'], true), true],
    'plan dependency rollback discards partial returning' => [static fn (): mixed => in_array('sqlite-rollback-to-discards-partial-or-fail-returning-next185', $plan185()['dependencies'], true), true],
    'plan dependency retry current source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-after-or-fail-current-source-next185', $plan185()['dependencies'], true), true],

    'malformed empty pre fail statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint($tables185, [], $failUpdate185, [$retryUpdate185], $unique185), InvalidArgumentException::class],
    'malformed empty fail statement rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint($tables185, [$preDelete185], '', [$retryUpdate185], $unique185), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint($tables185, [$preDelete185], $failUpdate185, [], $unique185), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint($tables185, [$preDelete185], $failUpdate185, [$retryUpdate185], []), InvalidArgumentException::class],
    'malformed savepoint name rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint($tables185, [$preDelete185], $failUpdate185, [$retryUpdate185], $unique185, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailRollbackRetrySavepoint(['wp_options' => ['bad']], [$preDelete185], $failUpdate185, [$retryUpdate185], $unique185), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases185 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next185 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
