<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'network_siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['option_name']];

$replaceSql = "UPDATE OR REPLACE wp_options SET (option_name, status, option_value, bytes) = ('siteurl', option_name || ':migrated', option_value || ':next', bytes + blog_id) WHERE option_id IN (2, 3, 5) RETURNING option_id, option_name, status, option_value, bytes, (option_name, status) = ('siteurl', status) AS tuple_ok ORDER BY option_id";
$replace = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', $unique);

$ignoreSql = "UPDATE OR IGNORE wp_options SET (option_name, status, option_value) = ('siteurl', option_name || ':ignored', option_value || ':next') WHERE option_id IN (2, 3) RETURNING option_id, option_name, status ORDER BY option_id";
$ignore = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);

$abortSql = "UPDATE wp_options SET (option_name, status) = ('siteurl', option_name || ':abort') WHERE option_id IN (2, 3) RETURNING option_id";
$nullSql = "UPDATE OR REPLACE wp_options SET (option_name, status) = (NULL, 'nullable') WHERE option_id IN (2, 3) RETURNING option_id, option_name, status ORDER BY option_id";

$cases = [
    'replace action parsed' => [static fn (): mixed => $replace()['action'], 'update'],
    'replace conflict action parsed' => [static fn (): mixed => $replace()['conflict_action'], 'replace'],
    'replace selected rows ordered by option id' => [static fn (): mixed => $replace()['plan']->selectedIds, [2, 3, 5]],
    'replace mutation rows use source order' => [static fn (): mixed => $replace()['plan']->mutationIds, [2, 3, 5]],
    'replace returns every updated attempt before later conflict deletes it' => [static fn (): mixed => array_column($replace()['returning'], 'option_id'), [2, 3, 5]],
    'replace returning sees assigned unique key for all attempts' => [static fn (): mixed => array_column($replace()['returning'], 'option_name'), ['siteurl', 'siteurl', 'siteurl']],
    'replace returning status uses each old option name' => [static fn (): mixed => array_column($replace()['returning'], 'status'), ['home:migrated', '_transient_feed:migrated', '_transient_feed:migrated']],
    'replace returning option value uses each old value' => [static fn (): mixed => array_column($replace()['returning'], 'option_value'), ['https://old.test:next', 'feed:next', 'network-feed:next']],
    'replace returning bytes use source blog ids' => [static fn (): mixed => array_column($replace()['returning'], 'bytes'), [25, 13, 16]],
    'replace returning tuple expressions see next row image' => [static fn (): mixed => array_column($replace()['returning'], 'tuple_ok'), [1, 1, 1]],
    'replace final rows keep only the last same statement conflicting update' => [static fn (): mixed => array_column($replace()['tables']['wp_options'], 'option_id'), [4, 5, 6, 7]],
    'replace final siteurl row is the last selected current-source row' => [static fn (): mixed => array_values(array_filter($replace()['tables']['wp_options'], static fn (array $row): bool => $row['option_name'] === 'siteurl'))[0]['option_id'], 5],
    'replace final siteurl status comes from final selected row' => [static fn (): mixed => array_values(array_filter($replace()['tables']['wp_options'], static fn (array $row): bool => $row['option_name'] === 'siteurl'))[0]['status'], '_transient_feed:migrated'],
    'replace deletes original conflict then earlier selected conflict rows' => [static fn (): mixed => array_column($replace()['deleted_conflict_rows'], 'option_id'), [1, 2, 3]],
    'replace deleted conflict names preserve deletion order' => [static fn (): mixed => array_column($replace()['deleted_conflict_rows'], 'option_name'), ['siteurl', 'siteurl', 'siteurl']],
    'replace records one conflict per selected update' => [static fn (): mixed => count($replace()['conflicts']), 3],
    'replace first conflict targets original siteurl' => [static fn (): mixed => $replace()['conflicts'][0]['conflicting_row_ids'], [1]],
    'replace second conflict targets row two current update' => [static fn (): mixed => $replace()['conflicts'][1]['conflicting_row_ids'], [2]],
    'replace third conflict targets row three current update' => [static fn (): mixed => $replace()['conflicts'][2]['conflicting_row_ids'], [3]],
    'replace conflicts use option name unique columns' => [static fn (): mixed => $replace()['conflicts'][0]['columns'], ['option_name']],
    'replace conflict key is siteurl' => [static fn (): mixed => $replace()['conflicts'][2]['key'], 'siteurl'],
    'replace ignored rows empty' => [static fn (): mixed => $replace()['ignored_rows'], []],
    'replace leaves unrelated timeout row' => [static fn (): mixed => array_column($replace()['tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'replace leaves unrelated network row' => [static fn (): mixed => array_column($replace()['tables']['wp_options'], 'option_name', 'option_id')[6], 'network_siteurl'],
    'replace parses row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($replaceSql)['assignments']), ['option_name', 'status', 'option_value', 'bytes']],
    'replace parse preserves returning projection' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($replaceSql)['returning'], "option_id, option_name, status, option_value, bytes, (option_name, status) = ('siteurl', status) AS tuple_ok"],
    'replace row value assignment can collide with current row after previous replacement' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE OR REPLACE wp_options SET (option_name, status) = ('home', option_name || ':taken') WHERE option_id IN (3, 5) RETURNING option_id, status ORDER BY option_id", $tables, 'option_id', $unique)['returning'], [['option_id' => 3, 'status' => '_transient_feed:taken'], ['option_id' => 5, 'status' => '_transient_feed:taken']]],
    'replace last home conflict survives after two selected updates' => [static fn (): mixed => array_column(SQLiteUpdateDeleteReturningSql::execute("UPDATE OR REPLACE wp_options SET (option_name, status) = ('home', option_name || ':taken') WHERE option_id IN (3, 5) RETURNING option_id ORDER BY option_id", $tables, 'option_id', $unique)['tables']['wp_options'], 'option_id'), [1, 4, 5, 6, 7]],

    'ignore conflict action parsed' => [static fn (): mixed => $ignore()['conflict_action'], 'ignore'],
    'ignore selected ids are conflicting rows' => [static fn (): mixed => $ignore()['plan']->selectedIds, [2, 3]],
    'ignore returns no rows when every update conflicts with current source' => [static fn (): mixed => $ignore()['returning'], []],
    'ignore records ignored row images after assignment' => [static fn (): mixed => array_column($ignore()['ignored_rows'], 'option_id'), [2, 3]],
    'ignore ignored rows carry assigned unique key' => [static fn (): mixed => array_column($ignore()['ignored_rows'], 'option_name'), ['siteurl', 'siteurl']],
    'ignore restores row two original option name' => [static fn (): mixed => array_column($ignore()['tables']['wp_options'], 'option_name', 'option_id')[2], 'home'],
    'ignore restores row three original option name' => [static fn (): mixed => array_column($ignore()['tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'ignore leaves original siteurl row' => [static fn (): mixed => array_column($ignore()['tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'ignore deletes no conflict rows' => [static fn (): mixed => $ignore()['deleted_conflict_rows'], []],
    'ignore records conflicts for both selected rows' => [static fn (): mixed => count($ignore()['conflicts']), 2],
    'ignore conflict ids point at original row after each restore' => [static fn (): mixed => array_column($ignore()['conflicts'], 'conflicting_row_ids'), [[1], [1]]],
    'ignore conflict keys are siteurl' => [static fn (): mixed => array_column($ignore()['conflicts'], 'key'), ['siteurl', 'siteurl']],

    'abort conflict throws before mutating final rows' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($abortSql, $tables, 'option_id', $unique), InvalidArgumentException::class],
    'fail conflict throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('UPDATE ', 'UPDATE OR FAIL ', $abortSql), $tables, 'option_id', $unique), InvalidArgumentException::class],
    'rollback conflict throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(str_replace('UPDATE ', 'UPDATE OR ROLLBACK ', $abortSql), $tables, 'option_id', $unique), InvalidArgumentException::class],
    'null unique row value assignment does not conflict' => [static fn (): mixed => array_column(SQLiteUpdateDeleteReturningSql::execute($nullSql, $tables, 'option_id', $unique)['returning'], 'option_id'), [2, 3]],
    'null unique rows both remain after replace' => [static fn (): mixed => array_column(array_filter(SQLiteUpdateDeleteReturningSql::execute($nullSql, $tables, 'option_id', $unique)['tables']['wp_options'], static fn (array $row): bool => $row['option_name'] === null), 'option_id'), [2, 3]],
    'null unique replacement deletes no rows' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($nullSql, $tables, 'option_id', $unique)['deleted_conflict_rows'], []],
    'malformed unique columns rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', [[]]), InvalidArgumentException::class],
    'malformed unique column name rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', [['']]), InvalidArgumentException::class],
    'missing unique column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', [['missing']]), InvalidArgumentException::class],
    'row value assignment conflict with missing rowid rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($replaceSql, ['wp_options' => [['option_name' => 'siteurl']]], 'option_id', $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['row value update delete returning conflict current source next130 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
