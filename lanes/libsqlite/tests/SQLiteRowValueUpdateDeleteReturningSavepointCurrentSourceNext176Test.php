<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 31, 'option_value' => 'a:0:{}'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 32, 'option_value' => 'rules'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 9, 'option_value' => 'feed'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 41, 'option_value' => 'network'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 42, 'option_value' => 'network-rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'pending', 'bucket' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$deleteSql = "DELETE FROM wp_options WHERE (blog_id, status, option_name) <> (1, NULL, 'active_plugins') RETURNING option_id, blog_id, status, option_name, (blog_id, status, option_name) = (1, NULL, 'active_plugins') AS tuple_eq, (blog_id, status, option_name) <> (1, NULL, 'active_plugins') AS tuple_ne ORDER BY option_id";
$unknownDeleteSql = "DELETE FROM wp_options WHERE (blog_id, status, option_name) = (1, NULL, 'active_plugins') RETURNING option_id, option_name";
$stageSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':stage176', status, option_value || ':stage176', bytes + 1) WHERE (blog_id, status, option_name) <> (1, NULL, 'active_plugins') RETURNING option_id, option_name, status, (blog_id, status, option_name) <> (1, NULL, 'active_plugins') AS tuple_ne ORDER BY option_id";
$rollbackSql = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name) = (1, 'active_plugins') WHERE option_id = 7 RETURNING option_id, blog_id, option_name";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, status, option_name) <> (1, NULL, 'active_plugins') RETURNING option_id, option_name, status, (blog_id, status, option_name) = (1, NULL, 'active_plugins') AS tuple_eq, (blog_id, status, option_name) <> (1, NULL, 'active_plugins') AS tuple_ne ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value) = ('kept176', option_value || ':kept') WHERE option_id = 2 RETURNING option_id, option_name, status, option_value";

$deleteOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables, 'option_id', $unique);
$unknownDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($unknownDeleteSql, $tables, 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext164(
    $tables,
    [$stageSql, $rollbackSql],
    [$retryDeleteSql, $retryUpdateSql],
    $unique,
    'wp_options_rowvalue_null_inequality_next176',
);

$cases = [
    'delete selects deterministic non equal ids through null middle' => [static fn (): mixed => $deleteOnly()['plan']->selectedIds, [1, 3, 4, 5, 6, 7]],
    'delete keeps unknown matching active plugins row' => [static fn (): mixed => array_column($deleteOnly()['tables']['wp_options'], 'option_id'), [2]],
    'delete returning ids exclude unknown row two' => [static fn (): mixed => array_column($deleteOnly()['returning'], 'option_id'), [1, 3, 4, 5, 6, 7]],
    'delete returning row one equality false' => [static fn (): mixed => $deleteOnly()['returning'][0]['tuple_eq'], 0],
    'delete returning row one inequality true' => [static fn (): mixed => $deleteOnly()['returning'][0]['tuple_ne'], 1],
    'delete returning rewrite rules equality false despite null middle' => [static fn (): mixed => $deleteOnly()['returning'][1]['tuple_eq'], 0],
    'delete returning rewrite rules inequality true despite null middle' => [static fn (): mixed => $deleteOnly()['returning'][1]['tuple_ne'], 1],
    'delete returning transient equality false despite null middle' => [static fn (): mixed => $deleteOnly()['returning'][2]['tuple_eq'], 0],
    'delete returning transient inequality true despite null middle' => [static fn (): mixed => $deleteOnly()['returning'][2]['tuple_ne'], 1],
    'delete returning network active plugins equality false by blog id' => [static fn (): mixed => $deleteOnly()['returning'][3]['tuple_eq'], 0],
    'delete returning network active plugins inequality true by blog id' => [static fn (): mixed => $deleteOnly()['returning'][3]['tuple_ne'], 1],
    'delete returning network rewrite rules equality false' => [static fn (): mixed => $deleteOnly()['returning'][4]['tuple_eq'], 0],
    'delete returning network rewrite rules inequality true' => [static fn (): mixed => $deleteOnly()['returning'][4]['tuple_ne'], 1],
    'delete returning pending theme equality false' => [static fn (): mixed => $deleteOnly()['returning'][5]['tuple_eq'], 0],
    'delete returning pending theme inequality true' => [static fn (): mixed => $deleteOnly()['returning'][5]['tuple_ne'], 1],
    'unknown equality delete selects no rows' => [static fn (): mixed => $unknownDelete()['plan']->selectedIds, []],
    'unknown equality delete returns no rows' => [static fn (): mixed => $unknownDelete()['returning'], []],
    'unknown equality delete leaves all ids' => [static fn (): mixed => array_column($unknownDelete()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],

    'plan status rolled back retried' => [static fn (): mixed => $plan()['status'], 'transaction-rolled-back-retried-current-source-next164'],
    'plan custom savepoint' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_null_inequality_next176'],
    'plan rollback ordinal after stage' => [static fn (): mixed => $plan()['rollback_statement_ordinal'], 1],
    'plan rollback reason names active plugins conflict' => [static fn (): mixed => str_contains((string) $plan()['rollback_reason'], 'blog_id,option_name=1|active_plugins using OR ROLLBACK'), true],
    'plan attempted stage selected deterministic ids' => [static fn (): mixed => $plan()['attempt_statements'][0]['selected_ids'], [1, 3, 4, 5, 6, 7]],
    'plan attempted stage returning ids' => [static fn (): mixed => array_column($plan()['attempted_returning_before_rollback'][0]['rows'], 'option_id'), [1, 3, 4, 5, 6, 7]],
    'plan attempted stage rewrite rules tuple ne true' => [static fn (): mixed => $plan()['attempted_returning_before_rollback'][0]['rows'][1]['tuple_ne'], 1],
    'plan attempted stage active plugins remains original before rollback' => [static fn (): mixed => array_column($plan()['attempted_current_source_tables']['wp_options'], 'option_name', 'option_id')[2], 'active_plugins'],
    'plan attempted stage rewrite rules mutated before rollback' => [static fn (): mixed => array_column($plan()['attempted_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], 'rewrite_rules:stage176'],
    'plan discarded attempted returning count six' => [static fn (): mixed => $plan()['discarded_returning_count'], 6],
    'plan attempted changes before rollback six' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 6],
    'plan rollback source restores rewrite rules' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], 'rewrite_rules'],
    'plan rollback source restores pending theme' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan retry actions delete then update' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['delete', 'update']],
    'plan retry delete selected deterministic ids' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [1, 3, 4, 5, 6, 7]],
    'plan retry delete source rows original names' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_name'), ['siteurl', 'rewrite_rules', '_transient_feed', 'active_plugins', 'rewrite_rules', 'pending_theme']],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [1, 3, 4, 5, 6, 7]],
    'plan retry delete returning equality false for rewrite rules' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][1]['tuple_eq'], 0],
    'plan retry delete returning inequality true for rewrite rules' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][1]['tuple_ne'], 1],
    'plan retry update selected kept id' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [2]],
    'plan retry update source row is original active plugins' => [static fn (): mixed => $plan()['retry_statements'][1]['source_rows'][0]['option_value'], 'a:0:{}'],
    'plan yielded update status kept' => [static fn (): mixed => $plan()['yielded_returning'][1]['rows'][0]['status'], 'kept176'],
    'plan yielded update value from rollback source' => [static fn (): mixed => $plan()['yielded_returning'][1]['rows'][0]['option_value'], 'a:0:{}:kept'],
    'plan yielded returning count seven' => [static fn (): mixed => $plan()['yielded_returning_count'], 7],
    'plan changes after retry seven' => [static fn (): mixed => $plan()['changes_after_retry'], 7],
    'plan final table keeps only active plugins id two' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [2]],
    'plan final id two status updated' => [static fn (): mixed => $plan()['current_source_tables']['wp_options'][0]['status'], 'kept176'],
    'plan final next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan changed tables reports wp options' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry one' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 1],
    'plan dependency includes rollback discard' => [static fn (): mixed => in_array('sqlite-rollback-conflict-discards-attempted-returning-streams', $plan()['dependencies'], true), true],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next176 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
