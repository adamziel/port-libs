<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$ignoreSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':ignored', option_value || ':ignored', bytes + 100) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (1, 'siteurl') AS duplicate_key ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$continueSql = "UPDATE wp_options SET (status, option_value, bytes) = ('continued', option_value || ':continued', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$cleanSql = "UPDATE wp_options SET (status, option_value, bytes) = ('clean', option_value || ':clean', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";

$parsedIgnore = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($ignoreSql);
$ignoredOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);
$afterIgnoreDelete = static function () use ($ignoreSql, $deleteSql, $tables, $unique): array {
    $ignored = SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($deleteSql, $ignored['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch(
    $tables,
    [$ignoreSql, $deleteSql, $continueSql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch(
    $tables,
    [$cleanSql, $deleteSql],
    $unique,
    'wp_options_rowvalue_clean_batch',
);

$cases = [
    'parser conflict action ignore' => [static fn (): mixed => $parsedIgnore()['conflict_action'], 'ignore'],
    'parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedIgnore()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser returning duplicate expression retained' => [static fn (): mixed => $parsedIgnore()['returning'], "option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (1, 'siteurl') AS duplicate_key"],
    'parser order by option id' => [static fn (): mixed => $parsedIgnore()['order_by'][0]['column'], 'option_id'],
    'ignore only selected ids' => [static fn (): mixed => $ignoredOnly()['plan']->selectedIds, [7, 8]],
    'ignore only mutation ids' => [static fn (): mixed => $ignoredOnly()['plan']->mutationIds, [7, 8]],
    'ignore only yields no returning rows' => [static fn (): mixed => $ignoredOnly()['returning'], []],
    'ignore only records two ignored rows' => [static fn (): mixed => count($ignoredOnly()['ignored_rows']), 2],
    'ignore only ignored row values are attempted duplicates' => [static fn (): mixed => array_column($ignoredOnly()['ignored_rows'], 'option_name'), ['siteurl', 'siteurl']],
    'ignore only records two conflicts' => [static fn (): mixed => count($ignoredOnly()['conflicts']), 2],
    'ignore only first conflict key' => [static fn (): mixed => $ignoredOnly()['conflicts'][0]['key'], '1|siteurl'],
    'ignore only first conflict id existing siteurl' => [static fn (): mixed => $ignoredOnly()['conflicts'][0]['conflicting_row_ids'], [1]],
    'ignore only row seven restored after conflict' => [static fn (): mixed => array_column($ignoredOnly()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'ignore only row eight restored after conflict' => [static fn (): mixed => array_column($ignoredOnly()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'ignore only failed conflict absent' => [static fn (): mixed => $ignoredOnly()['failed_conflict'], null],
    'delete after ignore selected restored transient ids' => [static fn (): mixed => $afterIgnoreDelete()['plan']->selectedIds, [3, 4]],
    'delete after ignore returns transient ids' => [static fn (): mixed => array_column($afterIgnoreDelete()['returning'], 'option_id'), [3, 4]],
    'delete after ignore keeps ignored candidate rows' => [static fn (): mixed => array_intersect([7, 8], array_column($afterIgnoreDelete()['tables']['wp_options'], 'option_id')), [7, 8]],

    'plan status released after ignore conflicts' => [static fn (): mixed => $plan()['status'], 'released-after-rowvalue-ignore-conflicts'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_ignore_batch'],
    'plan no rollback to savepoint' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], false],
    'plan savepoint released' => [static fn (): mixed => $plan()['savepoint_preserved'], false],
    'plan executed actions' => [static fn (): mixed => array_column($plan()['executed_statements'], 'action'), ['update', 'delete', 'update']],
    'plan executed conflict actions' => [static fn (): mixed => array_column($plan()['executed_statements'], 'conflict_action'), ['ignore', 'abort', 'abort']],
    'plan ignore selected ids' => [static fn (): mixed => $plan()['executed_statements'][0]['selected_ids'], [7, 8]],
    'plan ignore source rows original names' => [static fn (): mixed => array_column($plan()['executed_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'plan ignore statement yielded no returning' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'], []],
    'plan ignore stream count one' => [static fn (): mixed => count($plan()['ignored_returning']), 1],
    'plan ignore stream ordinal zero' => [static fn (): mixed => $plan()['ignored_returning'][0]['ordinal'], 0],
    'plan ignored returning count two' => [static fn (): mixed => $plan()['ignored_returning_count'], 2],
    'plan ignored row ids' => [static fn (): mixed => array_column($plan()['ignored_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan ignored rows are attempted siteurl duplicates' => [static fn (): mixed => array_column($plan()['ignored_returning'][0]['rows'], 'option_name'), ['siteurl', 'siteurl']],
    'plan ignored conflicts keys' => [static fn (): mixed => array_column($plan()['ignored_returning'][0]['conflicts'], 'key'), ['1|siteurl', '1|siteurl']],
    'plan delete selected transient ids' => [static fn (): mixed => $plan()['executed_statements'][1]['selected_ids'], [3, 4]],
    'plan delete source rows stale' => [static fn (): mixed => array_column($plan()['executed_statements'][1]['source_rows'], 'status'), ['stale', 'stale']],
    'plan delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan continue selected ids' => [static fn (): mixed => $plan()['executed_statements'][2]['selected_ids'], [7, 9]],
    'plan continue source rows use restored ignored row' => [static fn (): mixed => array_column($plan()['executed_statements'][2]['source_rows'], 'option_name'), ['pending_theme', 'orphaned_cache']],
    'plan continue returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][2]['rows'], 'option_id'), [7, 9]],
    'plan yielded returning count four' => [static fn (): mixed => $plan()['yielded_returning_count'], 4],
    'plan changes excludes ignored rows' => [static fn (): mixed => $plan()['changes'], 4],
    'plan final source ids' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row seven continued from original value' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:continued'],
    'plan final row eight unchanged by ignored update' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan final row nine continued' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'continued'],
    'plan final transients removed' => [static fn (): mixed => array_intersect([3, 4], array_column($plan()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan savepoint image original row seven' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan changed table wp options' => [static fn (): mixed => $plan()['savepoint_changed_tables'], ['wp_options']],
    'plan row count after deletes' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'plan dependency ignore returning' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-ignore-suppresses-returning', $plan()['dependencies'], true), true],
    'plan dependency delete continues' => [static fn (): mixed => in_array('sqlite-delete-returning-after-ignored-rowvalue-conflict-continues', $plan()['dependencies'], true), true],
    'plan dependency source released' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-released-after-ignore-conflict', $plan()['dependencies'], true), true],

    'clean plan custom savepoint' => [static fn (): mixed => $cleanPlan()['savepoint'], 'wp_options_rowvalue_clean_batch'],
    'clean plan no ignored rows' => [static fn (): mixed => $cleanPlan()['ignored_returning_count'], 0],
    'clean plan yielded ids update' => [static fn (): mixed => array_column($cleanPlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'clean plan yielded ids delete' => [static fn (): mixed => array_column($cleanPlan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'clean plan changes four' => [static fn (): mixed => $cleanPlan()['changes'], 4],
    'clean plan final row seven clean' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'clean'],

    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch($tables, [$ignoreSql], []), InvalidArgumentException::class],
    'malformed bad savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch($tables, [$ignoreSql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeIgnoreReturningSavepointBatch(['wp_options' => ['bad']], [$ignoreSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next165 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
