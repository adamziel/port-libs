<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows216 = [
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

$meta216 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 102, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 20],
    ['meta_id' => 103, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 30],
    ['meta_id' => 104, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 40],
    ['meta_id' => 105, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 106, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 15],
    ['meta_id' => 107, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 25],
    ['meta_id' => 108, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 35],
    ['meta_id' => 109, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 45],
];

$tables216 = ['wp_options' => $rows216, 'wp_optionmeta' => $meta216];
$unique216 = [['blog_id', 'option_name']];

$attemptUpdate216 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt216', option_value || ':attempt216', bytes + 4) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') AS in_distinct_batch ORDER BY option_id";
$attemptDelete216 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch') RETURNING option_id, blog_id, option_name, status, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch') AS cleanup_tuple ORDER BY option_id";
$retryUpdate216 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry216', option_value || ':retry216', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') AS in_retry_distinct ORDER BY option_id";
$retryDelete216 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult216 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate216, $tables216, 'option_id', $unique216);
$attemptDeleteResult216 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete216, $attemptUpdateResult216()['tables'], 'option_id', $unique216);
$retryUpdateResult216 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate216, $tables216, 'option_id', $unique216);
$retryDeleteResult216 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete216, $retryUpdateResult216()['tables'], 'option_id', $unique216);
$plan216 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint(
    $tables216,
    [$attemptUpdate216, $attemptDelete216],
    [$retryUpdate216, $retryDelete216],
    $unique216,
);
$customPlan216 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint(
    $tables216,
    [$attemptUpdate216],
    [$retryUpdate216],
    $unique216,
    'wp_custom_rowvalue_distinct216',
);

$cases216 = [
    'parser keeps distinct update subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate216)['where'], "(option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch')"],
    'parser keeps distinct delete subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptDelete216)['where'], "(option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch')"],
    'direct update selected distinct ids once' => [static fn (): mixed => $attemptUpdateResult216()['plan']->selectedIds, [7, 8]],
    'direct update mutation ids once' => [static fn (): mixed => $attemptUpdateResult216()['plan']->mutationIds, [7, 8]],
    'direct update returning ids once' => [static fn (): mixed => array_column($attemptUpdateResult216()['returning'], 'option_id'), [7, 8]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult216()['returning'], 'in_distinct_batch'), [1, 1]],
    'direct update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult216()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt216'],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult216()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt216'],
    'direct update ignores duplicate meta count' => [static fn (): mixed => count($attemptUpdateResult216()['returning']), 2],
    'direct delete selected distinct cleanup ids once' => [static fn (): mixed => $attemptDeleteResult216()['plan']->selectedIds, [3, 4]],
    'direct delete returning cleanup flags' => [static fn (): mixed => array_column($attemptDeleteResult216()['returning'], 'cleanup_tuple'), [1, 1]],
    'direct delete removes cleanup rows' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult216()['tables']['wp_options'], 'option_id'))), []],
    'retry update selected rollback source ids' => [static fn (): mixed => $retryUpdateResult216()['plan']->selectedIds, [7, 8]],
    'retry update row seven starts from original' => [static fn (): mixed => $retryUpdateResult216()['returning'][0]['option_value'], 'theme:retry216'],
    'retry update row eight starts from original' => [static fn (): mixed => $retryUpdateResult216()['returning'][1]['option_value'], 'rules:retry216'],
    'retry delete selected network row once' => [static fn (): mixed => $retryDeleteResult216()['plan']->selectedIds, [10]],
    'retry delete removes network row' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult216()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan216()['status'], 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source'],
    'plan savepoint' => [static fn (): mixed => $plan216()['savepoint'], 'wp_options_rowvalue_distinct_subquery'],
    'plan distinct flag' => [static fn (): mixed => $plan216()['distinct_subquery_source'], true],
    'plan rollback flags' => [static fn (): mixed => [$plan216()['rolled_back_to_savepoint'], $plan216()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan216()['retry_reads_savepoint_image'], $plan216()['savepoint_released_after_retry']], [true, true]],
    'plan savepoint image original row seven' => [static fn (): mixed => array_column($plan216()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($plan216()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt216'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan216()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt216'],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan216()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan216()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry216'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan216()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry216'],
    'plan final network row removed' => [static fn (): mixed => in_array(10, array_column($plan216()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan216()['next_source_tables'], $plan216()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan216()['attempt_statements'][0]['selected_ids'], $plan216()['attempt_statements'][1]['selected_ids']], [[7, 8], [3, 4]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan216()['retry_statements'][0]['selected_ids'], $plan216()['retry_statements'][1]['selected_ids']], [[7, 8], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan216()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules']],
    'plan discarded returning count' => [static fn (): mixed => $plan216()['discarded_attempt_returning_count'], 4],
    'plan yielded retry count' => [static fn (): mixed => $plan216()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan216()['attempt_changes_before_rollback'], 4],
    'plan retry changes count' => [static fn (): mixed => $plan216()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $plan216()['row_counts'], ['wp_optionmeta' => 9, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $plan216()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-in-select-distinct-subquery', $plan216()['dependencies'], true), true],
    'plan dependency delete distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-in-select-distinct-subquery', $plan216()['dependencies'], true), true],
    'plan dependency closure note' => [static fn (): mixed => $plan216()['dependency_closure'], 'no new support component needed; reuses native PHP row-value UPDATE/DELETE RETURNING, SELECT subquery tuple materialization, and savepoint current-source retry images'],
    'custom savepoint' => [static fn (): mixed => $customPlan216()['savepoint'], 'wp_custom_rowvalue_distinct216'],
    'custom yielded count' => [static fn (): mixed => $customPlan216()['yielded_after_retry_count'], 2],
    'malformed missing distinct subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate216, ['wp_options' => $rows216], 'option_id', $unique216), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint($tables216, [], [$retryUpdate216], $unique216), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint($tables216, [$attemptUpdate216], [], $unique216), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint($tables216, [$attemptUpdate216], [$retryUpdate216], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint($tables216, [$attemptUpdate216], [$retryUpdate216], $unique216, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctSubquerySavepoint(['wp_options' => ['bad']], [$attemptUpdate216], [$retryUpdate216], $unique216), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases216 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning distinct subquery savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
