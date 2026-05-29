<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows201 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables201 = ['wp_options' => $rows201];
$unique201 = [['blog_id', 'option_name']];
$outerSql201 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer201', option_value || ':outer201', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$savepointUpdate201 = "UPDATE wp_options SET (status, option_value, bytes) = ('discard201', option_value || ':discard201', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('discard201', 'pending_theme') AS discarded_pending ORDER BY option_id";
$savepointDelete201 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate201 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry201', option_value || ':retry201', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (option_value, status) IS NOT DISTINCT FROM (option_value, 'retry201') AS retry_tuple ORDER BY option_id DESC";
$retryDelete201 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'home')) AS dropped_network_home ORDER BY option_id";

$outerResult201 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql201, $tables201, 'option_id', $unique201);
$savepointUpdateResult201 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointUpdate201, $outerResult201()['tables'], 'option_id', $unique201);
$savepointDeleteResult201 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointDelete201, $savepointUpdateResult201()['tables'], 'option_id', $unique201);
$retryUpdateFromRollback201 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate201, $outerResult201()['tables'], 'option_id', $unique201);
$retryDeleteFromRetry201 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete201, $retryUpdateFromRollback201()['tables'], 'option_id', $unique201);
$plan201 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint(
    $tables201,
    [$outerSql201],
    [$savepointUpdate201, $savepointDelete201],
    [$retryUpdate201, $retryDelete201],
    $unique201,
);
$customPlan201 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint(
    $tables201,
    [$outerSql201],
    [$savepointUpdate201],
    [$retryUpdate201],
    $unique201,
    'wp_custom_next201',
);

