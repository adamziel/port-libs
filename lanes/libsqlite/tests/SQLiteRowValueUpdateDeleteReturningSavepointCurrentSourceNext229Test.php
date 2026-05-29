<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows229 = [
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
$targets229 = [
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

$tables229 = ['wp_options' => $rows229, 'wp_import_targets' => $targets229];
$unique229 = [['blog_id', 'option_name']];

$yieldUpdate229 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield229', option_value || ':yield229', bytes + 3) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY target_id LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY target_id LIMIT 2) AS yielded_by_select ORDER BY option_id";
$yieldDelete229 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_yield' ORDER BY target_id LIMIT 1) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_yield') AS deleted_by_select ORDER BY option_id";
$attemptUpdate229 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt229', option_value || ':attempt229', bytes + 5) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'attempt' ORDER BY target_id) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IN (('attempt229', 'pending_theme'), ('attempt229', 'rewrite_rules')) AS attempted_tuple ORDER BY option_id DESC";
$attemptDelete229 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_attempt' ORDER BY target_id) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS attempted_keep ORDER BY option_id";
$retryUpdate229 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry229', option_value || ':retry229', bytes + 1) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry' ORDER BY target_id LIMIT 3) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry229', 'rewrite_rules') AS retry_rewrite ORDER BY option_id";
$retryDelete229 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'delete_retry' ORDER BY target_id LIMIT 2) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((4, 'home')) AS retry_keep ORDER BY option_id";

$yieldUpdateResult229 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldUpdate229, $tables229, 'option_id', $unique229);
$yieldDeleteResult229 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldDelete229, $yieldUpdateResult229()['tables'], 'option_id', $unique229);
$attemptUpdateResult229 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate229, $yieldDeleteResult229()['tables'], 'option_id', $unique229);
$attemptDeleteResult229 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete229, $attemptUpdateResult229()['tables'], 'option_id', $unique229);
$retryUpdateResult229 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate229, $tables229, 'option_id', $unique229);
$retryDeleteResult229 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete229, $retryUpdateResult229()['tables'], 'option_id', $unique229);
$plan229 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease(
    $tables229,
    [$yieldUpdate229, $yieldDelete229],
    [$attemptUpdate229, $attemptDelete229],
    [$retryUpdate229, $retryDelete229],
    $unique229,
);
$customPlan229 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease(
    $tables229,
    [$yieldUpdate229],
    [$attemptUpdate229],
    [$retryUpdate229],
    $unique229,
    'custom_rowvalue_select_229',
);

