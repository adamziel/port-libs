<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];
$targets = [
    ['target_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'yield', 'priority' => 10],
    ['target_id' => 2, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'action' => 'yield', 'priority' => 20],
    ['target_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'action' => 'delete_yield', 'priority' => 30],
    ['target_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'attempt', 'priority' => 40],
    ['target_id' => 5, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'action' => 'attempt', 'priority' => 50],
    ['target_id' => 6, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'action' => 'delete_attempt', 'priority' => 60],
    ['target_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'retry', 'priority' => 70],
    ['target_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'action' => 'retry', 'priority' => 80],
    ['target_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'action' => 'retry', 'priority' => 90],
    ['target_id' => 10, 'blog_id' => 1, 'option_name' => '_transient_feed', 'action' => 'delete_retry', 'priority' => 100],
    ['target_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'action' => 'delete_retry', 'priority' => 110],
];

$tables = ['wp_options' => $rows, 'wp_import_targets' => $targets];
$unique = [['blog_id', 'option_name']];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield', option_value || ':yield', bytes + 3) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY target_id LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY target_id LIMIT 2) AS yielded_by_select ORDER BY option_id";
$yieldDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_yield' ORDER BY target_id LIMIT 1) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_yield') AS deleted_by_select ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt', option_value || ':attempt', bytes + 5) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'attempt' ORDER BY target_id) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IN (('attempt', 'pending_theme'), ('attempt', 'rewrite_rules')) AS attempted_tuple ORDER BY option_id DESC";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_attempt' ORDER BY target_id) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS attempted_keep ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 1) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry' ORDER BY target_id LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'rewrite_rules') AS retry_rewrite ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_retry' ORDER BY target_id LIMIT 2) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((4, 'home')) AS retry_keep ORDER BY option_id";

$yieldUpdateResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldUpdate, $tables, 'option_id', $unique);
$yieldDeleteResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldDelete, $yieldUpdateResult()['tables'], 'option_id', $unique);
$attemptUpdateResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate, $yieldDeleteResult()['tables'], 'option_id', $unique);
$attemptDeleteResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete, $attemptUpdateResult()['tables'], 'option_id', $unique);
$retryUpdateResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate, $tables, 'option_id', $unique);
$retryDeleteResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete, $retryUpdateResult()['tables'], 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease(
    $tables,
    [$yieldUpdate, $yieldDelete],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease(
    $tables,
    [$yieldUpdate],
    [$attemptUpdate],
    [$retryUpdate],
    $unique,
    'custom_rowvalue_select',
);

$cases = [
    'parser yield update has select subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($yieldUpdate)['where'] ?? '', 'SELECT blog_id'), true],
    'parser yield delete has select subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($yieldDelete)['where'] ?? '', "action = 'delete_yield'"), true],
    'parser attempt update keeps order by desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete)['returning'], 'retry_keep'), true],
    'yield update selected ids from target subquery' => [static fn (): mixed => $yieldUpdateResult()['plan']->selectedIds, [5, 7]],
    'yield update returning ids' => [static fn (): mixed => array_column($yieldUpdateResult()['returning'], 'option_id'), [5, 7]],
    'yield update select flags' => [static fn (): mixed => array_column($yieldUpdateResult()['returning'], 'yielded_by_select'), [1, 1]],
    'yield update row five value' => [static fn (): mixed => array_column($yieldUpdateResult()['returning'], 'option_value', 'option_id')[5], 'theme:yield'],
    'yield update target table preserved' => [static fn (): mixed => count($yieldUpdateResult()['tables']['wp_import_targets']), 11],
    'yield delete selected id from target subquery' => [static fn (): mixed => $yieldDeleteResult()['plan']->selectedIds, [3]],
    'yield delete returning id' => [static fn (): mixed => array_column($yieldDeleteResult()['returning'], 'option_id'), [3]],
    'yield delete select flag' => [static fn (): mixed => array_column($yieldDeleteResult()['returning'], 'deleted_by_select'), [1]],
    'yield delete removes transient before attempt' => [static fn (): mixed => in_array(3, array_column($yieldDeleteResult()['tables']['wp_options'], 'option_id'), true), false],
    'attempt update selected ids after yield' => [static fn (): mixed => $attemptUpdateResult()['plan']->selectedIds, [7, 5]],
    'attempt update returning desc selection order becomes table order' => [static fn (): mixed => array_column($attemptUpdateResult()['returning'], 'option_id'), [5, 7]],
    'attempt update selected ids preserve desc plan' => [static fn (): mixed => $attemptUpdateResult()['plan']->selectedIds, [7, 5]],
    'attempt update chained value suppressed later' => [static fn (): mixed => array_column($attemptUpdateResult()['returning'], 'option_value', 'option_id')[5], 'theme:yield:attempt'],
    'attempt update tuple flags' => [static fn (): mixed => array_column($attemptUpdateResult()['returning'], 'attempted_tuple'), [1, 1]],
    'attempt delete selected id from subquery' => [static fn (): mixed => $attemptDeleteResult()['plan']->selectedIds, [8]],
    'attempt delete returning id' => [static fn (): mixed => array_column($attemptDeleteResult()['returning'], 'option_id'), [8]],
    'attempt delete flag false for orphan' => [static fn (): mixed => array_column($attemptDeleteResult()['returning'], 'attempted_keep'), [0]],
    'attempt delete row eight absent before rollback' => [static fn (): mixed => in_array(8, array_column($attemptDeleteResult()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids from original image' => [static fn (): mixed => $retryUpdateResult()['plan']->selectedIds, [5, 7, 9]],
    'retry update row five has retry only' => [static fn (): mixed => array_column($retryUpdateResult()['returning'], 'option_value', 'option_id')[5], 'theme:retry'],
    'retry update row nine included by subquery' => [static fn (): mixed => array_column($retryUpdateResult()['returning'], 'option_value', 'option_id')[9], 'plugin:retry'],
    'retry update rewrite flag' => [static fn (): mixed => array_column($retryUpdateResult()['returning'], 'retry_rewrite'), [0, 1, 0]],
    'retry delete selected ids from original image' => [static fn (): mixed => $retryDeleteResult()['plan']->selectedIds, [3, 10]],
    'retry delete returning flags' => [static fn (): mixed => array_column($retryDeleteResult()['returning'], 'retry_keep'), [1, 0]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'plan status' => [static fn (): mixed => $plan()['status'], 'rowvalue-update-delete-returning-subquery-savepoint-release-current-source'],
    'plan savepoint' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_select_retry'],
    'plan subquery flag' => [static fn (): mixed => $plan()['rowvalue_subquery_targets'], true],
    'plan rollback flag' => [static fn (): mixed => $plan()['rollback_to_savepoint'], true],
    'plan release flag' => [static fn (): mixed => $plan()['release_commits_retry'], true],
    'plan yielded survives flag' => [static fn (): mixed => $plan()['yielded_rows_survive_rollback'], true],
    'plan attempted suppressed flag' => [static fn (): mixed => $plan()['attempted_rows_suppressed'], true],
    'plan retry image flag' => [static fn (): mixed => $plan()['retry_reads_savepoint_image'], true],
    'plan savepoint released flag' => [static fn (): mixed => $plan()['savepoint_released'], true],
    'plan yield current row five changed' => [static fn (): mixed => array_column($plan()['yield_current_source_tables']['wp_options'], 'status', 'option_id')[5], 'yield'],
    'plan yield current deleted row three' => [static fn (): mixed => in_array(3, array_column($plan()['yield_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt current row five chained' => [static fn (): mixed => array_column($plan()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'theme:yield:attempt'],
    'plan attempt current row eight deleted' => [static fn (): mixed => in_array(8, array_column($plan()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores original image' => [static fn (): mixed => $plan()['rollback_current_source_tables'], $plan()['savepoint_image_tables']],
    'plan current row five retry only' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'theme:retry'],
    'plan current row seven retry only' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'rules:retry'],
    'plan current row eight restored' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned'],
    'plan current row three deleted by retry' => [static fn (): mixed => in_array(3, array_column($plan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan yielded rows ids' => [static fn (): mixed => array_column($plan()['yielded_rows_before_rollback'], 'option_id'), [5, 7, 3]],
    'plan suppressed rows ids' => [static fn (): mixed => array_column($plan()['suppressed_rows_after_rollback'], 'option_id'), [5, 7, 8]],
    'plan retry rows ids' => [static fn (): mixed => array_column($plan()['retry_rows_after_release'], 'option_id'), [5, 7, 9, 3, 10]],
    'plan yielded count' => [static fn (): mixed => $plan()['yielded_returning_count'], 3],
    'plan suppressed count' => [static fn (): mixed => $plan()['suppressed_returning_count'], 3],
    'plan retry count' => [static fn (): mixed => $plan()['retry_returning_count'], 5],
    'plan yield change count' => [static fn (): mixed => $plan()['yield_change_count'], 3],
    'plan attempt change count' => [static fn (): mixed => $plan()['attempt_change_count'], 3],
    'plan retry change count' => [static fn (): mixed => $plan()['retry_change_count'], 5],
    'plan statement phases' => [static fn (): mixed => array_column($plan()['yield_statements'], 'phase'), ['yield-subquery-before-rollback-to-savepoint', 'yield-subquery-before-rollback-to-savepoint']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan()['attempt_statements'], 'phase'), ['attempt-subquery-after-yield-before-rollback-to-savepoint', 'attempt-subquery-after-yield-before-rollback-to-savepoint']],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['retry-subquery-after-rollback-release', 'retry-subquery-after-rollback-release']],
    'plan source rows came from savepoint image on retry' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'status'), ['queued', 'queued', 'queued']],
    'plan changed tables after release' => [static fn (): mixed => $plan()['changed_tables_after_release'], ['wp_options']],
    'plan wp options row count after release' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 8],
    'plan target row count preserved' => [static fn (): mixed => $plan()['row_counts']['wp_import_targets'], 11],
    'plan receipt yielded ids' => [static fn (): mixed => $plan()['release_receipt']['yielded_ids'], [5, 7, 3]],
    'plan receipt suppressed ids' => [static fn (): mixed => $plan()['release_receipt']['suppressed_ids'], [5, 7, 8]],
    'plan receipt retry ids' => [static fn (): mixed => $plan()['release_receipt']['retry_ids'], [5, 7, 9, 3, 10]],
    'plan receipt released tables' => [static fn (): mixed => $plan()['release_receipt']['released_tables'], ['wp_options', 'wp_import_targets']],
    'plan dependency subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-in-select-update-delete-returning', $plan()['dependencies'], true), true],
    'plan dependency release' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-rollback-to-release-retry', $plan()['dependencies'], true), true],
    'plan dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-select-savepoint-release-current-source', $plan()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'plan non overlap mentions yield-only rollback fencing' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'yield-only rollback fencing'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan()['savepoint'], 'custom_rowvalue_select'],
    'custom yielded count' => [static fn (): mixed => $customPlan()['yielded_returning_count'], 2],
    'custom suppressed count' => [static fn (): mixed => $customPlan()['suppressed_returning_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan()['retry_returning_count'], 3],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease($tables, [], [$attemptUpdate], [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease($tables, [$yieldUpdate], [], [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease($tables, [$yieldUpdate], [$attemptUpdate], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease($tables, [$yieldUpdate], [$attemptUpdate], [$retryUpdate], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease($tables, [$yieldUpdate], [$attemptUpdate], [$retryUpdate], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease(['wp_options' => ['bad']], [$yieldUpdate], [$attemptUpdate], [$retryUpdate], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue select retry savepoint release ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
