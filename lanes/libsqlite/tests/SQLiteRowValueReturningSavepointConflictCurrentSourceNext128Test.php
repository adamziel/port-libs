<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$ignoreSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value) = (1, 'home', 'ignored', option_name || ':ignored') WHERE (blog_id, option_name) IN ((2, 'pending_theme')) RETURNING option_id, blog_id, option_name, status, option_value";
$replaceSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value) = (1, 'home', 'replaced', option_name || ':replaced') WHERE (blog_id, option_name) IN ((2, 'pending_theme')) RETURNING option_id, blog_id, option_name, status, option_value";
$abortSql = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status) = (1, 'home', 'rollback') WHERE (blog_id, option_name) IN ((2, 'pending_theme')) RETURNING option_id, blog_id, option_name, status";
$commitStatements = [
    "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value) = (1, 'home', 'ignored', option_name || ':ignored') WHERE (blog_id, option_name) IN ((2, 'pending_theme')) RETURNING option_id, blog_id, option_name, status, option_value",
    "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value) = (1, 'home', 'replaced', option_name || ':replaced') WHERE (blog_id, option_name) IN ((2, 'home')) RETURNING option_id, blog_id, option_name, status, option_value",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id DESC LIMIT 1",
];
$rollbackStatements = [
    "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status) = (1, 'home', 'ignored') WHERE (blog_id, option_name) IN ((2, 'pending_theme')) RETURNING option_id, blog_id, option_name, status",
    "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status) = (1, 'siteurl', 'rollback') WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status",
];

$ignore = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);
$replace = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', $unique);
$commit = static fn (): array => SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan::execute($tables, $commitStatements, $unique, 'app_settings_conflict_batch', 'option_id');
$rollback = static fn (): array => SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan::execute($tables, $rollbackStatements, $unique, 'app_settings_conflict_batch', 'option_id');

