<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueSavepointUpsertCurrentSourceNextPlan;

$tests = [];

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 12, 'option_value' => 'Old Blog'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
];
$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name'], ['option_id']];

$insertSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value) VALUES (6, 2, 'blogdescription', 'no', 'inserted', 8, 'Network Tagline') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'updated', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$updateSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value) VALUES (7, 1, 'siteurl', 'no', 'incoming', 5, 'https://new.test') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'updated', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$rollbackSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value) VALUES (8, 2, 'home', 'no', 'incoming', 6, 'https://network-new.test') ON CONFLICT (blog_id, option_name) DO UPDATE SET (option_id, status, bytes, option_value) = (1, 'duplicate-rowid', bytes + excluded.bytes, excluded.option_value) RETURNING option_id, blog_id, option_name, status";
$nullKeySql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value) VALUES (9, 3, NULL, 'no', 'null-key', 1, 'anonymous') ON CONFLICT (blog_id, option_name) DO UPDATE SET (status, option_value) = ('updated', excluded.option_value) RETURNING option_id, blog_id, option_name, status";

$commitStatements = [$insertSql, $updateSql];
$rollbackStatements = [$insertSql, $updateSql, $rollbackSql];
$nullStatements = [$nullKeySql];

$parsed = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse($updateSql);
$commit = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, $commitStatements, $unique, 'wp_options_upsert_batch');
$rollback = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, $rollbackStatements, $unique, 'wp_options_upsert_batch');
$nullKey = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, $nullStatements, $unique, 'wp_options_null_key_batch');

