<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows197 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables197 = ['wp_options' => $rows197];
$unique197 = [['blog_id', 'option_name']];

$outerUpdate197 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer197', option_value || ':outer197', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerDelete197 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$innerUpdate197 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner197', option_value || ':inner197', bytes + 4) WHERE (blog_id, option_name) IN ((3, 'orphaned_cache'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner197', option_name) AS tuple_ok ORDER BY option_id";
$retryUpdate197 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry197', option_value || ':retry197', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete197 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$outerResult197 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate197, $tables197, 'option_id', $unique197);
$innerDeleteResult197 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete197, $outerResult197()['tables'], 'option_id', $unique197);
$innerUpdateResult197 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdate197, $innerDeleteResult197()['tables'], 'option_id', $unique197);
$retryUpdateFromRollback197 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate197, $outerResult197()['tables'], 'option_id', $unique197);
$retryDeleteFromRetry197 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete197, $retryUpdateFromRollback197()['tables'], 'option_id', $unique197);
$plan197 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry(
    $tables197,
    [$outerUpdate197],
    [$innerDelete197, $innerUpdate197],
    [$retryUpdate197, $retryDelete197],
    $unique197,
);
$customPlan197 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry(
    $tables197,
    [$outerUpdate197],
    [$innerUpdate197],
    [$retryUpdate197],
    $unique197,
    'wp_outer_custom197',
    'wp_inner_custom197',
);