$cases = [
    'parse update or ignore conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreSql)['conflict_action'], 'ignore'],
    'parse update or replace conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($replaceSql)['conflict_action'], 'replace'],
    'parse update or rollback conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($abortSql)['conflict_action'], 'rollback'],
    'parse default update conflict action is abort' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("UPDATE wp_options SET status = 'x' WHERE option_id = 1 RETURNING option_id")['conflict_action'], 'abort'],
    'ignore conflict action recorded' => [static fn (): mixed => $ignore()['conflict_action'], 'ignore'],
    'ignore selected row value id' => [static fn (): mixed => $ignore()['plan']->selectedIds, [7]],
    'ignore mutation row value id before conflict filter' => [static fn (): mixed => $ignore()['plan']->mutationIds, [7]],
    'ignore suppresses returning rows' => [static fn (): mixed => $ignore()['returning'], []],
    'ignore records one ignored attempted row' => [static fn (): mixed => array_column($ignore()['ignored_rows'], 'option_id'), [7]],
    'ignore attempted row carries conflicting key' => [static fn (): mixed => [$ignore()['ignored_rows'][0]['blog_id'], $ignore()['ignored_rows'][0]['option_name']], [1, 'home']],
    'ignore restores current table row seven key' => [static fn (): mixed => array_column($ignore()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'ignore restores current table row seven status' => [static fn (): mixed => array_column($ignore()['tables']['wp_options'], 'status', 'option_id')[7], null],
    'ignore conflict key recorded' => [static fn (): mixed => $ignore()['conflicts'][0]['key'], '1|home'],
    'ignore conflict peer id recorded' => [static fn (): mixed => $ignore()['conflicts'][0]['conflicting_row_ids'], [2]],
    'replace conflict action recorded' => [static fn (): mixed => $replace()['conflict_action'], 'replace'],
    'replace returns updated row' => [static fn (): mixed => $replace()['returning'], [['option_id' => 7, 'blog_id' => 1, 'option_name' => 'home', 'status' => 'replaced', 'option_value' => 'pending_theme:replaced']]],
    'replace deletes conflicting peer row' => [static fn (): mixed => array_column($replace()['deleted_conflict_rows'], 'option_id'), [2]],
    'replace current ids omit deleted peer' => [static fn (): mixed => array_column($replace()['tables']['wp_options'], 'option_id'), [1, 3, 4, 5, 6, 7, 8]],
    'replace row seven owns peer key' => [static fn (): mixed => array_column($replace()['tables']['wp_options'], 'option_name', 'option_id')[7], 'home'],
    'replace conflict metadata has row seven' => [static fn (): mixed => $replace()['conflicts'][0]['row_id'], 7],
    'rollback conflict throws in bare executor' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($abortSql, $tables, 'option_id', $unique), InvalidArgumentException::class],
    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit not rolled back' => [static fn (): mixed => $commit()['rolled_back'], false],
    'commit executed three statements' => [static fn (): mixed => count($commit()['executed_statements']), 3],
    'commit statement conflict actions' => [static fn (): mixed => array_column($commit()['executed_statements'], 'conflict_action'), ['ignore', 'replace', 'abort']],
    'commit yielded returning omits ignored row' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'], []],
    'commit replace returning row id six' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'option_id'), [6]],
    'commit delete returning old timeout row' => [static fn (): mixed => $commit()['yielded_returning'][2]['rows'], [['option_id' => 4, 'option_name' => '_transient_timeout_feed']]],
    'commit ignored rows carry ordinal zero' => [static fn (): mixed => $commit()['ignored_rows'][0]['ordinal'], 0],
    'commit replacement deleted row two' => [static fn (): mixed => $commit()['deleted_conflict_rows'][0]['row']['option_id'], 2],
    'commit conflict keys preserve order' => [static fn (): mixed => array_column($commit()['conflicts'], 'key'), ['1|home', '1|home']],
    'commit changes count excludes ignored and includes replace delete' => [static fn (): mixed => $commit()['changes'], 3],
    'commit current ids after replace and delete' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 3, 5, 6, 7, 8]],
    'commit row six replaced home key' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[6], 'replaced'],
    'commit row seven stayed pending theme after ignore' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'commit next source equals current source' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'rollback status rolls back to savepoint' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback statement ordinal is failing rollback conflict' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 1],
    'rollback reason includes OR ROLLBACK' => [static fn (): mixed => str_contains((string) $rollback()['rollback_reason'], 'OR ROLLBACK'), true],
    'rollback yields only pre-failure ignore statement stream' => [static fn (): mixed => count($rollback()['yielded_returning']), 1],
    'rollback pre-failure ignore yielded no rows' => [static fn (): mixed => $rollback()['yielded_returning'][0]['rows'], []],
    'rollback attempted returning only successful statement before failure' => [static fn (): mixed => count($rollback()['attempted_returning']), 1],
    'rollback restores original row ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'rollback next source keeps pre-failure ignore restored image' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes excludes thrown statement' => [static fn (): mixed => $rollback()['attempted_changes'], 0],
    'dependencies include conflict returning marker' => [static fn (): mixed => in_array('sqlite-update-or-conflict-returning', $commit()['dependencies'], true), true],
    'dependencies include row value marker' => [static fn (): mixed => in_array('sqlite-row-value-current-source-update', $commit()['dependencies'], true), true],
    'dependencies include savepoint rollback marker' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-conflict-rollback', $rollback()['dependencies'], true), true],
    'null conflict key is not considered duplicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE OR IGNORE wp_options SET (blog_id, option_name, status) = (1, NULL, 'null-key') WHERE option_id = 7 RETURNING option_id, option_name, status", $tables, 'option_id', $unique)['returning'], [['option_id' => 7, 'option_name' => null, 'status' => 'null-key']]],
    'malformed unique constraint rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', [[]]), InvalidArgumentException::class],
    'malformed missing unique column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', [['missing']]), InvalidArgumentException::class],
    'malformed empty savepoint statement list rejected' => [static fn (): mixed => SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty savepoint unique constraints rejected' => [static fn (): mixed => SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan::execute($tables, $commitStatements, []), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue returning savepoint conflict current source next128 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
