<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan;

require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan.php';

$rows290293 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables290293 = ['wp_options' => $rows290293];
$unique290293 = [['blog_id', 'option_name']];
$attemptUpdate290293 = "UPDATE wp_options SET status = 'attempt290293', option_value = option_value || ':attempt290293' WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (2, 'plugin_batch'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$attemptDelete290293 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdate290293 = "UPDATE wp_options SET status = 'retry290293', option_value = option_value || ':retry290293' WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'pending_theme'), (2, 'plugin_batch')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$retryDelete290293 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status ORDER BY option_id";

$plan290293 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute(
    $tables290293,
    [$attemptUpdate290293, $attemptDelete290293],
    [$retryUpdate290293, $retryDelete290293],
    $unique290293,
);

$customPlan290293 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute(
    $tables290293,
    [$attemptUpdate290293],
    [$retryUpdate290293],
    $unique290293,
    'wp_custom_window_next290293',
);

$cases290293 = [
    'status' => [static fn (): mixed => $plan290293()['status'], 'rowvalue-update-delete-returning-window-current-source-next290293'],
    'savepoint' => [static fn (): mixed => $plan290293()['savepoint'], 'wp_options_rowvalue_window_current_source_next290293'],
    'rollback flags' => [static fn (): mixed => [$plan290293()['rolled_back_to_savepoint'], $plan290293()['savepoint_preserved_after_rollback_to']], [true, true]],
    'window flags' => [static fn (): mixed => [$plan290293()['attempt_returning_window_suppressed_by_rollback'], $plan290293()['retry_returning_window_yielded_from_current_source']], [true, true]],
    'attempt update selected ids' => [static fn (): mixed => $plan290293()['attempt_statements'][0]['selected_ids'], [5, 6, 7]],
    'attempt delete selected ids' => [static fn (): mixed => $plan290293()['attempt_statements'][1]['selected_ids'], [3, 4]],
    'retry update selected ids' => [static fn (): mixed => $plan290293()['retry_statements'][0]['selected_ids'], [2, 5, 6]],
    'retry delete selected ids' => [static fn (): mixed => $plan290293()['retry_statements'][1]['selected_ids'], [3, 4, 7]],
    'attempt returning count' => [static fn (): mixed => $plan290293()['discarded_attempt_returning_count'], 5],
    'retry returning count' => [static fn (): mixed => $plan290293()['yielded_after_retry_count'], 6],
    'retry statement window count' => [static fn (): mixed => $plan290293()['yielded_retry_statement_window_count'], 6],
    'attempt changes count' => [static fn (): mixed => $plan290293()['attempt_changes_before_rollback'], 5],
    'retry changes count' => [static fn (): mixed => $plan290293()['retry_changes_after_rollback'], 6],
    'attempt window row numbers' => [static fn (): mixed => array_column($plan290293()['discarded_attempt_window_rows'], 'row_number'), [1, 2, 3, 4, 5]],
    'attempt window rowids' => [static fn (): mixed => array_column($plan290293()['discarded_attempt_window_rows'], 'current_rowid'), [3, 4, 5, 6, 7]],
    'retry window rowids' => [static fn (): mixed => array_column($plan290293()['yielded_retry_window_rows'], 'current_rowid'), [2, 3, 4, 5, 6, 7]],
    'retry statement row numbers' => [static fn (): mixed => array_column($plan290293()['yielded_retry_statement_window_rows'], 'row_number_in_statement'), [1, 2, 3, 1, 2, 3]],
    'retry statement ordinals' => [static fn (): mixed => array_column($plan290293()['yielded_retry_statement_window_rows'], 'statement_ordinal'), [0, 0, 0, 1, 1, 1]],
    'retry statement actions' => [static fn (): mixed => array_column($plan290293()['yielded_retry_statement_window_rows'], 'action'), ['update', 'update', 'update', 'delete', 'delete', 'delete']],
    'retry statement previous rowids' => [static fn (): mixed => array_column($plan290293()['yielded_retry_statement_window_rows'], 'previous_rowid_in_statement'), [null, 2, 5, null, 3, 4]],
    'retry statement next rowids' => [static fn (): mixed => array_column($plan290293()['yielded_retry_statement_window_rows'], 'next_rowid_in_statement'), [5, 6, null, 4, 7, null]],
    'retry statement peer counts' => [static fn (): mixed => array_column($plan290293()['yielded_retry_statement_window_rows'], 'statement_peer_count'), [3, 3, 3, 3, 3, 3]],
    'retry window statuses' => [static fn (): mixed => array_column($plan290293()['yielded_retry_window_rows'], 'status'), ['retry290293', 'stale', 'stale', 'retry290293', 'retry290293', 'queued']],
    'attempt table rolled back row six' => [static fn (): mixed => array_column($plan290293()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'plugin'],
    'final row two retried' => [static fn (): mixed => array_column($plan290293()['current_source_tables']['wp_options'], 'option_value', 'option_id')[2], 'https://home.test:retry290293'],
    'final row six retried' => [static fn (): mixed => array_column($plan290293()['current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'plugin:retry290293'],
    'final row three deleted' => [static fn (): mixed => in_array(3, array_column($plan290293()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'final row four deleted' => [static fn (): mixed => in_array(4, array_column($plan290293()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'final row seven deleted' => [static fn (): mixed => in_array(7, array_column($plan290293()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'next source equals current source' => [static fn (): mixed => $plan290293()['next_source_tables'], $plan290293()['current_source_tables']],
    'changed tables' => [static fn (): mixed => $plan290293()['changed_tables_after_retry'], ['wp_options']],
    'row counts' => [static fn (): mixed => $plan290293()['row_counts'], ['wp_options' => 4]],
    'window order columns' => [static fn (): mixed => $plan290293()['window_order_columns_next290293'], ['option_id']],
    'dependency update window' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-window-current-source-next290293', $plan290293()['dependencies'], true), true],
    'dependency delete window' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-window-current-source-next290293', $plan290293()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan290293()['dependency_closure_next290293'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan290293()['non_overlap_next290293'], 'next289 all-stream windows'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan290293()['savepoint'], 'wp_custom_window_next290293'],
    'custom retry count' => [static fn (): mixed => $customPlan290293()['yielded_after_retry_count'], 3],
    'malformed empty attempts rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute($tables290293, [], [$retryUpdate290293], $unique290293), InvalidArgumentException::class],
    'malformed empty retries rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute($tables290293, [$attemptUpdate290293], [], $unique290293), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute($tables290293, [$attemptUpdate290293], [$retryUpdate290293], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute($tables290293, [$attemptUpdate290293], [$retryUpdate290293], $unique290293, 'bad-name'), InvalidArgumentException::class],
    'malformed row rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan::execute(['wp_options' => ['bad']], [$attemptUpdate290293], [$retryUpdate290293], $unique290293), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases290293 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next290293 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
