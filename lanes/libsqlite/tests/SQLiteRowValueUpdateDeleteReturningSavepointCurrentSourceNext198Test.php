<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows198 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'transient', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'transient', 'bytes' => 14, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 42, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 33, 'option_value' => 'network-plugins'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'transient', 'bytes' => 17, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rules', 'bytes' => 29, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bucket' => 'cache', 'bytes' => null, 'option_value' => 'cache'],
];

$tables198 = ['wp_options' => $rows198];
$attemptUpdate198 = "UPDATE wp_options SET status = 'between198', range_flag = bytes BETWEEN 20 AND 33, outside_flag = bytes NOT BETWEEN 20 AND 33, option_value = option_value || ':between198' WHERE bytes BETWEEN 20 AND 33 RETURNING option_id, blog_id, option_name, status, range_flag, outside_flag, bytes BETWEEN 20 AND 33 AS returning_between, bytes NOT BETWEEN 20 AND 33 AS returning_not_between ORDER BY option_id";
$attemptDelete198 = "DELETE FROM wp_options WHERE bytes NOT BETWEEN 10 AND 35 AND autoload = 'no' RETURNING option_id, option_name, bytes NOT BETWEEN 10 AND 35 AS outside_range ORDER BY option_id";
$retryUpdate198 = str_replace('between198', 'retry198', $attemptUpdate198);
$retryDelete198 = $attemptDelete198;

$attemptUpdateResult198 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate198, $tables198);
$attemptDeleteAfterUpdate198 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete198, $attemptUpdateResult198()['tables']);
$retryUpdateResult198 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate198, $tables198);
$retryDeleteAfterUpdate198 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete198, $retryUpdateResult198()['tables']);
$plan198 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    $tables198,
    [$attemptUpdate198, $attemptDelete198],
    [$retryUpdate198, $retryDelete198],
    'app_settings_rowvalue_between_savepoint_next198',
);

