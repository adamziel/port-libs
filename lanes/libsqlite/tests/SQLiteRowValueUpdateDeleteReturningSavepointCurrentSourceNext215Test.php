<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows215 = [
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

$meta215 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'blog_id' => 2, 'priority' => 5],
    ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'blog_id' => 3, 'priority' => 20],
    ['meta_id' => 103, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'blog_id' => 3, 'priority' => 30],
    ['meta_id' => 104, 'meta_option_id' => 10, 'meta_key' => 'migration_batch', 'meta_value' => 'siteurl', 'blog_id' => 4, 'priority' => 40],
    ['meta_id' => 105, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'blog_id' => 1, 'priority' => 1],
    ['meta_id' => 106, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'blog_id' => 1, 'priority' => 2],
    ['meta_id' => 107, 'meta_option_id' => null, 'meta_key' => 'cleanup_batch', 'meta_value' => null, 'blog_id' => 99, 'priority' => 99],
    ['meta_id' => 108, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'blog_id' => 4, 'priority' => 9],
];

$tables215 = ['wp_options' => $rows215, 'wp_optionmeta' => $meta215];
$unique215 = [['blog_id', 'option_name']];

$attemptUpdate215 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt215', option_value || ':attempt215', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2 OFFSET 1) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2 OFFSET 1) AS picked ORDER BY option_id";
$attemptDelete215 = "DELETE FROM wp_options WHERE (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 2) AS outside_cleanup ORDER BY option_id";
$retryUpdate215 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry215', option_value || ':retry215', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 3) AS retry_picked ORDER BY option_id";
$retryDelete215 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult215 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate215, $tables215, 'option_id', $unique215);
$attemptDeleteResult215 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete215, $attemptUpdateResult215()['tables'], 'option_id', $unique215);
$retryUpdateResult215 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate215, $tables215, 'option_id', $unique215);
$retryDeleteResult215 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete215, $retryUpdateResult215()['tables'], 'option_id', $unique215);
$plan215 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint($tables215, [$attemptUpdate215, $attemptDelete215], [$retryUpdate215, $retryDelete215], $unique215);
$customPlan215 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint($tables215, [$attemptUpdate215], [$retryUpdate215], $unique215, 'wp_custom_rowvalue_limit215');

