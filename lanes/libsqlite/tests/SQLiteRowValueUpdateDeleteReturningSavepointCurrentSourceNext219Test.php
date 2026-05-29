<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows219 = [
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
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => '_transient_cache', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'cache'],
    ['option_id' => 12, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta219 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 102, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 20],
    ['meta_id' => 103, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 30],
    ['meta_id' => 104, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 105, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 15],
    ['meta_id' => 106, 'meta_option_id' => 11, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_cache', 'priority' => 25],
    ['meta_id' => 107, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 40],
    ['meta_id' => 108, 'meta_option_id' => 12, 'meta_key' => 'network_drop', 'meta_value' => 'network_plugin', 'priority' => 35],
];

$tables219 = ['wp_options' => $rows219, 'wp_optionmeta' => $meta219];
$unique219 = [['blog_id', 'option_name']];

$attemptUpdate219 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt219', option_value || ':attempt219', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT -1 OFFSET 1) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT -1 OFFSET 1) AS in_negative_limit_batch ORDER BY option_id";
$attemptDelete219 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT -1 OFFSET 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate219 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry219', option_value || ':retry219', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT -1 OFFSET 1) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT -1 OFFSET 1) AS in_retry_batch ORDER BY option_id DESC";
$retryDelete219 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY priority DESC LIMIT -1 OFFSET 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult219 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate219, $tables219, 'option_id', $unique219);
$attemptDeleteResult219 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete219, $attemptUpdateResult219()['tables'], 'option_id', $unique219);
$retryUpdateResult219 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate219, $tables219, 'option_id', $unique219);
$retryDeleteResult219 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete219, $retryUpdateResult219()['tables'], 'option_id', $unique219);
$plan219 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219(
    $tables219,
    [$attemptUpdate219, $attemptDelete219],
    [$retryUpdate219, $retryDelete219],
    $unique219,
);
$customPlan219 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219(
    $tables219,
    [$attemptUpdate219],
    [$retryUpdate219],
    $unique219,
    'wp_custom_rowvalue_negative_limit219',
);