$cases197 = [
    'parser outer row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerUpdate197)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser inner delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDelete197)['returning'], 'option_id, blog_id, option_name, status'],
    'parser inner update assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($innerUpdate197)['assignments']), ['status', 'option_value', 'bytes']],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate197)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'), (4, 'home'))"],
    'parser retry delete order by' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete197)['order_by'], [['column' => 'option_id']]],

    'outer selected ids' => [static fn (): mixed => $outerResult197()['plan']->selectedIds, [7, 8]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult197()['returning'], 'option_id'), [7, 8]],
    'outer row seven value' => [static fn (): mixed => array_column($outerResult197()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer197'],
    'outer row eight bytes' => [static fn (): mixed => array_column($outerResult197()['tables']['wp_options'], 'bytes', 'option_id')[8], 11],
    'inner delete selected ids' => [static fn (): mixed => $innerDeleteResult197()['plan']->selectedIds, [3, 4]],
    'inner delete removes transient ids' => [static fn (): mixed => array_intersect([3, 4], array_column($innerDeleteResult197()['tables']['wp_options'], 'option_id')), []],
    'inner update selected ids' => [static fn (): mixed => $innerUpdateResult197()['plan']->selectedIds, [9, 11]],
    'inner update tuple predicates true' => [static fn (): mixed => array_column($innerUpdateResult197()['returning'], 'tuple_ok'), [1, 1]],
    'inner update row nine value' => [static fn (): mixed => array_column($innerUpdateResult197()['tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:inner197'],
    'inner update row eleven value' => [static fn (): mixed => array_column($innerUpdateResult197()['tables']['wp_options'], 'option_value', 'option_id')[11], 'https://four-home.test:inner197'],
    'retry update from rollback selected ids' => [static fn (): mixed => $retryUpdateFromRollback197()['plan']->selectedIds, [7, 9, 11]],
    'retry update row seven keeps outer prefix' => [static fn (): mixed => $retryUpdateFromRollback197()['returning'][0]['option_value'], 'theme:outer197:retry197'],
    'retry update row nine restored before retry' => [static fn (): mixed => $retryUpdateFromRollback197()['returning'][1]['option_value'], 'cache:retry197'],
    'retry update row eleven restored before retry' => [static fn (): mixed => $retryUpdateFromRollback197()['returning'][2]['option_value'], 'https://four-home.test:retry197'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteFromRetry197()['plan']->selectedIds, [2, 10]],
    'retry delete removes rows' => [static fn (): mixed => array_intersect([2, 10], array_column($retryDeleteFromRetry197()['tables']['wp_options'], 'option_id')), []],

    'plan status' => [static fn (): mixed => $plan197()['status'], 'rowvalue-update-delete-returning-rollback-to-current-source-next197'],
    'plan savepoint names' => [static fn (): mixed => [$plan197()['outer_savepoint'], $plan197()['inner_savepoint']], ['wp_options_rowvalue_rollback_outer_next197', 'wp_options_rowvalue_rollback_inner_next197']],
    'plan rollback flag' => [static fn (): mixed => $plan197()['rollback_to_inner_savepoint'], true],
    'plan inner preserved after rollback' => [static fn (): mixed => $plan197()['inner_savepoint_preserved_after_rollback_to'], true],
    'plan release flags' => [static fn (): mixed => [$plan197()['inner_released_after_retry'], $plan197()['outer_released_after_inner_retry']], [true, true]],
    'plan outer image original' => [static fn (): mixed => $plan197()['outer_savepoint_image_tables'], $tables197],
    'plan outer current row seven' => [static fn (): mixed => array_column($plan197()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer197'],
    'plan outer current row eight' => [static fn (): mixed => array_column($plan197()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'outer197'],
    'plan inner image equals outer current' => [static fn (): mixed => $plan197()['inner_savepoint_image_tables'], $plan197()['outer_current_source_tables']],
    'plan inner attempt deletes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan197()['inner_attempt_current_source_tables']['wp_options'], 'option_id')), []],
    'plan inner attempt row nine inner' => [static fn (): mixed => array_column($plan197()['inner_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'inner197'],
    'plan inner attempt row eleven inner' => [static fn (): mixed => array_column($plan197()['inner_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[11], 'inner197'],
    'plan rollback restores inner image' => [static fn (): mixed => $plan197()['rollback_to_current_source_tables'], $plan197()['inner_savepoint_image_tables']],
    'plan rollback restores transient feed' => [static fn (): mixed => array_column($plan197()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[3], 'stale'],
    'plan rollback restores transient timeout' => [static fn (): mixed => array_column($plan197()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[4], 'stale'],
    'plan rollback restores row nine staged' => [static fn (): mixed => array_column($plan197()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'staged'],
    'plan rollback preserves row seven outer' => [static fn (): mixed => array_column($plan197()['rollback_to_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer197'],
    'plan final ids' => [static fn (): mixed => array_column($plan197()['current_source_tables']['wp_options'], 'option_id'), [1, 3, 4, 5, 6, 7, 8, 9, 11]],
    'plan final row seven retry after outer' => [static fn (): mixed => array_column($plan197()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer197:retry197'],
    'plan final row eight outer preserved' => [static fn (): mixed => array_column($plan197()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:outer197'],
    'plan final row nine retry only' => [static fn (): mixed => array_column($plan197()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:retry197'],
    'plan final row eleven retry only' => [static fn (): mixed => array_column($plan197()['current_source_tables']['wp_options'], 'option_value', 'option_id')[11], 'https://four-home.test:retry197'],
    'plan next source equals current' => [static fn (): mixed => $plan197()['next_source_tables'], $plan197()['current_source_tables']],
    'plan outer phases' => [static fn (): mixed => array_column($plan197()['outer_statements'], 'phase'), ['outer-before-inner-rollback-next197']],
    'plan inner phases' => [static fn (): mixed => array_column($plan197()['inner_statements'], 'phase'), ['inner-before-rollback-to-next197', 'inner-before-rollback-to-next197']],
    'plan retry phases' => [static fn (): mixed => array_column($plan197()['retry_statements'], 'phase'), ['retry-after-rollback-to-next197', 'retry-after-rollback-to-next197']],
    'plan inner actions' => [static fn (): mixed => array_column($plan197()['inner_statements'], 'action'), ['delete', 'update']],
    'plan retry actions' => [static fn (): mixed => array_column($plan197()['retry_statements'], 'action'), ['update', 'delete']],
    'plan inner delete source rows' => [static fn (): mixed => array_column($plan197()['inner_statements'][0]['source_rows'], 'option_id'), [3, 4]],
    'plan inner update source rows' => [static fn (): mixed => array_column($plan197()['inner_statements'][1]['source_rows'], 'option_id'), [9, 11]],
    'plan retry update source rows restored' => [static fn (): mixed => array_column($plan197()['retry_statements'][0]['source_rows'], 'status'), ['outer197', 'staged', 'live']],
    'plan retry delete source rows restored' => [static fn (): mixed => array_column($plan197()['retry_statements'][1]['source_rows'], 'option_id'), [2, 10]],
    'plan inner rolled back returning ids' => [static fn (): mixed => array_merge(array_column($plan197()['inner_rolled_back_returning'][0]['rows'], 'option_id'), array_column($plan197()['inner_rolled_back_returning'][1]['rows'], 'option_id')), [3, 4, 9, 11]],
    'plan suppressed returning ids' => [static fn (): mixed => array_merge(array_column($plan197()['suppressed_by_rollback_to_returning'][0]['rows'], 'option_id'), array_column($plan197()['suppressed_by_rollback_to_returning'][1]['rows'], 'option_id')), [3, 4, 9, 11]],
    'plan retry yielded ids' => [static fn (): mixed => array_merge(array_column($plan197()['yielded_after_retry_returning'][0]['rows'], 'option_id'), array_column($plan197()['yielded_after_retry_returning'][1]['rows'], 'option_id')), [7, 9, 11, 2, 10]],
    'plan outer returning count' => [static fn (): mixed => $plan197()['outer_yielded_returning_count'], 2],
    'plan inner rolled back count' => [static fn (): mixed => $plan197()['inner_rolled_back_returning_count'], 4],
    'plan suppressed rollback count' => [static fn (): mixed => $plan197()['suppressed_by_rollback_to_count'], 4],
    'plan retry count' => [static fn (): mixed => $plan197()['yielded_after_retry_count'], 5],
    'plan outer changes preserved' => [static fn (): mixed => $plan197()['outer_changes_preserved'], 2],
    'plan inner changes rolled back' => [static fn (): mixed => $plan197()['inner_changes_rolled_back'], 4],
    'plan retry changes after rollback' => [static fn (): mixed => $plan197()['retry_changes_after_rollback_to'], 5],
    'plan changed tables' => [static fn (): mixed => $plan197()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan197()['row_counts']['wp_options'], 9],
    'plan dependency rollback to' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-rollback-to-savepoint-next197', $plan197()['dependencies'], true), true],
    'plan dependency delete restore' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-rollback-to-restores-current-source-next197', $plan197()['dependencies'], true), true],
    'plan dependency retry update' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-retry-after-rollback-to-next197', $plan197()['dependencies'], true), true],
    'custom plan savepoints' => [static fn (): mixed => [$customPlan197()['outer_savepoint'], $customPlan197()['inner_savepoint']], ['wp_outer_custom197', 'wp_inner_custom197']],
    'custom plan inner rolled back count' => [static fn (): mixed => $customPlan197()['inner_rolled_back_returning_count'], 2],
    'custom plan retry count' => [static fn (): mixed => $customPlan197()['yielded_after_retry_count'], 3],
    'custom plan final row count' => [static fn (): mixed => $customPlan197()['row_counts']['wp_options'], 11],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry($tables197, [], [$innerUpdate197], [$retryUpdate197], $unique197), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry($tables197, [$outerUpdate197], [], [$retryUpdate197], $unique197), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry($tables197, [$outerUpdate197], [$innerUpdate197], [], $unique197), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry($tables197, [$outerUpdate197], [$innerUpdate197], [$retryUpdate197], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry($tables197, [$outerUpdate197], [$innerUpdate197], [$retryUpdate197], $unique197, 'bad-name'), InvalidArgumentException::class],
    'malformed inner savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry($tables197, [$outerUpdate197], [$innerUpdate197], [$retryUpdate197], $unique197, 'ok_name', 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry(['wp_options' => ['bad']], [$outerUpdate197], [$innerUpdate197], [$retryUpdate197], $unique197), InvalidArgumentException::class],
    'malformed rowid rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackToInnerSavepointRetry(['wp_options' => [['option_name' => 'siteurl']]], [$outerUpdate197], [$innerUpdate197], [$retryUpdate197], $unique197), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases197 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next197 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
