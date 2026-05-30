<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows211 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables211 = ['wp_options' => $rows211];
$unique211 = [['blog_id', 'option_name']];

$preUpdate211 = "UPDATE wp_options SET (status, option_value, bytes) = ('pre211', option_value || ':pre211', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preDelete211 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN (VALUES (1, 'siteurl')) AS not_siteurl ORDER BY option_id";
$ignoreUpdate211 = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_migrated' ELSE 'siteurl' END, 'ignore211', option_value || ':ignore211', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_conflict ORDER BY option_id";
$afterUpdate211 = "UPDATE wp_options SET (status, option_value, bytes) = ('after211', option_value || ':after211', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme_migrated'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$afterDelete211 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'siteurl')) AS dropped_network_siteurl ORDER BY option_id";

$preUpdateResult211 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdate211, $tables211, 'option_id', $unique211);
$preDeleteResult211 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDelete211, $preUpdateResult211()['tables'], 'option_id', $unique211);
$ignoreProbe211 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreUpdate211, $preDeleteResult211()['tables'], 'option_id', [], true);
$ignoreResult211 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreUpdate211, $preDeleteResult211()['tables'], 'option_id', $unique211, true);
$afterUpdateResult211 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($afterUpdate211, $ignoreResult211()['tables'], 'option_id', $unique211);
$afterDeleteResult211 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($afterDelete211, $afterUpdateResult211()['tables'], 'option_id', $unique211);
$plan211 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease(
    $tables211,
    [$preUpdate211, $preDelete211],
    $ignoreUpdate211,
    [$afterUpdate211, $afterDelete211],
    $unique211,
);
$customPlan211 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease(
    $tables211,
    [$preUpdate211],
    $ignoreUpdate211,
    [$afterUpdate211],
    $unique211,
    'wp_custom_ignore211',
);

