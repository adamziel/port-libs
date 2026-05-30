<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows214 = [
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
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
    ['option_id' => 12, 'blog_id' => 5, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 8, 'option_value' => 'cache'],
];

$meta214 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 30, 'blog_id' => 2],
    ['meta_id' => 102, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 50, 'blog_id' => 3],
    ['meta_id' => 103, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 40, 'blog_id' => 3],
    ['meta_id' => 104, 'meta_option_id' => 12, 'meta_key' => 'migration_batch', 'meta_value' => 'orphaned_cache', 'priority' => 10, 'blog_id' => 5],
    ['meta_id' => 105, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 20, 'blog_id' => 1],
    ['meta_id' => 106, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 10, 'blog_id' => 1],
    ['meta_id' => 107, 'meta_option_id' => null, 'meta_key' => 'cleanup_batch', 'meta_value' => null, 'priority' => 0, 'blog_id' => 99],
    ['meta_id' => 108, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 90, 'blog_id' => 4],
    ['meta_id' => 109, 'meta_option_id' => 11, 'meta_key' => 'network_drop', 'meta_value' => 'home', 'priority' => 70, 'blog_id' => 4],
    ['meta_id' => 110, 'meta_option_id' => 5, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 60, 'blog_id' => 2],
];

$tables214 = ['wp_options' => $rows214, 'wp_optionmeta' => $meta214];
$unique214 = [['blog_id', 'option_name']];

$attemptUpdate214 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt214', option_value || ':attempt214', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) AS picked_top ORDER BY option_id";
$attemptDelete214 = "DELETE FROM wp_options WHERE (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority DESC LIMIT 2) AND autoload = 'no' RETURNING option_id, blog_id, option_name, status, (option_id, option_name) NOT IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority DESC LIMIT 2) AS outside_cleanup ORDER BY option_id";
$retryUpdate214 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry214', option_value || ':retry214', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 1, 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 1, 2) AS picked_window ORDER BY option_id DESC";
$retryDelete214 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 2 OFFSET 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate214, $tables214, 'option_id', $unique214);
$attemptDeleteResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete214, $attemptUpdateResult214()['tables'], 'option_id', $unique214);
$retryUpdateResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate214, $tables214, 'option_id', $unique214);
$retryDeleteResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete214, $retryUpdateResult214()['tables'], 'option_id', $unique214);
$plan214 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(
    $tables214,
    [$attemptUpdate214, $attemptDelete214],
    [$retryUpdate214, $retryDelete214],
    $unique214,
);
$customPlan214 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(
    $tables214,
    [$attemptUpdate214],
    [$retryUpdate214],
    $unique214,
    'wp_custom_ordered_subquery214',
);

