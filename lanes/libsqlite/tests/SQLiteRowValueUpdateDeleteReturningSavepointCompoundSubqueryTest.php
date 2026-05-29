<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows231 = [
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

$meta231 = [
    ['meta_id' => 301, 'meta_option_id' => 7, 'meta_key' => 'migration_primary', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 302, 'meta_option_id' => 8, 'meta_key' => 'migration_primary', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 303, 'meta_option_id' => 8, 'meta_key' => 'migration_secondary', 'meta_value' => 'rewrite_rules', 'priority' => 21],
    ['meta_id' => 304, 'meta_option_id' => 9, 'meta_key' => 'migration_secondary', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 305, 'meta_option_id' => 3, 'meta_key' => 'cleanup_candidate', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 306, 'meta_option_id' => 4, 'meta_key' => 'cleanup_candidate', 'meta_value' => '_transient_timeout_feed', 'priority' => 6],
    ['meta_id' => 307, 'meta_option_id' => 4, 'meta_key' => 'cleanup_keep', 'meta_value' => '_transient_timeout_feed', 'priority' => 7],
    ['meta_id' => 308, 'meta_option_id' => 8, 'meta_key' => 'retry_candidate', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 309, 'meta_option_id' => 10, 'meta_key' => 'retry_candidate', 'meta_value' => 'network_plugin', 'priority' => 25],
    ['meta_id' => 310, 'meta_option_id' => 8, 'meta_key' => 'retry_confirmed', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 311, 'meta_option_id' => 10, 'meta_key' => 'retry_confirmed', 'meta_value' => 'network_plugin', 'priority' => 25],
    ['meta_id' => 312, 'meta_option_id' => 10, 'meta_key' => 'retry_network', 'meta_value' => 'network_plugin', 'priority' => 40],
    ['meta_id' => 313, 'meta_option_id' => 10, 'meta_key' => 'retry_network_duplicate', 'meta_value' => 'network_plugin', 'priority' => 41],
];

$tables231 = ['wp_options' => $rows231, 'wp_optionmeta' => $meta231];
$unique231 = [['blog_id', 'option_name']];

$attemptUpdate231 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt231', option_value || ':attempt231', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_primary' UNION SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_secondary') RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_primary' UNION SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_secondary') AS in_compound_batch ORDER BY option_id";
$attemptDelete231 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_candidate' EXCEPT SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_keep') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate231 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry231', option_value || ':retry231', bytes + 2) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_candidate' INTERSECT SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_confirmed') RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_candidate' INTERSECT SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_confirmed') AS in_retry_intersect ORDER BY option_id DESC";
$retryDelete231 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_network' UNION ALL SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_network_duplicate') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult231 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate231, $tables231, 'option_id', $unique231);
$attemptDeleteResult231 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete231, $attemptUpdateResult231()['tables'], 'option_id', $unique231);
$retryUpdateResult231 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate231, $tables231, 'option_id', $unique231);
$retryDeleteResult231 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete231, $retryUpdateResult231()['tables'], 'option_id', $unique231);
$plan231 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback(
    $tables231,
    [$attemptUpdate231, $attemptDelete231],
    [$retryUpdate231, $retryDelete231],
    $unique231,
);
$customPlan231 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback(
    $tables231,
    [$attemptUpdate231],
    [$retryUpdate231],
    $unique231,
    'wp_custom_rowvalue_compound',
);