$cases215 = [
    'parser keeps update ordered limited subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate215)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2 OFFSET 1)"],
    'parser keeps delete ordered limited subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptDelete215)['where'], "(option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 2)"],
    'direct update selected ordered limit ids' => [static fn (): mixed => $attemptUpdateResult215()['plan']->selectedIds, [8, 9]],
    'direct update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult215()['returning'], 'option_id'), [8, 9]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult215()['returning'], 'picked'), [1, 1]],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult215()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt215'],
    'direct update row nine value' => [static fn (): mixed => array_column($attemptUpdateResult215()['tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:attempt215'],
    'direct update skips highest offset row ten' => [static fn (): mixed => array_column($attemptUpdateResult215()['tables']['wp_options'], 'option_value', 'option_id')[10], 'https://four.test'],
    'direct update skips low priority row seven' => [static fn (): mixed => array_column($attemptUpdateResult215()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'direct delete selected ordered cleanup complement' => [static fn (): mixed => $attemptDeleteResult215()['plan']->selectedIds, [1, 2, 5, 6, 7, 8, 9, 10]],
    'direct delete returning outside cleanup flags' => [static fn (): mixed => array_unique(array_column($attemptDeleteResult215()['returning'], 'outside_cleanup')), [1]],
    'direct delete leaves limited cleanup ids' => [static fn (): mixed => array_column($attemptDeleteResult215()['tables']['wp_options'], 'option_id'), [3, 4]],
    'retry update selected top three ids' => [static fn (): mixed => $retryUpdateResult215()['plan']->selectedIds, [8, 9, 10]],
    'retry update row eight starts original' => [static fn (): mixed => $retryUpdateResult215()['returning'][0]['option_value'], 'rules:retry215'],
    'retry update row ten included without offset' => [static fn (): mixed => $retryUpdateResult215()['returning'][2]['option_value'], 'https://four.test:retry215'],
    'retry delete selected network drop' => [static fn (): mixed => $retryDeleteResult215()['plan']->selectedIds, [10]],
    'retry delete removes network siteurl' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult215()['tables']['wp_options'], 'option_id'), true), false],
    'retry delete keeps retry rows eight nine' => [static fn (): mixed => [array_column($retryDeleteResult215()['tables']['wp_options'], 'option_value', 'option_id')[8], array_column($retryDeleteResult215()['tables']['wp_options'], 'option_value', 'option_id')[9]], ['rules:retry215', 'plugin:retry215']],
    'plan status' => [static fn (): mixed => $plan215()['status'], 'rowvalue-update-delete-returning-subquery-limit-savepoint-current-source-next215'],
    'plan savepoint' => [static fn (): mixed => $plan215()['savepoint'], 'wp_options_rowvalue_subquery_limit_next215'],
    'plan rollback flags' => [static fn (): mixed => [$plan215()['rolled_back_to_savepoint'], $plan215()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan215()['retry_reads_savepoint_image'], $plan215()['savepoint_released_after_retry']], [true, true]],
    'plan savepoint image row eight original' => [static fn (): mixed => array_column($plan215()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan attempt deletes row eight after returning' => [static fn (): mixed => array_key_exists(8, array_column($plan215()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')), false],
    'plan attempt leaves only cleanup rows' => [static fn (): mixed => array_column($plan215()['attempt_current_source_tables']['wp_options'], 'option_id'), [3, 4]],
    'plan rollback restores row eight' => [static fn (): mixed => array_column($plan215()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan215()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry215'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan215()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry215'],
    'plan final network drop removed' => [static fn (): mixed => in_array(10, array_column($plan215()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan215()['next_source_tables'], $plan215()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan215()['attempt_statements'], 'phase'), ['attempt-before-rollback-next215', 'attempt-before-rollback-next215']],
    'plan retry phases' => [static fn (): mixed => array_column($plan215()['retry_statements'], 'phase'), ['retry-after-rollback-next215', 'retry-after-rollback-next215']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan215()['attempt_statements'][0]['selected_ids'], $plan215()['attempt_statements'][1]['selected_ids']], [[8, 9], [1, 2, 5, 6, 7, 8, 9, 10]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan215()['retry_statements'][0]['selected_ids'], $plan215()['retry_statements'][1]['selected_ids']], [[8, 9, 10], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan215()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin', 'https://four.test']],
    'plan discarded returning count' => [static fn (): mixed => $plan215()['discarded_attempt_returning_count'], 10],
    'plan yielded retry count' => [static fn (): mixed => $plan215()['yielded_after_retry_count'], 4],
    'plan attempt changes count' => [static fn (): mixed => $plan215()['attempt_changes_before_rollback'], 10],
    'plan retry changes count' => [static fn (): mixed => $plan215()['retry_changes_after_rollback'], 4],
    'plan row counts preserved metadata' => [static fn (): mixed => $plan215()['row_counts'], ['wp_optionmeta' => 8, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $plan215()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update ordered limit' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-in-select-order-limit-next215', $plan215()['dependencies'], true), true],
    'plan dependency delete ordered limit' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-not-in-select-order-limit-next215', $plan215()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan215()['savepoint'], 'wp_custom_rowvalue_limit215'],
    'custom yielded count' => [static fn (): mixed => $customPlan215()['yielded_after_retry_count'], 3],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate215, ['wp_options' => $rows215], 'option_id', $unique215), InvalidArgumentException::class],
    'malformed ordered subquery column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('ORDER BY priority DESC', 'ORDER BY missing_column DESC', $attemptUpdate215), $tables215, 'option_id', $unique215), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint($tables215, [], [$retryUpdate215], $unique215), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint($tables215, [$attemptUpdate215], [], $unique215), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint($tables215, [$attemptUpdate215], [$retryUpdate215], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint($tables215, [$attemptUpdate215], [$retryUpdate215], $unique215, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubqueryLimitSavepoint(['wp_options' => ['bad']], [$attemptUpdate215], [$retryUpdate215], $unique215), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases215 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next215 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
