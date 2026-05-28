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

$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, '_transient_feed'), (3, '_missing')) RETURNING option_id, option_name || ':' || status AS old_label, bytes + blog_id + 1 AS old_weight, (blog_id, status) = (1, 'stale') AS was_blog_one_stale ORDER BY blog_id DESC LIMIT 2";
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables);

$updateSql = "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'migrated', option_name || ':migrated', bytes + blog_id + 100) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (2, 'siteurl'), (2, 'pending_theme')) RETURNING option_id, option_name || ':' || status AS next_label, bytes + blog_id AS next_weight, (autoload, status) = ('yes', 'migrated') AS migrated_tuple, status IS NOT NULL AS status_present ORDER BY option_id DESC LIMIT 2";
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables);

$cases = [
    'delete returning expression keeps source mutation order' => [static fn (): mixed => array_column($delete()['returning'], 'option_id'), [3, 5]],
    'delete returning concat expression sees old rows' => [static fn (): mixed => array_column($delete()['returning'], 'old_label'), ['_transient_feed:stale', '_transient_feed:stale']],
    'delete returning addition expression can add columns and literals' => [static fn (): mixed => array_column($delete()['returning'], 'old_weight'), [14, 17]],
    'delete returning row value comparison true becomes one' => [static fn (): mixed => $delete()['returning'][0]['was_blog_one_stale'], 1],
    'delete returning row value comparison false becomes zero' => [static fn (): mixed => $delete()['returning'][1]['was_blog_one_stale'], 0],
    'delete returning aliases preserve projection order' => [static fn (): mixed => array_keys($delete()['returning'][0]), ['option_id', 'old_label', 'old_weight', 'was_blog_one_stale']],
    'delete returning selected ids still come from ORDER LIMIT' => [static fn (): mixed => $delete()['plan']->selectedIds, [5, 3]],
    'delete returning result removes row-value matches' => [static fn (): mixed => array_column($delete()['tables']['wp_options'], 'option_id'), [1, 2, 4, 6, 7, 8]],
    'delete returning row-value IN expression exact match' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 6 RETURNING (blog_id, option_name) IN ((2, 'siteurl'), (2, 'home')) AS matched", $tables)['returning'], [['matched' => 1]]],
    'delete returning row-value NOT IN expression false' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 6 RETURNING (blog_id, option_name) NOT IN ((2, 'siteurl'), (2, 'home')) AS not_matched", $tables)['returning'], [['not_matched' => 0]]],
    'delete returning row-value expression unknown with null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 7 RETURNING (blog_id, status) = (2, NULL) AS null_match", $tables)['returning'], [['null_match' => null]]],
    'delete returning IS NULL expression after old row' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 7 RETURNING status IS NULL AS was_null", $tables)['returning'], [['was_null' => 1]]],
    'update returning expression follows source mutation order' => [static fn (): mixed => array_column($update()['returning'], 'option_id'), [6, 7]],
    'update returning concat expression sees new status' => [static fn (): mixed => array_column($update()['returning'], 'next_label'), ['siteurl:migrated', 'pending_theme:migrated']],
    'update returning addition expression sees new bytes' => [static fn (): mixed => array_column($update()['returning'], 'next_weight'), [129, 111]],
    'update returning row-value comparison sees assigned columns' => [static fn (): mixed => array_column($update()['returning'], 'migrated_tuple'), [1, 1]],
    'update returning IS NOT NULL expression sees assigned status' => [static fn (): mixed => array_column($update()['returning'], 'status_present'), [1, 1]],
    'update returning selected ids still sorted by ORDER LIMIT' => [static fn (): mixed => $update()['plan']->selectedIds, [7, 6]],
    'update returning mutation ids remain source order after sorted selection' => [static fn (): mixed => $update()['plan']->mutationIds, [6, 7]],
    'update returning result row six bytes changed' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'bytes', 'option_id')[6], 127],
    'update returning result row seven copied name into value' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'option_value', 'option_id')[7], 'pending_theme:migrated'],
    'update returning expression can mix updated row columns' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (status, bytes) = ('staged', bytes + 10) WHERE option_id = 1 RETURNING option_name || ':' || status AS label, bytes + blog_id AS weight", $tables)['returning'], [['label' => 'siteurl:staged', 'weight' => 35]]],
    'update returning row-value greater expression true' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (blog_id, status) = (3, 'migrated') WHERE option_id = 6 RETURNING (blog_id, option_name) > (2, 'zzzz') AS after_network", $tables)['returning'], [['after_network' => 1]]],
    'update returning row-value less expression false' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET (blog_id, status) = (3, 'migrated') WHERE option_id = 6 RETURNING (blog_id, option_name) < (2, 'zzzz') AS before_network", $tables)['returning'], [['before_network' => 0]]],
    'update returning row-value NOT IN expression unknown with null tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = NULL WHERE option_id = 7 RETURNING (blog_id, status) NOT IN ((2, NULL)) AS not_unknown", $tables)['returning'], [['not_unknown' => null]]],
    'update returning expression parse preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['returning'], "option_id, option_name || ':' || status AS next_label, bytes + blog_id AS next_weight, (autoload, status) = ('yes', 'migrated') AS migrated_tuple, status IS NOT NULL AS status_present"],
    'malformed returning expression without alias rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING option_name || status", $tables), InvalidArgumentException::class],
    'malformed returning row-value arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING (blog_id, option_name) = (1) AS bad", $tables), InvalidArgumentException::class],
    'malformed returning row-value column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING (blog_id + 1, option_name) = (1, 'siteurl') AS bad", $tables), InvalidArgumentException::class],
    'malformed returning unsupported literal rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING json(status) AS bad", $tables), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['row value update delete returning current source next117 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
