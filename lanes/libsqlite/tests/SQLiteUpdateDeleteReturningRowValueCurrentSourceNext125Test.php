<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep', 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'status' => 'keep', 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'status' => 'keep', 'option_value' => 'feed'],
    ['option_id' => 4, 'option_name' => '_transient_big', 'autoload' => 'no', 'bytes' => 110, 'status' => 'keep', 'option_value' => str_repeat('x', 8)],
    ['option_id' => 5, 'option_name' => '_transient_small', 'autoload' => 'no', 'bytes' => 7, 'status' => 'keep', 'option_value' => 'tiny'],
    ['option_id' => 6, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 95, 'status' => 'keep', 'option_value' => 'plugins'],
    ['option_id' => 7, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'status' => 'keep', 'option_value' => 'Example'],
    ['option_id' => 8, 'option_name' => 'orphaned_cache', 'autoload' => null, 'bytes' => 50, 'status' => 'keep', 'option_value' => 'nullable'],
];
$tables = ['wp_options' => $options];

$deleteSql = "DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id, option_name, (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) AS in_range ORDER BY option_id";
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables);

$updateSql = "UPDATE wp_options SET (status, option_value) = (autoload, option_name || ':row-value') WHERE (autoload, bytes) NOT BETWEEN ('no', 10) AND ('yes', 23) AND option_id IN (1, 2, 4, 7) RETURNING option_id, status, option_value, (autoload, bytes) BETWEEN ('yes', 1) AND ('yes', 30) AS yes_window ORDER BY option_id";
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables);

$nullDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 1) AND ('no', NULL) RETURNING option_id", $tables);
$nullReturning = static fn (): array => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'checked' WHERE option_id IN (3, 8) RETURNING option_id, (autoload, bytes) BETWEEN ('no', 1) AND ('no', NULL) AS nullable_range ORDER BY option_id", $tables);

