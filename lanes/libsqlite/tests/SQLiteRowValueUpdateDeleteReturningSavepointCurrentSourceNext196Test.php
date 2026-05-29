<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows196 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no-cache', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables196 = ['wp_options' => $rows196];
$unique196 = [['blog_id', 'autoload']];

$preUpdate196 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer196', option_value || ':outer196', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes ORDER BY option_id";
$preDelete196 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, autoload, status ORDER BY option_id";
$failUpdate196 = "UPDATE OR FAIL wp_options SET (blog_id, status, option_value, bytes) = (1, 'fail196', option_value || ':fail196', bytes + 4) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) = (1, 'no') AS fail_prefix_tuple ORDER BY option_id";
$retryUpdate196 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry196', option_value || ':retry196', bytes + 5) WHERE (blog_id, option_name) IN ((1, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes ORDER BY option_id";
$retryDelete196 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$preUpdateResult196 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdate196, $tables196, 'option_id', $unique196);
$preDeleteResult196 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDelete196, $preUpdateResult196()['tables'], 'option_id', $unique196);
$failPreservedResult196 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate196, $preDeleteResult196()['tables'], 'option_id', $unique196, true);
$retryUpdateResult196 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate196, $failPreservedResult196()['tables'], 'option_id', $unique196);
$retryDeleteResult196 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete196, $retryUpdateResult196()['tables'], 'option_id', $unique196);
$plan196 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint(
    $tables196,
    [$preUpdate196, $preDelete196],
    $failUpdate196,
    [$retryUpdate196, $retryDelete196],
    $unique196,
);
$customPlan196 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint(
    $tables196,
    [$preUpdate196],
    $failUpdate196,
    [$retryUpdate196],
    $unique196,
    'wp_custom_fail196',
);

