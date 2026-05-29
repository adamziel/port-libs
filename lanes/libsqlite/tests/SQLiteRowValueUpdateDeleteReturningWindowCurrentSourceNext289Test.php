<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan;

require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan.php';

$rows289 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables289 = ['wp_options' => $rows289];
$unique289 = [['blog_id', 'option_name']];
$attemptUpdate289 = "UPDATE wp_options SET status = 'attempt289', option_value = option_value || ':attempt289' WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (2, 'plugin_batch'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$attemptDelete289 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdate289 = "UPDATE wp_options SET status = 'retry289', option_value = option_value || ':retry289' WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'pending_theme')) RETURNING option_id, option_name, status, option_value ORDER BY option_id";
$retryDelete289 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (3, 'rewrite_rules')) RETURNING option_id, option_name, status ORDER BY option_id";

$plan289 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute(
    $tables289,
    [$attemptUpdate289, $attemptDelete289],
    [$retryUpdate289, $retryDelete289],
    $unique289,
);

$customPlan289 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute(
    $tables289,
    [$attemptUpdate289],
    [$retryUpdate289],
    $unique289,
    'wp_custom_window_next289',
);

$cases289 = [
    'status' => [static fn (): mixed => $plan289()['status'], 'rowvalue-update-delete-returning-window-current-source-next289'],
    'savepoint' => [static fn (): mixed => $plan289()['savepoint'], 'wp_options_rowvalue_window_current_source_next289'],
    'rollback flags' => [static fn (): mixed => [$plan289()['rolled_back_to_savepoint'], $plan289()['savepoint_preserved_after_rollback_to']], [true, true]],
    'window flags' => [static fn (): mixed => [$plan289()['attempt_returning_window_suppressed_by_rollback'], $plan289()['retry_returning_window_yielded_from_current_source']], [true, true]],
    'attempt update selected ids' => [static fn (): mixed => $plan289()['attempt_statements'][0]['selected_ids'], [5, 6, 7]],
    'attempt delete selected ids' => [static fn (): mixed => $plan289()['attempt_statements'][1]['selected_ids'], [3, 4]],
    'retry update selected ids' => [static fn (): mixed => $plan289()['retry_statements'][0]['selected_ids'], [2, 5]],
    'retry delete selected ids' => [static fn (): mixed => $plan289()['retry_statements'][1]['selected_ids'], [4, 7]],
    'attempt returning count' => [static fn (): mixed => $plan289()['discarded_attempt_returning_count'], 5],
    'retry returning count' => [static fn (): mixed => $plan289()['yielded_after_retry_count'], 4],
    'attempt changes count' => [static fn (): mixed => $plan289()['attempt_changes_before_rollback'], 5],
    'retry changes count' => [static fn (): mixed => $plan289()['retry_changes_after_rollback'], 4],
    'attempt window row numbers' => [static fn (): mixed => array_column($plan289()['discarded_attempt_window_rows'], 'row_number'), [1, 2, 3, 4, 5]],
    'attempt window rowids' => [static fn (): mixed => array_column($plan289()['discarded_attempt_window_rows'], 'current_rowid'), [3, 4, 5, 6, 7]],
    'retry window rowids' => [static fn (): mixed => array_column($plan289()['yielded_retry_window_rows'], 'current_rowid'), [2, 4, 5, 7]],
    'retry window previous rowids' => [static fn (): mixed => array_column($plan289()['yielded_retry_window_rows'], 'previous_rowid'), [null, 2, 4, 5]],
    'retry window next rowids' => [static fn (): mixed => array_column($plan289()['yielded_retry_window_rows'], 'next_rowid'), [4, 5, 7, null]],
    'retry window peer counts' => [static fn (): mixed => array_column($plan289()['yielded_retry_window_rows'], 'peer_count'), [4, 4, 4, 4]],
    'retry window statuses' => [static fn (): mixed => array_column($plan289()['yielded_retry_window_rows'], 'status'), ['retry289', 'stale', 'retry289', 'queued']],
    'attempt table rolled back row six' => [static fn (): mixed => array_column($plan289()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'plugin'],
    'final row two retried' => [static fn (): mixed => array_column($plan289()['current_source_tables']['wp_options'], 'option_value', 'option_id')[2], 'https://home.test:retry289'],
    'final row five retried' => [static fn (): mixed => array_column($plan289()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'theme:retry289'],
    'final row four deleted' => [static fn (): mixed => in_array(4, array_column($plan289()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'final row seven deleted' => [static fn (): mixed => in_array(7, array_column($plan289()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'next source equals current source' => [static fn (): mixed => $plan289()['next_source_tables'], $plan289()['current_source_tables']],
    'changed tables' => [static fn (): mixed => $plan289()['changed_tables_after_retry'], ['wp_options']],
    'row counts' => [static fn (): mixed => $plan289()['row_counts'], ['wp_options' => 5]],
    'window order columns' => [static fn (): mixed => $plan289()['window_order_columns_next289'], ['option_id']],
    'dependency update window' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-window-current-source-next289', $plan289()['dependencies'], true), true],
    'dependency delete window' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-window-current-source-next289', $plan289()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan289()['dependency_closure_next289'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan289()['non_overlap_next289'], 'avoids accepted next219'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan289()['savepoint'], 'wp_custom_window_next289'],
    'custom retry count' => [static fn (): mixed => $customPlan289()['yielded_after_retry_count'], 2],
    'malformed empty attempts rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute($tables289, [], [$retryUpdate289], $unique289), InvalidArgumentException::class],
    'malformed empty retries rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute($tables289, [$attemptUpdate289], [], $unique289), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute($tables289, [$attemptUpdate289], [$retryUpdate289], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute($tables289, [$attemptUpdate289], [$retryUpdate289], $unique289, 'bad-name'), InvalidArgumentException::class],
    'malformed row rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext289Plan::execute(['wp_options' => ['bad']], [$attemptUpdate289], [$retryUpdate289], $unique289), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases289 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next289 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
