<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows177 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables177 = ['wp_options' => $rows177];
$unique177 = [['blog_id', 'option_name']];

$outerSql177 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer177', option_value || ':outer177', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('outer177', 'pending_theme') AS pending_outer ORDER BY option_id";
$innerYieldSql177 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner177', option_value || ':inner177', bytes + 3) WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerDiscardDeleteSql177 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$innerDiscardReplaceSql177 = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'discarded177', option_value || ':discarded177', bytes + 40) WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value, bytes";
$innerRetryUpdateSql177 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry177', option_value || ':retry177', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry177', 'pending_theme') AS pending_retry ORDER BY option_id";
$innerRetryDeleteSql177 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$outer177 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql177, $tables177, 'option_id', $unique177);
$innerYield177 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerYieldSql177, $outer177()['tables'], 'option_id', $unique177);
$innerDiscardDelete177 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDiscardDeleteSql177, $innerYield177()['tables'], 'option_id', $unique177);
$innerDiscardReplace177 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDiscardReplaceSql177, $innerDiscardDelete177()['tables'], 'option_id', $unique177);
$innerRetry177 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerRetryUpdateSql177, $outer177()['tables'], 'option_id', $unique177);
$plan177 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint(
    $tables177,
    [$outerSql177],
    [$innerYieldSql177],
    [$innerDiscardDeleteSql177, $innerDiscardReplaceSql177],
    [$innerRetryUpdateSql177, $innerRetryDeleteSql177],
    $unique177,
);

