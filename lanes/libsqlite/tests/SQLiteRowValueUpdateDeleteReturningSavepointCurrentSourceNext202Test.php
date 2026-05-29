<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows202 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no-cache', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables202 = ['wp_options' => $rows202];
$attemptUpdate202 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt202', option_value || ':attempt202', bytes + 2) WHERE (((blog_id, option_name) = (1, 'siteurl')) OR ((blog_id, option_name) = (2, 'home'))) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (((blog_id, option_name) = (blog_id, option_name))) AS same_tuple, (((blog_id, autoload) IS DISTINCT FROM (1, 'no'))) AS distinct_tuple ORDER BY option_id";
$attemptDelete202 = "DELETE FROM wp_options WHERE (((blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')))) RETURNING option_id, blog_id, option_name, (((blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')))) AS deleted_tuple ORDER BY option_id";
$retryUpdate202 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry202', option_value || ':retry202', bytes + 5) WHERE (((blog_id, option_name) IS (1, 'siteurl')) OR ((blog_id, option_name) IS (2, 'home'))) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (((blog_id, option_name) IS NOT DISTINCT FROM (blog_id, option_name))) AS stable_tuple ORDER BY option_id";
$retryDelete202 = "DELETE FROM wp_options WHERE (((blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'siteurl'), (2, 'home'), (3, 'rewrite_rules'), (3, 'plugin_batch'), (4, 'siteurl')))) RETURNING option_id, blog_id, option_name, (((blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'home')))) AS outside_keep ORDER BY option_id";

$attemptUpdateResult202 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate202, $tables202);
$attemptDeleteResult202 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete202, $attemptUpdateResult202()['tables']);
$retryUpdateResult202 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate202, $tables202);
$retryDeleteResult202 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete202, $retryUpdateResult202()['tables']);
$plan202 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint(
    $tables202,
    [$attemptUpdate202, $attemptDelete202],
    [$retryUpdate202, $retryDelete202],
);
$customPlan202 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint(
    $tables202,
    [$attemptUpdate202],
    [$retryUpdate202],
    [],
    'wp_custom_parenthesized202',
);

