<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 16, 'option_value' => 'orphan'],
];

$tables = ['wp_options' => $rows];

$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, '_transient_feed'), (3, '_missing')) RETURNING option_id, blog_id, option_name ORDER BY blog_id DESC LIMIT 2";
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables);

$updateSql = "UPDATE wp_options SET (autoload, status, option_value) = ('yes', 'migrated', option_name || ':migrated'), bytes = bytes + 100 WHERE (blog_id, option_name) = (1, 'siteurl') RETURNING option_id, autoload, status, option_value, bytes";
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables);

$cases = [
    'delete row value action parsed' => [static fn (): mixed => $delete()['action'], 'delete'],
    'delete row value qualified rows count' => [static fn (): mixed => $delete()['plan']->toArray()['qualified_rows'], 2],
    'delete row value selected ordered ids' => [static fn (): mixed => $delete()['plan']->selectedIds, [5, 3]],
    'delete row value mutation source ids' => [static fn (): mixed => $delete()['plan']->mutationIds, [3, 5]],
    'delete row value returning source order' => [static fn (): mixed => array_column($delete()['returning'], 'option_id'), [3, 5]],
    'delete row value returning blogs' => [static fn (): mixed => array_column($delete()['returning'], 'blog_id'), [1, 2]],
    'delete row value removes only tuple matches' => [static fn (): mixed => array_column($delete()['tables']['wp_options'], 'option_id'), [1, 2, 4, 6, 7, 8]],
    'delete row value preserves nonmatching same name' => [static fn (): mixed => in_array(8, array_column($delete()['tables']['wp_options'], 'option_id'), true), true],
    'delete row value NOT IN keeps listed tuples out' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN ((1, 'siteurl'), (1, 'home'), (2, 'siteurl')) RETURNING option_id ORDER BY option_id LIMIT 3", $tables)['plan']->selectedIds, [3, 4, 5]],
    'delete row value inequality picks lexical later tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) > (1, 'zzzz') RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [5, 6, 7, 8]],
    'delete row value less equal includes blog one feed pair' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) <= (1, '_transient_feed') RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [3]],
    'delete row value null comparison is unknown' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) = (2, NULL) RETURNING option_id", $tables)['returning'], []],
    'delete row value null in list is unknown without match' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) IN ((2, NULL)) RETURNING option_id", $tables)['returning'], []],
    'delete row value null not in list keeps decisive mismatches' => [static fn (): mixed => array_column(SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) NOT IN ((2, NULL)) RETURNING option_id", $tables)['returning'], 'option_id'), [1, 2, 3, 4, 8]],
    'delete row value exact match beats later null tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) IN ((2, 'live'), (2, NULL)) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [6]],
    'delete row value works with scalar AND predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE autoload = 'no' AND (blog_id, option_name) = (1, '_transient_timeout_feed') RETURNING option_id", $tables)['plan']->selectedIds, [4]],
    'delete row value not equal excludes exact tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) <> (1, 'siteurl') RETURNING option_id ORDER BY option_id LIMIT 3", $tables)['plan']->selectedIds, [2, 3, 4]],
    'delete row value not equal null is unknown for matching prefix' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) <> (2, NULL) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 3, 4, 8]],
    'delete row value greater than null keeps decisive first term' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, status) > (2, NULL) RETURNING option_id ORDER BY option_id", $tables)['returning'], [['option_id' => 8]]],
    'delete row value comma limit still applies' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (autoload, status) = ('no', 'stale') RETURNING option_id ORDER BY option_id LIMIT 1, 2", $tables)['plan']->selectedIds, [4, 5]],
    'delete row value projection old tuple columns' => [static fn (): mixed => $delete()['returning'][0], ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed']],
    'delete row value parse where preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSql)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (2, '_transient_feed'), (3, '_missing'))"],
    'delete row value parse order preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSql)['order_by'], [['column' => 'blog_id', 'direction' => 'DESC']]],
    'update row value action parsed' => [static fn (): mixed => $update()['action'], 'update'],
    'update row value assignment columns parsed' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($updateSql)['assignments']), ['autoload', 'status', 'option_value', 'bytes']],
    'update row value selected id' => [static fn (): mixed => $update()['plan']->selectedIds, [1]],
    'update row value mutation id' => [static fn (): mixed => $update()['plan']->mutationIds, [1]],
    'update row value returning new autoload' => [static fn (): mixed => $update()['returning'][0]['autoload'], 'yes'],
    'update row value returning new status' => [static fn (): mixed => $update()['returning'][0]['status'], 'migrated'],
    'update row value returning concat expression' => [static fn (): mixed => $update()['returning'][0]['option_value'], 'siteurl:migrated'],
    'update row value scalar assignment also applies' => [static fn (): mixed => $update()['returning'][0]['bytes'], 124],
    'update row value result mutates one row' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id')[1], 'migrated'],
    'update row value result preserves unrelated row' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'update row value IN predicate updates ordered source rows' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (autoload, status) = ('yes', 'purged') WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (2, '_transient_feed')) RETURNING option_id, status ORDER BY bytes DESC LIMIT 2", $tables)['returning'], [['option_id' => 4, 'status' => 'purged'], ['option_id' => 5, 'status' => 'purged']]],
    'update row value NOT IN predicate skips live site urls' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'cleanup' WHERE (blog_id, option_name) NOT IN ((1, 'siteurl'), (2, 'siteurl')) RETURNING option_id ORDER BY option_id LIMIT 4", $tables)['plan']->selectedIds, [2, 3, 4, 5]],
    'update row value greater equal predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'network' WHERE (blog_id, option_name) >= (2, '_transient_feed') RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [5, 6, 7, 8]],
    'update row value null assignment writes null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, option_value) = (NULL, option_name) WHERE (blog_id, option_name) = (1, 'home') RETURNING option_id, status, option_value", $tables)['returning'], [['option_id' => 2, 'status' => null, 'option_value' => 'home']]],
    'update row value assignment summary callable backed' => [static fn (): mixed => $update()['plan']->toArray()['assignments'], ['autoload' => 'callable', 'status' => 'callable', 'option_value' => 'callable', 'bytes' => 'callable']],
    'update row value source order returning after sorted selection' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (autoload, status) = ('yes', 'bulk') WHERE (autoload, status) = ('no', 'stale') RETURNING option_id ORDER BY bytes DESC LIMIT 2", $tables)['plan']->mutationIds, [5, 8]],
    'update row value source order returning values' => [static fn (): mixed => array_column(SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (autoload, status) = ('yes', 'bulk') WHERE (autoload, status) = ('no', 'stale') RETURNING option_id, status ORDER BY bytes DESC LIMIT 2", $tables)['returning'], 'status'), ['bulk', 'bulk']],
    'update row value no match returns empty' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (autoload, status) = ('yes', 'none') WHERE (blog_id, option_name) = (9, 'missing') RETURNING option_id", $tables)['returning'], []],
    'update row value less than predicate source order' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, option_value) = ('local', option_name) WHERE (blog_id, option_name) < (2, 'siteurl') RETURNING option_id, status ORDER BY option_id", $tables)['returning'], [['option_id' => 1, 'status' => 'local'], ['option_id' => 2, 'status' => 'local'], ['option_id' => 3, 'status' => 'local'], ['option_id' => 4, 'status' => 'local'], ['option_id' => 5, 'status' => 'local'], ['option_id' => 7, 'status' => 'local']]],
    'update row value less than predicate expression copy' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, option_value) = ('local', option_name) WHERE (blog_id, option_name) < (2, 'siteurl') RETURNING option_id, option_value ORDER BY option_id LIMIT 1", $tables)['returning'], [['option_id' => 1, 'option_value' => 'siteurl']]],
    'update row value not in with null keeps decisive mismatches' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'decisive' WHERE (blog_id, status) NOT IN ((2, NULL)) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 3, 4, 8]],
    'delete row value subquery expression order limit selects computed priority tuples' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 1 + 1 OFFSET 1) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [4, 5]],
    'delete row value subquery expression order returning source order' => [static fn (): mixed => array_column(SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 1 + 1 OFFSET 1) RETURNING option_id ORDER BY option_id", $tables)['returning'], 'option_id'), [4, 5]],
    'delete row value subquery expression order leaves highest priority skipped by offset' => [static fn (): mixed => in_array(8, array_column(SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 1 + 1 OFFSET 1) RETURNING option_id ORDER BY option_id", $tables)['tables']['wp_options'], 'option_id'), true), true],
    'update row value subquery expression order limit updates selected tuples' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'ranked' WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 2) RETURNING option_id, status ORDER BY option_id", $tables)['returning'], [['option_id' => 5, 'status' => 'ranked'], ['option_id' => 8, 'status' => 'ranked']]],
    'update row value subquery expression order limit omits third computed tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'ranked' WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 2) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [5, 8]],
    'update row value subquery expression order offset with arithmetic limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'ranked' WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 1 + 1 OFFSET 2 - 1) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [4, 5]],
    'update row value subquery expression order parse still preserves outer where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("UPDATE wp_options SET status = 'ranked' WHERE (blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 1 + 1 OFFSET 1) RETURNING option_id ORDER BY option_id")['where'], "(blog_id, option_name) IN (SELECT blog_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY bytes + blog_id DESC LIMIT 1 + 1 OFFSET 1)"],
    'parse row value update where preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['where'], "(blog_id, option_name) = (1, 'siteurl')"],
    'parse row value update returning preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['returning'], 'option_id, autoload, status, option_value, bytes'],
    'malformed row value assignment arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (autoload, status) = ('yes') WHERE option_id = 1 RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed row value predicate arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) = (1) RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed row value IN tuple rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) IN (1, 'siteurl') RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed row value column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id + 1, option_name) = (1, 'siteurl') RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed duplicate row value assignment rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, status) = ('a', 'b') WHERE option_id = 1 RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed unbalanced row value rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name = (1, 'siteurl') RETURNING option_id", $tables), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['row value update delete current source next110 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
