<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows213 = [
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

$meta213 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 30],
    ['meta_id' => 103, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 20],
    ['meta_id' => 104, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 105, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 15],
    ['meta_id' => 106, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 40],
];

$tables213 = ['wp_options' => $rows213, 'wp_optionmeta' => $meta213];
$unique213 = [['blog_id', 'option_name']];

$attemptUpdate213 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt213', option_value || ':attempt213', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) AS in_ordered_batch ORDER BY option_id";
$attemptDelete213 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate213 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry213', option_value || ':retry213', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) AS in_retry_batch ORDER BY option_id DESC";
$retryDelete213 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult213 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate213, $tables213, 'option_id', $unique213);
$attemptDeleteResult213 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete213, $attemptUpdateResult213()['tables'], 'option_id', $unique213);
$retryUpdateResult213 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate213, $tables213, 'option_id', $unique213);
$retryDeleteResult213 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete213, $retryUpdateResult213()['tables'], 'option_id', $unique213);
$plan213 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint(
    $tables213,
    [$attemptUpdate213, $attemptDelete213],
    [$retryUpdate213, $retryDelete213],
    $unique213,
);
$customPlan213 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint(
    $tables213,
    [$attemptUpdate213],
    [$retryUpdate213],
    $unique213,
    'wp_custom_rowvalue_order_limit213',
);

$cases213 = [
    'parser keeps ordered limited update subquery' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate213)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2)"],
    'direct update selected highest priority ids' => [static fn (): mixed => $attemptUpdateResult213()['plan']->selectedIds, [8, 9]],
    'direct update returning ids sorted by statement order' => [static fn (): mixed => array_column($attemptUpdateResult213()['returning'], 'option_id'), [8, 9]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult213()['returning'], 'in_ordered_batch'), [1, 1]],
    'direct update excludes low priority row seven' => [static fn (): mixed => array_column($attemptUpdateResult213()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'direct delete selected lowest cleanup id' => [static fn (): mixed => $attemptDeleteResult213()['plan']->selectedIds, [3]],
    'direct delete keeps higher priority cleanup id' => [static fn (): mixed => in_array(4, array_column($attemptDeleteResult213()['tables']['wp_options'], 'option_id'), true), true],
    'retry update selected ascending priority ids' => [static fn (): mixed => $retryUpdateResult213()['plan']->selectedIds, [9, 7]],
    'retry update returning source order' => [static fn (): mixed => array_column($retryUpdateResult213()['returning'], 'option_id'), [7, 9]],
    'retry update starts row seven from original' => [static fn (): mixed => array_column($retryUpdateResult213()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry213'],
    'retry delete selected network row' => [static fn (): mixed => $retryDeleteResult213()['plan']->selectedIds, [10]],
    'retry delete removes network row' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult213()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan213()['status'], 'rowvalue-update-delete-returning-order-limit-subquery-savepoint-current-source-next213'],
    'plan savepoint' => [static fn (): mixed => $plan213()['savepoint'], 'wp_options_rowvalue_order_limit_next213'],
    'plan ordered limited source flag' => [static fn (): mixed => $plan213()['ordered_limited_subquery_source'], true],
    'plan rollback flags' => [static fn (): mixed => [$plan213()['rolled_back_to_savepoint'], $plan213()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan213()['retry_reads_savepoint_image'], $plan213()['savepoint_released_after_retry']], [true, true]],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan213()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt213'],
    'plan attempt row nine mutated' => [static fn (): mixed => array_column($plan213()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:attempt213'],
    'plan rollback restores row eight' => [static fn (): mixed => array_column($plan213()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan213()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry213'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan213()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry213'],
    'plan final network row removed' => [static fn (): mixed => in_array(10, array_column($plan213()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan213()['next_source_tables'], $plan213()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan213()['attempt_statements'][0]['selected_ids'], $plan213()['attempt_statements'][1]['selected_ids']], [[8, 9], [3]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan213()['retry_statements'][0]['selected_ids'], $plan213()['retry_statements'][1]['selected_ids']], [[9, 7], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan213()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'plugin']],
    'plan discarded returning count' => [static fn (): mixed => $plan213()['discarded_attempt_returning_count'], 3],
    'plan yielded retry count' => [static fn (): mixed => $plan213()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan213()['attempt_changes_before_rollback'], 3],
    'plan retry changes count' => [static fn (): mixed => $plan213()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $plan213()['row_counts'], ['wp_optionmeta' => 6, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $plan213()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update order limit' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-in-select-order-limit-next213', $plan213()['dependencies'], true), true],
    'plan dependency delete order limit' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-in-select-order-limit-next213', $plan213()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan213()['savepoint'], 'wp_custom_rowvalue_order_limit213'],
    'custom yielded count' => [static fn (): mixed => $customPlan213()['yielded_after_retry_count'], 2],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate213, ['wp_options' => $rows213], 'option_id', $unique213), InvalidArgumentException::class],
    'malformed bad order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('ORDER BY priority DESC', 'ORDER BY no_such_column DESC', $attemptUpdate213), $tables213, 'option_id', $unique213), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint($tables213, [], [$retryUpdate213], $unique213), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint($tables213, [$attemptUpdate213], [], $unique213), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint($tables213, [$attemptUpdate213], [$retryUpdate213], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint($tables213, [$attemptUpdate213], [$retryUpdate213], $unique213, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeOrderedLimitSubquerySavepoint(['wp_options' => ['bad']], [$attemptUpdate213], [$retryUpdate213], $unique213), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases213 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next213 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
