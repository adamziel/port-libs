<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rowsbounded = [
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

$metabounded = [
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

$tablesbounded = ['wp_options' => $rowsbounded, 'wp_optionmeta' => $metabounded];
$uniquebounded = [['blog_id', 'option_name']];

$attemptUpdatebounded = "UPDATE wp_options SET (status, option_value, bytes) = ('attemptbounded', option_value || ':attemptbounded', bytes + 4) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority ASC LIMIT 2) AS in_distinct_batch ORDER BY option_id";
$attemptDeletebounded = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY priority ASC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdatebounded = "UPDATE wp_options SET (status, option_value, bytes) = ('retrybounded', option_value || ':retrybounded', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY priority DESC LIMIT 2) AS in_retry_distinct ORDER BY option_id DESC";
$retryDeletebounded = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_retry' ORDER BY priority ASC LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResultbounded = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdatebounded, $tablesbounded, 'option_id', $uniquebounded);
$attemptDeleteResultbounded = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDeletebounded, $attemptUpdateResultbounded()['tables'], 'option_id', $uniquebounded);
$retryUpdateResultbounded = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdatebounded, $tablesbounded, 'option_id', $uniquebounded);
$retryDeleteResultbounded = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeletebounded, $retryUpdateResultbounded()['tables'], 'option_id', $uniquebounded);
$planbounded = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback(
    $tablesbounded,
    [$attemptUpdatebounded, $attemptDeletebounded],
    [$retryUpdatebounded, $retryDeletebounded],
    $uniquebounded,
);
$customPlanbounded = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback(
    $tablesbounded,
    [$attemptUpdatebounded],
    [$retryUpdatebounded],
    $uniquebounded,
    'wp_custom_rowvalue_distinctbounded',
);

