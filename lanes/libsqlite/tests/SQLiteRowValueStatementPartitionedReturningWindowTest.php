<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';

$rowsStatementPartitioned = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tablesStatementPartitioned = ['wp_options' => $rowsStatementPartitioned];
$uniqueStatementPartitioned = [['blog_id', 'option_name']];
$attemptUpdateStatementPartitioned = "UPDATE wp_options SET status = 'attempt', option_value = option_value || ':attempt' WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (2, 'plugin_batch'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$attemptDeleteStatementPartitioned = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdateStatementPartitioned = "UPDATE wp_options SET status = 'retry', option_value = option_value || ':retry' WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'pending_theme'), (2, 'plugin_batch')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$retryDeleteStatementPartitioned = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status ORDER BY option_id";

$planStatementPartitioned = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry(
    $tablesStatementPartitioned,
    [$attemptUpdateStatementPartitioned, $attemptDeleteStatementPartitioned],
    [$retryUpdateStatementPartitioned, $retryDeleteStatementPartitioned],
    $uniqueStatementPartitioned,
);

$customPlanStatementPartitioned = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry(
    $tablesStatementPartitioned,
    [$attemptUpdateStatementPartitioned],
    [$retryUpdateStatementPartitioned],
    $uniqueStatementPartitioned,
    'wp_custom_statement_partitioned_window',
);

$casesStatementPartitioned = [
    'status' => [static fn (): mixed => $planStatementPartitioned()['status'], 'rowvalue-update-delete-returning-window-statement-partitioned'],
    'savepoint' => [static fn (): mixed => $planStatementPartitioned()['savepoint'], 'app_settings_rowvalue_statement_partitioned_window'],
    'rollback flags' => [static fn (): mixed => [$planStatementPartitioned()['rolled_back_to_savepoint'], $planStatementPartitioned()['savepoint_preserved_after_rollback_to']], [true, true]],
    'window flags' => [static fn (): mixed => [$planStatementPartitioned()['attempt_returning_window_suppressed_by_rollback'], $planStatementPartitioned()['retry_returning_window_yielded_from_current_source']], [true, true]],
    'attempt update selected ids' => [static fn (): mixed => $planStatementPartitioned()['attempt_statements'][0]['selected_ids'], [5, 6, 7]],
    'attempt delete selected ids' => [static fn (): mixed => $planStatementPartitioned()['attempt_statements'][1]['selected_ids'], [3, 4]],
    'retry update selected ids' => [static fn (): mixed => $planStatementPartitioned()['retry_statements'][0]['selected_ids'], [2, 5, 6]],
    'retry delete selected ids' => [static fn (): mixed => $planStatementPartitioned()['retry_statements'][1]['selected_ids'], [3, 4, 7]],
    'attempt returning count' => [static fn (): mixed => $planStatementPartitioned()['discarded_attempt_returning_count'], 5],
    'retry returning count' => [static fn (): mixed => $planStatementPartitioned()['yielded_after_retry_count'], 6],
    'retry statement window count' => [static fn (): mixed => $planStatementPartitioned()['yielded_retry_statement_window_count'], 6],
    'attempt changes count' => [static fn (): mixed => $planStatementPartitioned()['attempt_changes_before_rollback'], 5],
    'retry changes count' => [static fn (): mixed => $planStatementPartitioned()['retry_changes_after_rollback'], 6],
    'attempt window row numbers' => [static fn (): mixed => array_column($planStatementPartitioned()['discarded_attempt_window_rows'], 'row_number'), [1, 2, 3, 4, 5]],
    'attempt window rowids' => [static fn (): mixed => array_column($planStatementPartitioned()['discarded_attempt_window_rows'], 'current_rowid'), [3, 4, 5, 6, 7]],
    'retry window rowids' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_window_rows'], 'current_rowid'), [2, 3, 4, 5, 6, 7]],
    'retry statement row numbers' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_statement_window_rows'], 'row_number_in_statement'), [1, 2, 3, 1, 2, 3]],
    'retry statement ordinals' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_statement_window_rows'], 'statement_ordinal'), [0, 0, 0, 1, 1, 1]],
    'retry statement actions' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_statement_window_rows'], 'action'), ['update', 'update', 'update', 'delete', 'delete', 'delete']],
    'retry statement previous rowids' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_statement_window_rows'], 'previous_rowid_in_statement'), [null, 2, 5, null, 3, 4]],
    'retry statement next rowids' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_statement_window_rows'], 'next_rowid_in_statement'), [5, 6, null, 4, 7, null]],
    'retry statement peer counts' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_statement_window_rows'], 'statement_peer_count'), [3, 3, 3, 3, 3, 3]],
    'retry window statuses' => [static fn (): mixed => array_column($planStatementPartitioned()['yielded_retry_window_rows'], 'status'), ['retry', 'stale', 'stale', 'retry', 'retry', 'queued']],
    'attempt table rolled back row six' => [static fn (): mixed => array_column($planStatementPartitioned()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'plugin'],
    'final row two retried' => [static fn (): mixed => array_column($planStatementPartitioned()['current_source_tables']['wp_options'], 'option_value', 'option_id')[2], 'https://home.test:retry'],
    'final row six retried' => [static fn (): mixed => array_column($planStatementPartitioned()['current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'plugin:retry'],
    'final row three deleted' => [static fn (): mixed => in_array(3, array_column($planStatementPartitioned()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'final row four deleted' => [static fn (): mixed => in_array(4, array_column($planStatementPartitioned()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'final row seven deleted' => [static fn (): mixed => in_array(7, array_column($planStatementPartitioned()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'next source equals current source' => [static fn (): mixed => $planStatementPartitioned()['next_source_tables'], $planStatementPartitioned()['current_source_tables']],
    'changed tables' => [static fn (): mixed => $planStatementPartitioned()['changed_tables_after_retry'], ['wp_options']],
    'row counts' => [static fn (): mixed => $planStatementPartitioned()['row_counts'], ['wp_options' => 4]],
    'window order columns' => [static fn (): mixed => $planStatementPartitioned()['window_order_columns'], ['option_id']],
    'dependency update window' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-window-statement-partitioned', $planStatementPartitioned()['dependencies'], true), true],
    'dependency delete window' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-window-statement-partitioned', $planStatementPartitioned()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($planStatementPartitioned()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($planStatementPartitioned()['non_overlap'], 'all-stream windows'), true],
    'custom savepoint' => [static fn (): mixed => $customPlanStatementPartitioned()['savepoint'], 'wp_custom_statement_partitioned_window'],
    'custom retry count' => [static fn (): mixed => $customPlanStatementPartitioned()['yielded_after_retry_count'], 3],
    'malformed empty attempts rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry($tablesStatementPartitioned, [], [$retryUpdateStatementPartitioned], $uniqueStatementPartitioned), InvalidArgumentException::class],
    'malformed empty retries rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry($tablesStatementPartitioned, [$attemptUpdateStatementPartitioned], [], $uniqueStatementPartitioned), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry($tablesStatementPartitioned, [$attemptUpdateStatementPartitioned], [$retryUpdateStatementPartitioned], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry($tablesStatementPartitioned, [$attemptUpdateStatementPartitioned], [$retryUpdateStatementPartitioned], $uniqueStatementPartitioned, 'bad-name'), InvalidArgumentException::class],
    'malformed row rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementPartitionedReturningWindowSavepointRetry(['wp_options' => ['bad']], [$attemptUpdateStatementPartitioned], [$retryUpdateStatementPartitioned], $uniqueStatementPartitioned), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesStatementPartitioned as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window statement partitioned ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