$cases = [
    'delete parses action' => [static fn (): mixed => $delete()['action'], 'delete'],
    'delete keeps target table' => [static fn (): mixed => $delete()['table'], 'wp_options'],
    'delete row value between selects feed and site transient' => [static fn (): mixed => $delete()['plan']->selectedIds, [3, 6]],
    'delete row value between mutates in source order' => [static fn (): mixed => $delete()['plan']->mutationIds, [3, 6]],
    'delete returning ids are old rows' => [static fn (): mixed => array_column($delete()['returning'], 'option_id'), [3, 6]],
    'delete returning names are old rows' => [static fn (): mixed => array_column($delete()['returning'], 'option_name'), ['_transient_feed', '_site_transient_update_plugins']],
    'delete returning row value between expression is one' => [static fn (): mixed => array_column($delete()['returning'], 'in_range'), [1, 1]],
    'delete removes selected row values only' => [static fn (): mixed => array_column($delete()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 7, 8]],
    'delete qualified count excludes lower and upper misses' => [static fn (): mixed => $delete()['plan']->toArray()['qualified_rows'], 2],
    'delete selected count is two' => [static fn (): mixed => $delete()['plan']->toArray()['selected_rows'], 2],
    'delete order summary still preserved' => [static fn (): mixed => $delete()['plan']->toArray()['order_by'][0], ['column' => 'option_id']],
    'delete lower boundary includes equal tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 12) AND ('no', 12) RETURNING option_id", $tables)['plan']->selectedIds, [3]],
    'delete upper boundary includes equal tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 95) AND ('no', 95) RETURNING option_id", $tables)['plan']->selectedIds, [6]],
    'delete row value less than lower is excluded' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [3, 6]],
    'delete row value greater than upper is excluded' => [static fn (): mixed => in_array(4, SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id", $tables)['plan']->selectedIds, true), false],
    'delete not between keeps row before lower' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) NOT BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 4, 5, 7]],
    'delete not between nullable row is not true' => [static fn (): mixed => in_array(8, SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) NOT BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id", $tables)['plan']->selectedIds, true), false],
    'delete between nullable upper produces no selected rows' => [static fn (): mixed => $nullDelete()['plan']->selectedIds, []],
    'delete where and after row between still applies scalar predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no', 1) AND ('no', 120) AND option_name LIKE '_transient_%' RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [3, 4, 5]],
    'delete scalar and before row between still applies row predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_name GLOB '_site*' AND (autoload, bytes) BETWEEN ('no', 1) AND ('no', 120) RETURNING option_id", $tables)['plan']->selectedIds, [6]],
    'delete row between returning false projects zero' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 4 RETURNING (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) AS in_range", $tables)['returning'][0]['in_range'], 0],
    'delete row between returning nullable projects null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 8 RETURNING (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100) AS in_range", $tables)['returning'][0]['in_range'], null],
    'delete not between returning true projects one' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 4 RETURNING (autoload, bytes) NOT BETWEEN ('no', 10) AND ('no', 100) AS outside_range", $tables)['returning'][0]['outside_range'], 1],
    'delete not between returning nullable stays null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 8 RETURNING (autoload, bytes) NOT BETWEEN ('no', 10) AND ('no', 100) AS outside_range", $tables)['returning'][0]['outside_range'], null],
    'delete row value comparison after decisive prefix ignores later null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes, status) > ('no', 20, NULL) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 4, 6, 7]],
    'delete row value comparison null before decisive suffix is unknown' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes, status) > ('no', NULL, 'keep') RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 7]],
    'delete row between arity mismatch is rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN ('no') AND ('no', 100) RETURNING option_id", $tables), InvalidArgumentException::class],
    'update parses action' => [static fn (): mixed => $update()['action'], 'update'],
    'update row value not between selected ids' => [static fn (): mixed => $update()['plan']->selectedIds, [1, 2]],
    'update row value assignment summary includes status' => [static fn (): mixed => $update()['plan']->toArray()['assignments']['status'], 'callable'],
    'update row value assignment summary includes option value' => [static fn (): mixed => $update()['plan']->toArray()['assignments']['option_value'], 'callable'],
    'update returning status uses current source autoload' => [static fn (): mixed => array_column($update()['returning'], 'status'), ['yes', 'yes']],
    'update returning option value uses current source name concatenation' => [static fn (): mixed => array_column($update()['returning'], 'option_value'), ['siteurl:row-value', 'home:row-value']],
    'update returning between expression sees next row values' => [static fn (): mixed => array_column($update()['returning'], 'yes_window'), [1, 1]],
    'update result mutates only selected rows' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id'), [1 => 'yes', 2 => 'yes', 3 => 'keep', 4 => 'keep', 5 => 'keep', 6 => 'keep', 7 => 'keep', 8 => 'keep']],
    'update result preserves unselected option value' => [static fn (): mixed => $update()['tables']['wp_options'][3]['option_value'], str_repeat('x', 8)],
    'update row value assignment swaps from source columns simultaneously' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, option_value) = (option_value, status) WHERE option_id = 7 RETURNING status, option_value", $tables)['returning'][0], ['status' => 'Example', 'option_value' => 'keep']],
    'update row value between can use updated columns in returning expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (autoload, bytes) = ('no', 20) WHERE option_id = 1 RETURNING (autoload, bytes) BETWEEN ('no', 10) AND ('no', 30) AS moved", $tables)['returning'][0]['moved'], 1],
    'update row value between with limit keeps ordered selected row' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'range' WHERE (autoload, bytes) BETWEEN ('no', 1) AND ('no', 120) RETURNING option_id ORDER BY bytes DESC LIMIT 1", $tables)['plan']->selectedIds, [4]],
    'update row value between with offset keeps second ordered row' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'range' WHERE (autoload, bytes) BETWEEN ('no', 1) AND ('no', 120) RETURNING option_id ORDER BY bytes DESC LIMIT 1 OFFSET 1", $tables)['plan']->selectedIds, [6]],
    'update row value not between excludes null because unknown' => [static fn (): mixed => in_array(8, SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'outside' WHERE (autoload, bytes) NOT BETWEEN ('no', 10) AND ('no', 100) RETURNING option_id", $tables)['plan']->selectedIds, true), false],
    'update nullable returning keeps true and null distinct' => [static fn (): mixed => array_column($nullReturning()['returning'], 'nullable_range'), [null, null]],
    'update row value between and scalar and keeps both terms' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'range' WHERE (autoload, bytes) BETWEEN ('no', 1) AND ('no', 120) AND option_id NOT IN (4, 6) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [3, 5]],
    'update row value not between and scalar and keeps both terms' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'outside' WHERE (autoload, bytes) NOT BETWEEN ('no', 1) AND ('no', 120) AND option_id IN (1, 2, 7) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 7]],
    'update row value assignment arity mismatch rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, option_value) = ('x') WHERE option_id = 1 RETURNING option_id", $tables), InvalidArgumentException::class],
    'update row value assignment repeated column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, status) = ('x', 'y') WHERE option_id = 1 RETURNING option_id", $tables), InvalidArgumentException::class],
    'parse update row value assignments expands columns' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("UPDATE wp_options SET (status, option_value) = ('x', 'y') WHERE option_id = 1 RETURNING option_id")['assignments'], ['status' => "'x'", 'option_value' => "'y'"]],
    'parse delete row value between preserves where clause' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSql)['where'], "(autoload, bytes) BETWEEN ('no', 10) AND ('no', 100)"],
    'parse update row value not between preserves where clause' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['where'], "(autoload, bytes) NOT BETWEEN ('no', 10) AND ('yes', 23) AND option_id IN (1, 2, 4, 7)"],
    'delete row value between rejects scalar left side' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload) BETWEEN ('no') AND ('yes') RETURNING option_id", $tables), InvalidArgumentException::class],
    'delete row value between rejects malformed lower tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, bytes) BETWEEN 'no' AND ('no', 100) RETURNING option_id", $tables), InvalidArgumentException::class],
    'returning row value between requires alias' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING (autoload, bytes) BETWEEN ('no', 10) AND ('no', 100)", $tables), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream update delete returning row value current source next125 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
