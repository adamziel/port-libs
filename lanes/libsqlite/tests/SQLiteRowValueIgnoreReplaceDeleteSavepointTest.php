<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows203 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://two.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables203 = ['wp_options' => $rows203];
$unique203 = [['blog_id', 'autoload']];

$ignoreUpdate203 = "UPDATE OR IGNORE wp_options SET (blog_id, autoload, status, option_value, bytes) = (1, 'yes', 'ignored203', option_value || ':ignored203', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) = (1, 'no') AS tuple_match ORDER BY option_id";
$replaceUpdate203 = "UPDATE OR REPLACE wp_options SET (blog_id, autoload, status, option_value, bytes) = (4, 'yes', 'replace203', option_value || ':replace203', bytes + 4) WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) IS (4, 'yes') AS tuple_is ORDER BY option_id";
$deleteAfterReplace203 = "DELETE FROM wp_options WHERE (blog_id, autoload) IN ((4, 'yes'), (1, 'manual')) RETURNING option_id, blog_id, option_name, autoload, status, (blog_id, autoload) IS DISTINCT FROM (1, 'yes') AS distinct_from_site ORDER BY option_id";

$ignoreResult203 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreUpdate203, $tables203, 'option_id', $unique203);
$replaceResult203 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceUpdate203, $ignoreResult203()['tables'], 'option_id', $unique203);
$deleteResult203 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteAfterReplace203, $replaceResult203()['tables'], 'option_id', $unique203);
$plan203 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint(
    $tables203,
    [$ignoreUpdate203],
    [$replaceUpdate203],
    [$deleteAfterReplace203],
    $unique203,
);
$customPlan203 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint(
    $tables203,
    [$ignoreUpdate203],
    [$replaceUpdate203],
    [$deleteAfterReplace203],
    $unique203,
    'wp_custom_ignore_replace203',
);