$cases202 = [
    'parser keeps parenthesized attempt where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate202)['where'], "(((blog_id, option_name) = (1, 'siteurl')) OR ((blog_id, option_name) = (2, 'home')))"],
    'parser keeps parenthesized returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate202)['returning'], 'same_tuple'), true],
    'parser keeps parenthesized delete where' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDelete202)['where'] ?? '', 'VALUES'), true],
    'attempt update selected parenthesized ids' => [static fn (): mixed => $attemptUpdateResult202()['plan']->selectedIds, [1, 6]],
    'attempt update mutation ids' => [static fn (): mixed => $attemptUpdateResult202()['plan']->mutationIds, [1, 6]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult202()['returning'], 'option_id'), [1, 6]],
    'attempt update returning same tuple flags' => [static fn (): mixed => array_column($attemptUpdateResult202()['returning'], 'same_tuple'), [1, 1]],
    'attempt update returning distinct flags' => [static fn (): mixed => array_column($attemptUpdateResult202()['returning'], 'distinct_tuple'), [1, 1]],
    'attempt update row one status' => [static fn (): mixed => array_column($attemptUpdateResult202()['tables']['wp_options'], 'status', 'option_id')[1], 'attempt202'],
    'attempt update row six value' => [static fn (): mixed => array_column($attemptUpdateResult202()['tables']['wp_options'], 'option_value', 'option_id')[6], 'https://two-home.test:attempt202'],
    'attempt delete selected parenthesized ids' => [static fn (): mixed => $attemptDeleteResult202()['plan']->selectedIds, [3, 4]],
    'attempt delete returning flags' => [static fn (): mixed => array_column($attemptDeleteResult202()['returning'], 'deleted_tuple'), [1, 1]],
    'attempt delete removes transient ids' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult202()['tables']['wp_options'], 'option_id'))), []],
    'retry update selected ids from savepoint image' => [static fn (): mixed => $retryUpdateResult202()['plan']->selectedIds, [1, 6]],
    'retry update row one starts original value' => [static fn (): mixed => $retryUpdateResult202()['returning'][0]['option_value'], 'https://one.test:retry202'],
    'retry update row six starts original value' => [static fn (): mixed => $retryUpdateResult202()['returning'][1]['option_value'], 'https://two-home.test:retry202'],
    'retry update returning stable flags' => [static fn (): mixed => array_column($retryUpdateResult202()['returning'], 'stable_tuple'), [1, 1]],
    'retry delete selected ids after retry update' => [static fn (): mixed => $retryDeleteResult202()['plan']->selectedIds, [2, 3, 4, 7]],
    'retry delete returning outside flags' => [static fn (): mixed => array_column($retryDeleteResult202()['returning'], 'outside_keep'), [1, 1, 1, 1]],
    'retry delete preserves kept ids' => [static fn (): mixed => array_values(array_intersect([1, 6, 8, 9, 10], array_column($retryDeleteResult202()['tables']['wp_options'], 'option_id'))), [1, 6, 8, 9, 10]],

    'plan status' => [static fn (): mixed => $plan202()['status'], 'rowvalue-parenthesized-returning-savepoint-current-source-next202'],
    'plan savepoint' => [static fn (): mixed => $plan202()['savepoint'], 'wp_options_rowvalue_parenthesized_next202'],
    'plan rolled back' => [static fn (): mixed => $plan202()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved' => [static fn (): mixed => $plan202()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan202()['savepoint_released_after_retry'], true],
    'plan savepoint image original' => [static fn (): mixed => $plan202()['savepoint_image_tables'], $tables202],
    'plan attempt row one changed' => [static fn (): mixed => array_column($plan202()['attempt_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'attempt202'],
    'plan attempt row six changed' => [static fn (): mixed => array_column($plan202()['attempt_current_source_tables']['wp_options'], 'status', 'option_id')[6], 'attempt202'],
    'plan attempt deletes transient' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($plan202()['attempt_current_source_tables']['wp_options'], 'option_id'))), []],
    'plan rollback restores row one' => [static fn (): mixed => array_column($plan202()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan rollback restores row six' => [static fn (): mixed => array_column($plan202()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[6], 'live'],
    'plan rollback restores transient ids' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($plan202()['rollback_to_current_source_tables']['wp_options'], 'option_id'))), [3, 4]],
    'plan current row one retry' => [static fn (): mixed => array_column($plan202()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'retry202'],
    'plan current row six retry' => [static fn (): mixed => array_column($plan202()['current_source_tables']['wp_options'], 'status', 'option_id')[6], 'retry202'],
    'plan current removed ids' => [static fn (): mixed => array_values(array_intersect([2, 3, 4, 7], array_column($plan202()['current_source_tables']['wp_options'], 'option_id'))), []],
    'plan final ids' => [static fn (): mixed => array_column($plan202()['current_source_tables']['wp_options'], 'option_id'), [1, 5, 6, 8, 9, 10]],
    'plan next equals current' => [static fn (): mixed => $plan202()['next_source_tables'], $plan202()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan202()['attempt_statements'], 'phase'), ['attempt-before-rollback-next202', 'attempt-before-rollback-next202']],
    'plan retry phases' => [static fn (): mixed => array_column($plan202()['retry_statements'], 'phase'), ['retry-after-parenthesized-rollback-next202', 'retry-after-parenthesized-rollback-next202']],
    'plan attempt source ids first statement' => [static fn (): mixed => array_column($plan202()['attempt_statements'][0]['source_rows'], 'option_id'), [1, 6]],
    'plan attempt returning ids first statement' => [static fn (): mixed => array_column($plan202()['attempt_statements'][0]['returning_rows'], 'option_id'), [1, 6]],
    'plan attempt source ids second statement' => [static fn (): mixed => array_column($plan202()['attempt_statements'][1]['source_rows'], 'option_id'), [3, 4]],
    'plan retry source ids first statement' => [static fn (): mixed => array_column($plan202()['retry_statements'][0]['source_rows'], 'option_id'), [1, 6]],
    'plan retry source row one original value' => [static fn (): mixed => $plan202()['retry_statements'][0]['source_rows'][0]['option_value'], 'https://one.test'],
    'plan retry source ids second statement' => [static fn (): mixed => array_column($plan202()['retry_statements'][1]['source_rows'], 'option_id'), [2, 3, 4, 7]],
    'plan attempt count' => [static fn (): mixed => $plan202()['attempt_returning_count'], 4],
    'plan suppressed count' => [static fn (): mixed => $plan202()['suppressed_by_rollback_count'], 4],
    'plan retry count' => [static fn (): mixed => $plan202()['yielded_after_retry_count'], 6],
    'plan attempt changes' => [static fn (): mixed => $plan202()['attempt_changes_before_rollback_to'], 4],
    'plan retry changes' => [static fn (): mixed => $plan202()['changes_after_retry_release'], 6],
    'plan changed tables' => [static fn (): mixed => $plan202()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan202()['row_counts']['wp_options'], 6],
    'plan dependency where unwrap' => [static fn (): mixed => in_array('sqlite-rowvalue-parenthesized-where-predicate-next202', $plan202()['dependencies'], true), true],
    'plan dependency returning unwrap' => [static fn (): mixed => in_array('sqlite-rowvalue-parenthesized-returning-expression-next202', $plan202()['dependencies'], true), true],
    'plan dependency retry image' => [static fn (): mixed => in_array('sqlite-rowvalue-parenthesized-retry-reads-savepoint-image-next202', $plan202()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan202()['savepoint'], 'wp_custom_parenthesized202'],
    'custom plan attempt count' => [static fn (): mixed => $customPlan202()['attempt_returning_count'], 2],
    'custom plan retry count' => [static fn (): mixed => $customPlan202()['yielded_after_retry_count'], 2],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint($tables202, [], [$retryUpdate202]), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint($tables202, [$attemptUpdate202], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint($tables202, [$attemptUpdate202], [$retryUpdate202], [], 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint(['wp_options' => ['bad']], [$attemptUpdate202], [$retryUpdate202]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases202 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next202 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
