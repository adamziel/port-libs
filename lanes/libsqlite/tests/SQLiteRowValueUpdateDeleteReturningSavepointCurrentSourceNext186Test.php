<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows186 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bytes' => 31, 'option_value' => 'a:0:{}'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bytes' => 32, 'option_value' => 'rules'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 8, 'option_value' => 'cache'],
];

$tables186 = ['wp_options' => $rows186];
$unique186 = [['blog_id', 'option_name']];

$emptyInDelete186 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN () RETURNING option_id, (blog_id, option_name) IN () AS empty_in, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id";
$emptyNotInDelete186 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN () RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN () AS empty_in, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id LIMIT 2";
$outerUpdate186 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer186', option_value || ':outer186', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$attemptDelete186 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN () RETURNING option_id, (blog_id, option_name) IN () AS empty_in ORDER BY option_id";
$attemptUpdate186 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt186', option_value || ':attempt186', bytes + 10) WHERE (blog_id, option_name) NOT IN () RETURNING option_id, option_name, status, option_value, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id LIMIT 3";
$retryDelete186 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN () RETURNING option_id, option_name, status, (blog_id, option_name) IN () AS empty_in, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id LIMIT 2";
$retryUpdate186 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry186', option_value || ':retry186', bytes + 5) WHERE (blog_id, option_name) NOT IN () RETURNING option_id, option_name, status, option_value, (blog_id, option_name) NOT IN () AS empty_not_in ORDER BY option_id LIMIT 2";

$emptyInDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($emptyInDelete186, $tables186, 'option_id', $unique186);
$emptyNotInDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($emptyNotInDelete186, $tables186, 'option_id', $unique186);
$outerUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate186, $tables186, 'option_id', $unique186);
$attemptUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate186, $outerUpdate()['tables'], 'option_id', $unique186);
$retryDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete186, $outerUpdate()['tables'], 'option_id', $unique186);
$retryUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate186, $retryDelete()['tables'], 'option_id', $unique186);
$plan186 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry(
    $tables186,
    [$outerUpdate186],
    [$attemptDelete186, $attemptUpdate186],
    [$retryDelete186, $retryUpdate186],
    $unique186,
);
$customPlan186 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry(
    $tables186,
    [$outerUpdate186],
    [$attemptUpdate186],
    [$retryUpdate186],
    $unique186,
    'wp_outer_empty_in_custom186',
    'wp_inner_empty_in_custom186',
);