$cases231 = [
    'parser keeps union update subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate231)['where'], 'UNION SELECT'), true],
    'parser keeps returning union expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate231)['returning'], 'UNION SELECT'), true],
    'parser keeps intersect retry expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate231)['where'], 'INTERSECT SELECT'), true],
    'parser keeps except delete expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDelete231)['where'], 'EXCEPT SELECT'), true],
    'parser keeps union all delete expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete231)['where'], 'UNION ALL SELECT'), true],
    'parser retry order by desc retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate231)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct update selected compound ids' => [static fn (): mixed => $attemptUpdateResult231()['plan']->selectedIds, [7, 8, 9]],
    'direct update mutation ids' => [static fn (): mixed => $attemptUpdateResult231()['plan']->mutationIds, [7, 8, 9]],
    'direct update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult231()['returning'], 'option_id'), [7, 8, 9]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult231()['returning'], 'in_compound_batch'), [1, 1, 1]],
    'direct update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult231()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt231'],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult231()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt231'],
    'direct update row nine value' => [static fn (): mixed => array_column($attemptUpdateResult231()['tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:attempt231'],
    'direct update union collapses duplicate row eight' => [static fn (): mixed => count(array_filter($attemptUpdateResult231()['returning'], static fn (array $row): bool => $row['option_id'] === 8)), 1],
    'direct delete except keeps protected cleanup row' => [static fn (): mixed => in_array(4, array_column($attemptDeleteResult231()['tables']['wp_options'], 'option_id'), true), true],
    'direct delete except removes unprotected cleanup row' => [static fn (): mixed => in_array(3, array_column($attemptDeleteResult231()['tables']['wp_options'], 'option_id'), true), false],
    'direct delete returning id' => [static fn (): mixed => array_column($attemptDeleteResult231()['returning'], 'option_id'), [3]],
    'retry update intersect selected ids' => [static fn (): mixed => $retryUpdateResult231()['plan']->selectedIds, [10, 8]],
    'retry update mutation ids' => [static fn (): mixed => $retryUpdateResult231()['plan']->mutationIds, [8, 10]],
    'retry update returning order desc' => [static fn (): mixed => array_column($retryUpdateResult231()['returning'], 'option_id'), [8, 10]],
    'retry update returning flags' => [static fn (): mixed => array_column($retryUpdateResult231()['returning'], 'in_retry_intersect'), [1, 1]],
    'retry update skips non-intersect row seven' => [static fn (): mixed => array_column($retryUpdateResult231()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'retry update row eight value' => [static fn (): mixed => $retryUpdateResult231()['returning'][0]['option_value'], 'rules:retry231'],
    'retry update row ten value' => [static fn (): mixed => $retryUpdateResult231()['returning'][1]['option_value'], 'network:retry231'],
    'retry delete union all selected once' => [static fn (): mixed => $retryDeleteResult231()['plan']->selectedIds, [10]],
    'retry delete union all returning once' => [static fn (): mixed => array_column($retryDeleteResult231()['returning'], 'option_id'), [10]],
    'retry delete removes network row' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult231()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan231()['status'], 'rowvalue-update-delete-returning-compound-subquery-savepoint-current-source'],
    'plan savepoint' => [static fn (): mixed => $plan231()['savepoint'], 'wp_options_rowvalue_compound_subquery'],
    'plan compound flag' => [static fn (): mixed => $plan231()['compound_subquery_source'], true],
    'plan operators' => [static fn (): mixed => $plan231()['compound_operators'], ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT']],
    'plan rollback flags' => [static fn (): mixed => [$plan231()['rolled_back_to_savepoint'], $plan231()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$plan231()['retry_reads_savepoint_image'], $plan231()['savepoint_released_after_retry']], [true, true]],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($plan231()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt231'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan231()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt231'],
    'plan attempt row nine mutated' => [static fn (): mixed => array_column($plan231()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:attempt231'],
    'plan attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($plan231()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt row four kept' => [static fn (): mixed => in_array(4, array_column($plan231()['attempt_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan231()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores cleanup row three' => [static fn (): mixed => in_array(3, array_column($plan231()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan231()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry231'],
    'plan final row ten removed after retry delete' => [static fn (): mixed => in_array(10, array_column($plan231()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row seven restored not retried' => [static fn (): mixed => array_column($plan231()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan next source equals current' => [static fn (): mixed => $plan231()['next_source_tables'], $plan231()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan231()['attempt_statements'][0]['selected_ids'], $plan231()['attempt_statements'][1]['selected_ids']], [[7, 8, 9], [3]]],
    'plan attempt mutation ids' => [static fn (): mixed => [$plan231()['attempt_statements'][0]['mutation_ids'], $plan231()['attempt_statements'][1]['mutation_ids']], [[7, 8, 9], [3]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan231()['retry_statements'][0]['selected_ids'], $plan231()['retry_statements'][1]['selected_ids']], [[10, 8], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan231()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'network']],
    'plan discarded returning count' => [static fn (): mixed => $plan231()['discarded_attempt_returning_count'], 4],
    'plan yielded retry count' => [static fn (): mixed => $plan231()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $plan231()['attempt_changes_before_rollback'], 4],
    'plan retry changes count' => [static fn (): mixed => $plan231()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $plan231()['row_counts'], ['wp_optionmeta' => 13, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $plan231()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update compound' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-compound-select-subquery', $plan231()['dependencies'], true), true],
    'plan dependency delete compound' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-compound-select-subquery', $plan231()['dependencies'], true), true],
    'plan dependency savepoint compound' => [static fn (): mixed => in_array('sqlite-rowvalue-compound-subquery-savepoint-current-source', $plan231()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan231()['dependency_closure'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan231()['non_overlap'], 'avoids accepted DISTINCT'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan231()['savepoint'], 'wp_custom_rowvalue_compound'],
    'custom yielded count' => [static fn (): mixed => $customPlan231()['yielded_after_retry_count'], 2],
    'malformed missing compound subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('wp_optionmeta WHERE meta_key = \'migration_secondary\'', 'missing_meta WHERE meta_key = \'migration_secondary\'', $attemptUpdate231), $tables231, 'option_id', $unique231), InvalidArgumentException::class],
    'malformed unsupported compound arm rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = \'migration_secondary\'', 'SELECT meta_option_id FROM wp_optionmeta WHERE meta_key = \'migration_secondary\'', $attemptUpdate231), $tables231, 'option_id', $unique231), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback($tables231, [], [$retryUpdate231], $unique231), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback($tables231, [$attemptUpdate231], [], $unique231), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback($tables231, [$attemptUpdate231], [$retryUpdate231], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback($tables231, [$attemptUpdate231], [$retryUpdate231], $unique231, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeCompoundSubquerySavepointRollback(['wp_options' => ['bad']], [$attemptUpdate231], [$retryUpdate231], $unique231), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases231 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint compound subquery current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
