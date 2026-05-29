<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows226 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta226 = [
    ['meta_id' => 201, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 202, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 11],
    ['meta_id' => 203, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 204, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 21],
    ['meta_id' => 205, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 206, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 207, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 6],
    ['meta_id' => 208, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 15],
    ['meta_id' => 209, 'meta_option_id' => 10, 'meta_key' => 'network_retry', 'meta_value' => 'network_plugin', 'priority' => 25],
    ['meta_id' => 210, 'meta_option_id' => 10, 'meta_key' => 'network_retry', 'meta_value' => 'network_plugin', 'priority' => 26],
];

$tables226 = ['wp_options' => $rows226, 'wp_optionmeta' => $meta226];
$unique226 = [['blog_id', 'option_name']];

$attemptUpdate226 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt226', option_value || ':attempt226', bytes + 4) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) AS in_distinct_batch ORDER BY option_id";
$attemptDelete226 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate226 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry226', option_value || ':retry226', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) AS in_retry_distinct ORDER BY option_id DESC";
$retryDelete226 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_retry' ORDER BY priority ASC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult226 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate226, $tables226, 'option_id', $unique226);
$attemptDeleteResult226 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete226, $attemptUpdateResult226()['tables'], 'option_id', $unique226);
$retryUpdateResult226 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate226, $tables226, 'option_id', $unique226);
$retryDeleteResult226 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete226, $retryUpdateResult226()['tables'], 'option_id', $unique226);
$plan226 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback(
    $tables226,
    [$attemptUpdate226, $attemptDelete226],
    [$retryUpdate226, $retryDelete226],
    $unique226,
);
$customPlan226 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback(
    $tables226,
    [$attemptUpdate226],
    [$retryUpdate226],
    $unique226,
    'wp_custom_rowvalue_distinct226',
);

$cases226 = [
    'parser keeps distinct update subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate226)['where'], 'SELECT DISTINCT'), true],
    'parser keeps distinct returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate226)['returning'], 'SELECT DISTINCT'), true],
    'parser retry order by desc retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate226)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct update selected distinct ids' => [static fn (): mixed => $attemptUpdateResult226()['plan']->selectedIds, [7, 8]],
    'direct update mutation ids distinct input order' => [static fn (): mixed => $attemptUpdateResult226()['plan']->mutationIds, [7, 8]],
    'direct update returning ids input order' => [static fn (): mixed => array_column($attemptUpdateResult226()['returning'], 'option_id'), [7, 8]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult226()['returning'], 'in_distinct_batch'), [1, 1]],
    'direct update skips third distinct tuple' => [static fn (): mixed => array_column($attemptUpdateResult226()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'direct update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult226()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt226'],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult226()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt226'],
    'direct delete selected first distinct cleanup row' => [static fn (): mixed => $attemptDeleteResult226()['plan']->selectedIds, [3]],
    'direct delete returning one duplicate collapsed row' => [static fn (): mixed => array_column($attemptDeleteResult226()['returning'], 'option_id'), [3]],
    'direct delete keeps second cleanup row' => [static fn (): mixed => in_array(4, array_column($attemptDeleteResult226()['tables']['wp_options'], 'option_id'), true), true],
    'direct delete removes first cleanup row' => [static fn (): mixed => in_array(3, array_column($attemptDeleteResult226()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected descending distinct ids' => [static fn (): mixed => $retryUpdateResult226()['plan']->selectedIds, [9, 8]],
    'retry update mutation input order' => [static fn (): mixed => $retryUpdateResult226()['plan']->mutationIds, [8, 9]],
    'retry update returning order from order by desc' => [static fn (): mixed => array_column($retryUpdateResult226()['returning'], 'option_id'), [8, 9]],
    'retry update row eight original prefix' => [static fn (): mixed => $retryUpdateResult226()['returning'][0]['option_value'], 'rules:retry226'],
    'retry update row nine original prefix' => [static fn (): mixed => $retryUpdateResult226()['returning'][1]['option_value'], 'plugin:retry226'],
    'retry update skips lowest priority row seven' => [static fn (): mixed => array_column($retryUpdateResult226()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'retry delete selected one distinct network row' => [static fn (): mixed => $retryDeleteResult226()['plan']->selectedIds, [10]],
    'retry delete removes network row once' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult226()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan226()['status'], 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next226'],
    'plan savepoint' => [static fn (): mixed => $plan226()['savepoint'], 'wp_options_rowvalue_distinct_subquery_next226'],
    'plan distinct flag' => [static fn (): mixed => $plan226()['distinct_subquery_source'], true],
    'plan rollback flags' => [static fn (): mixed => [$plan226()['rolled_back_to_savepoint'], $plan226()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan226()['retry_reads_savepoint_image'], $plan226()['savepoint_released_after_retry']], [true, true]],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($plan226()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt226'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan226()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt226'],
    'plan attempt row nine skipped' => [static fn (): mixed => array_column($plan226()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin'],
    'plan attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($plan226()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan226()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores cleanup row three' => [static fn (): mixed => in_array(3, array_column($plan226()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan226()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry226'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan226()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry226'],
    'plan final row seven restored not retried' => [static fn (): mixed => array_column($plan226()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan final network row removed' => [static fn (): mixed => in_array(10, array_column($plan226()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan226()['next_source_tables'], $plan226()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan226()['attempt_statements'][0]['selected_ids'], $plan226()['attempt_statements'][1]['selected_ids']], [[7, 8], [3]]],
    'plan attempt mutation ids' => [static fn (): mixed => [$plan226()['attempt_statements'][0]['mutation_ids'], $plan226()['attempt_statements'][1]['mutation_ids']], [[7, 8], [3]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan226()['retry_statements'][0]['selected_ids'], $plan226()['retry_statements'][1]['selected_ids']], [[9, 8], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan226()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin']],
    'plan discarded returning count' => [static fn (): mixed => $plan226()['discarded_attempt_returning_count'], 3],
    'plan yielded retry count' => [static fn (): mixed => $plan226()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan226()['attempt_changes_before_rollback'], 3],
    'plan retry changes count' => [static fn (): mixed => $plan226()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $plan226()['row_counts'], ['wp_optionmeta' => 10, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $plan226()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-distinct-select-subquery-next226', $plan226()['dependencies'], true), true],
    'plan dependency delete distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-distinct-select-subquery-next226', $plan226()['dependencies'], true), true],
    'plan dependency savepoint distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-distinct-subquery-savepoint-current-source-next226', $plan226()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan226()['dependency_closure_next226'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan226()['non_overlap_next226'], 'avoids accepted next219'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan226()['savepoint'], 'wp_custom_rowvalue_distinct226'],
    'custom yielded count' => [static fn (): mixed => $customPlan226()['yielded_after_retry_count'], 2],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate226, ['wp_options' => $rows226], 'option_id', $unique226), InvalidArgumentException::class],
    'malformed bad order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('ORDER BY priority ASC', 'ORDER BY no_such_column ASC', $attemptUpdate226), $tables226, 'option_id', $unique226), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback($tables226, [], [$retryUpdate226], $unique226), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback($tables226, [$attemptUpdate226], [], $unique226), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback($tables226, [$attemptUpdate226], [$retryUpdate226], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback($tables226, [$attemptUpdate226], [$retryUpdate226], $unique226, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeBoundedDistinctSubquerySavepointRollback(['wp_options' => ['bad']], [$attemptUpdate226], [$retryUpdate226], $unique226), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases226 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next226 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
