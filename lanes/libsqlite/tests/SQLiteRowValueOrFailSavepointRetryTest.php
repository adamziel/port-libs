<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rowsorFailRetry = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'transientorFailRetry', 'autoload' => 'no', 'status' => 'existing', 'bytes' => 10, 'option_value' => 'old-transient'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'draft_feed', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 30, 'option_value' => 'draft-feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => 'draft_conflict', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 40, 'option_value' => 'draft-conflict'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'draft_later', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 50, 'option_value' => 'draft-later'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 60, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 70, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'cleanuporFailRetry', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 80, 'option_value' => 'cleanup'],
];

$tablesorFailRetry = ['wp_options' => $rowsorFailRetry];
$uniqueorFailRetry = [['blog_id', 'option_name']];

$outerUpdateorFailRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('outerorFailRetry', option_value || ':outerorFailRetry', bytes + 1) WHERE (blog_id, option_name) IN ((4, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$failUpdateorFailRetry = "UPDATE OR FAIL wp_options SET (option_name, status, option_value, bytes) = ('transientorFailRetry', 'failorFailRetry', option_value || ':failorFailRetry', bytes + 5) WHERE (blog_id, option_name) IN (VALUES (2, 'draft_feed'), (1, 'draft_conflict'), (3, 'draft_later')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (2, 'transientorFailRetry') AS prefix_tuple_is ORDER BY option_id";
$retryUpdateorFailRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('retryorFailRetry', option_value || ':retryorFailRetry', bytes + 7) WHERE (blog_id, option_name) IN ((2, 'draft_feed'), (3, 'draft_later')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (option_name, status) IS NOT ('transientorFailRetry', 'failorFailRetry') AS retried_from_image ORDER BY option_id";
$retryDeleteorFailRetry = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (4, 'cleanuporFailRetry'), (1, 'draft_conflict')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (4, 'cleanuporFailRetry') AS not_cleanup ORDER BY option_id DESC";

$outerResultorFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdateorFailRetry, $tablesorFailRetry, 'option_id', $uniqueorFailRetry);
$failNoPreserveorFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdateorFailRetry, $outerResultorFailRetry()['tables'], 'option_id', $uniqueorFailRetry);
$failPreserveorFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdateorFailRetry, $outerResultorFailRetry()['tables'], 'option_id', $uniqueorFailRetry, true);
$retryUpdateAfterRollbackorFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateorFailRetry, $outerResultorFailRetry()['tables'], 'option_id', $uniqueorFailRetry);
$retryDeleteAfterRollbackorFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeleteorFailRetry, $retryUpdateAfterRollbackorFailRetry()['tables'], 'option_id', $uniqueorFailRetry);
$planorFailRetry = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry(
    $tablesorFailRetry,
    [$outerUpdateorFailRetry],
    [$failUpdateorFailRetry],
    [$retryUpdateorFailRetry, $retryDeleteorFailRetry],
    $uniqueorFailRetry,
);
$customPlanorFailRetry = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry(
    $tablesorFailRetry,
    [$outerUpdateorFailRetry],
    [$failUpdateorFailRetry],
    [$retryUpdateorFailRetry],
    $uniqueorFailRetry,
    'wp_custom_fail_orFailRetry',
);