$cases201 = [
    'parser update row value set columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($savepointUpdate201)['assignments']), ['status', 'option_value', 'bytes']],
    'parser update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($savepointUpdate201)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser delete values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete201)['where'] ?? '', 'VALUES'), true],
    'parser retry returning row value expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate201)['returning'], 'IS NOT DISTINCT FROM'), true],
    'outer selected ids' => [static fn (): mixed => $outerResult201()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult201()['returning'], 'option_id'), [1, 2]],
    'outer row one status' => [static fn (): mixed => array_column($outerResult201()['tables']['wp_options'], 'status', 'option_id')[1], 'outer201'],
    'outer row two value' => [static fn (): mixed => array_column($outerResult201()['tables']['wp_options'], 'option_value', 'option_id')[2], 'https://home.test:outer201'],
    'savepoint update selected ids' => [static fn (): mixed => $savepointUpdateResult201()['plan']->selectedIds, [7, 8]],
    'savepoint update returning ids' => [static fn (): mixed => array_column($savepointUpdateResult201()['returning'], 'option_id'), [7, 8]],
    'savepoint update predicate flag' => [static fn (): mixed => array_column($savepointUpdateResult201()['returning'], 'discarded_pending'), [1, 0]],
    'savepoint update current row seven discarded' => [static fn (): mixed => array_column($savepointUpdateResult201()['tables']['wp_options'], 'status', 'option_id')[7], 'discard201'],
    'savepoint delete selected ids' => [static fn (): mixed => $savepointDeleteResult201()['plan']->selectedIds, [3, 4]],
    'savepoint delete removes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($savepointDeleteResult201()['tables']['wp_options'], 'option_id')), []],
    'retry update selected ids after rollback' => [static fn (): mixed => $retryUpdateFromRollback201()['plan']->selectedIds, [8, 7]],
    'retry update returning current-source order' => [static fn (): mixed => array_column($retryUpdateFromRollback201()['returning'], 'option_id'), [7, 8]],
    'retry update row seven does not include discarded suffix' => [static fn (): mixed => array_column($retryUpdateFromRollback201()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry201'],
    'retry update tuple flags' => [static fn (): mixed => array_column($retryUpdateFromRollback201()['returning'], 'retry_tuple'), [1, 1]],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteFromRetry201()['plan']->selectedIds, [4, 11]],
    'retry delete network home flag' => [static fn (): mixed => array_column($retryDeleteFromRetry201()['returning'], 'dropped_network_home'), [0, 1]],

    'plan status' => [static fn (): mixed => $plan201()['status'], 'rowvalue-update-delete-returning-rollback-to-current-source-next201'],
    'plan savepoint' => [static fn (): mixed => $plan201()['savepoint'], 'wp_options_rowvalue_rollback_to_next201'],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan201()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback' => [static fn (): mixed => $plan201()['savepoint_preserved_after_rollback_to'], true],
    'plan savepoint released after retry' => [static fn (): mixed => $plan201()['savepoint_released_after_retry'], true],
    'plan initial tables' => [static fn (): mixed => $plan201()['initial_tables'], $tables201],
    'plan outer current row one' => [static fn (): mixed => array_column($plan201()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer201'],
    'plan savepoint image equals outer current' => [static fn (): mixed => $plan201()['savepoint_image_tables'], $plan201()['outer_current_source_tables']],
    'plan savepoint attempt row seven discarded' => [static fn (): mixed => array_column($plan201()['savepoint_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'discard201'],
    'plan savepoint attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($plan201()['savepoint_attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback source restores row seven null' => [static fn (): mixed => array_column($plan201()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback source restores transient feed' => [static fn (): mixed => array_column($plan201()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan rollback source preserves outer row one' => [static fn (): mixed => array_column($plan201()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer201'],
    'plan retry update source rows restored' => [static fn (): mixed => array_column($plan201()['retry_statements'][0]['source_rows'], 'status'), [null, 'queued']],
    'plan retry delete source rows include timeout and home' => [static fn (): mixed => array_column($plan201()['retry_statements'][1]['source_rows'], 'option_id'), [4, 11]],
    'plan outer yielding count' => [static fn (): mixed => $plan201()['outer_yielded_returning_count'], 2],
    'plan discarded savepoint yielding count' => [static fn (): mixed => $plan201()['discarded_savepoint_returning_count'], 4],
    'plan retry yielded count' => [static fn (): mixed => $plan201()['yielded_after_retry_count'], 4],
    'plan discarded changes' => [static fn (): mixed => $plan201()['discarded_savepoint_changes'], 4],
    'plan retry changes' => [static fn (): mixed => $plan201()['changes_after_retry'], 4],
    'plan discarded streams actions' => [static fn (): mixed => array_column($plan201()['discarded_savepoint_returning'], 'action'), ['update', 'delete']],
    'plan yielded retry streams actions' => [static fn (): mixed => array_column($plan201()['yielded_after_retry_returning'], 'action'), ['update', 'delete']],
    'plan current row seven retry' => [static fn (): mixed => array_column($plan201()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry201'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan201()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry201'],
    'plan current row three restored after rollback' => [static fn (): mixed => array_column($plan201()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan current row four deleted by retry' => [static fn (): mixed => in_array(4, array_column($plan201()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current row eleven deleted' => [static fn (): mixed => in_array(11, array_column($plan201()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan201()['next_source_tables'], $plan201()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan201()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan201()['row_counts']['wp_options'], 9],
    'plan dependency discarded returning' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-discarded-by-rollback-to-savepoint-next201', $plan201()['dependencies'], true), true],
    'plan dependency restored source' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-restores-current-source-for-rowvalue-retry-next201', $plan201()['dependencies'], true), true],
    'plan dependency retry yields' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-after-rollback-to-yields-from-restored-image-next201', $plan201()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan201()['savepoint'], 'wp_custom_next201'],
    'custom plan discarded count' => [static fn (): mixed => $customPlan201()['discarded_savepoint_returning_count'], 2],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint($tables201, [], [$savepointUpdate201], [$retryUpdate201], $unique201), InvalidArgumentException::class],
    'malformed empty savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint($tables201, [$outerSql201], [], [$retryUpdate201], $unique201), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint($tables201, [$outerSql201], [$savepointUpdate201], [], $unique201), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint($tables201, [$outerSql201], [$savepointUpdate201], [$retryUpdate201], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint($tables201, [$outerSql201], [$savepointUpdate201], [$retryUpdate201], $unique201, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepoint(['wp_options' => ['bad']], [$outerSql201], [$savepointUpdate201], [$retryUpdate201], $unique201), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases201 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next201 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