$casesbounded = [
    'parser keeps distinct update subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdatebounded)['where'], 'SELECT DISTINCT'), true],
    'parser keeps distinct returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdatebounded)['returning'], 'SELECT DISTINCT'), true],
    'parser retry order by desc retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdatebounded)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct update selected distinct ids' => [static fn (): mixed => $attemptUpdateResultbounded()['plan']->selectedIds, [7, 8]],
    'direct update mutation ids distinct input order' => [static fn (): mixed => $attemptUpdateResultbounded()['plan']->mutationIds, [7, 8]],
    'direct update returning ids input order' => [static fn (): mixed => array_column($attemptUpdateResultbounded()['returning'], 'option_id'), [7, 8]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResultbounded()['returning'], 'in_distinct_batch'), [1, 1]],
    'direct update skips third distinct tuple' => [static fn (): mixed => array_column($attemptUpdateResultbounded()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'direct update row seven value' => [static fn (): mixed => array_column($attemptUpdateResultbounded()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attemptbounded'],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResultbounded()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attemptbounded'],
    'direct delete selected first distinct cleanup row' => [static fn (): mixed => $attemptDeleteResultbounded()['plan']->selectedIds, [3]],
    'direct delete returning one duplicate collapsed row' => [static fn (): mixed => array_column($attemptDeleteResultbounded()['returning'], 'option_id'), [3]],
    'direct delete keeps second cleanup row' => [static fn (): mixed => in_array(4, array_column($attemptDeleteResultbounded()['tables']['wp_options'], 'option_id'), true), true],
    'direct delete removes first cleanup row' => [static fn (): mixed => in_array(3, array_column($attemptDeleteResultbounded()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected descending distinct ids' => [static fn (): mixed => $retryUpdateResultbounded()['plan']->selectedIds, [9, 8]],
    'retry update mutation input order' => [static fn (): mixed => $retryUpdateResultbounded()['plan']->mutationIds, [8, 9]],
    'retry update returning order from order by desc' => [static fn (): mixed => array_column($retryUpdateResultbounded()['returning'], 'option_id'), [8, 9]],
    'retry update row eight original prefix' => [static fn (): mixed => $retryUpdateResultbounded()['returning'][0]['option_value'], 'rules:retrybounded'],
    'retry update row nine original prefix' => [static fn (): mixed => $retryUpdateResultbounded()['returning'][1]['option_value'], 'plugin:retrybounded'],
    'retry update skips lowest priority row seven' => [static fn (): mixed => array_column($retryUpdateResultbounded()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'retry delete selected one distinct network row' => [static fn (): mixed => $retryDeleteResultbounded()['plan']->selectedIds, [10]],
    'retry delete removes network row once' => [static fn (): mixed => in_array(10, array_column($retryDeleteResultbounded()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $planbounded()['status'], 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source'],
    'plan savepoint' => [static fn (): mixed => $planbounded()['savepoint'], 'app_settings_rowvalue_distinct_subquery'],
    'plan distinct flag' => [static fn (): mixed => $planbounded()['distinct_subquery_source'], true],
    'plan rollback flags' => [static fn (): mixed => [$planbounded()['rolled_back_to_savepoint'], $planbounded()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan retry release flags' => [static fn (): mixed => [$planbounded()['retry_reads_savepoint_image'], $planbounded()['savepoint_released_after_retry']], [true, true]],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($planbounded()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attemptbounded'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($planbounded()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attemptbounded'],
    'plan attempt row nine skipped' => [static fn (): mixed => array_column($planbounded()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin'],
    'plan attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($planbounded()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($planbounded()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores cleanup row three' => [static fn (): mixed => in_array(3, array_column($planbounded()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row eight retry' => [static fn (): mixed => array_column($planbounded()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retrybounded'],
    'plan final row nine retry' => [static fn (): mixed => array_column($planbounded()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retrybounded'],
    'plan final row seven restored not retried' => [static fn (): mixed => array_column($planbounded()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan final network row removed' => [static fn (): mixed => in_array(10, array_column($planbounded()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $planbounded()['next_source_tables'], $planbounded()['current_source_tables']],
    'plan attempt selected ids' => [static fn (): mixed => [$planbounded()['attempt_statements'][0]['selected_ids'], $planbounded()['attempt_statements'][1]['selected_ids']], [[7, 8], [3]]],
    'plan attempt mutation ids' => [static fn (): mixed => [$planbounded()['attempt_statements'][0]['mutation_ids'], $planbounded()['attempt_statements'][1]['mutation_ids']], [[7, 8], [3]]],
    'plan retry selected ids' => [static fn (): mixed => [$planbounded()['retry_statements'][0]['selected_ids'], $planbounded()['retry_statements'][1]['selected_ids']], [[9, 8], [10]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($planbounded()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin']],
    'plan discarded returning count' => [static fn (): mixed => $planbounded()['discarded_attempt_returning_count'], 3],
    'plan yielded retry count' => [static fn (): mixed => $planbounded()['yielded_after_retry_count'], 3],
    'plan attempt changes count' => [static fn (): mixed => $planbounded()['attempt_changes_before_rollback'], 3],
    'plan retry changes count' => [static fn (): mixed => $planbounded()['retry_changes_after_rollback'], 3],
    'plan row counts preserve metadata' => [static fn (): mixed => $planbounded()['row_counts'], ['wp_optionmeta' => 10, 'wp_options' => 9]],
    'plan changed tables only options' => [static fn (): mixed => $planbounded()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency update distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-distinct-select-subquery', $planbounded()['dependencies'], true), true],
    'plan dependency delete distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-distinct-select-subquery', $planbounded()['dependencies'], true), true],
    'plan dependency savepoint distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-distinct-subquery-savepoint-current-source', $planbounded()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($planbounded()['dependency_closure'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($planbounded()['non_overlap'], 'avoids accepted negative LIMIT'), true],
    'custom savepoint' => [static fn (): mixed => $customPlanbounded()['savepoint'], 'wp_custom_rowvalue_distinctbounded'],
    'custom yielded count' => [static fn (): mixed => $customPlanbounded()['yielded_after_retry_count'], 2],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdatebounded, ['wp_options' => $rowsbounded], 'option_id', $uniquebounded), InvalidArgumentException::class],
    'malformed bad order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('ORDER BY priority ASC', 'ORDER BY no_such_column ASC', $attemptUpdatebounded), $tablesbounded, 'option_id', $uniquebounded), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback($tablesbounded, [], [$retryUpdatebounded], $uniquebounded), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback($tablesbounded, [$attemptUpdatebounded], [], $uniquebounded), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback($tablesbounded, [$attemptUpdatebounded], [$retryUpdatebounded], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback($tablesbounded, [$attemptUpdatebounded], [$retryUpdatebounded], $uniquebounded, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBoundedDistinctSubquerySavepointRollback(['wp_options' => ['bad']], [$attemptUpdatebounded], [$retryUpdatebounded], $uniquebounded), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesbounded as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source bounded distinct ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