$cases214 = [
    'parser keeps ordered update subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate214)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2)"],
    'parser keeps comma limit subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate214)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 1, 2)"],
    'parser keeps offset subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete214)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT 2 OFFSET 1)"],
    'direct update selected top ids in table order' => [static fn (): mixed => $attemptUpdateResult214()['plan']->selectedIds, [8, 9]],
    'direct update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult214()['returning'], 'option_id'), [8, 9]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult214()['returning'], 'picked_top'), [1, 1]],
    'direct update excludes lower priority row seven' => [static fn (): mixed => array_column($attemptUpdateResult214()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult214()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt214'],
    'direct update row nine bytes' => [static fn (): mixed => array_column($attemptUpdateResult214()['tables']['wp_options'], 'bytes', 'option_id')[9], 15],
    'direct delete ordered cleanup excludes null poison' => [static fn (): mixed => $attemptDeleteResult214()['plan']->selectedIds, [7, 9, 12]],
    'direct delete returning outside cleanup flags' => [static fn (): mixed => array_column($attemptDeleteResult214()['returning'], 'outside_cleanup'), [1, 1, 1]],
    'direct delete leaves cleanup transient ids' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult214()['tables']['wp_options'], 'option_id'))), [3, 4]],
    'direct delete removes queued noautoload ids' => [static fn (): mixed => array_values(array_intersect([7, 9, 12], array_column($attemptDeleteResult214()['tables']['wp_options'], 'option_id'))), []],
    'retry update comma limit selected order' => [static fn (): mixed => $retryUpdateResult214()['plan']->selectedIds, [9, 7]],
    'retry update returning mutation order' => [static fn (): mixed => array_column($retryUpdateResult214()['returning'], 'option_id'), [7, 9]],
    'retry update returning flags' => [static fn (): mixed => array_column($retryUpdateResult214()['returning'], 'picked_window'), [1, 1]],
    'retry update row seven starts original' => [static fn (): mixed => array_column($retryUpdateResult214()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry214'],
    'retry update row eight not retried' => [static fn (): mixed => array_column($retryUpdateResult214()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'retry delete offset ids' => [static fn (): mixed => $retryDeleteResult214()['plan']->selectedIds, [5, 11]],
    'retry delete returning ids' => [static fn (): mixed => array_column($retryDeleteResult214()['returning'], 'option_id'), [5, 11]],
    'retry delete keeps highest priority network siteurl' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult214()['tables']['wp_options'], 'option_id'), true), true],
    'retry delete removes offset network rows' => [static fn (): mixed => array_values(array_intersect([5, 11], array_column($retryDeleteResult214()['tables']['wp_options'], 'option_id'))), []],

    'plan status' => [static fn (): mixed => $plan214()['status'], 'rowvalue-update-delete-returning-ordered-subquery-savepoint-current-source-next214'],
    'plan savepoint' => [static fn (): mixed => $plan214()['savepoint'], 'app_settings_rowvalue_ordered_subquery_next214'],
    'plan rollback flags' => [static fn (): mixed => [$plan214()['rolled_back_to_savepoint'], $plan214()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan ordered retry flags' => [static fn (): mixed => [$plan214()['ordered_subquery_limit_respected'], $plan214()['retry_reads_savepoint_image'], $plan214()['savepoint_released_after_retry']], [true, true, true]],
    'plan savepoint image row eight original' => [static fn (): mixed => array_column($plan214()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan214()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt214'],
    'plan attempt row seven deleted after second attempt' => [static fn (): mixed => in_array(7, array_column($plan214()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan214()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores row eight' => [static fn (): mixed => array_column($plan214()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan214()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry214'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan214()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry214'],
    'plan final row eight original' => [static fn (): mixed => array_column($plan214()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan final keeps row ten' => [static fn (): mixed => in_array(10, array_column($plan214()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final deletes row eleven' => [static fn (): mixed => in_array(11, array_column($plan214()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final deletes row five' => [static fn (): mixed => in_array(5, array_column($plan214()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan214()['next_source_tables'], $plan214()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan214()['attempt_statements'], 'phase'), ['attempt-ordered-subquery-before-rollback-next214', 'attempt-ordered-subquery-before-rollback-next214']],
    'plan retry phases' => [static fn (): mixed => array_column($plan214()['retry_statements'], 'phase'), ['retry-ordered-subquery-after-rollback-next214', 'retry-ordered-subquery-after-rollback-next214']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan214()['attempt_statements'][0]['selected_ids'], $plan214()['attempt_statements'][1]['selected_ids']], [[8, 9], [7, 9, 12]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan214()['retry_statements'][0]['selected_ids'], $plan214()['retry_statements'][1]['selected_ids']], [[9, 7], [5, 11]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan214()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'plugin']],
    'plan retry delete source names' => [static fn (): mixed => array_column($plan214()['retry_statements'][1]['source_rows'], 'option_name'), ['siteurl', 'home']],
    'plan discarded returning count' => [static fn (): mixed => $plan214()['discarded_attempt_returning_count'], 5],
    'plan yielded retry count' => [static fn (): mixed => $plan214()['yielded_after_retry_count'], 4],
    'plan attempt changes count' => [static fn (): mixed => $plan214()['attempt_changes_before_rollback'], 5],
    'plan retry changes count' => [static fn (): mixed => $plan214()['retry_changes_after_rollback'], 4],
    'plan row counts' => [static fn (): mixed => $plan214()['row_counts'], ['wp_optionmeta' => 10, 'wp_options' => 10]],
    'plan changed tables only options' => [static fn (): mixed => $plan214()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update ordered subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-in-select-order-limit-update-returning-next214', $plan214()['dependencies'], true), true],
    'plan dependency delete ordered subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-not-in-select-order-limit-delete-returning-next214', $plan214()['dependencies'], true), true],
    'plan dependency savepoint current source' => [static fn (): mixed => in_array('sqlite-rowvalue-ordered-subquery-savepoint-current-source-next214', $plan214()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan214()['savepoint'], 'wp_custom_ordered_subquery214'],
    'custom yielded count' => [static fn (): mixed => $customPlan214()['yielded_after_retry_count'], 2],
    'limit only subquery update selected ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'limit214' WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' LIMIT 1) RETURNING option_id ORDER BY option_id", $tables214, 'option_id', $unique214)['plan']->selectedIds, [7]],
    'order only subquery update selected ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'order214' WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority ASC) RETURNING option_id ORDER BY option_id", $tables214, 'option_id', $unique214)['plan']->selectedIds, [5, 10, 11]],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate214, ['wp_options' => $rows214], 'option_id', $unique214), InvalidArgumentException::class],
    'malformed subquery order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'bad214' WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY missing LIMIT 1) RETURNING option_id", $tables214, 'option_id', $unique214), InvalidArgumentException::class],
    'malformed subquery offset without limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'bad214' WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' OFFSET 1) RETURNING option_id", $tables214, 'option_id', $unique214), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [], [$retryUpdate214], $unique214), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [$attemptUpdate214], [], $unique214), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [$attemptUpdate214], [$retryUpdate214], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [$attemptUpdate214], [$retryUpdate214], $unique214, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(['wp_options' => ['bad']], [$attemptUpdate214], [$retryUpdate214], $unique214), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases214 as $name => [$callback, $expected]) {
    $tests['rowvalue ordered subquery savepoint retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
