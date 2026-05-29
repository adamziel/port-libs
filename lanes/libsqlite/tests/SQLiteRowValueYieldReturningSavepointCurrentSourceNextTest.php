<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows223 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables223 = ['wp_options' => $rows223];
$unique223 = [['blog_id', 'option_name']];

$yieldUpdate223 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield223', option_value || ':yield223', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IS ('yield223', 'pending_theme') AS yielded_pending ORDER BY option_id";
$yieldDelete223 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS yielded_delete ORDER BY option_id";
$attemptUpdate223 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt223', option_value || ':attempt223', bytes + 5) WHERE (status, option_name) IN (('yield223', 'pending_theme'), ('yield223', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) AS attempted_tuple ORDER BY option_id DESC";
$attemptDelete223 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS attempted_keep ORDER BY option_id";
$retryUpdate223 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry223', option_value || ':retry223', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry223', 'rewrite_rules') AS retry_rewrite ORDER BY option_id";
$retryDelete223 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((4, 'home')) AS retry_keep ORDER BY option_id";

$yieldUpdateResult223 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldUpdate223, $tables223, 'option_id', $unique223);
$yieldDeleteResult223 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldDelete223, $yieldUpdateResult223()['tables'], 'option_id', $unique223);
$attemptUpdateResult223 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate223, $yieldDeleteResult223()['tables'], 'option_id', $unique223);
$attemptDeleteResult223 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete223, $attemptUpdateResult223()['tables'], 'option_id', $unique223);
$retryUpdateResult223 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate223, $tables223, 'option_id', $unique223);
$retryDeleteResult223 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete223, $retryUpdateResult223()['tables'], 'option_id', $unique223);
$plan223 = static fn (): array => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute(
    $tables223,
    [$yieldUpdate223, $yieldDelete223],
    [$attemptUpdate223, $attemptDelete223],
    [$retryUpdate223, $retryDelete223],
    $unique223,
);
$customPlan223 = static fn (): array => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute(
    $tables223,
    [$yieldUpdate223],
    [$attemptUpdate223],
    [$retryUpdate223],
    $unique223,
    'wp_custom_yield',
);

