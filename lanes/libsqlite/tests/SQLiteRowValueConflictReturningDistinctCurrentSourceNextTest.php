<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 20, 'expected_bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'staged', 'bytes' => 21, 'expected_bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => 'stale', 'bytes' => 12, 'expected_bytes' => 13, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => null, 'bytes' => 13, 'expected_bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 25, 'expected_bytes' => 25.0, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => null, 'expected_status' => null, 'bytes' => 26, 'expected_bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'expected_status' => 'queued', 'bytes' => 7, 'expected_bytes' => '7', 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'expected_status' => 'staged', 'bytes' => 5, 'expected_bytes' => null, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'expected_status' => 'queued', 'bytes' => 9, 'expected_bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$ignoreSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, bytes, option_value) = (1, 'siteurl', 'ignored-conflict', bytes + 1, option_value || ':ignored') WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 2 RETURNING option_id, blog_id, option_name, status, bytes, (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AS still_drifted";
$replaceSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, bytes, option_value) = (1, 'siteurl', 'replace-conflict', bytes + 10, option_value || ':replace') WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 3 RETURNING option_id, blog_id, option_name, status, bytes, (status, bytes) IS NOT DISTINCT FROM ('replace-conflict', 22) AS replaced_tuple";
$deleteCleanSql = "DELETE FROM wp_options WHERE (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AND autoload = 'yes' RETURNING option_id, option_name, (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AS clean_drift ORDER BY option_id";
$abortSql = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', 'abort-conflict', option_value || ':abort') WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 7 RETURNING option_id, blog_id, option_name, status, (status, expected_status) IS DISTINCT FROM (NULL, 'queued') AS one_sided_null";

$plan = static fn (): array => SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan::execute(
    $tables,
    [$ignoreSql, $replaceSql, $deleteCleanSql, $abortSql],
    $unique,
);
$completed = static fn (): array => SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan::execute(
    $tables,
    [$ignoreSql, $replaceSql, $deleteCleanSql],
    $unique,
);
$ignoreOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);
$replaceOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', $unique);
$deleteCleanOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteCleanSql, $tables, 'option_id', $unique);
$abortOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($abortSql, $completed()['current_source_tables'], 'option_id', $unique);
$parsedIgnore = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($ignoreSql);
$parsedReplace = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($replaceSql);
$parsedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($deleteCleanSql);
$parsedAbort = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($abortSql);