$cases229 = [
    'parser yield update has select subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($yieldUpdate229)['where'] ?? '', 'SELECT blog_id'), true],
    'parser yield delete has select subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($yieldDelete229)['where'] ?? '', "action = 'delete_yield'"), true],
    'parser attempt update keeps order by desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate229)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete229)['returning'], 'retry_keep'), true],
    'yield update selected ids from target subquery' => [static fn (): mixed => $yieldUpdateResult229()['plan']->selectedIds, [5, 7]],
    'yield update returning ids' => [static fn (): mixed => array_column($yieldUpdateResult229()['returning'], 'option_id'), [5, 7]],
    'yield update select flags' => [static fn (): mixed => array_column($yieldUpdateResult229()['returning'], 'yielded_by_select'), [1, 1]],
    'yield update row five value' => [static fn (): mixed => array_column($yieldUpdateResult229()['returning'], 'option_value', 'option_id')[5], 'theme:yield229'],
    'yield update target table preserved' => [static fn (): mixed => count($yieldUpdateResult229()['tables']['wp_import_targets']), 11],
    'yield delete selected id from target subquery' => [static fn (): mixed => $yieldDeleteResult229()['plan']->selectedIds, [3]],
    'yield delete returning id' => [static fn (): mixed => array_column($yieldDeleteResult229()['returning'], 'option_id'), [3]],
    'yield delete select flag' => [static fn (): mixed => array_column($yieldDeleteResult229()['returning'], 'deleted_by_select'), [1]],
    'yield delete removes transient before attempt' => [static fn (): mixed => in_array(3, array_column($yieldDeleteResult229()['tables']['wp_options'], 'option_id'), true), false],
    'attempt update selected ids after yield' => [static fn (): mixed => $attemptUpdateResult229()['plan']->selectedIds, [7, 5]],
    'attempt update returning desc selection order becomes table order' => [static fn (): mixed => array_column($attemptUpdateResult229()['returning'], 'option_id'), [5, 7]],
    'attempt update selected ids preserve desc plan' => [static fn (): mixed => $attemptUpdateResult229()['plan']->selectedIds, [7, 5]],
    'attempt update chained value suppressed later' => [static fn (): mixed => array_column($attemptUpdateResult229()['returning'], 'option_value', 'option_id')[5], 'theme:yield229:attempt229'],
    'attempt update tuple flags' => [static fn (): mixed => array_column($attemptUpdateResult229()['returning'], 'attempted_tuple'), [1, 1]],
    'attempt delete selected id from subquery' => [static fn (): mixed => $attemptDeleteResult229()['plan']->selectedIds, [8]],
    'attempt delete returning id' => [static fn (): mixed => array_column($attemptDeleteResult229()['returning'], 'option_id'), [8]],
    'attempt delete flag false for orphan' => [static fn (): mixed => array_column($attemptDeleteResult229()['returning'], 'attempted_keep'), [0]],
    'attempt delete row eight absent before rollback' => [static fn (): mixed => in_array(8, array_column($attemptDeleteResult229()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids from original image' => [static fn (): mixed => $retryUpdateResult229()['plan']->selectedIds, [5, 7, 9]],
    'retry update row five has retry only' => [static fn (): mixed => array_column($retryUpdateResult229()['returning'], 'option_value', 'option_id')[5], 'theme:retry229'],
    'retry update row nine included by subquery' => [static fn (): mixed => array_column($retryUpdateResult229()['returning'], 'option_value', 'option_id')[9], 'plugin:retry229'],
    'retry update rewrite flag' => [static fn (): mixed => array_column($retryUpdateResult229()['returning'], 'retry_rewrite'), [0, 1, 0]],
    'retry delete selected ids from original image' => [static fn (): mixed => $retryDeleteResult229()['plan']->selectedIds, [3, 10]],
    'retry delete returning flags' => [static fn (): mixed => array_column($retryDeleteResult229()['returning'], 'retry_keep'), [1, 0]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult229()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'plan status' => [static fn (): mixed => $plan229()['status'], 'rowvalue-update-delete-returning-subquery-savepoint-release-current-source-next229'],
    'plan savepoint' => [static fn (): mixed => $plan229()['savepoint'], 'wp_options_rowvalue_select_retry_next229'],
    'plan subquery flag' => [static fn (): mixed => $plan229()['rowvalue_subquery_targets_next229'], true],
    'plan rollback flag' => [static fn (): mixed => $plan229()['rollback_to_savepoint_next229'], true],
    'plan release flag' => [static fn (): mixed => $plan229()['release_commits_retry_next229'], true],
    'plan yielded survives flag' => [static fn (): mixed => $plan229()['yielded_rows_survive_rollback_next229'], true],
    'plan attempted suppressed flag' => [static fn (): mixed => $plan229()['attempted_rows_suppressed_next229'], true],
    'plan retry image flag' => [static fn (): mixed => $plan229()['retry_reads_savepoint_image_next229'], true],
    'plan savepoint released flag' => [static fn (): mixed => $plan229()['savepoint_released_next229'], true],
    'plan yield current row five changed' => [static fn (): mixed => array_column($plan229()['yield_current_source_tables']['wp_options'], 'status', 'option_id')[5], 'yield229'],
    'plan yield current deleted row three' => [static fn (): mixed => in_array(3, array_column($plan229()['yield_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt current row five chained' => [static fn (): mixed => array_column($plan229()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'theme:yield229:attempt229'],
    'plan attempt current row eight deleted' => [static fn (): mixed => in_array(8, array_column($plan229()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores original image' => [static fn (): mixed => $plan229()['rollback_current_source_tables'], $plan229()['savepoint_image_tables']],
    'plan current row five retry only' => [static fn (): mixed => array_column($plan229()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'theme:retry229'],
    'plan current row seven retry only' => [static fn (): mixed => array_column($plan229()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'rules:retry229'],
    'plan current row eight restored' => [static fn (): mixed => array_column($plan229()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned'],
    'plan current row three deleted by retry' => [static fn (): mixed => in_array(3, array_column($plan229()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan229()['next_source_tables'], $plan229()['current_source_tables']],
    'plan yielded rows ids' => [static fn (): mixed => array_column($plan229()['yielded_rows_before_rollback'], 'option_id'), [5, 7, 3]],
    'plan suppressed rows ids' => [static fn (): mixed => array_column($plan229()['suppressed_rows_after_rollback'], 'option_id'), [5, 7, 8]],
    'plan retry rows ids' => [static fn (): mixed => array_column($plan229()['retry_rows_after_release'], 'option_id'), [5, 7, 9, 3, 10]],
    'plan yielded count' => [static fn (): mixed => $plan229()['yielded_returning_count'], 3],
    'plan suppressed count' => [static fn (): mixed => $plan229()['suppressed_returning_count'], 3],
    'plan retry count' => [static fn (): mixed => $plan229()['retry_returning_count'], 5],
    'plan yield change count' => [static fn (): mixed => $plan229()['yield_change_count'], 3],
    'plan attempt change count' => [static fn (): mixed => $plan229()['attempt_change_count'], 3],
    'plan retry change count' => [static fn (): mixed => $plan229()['retry_change_count'], 5],
    'plan statement phases' => [static fn (): mixed => array_column($plan229()['yield_statements'], 'phase'), ['yield-subquery-before-rollback-to-next229', 'yield-subquery-before-rollback-to-next229']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan229()['attempt_statements'], 'phase'), ['attempt-subquery-after-yield-before-rollback-to-next229', 'attempt-subquery-after-yield-before-rollback-to-next229']],
    'plan retry phases' => [static fn (): mixed => array_column($plan229()['retry_statements'], 'phase'), ['retry-subquery-after-rollback-release-next229', 'retry-subquery-after-rollback-release-next229']],
    'plan source rows came from savepoint image on retry' => [static fn (): mixed => array_column($plan229()['retry_statements'][0]['source_rows'], 'status'), ['queued', 'queued', 'queued']],
    'plan changed tables after release' => [static fn (): mixed => $plan229()['changed_tables_after_release'], ['wp_options']],
    'plan wp options row count after release' => [static fn (): mixed => $plan229()['row_counts']['wp_options'], 8],
    'plan target row count preserved' => [static fn (): mixed => $plan229()['row_counts']['wp_import_targets'], 11],
    'plan receipt yielded ids' => [static fn (): mixed => $plan229()['release_receipt_next229']['yielded_ids'], [5, 7, 3]],
    'plan receipt suppressed ids' => [static fn (): mixed => $plan229()['release_receipt_next229']['suppressed_ids'], [5, 7, 8]],
    'plan receipt retry ids' => [static fn (): mixed => $plan229()['release_receipt_next229']['retry_ids'], [5, 7, 9, 3, 10]],
    'plan receipt released tables' => [static fn (): mixed => $plan229()['release_receipt_next229']['released_tables'], ['wp_options', 'wp_import_targets']],
    'plan dependency subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-in-select-update-delete-returning-next229', $plan229()['dependencies'], true), true],
    'plan dependency release' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-rollback-to-release-retry-next229', $plan229()['dependencies'], true), true],
    'plan dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-select-savepoint-release-current-source-next229', $plan229()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan229()['dependency_closure_next229'], 'no new support component needed'), true],
    'plan non overlap mentions next223' => [static fn (): mixed => str_contains($plan229()['non_overlap_next229'], 'next223'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan229()['savepoint'], 'custom_rowvalue_select_229'],
    'custom yielded count' => [static fn (): mixed => $customPlan229()['yielded_returning_count'], 2],
    'custom suppressed count' => [static fn (): mixed => $customPlan229()['suppressed_returning_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan229()['retry_returning_count'], 3],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease($tables229, [], [$attemptUpdate229], [$retryUpdate229], $unique229), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease($tables229, [$yieldUpdate229], [], [$retryUpdate229], $unique229), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease($tables229, [$yieldUpdate229], [$attemptUpdate229], [], $unique229), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease($tables229, [$yieldUpdate229], [$attemptUpdate229], [$retryUpdate229], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease($tables229, [$yieldUpdate229], [$attemptUpdate229], [$retryUpdate229], $unique229, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeSelectRetrySavepointRelease(['wp_options' => ['bad']], [$yieldUpdate229], [$attemptUpdate229], [$retryUpdate229], $unique229), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases229 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next229 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