$casesorFailRetry = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdateorFailRetry)['conflict_action'], 'fail'],
    'parser fail row value where preserves values keyword' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdateorFailRetry)['where'], "(blog_id, option_name) IN (VALUES (2, 'draft_feed'), (1, 'draft_conflict'), (3, 'draft_later'))"],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdateorFailRetry)['where'], "(blog_id, option_name) IN ((2, 'draft_feed'), (3, 'draft_later'))"],
    'parser retry delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDeleteorFailRetry)['where'], "(blog_id, option_name) IN (VALUES (4, 'cleanuporFailRetry'), (1, 'draft_conflict'))"],

    'outer update selected rewrite row' => [static fn (): mixed => $outerResultorFailRetry()['plan']->selectedIds, [7]],
    'outer update returning rewrite row' => [static fn (): mixed => array_column($outerResultorFailRetry()['returning'], 'option_id'), [7]],
    'outer update current row seven status' => [static fn (): mixed => array_column($outerResultorFailRetry()['tables']['wp_options'], 'status', 'option_id')[7], 'outerorFailRetry'],

    'or fail without preserve throws' => [static fn (): mixed => $failNoPreserveorFailRetry(), InvalidArgumentException::class],
    'or fail preserve selected ids' => [static fn (): mixed => $failPreserveorFailRetry()['plan']->selectedIds, [3, 4, 5]],
    'or fail preserve mutation ids' => [static fn (): mixed => $failPreserveorFailRetry()['plan']->mutationIds, [3, 4, 5]],
    'or fail preserve returns prefix row only' => [static fn (): mixed => array_column($failPreserveorFailRetry()['returning'], 'option_id'), [3]],
    'or fail prefix tuple is true' => [static fn (): mixed => array_column($failPreserveorFailRetry()['returning'], 'prefix_tuple_is'), [1]],
    'or fail records first conflict row' => [static fn (): mixed => $failPreserveorFailRetry()['failed_conflict']['row_id'], 4],
    'or fail conflict key' => [static fn (): mixed => $failPreserveorFailRetry()['failed_conflict']['key'], '1|transientorFailRetry'],
    'or fail conflict existing row' => [static fn (): mixed => $failPreserveorFailRetry()['failed_conflict']['conflicting_row_ids'], [1]],
    'or fail current row three renamed before rollback' => [static fn (): mixed => array_column($failPreserveorFailRetry()['tables']['wp_options'], 'option_name', 'option_id')[3], 'transientorFailRetry'],
    'or fail current row three status before rollback' => [static fn (): mixed => array_column($failPreserveorFailRetry()['tables']['wp_options'], 'status', 'option_id')[3], 'failorFailRetry'],
    'or fail current row four rolled back within statement' => [static fn (): mixed => array_column($failPreserveorFailRetry()['tables']['wp_options'], 'option_name', 'option_id')[4], 'draft_conflict'],
    'or fail current row five not reached' => [static fn (): mixed => array_column($failPreserveorFailRetry()['tables']['wp_options'], 'status', 'option_id')[5], 'draft'],

    'retry update after rollback selected original rows' => [static fn (): mixed => $retryUpdateAfterRollbackorFailRetry()['plan']->selectedIds, [3, 5]],
    'retry update after rollback returning ids' => [static fn (): mixed => array_column($retryUpdateAfterRollbackorFailRetry()['returning'], 'option_id'), [3, 5]],
    'retry update row three kept original name' => [static fn (): mixed => array_column($retryUpdateAfterRollbackorFailRetry()['returning'], 'option_name', 'option_id')[3], 'draft_feed'],
    'retry update row three value excludes fail prefix' => [static fn (): mixed => array_column($retryUpdateAfterRollbackorFailRetry()['returning'], 'option_value', 'option_id')[3], 'draft-feed:retryorFailRetry'],
    'retry update row five processed after rollback' => [static fn (): mixed => array_column($retryUpdateAfterRollbackorFailRetry()['returning'], 'option_value', 'option_id')[5], 'draft-later:retryorFailRetry'],
    'retry update tuple flags from image' => [static fn (): mixed => array_column($retryUpdateAfterRollbackorFailRetry()['returning'], 'retried_from_image'), [1, 1]],
    'retry delete selected ids order by desc' => [static fn (): mixed => $retryDeleteAfterRollbackorFailRetry()['plan']->selectedIds, [8, 4]],
    'retry delete returning ids' => [static fn (): mixed => array_column($retryDeleteAfterRollbackorFailRetry()['returning'], 'option_id'), [4, 8]],
    'retry delete distinct flags' => [static fn (): mixed => array_column($retryDeleteAfterRollbackorFailRetry()['returning'], 'not_cleanup'), [1, 0]],

    'plan status' => [static fn (): mixed => $planorFailRetry()['status'], 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-or-fail-savepoint-retry'],
    'plan savepoint' => [static fn (): mixed => $planorFailRetry()['savepoint'], 'wp_options_rowvalue_fail_or_fail_savepoint_retry'],
    'plan fail prefix flag' => [static fn (): mixed => $planorFailRetry()['statement_fail_preserved_prefix_or-fail-savepoint-retry'], true],
    'plan rolled back' => [static fn (): mixed => $planorFailRetry()['rolled_back_to_savepoint'], true],
    'plan release after retry' => [static fn (): mixed => $planorFailRetry()['savepoint_released_after_retry'], true],
    'plan savepoint image equals outer current' => [static fn (): mixed => $planorFailRetry()['savepoint_image_tables'], $planorFailRetry()['outer_current_source_tables']],
    'plan fail prefix row three exists' => [static fn (): mixed => array_column($planorFailRetry()['fail_prefix_current_source_tables']['wp_options'], 'status', 'option_id')[3], 'failorFailRetry'],
    'plan rollback discards fail prefix' => [static fn (): mixed => array_column($planorFailRetry()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[3], 'draft'],
    'plan retry current row three' => [static fn (): mixed => array_column($planorFailRetry()['current_source_tables']['wp_options'], 'status', 'option_id')[3], 'retryorFailRetry'],
    'plan retry current row five' => [static fn (): mixed => array_column($planorFailRetry()['current_source_tables']['wp_options'], 'status', 'option_id')[5], 'retryorFailRetry'],
    'plan current excludes deleted row four' => [static fn (): mixed => in_array(4, array_column($planorFailRetry()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current excludes cleanup row eight' => [static fn (): mixed => in_array(8, array_column($planorFailRetry()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $planorFailRetry()['next_source_tables'], $planorFailRetry()['current_source_tables']],
    'plan fail selected ids' => [static fn (): mixed => $planorFailRetry()['fail_statements'][0]['selected_ids'], [3, 4, 5]],
    'plan fail returning rows prefix only' => [static fn (): mixed => array_column($planorFailRetry()['fail_statements'][0]['returning_rows'], 'option_id'), [3]],
    'plan fail failed conflict row id' => [static fn (): mixed => $planorFailRetry()['fail_statements'][0]['failed_conflict']['row_id'], 4],
    'plan retry source row three from rollback image' => [static fn (): mixed => array_column($planorFailRetry()['retry_statements'][0]['source_rows'], 'option_name', 'option_id')[3], 'draft_feed'],
    'plan retry source row five from rollback image' => [static fn (): mixed => array_column($planorFailRetry()['retry_statements'][0]['source_rows'], 'option_name', 'option_id')[5], 'draft_later'],
    'plan retry delete source rows' => [static fn (): mixed => array_column($planorFailRetry()['retry_statements'][1]['source_rows'], 'option_id'), [4, 8]],
    'plan outer returning count' => [static fn (): mixed => $planorFailRetry()['outer_returning_count'], 1],
    'plan fail returning count' => [static fn (): mixed => $planorFailRetry()['fail_prefix_returning_count'], 1],
    'plan suppressed rollback count' => [static fn (): mixed => $planorFailRetry()['suppressed_by_rollback_count'], 1],
    'plan retry returning count' => [static fn (): mixed => $planorFailRetry()['yielded_after_retry_count'], 4],
    'plan fail conflict count' => [static fn (): mixed => $planorFailRetry()['fail_conflict_count'], 1],
    'plan changes preserved by fail before rollback' => [static fn (): mixed => $planorFailRetry()['changes_preserved_by_fail_before_rollback'], 1],
    'plan changes after retry' => [static fn (): mixed => $planorFailRetry()['changes_after_retry'], 4],
    'plan changed tables' => [static fn (): mixed => $planorFailRetry()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $planorFailRetry()['row_counts']['wp_options'], 6],
    'plan dependency fail prefix' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-returning-prefix-or-fail-savepoint-retry', $planorFailRetry()['dependencies'], true), true],
    'plan dependency rollback suppression' => [static fn (): mixed => in_array('sqlite-rowvalue-savepoint-rollback-discards-or-fail-prefix-or-fail-savepoint-retry', $planorFailRetry()['dependencies'], true), true],
    'plan non overlap mentions abort' => [static fn (): mixed => str_contains($planorFailRetry()['non_overlap_or-fail-savepoint-retry'], 'OR ABORT abort-statement-savepoint'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlanorFailRetry()['savepoint'], 'wp_custom_fail_orFailRetry'],
    'custom plan retry count' => [static fn (): mixed => $customPlanorFailRetry()['yielded_after_retry_count'], 2],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry($tablesorFailRetry, [], [$failUpdateorFailRetry], [$retryUpdateorFailRetry], $uniqueorFailRetry), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry($tablesorFailRetry, [$outerUpdateorFailRetry], [], [$retryUpdateorFailRetry], $uniqueorFailRetry), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry($tablesorFailRetry, [$outerUpdateorFailRetry], [$failUpdateorFailRetry], [], $uniqueorFailRetry), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry($tablesorFailRetry, [$outerUpdateorFailRetry], [$failUpdateorFailRetry], [$retryUpdateorFailRetry], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry($tablesorFailRetry, [$outerUpdateorFailRetry], [$failUpdateorFailRetry], [$retryUpdateorFailRetry], $uniqueorFailRetry, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrFailSavepointRetry(['wp_options' => ['bad']], [$outerUpdateorFailRetry], [$failUpdateorFailRetry], [$retryUpdateorFailRetry], $uniqueorFailRetry), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesorFailRetry as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source or-fail-savepoint-retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
