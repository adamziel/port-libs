<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep', 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep', 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'status' => 'keep', 'option_value' => 'feed'],
    ['option_id' => 4, 'option_name' => '_transient_big', 'autoload' => 'no', 'bytes' => 110, 'status' => 'keep', 'option_value' => str_repeat('x', 8)],
    ['option_id' => 5, 'option_name' => '_transient_small', 'autoload' => 'no', 'bytes' => 7, 'status' => 'keep', 'option_value' => 'tiny'],
    ['option_id' => 6, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 95, 'status' => 'keep', 'option_value' => 'plugins'],
    ['option_id' => 7, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'status' => 'keep', 'option_value' => 'Example'],
];

$tables = ['wp_options' => $options];

$deleteSql = "DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id AS deleted_id, option_name, bytes ORDER BY bytes DESC, option_name ASC LIMIT 2 OFFSET 1";
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables);

$updateSql = "UPDATE wp_options SET status = 'expired', bytes = bytes + 10, option_value = option_name || ':expired' WHERE autoload = 'no' RETURNING option_id, status, bytes, option_value ORDER BY bytes ASC, option_name DESC LIMIT 2";
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables);

$cases = [
    'delete action parsed' => [static fn (): mixed => $delete()['action'], 'delete'],
    'delete table parsed' => [static fn (): mixed => $delete()['table'], 'wp_options'],
    'delete order selects expected ids' => [static fn (): mixed => $delete()['plan']->selectedIds, [6, 3]],
    'delete returning follows source mutation order' => [static fn (): mixed => array_column($delete()['returning'], 'deleted_id'), [3, 6]],
    'delete returning includes old option names' => [static fn (): mixed => array_column($delete()['returning'], 'option_name'), ['_transient_feed', '_site_transient_update_plugins']],
    'delete returning includes old bytes' => [static fn (): mixed => array_column($delete()['returning'], 'bytes'), [12, 95]],
    'delete result removes selected rows' => [static fn (): mixed => array_column($delete()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 7]],
    'delete qualified rows counted before limit' => [static fn (): mixed => $delete()['plan']->toArray()['qualified_rows'], 4],
    'delete selected rows counted after offset' => [static fn (): mixed => $delete()['plan']->toArray()['selected_rows'], 2],
    'delete order by summary preserved' => [static fn (): mixed => $delete()['plan']->toArray()['order_by'][0], ['column' => 'bytes', 'direction' => 'DESC']],
    'delete limit summary preserved' => [static fn (): mixed => $delete()['plan']->toArray()['limit'], 2],
    'delete offset summary preserved' => [static fn (): mixed => $delete()['plan']->toArray()['offset'], 1],
    'delete comma limit uses offset count form' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id ORDER BY bytes DESC LIMIT 1, 2", $tables)['plan']->selectedIds, [6, 3]],
    'delete negative limit after offset returns all remaining' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id ORDER BY bytes DESC LIMIT 1, -1", $tables)['plan']->selectedIds, [6, 3, 5]],
    'delete zero limit returns no rows' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id ORDER BY bytes DESC LIMIT 0", $tables)['returning'], []],
    'delete no where can use limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options RETURNING option_id ORDER BY option_id DESC LIMIT 1", $tables)['plan']->selectedIds, [7]],
    'delete IN predicate filters values' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id IN (3, 5, 7) RETURNING option_id ORDER BY option_id ASC LIMIT 2", $tables)['plan']->selectedIds, [3, 5]],
    'delete NOT IN predicate filters values' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id NOT IN (1, 2, 3, 4, 5, 6) RETURNING option_id", $tables)['plan']->selectedIds, [7]],
    'delete LIKE predicate filters option names' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_name LIKE '_transient_%' RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [3, 4, 5]],
    'delete GLOB predicate filters option names' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_name GLOB '_site*' RETURNING option_id", $tables)['plan']->selectedIds, [6]],
    'delete IS NULL predicate returns empty' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE autoload IS NULL RETURNING option_id", $tables)['returning'], []],
    'delete comparison predicate uses numeric bytes' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE bytes >= 95 RETURNING option_id ORDER BY bytes DESC", $tables)['plan']->selectedIds, [4, 6]],
    'delete alias projection key preserved' => [static fn (): mixed => array_keys($delete()['returning'][0]), ['deleted_id', 'option_name', 'bytes']],
    'delete wildcard projection returns old row' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 3 RETURNING *", $tables)['returning'][0]['status'], 'keep'],
    'update action parsed' => [static fn (): mixed => $update()['action'], 'update'],
    'update table parsed' => [static fn (): mixed => $update()['table'], 'wp_options'],
    'update order selects lowest transient ids in source mutation order' => [static fn (): mixed => $update()['plan']->selectedIds, [5, 3]],
    'update returning ids follow source mutation order' => [static fn (): mixed => array_column($update()['returning'], 'option_id'), [3, 5]],
    'update returning sees new status' => [static fn (): mixed => array_column($update()['returning'], 'status'), ['expired', 'expired']],
    'update returning sees numeric expression assignment' => [static fn (): mixed => array_column($update()['returning'], 'bytes'), [22, 17]],
    'update returning sees concatenation assignment' => [static fn (): mixed => array_column($update()['returning'], 'option_value'), ['_transient_feed:expired', '_transient_small:expired']],
    'update result preserves source row count' => [static fn (): mixed => count($update()['tables']['wp_options']), 7],
    'update result mutates selected rows only' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id'), [1 => 'keep', 2 => 'keep', 3 => 'expired', 4 => 'keep', 5 => 'expired', 6 => 'keep', 7 => 'keep']],
    'update assignment summary is callable-backed' => [static fn (): mixed => $update()['plan']->toArray()['assignments'], ['status' => 'callable', 'bytes' => 'callable', 'option_value' => 'callable']],
    'update no match returns empty' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'none' WHERE option_id = 99 RETURNING option_id ORDER BY option_id LIMIT 5", $tables)['returning'], []],
    'update no where updates bounded first row by order' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'first' RETURNING option_id, status ORDER BY option_id LIMIT 1", $tables)['returning'], [['option_id' => 1, 'status' => 'first']]],
    'update OFFSET selects later ordered row' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'later' WHERE autoload = 'yes' RETURNING option_id ORDER BY option_name ASC LIMIT 1 OFFSET 1", $tables)['plan']->selectedIds, [2]],
    'update comma limit selects ordered window' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'later' WHERE autoload = 'yes' RETURNING option_id ORDER BY option_name ASC LIMIT 1, 2", $tables)['plan']->selectedIds, [2, 1]],
    'update alias projection returns new values' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'autoloaded' WHERE autoload = 'yes' RETURNING option_id AS id, status AS state ORDER BY option_id LIMIT 2", $tables)['returning'], [['id' => 1, 'state' => 'autoloaded'], ['id' => 2, 'state' => 'autoloaded']]],
    'update column assignment copies old value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = autoload WHERE option_id = 1 RETURNING option_id, status", $tables)['returning'], [['option_id' => 1, 'status' => 'yes']]],
    'update NULL assignment returns null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = NULL WHERE option_id = 1 RETURNING status", $tables)['returning'], [['status' => null]]],
    'update AND predicates all apply' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'big' WHERE autoload = 'no' AND bytes > 90 RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [4, 6]],
    'parse delete returning clause' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSql)['returning'], 'option_id AS deleted_id, option_name, bytes'],
    'parse update assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($updateSql)['assignments']), ['status', 'bytes', 'option_value']],
    'parse comma limit offset' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM wp_options RETURNING option_id LIMIT 3, 4")['offset'], 3],
    'malformed non dml rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute('SELECT * FROM wp_options', $tables), InvalidArgumentException::class],
    'malformed missing returning rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE autoload = 'no'", $tables), InvalidArgumentException::class],
    'malformed table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM missing RETURNING option_id", $tables), InvalidArgumentException::class],
    'delete order expression selects lowest computed bytes' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options RETURNING option_id ORDER BY bytes + 1 LIMIT 1", $tables)['plan']->selectedIds, [5]],
    'malformed limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options RETURNING option_id LIMIT one", $tables), InvalidArgumentException::class],
    'malformed returning expression rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options RETURNING option_id + 1", $tables), InvalidArgumentException::class],
    'delete scalar between predicate returns empty when no rows match' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE bytes BETWEEN 1 AND 2 RETURNING option_id", $tables)['returning'], []],
    'malformed assignment rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status WHERE option_id = 1 RETURNING option_id", $tables), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream update delete returning order limit sql ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
