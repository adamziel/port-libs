<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows232 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'theme_mods_twenty', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 13, 'option_value' => 'theme-mods'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'timeout'],
    ['option_id' => 10, 'blog_id' => 5, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];
$targets232 = [
    ['target_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'yield', 'priority' => 10],
    ['target_id' => 2, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'action' => 'yield', 'priority' => 20],
    ['target_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'action' => 'yield_delete', 'priority' => 30],
    ['target_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'attempt', 'priority' => 40],
    ['target_id' => 5, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'action' => 'attempt', 'priority' => 50],
    ['target_id' => 6, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'action' => 'attempt_delete', 'priority' => 60],
    ['target_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'action' => 'retry', 'priority' => 70],
    ['target_id' => 8, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'action' => 'retry', 'priority' => 80],
    ['target_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'action' => 'retry', 'priority' => 90],
    ['target_id' => 10, 'blog_id' => 4, 'option_name' => 'theme_mods_twenty', 'action' => 'retry', 'priority' => 100],
    ['target_id' => 11, 'blog_id' => 1, 'option_name' => '_transient_feed', 'action' => 'retry_delete', 'priority' => 110],
    ['target_id' => 12, 'blog_id' => 4, 'option_name' => '_transient_timeout_feed', 'action' => 'retry_delete', 'priority' => 120],
];

$tables232 = ['wp_options' => $rows232, 'wp_import_targets' => $targets232];
$unique232 = [['blog_id', 'option_name']];

$yieldUpdate232 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield232', option_value || ':yield232', bytes + 3) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield' ORDER BY priority LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield') AS yielded_by_select ORDER BY option_id";
$yieldDelete232 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield_delete' ORDER BY priority LIMIT 1) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'yield_delete') AS yielded_delete ORDER BY option_id";
$attemptUpdate232 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt232', option_value || ':attempt232', bytes + 5) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'attempt' ORDER BY priority) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IN (('attempt232', 'pending_theme'), ('attempt232', 'rewrite_rules')) AS attempted_tuple ORDER BY option_id DESC";
$attemptDelete232 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'attempt_delete' ORDER BY priority) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((3, 'orphaned_cache')) AS attempted_keep ORDER BY option_id";
$retryUpdate232 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry232', option_value || ':retry232', bytes + 1) WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry' ORDER BY priority LIMIT 4) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry232', 'rewrite_rules') AS retry_rewrite ORDER BY option_id";
$retryDelete232 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_import_targets WHERE action = 'retry_delete' ORDER BY priority LIMIT 2) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((4, '_transient_timeout_feed')) AS retry_keep ORDER BY option_id";

$yieldUpdateResult232 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldUpdate232, $tables232, 'option_id', $unique232);
$yieldDeleteResult232 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldDelete232, $yieldUpdateResult232()['tables'], 'option_id', $unique232);
$attemptUpdateResult232 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate232, $yieldDeleteResult232()['tables'], 'option_id', $unique232);
$attemptDeleteResult232 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete232, $attemptUpdateResult232()['tables'], 'option_id', $unique232);
$retryUpdateResult232 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate232, $tables232, 'option_id', $unique232);
$retryDeleteResult232 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete232, $retryUpdateResult232()['tables'], 'option_id', $unique232);
$plan232 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan(
    $tables232,
    [$yieldUpdate232, $yieldDelete232],
    [$attemptUpdate232, $attemptDelete232],
    [$retryUpdate232, $retryDelete232],
    $unique232,
);
$customPlan232 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan(
    $tables232,
    [$yieldUpdate232],
    [$attemptUpdate232],
    [$retryUpdate232],
    $unique232,
    'custom_rowvalue_window_232',
);