$cases177 = [
    'parser outer row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($outerSql177)['assignments']), ['status', 'option_value', 'bytes']],
    'parser inner yielded returning columns' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerYieldSql177)['returning'], 'option_id, blog_id, option_name, status, option_value, bytes'],
    'parser discarded delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDiscardDeleteSql177)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'parser discarded replace conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDiscardReplaceSql177)['conflict_action'], 'replace'],
    'outer selected ids' => [static fn (): mixed => $outer177()['plan']->selectedIds, [7, 8]],
    'outer returning ids' => [static fn (): mixed => array_column($outer177()['returning'], 'option_id'), [7, 8]],
    'outer returning predicate true for row seven' => [static fn (): mixed => $outer177()['returning'][0]['pending_outer'], 1],
    'outer current row seven status' => [static fn (): mixed => array_column($outer177()['tables']['wp_options'], 'status', 'option_id')[7], 'outer177'],
    'outer current row eight value' => [static fn (): mixed => array_column($outer177()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:outer177'],
    'inner yielded selected ids include outer row seven and row nine' => [static fn (): mixed => $innerYield177()['plan']->selectedIds, [7, 9]],
    'inner yielded returning ids' => [static fn (): mixed => array_column($innerYield177()['returning'], 'option_id'), [7, 9]],
    'inner yielded row seven sees outer value first' => [static fn (): mixed => $innerYield177()['returning'][0]['option_value'], 'theme:outer177:inner177'],
    'inner yielded row nine status' => [static fn (): mixed => array_column($innerYield177()['tables']['wp_options'], 'status', 'option_id')[9], 'inner177'],
    'inner discarded delete ids' => [static fn (): mixed => array_column($innerDiscardDelete177()['returning'], 'option_id'), [3, 4]],
    'inner discarded delete removes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($innerDiscardDelete177()['tables']['wp_options'], 'option_id')), []],
    'inner discarded replace deletes conflicting row ten' => [static fn (): mixed => array_column($innerDiscardReplace177()['deleted_conflict_rows'], 'option_id'), [10]],
    'inner discarded replace returns row seven' => [static fn (): mixed => array_column($innerDiscardReplace177()['returning'], 'option_id'), [7]],
    'inner discarded replace row seven key' => [static fn (): mixed => array_column($innerDiscardReplace177()['tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'retry starts from inner image row seven outer value' => [static fn (): mixed => $innerRetry177()['returning'][0]['option_value'], 'theme:outer177:retry177'],

    'plan status' => [static fn (): mixed => $plan177()['status'], 'inner-rollback-to-retry-current-source-next177'],
    'plan outer savepoint' => [static fn (): mixed => $plan177()['outer_savepoint'], 'wp_options_outer_rowvalue_next177'],
    'plan inner savepoint' => [static fn (): mixed => $plan177()['inner_savepoint'], 'wp_options_inner_rowvalue_next177'],
    'plan rolled back to inner' => [static fn (): mixed => $plan177()['rolled_back_to_inner_savepoint'], true],
    'plan outer preserved after inner rollback' => [static fn (): mixed => $plan177()['outer_savepoint_preserved_after_inner_rollback_to'], true],
    'plan inner preserved after rollback' => [static fn (): mixed => $plan177()['inner_savepoint_preserved_after_rollback_to'], true],
    'plan inner released after retry' => [static fn (): mixed => $plan177()['inner_released_after_retry'], true],
    'plan outer released after inner retry' => [static fn (): mixed => $plan177()['outer_released_after_inner_retry'], true],
    'plan outer image original table' => [static fn (): mixed => $plan177()['outer_savepoint_image_tables'], $tables177],
    'plan outer current source row seven status' => [static fn (): mixed => array_column($plan177()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer177'],
    'plan inner image equals outer current source' => [static fn (): mixed => $plan177()['inner_savepoint_image_tables'], $plan177()['outer_current_source_tables']],
    'plan yielded current row nine inner' => [static fn (): mixed => array_column($plan177()['inner_yielded_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'inner177'],
    'plan discarded current row ten absent' => [static fn (): mixed => in_array(10, array_column($plan177()['inner_discarded_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback to inner restores row ten' => [static fn (): mixed => in_array(10, array_column($plan177()['rollback_to_inner_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan rollback to inner keeps outer row seven status' => [static fn (): mixed => array_column($plan177()['rollback_to_inner_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer177'],
    'plan rollback to inner restores row nine staged' => [static fn (): mixed => array_column($plan177()['rollback_to_inner_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'staged'],
    'plan outer yielded count' => [static fn (): mixed => $plan177()['outer_yielded_returning_count'], 2],
    'plan inner yielded before rollback count' => [static fn (): mixed => $plan177()['inner_yielded_before_rollback_count'], 2],
    'plan inner discarded before rollback count' => [static fn (): mixed => $plan177()['inner_discarded_before_rollback_count'], 3],
    'plan inner suppressed count' => [static fn (): mixed => $plan177()['inner_suppressed_by_rollback_count'], 5],
    'plan inner yielded after retry count' => [static fn (): mixed => $plan177()['inner_yielded_after_retry_count'], 4],
    'plan outer changes preserved' => [static fn (): mixed => $plan177()['outer_changes_preserved'], 2],
    'plan inner attempted changes before rollback' => [static fn (): mixed => $plan177()['inner_attempted_changes_before_rollback_to'], 6],
    'plan inner changes after retry release' => [static fn (): mixed => $plan177()['inner_changes_after_retry_release'], 4],
    'plan outer statement phase' => [static fn (): mixed => $plan177()['outer_statements'][0]['phase'], 'outer-before-inner'],
    'plan yielded statement phase' => [static fn (): mixed => $plan177()['inner_yielded_statements'][0]['phase'], 'inner-yielded-before-rollback'],
    'plan discarded phases' => [static fn (): mixed => array_column($plan177()['inner_discarded_statements'], 'phase'), ['inner-discarded-before-rollback', 'inner-discarded-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plan177()['inner_retry_statements'], 'phase'), ['inner-retry-after-rollback', 'inner-retry-after-rollback']],
    'plan yielded source rows see outer current' => [static fn (): mixed => array_column($plan177()['inner_yielded_statements'][0]['source_rows'], 'status'), ['outer177', 'staged']],
    'plan retry source rows see inner image' => [static fn (): mixed => array_column($plan177()['inner_retry_statements'][0]['source_rows'], 'status'), ['outer177', 'staged']],
    'plan retry update ids' => [static fn (): mixed => array_column($plan177()['inner_yielded_after_retry_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan retry delete ids' => [static fn (): mixed => array_column($plan177()['inner_yielded_after_retry_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan final row seven retry value keeps outer' => [static fn (): mixed => array_column($plan177()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer177:retry177'],
    'plan final row eight outer preserved' => [static fn (): mixed => array_column($plan177()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'outer177'],
    'plan final row nine retry status' => [static fn (): mixed => array_column($plan177()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry177'],
    'plan final row ten restored' => [static fn (): mixed => array_column($plan177()['current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan final deletes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan177()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan177()['next_source_tables'], $plan177()['current_source_tables']],
    'plan row count after retry' => [static fn (): mixed => $plan177()['row_counts']['wp_options'], 8],
    'plan changed tables after retry' => [static fn (): mixed => $plan177()['changed_tables_after_inner_retry'], ['wp_options']],
    'plan dependency yield before rollback' => [static fn (): mixed => in_array('sqlite-inner-savepoint-rowvalue-returning-yield-before-rollback-next177', $plan177()['dependencies'], true), true],
    'plan dependency preserves outer source' => [static fn (): mixed => in_array('sqlite-rollback-to-inner-savepoint-preserves-outer-current-source-next177', $plan177()['dependencies'], true), true],
    'plan dependency retries from inner image' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-retry-starts-from-inner-image-next177', $plan177()['dependencies'], true), true],

    'malformed empty outer statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint($tables177, [], [$innerYieldSql177], [$innerDiscardDeleteSql177], [$innerRetryUpdateSql177], $unique177), InvalidArgumentException::class],
    'malformed empty yielded statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint($tables177, [$outerSql177], [], [$innerDiscardDeleteSql177], [$innerRetryUpdateSql177], $unique177), InvalidArgumentException::class],
    'malformed empty discarded statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint($tables177, [$outerSql177], [$innerYieldSql177], [], [$innerRetryUpdateSql177], $unique177), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint($tables177, [$outerSql177], [$innerYieldSql177], [$innerDiscardDeleteSql177], [], $unique177), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint($tables177, [$outerSql177], [$innerYieldSql177], [$innerDiscardDeleteSql177], [$innerRetryUpdateSql177], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeInnerRollbackRetrySavepoint(['wp_options' => ['bad']], [$outerSql177], [$innerYieldSql177], [$innerDiscardDeleteSql177], [$innerRetryUpdateSql177], $unique177), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases177 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next177 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