$cases223 = [
    'parser yield update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldUpdate223)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser yield delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldDelete223)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'))"],
    'parser attempt update status row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate223)['where'], "(status, option_name) IN (('yield223', 'pending_theme'), ('yield223', 'rewrite_rules'))"],
    'parser attempt delete values where' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDelete223)['where'] ?? '', 'VALUES'), true],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate223)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete223)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (4, 'home'))"],
    'yield update selected ids' => [static fn (): mixed => $yieldUpdateResult223()['plan']->selectedIds, [5, 7]],
    'yield update returning ids' => [static fn (): mixed => array_column($yieldUpdateResult223()['returning'], 'option_id'), [5, 7]],
    'yield update predicate flag' => [static fn (): mixed => array_column($yieldUpdateResult223()['returning'], 'yielded_pending'), [1, 0]],
    'yield update row five value' => [static fn (): mixed => array_column($yieldUpdateResult223()['returning'], 'option_value', 'option_id')[5], 'theme:yield223'],
    'yield delete selected id' => [static fn (): mixed => $yieldDeleteResult223()['plan']->selectedIds, [3]],
    'yield delete returning id' => [static fn (): mixed => array_column($yieldDeleteResult223()['returning'], 'option_id'), [3]],
    'yield delete flag' => [static fn (): mixed => array_column($yieldDeleteResult223()['returning'], 'yielded_delete'), [1]],
    'yield delete removes transient from attempt source' => [static fn (): mixed => in_array(3, array_column($yieldDeleteResult223()['tables']['wp_options'], 'option_id'), true), false],
    'attempt update selected ids after yield' => [static fn (): mixed => $attemptUpdateResult223()['plan']->selectedIds, [7, 5]],
    'attempt update returning table order' => [static fn (): mixed => array_column($attemptUpdateResult223()['returning'], 'option_id'), [5, 7]],
    'attempt update chained value suppressed later' => [static fn (): mixed => array_column($attemptUpdateResult223()['returning'], 'option_value', 'option_id')[5], 'theme:yield223:attempt223'],
    'attempt update tuple flags' => [static fn (): mixed => array_column($attemptUpdateResult223()['returning'], 'attempted_tuple'), [1, 1]],
    'attempt delete selected ids' => [static fn (): mixed => $attemptDeleteResult223()['plan']->selectedIds, [4, 8]],
    'attempt delete returning ids' => [static fn (): mixed => array_column($attemptDeleteResult223()['returning'], 'option_id'), [4, 8]],
    'attempt delete flags' => [static fn (): mixed => array_column($attemptDeleteResult223()['returning'], 'attempted_keep'), [1, 0]],
    'attempt delete row eight suppressed later' => [static fn (): mixed => in_array(8, array_column($attemptDeleteResult223()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids from original image' => [static fn (): mixed => $retryUpdateResult223()['plan']->selectedIds, [5, 7]],
    'retry update row five has retry only' => [static fn (): mixed => array_column($retryUpdateResult223()['returning'], 'option_value', 'option_id')[5], 'theme:retry223'],
    'retry update rewrite flag' => [static fn (): mixed => array_column($retryUpdateResult223()['returning'], 'retry_rewrite'), [0, 1]],
    'retry delete selected ids includes restored transient' => [static fn (): mixed => $retryDeleteResult223()['plan']->selectedIds, [3, 10]],
    'retry delete returning flags' => [static fn (): mixed => array_column($retryDeleteResult223()['returning'], 'retry_keep'), [1, 0]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult223()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'plan status' => [static fn (): mixed => $plan223()['status'], 'rowvalue-update-delete-returning-yield-savepoint-current-source'],
    'plan savepoint' => [static fn (): mixed => $plan223()['savepoint'], 'wp_options_rowvalue_yield'],
    'plan yielded survives flag' => [static fn (): mixed => $plan223()['yielded_rows_survive_rollback'], true],
    'plan attempted suppressed flag' => [static fn (): mixed => $plan223()['attempted_rows_suppressed'], true],
    'plan retry image flag' => [static fn (): mixed => $plan223()['retry_reads_savepoint_image'], true],
    'plan savepoint active flag' => [static fn (): mixed => $plan223()['savepoint_remains_active'], true],
    'plan yield current deleted row three' => [static fn (): mixed => in_array(3, array_column($plan223()['yield_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt current deleted row eight' => [static fn (): mixed => in_array(8, array_column($plan223()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores original image' => [static fn (): mixed => $plan223()['rollback_current_source_tables'], $plan223()['savepoint_image_tables']],
    'plan current row five retry only' => [static fn (): mixed => array_column($plan223()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'theme:retry223'],
    'plan current row seven retry only' => [static fn (): mixed => array_column($plan223()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'rules:retry223'],
    'plan current row eight restored' => [static fn (): mixed => array_column($plan223()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned'],
    'plan current row three deleted by retry' => [static fn (): mixed => in_array(3, array_column($plan223()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan223()['next_source_tables'], $plan223()['current_source_tables']],
    'plan yielded rows ids' => [static fn (): mixed => array_column($plan223()['yielded_rows_before_rollback'], 'option_id'), [5, 7, 3]],
    'plan suppressed rows ids' => [static fn (): mixed => array_column($plan223()['suppressed_rows_after_rollback'], 'option_id'), [5, 7, 4, 8]],
    'plan retry rows ids' => [static fn (): mixed => array_column($plan223()['retry_rows_after_rollback'], 'option_id'), [5, 7, 3, 10]],
    'plan yielded count' => [static fn (): mixed => $plan223()['yielded_returning_count'], 3],
    'plan suppressed count' => [static fn (): mixed => $plan223()['suppressed_returning_count'], 4],
    'plan retry count' => [static fn (): mixed => $plan223()['retry_returning_count'], 4],
    'plan yield change count' => [static fn (): mixed => $plan223()['yield_change_count'], 3],
    'plan attempt change count' => [static fn (): mixed => $plan223()['attempt_change_count'], 4],
    'plan retry change count' => [static fn (): mixed => $plan223()['retry_change_count'], 4],
    'plan statement phases' => [static fn (): mixed => array_column($plan223()['yield_statements'], 'phase'), ['yield-before-rollback-to-savepoint', 'yield-before-rollback-to-savepoint']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan223()['attempt_statements'], 'phase'), ['attempt-after-yield-before-rollback-to-savepoint', 'attempt-after-yield-before-rollback-to-savepoint']],
    'plan retry phases' => [static fn (): mixed => array_column($plan223()['retry_statements'], 'phase'), ['retry-after-yield-rollback-to-savepoint', 'retry-after-yield-rollback-to-savepoint']],
    'plan receipt yielded ids' => [static fn (): mixed => $plan223()['yield_receipt']['yielded_ids'], [5, 7, 3]],
    'plan receipt suppressed ids' => [static fn (): mixed => $plan223()['yield_receipt']['suppressed_ids'], [5, 7, 4, 8]],
    'plan receipt retry ids' => [static fn (): mixed => $plan223()['yield_receipt']['retry_ids'], [5, 7, 3, 10]],
    'plan changed tables after retry' => [static fn (): mixed => $plan223()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan223()['row_counts']['wp_options'], 8],
    'plan dependency yield' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-yield-before-rollback', $plan223()['dependencies'], true), true],
    'plan non overlap names rollback surface' => [static fn (): mixed => str_contains($plan223()['non_overlap'], 'rollback-to-current-source'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan223()['savepoint'], 'wp_custom_yield'],
    'custom yielded count' => [static fn (): mixed => $customPlan223()['yielded_returning_count'], 2],
    'custom suppressed count' => [static fn (): mixed => $customPlan223()['suppressed_returning_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan223()['retry_returning_count'], 2],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute($tables223, [], [$attemptUpdate223], [$retryUpdate223], $unique223), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute($tables223, [$yieldUpdate223], [], [$retryUpdate223], $unique223), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute($tables223, [$yieldUpdate223], [$attemptUpdate223], [], $unique223), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute($tables223, [$yieldUpdate223], [$attemptUpdate223], [$retryUpdate223], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute($tables223, [$yieldUpdate223], [$attemptUpdate223], [$retryUpdate223], $unique223, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$yieldUpdate223], [$attemptUpdate223], [$retryUpdate223], $unique223), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases223 as $name => [$callback, $expected]) {
    $tests['rowvalue yield returning savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
