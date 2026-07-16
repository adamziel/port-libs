<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan;

$tests = [];

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['table' => 'wp_options', 'columns' => ['blog_id', 'option_name']]];

$commitStatements = [
    "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'migrated', option_name || ':migrated', bytes + blog_id + 100) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (2, 'pending_theme')) RETURNING option_id, option_name || ':' || status AS label ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, '_transient_feed')) RETURNING option_id, blog_id, option_name ORDER BY blog_id DESC",
];

$rollbackStatements = [
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, option_name",
    "UPDATE wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate') WHERE (blog_id, option_name) = (2, 'pending_theme') RETURNING option_id, blog_id, option_name, status",
];

$nullUniqueStatements = [
    "UPDATE wp_options SET (blog_id, option_name, status) = (2, NULL, 'null-key') WHERE option_id = 7 RETURNING option_id, option_name, status",
];

$commit = static fn (): array => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $commitStatements, $unique, 'app_settings_cleanup', 'option_id');
$rollback = static fn (): array => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $rollbackStatements, $unique, 'app_settings_cleanup', 'option_id');
$nullUnique = static fn (): array => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $nullUniqueStatements, $unique, 'app_settings_cleanup', 'option_id');

$cases = [
    'commit status releases savepoint' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit not rolled back' => [static fn (): mixed => $commit()['rolled_back'], false],
    'commit savepoint is named' => [static fn (): mixed => $commit()['savepoint'], 'app_settings_cleanup'],
    'commit changes count includes update and delete rows' => [static fn (): mixed => $commit()['changes'], 4],
    'commit attempted changes count matches changes' => [static fn (): mixed => $commit()['attempted_changes'], 4],
    'commit executed two statements' => [static fn (): mixed => count($commit()['executed_statements']), 2],
    'commit first statement is update' => [static fn (): mixed => $commit()['executed_statements'][0]['action'], 'update'],
    'commit first selected ids' => [static fn (): mixed => $commit()['executed_statements'][0]['selected_ids'], [1, 7]],
    'commit first mutation ids source order' => [static fn (): mixed => $commit()['executed_statements'][0]['mutation_ids'], [1, 7]],
    'commit first returning labels' => [static fn (): mixed => array_column($commit()['executed_statements'][0]['returning_rows'], 'label'), ['siteurl:migrated', 'pending_theme:migrated']],
    'commit second statement is delete' => [static fn (): mixed => $commit()['executed_statements'][1]['action'], 'delete'],
    'commit delete selected order honors order by' => [static fn (): mixed => $commit()['executed_statements'][1]['selected_ids'], [5, 3]],
    'commit delete mutation order follows source rows' => [static fn (): mixed => $commit()['executed_statements'][1]['mutation_ids'], [3, 5]],
    'commit yielded both returning streams' => [static fn (): mixed => array_column($commit()['yielded_returning'], 'action'), ['update', 'delete']],
    'commit attempted returning equals yielded count' => [static fn (): mixed => count($commit()['attempted_returning']), count($commit()['yielded_returning'])],
    'commit current source row ids after delete' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 6, 7]],
    'commit next source matches current source' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit updated row one status' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'migrated'],
    'commit updated row seven value' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'pending_theme:migrated'],
    'commit delete removed blog one feed' => [static fn (): mixed => in_array(3, array_column($commit()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'commit delete removed blog two feed' => [static fn (): mixed => in_array(5, array_column($commit()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'commit rollback changed tables empty' => [static fn (): mixed => $commit()['rollback_changed_tables'], []],
    'commit savepoint image preserves original count' => [static fn (): mixed => count($commit()['savepoint_image_tables']['wp_options']), 7],
    'commit attempted row count is after delete' => [static fn (): mixed => $commit()['attempted_row_counts']['wp_options'], 5],
    'commit restored row count is committed count' => [static fn (): mixed => $commit()['rollback_restored_row_counts']['wp_options'], 5],
    'rollback status rolls back to savepoint' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback preserves savepoint active' => [static fn (): mixed => $rollback()['savepoint_preserved'], true],
    'rollback reason names unique constraint' => [static fn (): mixed => $rollback()['rollback_reason'], 'unique-constraint:wp_options:blog_id,option_name:1|home'],
    'rollback statement ordinal is failing update' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 1],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes includes delete and update' => [static fn (): mixed => $rollback()['attempted_changes'], 2],
    'rollback executed statement count includes failing statement' => [static fn (): mixed => count($rollback()['executed_statements']), 2],
    'rollback yielded only successful delete returning' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['delete']],
    'rollback attempted returning retains failing update evidence' => [static fn (): mixed => array_column($rollback()['attempted_returning'], 'action'), ['delete', 'update']],
    'rollback delete returning old row before rollback' => [static fn (): mixed => $rollback()['yielded_returning'][0]['rows'], [['option_id' => 4, 'option_name' => '_transient_timeout_feed']]],
    'rollback failing update returning attempted duplicate' => [static fn (): mixed => $rollback()['attempted_returning'][1]['rows'], [['option_id' => 7, 'blog_id' => 1, 'option_name' => 'home', 'status' => 'duplicate']]],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'rollback current source restores row four' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'rollback current source restores row seven option name' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'rollback next source keeps attempted row ids after delete' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7]],
    'rollback next source keeps attempted duplicate name' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'home'],
    'rollback changed tables records wp options' => [static fn (): mixed => $rollback()['rollback_changed_tables'], ['wp_options']],
    'rollback restored row count returns original' => [static fn (): mixed => $rollback()['rollback_restored_row_counts']['wp_options'], 7],
    'rollback attempted row count shows delete before rollback' => [static fn (): mixed => $rollback()['attempted_row_counts']['wp_options'], 6],
    'rollback dependencies include row value marker' => [static fn (): mixed => in_array('sqlite-row-value-update-delete', $rollback()['dependencies'], true), true],
    'rollback dependencies include savepoint current source marker' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-rollback', $rollback()['dependencies'], true), true],
    'rollback dependencies include returning ordering marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-savepoint-rollback', $rollback()['dependencies'], true), true],
    'null unique status releases because sqlite unique permits nulls' => [static fn (): mixed => $nullUnique()['status'], 'released'],
    'null unique row seven option name becomes null' => [static fn (): mixed => array_column($nullUnique()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], null],
    'null unique changes count one' => [static fn (): mixed => $nullUnique()['changes'], 1],
    'null unique returning rows include null key' => [static fn (): mixed => $nullUnique()['yielded_returning'][0]['rows'], [['option_id' => 7, 'option_name' => null, 'status' => 'null-key']]],
    'malformed empty statement list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed missing unique table rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $commitStatements, [['table' => 'missing', 'columns' => ['id']]], 'app_settings_cleanup', 'option_id'), InvalidArgumentException::class],
    'malformed missing unique column rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $commitStatements, [['table' => 'wp_options', 'columns' => ['missing']]], 'app_settings_cleanup', 'option_id'), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['row value update delete savepoint current source next126 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
