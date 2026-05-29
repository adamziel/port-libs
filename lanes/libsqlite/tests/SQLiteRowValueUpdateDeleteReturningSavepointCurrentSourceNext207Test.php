<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows207 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'transient207', 'autoload' => 'no', 'status' => 'existing', 'bytes' => 10, 'option_value' => 'old-transient'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'draft_feed', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 30, 'option_value' => 'draft-feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => 'draft_conflict', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 40, 'option_value' => 'draft-conflict'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'draft_later', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 50, 'option_value' => 'draft-later'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 60, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 70, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'cleanup207', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 80, 'option_value' => 'cleanup'],
];

$tables207 = ['wp_options' => $rows207];
$unique207 = [['blog_id', 'option_name']];

$outerUpdate207 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer207', option_value || ':outer207', bytes + 1) WHERE (blog_id, option_name) IN ((4, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$failUpdate207 = "UPDATE OR FAIL wp_options SET (option_name, status, option_value, bytes) = ('transient207', 'fail207', option_value || ':fail207', bytes + 5) WHERE (blog_id, option_name) IN (VALUES (2, 'draft_feed'), (1, 'draft_conflict'), (3, 'draft_later')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (2, 'transient207') AS prefix_tuple_is ORDER BY option_id";
$retryUpdate207 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry207', option_value || ':retry207', bytes + 7) WHERE (blog_id, option_name) IN ((2, 'draft_feed'), (3, 'draft_later')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (option_name, status) IS NOT ('transient207', 'fail207') AS retried_from_image ORDER BY option_id";
$retryDelete207 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (4, 'cleanup207'), (1, 'draft_conflict')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (4, 'cleanup207') AS not_cleanup ORDER BY option_id DESC";

$outerResult207 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate207, $tables207, 'option_id', $unique207);
$failNoPreserve207 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate207, $outerResult207()['tables'], 'option_id', $unique207);
$failPreserve207 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate207, $outerResult207()['tables'], 'option_id', $unique207, true);
$retryUpdateAfterRollback207 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate207, $outerResult207()['tables'], 'option_id', $unique207);
$retryDeleteAfterRollback207 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete207, $retryUpdateAfterRollback207()['tables'], 'option_id', $unique207);
$plan207 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry(
    $tables207,
    [$outerUpdate207],
    [$failUpdate207],
    [$retryUpdate207, $retryDelete207],
    $unique207,
);
$customPlan207 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry(
    $tables207,
    [$outerUpdate207],
    [$failUpdate207],
    [$retryUpdate207],
    $unique207,
    'wp_custom_fail_207',
);