$cases219 = [
    'parser keeps negative limit offset update subquery' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate219)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT -1 OFFSET 1)"],
    'parser keeps negative limit offset returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate219)['returning'], 'LIMIT -1 OFFSET 1'), true],
    'parser retry order by desc retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate219)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct update selected after offset ids' => [static fn (): mixed => $attemptUpdateResult219()['plan']->selectedIds, [8, 9]],
    'direct update mutation ids input order' => [static fn (): mixed => $attemptUpdateResult219()['plan']->mutationIds, [8, 9]],
    'direct update returning ids input order' => [static fn (): mixed => array_column($attemptUpdateResult219()['returning'], 'option_id'), [8, 9]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult219()['returning'], 'in_negative_limit_batch'), [1, 1]],
    'direct update skips offset row seven' => [static fn (): mixed => array_column($attemptUpdateResult219()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'direct update mutates row eight' => [static fn (): mixed => array_column($attemptUpdateResult219()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt219'],
    'direct update mutates row nine' => [static fn (): mixed => array_column($attemptUpdateResult219()['tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:attempt219'],
    'direct delete selected after cleanup offset' => [static fn (): mixed => $attemptDeleteResult219()['plan']->selectedIds, [4, 11]],
    'direct delete returning ids' => [static fn (): mixed => array_column($attemptDeleteResult219()['returning'], 'option_id'), [4, 11]],
    'direct delete keeps skipped cleanup row' => [static fn (): mixed => in_array(3, array_column($attemptDeleteResult219()['tables']['wp_options'], 'option_id'), true), true],
    'direct delete removes offset cleanup rows' => [static fn (): mixed => array_values(array_intersect([4, 11], array_column($attemptDeleteResult219()['tables']['wp_options'], 'option_id'))), []],
    'retry update selected descending offset ids' => [static fn (): mixed => $retryUpdateResult219()['plan']->selectedIds, [9, 7]],
    'retry update mutation input order' => [static fn (): mixed => $retryUpdateResult219()['plan']->mutationIds, [7, 9]],
    'retry update returning order from order by desc' => [static fn (): mixed => array_column($retryUpdateResult219()['returning'], 'option_id'), [7, 9]],
    'retry update row seven original prefix' => [static fn (): mixed => $retryUpdateResult219()['returning'][0]['option_value'], 'theme:retry219'],
    'retry update row nine original prefix' => [static fn (): mixed => $retryUpdateResult219()['returning'][1]['option_value'], 'plugin:retry219'],
    'retry update skips highest priority row eight' => [static fn (): mixed => array_column($retryUpdateResult219()['tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'retry delete selected second network row' => [static fn (): mixed => $retryDeleteResult219()['plan']->selectedIds, [12]],
    'retry delete removes second network row' => [static fn (): mixed => in_array(12, array_column($retryDeleteResult219()['tables']['wp_options'], 'option_id'), true), false],
    'retry delete keeps first network row' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult219()['tables']['wp_options'], 'option_id'), true), true],

    'plan status' => [static fn (): mixed => $plan219()['status'], 'rowvalue-update-delete-returning-negative-limit-offset-subquery-savepoint-current-source-next219'],
    'plan savepoint' => [static fn (): mixed => $plan219()['savepoint'], 'wp_options_rowvalue_negative_limit_offset_next219'],
    'plan negative limit flag' => [static fn (): mixed => $plan219()['negative_limit_offset_subquery_source'], true],
    'plan rollback flags' => [static fn (): mixed => [$plan219()['rolled_back_to_savepoint'], $plan219()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan219()['retry_reads_savepoint_image'], $plan219()['savepoint_released_after_retry']], [true, true]],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan219()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt219'],
    'plan attempt row nine mutated' => [static fn (): mixed => array_column($plan219()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:attempt219'],
    'plan attempt row four deleted' => [static fn (): mixed => in_array(4, array_column($plan219()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt row eleven deleted' => [static fn (): mixed => in_array(11, array_column($plan219()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row eight' => [static fn (): mixed => array_column($plan219()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan rollback restores cleanup row four' => [static fn (): mixed => in_array(4, array_column($plan219()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan219()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry219'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan219()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry219'],
    'plan final row eight restored not retried' => [static fn (): mixed => array_column($plan219()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan final network row twelve removed' => [static fn (): mixed => in_array(12, array_column($plan219()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final network row ten preserved' => [static fn (): mixed => in_array(10, array_column($plan219()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plan219()['next_source_tables'], $plan219()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan219()['attempt_statements'][0]['selected_ids'], $plan219()['attempt_statements'][1]['selected_ids']], [[8, 9], [4, 11]]],
    'plan attempt mutation ids' => [static fn (): mixed => [$plan219()['attempt_statements'][0]['mutation_ids'], $plan219()['attempt_statements'][1]['mutation_ids']], [[8, 9], [4, 11]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan219()['retry_statements'][0]['selected_ids'], $plan219()['retry_statements'][1]['selected_ids']], [[9, 7], [12]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan219()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'plugin']],
    'plan discarded returning count' => [static fn (): mixed => $plan219()['discarded_attempt_returning_count'], 4],
    'plan yielded retry count' => [static fn (): mixed => $plan219()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan219()['attempt_changes_before_rollback'], 4],
    'plan retry changes count' => [static fn (): mixed => $plan219()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $plan219()['row_counts'], ['wp_optionmeta' => 8, 'wp_options' => 11]],
    'plan changed tables only options' => [static fn (): mixed => $plan219()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update negative limit' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-in-select-negative-limit-offset-next219', $plan219()['dependencies'], true), true],
    'plan dependency delete negative limit' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-in-select-negative-limit-offset-next219', $plan219()['dependencies'], true), true],
    'plan dependency savepoint negative limit' => [static fn (): mixed => in_array('sqlite-rowvalue-negative-limit-offset-subquery-savepoint-current-source-next219', $plan219()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan219()['dependency_closure_next219'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan219()['non_overlap_next219'], 'avoids accepted next213'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan219()['savepoint'], 'wp_custom_rowvalue_negative_limit219'],
    'custom yielded count' => [static fn (): mixed => $customPlan219()['yielded_after_retry_count'], 2],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate219, ['wp_options' => $rows219], 'option_id', $unique219), InvalidArgumentException::class],
    'malformed bad order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('ORDER BY priority ASC', 'ORDER BY no_such_column ASC', $attemptUpdate219), $tables219, 'option_id', $unique219), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219($tables219, [], [$retryUpdate219], $unique219), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219($tables219, [$attemptUpdate219], [], $unique219), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219($tables219, [$attemptUpdate219], [$retryUpdate219], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219($tables219, [$attemptUpdate219], [$retryUpdate219], $unique219, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext219(['wp_options' => ['bad']], [$attemptUpdate219], [$retryUpdate219], $unique219), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases219 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next219 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
