<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

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
$failRows = [$rows[0], $rows[1], $rows[2], $rows[3], $rows[4], $rows[5], $rows[7], $rows[6]];
$failTables = ['wp_options' => $failRows];
$unique = [['blog_id', 'option_name']];

$failSql = "UPDATE OR FAIL wp_options SET (option_name, status, option_value) = ('siteurl', option_name || ':fail', option_value || ':next') WHERE option_id IN (8, 7) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id DESC";
$commitSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status) = (blog_id, option_name || ':migrated', 'ok') WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";

$fail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $failTables, 'option_id', $unique, true);
$plainFail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $failTables, 'option_id', $unique);
$savepointFail = static fn (): array => SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute($failTables, [$failSql, $cleanupSql], $unique, 'app_settings_fail_batch', 'option_id');
$savepointCommit = static fn (): array => SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute($tables, [$commitSql, $cleanupSql], $unique, 'app_settings_fail_batch', 'option_id');

$cases = [
    'parser records OR FAIL conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failSql)['conflict_action'], 'fail'],
    'plain fail still throws outside preserve mode' => [$plainFail, InvalidArgumentException::class],
    'preserve fail returns conflict action' => [static fn (): mixed => $fail()['conflict_action'], 'fail'],
    'preserve fail selected rows use descending order' => [static fn (): mixed => $fail()['plan']->selectedIds, [8, 7]],
    'preserve fail mutation ids include attempted failing row' => [static fn (): mixed => $fail()['plan']->mutationIds, [8, 7]],
    'preserve fail returns prior successful row only' => [static fn (): mixed => array_column($fail()['returning'], 'option_id'), [8]],
    'preserve fail returning status uses row-value source expression' => [static fn (): mixed => $fail()['returning'][0]['status'], 'orphaned_cache:fail'],
    'preserve fail returning option value uses source value' => [static fn (): mixed => $fail()['returning'][0]['option_value'], 'cache:next'],
    'preserve fail current row eight changed' => [static fn (): mixed => array_column($fail()['tables']['wp_options'], 'status', 'option_id')[8], 'orphaned_cache:fail'],
    'preserve fail current row seven restored after conflict' => [static fn (): mixed => array_column($fail()['tables']['wp_options'], 'status', 'option_id')[7], null],
    'preserve fail current row seven not reached' => [static fn (): mixed => array_column($fail()['tables']['wp_options'], 'status', 'option_id')[7], null],
    'preserve fail conflict row id is row seven' => [static fn (): mixed => $fail()['failed_conflict']['row_id'], 7],
    'preserve fail conflict peer is network siteurl' => [static fn (): mixed => $fail()['failed_conflict']['conflicting_row_ids'], [5]],
    'preserve fail conflict key records row value unique tuple' => [static fn (): mixed => $fail()['failed_conflict']['key'], '2|siteurl'],
    'preserve fail conflicts list includes failed conflict' => [static fn (): mixed => count($fail()['conflicts']), 1],
    'preserve fail ignores no rows' => [static fn (): mixed => $fail()['ignored_rows'], []],
    'preserve fail deletes no conflict rows' => [static fn (): mixed => $fail()['deleted_conflict_rows'], []],
    'preserve fail keeps original row count' => [static fn (): mixed => count($fail()['tables']['wp_options']), 8],

    'savepoint fail status is preserved failure' => [static fn (): mixed => $savepointFail()['status'], 'failed-savepoint-preserved'],
    'savepoint fail flag true' => [static fn (): mixed => $savepointFail()['failed'], true],
    'savepoint fail statement ordinal zero' => [static fn (): mixed => $savepointFail()['failed_statement_ordinal'], 0],
    'savepoint fail does not run cleanup statement' => [static fn (): mixed => count($savepointFail()['executed_statements']), 1],
    'savepoint fail keeps savepoint active' => [static fn (): mixed => $savepointFail()['savepoint_preserved'], true],
    'savepoint fail current source includes prior row change' => [static fn (): mixed => array_column($savepointFail()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned_cache:fail'],
    'savepoint fail current source restores failing row' => [static fn (): mixed => array_column($savepointFail()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'savepoint fail current source leaves savepoint image row one' => [static fn (): mixed => array_column($savepointFail()['current_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'savepoint fail image still has original row eight' => [static fn (): mixed => array_column($savepointFail()['savepoint_image_tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'savepoint fail changed table reports wp options' => [static fn (): mixed => $savepointFail()['savepoint_changed_tables'], ['wp_options']],
    'savepoint fail yielded one returning stream' => [static fn (): mixed => count($savepointFail()['yielded_returning']), 1],
    'savepoint fail yielded stream has one row' => [static fn (): mixed => array_column($savepointFail()['yielded_returning'][0]['rows'], 'option_id'), [8]],
    'savepoint fail changes count preserves prior row change' => [static fn (): mixed => $savepointFail()['changes'], 1],
    'savepoint fail row count unchanged' => [static fn (): mixed => $savepointFail()['row_counts']['wp_options'], 8],
    'savepoint fail statement conflict action recorded' => [static fn (): mixed => $savepointFail()['executed_statements'][0]['conflict_action'], 'fail'],
    'savepoint fail statement failed conflict row id recorded' => [static fn (): mixed => $savepointFail()['executed_statements'][0]['failed_conflict']['row_id'], 7],
    'savepoint fail dependency records partial fail behavior' => [static fn (): mixed => in_array('sqlite-update-or-fail-partial-rowvalue-returning', $savepointFail()['dependencies'], true), true],
    'savepoint fail dependency records preserved savepoint' => [static fn (): mixed => in_array('sqlite-savepoint-preserves-fail-statement-changes', $savepointFail()['dependencies'], true), true],

    'savepoint commit status released' => [static fn (): mixed => $savepointCommit()['status'], 'released'],
    'savepoint commit failed false' => [static fn (): mixed => $savepointCommit()['failed'], false],
    'savepoint commit executes both statements' => [static fn (): mixed => count($savepointCommit()['executed_statements']), 2],
    'savepoint commit first statement returns two migrated rows' => [static fn (): mixed => array_column($savepointCommit()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'savepoint commit cleanup deletes two old transient rows' => [static fn (): mixed => array_column($savepointCommit()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'savepoint commit final row ids omit deleted transient rows' => [static fn (): mixed => array_column($savepointCommit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'savepoint commit row seven migrated name' => [static fn (): mixed => array_column($savepointCommit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:migrated'],
    'savepoint commit row eight migrated status' => [static fn (): mixed => array_column($savepointCommit()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'ok'],
    'savepoint commit changes include updates and deletes' => [static fn (): mixed => $savepointCommit()['changes'], 4],
    'savepoint commit changed tables reports wp options' => [static fn (): mixed => $savepointCommit()['savepoint_changed_tables'], ['wp_options']],
    'savepoint commit row count after cleanup' => [static fn (): mixed => $savepointCommit()['row_counts']['wp_options'], 6],
    'savepoint commit failed conflict is null' => [static fn (): mixed => $savepointCommit()['failed_conflict'], null],
    'savepoint commit does not preserve savepoint' => [static fn (): mixed => $savepointCommit()['savepoint_preserved'], false],

    'malformed empty statement list rejected' => [static fn (): mixed => SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute($tables, [$commitSql], []), InvalidArgumentException::class],
    'malformed table rows rejected' => [static fn (): mixed => SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$commitSql], $unique, 'app_settings_fail_batch', 'option_id'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['row value returning fail savepoint current source next132 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