$cases203 = [
    'parser ignore conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate203)['conflict_action'], 'ignore'],
    'parser replace conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($replaceUpdate203)['conflict_action'], 'replace'],
    'parser delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteAfterReplace203)['where'], "(blog_id, autoload) IN ((4, 'yes'), (1, 'manual'))"],

    'ignore selected ids' => [static fn (): mixed => $ignoreResult203()['plan']->selectedIds, [5, 6]],
    'ignore mutation ids' => [static fn (): mixed => $ignoreResult203()['plan']->mutationIds, [5, 6]],
    'ignore returning suppresses conflicted rows' => [static fn (): mixed => $ignoreResult203()['returning'], []],
    'ignore records both ignored rows' => [static fn (): mixed => array_column($ignoreResult203()['ignored_rows'], 'option_id'), [5, 6]],
    'ignore records conflicts' => [static fn (): mixed => count($ignoreResult203()['conflicts']), 2],
    'ignore restores row five source' => [static fn (): mixed => array_column($ignoreResult203()['tables']['wp_options'], 'status', 'option_id')[5], 'queued'],
    'ignore restores row six source' => [static fn (): mixed => array_column($ignoreResult203()['tables']['wp_options'], 'option_value', 'option_id')[6], 'rules'],

    'replace selected id' => [static fn (): mixed => $replaceResult203()['plan']->selectedIds, [7]],
    'replace returning id' => [static fn (): mixed => array_column($replaceResult203()['returning'], 'option_id'), [7]],
    'replace tuple is true' => [static fn (): mixed => $replaceResult203()['returning'][0]['tuple_is'], 1],
    'replace deletes conflicting row eight' => [static fn (): mixed => array_column($replaceResult203()['deleted_conflict_rows'], 'option_id'), [8]],
    'replace conflict row id' => [static fn (): mixed => $replaceResult203()['conflicts'][0]['row_id'], 7],
    'replace row seven moved to blog four' => [static fn (): mixed => array_column($replaceResult203()['tables']['wp_options'], 'blog_id', 'option_id')[7], 4],
    'replace removes row eight from current source' => [static fn (): mixed => in_array(8, array_column($replaceResult203()['tables']['wp_options'], 'option_id'), true), false],

    'delete after replace selected ids' => [static fn (): mixed => $deleteResult203()['plan']->selectedIds, [2, 7]],
    'delete after replace returning ids' => [static fn (): mixed => array_column($deleteResult203()['returning'], 'option_id'), [2, 7]],
    'delete after replace distinct flags' => [static fn (): mixed => array_column($deleteResult203()['returning'], 'distinct_from_site'), [1, 1]],
    'delete after replace final ids' => [static fn (): mixed => array_column($deleteResult203()['tables']['wp_options'], 'option_id'), [1, 3, 4, 5, 6]],

    'plan status' => [static fn (): mixed => $plan203()['status'], 'rowvalue-update-delete-returning-ignore-replace-savepoint-current-source-ignore_replace_delete'],
    'plan savepoint name' => [static fn (): mixed => $plan203()['savepoint'], 'wp_options_rowvalue_ignore_replace_ignore_replace_delete'],
    'plan savepoint image original' => [static fn (): mixed => $plan203()['savepoint_image_tables'], $tables203],
    'plan savepoint active after ignore' => [static fn (): mixed => $plan203()['savepoint_active_after_ignore'], true],
    'plan savepoint active after replace' => [static fn (): mixed => $plan203()['savepoint_active_after_replace'], true],
    'plan savepoint released after delete' => [static fn (): mixed => $plan203()['savepoint_released_after_delete'], true],
    'plan ignore current source unchanged row five' => [static fn (): mixed => array_column($plan203()['ignore_current_source_tables']['wp_options'], 'status', 'option_id')[5], 'queued'],
    'plan replace current source row seven value' => [static fn (): mixed => array_column($plan203()['replace_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'cache:replace203'],
    'plan replace current source excludes row eight' => [static fn (): mixed => in_array(8, array_column($plan203()['replace_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current source final ids' => [static fn (): mixed => array_column($plan203()['current_source_tables']['wp_options'], 'option_id'), [1, 3, 4, 5, 6]],
    'plan next source equals current' => [static fn (): mixed => $plan203()['next_source_tables'], $plan203()['current_source_tables']],
    'plan ignore statement phase' => [static fn (): mixed => $plan203()['ignore_statements'][0]['phase'], 'ignore-conflict-current-source-ignore_replace_delete'],
    'plan replace statement phase' => [static fn (): mixed => $plan203()['replace_statements'][0]['phase'], 'replace-conflict-current-source-ignore_replace_delete'],
    'plan delete statement phase' => [static fn (): mixed => $plan203()['delete_statements'][0]['phase'], 'delete-after-replace-current-source-ignore_replace_delete'],
    'plan ignore source ids' => [static fn (): mixed => array_column($plan203()['ignore_statements'][0]['source_rows'], 'option_id'), [5, 6]],
    'plan replace source ids' => [static fn (): mixed => array_column($plan203()['replace_statements'][0]['source_rows'], 'option_id'), [7]],
    'plan delete source ids' => [static fn (): mixed => array_column($plan203()['delete_statements'][0]['source_rows'], 'option_id'), [2, 7]],
    'plan ignored rows' => [static fn (): mixed => array_column($plan203()['ignored_rows'], 'option_id'), [5, 6]],
    'plan replace deleted conflict rows' => [static fn (): mixed => array_column($plan203()['replace_deleted_conflict_rows'], 'option_id'), [8]],
    'plan ignore yielded count' => [static fn (): mixed => $plan203()['ignore_yielded_count'], 0],
    'plan replace yielded count' => [static fn (): mixed => $plan203()['replace_yielded_count'], 1],
    'plan delete yielded count' => [static fn (): mixed => $plan203()['delete_yielded_count'], 2],
    'plan ignore conflict count' => [static fn (): mixed => $plan203()['ignore_conflict_count'], 2],
    'plan replace conflict count' => [static fn (): mixed => $plan203()['replace_conflict_count'], 1],
    'plan changed tables' => [static fn (): mixed => $plan203()['changed_tables'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan203()['row_counts']['wp_options'], 5],
    'plan dependency ignore' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-ignore-returning-current-source-ignore_replace_delete', $plan203()['dependencies'], true), true],
    'plan dependency replace' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-replace-returning-conflict-delete-ignore_replace_delete', $plan203()['dependencies'], true), true],
    'plan dependency delete' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-after-replace-current-source-ignore_replace_delete', $plan203()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan203()['savepoint'], 'wp_custom_ignore_replace203'],

    'malformed empty ignore rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [], [$replaceUpdate203], [$deleteAfterReplace203], $unique203), InvalidArgumentException::class],
    'malformed empty replace rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [$ignoreUpdate203], [], [$deleteAfterReplace203], $unique203), InvalidArgumentException::class],
    'malformed empty delete rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [$ignoreUpdate203], [$replaceUpdate203], [], $unique203), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [$ignoreUpdate203], [$replaceUpdate203], [$deleteAfterReplace203], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [$ignoreUpdate203], [$replaceUpdate203], [$deleteAfterReplace203], $unique203, 'bad-name'), InvalidArgumentException::class],
    'malformed ignore phase rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [$replaceUpdate203], [$replaceUpdate203], [$deleteAfterReplace203], $unique203), InvalidArgumentException::class],
    'malformed replace phase rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint($tables203, [$ignoreUpdate203], [$ignoreUpdate203], [$deleteAfterReplace203], $unique203), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeIgnoreReplaceDeleteSavepoint(['wp_options' => ['bad']], [$ignoreUpdate203], [$replaceUpdate203], [$deleteAfterReplace203], $unique203), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases203 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source ignore_replace_delete ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