$cases196 = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate196)['conflict_action'], 'fail'],
    'parser fail row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdate196)['assignments']), ['blog_id', 'status', 'option_value', 'bytes']],
    'parser fail row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate196)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'))"],
    'direct fail statement throws by default' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($failUpdate196, $preDeleteResult196()['tables'], 'option_id', $unique196), InvalidArgumentException::class],
    'preserved fail selected ids' => [static fn (): mixed => $failPreservedResult196()['plan']->selectedIds, [7, 9]],
    'preserved fail mutation ids' => [static fn (): mixed => $failPreservedResult196()['plan']->mutationIds, [7, 9]],
    'preserved fail returning only prefix row' => [static fn (): mixed => array_column($failPreservedResult196()['returning'], 'option_id'), [7]],
    'preserved fail tuple projection true' => [static fn (): mixed => $failPreservedResult196()['returning'][0]['fail_prefix_tuple'], 1],
    'preserved fail conflict row id' => [static fn (): mixed => $failPreservedResult196()['failed_conflict']['row_id'] ?? null, 9],
    'preserved fail conflict columns' => [static fn (): mixed => $failPreservedResult196()['failed_conflict']['columns'] ?? null, ['blog_id', 'autoload']],
    'preserved fail conflicting ids' => [static fn (): mixed => $failPreservedResult196()['failed_conflict']['conflicting_row_ids'] ?? null, [1]],
    'preserved fail row seven moved blog' => [static fn (): mixed => array_column($failPreservedResult196()['tables']['wp_options'], 'blog_id', 'option_id')[7], 1],
    'preserved fail row seven value keeps outer prefix' => [static fn (): mixed => array_column($failPreservedResult196()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer196:fail196'],
    'preserved fail row nine rolled back to pre fail' => [static fn (): mixed => array_column($failPreservedResult196()['tables']['wp_options'], 'option_value', 'option_id')[9], 'cache'],
    'retry update selected current source rows' => [static fn (): mixed => $retryUpdateResult196()['plan']->selectedIds, [7, 9]],
    'retry update row seven sees fail prefix' => [static fn (): mixed => $retryUpdateResult196()['returning'][0]['option_value'], 'theme:outer196:fail196:retry196'],
    'retry update row nine sees pre fail source' => [static fn (): mixed => $retryUpdateResult196()['returning'][1]['option_value'], 'cache:retry196'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult196()['plan']->selectedIds, [2, 10]],
    'retry delete removes current rows' => [static fn (): mixed => array_intersect([2, 10], array_column($retryDeleteResult196()['tables']['wp_options'], 'option_id')), []],

    'plan status' => [static fn (): mixed => $plan196()['status'], 'rowvalue-or-fail-preserves-statement-prefix-next196'],
    'plan savepoint name' => [static fn (): mixed => $plan196()['savepoint'], 'wp_options_rowvalue_fail_next196'],
    'plan savepoint active after fail' => [static fn (): mixed => $plan196()['savepoint_active_after_fail'], true],
    'plan savepoint released after retry' => [static fn (): mixed => $plan196()['savepoint_released_after_retry'], true],
    'plan savepoint image original' => [static fn (): mixed => $plan196()['savepoint_image_tables'], $tables196],
    'plan pre fail row seven outer' => [static fn (): mixed => array_column($plan196()['pre_fail_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer196'],
    'plan pre fail deletes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan196()['pre_fail_current_source_tables']['wp_options'], 'option_id')), []],
    'plan fail partial row seven blog moved' => [static fn (): mixed => array_column($plan196()['fail_partial_current_source_tables']['wp_options'], 'blog_id', 'option_id')[7], 1],
    'plan fail partial row seven status' => [static fn (): mixed => array_column($plan196()['fail_partial_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'fail196'],
    'plan fail partial row nine not rewritten' => [static fn (): mixed => array_column($plan196()['fail_partial_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'staged'],
    'plan current final ids' => [static fn (): mixed => array_column($plan196()['current_source_tables']['wp_options'], 'option_id'), [1, 5, 6, 7, 8, 9]],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan196()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer196:fail196:retry196'],
    'plan final row nine retry only' => [static fn (): mixed => array_column($plan196()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:retry196'],
    'plan next source equals current' => [static fn (): mixed => $plan196()['next_source_tables'], $plan196()['current_source_tables']],
    'plan pre fail phases' => [static fn (): mixed => array_column($plan196()['pre_fail_statements'], 'phase'), ['before-fail-statement', 'before-fail-statement']],
    'plan fail phase' => [static fn (): mixed => $plan196()['fail_statement']['phase'], 'rowvalue-or-fail-statement'],
    'plan fail flag' => [static fn (): mixed => $plan196()['fail_statement']['failed'], true],
    'plan fail does not roll back statement prefix' => [static fn (): mixed => $plan196()['fail_statement']['statement_rolled_back'], false],
    'plan fail prefix count' => [static fn (): mixed => $plan196()['fail_statement']['prefix_changes_preserved'], 1],
    'plan fail selected ids' => [static fn (): mixed => $plan196()['fail_statement']['selected_ids'], [7, 9]],
    'plan fail returning ids' => [static fn (): mixed => array_column($plan196()['fail_statement']['returning_rows'], 'option_id'), [7]],
    'plan fail conflict action' => [static fn (): mixed => $plan196()['fail_statement']['conflict_action'], 'fail'],
    'plan fail error names unique columns' => [static fn (): mixed => str_contains($plan196()['fail_statement']['error'], 'blog_id,autoload'), true],
    'plan fail conflict metadata row' => [static fn (): mixed => $plan196()['fail_statement']['failed_conflict']['row_id'], 9],
    'plan retry phases' => [static fn (): mixed => array_column($plan196()['retry_statements'], 'phase'), ['retry-after-or-fail', 'retry-after-or-fail']],
    'plan retry source ids first statement' => [static fn (): mixed => array_column($plan196()['retry_statements'][0]['source_rows'], 'option_id'), [7, 9]],
    'plan retry source row seven value' => [static fn (): mixed => $plan196()['retry_statements'][0]['source_rows'][0]['option_value'], 'theme:outer196:fail196'],
    'plan retry delete source ids' => [static fn (): mixed => array_column($plan196()['retry_statements'][1]['source_rows'], 'option_id'), [2, 10]],
    'plan yielded before fail count' => [static fn (): mixed => $plan196()['yielded_before_fail_count'], 4],
    'plan yielded by fail prefix count' => [static fn (): mixed => $plan196()['yielded_by_fail_before_conflict_count'], 1],
    'plan yielded after retry count' => [static fn (): mixed => $plan196()['yielded_after_retry_count'], 4],
    'plan pre fail changes preserved' => [static fn (): mixed => $plan196()['pre_fail_changes_preserved'], 4],
    'plan fail prefix changes preserved' => [static fn (): mixed => $plan196()['fail_prefix_changes_preserved'], 1],
    'plan retry changes after fail' => [static fn (): mixed => $plan196()['retry_changes_after_fail'], 4],
    'plan changed tables' => [static fn (): mixed => $plan196()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan196()['row_counts']['wp_options'], 6],
    'plan dependency fail prefix' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-prefix-preserved-next196', $plan196()['dependencies'], true), true],
    'plan dependency current source' => [static fn (): mixed => in_array('sqlite-rowvalue-savepoint-current-source-after-fail-next196', $plan196()['dependencies'], true), true],
    'plan dependency retry delete' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-retry-after-fail-next196', $plan196()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan196()['savepoint'], 'wp_custom_fail196'],
    'custom plan pre fail count' => [static fn (): mixed => $customPlan196()['yielded_before_fail_count'], 2],
    'malformed empty pre fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [], $failUpdate196, [$retryUpdate196], $unique196), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [$preUpdate196], '', [$retryUpdate196], $unique196), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [$preUpdate196], $failUpdate196, [], $unique196), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [$preUpdate196], $failUpdate196, [$retryUpdate196], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [$preUpdate196], $failUpdate196, [$retryUpdate196], $unique196, 'bad-name'), InvalidArgumentException::class],
    'malformed non fail statement rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [$preUpdate196], $retryUpdate196, [$retryDelete196], $unique196), InvalidArgumentException::class],
    'malformed non conflicting fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint($tables196, [$preUpdate196], "UPDATE OR FAIL wp_options SET status = 'ok196' WHERE option_id = 7 RETURNING option_id", [$retryUpdate196], $unique196), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailConflictPreserveRetrySavepoint(['wp_options' => ['bad']], [$preUpdate196], $failUpdate196, [$retryUpdate196], $unique196), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases196 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next196 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