$cases = [
    'parse table name' => [static fn (): mixed => $parsed()['table'], 'wp_options'],
    'parse insert columns' => [static fn (): mixed => $parsed()['columns'], ['option_id', 'blog_id', 'option_name', 'autoload', 'status', 'bytes', 'option_value']],
    'parse conflict target' => [static fn (): mixed => $parsed()['conflict_target'], ['blog_id', 'option_name']],
    'parse row value assignments' => [static fn (): mixed => array_keys($parsed()['assignments']), ['autoload', 'status', 'bytes', 'option_value']],
    'parse returning columns' => [static fn (): mixed => $parsed()['returning'], ['option_id', 'blog_id', 'option_name', 'autoload', 'status', 'bytes', 'option_value']],
    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit not rolled back' => [static fn (): mixed => $commit()['rolled_back'], false],
    'commit savepoint name recorded' => [static fn (): mixed => $commit()['savepoint'], 'wp_options_upsert_batch'],
    'commit executed two statements' => [static fn (): mixed => count($commit()['executed_statements']), 2],
    'commit statement actions insert then update' => [static fn (): mixed => array_column($commit()['executed_statements'], 'action'), ['insert', 'update']],
    'commit first input row id' => [static fn (): mixed => $commit()['executed_statements'][0]['input_row']['option_id'], 6],
    'commit first returning inserted row' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['option_name'], 'blogdescription'],
    'commit second returning keeps original rowid' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['option_id'], 1],
    'commit second returning updates autoload from excluded' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['autoload'], 'no'],
    'commit second returning adds bytes' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['bytes'], 29],
    'commit second returning concatenates old and excluded values' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['option_value'], 'https://old.test:https://new.test'],
    'commit records inserted row ordinal' => [static fn (): mixed => $commit()['inserted_rows'][0]['ordinal'], 0],
    'commit records updated row ordinal' => [static fn (): mixed => $commit()['updated_rows'][0]['ordinal'], 1],
    'commit records conflict row id' => [static fn (): mixed => $commit()['conflicts'][0]['row_id'], 1],
    'commit records conflict key' => [static fn (): mixed => $commit()['conflicts'][0]['key'], '1|siteurl'],
    'commit changes count is two' => [static fn (): mixed => $commit()['changes'], 2],
    'commit attempted changes count is two' => [static fn (): mixed => $commit()['attempted_changes'], 2],
    'commit current row count includes insert' => [static fn (): mixed => count($commit()['current_source_tables']['wp_options']), 6],
    'commit current source equals next source' => [static fn (): mixed => $commit()['current_source_tables'], $commit()['next_source_tables']],
    'commit savepoint image retains original rows' => [static fn (): mixed => count($commit()['savepoint_image_tables']['wp_options']), 5],
    'commit inserted row exists in current source' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[6], 'blogdescription'],
    'commit updated siteurl status' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'updated'],
    'commit home row remains untouched' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'rollback status rolls back' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback statement ordinal is failing upsert' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 2],
    'rollback reason names rowid unique failure' => [static fn (): mixed => $rollback()['rollback_reason'], 'SQLite UPSERT unique constraint failed after DO UPDATE: option_id=1'],
    'rollback yielded only successful pre-failure streams' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['insert', 'update']],
    'rollback attempted returning omits thrown statement' => [static fn (): mixed => count($rollback()['attempted_returning']), 2],
    'rollback current source restores original row count' => [static fn (): mixed => count($rollback()['current_source_tables']['wp_options']), 5],
    'rollback current source restores siteurl bytes' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'bytes', 'option_id')[1], 24],
    'rollback current source omits inserted row six' => [static fn (): mixed => in_array(6, array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback next source retains attempted insert' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[6], 'blogdescription'],
    'rollback next source retains attempted siteurl update' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'status', 'option_id')[1], 'updated'],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes counts successful statements before failing throw' => [static fn (): mixed => $rollback()['attempted_changes'], 2],
    'rollback savepoint image remains original' => [static fn (): mixed => $rollback()['savepoint_image_tables'], $tables],
    'null conflict key inserts rather than updates' => [static fn (): mixed => $nullKey()['executed_statements'][0]['action'], 'insert'],
    'null conflict key returning has null option name' => [static fn (): mixed => $nullKey()['yielded_returning'][0]['rows'][0]['option_name'], null],
    'null conflict key current source row count increments' => [static fn (): mixed => count($nullKey()['current_source_tables']['wp_options']), 6],
    'dependencies include upsert marker' => [static fn (): mixed => in_array('sqlite-insert-on-conflict-do-update', $commit()['dependencies'], true), true],
    'dependencies include row-value assignment marker' => [static fn (): mixed => in_array('sqlite-row-value-upsert-assignment', $commit()['dependencies'], true), true],
    'dependencies include savepoint rollback marker' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-upsert-rollback', $rollback()['dependencies'], true), true],
    'malformed empty statement list rejected' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, $commitStatements, []), InvalidArgumentException::class],
    'malformed non upsert SQL rejected' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse('INSERT INTO wp_options VALUES (1)'), InvalidArgumentException::class],
    'malformed unknown conflict target rolls back savepoint' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, [$updateSql], [['option_name']])['status'], 'rolled-back-to-savepoint'],
    'malformed unknown conflict target reason recorded' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, [$updateSql], [['option_name']])['rollback_reason'], 'SQLite row-value UPSERT conflict target does not match a unique constraint'],
    'malformed assignment arity rejected' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse("INSERT INTO wp_options (option_id, blog_id, option_name) VALUES (10, 1, 'x') ON CONFLICT (blog_id, option_name) DO UPDATE SET (status, bytes) = ('bad') RETURNING option_id"), InvalidArgumentException::class],
    'malformed insert arity rolls back savepoint' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, ["INSERT INTO wp_options (option_id, blog_id, option_name) VALUES (10, 1) ON CONFLICT (blog_id, option_name) DO UPDATE SET (status, bytes) = ('bad', 1) RETURNING option_id"], $unique)['rollback_reason'], 'SQLite row-value UPSERT insert arity mismatch'],
    'malformed missing excluded column rolls back savepoint' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, ["INSERT INTO wp_options (option_id, blog_id, option_name, status) VALUES (10, 1, 'home', 'x') ON CONFLICT (blog_id, option_name) DO UPDATE SET (status, option_value) = ('x', excluded.missing) RETURNING option_id"], $unique)['rollback_reason'], 'SQLite row-value UPSERT column missing is missing'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue savepoint upsert current source next131 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
