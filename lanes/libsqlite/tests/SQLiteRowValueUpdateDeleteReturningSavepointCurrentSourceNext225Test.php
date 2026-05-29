<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows225 = [
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

$meta225 = [
    ['meta_id' => 101, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 102, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'priority' => 11],
    ['meta_id' => 103, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 104, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'priority' => 21],
    ['meta_id' => 105, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 106, 'meta_option_id' => 9, 'meta_key' => 'migration_batch', 'meta_value' => 'plugin_batch', 'priority' => 31],
    ['meta_id' => 107, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 108, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'priority' => 6],
    ['meta_id' => 109, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 15],
    ['meta_id' => 110, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'priority' => 16],
    ['meta_id' => 111, 'meta_option_id' => 11, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_cache', 'priority' => 25],
    ['meta_id' => 112, 'meta_option_id' => 11, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_cache', 'priority' => 26],
    ['meta_id' => 113, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 40],
    ['meta_id' => 114, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'priority' => 41],
    ['meta_id' => 115, 'meta_option_id' => 12, 'meta_key' => 'network_drop', 'meta_value' => 'network_plugin', 'priority' => 35],
    ['meta_id' => 116, 'meta_option_id' => 12, 'meta_key' => 'network_drop', 'meta_value' => 'network_plugin', 'priority' => 36],
];

$tables225 = ['wp_options' => $rows225, 'wp_optionmeta' => $meta225];
$unique225 = [['blog_id', 'option_name']];

$attemptUpdate225 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt225', option_value || ':attempt225', bytes + 4) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id ASC LIMIT 2) AS in_distinct_batch ORDER BY option_id";
$attemptDelete225 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY meta_option_id ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate225 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry225', option_value || ':retry225', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id DESC LIMIT 2) AS in_retry_distinct ORDER BY option_id DESC";
$retryDelete225 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop' ORDER BY meta_option_id DESC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult225 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate225, $tables225, 'option_id', $unique225);
$attemptDeleteResult225 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete225, $attemptUpdateResult225()['tables'], 'option_id', $unique225);
$retryUpdateResult225 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate225, $tables225, 'option_id', $unique225);
$retryDeleteResult225 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete225, $retryUpdateResult225()['tables'], 'option_id', $unique225);
$plainDuplicateUpdate225 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute(str_replace('SELECT DISTINCT', 'SELECT', $attemptUpdate225), $tables225, 'option_id', $unique225);
$plan225 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225(
    $tables225,
    [$attemptUpdate225, $attemptDelete225],
    [$retryUpdate225, $retryDelete225],
    $unique225,
);
$customPlan225 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225(
    $tables225,
    [$attemptUpdate225],
    [$retryUpdate225],
    $unique225,
    'wp_custom_rowvalue_distinct225',
);