$cases198 = [
    'parser preserves update between where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate198)['where'], 'bytes BETWEEN 20 AND 33'],
    'parser preserves update between assignment' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate198)['assignments']['range_flag'], 'bytes BETWEEN 20 AND 33'],
    'parser preserves update not between assignment' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate198)['assignments']['outside_flag'], 'bytes NOT BETWEEN 20 AND 33'],
    'parser preserves delete not between where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptDelete198)['where'], "bytes NOT BETWEEN 10 AND 35 AND autoload = 'no'"],
    'attempt update selected inclusive range ids' => [static fn (): mixed => $attemptUpdateResult198()['plan']->selectedIds, [1, 2, 6, 8]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult198()['returning'], 'option_id'), [1, 2, 6, 8]],
    'attempt update assignment between flags true' => [static fn (): mixed => array_column($attemptUpdateResult198()['returning'], 'range_flag'), [1, 1, 1, 1]],
    'attempt update assignment not between flags false' => [static fn (): mixed => array_column($attemptUpdateResult198()['returning'], 'outside_flag'), [0, 0, 0, 0]],
    'attempt update returning between flags see new row' => [static fn (): mixed => array_column($attemptUpdateResult198()['returning'], 'returning_between'), [1, 1, 1, 1]],
    'attempt update returning not between flags false' => [static fn (): mixed => array_column($attemptUpdateResult198()['returning'], 'returning_not_between'), [0, 0, 0, 0]],
    'attempt update leaves low transient unchanged' => [static fn (): mixed => array_column($attemptUpdateResult198()['tables']['wp_options'], 'status', 'option_id')[3], 'stale'],
    'attempt update stores numeric flag on row six' => [static fn (): mixed => array_column($attemptUpdateResult198()['tables']['wp_options'], 'range_flag', 'option_id')[6], 1],
    'attempt update keeps null bytes row unselected' => [static fn (): mixed => array_key_exists('range_flag', array_column($attemptUpdateResult198()['tables']['wp_options'], null, 'option_id')[9]), false],
    'attempt delete selected only outside transient' => [static fn (): mixed => $attemptDeleteAfterUpdate198()['plan']->selectedIds, [3]],
    'attempt delete returning outside flag true' => [static fn (): mixed => $attemptDeleteAfterUpdate198()['returning'][0]['outside_range'], 1],
    'attempt delete keeps null bytes row because between is unknown' => [static fn (): mixed => in_array(9, array_column($attemptDeleteAfterUpdate198()['tables']['wp_options'], 'option_id'), true), true],
    'attempt delete removes low transient' => [static fn (): mixed => in_array(3, array_column($attemptDeleteAfterUpdate198()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected same inclusive range ids' => [static fn (): mixed => $retryUpdateResult198()['plan']->selectedIds, [1, 2, 6, 8]],
    'retry update starts from original option value' => [static fn (): mixed => $retryUpdateResult198()['returning'][0]['status'], 'retry198'],
    'retry delete selected same low transient' => [static fn (): mixed => $retryDeleteAfterUpdate198()['plan']->selectedIds, [3]],

    'plan status reuses current source rollback model' => [static fn (): mixed => $plan198()['status'], 'rowvalue-empty-in-returning-rolled-back-retried-next188'],
    'plan custom savepoint' => [static fn (): mixed => $plan198()['savepoint'], 'app_settings_rowvalue_between_savepoint_next198'],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan198()['rolled_back_to_savepoint'], true],
    'plan released after retry' => [static fn (): mixed => $plan198()['released_after_retry'], true],
    'plan attempt statement actions' => [static fn (): mixed => array_column($plan198()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan198()['retry_statements'], 'action'), ['update', 'delete']],
    'plan attempt update selected ids' => [static fn (): mixed => $plan198()['attempt_statements'][0]['selected_ids'], [1, 2, 6, 8]],
    'plan attempt delete selected ids' => [static fn (): mixed => $plan198()['attempt_statements'][1]['selected_ids'], [3]],
    'plan retry update selected ids' => [static fn (): mixed => $plan198()['retry_statements'][0]['selected_ids'], [1, 2, 6, 8]],
    'plan retry delete selected ids' => [static fn (): mixed => $plan198()['retry_statements'][1]['selected_ids'], [3]],
    'plan attempt returning count' => [static fn (): mixed => $plan198()['attempt_returning_count'], 5],
    'plan suppressed returning count' => [static fn (): mixed => $plan198()['suppressed_by_rollback_count'], 5],
    'plan yielded after retry count' => [static fn (): mixed => $plan198()['yielded_after_retry_count'], 5],
    'plan attempt changes before rollback' => [static fn (): mixed => $plan198()['attempt_changes_before_rollback_to'], 5],
    'plan retry changes after release' => [static fn (): mixed => $plan198()['changes_after_retry_release'], 5],
    'plan rollback restores original row one value' => [static fn (): mixed => array_column($plan198()['rollback_to_current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test'],
    'plan rollback restores deleted row three' => [static fn (): mixed => in_array(3, array_column($plan198()['rollback_to_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan attempt current row one attempted status' => [static fn (): mixed => array_column($plan198()['attempt_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'between198'],
    'plan attempt current omits deleted row three' => [static fn (): mixed => in_array(3, array_column($plan198()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row one retry status' => [static fn (): mixed => array_column($plan198()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'retry198'],
    'plan final ids omit row three' => [static fn (): mixed => array_column($plan198()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'plan final null bytes row kept' => [static fn (): mixed => in_array(9, array_column($plan198()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plan198()['next_source_tables'], $plan198()['current_source_tables']],
    'plan changed tables after retry' => [static fn (): mixed => $plan198()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan198()['row_counts']['wp_options'], 8],
    'plan keeps rollback dependency token' => [static fn (): mixed => in_array('sqlite-rowvalue-empty-in-returning-rollback-current-source-next188', $plan198()['dependencies'], true), true],
    'delete scalar between low bound inclusive' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE bytes BETWEEN 8 AND 8 RETURNING option_id", $tables198)['returning'], [['option_id' => 3]]],
    'delete scalar between high bound inclusive' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE bytes BETWEEN 42 AND 42 RETURNING option_id", $tables198)['returning'], [['option_id' => 5]]],
    'delete scalar not between excludes null as unknown' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE bytes NOT BETWEEN 1 AND 100 RETURNING option_id", $tables198)['returning'], []],
    'update scalar between can use column expression bounds' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET range_flag = bytes BETWEEN blog_id + 18 AND blog_id + 30 WHERE option_id = 8 RETURNING range_flag", $tables198)['returning'], [['range_flag' => 1]]],
    'malformed scalar between missing upper rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE bytes BETWEEN 1 RETURNING option_id", $tables198), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases198 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next198 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