$cases = [
    'parse ignore conflict action' => [static fn (): mixed => $parsedIgnore()['conflict_action'], 'ignore'],
    'parse ignore distinct where' => [static fn (): mixed => $parsedIgnore()['where'], "(status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND option_id = 2"],
    'parse ignore returning distinct expression' => [static fn (): mixed => str_contains($parsedIgnore()['returning'], 'still_drifted'), true],
    'parse replace conflict action' => [static fn (): mixed => $parsedReplace()['conflict_action'], 'replace'],
    'parse replace row-value assignment columns' => [static fn (): mixed => array_keys($parsedReplace()['assignments']), ['blog_id', 'option_name', 'status', 'bytes', 'option_value']],
    'parse replace returning not distinct expression' => [static fn (): mixed => str_contains($parsedReplace()['returning'], 'replaced_tuple'), true],
    'parse delete distinct clean where' => [static fn (): mixed => $parsedDelete()['where'], "(status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AND autoload = 'yes'"],
    'parse delete order column' => [static fn (): mixed => $parsedDelete()['order_by'][0]['column'], 'option_id'],
    'parse abort conflict action' => [static fn (): mixed => $parsedAbort()['conflict_action'], 'abort'],
    'parse abort distinct where' => [static fn (): mixed => str_contains($parsedAbort()['where'] ?? '', 'IS DISTINCT FROM'), true],

    'ignore only selected drift row' => [static fn (): mixed => $ignoreOnly()['plan']->selectedIds, [2]],
    'ignore only mutation row id' => [static fn (): mixed => $ignoreOnly()['plan']->mutationIds, [2]],
    'ignore only returns no rows' => [static fn (): mixed => $ignoreOnly()['returning'], []],
    'ignore only records conflict' => [static fn (): mixed => $ignoreOnly()['conflicts'][0]['key'], '1|siteurl'],
    'ignore only records conflicting row id' => [static fn (): mixed => $ignoreOnly()['conflicts'][0]['conflicting_row_ids'], [1]],
    'ignore only records ignored attempted row' => [static fn (): mixed => $ignoreOnly()['ignored_rows'][0]['status'], 'ignored-conflict'],
    'ignore only current row two unchanged' => [static fn (): mixed => array_column($ignoreOnly()['tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'ignore only row one preserved' => [static fn (): mixed => array_column($ignoreOnly()['tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],

    'replace only selected drift row' => [static fn (): mixed => $replaceOnly()['plan']->selectedIds, [3]],
    'replace only returning row id' => [static fn (): mixed => $replaceOnly()['returning'][0]['option_id'], 3],
    'replace only returning status' => [static fn (): mixed => $replaceOnly()['returning'][0]['status'], 'replace-conflict'],
    'replace only returning bytes' => [static fn (): mixed => $replaceOnly()['returning'][0]['bytes'], 22],
    'replace only returning not distinct true' => [static fn (): mixed => $replaceOnly()['returning'][0]['replaced_tuple'], 1],
    'replace only deletes conflicting siteurl' => [static fn (): mixed => array_column($replaceOnly()['deleted_conflict_rows'], 'option_id'), [1]],
    'replace only final row ids' => [static fn (): mixed => array_column($replaceOnly()['tables']['wp_options'], 'option_id'), [2, 3, 4, 5, 6, 7, 8, 9]],
    'replace only row three becomes siteurl' => [static fn (): mixed => array_column($replaceOnly()['tables']['wp_options'], 'option_name', 'option_id')[3], 'siteurl'],

    'delete clean only selected aligned rows' => [static fn (): mixed => $deleteCleanOnly()['plan']->selectedIds, [1, 5, 6, 9]],
    'delete clean only returning row ids' => [static fn (): mixed => array_column($deleteCleanOnly()['returning'], 'option_id'), [1, 5, 6, 9]],
    'delete clean only clean drift flags false' => [static fn (): mixed => array_column($deleteCleanOnly()['returning'], 'clean_drift'), [0, 0, 0, 0]],
    'delete clean only leaves drift rows' => [static fn (): mixed => array_column($deleteCleanOnly()['tables']['wp_options'], 'option_id'), [2, 3, 4, 7, 8]],
    'delete clean only action delete' => [static fn (): mixed => $deleteCleanOnly()['action'], 'delete'],

    'plan status stopped after abort conflict' => [static fn (): mixed => $plan()['status'], 'stopped-after-conflict'],
    'plan failed ordinal' => [static fn (): mixed => $plan()['failed_statement']['ordinal'], 3],
    'plan failed reason' => [static fn (): mixed => $plan()['failed_statement']['reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ABORT'],
    'plan failed source is current before abort' => [static fn (): mixed => $plan()['failed_statement']['statement_source_tables'], $completed()['current_source_tables']],
    'plan current source retains completed prefix' => [static fn (): mixed => $plan()['current_source_tables'], $completed()['current_source_tables']],
    'plan executes three statements before failure' => [static fn (): mixed => count($plan()['executed_statements']), 3],
    'plan executed conflict actions' => [static fn (): mixed => array_column($plan()['executed_statements'], 'conflict_action'), ['ignore', 'replace', 'abort']],
    'plan selected ids per statement' => [static fn (): mixed => array_column($plan()['executed_statements'], 'selected_ids'), [[2], [3], [5, 6, 9]]],
    'plan source row for replace sees ignore preserved row one' => [static fn (): mixed => $plan()['executed_statements'][1]['source_rows'][0]['option_name'], '_transient_feed'],
    'plan ignore yielded no rows' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'], []],
    'plan replace yielded row three' => [static fn (): mixed => $plan()['yielded_returning'][1]['rows'][0]['option_id'], 3],
    'plan delete yielded row ids after replace removed row one' => [static fn (): mixed => array_column($plan()['yielded_returning'][2]['rows'], 'option_id'), [5, 6, 9]],
    'plan returning count excludes ignored and failed' => [static fn (): mixed => $plan()['returning_count'], 4],
    'plan ignored count one' => [static fn (): mixed => $plan()['ignored_count'], 1],
    'plan deleted conflict count one' => [static fn (): mixed => $plan()['deleted_conflict_count'], 1],
    'plan conflict count includes failed abort' => [static fn (): mixed => $plan()['conflict_count'], 3],
    'plan conflicts keys ignore replace' => [static fn (): mixed => array_column($plan()['conflicts'], 'key'), ['1|siteurl', '1|siteurl']],
    'plan ignored row attempted status' => [static fn (): mixed => $plan()['ignored_rows'][0]['row']['status'], 'ignored-conflict'],
    'plan deleted conflict row was siteurl' => [static fn (): mixed => $plan()['deleted_conflict_rows'][0]['row']['option_name'], 'siteurl'],
    'plan current row ids after completed prefix' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [2, 3, 4, 7, 8]],
    'plan current row two still live after ignore' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'plan current row three replaced siteurl' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], 'siteurl'],
    'plan current row four survives delete clean' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan current row seven unchanged by failed abort' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan dependency distinct' => [static fn (): mixed => in_array('sqlite-row-value-is-distinct-from-update-delete', $plan()['dependencies'], true), true],
    'plan dependency conflict policy' => [static fn (): mixed => in_array('sqlite-update-returning-conflict-policy', $plan()['dependencies'], true), true],
    'plan dependency returning current source' => [static fn (): mixed => in_array('sqlite-returning-current-source-after-conflict', $plan()['dependencies'], true), true],

    'completed status' => [static fn (): mixed => $completed()['status'], 'completed-current-source'],
    'completed failed statement absent' => [static fn (): mixed => $completed()['failed_statement'], null],
    'completed returning count four' => [static fn (): mixed => $completed()['returning_count'], 4],
    'completed current rows after delete clean' => [static fn (): mixed => array_column($completed()['current_source_tables']['wp_options'], 'option_id'), [2, 3, 4, 7, 8]],
    'completed current row three bytes' => [static fn (): mixed => array_column($completed()['current_source_tables']['wp_options'], 'bytes', 'option_id')[3], 22],
    'completed current row five deleted' => [static fn (): mixed => in_array(5, array_column($completed()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'abort only throws after completed prefix' => [$abortOnly, InvalidArgumentException::class],
    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan::execute($tables, [$ignoreSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$ignoreSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue conflict returning distinct current source next147 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