$cases225 = [
    'parser keeps distinct update subquery' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate225)['where'], "(option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_option_id ASC LIMIT 2)"],
    'parser keeps distinct returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate225)['returning'], 'SELECT DISTINCT'), true],
    'parser retry order by desc retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate225)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'plain duplicate source limit only reaches row seven' => [static fn (): mixed => $plainDuplicateUpdate225()['plan']->selectedIds, [7]],
    'distinct update selected ids collapse duplicates' => [static fn (): mixed => $attemptUpdateResult225()['plan']->selectedIds, [7, 8]],
    'distinct update mutation ids input order' => [static fn (): mixed => $attemptUpdateResult225()['plan']->mutationIds, [7, 8]],
    'distinct update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult225()['returning'], 'option_id'), [7, 8]],
    'distinct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult225()['returning'], 'in_distinct_batch'), [1, 1]],
    'distinct update skips third tuple' => [static fn (): mixed => array_column($attemptUpdateResult225()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'distinct update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult225()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt225'],
    'distinct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult225()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt225'],
    'distinct delete selected cleanup ids' => [static fn (): mixed => $attemptDeleteResult225()['plan']->selectedIds, [3, 4]],
    'distinct delete returning cleanup ids' => [static fn (): mixed => array_column($attemptDeleteResult225()['returning'], 'option_id'), [3, 4]],
    'distinct delete keeps third cleanup row' => [static fn (): mixed => in_array(11, array_column($attemptDeleteResult225()['tables']['wp_options'], 'option_id'), true), true],
    'distinct delete removes first cleanup rows' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult225()['tables']['wp_options'], 'option_id'))), []],
    'retry update selected descending distinct ids' => [static fn (): mixed => $retryUpdateResult225()['plan']->selectedIds, [9, 8]],
    'retry update mutation input order' => [static fn (): mixed => $retryUpdateResult225()['plan']->mutationIds, [8, 9]],
    'retry update returning order by desc' => [static fn (): mixed => array_column($retryUpdateResult225()['returning'], 'option_id'), [8, 9]],
    'retry update row eight original prefix' => [static fn (): mixed => $retryUpdateResult225()['returning'][0]['option_value'], 'rules:retry225'],
    'retry update row nine original prefix' => [static fn (): mixed => $retryUpdateResult225()['returning'][1]['option_value'], 'plugin:retry225'],
    'retry update skips lowest distinct row seven' => [static fn (): mixed => array_column($retryUpdateResult225()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'retry delete selected highest network row' => [static fn (): mixed => $retryDeleteResult225()['plan']->selectedIds, [12]],
    'retry delete removes highest network row' => [static fn (): mixed => in_array(12, array_column($retryDeleteResult225()['tables']['wp_options'], 'option_id'), true), false],
    'retry delete keeps lower network row' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult225()['tables']['wp_options'], 'option_id'), true), true],

    'plan status' => [static fn (): mixed => $plan225()['status'], 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next225'],
    'plan savepoint' => [static fn (): mixed => $plan225()['savepoint'], 'wp_options_rowvalue_distinct_subquery_next225'],
    'plan distinct source flag' => [static fn (): mixed => $plan225()['distinct_subquery_source'], true],
    'plan rollback flags' => [static fn (): mixed => [$plan225()['rolled_back_to_savepoint'], $plan225()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan225()['retry_reads_savepoint_image'], $plan225()['savepoint_released_after_retry']], [true, true]],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($plan225()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt225'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan225()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt225'],
    'plan attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($plan225()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt row four deleted' => [static fn (): mixed => in_array(4, array_column($plan225()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan225()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores cleanup row three' => [static fn (): mixed => in_array(3, array_column($plan225()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan225()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry225'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan225()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry225'],
    'plan final row seven restored not retried' => [static fn (): mixed => array_column($plan225()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan final network row twelve removed' => [static fn (): mixed => in_array(12, array_column($plan225()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final network row ten preserved' => [static fn (): mixed => in_array(10, array_column($plan225()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plan225()['next_source_tables'], $plan225()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan225()['attempt_statements'][0]['selected_ids'], $plan225()['attempt_statements'][1]['selected_ids']], [[7, 8], [3, 4]]],
    'plan attempt mutation ids' => [static fn (): mixed => [$plan225()['attempt_statements'][0]['mutation_ids'], $plan225()['attempt_statements'][1]['mutation_ids']], [[7, 8], [3, 4]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan225()['retry_statements'][0]['selected_ids'], $plan225()['retry_statements'][1]['selected_ids']], [[9, 8], [12]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan225()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin']],
    'plan discarded returning count' => [static fn (): mixed => $plan225()['discarded_attempt_returning_count'], 4],
    'plan yielded retry count' => [static fn (): mixed => $plan225()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan225()['attempt_changes_before_rollback'], 4],
    'plan retry changes count' => [static fn (): mixed => $plan225()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $plan225()['row_counts'], ['wp_optionmeta' => 16, 'wp_options' => 11]],
    'plan changed tables only options' => [static fn (): mixed => $plan225()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-in-select-distinct-next225', $plan225()['dependencies'], true), true],
    'plan dependency delete distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-in-select-distinct-next225', $plan225()['dependencies'], true), true],
    'plan dependency savepoint distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-distinct-subquery-savepoint-current-source-next225', $plan225()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan225()['dependency_closure_next225'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan225()['non_overlap_next225'], 'avoids accepted next219'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan225()['savepoint'], 'wp_custom_rowvalue_distinct225'],
    'custom yielded count' => [static fn (): mixed => $customPlan225()['yielded_after_retry_count'], 2],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate225, ['wp_options' => $rows225], 'option_id', $unique225), InvalidArgumentException::class],
    'malformed bad order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('ORDER BY meta_option_id ASC', 'ORDER BY no_such_column ASC', $attemptUpdate225), $tables225, 'option_id', $unique225), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225($tables225, [], [$retryUpdate225], $unique225), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225($tables225, [$attemptUpdate225], [], $unique225), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225($tables225, [$attemptUpdate225], [$retryUpdate225], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225($tables225, [$attemptUpdate225], [$retryUpdate225], $unique225, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext225(['wp_options' => ['bad']], [$attemptUpdate225], [$retryUpdate225], $unique225), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases225 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next225 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
