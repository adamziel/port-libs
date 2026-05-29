<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows212 = [
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

$meta212 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'blog_id' => 2],
    ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'blog_id' => 3],
    ['meta_id' => 103, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'blog_id' => 1],
    ['meta_id' => 104, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'blog_id' => 1],
    ['meta_id' => 105, 'meta_option_id' => null, 'meta_key' => 'cleanup_batch', 'meta_value' => null, 'blog_id' => 99],
    ['meta_id' => 106, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'blog_id' => 4],
];

$tables212 = ['wp_options' => $rows212, 'wp_optionmeta' => $meta212];
$unique212 = [['blog_id', 'option_name']];

$attemptUpdate212 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt212', option_value || ':attempt212', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') AS in_batch ORDER BY option_id";
$attemptDelete212 = "DELETE FROM wp_options WHERE (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch') RETURNING option_id, blog_id, option_name, status, (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch') AS kept_out ORDER BY option_id";
$retryUpdate212 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry212', option_value || ':retry212', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') RETURNING option_id, blog_id, option_name, status, option_value, in_batch AS invalid_alias ORDER BY option_id";
$retryUpdate212 = str_replace(', in_batch AS invalid_alias', ", (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') AS in_batch", $retryUpdate212);
$retryDelete212 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult212 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate212, $tables212, 'option_id', $unique212);
$attemptDeleteResult212 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete212, $attemptUpdateResult212()['tables'], 'option_id', $unique212);
$retryUpdateResult212 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate212, $tables212, 'option_id', $unique212);
$retryDeleteResult212 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete212, $retryUpdateResult212()['tables'], 'option_id', $unique212);
$plan212 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry(
    $tables212,
    [$attemptUpdate212, $attemptDelete212],
    [$retryUpdate212, $retryDelete212],
    $unique212,
);
$customPlan212 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry(
    $tables212,
    [$attemptUpdate212],
    [$retryUpdate212],
    $unique212,
    'wp_custom_rowvalue_subquery212',
);

$cases212 = [
    'parser keeps update subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate212)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch')"],
    'parser keeps delete subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptDelete212)['where'], "(option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch')"],
    'direct update selected subquery ids' => [static fn (): mixed => $attemptUpdateResult212()['plan']->selectedIds, [7, 8]],
    'direct update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult212()['returning'], 'option_id'), [7, 8]],
    'direct update returning subquery flags' => [static fn (): mixed => array_column($attemptUpdateResult212()['returning'], 'in_batch'), [1, 1]],
    'direct update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult212()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt212'],
    'direct delete not in subquery null poisons no match' => [static fn (): mixed => $attemptDeleteResult212()['plan']->selectedIds, []],
    'direct delete leaves all rows after null poisoned not in' => [static fn (): mixed => array_column($attemptDeleteResult212()['tables']['wp_options'], 'option_id'), range(1, 10)],
    'retry update selected rollback source ids' => [static fn (): mixed => $retryUpdateResult212()['plan']->selectedIds, [7, 8]],
    'retry update row seven starts from original source' => [static fn (): mixed => $retryUpdateResult212()['returning'][0]['option_value'], 'theme:retry212'],
    'retry delete selected network drop' => [static fn (): mixed => $retryDeleteResult212()['plan']->selectedIds, [10]],
    'retry delete removes network siteurl' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult212()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan212()['status'], 'rowvalue-update-delete-returning-subquery-savepoint-current-source-next212'],
    'plan savepoint' => [static fn (): mixed => $plan212()['savepoint'], 'wp_options_rowvalue_subquery_next212'],
    'plan rollback flags' => [static fn (): mixed => [$plan212()['rolled_back_to_savepoint'], $plan212()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan212()['retry_reads_savepoint_image'], $plan212()['savepoint_released_after_retry']], [true, true]],
    'plan savepoint image original row seven' => [static fn (): mixed => array_column($plan212()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($plan212()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt212'],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan212()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan212()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry212'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan212()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry212'],
    'plan final network drop removed' => [static fn (): mixed => in_array(10, array_column($plan212()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan212()['next_source_tables'], $plan212()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan212()['attempt_statements'], 'phase'), ['attempt-before-rollback-next212', 'attempt-before-rollback-next212']],
    'plan retry phases' => [static fn (): mixed => array_column($plan212()['retry_statements'], 'phase'), ['retry-after-rollback-next212', 'retry-after-rollback-next212']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan212()['attempt_statements'][0]['selected_ids'], $plan212()['attempt_statements'][1]['selected_ids']], [[7, 8], []]],
    'plan retry selected ids' => [static fn (): mixed => [$plan212()['retry_statements'][0]['selected_ids'], $plan212()['retry_statements'][1]['selected_ids']], [[7, 8], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan212()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules']],
    'plan discarded returning count' => [static fn (): mixed => $plan212()['discarded_attempt_returning_count'], 2],
    'plan yielded retry count' => [static fn (): mixed => $plan212()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan212()['attempt_changes_before_rollback'], 2],
    'plan retry changes count' => [static fn (): mixed => $plan212()['retry_changes_after_rollback'], 3],
    'plan row counts preserved metadata' => [static fn (): mixed => $plan212()['row_counts'], ['wp_optionmeta' => 6, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $plan212()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-in-select-subquery-next212', $plan212()['dependencies'], true), true],
    'plan dependency delete subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-not-in-select-subquery-next212', $plan212()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan212()['savepoint'], 'wp_custom_rowvalue_subquery212'],
    'custom yielded count' => [static fn (): mixed => $customPlan212()['yielded_after_retry_count'], 2],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate212, ['wp_options' => $rows212], 'option_id', $unique212), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry($tables212, [], [$retryUpdate212], $unique212), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry($tables212, [$attemptUpdate212], [], $unique212), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry($tables212, [$attemptUpdate212], [$retryUpdate212], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry($tables212, [$attemptUpdate212], [$retryUpdate212], $unique212, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry(['wp_options' => ['bad']], [$attemptUpdate212], [$retryUpdate212], $unique212), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases212 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next212 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