$cases207 = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate207)['conflict_action'], 'fail'],
    'parser fail row value where preserves values keyword' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate207)['where'], "(blog_id, option_name) IN (VALUES (2, 'draft_feed'), (1, 'draft_conflict'), (3, 'draft_later'))"],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate207)['where'], "(blog_id, option_name) IN ((2, 'draft_feed'), (3, 'draft_later'))"],
    'parser retry delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete207)['where'], "(blog_id, option_name) IN (VALUES (4, 'cleanup207'), (1, 'draft_conflict'))"],

    'outer update selected rewrite row' => [static fn (): mixed => $outerResult207()['plan']->selectedIds, [7]],
    'outer update returning rewrite row' => [static fn (): mixed => array_column($outerResult207()['returning'], 'option_id'), [7]],
    'outer update current row seven status' => [static fn (): mixed => array_column($outerResult207()['tables']['wp_options'], 'status', 'option_id')[7], 'outer207'],

    'or fail without preserve throws' => [static fn (): mixed => $failNoPreserve207(), InvalidArgumentException::class],
    'or fail preserve selected ids' => [static fn (): mixed => $failPreserve207()['plan']->selectedIds, [3, 4, 5]],
    'or fail preserve mutation ids' => [static fn (): mixed => $failPreserve207()['plan']->mutationIds, [3, 4, 5]],
    'or fail preserve returns prefix row only' => [static fn (): mixed => array_column($failPreserve207()['returning'], 'option_id'), [3]],
    'or fail prefix tuple is true' => [static fn (): mixed => array_column($failPreserve207()['returning'], 'prefix_tuple_is'), [1]],
    'or fail records first conflict row' => [static fn (): mixed => $failPreserve207()['failed_conflict']['row_id'], 4],
    'or fail conflict key' => [static fn (): mixed => $failPreserve207()['failed_conflict']['key'], '1|transient207'],
    'or fail conflict existing row' => [static fn (): mixed => $failPreserve207()['failed_conflict']['conflicting_row_ids'], [1]],
    'or fail current row three renamed before rollback' => [static fn (): mixed => array_column($failPreserve207()['tables']['wp_options'], 'option_name', 'option_id')[3], 'transient207'],
    'or fail current row three status before rollback' => [static fn (): mixed => array_column($failPreserve207()['tables']['wp_options'], 'status', 'option_id')[3], 'fail207'],
    'or fail current row four rolled back within statement' => [static fn (): mixed => array_column($failPreserve207()['tables']['wp_options'], 'option_name', 'option_id')[4], 'draft_conflict'],
    'or fail current row five not reached' => [static fn (): mixed => array_column($failPreserve207()['tables']['wp_options'], 'status', 'option_id')[5], 'draft'],

    'retry update after rollback selected original rows' => [static fn (): mixed => $retryUpdateAfterRollback207()['plan']->selectedIds, [3, 5]],
    'retry update after rollback returning ids' => [static fn (): mixed => array_column($retryUpdateAfterRollback207()['returning'], 'option_id'), [3, 5]],
    'retry update row three kept original name' => [static fn (): mixed => array_column($retryUpdateAfterRollback207()['returning'], 'option_name', 'option_id')[3], 'draft_feed'],
    'retry update row three value excludes fail prefix' => [static fn (): mixed => array_column($retryUpdateAfterRollback207()['returning'], 'option_value', 'option_id')[3], 'draft-feed:retry207'],
    'retry update row five processed after rollback' => [static fn (): mixed => array_column($retryUpdateAfterRollback207()['returning'], 'option_value', 'option_id')[5], 'draft-later:retry207'],
    'retry update tuple flags from image' => [static fn (): mixed => array_column($retryUpdateAfterRollback207()['returning'], 'retried_from_image'), [1, 1]],
    'retry delete selected ids order by desc' => [static fn (): mixed => $retryDeleteAfterRollback207()['plan']->selectedIds, [8, 4]],
    'retry delete returning ids' => [static fn (): mixed => array_column($retryDeleteAfterRollback207()['returning'], 'option_id'), [4, 8]],
    'retry delete distinct flags' => [static fn (): mixed => array_column($retryDeleteAfterRollback207()['returning'], 'not_cleanup'), [1, 0]],

    'plan status' => [static fn (): mixed => $plan207()['status'], 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-next207'],
    'plan savepoint' => [static fn (): mixed => $plan207()['savepoint'], 'wp_options_rowvalue_fail_next207'],
    'plan fail prefix flag' => [static fn (): mixed => $plan207()['statement_fail_preserved_prefix_next207'], true],
    'plan rolled back' => [static fn (): mixed => $plan207()['rolled_back_to_savepoint'], true],
    'plan release after retry' => [static fn (): mixed => $plan207()['savepoint_released_after_retry'], true],
    'plan savepoint image equals outer current' => [static fn (): mixed => $plan207()['savepoint_image_tables'], $plan207()['outer_current_source_tables']],
    'plan fail prefix row three exists' => [static fn (): mixed => array_column($plan207()['fail_prefix_current_source_tables']['wp_options'], 'status', 'option_id')[3], 'fail207'],
    'plan rollback discards fail prefix' => [static fn (): mixed => array_column($plan207()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[3], 'draft'],
    'plan retry current row three' => [static fn (): mixed => array_column($plan207()['current_source_tables']['wp_options'], 'status', 'option_id')[3], 'retry207'],
    'plan retry current row five' => [static fn (): mixed => array_column($plan207()['current_source_tables']['wp_options'], 'status', 'option_id')[5], 'retry207'],
    'plan current excludes deleted row four' => [static fn (): mixed => in_array(4, array_column($plan207()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current excludes cleanup row eight' => [static fn (): mixed => in_array(8, array_column($plan207()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan207()['next_source_tables'], $plan207()['current_source_tables']],
    'plan fail selected ids' => [static fn (): mixed => $plan207()['fail_statements'][0]['selected_ids'], [3, 4, 5]],
    'plan fail returning rows prefix only' => [static fn (): mixed => array_column($plan207()['fail_statements'][0]['returning_rows'], 'option_id'), [3]],
    'plan fail failed conflict row id' => [static fn (): mixed => $plan207()['fail_statements'][0]['failed_conflict']['row_id'], 4],
    'plan retry source row three from rollback image' => [static fn (): mixed => array_column($plan207()['retry_statements'][0]['source_rows'], 'option_name', 'option_id')[3], 'draft_feed'],
    'plan retry source row five from rollback image' => [static fn (): mixed => array_column($plan207()['retry_statements'][0]['source_rows'], 'option_name', 'option_id')[5], 'draft_later'],
    'plan retry delete source rows' => [static fn (): mixed => array_column($plan207()['retry_statements'][1]['source_rows'], 'option_id'), [4, 8]],
    'plan outer returning count' => [static fn (): mixed => $plan207()['outer_returning_count'], 1],
    'plan fail returning count' => [static fn (): mixed => $plan207()['fail_prefix_returning_count'], 1],
    'plan suppressed rollback count' => [static fn (): mixed => $plan207()['suppressed_by_rollback_count'], 1],
    'plan retry returning count' => [static fn (): mixed => $plan207()['yielded_after_retry_count'], 4],
    'plan fail conflict count' => [static fn (): mixed => $plan207()['fail_conflict_count'], 1],
    'plan changes preserved by fail before rollback' => [static fn (): mixed => $plan207()['changes_preserved_by_fail_before_rollback'], 1],
    'plan changes after retry' => [static fn (): mixed => $plan207()['changes_after_retry'], 4],
    'plan changed tables' => [static fn (): mixed => $plan207()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan207()['row_counts']['wp_options'], 6],
    'plan dependency fail prefix' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-returning-prefix-next207', $plan207()['dependencies'], true), true],
    'plan dependency rollback suppression' => [static fn (): mixed => in_array('sqlite-rowvalue-savepoint-rollback-discards-or-fail-prefix-next207', $plan207()['dependencies'], true), true],
    'plan non overlap mentions abort' => [static fn (): mixed => str_contains($plan207()['non_overlap_next207'], 'OR ABORT next200'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan207()['savepoint'], 'wp_custom_fail_207'],
    'custom plan retry count' => [static fn (): mixed => $customPlan207()['yielded_after_retry_count'], 2],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry($tables207, [], [$failUpdate207], [$retryUpdate207], $unique207), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry($tables207, [$outerUpdate207], [], [$retryUpdate207], $unique207), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry($tables207, [$outerUpdate207], [$failUpdate207], [], $unique207), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry($tables207, [$outerUpdate207], [$failUpdate207], [$retryUpdate207], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry($tables207, [$outerUpdate207], [$failUpdate207], [$retryUpdate207], $unique207, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrFailSavepointRetry(['wp_options' => ['bad']], [$outerUpdate207], [$failUpdate207], [$retryUpdate207], $unique207), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases207 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next207 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
