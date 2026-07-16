<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows188 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables188 = ['wp_options' => $rows188];
$emptyDelete188 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN () RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN () AS empty_member ORDER BY option_id";
$notEmptyUpdate188 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt188', option_value || ':attempt188', bytes + 1) WHERE (blog_id, option_name) NOT IN () RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) NOT IN () AS outside_empty ORDER BY option_id";
$retryDelete188 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN () AND autoload = 'no' RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN () AS outside_empty ORDER BY option_id";
$retryUpdate188 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry188', option_value || ':retry188', bytes + 5) WHERE (blog_id, option_name) NOT IN () AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";

$emptyDeleteResult188 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($emptyDelete188, $tables188);
$notEmptyUpdateResult188 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($notEmptyUpdate188, $tables188);
$retryDeleteResult188 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete188, $tables188);
$retryUpdateAfterDelete188 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate188, $retryDeleteResult188()['tables']);
$plan188 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    $tables188,
    [$emptyDelete188, $notEmptyUpdate188],
    [$retryDelete188, $retryUpdate188],
);

$cases188 = [
    'parser empty delete where retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($emptyDelete188)['where'], '(blog_id, option_name) IN ()'],
    'parser not empty update where retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($notEmptyUpdate188)['where'], '(blog_id, option_name) NOT IN ()'],
    'parser retry delete where retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete188)['where'], "(blog_id, option_name) NOT IN () AND autoload = 'no'"],
    'parser update row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($notEmptyUpdate188)['assignments']), ['status', 'option_value', 'bytes']],
    'empty delete selects no rows' => [static fn (): mixed => $emptyDeleteResult188()['plan']->selectedIds, []],
    'empty delete returns no rows' => [static fn (): mixed => $emptyDeleteResult188()['returning'], []],
    'empty delete leaves source ids unchanged' => [static fn (): mixed => array_column($emptyDeleteResult188()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6]],
    'empty delete mutation ids empty' => [static fn (): mixed => $emptyDeleteResult188()['plan']->mutationIds, []],
    'not empty update selects every row' => [static fn (): mixed => $notEmptyUpdateResult188()['plan']->selectedIds, [1, 2, 3, 4, 5, 6]],
    'not empty update returns every row' => [static fn (): mixed => array_column($notEmptyUpdateResult188()['returning'], 'option_id'), [1, 2, 3, 4, 5, 6]],
    'not empty update returning flags true' => [static fn (): mixed => array_column($notEmptyUpdateResult188()['returning'], 'outside_empty'), [1, 1, 1, 1, 1, 1]],
    'not empty update first value changed' => [static fn (): mixed => $notEmptyUpdateResult188()['returning'][0]['option_value'], 'https://old.test:attempt188'],
    'not empty update null status overwritten' => [static fn (): mixed => array_column($notEmptyUpdateResult188()['tables']['wp_options'], 'status', 'option_id')[5], 'attempt188'],
    'not empty update bytes incremented' => [static fn (): mixed => array_column($notEmptyUpdateResult188()['tables']['wp_options'], 'bytes', 'option_id')[6], 10],
    'retry delete selects non autoload rows' => [static fn (): mixed => $retryDeleteResult188()['plan']->selectedIds, [3, 4, 5]],
    'retry delete returning ids' => [static fn (): mixed => array_column($retryDeleteResult188()['returning'], 'option_id'), [3, 4, 5]],
    'retry delete flags true' => [static fn (): mixed => array_column($retryDeleteResult188()['returning'], 'outside_empty'), [1, 1, 1]],
    'retry delete removes only non autoload ids' => [static fn (): mixed => array_column($retryDeleteResult188()['tables']['wp_options'], 'option_id'), [1, 2, 6]],
    'retry update after delete selects autoload rows' => [static fn (): mixed => $retryUpdateAfterDelete188()['plan']->selectedIds, [1, 2, 6]],
    'retry update after delete returns autoload rows' => [static fn (): mixed => array_column($retryUpdateAfterDelete188()['returning'], 'option_id'), [1, 2, 6]],
    'retry update after delete starts from original value' => [static fn (): mixed => $retryUpdateAfterDelete188()['returning'][0]['option_value'], 'https://old.test:retry188'],

    'plan status' => [static fn (): mixed => $plan188()['status'], 'rowvalue-empty-in-returning-rolled-back-retried-next188'],
    'plan savepoint name' => [static fn (): mixed => $plan188()['savepoint'], 'app_settings_rowvalue_empty_in_next188'],
    'plan rolled back' => [static fn (): mixed => $plan188()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved' => [static fn (): mixed => $plan188()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan188()['released_after_retry'], true],
    'plan image ids' => [static fn (): mixed => array_column($plan188()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6]],
    'plan attempt current source keeps every row' => [static fn (): mixed => array_column($plan188()['attempt_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6]],
    'plan attempt current row one attempted value' => [static fn (): mixed => array_column($plan188()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:attempt188'],
    'plan rollback restores row one original value' => [static fn (): mixed => array_column($plan188()['rollback_to_current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test'],
    'plan rollback restores row five null status' => [static fn (): mixed => array_column($plan188()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[5], null],
    'plan attempt statement actions' => [static fn (): mixed => array_column($plan188()['attempt_statements'], 'action'), ['delete', 'update']],
    'plan attempt empty delete selected none' => [static fn (): mixed => $plan188()['attempt_statements'][0]['selected_ids'], []],
    'plan attempt update selected all ids' => [static fn (): mixed => $plan188()['attempt_statements'][1]['selected_ids'], [1, 2, 3, 4, 5, 6]],
    'plan attempt update source row values original' => [static fn (): mixed => array_column($plan188()['attempt_statements'][1]['source_rows'], 'option_value'), ['https://old.test', 'https://home.test', 'feed', 'timeout', 'theme', 'rules']],
    'plan attempt returning count' => [static fn (): mixed => $plan188()['attempt_returning_count'], 6],
    'plan suppressed by rollback count' => [static fn (): mixed => $plan188()['suppressed_by_rollback_count'], 6],
    'plan attempt changes before rollback' => [static fn (): mixed => $plan188()['attempt_changes_before_rollback_to'], 6],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan188()['retry_statements'], 'action'), ['delete', 'update']],
    'plan retry statement phases' => [static fn (): mixed => array_column($plan188()['retry_statements'], 'phase'), ['retry-after-rollback', 'retry-after-rollback']],
    'plan retry delete selected original non autoload rows' => [static fn (): mixed => $plan188()['retry_statements'][0]['selected_ids'], [3, 4, 5]],
    'plan retry delete source values original' => [static fn (): mixed => array_column($plan188()['retry_statements'][0]['source_rows'], 'option_value'), ['feed', 'timeout', 'theme']],
    'plan retry update selected remaining autoload rows' => [static fn (): mixed => $plan188()['retry_statements'][1]['selected_ids'], [1, 2, 6]],
    'plan retry update source starts original' => [static fn (): mixed => array_column($plan188()['retry_statements'][1]['source_rows'], 'option_value'), ['https://old.test', 'https://home.test', 'rules']],
    'plan retry delete yielded ids' => [static fn (): mixed => array_column($plan188()['yielded_after_retry_returning'][0]['rows'], 'option_id'), [3, 4, 5]],
    'plan retry update yielded ids' => [static fn (): mixed => array_column($plan188()['yielded_after_retry_returning'][1]['rows'], 'option_id'), [1, 2, 6]],
    'plan yielded after retry count' => [static fn (): mixed => $plan188()['yielded_after_retry_count'], 6],
    'plan changes after retry release' => [static fn (): mixed => $plan188()['changes_after_retry_release'], 6],
    'plan final ids omit deleted non autoload rows' => [static fn (): mixed => array_column($plan188()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 6]],
    'plan final row one retry value' => [static fn (): mixed => array_column($plan188()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:retry188'],
    'plan final row six retry status' => [static fn (): mixed => array_column($plan188()['current_source_tables']['wp_options'], 'status', 'option_id')[6], 'retry188'],
    'plan next source equals current source' => [static fn (): mixed => $plan188()['next_source_tables'], $plan188()['current_source_tables']],
    'plan row count after retry' => [static fn (): mixed => $plan188()['row_counts']['wp_options'], 3],
    'plan changed tables after retry' => [static fn (): mixed => $plan188()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency empty in false' => [static fn (): mixed => in_array('sqlite-rowvalue-empty-in-list-is-false-next188', $plan188()['dependencies'], true), true],
    'plan dependency empty not in true' => [static fn (): mixed => in_array('sqlite-rowvalue-empty-not-in-list-is-true-next188', $plan188()['dependencies'], true), true],
    'plan dependency rollback current source' => [static fn (): mixed => in_array('sqlite-rowvalue-empty-in-returning-rollback-current-source-next188', $plan188()['dependencies'], true), true],

    'malformed empty attempts rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint($tables188, [], [$retryUpdate188]), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint($tables188, [$emptyDelete188], []), InvalidArgumentException::class],
    'malformed savepoint name rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint($tables188, [$emptyDelete188], [$retryUpdate188], 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(['wp_options' => ['bad']], [$emptyDelete188], [$retryUpdate188]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases188 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next188 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
