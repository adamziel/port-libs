<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows174 = [
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

$tables174 = ['wp_options' => $rows174];
$unique174 = [['blog_id', 'option_name']];

$outerSql174 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer174', option_value || ':outer174', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('outer174', 'pending_theme') AS pending_outer ORDER BY option_id";
$innerReplaceSql174 = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'released174', option_value || ':released174', bytes + 50) WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value, bytes";
$innerDeleteSql174 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdateSql174 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry174', option_value || ':retry174', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry174', 'pending_theme') AS pending_retry ORDER BY option_id";
$retryDeleteSql174 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$outer174 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql174, $tables174, 'option_id', $unique174);
$innerReplace174 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerReplaceSql174, $outer174()['tables'], 'option_id', $unique174);
$innerDelete174 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDeleteSql174, $innerReplace174()['tables'], 'option_id', $unique174);
$retry174 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql174, $tables174, 'option_id', $unique174);
$plan174 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext174(
    $tables174,
    [$outerSql174],
    [$innerReplaceSql174, $innerDeleteSql174],
    [$retryUpdateSql174, $retryDeleteSql174],
    $unique174,
);

$cases174 = [
    'parser outer row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($outerSql174)['assignments']), ['status', 'option_value', 'bytes']],
    'parser inner replace action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerReplaceSql174)['conflict_action'], 'replace'],
    'parser delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDeleteSql174)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'outer selected ids' => [static fn (): mixed => $outer174()['plan']->selectedIds, [7, 8]],
    'outer returning ids' => [static fn (): mixed => array_column($outer174()['returning'], 'option_id'), [7, 8]],
    'outer returning predicate true for row seven' => [static fn (): mixed => $outer174()['returning'][0]['pending_outer'], 1],
    'outer current row seven status' => [static fn (): mixed => array_column($outer174()['tables']['wp_options'], 'status', 'option_id')[7], 'outer174'],
    'outer current row eight value' => [static fn (): mixed => array_column($outer174()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:outer174'],
    'inner replace deletes conflicting row ten' => [static fn (): mixed => array_column($innerReplace174()['deleted_conflict_rows'], 'option_id'), [10]],
    'inner replace returns row seven' => [static fn (): mixed => array_column($innerReplace174()['returning'], 'option_id'), [7]],
    'inner replace row seven released key' => [static fn (): mixed => array_column($innerReplace174()['tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'inner delete selected transient ids' => [static fn (): mixed => $innerDelete174()['plan']->selectedIds, [3, 4]],
    'inner delete returning transient ids' => [static fn (): mixed => array_column($innerDelete174()['returning'], 'option_id'), [3, 4]],
    'inner released image removes row ten' => [static fn (): mixed => in_array(10, array_column($innerDelete174()['tables']['wp_options'], 'option_id'), true), false],
    'retry starts from original row seven value' => [static fn (): mixed => $retry174()['returning'][0]['option_value'], 'theme:retry174'],

    'plan status' => [static fn (): mixed => $plan174()['status'], 'inner-released-outer-rollback-to-retry-current-source-next174'],
    'plan outer savepoint' => [static fn (): mixed => $plan174()['outer_savepoint'], 'wp_options_outer_rowvalue_next174'],
    'plan inner savepoint' => [static fn (): mixed => $plan174()['inner_savepoint'], 'wp_options_inner_rowvalue_next174'],
    'plan inner released into outer' => [static fn (): mixed => $plan174()['inner_released_into_outer'], true],
    'plan rolled back to outer' => [static fn (): mixed => $plan174()['rolled_back_to_outer_savepoint'], true],
    'plan outer preserved after rollback to' => [static fn (): mixed => $plan174()['outer_savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan174()['released_after_retry'], true],
    'plan outer image original table' => [static fn (): mixed => $plan174()['outer_savepoint_image_tables'], $tables174],
    'plan inner image includes outer row seven status' => [static fn (): mixed => array_column($plan174()['inner_savepoint_image_tables']['wp_options'], 'status', 'option_id')[7], 'outer174'],
    'plan inner image includes outer row eight value' => [static fn (): mixed => array_column($plan174()['inner_savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:outer174'],
    'plan released inner current row seven key' => [static fn (): mixed => array_column($plan174()['released_inner_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'plan released inner current deleted transients absent' => [static fn (): mixed => array_intersect([3, 4], array_column($plan174()['released_inner_current_source_tables']['wp_options'], 'option_id')), []],
    'plan rollback to outer restores original row seven status' => [static fn (): mixed => array_column($plan174()['rollback_to_outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback to outer restores row ten' => [static fn (): mixed => in_array(10, array_column($plan174()['rollback_to_outer_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan discarded outer returning count' => [static fn (): mixed => $plan174()['discarded_outer_returning_count'], 2],
    'plan discarded inner released returning count' => [static fn (): mixed => $plan174()['discarded_inner_released_returning_count'], 3],
    'plan yielded retry count' => [static fn (): mixed => $plan174()['yielded_retry_returning_count'], 4],
    'plan discarded outer changes' => [static fn (): mixed => $plan174()['discarded_outer_changes'], 2],
    'plan discarded inner released changes include conflict delete' => [static fn (): mixed => $plan174()['discarded_inner_released_changes'], 4],
    'plan changes after retry' => [static fn (): mixed => $plan174()['changes_after_retry'], 4],
    'plan outer statement phase' => [static fn (): mixed => $plan174()['outer_statements'][0]['phase'], 'outer-before-inner'],
    'plan inner statement phases' => [static fn (): mixed => array_column($plan174()['inner_released_statements'], 'phase'), ['inner-before-release', 'inner-before-release']],
    'plan retry phases' => [static fn (): mixed => array_column($plan174()['retry_statements'], 'phase'), ['after-outer-rollback', 'after-outer-rollback']],
    'plan outer source rows original statuses' => [static fn (): mixed => array_column($plan174()['outer_statements'][0]['source_rows'], 'status'), [null, 'queued']],
    'plan inner replace source sees outer row seven' => [static fn (): mixed => $plan174()['inner_released_statements'][0]['source_rows'][0]['status'], 'outer174'],
    'plan retry source sees original row seven' => [static fn (): mixed => $plan174()['retry_statements'][0]['source_rows'][0]['status'], null],
    'plan retry update ids' => [static fn (): mixed => array_column($plan174()['yielded_retry_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan retry delete ids' => [static fn (): mixed => array_column($plan174()['yielded_retry_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan final row seven retry value' => [static fn (): mixed => array_column($plan174()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry174'],
    'plan final row nine retry status' => [static fn (): mixed => array_column($plan174()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry174'],
    'plan final row eight original status restored' => [static fn (): mixed => array_column($plan174()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan final row ten restored' => [static fn (): mixed => array_column($plan174()['current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan final deletes transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan174()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan174()['next_source_tables'], $plan174()['current_source_tables']],
    'plan row count after retry' => [static fn (): mixed => $plan174()['row_counts']['wp_options'], 8],
    'plan changed tables after retry' => [static fn (): mixed => $plan174()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency release propagation' => [static fn (): mixed => in_array('sqlite-release-inner-savepoint-propagates-rowvalue-returning-to-outer-next174', $plan174()['dependencies'], true), true],
    'plan dependency outer rollback discard' => [static fn (): mixed => in_array('sqlite-rollback-to-outer-savepoint-discards-released-inner-rowvalue-effects-next174', $plan174()['dependencies'], true), true],
    'plan dependency retry from outer image' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-retry-starts-from-outer-image-next174', $plan174()['dependencies'], true), true],

    'malformed empty outer statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext174($tables174, [], [$innerDeleteSql174], [$retryUpdateSql174], $unique174), InvalidArgumentException::class],
    'malformed empty inner statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext174($tables174, [$outerSql174], [], [$retryUpdateSql174], $unique174), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext174($tables174, [$outerSql174], [$innerDeleteSql174], [], $unique174), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext174($tables174, [$outerSql174], [$innerDeleteSql174], [$retryUpdateSql174], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext174(['wp_options' => ['bad']], [$outerSql174], [$innerDeleteSql174], [$retryUpdateSql174], $unique174), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases174 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next174 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