$cases211 = [
    'parser ignore conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate211)['conflict_action'], 'ignore'],
    'parser ignore assignments row value' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate211)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser ignore where row value in' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate211)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser after delete returning flag' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($afterDelete211)['returning'], 'dropped_network_siteurl'), true],
    'pre update selected ids' => [static fn (): mixed => $preUpdateResult211()['plan']->selectedIds, [7, 8]],
    'pre update returning ids' => [static fn (): mixed => array_column($preUpdateResult211()['returning'], 'option_id'), [7, 8]],
    'pre update row seven value' => [static fn (): mixed => array_column($preUpdateResult211()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:pre211'],
    'pre delete selected ids' => [static fn (): mixed => $preDeleteResult211()['plan']->selectedIds, [3, 9]],
    'pre delete returning ids' => [static fn (): mixed => array_column($preDeleteResult211()['returning'], 'option_id'), [3, 9]],
    'pre delete removes plugin batch' => [static fn (): mixed => in_array(9, array_column($preDeleteResult211()['tables']['wp_options'], 'option_id'), true), false],
    'ignore probe would return both ids without unique constraints' => [static fn (): mixed => array_column($ignoreProbe211()['returning'], 'option_id'), [7, 8]],
    'ignore returns only non conflicting row' => [static fn (): mixed => array_column($ignoreResult211()['returning'], 'option_id'), [7]],
    'ignore records conflict row eight' => [static fn (): mixed => $ignoreResult211()['conflicts'][0]['row_id'], 8],
    'ignore records unique key' => [static fn (): mixed => $ignoreResult211()['conflicts'][0]['key'], '1|siteurl'],
    'ignore row seven migrated' => [static fn (): mixed => array_column($ignoreResult211()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_migrated'],
    'ignore row eight restored after conflict' => [static fn (): mixed => array_column($ignoreResult211()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'ignore row eight returning suppressed' => [static fn (): mixed => array_column($ignoreResult211()['ignored_rows'], 'option_id'), [8]],
    'ignore keeps pre delete removals' => [static fn (): mixed => array_intersect([3, 9], array_column($ignoreResult211()['tables']['wp_options'], 'option_id')), []],
    'after update selected ids sees ignore current source' => [static fn (): mixed => $afterUpdateResult211()['plan']->selectedIds, [8, 7]],
    'after update row seven keeps ignore prefix' => [static fn (): mixed => $afterUpdateResult211()['returning'][0]['option_value'], 'theme:pre211:ignore211:after211'],
    'after update row eight keeps pre prefix only' => [static fn (): mixed => $afterUpdateResult211()['returning'][1]['option_value'], 'rules:pre211:after211'],
    'after delete selected ids' => [static fn (): mixed => $afterDeleteResult211()['plan']->selectedIds, [4, 10]],
    'after delete network flag' => [static fn (): mixed => array_column($afterDeleteResult211()['returning'], 'dropped_network_siteurl'), [0, 1]],

    'plan status' => [static fn (): mixed => $plan211()['status'], 'rowvalue-update-delete-returning-or-ignore-current-source-next211'],
    'plan savepoint' => [static fn (): mixed => $plan211()['savepoint'], 'app_settings_rowvalue_ignore_next211'],
    'plan savepoint preserved' => [static fn (): mixed => $plan211()['savepoint_preserved_after_ignore'], true],
    'plan ignored conflicts suppressed' => [static fn (): mixed => $plan211()['ignored_conflicts_are_not_returned'], true],
    'plan ignored rows restored' => [static fn (): mixed => $plan211()['ignored_rows_restored_to_statement_start'], true],
    'plan pre changes preserved' => [static fn (): mixed => $plan211()['pre_ignore_changes_preserved'], true],
    'plan after reads source' => [static fn (): mixed => $plan211()['after_ignore_reads_current_source'], true],
    'plan released after ignore' => [static fn (): mixed => $plan211()['savepoint_released_after_ignore'], true],
    'plan savepoint image original' => [static fn (): mixed => $plan211()['savepoint_image_tables'], $tables211],
    'plan pre row seven changed' => [static fn (): mixed => array_column($plan211()['pre_ignore_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'pre211'],
    'plan pre row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan211()['pre_ignore_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan ignore row seven migrated' => [static fn (): mixed => array_column($plan211()['ignore_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_migrated'],
    'plan ignore row eight restored' => [static fn (): mixed => array_column($plan211()['ignore_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan final row seven after' => [static fn (): mixed => array_column($plan211()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'after211'],
    'plan final row eight after' => [static fn (): mixed => array_column($plan211()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'after211'],
    'plan final deleted ids gone' => [static fn (): mixed => array_intersect([3, 4, 9, 10], array_column($plan211()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan211()['next_source_tables'], $plan211()['current_source_tables']],
    'plan pre phases' => [static fn (): mixed => array_column($plan211()['pre_ignore_statements'], 'phase'), ['before-ignore-next211', 'before-ignore-next211']],
    'plan ignore phase' => [static fn (): mixed => $plan211()['ignore_statement']['phase'], 'or-ignore-next211'],
    'plan ignore selected ids' => [static fn (): mixed => $plan211()['ignore_statement']['selected_ids'], [7, 8]],
    'plan ignore mutation ids' => [static fn (): mixed => $plan211()['ignore_statement']['mutation_ids'], [7, 8]],
    'plan ignore returning ids' => [static fn (): mixed => array_column($plan211()['ignore_statement']['returning_rows'], 'option_id'), [7]],
    'plan ignore probe returning ids' => [static fn (): mixed => array_column($plan211()['ignore_statement']['probe_returning_rows'], 'option_id'), [7, 8]],
    'plan ignored ids' => [static fn (): mixed => array_column($plan211()['ignored_by_conflict_returning'], 'option_id'), [8]],
    'plan ignored conflict key' => [static fn (): mixed => $plan211()['ignore_statement']['conflicts'][0]['key'], '1|siteurl'],
    'plan after phases' => [static fn (): mixed => array_column($plan211()['after_ignore_statements'], 'phase'), ['after-ignore-next211', 'after-ignore-next211']],
    'plan after update source ids' => [static fn (): mixed => array_column($plan211()['after_ignore_statements'][0]['source_rows'], 'option_id'), [7, 8]],
    'plan after delete source ids' => [static fn (): mixed => array_column($plan211()['after_ignore_statements'][1]['source_rows'], 'option_id'), [4, 10]],
    'plan pre yielded count' => [static fn (): mixed => $plan211()['pre_ignore_yielded_count'], 4],
    'plan ignore yielded count' => [static fn (): mixed => $plan211()['ignore_yielded_count'], 1],
    'plan ignored count' => [static fn (): mixed => $plan211()['ignored_by_conflict_count'], 1],
    'plan after yielded count' => [static fn (): mixed => $plan211()['yielded_after_ignore_count'], 4],
    'plan pre changes count' => [static fn (): mixed => $plan211()['pre_ignore_changes_count'], 4],
    'plan ignore changes count' => [static fn (): mixed => $plan211()['ignore_changes_count'], 1],
    'plan after changes count' => [static fn (): mixed => $plan211()['after_ignore_changes_count'], 4],
    'plan changed tables' => [static fn (): mixed => $plan211()['changed_tables_after_release'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan211()['row_counts']['wp_options'], 6],
    'plan dependency ignore suppresses' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-ignore-suppresses-conflict-returning-next211', $plan211()['dependencies'], true), true],
    'plan dependency pre source' => [static fn (): mixed => in_array('sqlite-rowvalue-ignore-preserves-preceding-savepoint-current-source-next211', $plan211()['dependencies'], true), true],
    'plan dependency after source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-after-ignore-reads-current-source-next211', $plan211()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => $plan211()['dependency_closure'], 'no-new-support-component-reuses-native-update-delete-returning-rowvalue-conflict-and-savepoint-current-source'],
    'plan non overlap note' => [static fn (): mixed => str_contains($plan211()['non_overlap'], 'avoids accepted fail-statement-retry OR FAIL'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan211()['savepoint'], 'wp_custom_ignore211'],
    'custom pre count' => [static fn (): mixed => $customPlan211()['pre_ignore_yielded_count'], 2],
    'custom after count' => [static fn (): mixed => $customPlan211()['yielded_after_ignore_count'], 2],
    'malformed empty pre rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease($tables211, [], $ignoreUpdate211, [$afterUpdate211], $unique211), InvalidArgumentException::class],
    'malformed empty ignore rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease($tables211, [$preUpdate211], '', [$afterUpdate211], $unique211), InvalidArgumentException::class],
    'malformed empty after rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease($tables211, [$preUpdate211], $ignoreUpdate211, [], $unique211), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease($tables211, [$preUpdate211], $ignoreUpdate211, [$afterUpdate211], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease($tables211, [$preUpdate211], $ignoreUpdate211, [$afterUpdate211], $unique211, 'bad-name'), InvalidArgumentException::class],
    'malformed ignore action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease($tables211, [$preUpdate211], str_replace('OR IGNORE', 'OR FAIL', $ignoreUpdate211), [$afterUpdate211], $unique211), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrIgnoreSavepointRelease(['wp_options' => ['bad']], [$preUpdate211], $ignoreUpdate211, [$afterUpdate211], $unique211), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases211 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next211 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