$cases186 = [
    'parser empty in where retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($emptyInDelete186)['where'], '(blog_id, option_name) IN ()'],
    'parser empty not in where retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($emptyNotInDelete186)['where'], '(blog_id, option_name) NOT IN ()'],
    'parser returning keeps empty expressions' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($emptyNotInDelete186)['returning'], 'NOT IN ()'), true],
    'empty row value in selects no rows' => [static fn (): mixed => $emptyInDelete()['plan']->selectedIds, []],
    'empty row value in returns no rows' => [static fn (): mixed => $emptyInDelete()['returning'], []],
    'empty row value in leaves all rows' => [static fn (): mixed => array_column($emptyInDelete()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'empty row value not in selects first limited rows' => [static fn (): mixed => $emptyNotInDelete()['plan']->selectedIds, [1, 2]],
    'empty row value not in returns ids' => [static fn (): mixed => array_column($emptyNotInDelete()['returning'], 'option_id'), [1, 2]],
    'empty row value not in expression true' => [static fn (): mixed => array_column($emptyNotInDelete()['returning'], 'empty_not_in'), [1, 1]],
    'empty row value in expression false' => [static fn (): mixed => array_column($emptyNotInDelete()['returning'], 'empty_in'), [0, 0]],
    'empty not in includes nullable tuple rows when not limited' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) NOT IN () RETURNING option_id ORDER BY option_id", $tables186, 'option_id', $unique186)['plan']->selectedIds, [1, 2, 3, 4, 5, 6, 7, 8]],
    'returning empty not in true for nullable tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 5 RETURNING (blog_id, status) NOT IN () AS empty_not_in", $tables186, 'option_id', $unique186)['returning'][0]['empty_not_in'], 1],
    'returning empty in false for nullable tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 5 RETURNING (blog_id, status) IN () AS empty_in", $tables186, 'option_id', $unique186)['returning'][0]['empty_in'], 0],
    'outer update selected ids' => [static fn (): mixed => $outerUpdate()['plan']->selectedIds, [7, 8]],
    'outer update returns staged rows' => [static fn (): mixed => array_column($outerUpdate()['returning'], 'status'), ['outer186', 'outer186']],
    'attempt update not in selects from outer current' => [static fn (): mixed => $attemptUpdate()['plan']->selectedIds, [1, 2, 3]],
    'attempt update returning empty not in true' => [static fn (): mixed => array_column($attemptUpdate()['returning'], 'empty_not_in'), [1, 1, 1]],
    'attempt update mutates row one' => [static fn (): mixed => array_column($attemptUpdate()['tables']['wp_options'], 'status', 'option_id')[1], 'attempt186'],
    'retry delete after outer source selects first two' => [static fn (): mixed => $retryDelete()['plan']->selectedIds, [1, 2]],
    'retry delete after outer source leaves row seven' => [static fn (): mixed => array_column($retryDelete()['tables']['wp_options'], 'status', 'option_id')[7], 'outer186'],
    'retry update after delete selects next rows' => [static fn (): mixed => $retryUpdate()['plan']->selectedIds, [3, 4]],
    'retry update values start from original rows' => [static fn (): mixed => array_column($retryUpdate()['returning'], 'option_value'), ['feed:retry186', 'timeout:retry186']],

    'plan status' => [static fn (): mixed => $plan186()['status'], 'empty-rowvalue-in-savepoint-current-source-retry-next186'],
    'plan outer savepoint name' => [static fn (): mixed => $plan186()['outer_savepoint'], 'wp_options_outer_empty_rowvalue_next186'],
    'plan inner savepoint name' => [static fn (): mixed => $plan186()['inner_savepoint'], 'wp_options_inner_empty_rowvalue_next186'],
    'plan rolled back to inner' => [static fn (): mixed => $plan186()['rolled_back_to_inner_savepoint'], true],
    'plan outer release flag' => [static fn (): mixed => $plan186()['outer_released_after_retry'], true],
    'plan discards attempt stream flag' => [static fn (): mixed => $plan186()['inner_rollback_discards_attempt_stream'], true],
    'plan outer image original' => [static fn (): mixed => $plan186()['outer_savepoint_image_tables'], $tables186],
    'plan outer current row seven staged' => [static fn (): mixed => array_column($plan186()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer186'],
    'plan inner image equals outer current' => [static fn (): mixed => $plan186()['inner_savepoint_image_tables'], $plan186()['outer_current_source_tables']],
    'plan attempt delete selects none' => [static fn (): mixed => $plan186()['attempt_statements'][0]['selected_ids'], []],
    'plan attempt update selects first three' => [static fn (): mixed => $plan186()['attempt_statements'][1]['selected_ids'], [1, 2, 3]],
    'plan attempt current row one changed' => [static fn (): mixed => array_column($plan186()['attempt_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'attempt186'],
    'plan rollback restores row one live' => [static fn (): mixed => array_column($plan186()['rollback_to_inner_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan rollback preserves outer row seven' => [static fn (): mixed => array_column($plan186()['rollback_to_inner_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer186'],
    'plan retry delete ids' => [static fn (): mixed => $plan186()['retry_statements'][0]['selected_ids'], [1, 2]],
    'plan retry update ids' => [static fn (): mixed => $plan186()['retry_statements'][1]['selected_ids'], [3, 4]],
    'plan current ids after retry' => [static fn (): mixed => array_column($plan186()['current_source_tables']['wp_options'], 'option_id'), [3, 4, 5, 6, 7, 8]],
    'plan current row three retry status' => [static fn (): mixed => array_column($plan186()['current_source_tables']['wp_options'], 'status', 'option_id')[3], 'retry186'],
    'plan current row seven keeps outer status' => [static fn (): mixed => array_column($plan186()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer186'],
    'plan next source equals current' => [static fn (): mixed => $plan186()['next_source_tables'], $plan186()['current_source_tables']],
    'plan outer statement phase' => [static fn (): mixed => $plan186()['outer_statements'][0]['phase'], 'outer-before-empty-rowvalue-rollback'],
    'plan attempt phases' => [static fn (): mixed => array_column($plan186()['attempt_statements'], 'phase'), ['attempt-empty-rowvalue-before-rollback', 'attempt-empty-rowvalue-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plan186()['retry_statements'], 'phase'), ['retry-empty-rowvalue-after-rollback', 'retry-empty-rowvalue-after-rollback']],
    'plan outer yielded ids' => [static fn (): mixed => array_column($plan186()['outer_yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan attempt delete returning empty' => [static fn (): mixed => $plan186()['attempt_returning_before_rollback'][0]['rows'], []],
    'plan attempt update returning ids' => [static fn (): mixed => array_column($plan186()['attempt_returning_before_rollback'][1]['rows'], 'option_id'), [1, 2, 3]],
    'plan suppressed stream equals attempt' => [static fn (): mixed => $plan186()['suppressed_by_rollback_returning'], $plan186()['attempt_returning_before_rollback']],
    'plan retry returning ids' => [static fn (): mixed => [array_column($plan186()['yielded_after_retry_returning'][0]['rows'], 'option_id'), array_column($plan186()['yielded_after_retry_returning'][1]['rows'], 'option_id')], [[1, 2], [3, 4]]],
    'plan counts outer' => [static fn (): mixed => $plan186()['outer_yielded_count'], 2],
    'plan counts attempt' => [static fn (): mixed => $plan186()['attempt_yielded_before_rollback_count'], 3],
    'plan counts suppressed' => [static fn (): mixed => $plan186()['suppressed_by_rollback_count'], 3],
    'plan counts retry' => [static fn (): mixed => $plan186()['yielded_after_retry_count'], 4],
    'plan changes outer' => [static fn (): mixed => $plan186()['outer_changes_preserved'], 2],
    'plan changes attempt' => [static fn (): mixed => $plan186()['attempted_changes_before_rollback'], 3],
    'plan changes retry' => [static fn (): mixed => $plan186()['retry_changes_after_rollback'], 4],
    'plan changed tables' => [static fn (): mixed => $plan186()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan186()['row_counts']['wp_options'], 6],
    'plan dependency empty in' => [static fn (): mixed => in_array('sqlite-rowvalue-empty-in-false-not-in-true-next186', $plan186()['dependencies'], true), true],
    'plan dependency nullable not in' => [static fn (): mixed => in_array('sqlite-empty-rowvalue-not-in-selects-null-tuples-next186', $plan186()['dependencies'], true), true],
    'plan dependency rollback' => [static fn (): mixed => in_array('sqlite-empty-rowvalue-rollback-discards-attempt-returning-next186', $plan186()['dependencies'], true), true],
    'custom savepoint names' => [static fn (): mixed => [$customPlan186()['outer_savepoint'], $customPlan186()['inner_savepoint']], ['wp_outer_empty_in_custom186', 'wp_inner_empty_in_custom186']],
    'custom retry count' => [static fn (): mixed => $customPlan186()['yielded_after_retry_count'], 2],
    'custom keeps row one because no retry delete' => [static fn (): mixed => array_column($customPlan186()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'retry186'],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry($tables186, [], [$attemptUpdate186], [$retryUpdate186], $unique186), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry($tables186, [$outerUpdate186], [], [$retryUpdate186], $unique186), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry($tables186, [$outerUpdate186], [$attemptUpdate186], [], $unique186), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry($tables186, [$outerUpdate186], [$attemptUpdate186], [$retryUpdate186], []), InvalidArgumentException::class],
    'malformed same savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry($tables186, [$outerUpdate186], [$attemptUpdate186], [$retryUpdate186], $unique186, 'same', 'same'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeEmptyRowValueInSavepointRetry(['wp_options' => ['bad']], [$outerUpdate186], [$attemptUpdate186], [$retryUpdate186], $unique186), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases186 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next186 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