$cases232 = [
    'parser yield update select source' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($yieldUpdate232)['where'] ?? '', "action = 'yield'"), true],
    'parser yield delete select source' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($yieldDelete232)['where'] ?? '', "action = 'yield_delete'"), true],
    'parser attempt order by desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate232)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete232)['returning'], 'retry_keep'), true],
    'yield update selected ids' => [static fn (): mixed => $yieldUpdateResult232()['plan']->selectedIds, [4, 5]],
    'yield update returning ids' => [static fn (): mixed => array_column($yieldUpdateResult232()['returning'], 'option_id'), [4, 5]],
    'yield update flags' => [static fn (): mixed => array_column($yieldUpdateResult232()['returning'], 'yielded_by_select'), [1, 1]],
    'yield update row four value' => [static fn (): mixed => array_column($yieldUpdateResult232()['returning'], 'option_value', 'option_id')[4], 'theme:yield232'],
    'yield update target table preserved' => [static fn (): mixed => count($yieldUpdateResult232()['tables']['wp_import_targets']), 12],
    'yield delete selected id' => [static fn (): mixed => $yieldDeleteResult232()['plan']->selectedIds, [3]],
    'yield delete returning flag' => [static fn (): mixed => array_column($yieldDeleteResult232()['returning'], 'yielded_delete'), [1]],
    'yield delete removes transient' => [static fn (): mixed => in_array(3, array_column($yieldDeleteResult232()['tables']['wp_options'], 'option_id'), true), false],
    'attempt update selected ids desc plan' => [static fn (): mixed => $attemptUpdateResult232()['plan']->selectedIds, [5, 4]],
    'attempt update returning table order' => [static fn (): mixed => array_column($attemptUpdateResult232()['returning'], 'option_id'), [4, 5]],
    'attempt update chained values' => [static fn (): mixed => array_column($attemptUpdateResult232()['returning'], 'option_value', 'option_id')[4], 'theme:yield232:attempt232'],
    'attempt update tuple flags' => [static fn (): mixed => array_column($attemptUpdateResult232()['returning'], 'attempted_tuple'), [1, 1]],
    'attempt delete selected orphan' => [static fn (): mixed => $attemptDeleteResult232()['plan']->selectedIds, [7]],
    'attempt delete returning false keep' => [static fn (): mixed => array_column($attemptDeleteResult232()['returning'], 'attempted_keep'), [0]],
    'attempt delete removes orphan before rollback' => [static fn (): mixed => in_array(7, array_column($attemptDeleteResult232()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected original ids' => [static fn (): mixed => $retryUpdateResult232()['plan']->selectedIds, [4, 5, 6, 8]],
    'retry update row four retry only' => [static fn (): mixed => array_column($retryUpdateResult232()['returning'], 'option_value', 'option_id')[4], 'theme:retry232'],
    'retry update row eight included' => [static fn (): mixed => array_column($retryUpdateResult232()['returning'], 'option_value', 'option_id')[8], 'theme-mods:retry232'],
    'retry update rewrite flag' => [static fn (): mixed => array_column($retryUpdateResult232()['returning'], 'retry_rewrite'), [0, 1, 0, 0]],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult232()['plan']->selectedIds, [3, 9]],
    'retry delete flags' => [static fn (): mixed => array_column($retryDeleteResult232()['returning'], 'retry_keep'), [1, 0]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult232()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 10]],
    'plan status' => [static fn (): mixed => $plan232()['status'], 'rowvalue-update-delete-returning-window-current-source-next232'],
    'plan savepoint' => [static fn (): mixed => $plan232()['savepoint'], 'app_settings_rowvalue_window_current_next232'],
    'plan base subquery flag' => [static fn (): mixed => $plan232()['rowvalue_subquery_targets_next229'], true],
    'plan window flag' => [static fn (): mixed => $plan232()['window_current_source_next232'], true],
    'plan rollback flag' => [static fn (): mixed => $plan232()['rollback_to_savepoint_next229'], true],
    'plan release flag' => [static fn (): mixed => $plan232()['release_commits_retry_next229'], true],
    'plan yielded rows survive flag' => [static fn (): mixed => $plan232()['yielded_rows_survive_rollback_next229'], true],
    'plan retry image flag' => [static fn (): mixed => $plan232()['retry_reads_savepoint_image_next229'], true],
    'plan yield current row four' => [static fn (): mixed => array_column($plan232()['yield_current_source_tables']['wp_options'], 'status', 'option_id')[4], 'yield232'],
    'plan attempt current row four chained' => [static fn (): mixed => array_column($plan232()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[4], 'theme:yield232:attempt232'],
    'plan attempt deletes orphan' => [static fn (): mixed => in_array(7, array_column($plan232()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores original image' => [static fn (): mixed => $plan232()['rollback_current_source_tables'], $plan232()['savepoint_image_tables']],
    'plan current row four retry only' => [static fn (): mixed => array_column($plan232()['current_source_tables']['wp_options'], 'option_value', 'option_id')[4], 'theme:retry232'],
    'plan current row five retry only' => [static fn (): mixed => array_column($plan232()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'rules:retry232'],
    'plan current orphan restored' => [static fn (): mixed => array_column($plan232()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'orphaned'],
    'plan current transient feed deleted' => [static fn (): mixed => in_array(3, array_column($plan232()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current timeout deleted' => [static fn (): mixed => in_array(9, array_column($plan232()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan232()['next_source_tables'], $plan232()['current_source_tables']],
    'plan yielded ids' => [static fn (): mixed => array_column($plan232()['yielded_rows_before_rollback'], 'option_id'), [4, 5, 3]],
    'plan suppressed ids' => [static fn (): mixed => array_column($plan232()['suppressed_rows_after_rollback'], 'option_id'), [4, 5, 7]],
    'plan retry ids' => [static fn (): mixed => array_column($plan232()['retry_rows_after_release'], 'option_id'), [4, 5, 6, 8, 3, 9]],
    'plan window ids' => [static fn (): mixed => $plan232()['window_retry_ids_after_release_next232'], [4, 5, 6, 8, 3, 9]],
    'plan window row numbers' => [static fn (): mixed => $plan232()['window_retry_row_numbers_next232'], [1, 2, 3, 4, 5, 6]],
    'plan window partition numbers' => [static fn (): mixed => $plan232()['window_retry_partition_numbers_next232'], [1, 2, 3, 4, 1, 2]],
    'plan window partition keys' => [static fn (): mixed => array_column($plan232()['window_retry_rows_after_release_next232'], 'partition_key'), ['retry232', 'retry232', 'retry232', 'retry232', 'stale', 'stale']],
    'plan current source order ids' => [static fn (): mixed => array_column($plan232()['current_source_window_order_next232'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 10]],
    'plan current source ordinals' => [static fn (): mixed => array_column($plan232()['current_source_window_order_next232'], 'ordinal'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'plan counts' => [static fn (): mixed => [$plan232()['yielded_returning_count'], $plan232()['suppressed_returning_count'], $plan232()['retry_returning_count']], [3, 3, 6]],
    'plan change counts' => [static fn (): mixed => [$plan232()['yield_change_count'], $plan232()['attempt_change_count'], $plan232()['retry_change_count']], [3, 3, 6]],
    'plan changed tables' => [static fn (): mixed => $plan232()['changed_tables_after_release'], ['wp_options']],
    'plan row counts' => [static fn (): mixed => $plan232()['row_counts'], ['wp_options' => 8, 'wp_import_targets' => 12]],
    'plan receipt retry ids' => [static fn (): mixed => $plan232()['release_receipt_next229']['retry_ids'], [4, 5, 6, 8, 3, 9]],
    'plan dependency next232' => [static fn (): mixed => in_array('sqlite-rowvalue-update-returning-window-current-source-next232', $plan232()['dependencies_next232'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan232()['dependency_closure_next232'], 'no new support component needed'), true],
    'plan non overlap mentions next229' => [static fn (): mixed => str_contains($plan232()['non_overlap_next232'], 'next229'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan232()['savepoint'], 'custom_rowvalue_window_232'],
    'custom window ids' => [static fn (): mixed => $customPlan232()['window_retry_ids_after_release_next232'], [4, 5, 6, 8]],
    'custom window row numbers' => [static fn (): mixed => $customPlan232()['window_retry_row_numbers_next232'], [1, 2, 3, 4]],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan($tables232, [], [$attemptUpdate232], [$retryUpdate232], $unique232), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan($tables232, [$yieldUpdate232], [], [$retryUpdate232], $unique232), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan($tables232, [$yieldUpdate232], [$attemptUpdate232], [], $unique232), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan($tables232, [$yieldUpdate232], [$attemptUpdate232], [$retryUpdate232], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan($tables232, [$yieldUpdate232], [$attemptUpdate232], [$retryUpdate232], $unique232, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRetryWindowPlan(['wp_options' => ['bad']], [$yieldUpdate232], [$attemptUpdate232], [$retryUpdate232], $unique232), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases232 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next232 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
