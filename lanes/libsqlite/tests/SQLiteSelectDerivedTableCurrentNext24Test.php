<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test/home', 'autoload' => 'yes', 'bytes' => 29],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Port Libs', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugin-cache', 'autoload' => 'no', 'bytes' => 44],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'option_value' => '{"color":"blue"}', 'autoload' => 'yes', 'bytes' => 16],
];

$meta = [
    ['option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'front'],
    ['option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'front'],
    ['option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'admin'],
    ['option_id' => 6, 'meta_key' => 'scope', 'meta_value' => 'theme'],
];

$tables = ['wp_options' => $options, 'option_meta' => $meta];

$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);

return [
    'materializes ordered limited derived table rows' => static function (TestRunner $t) use ($column, $tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT option_name, byte_bucket FROM (SELECT option_name, bytes + 1 AS byte_bucket FROM wp_options WHERE autoload = 'yes' ORDER BY bytes DESC LIMIT 3) AS recent ORDER BY byte_bucket",
            $tables,
        );

        $t->same(3, count($rows));
        $t->same(['theme_mods', 'siteurl', 'home'], array_column($rows, 'option_name'));
        $t->same([17, 25, 30], array_column($rows, 'byte_bucket'));
        $t->same(['option_name', 'byte_bucket'], array_keys($rows[0]));
        $t->same(['theme_mods', 'siteurl', 'home'], $column("SELECT option_name FROM (SELECT option_name, bytes FROM wp_options WHERE autoload = 'yes' ORDER BY bytes DESC LIMIT 3) AS d ORDER BY bytes", 'option_name'));
    },

    'renames derived table output columns through alias lists' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT picked_name, picked_bytes FROM (SELECT option_name, bytes FROM wp_options WHERE option_id <= 3 ORDER BY option_id) AS picked(picked_name, picked_bytes) WHERE picked_bytes >= 10 ORDER BY picked_name",
            $tables,
        );

        $t->same(2, count($rows));
        $t->same(['home', 'siteurl'], array_column($rows, 'picked_name'));
        $t->same([29, 24], array_column($rows, 'picked_bytes'));
        $t->same(['picked_name', 'picked_bytes'], array_keys($rows[0]));
        $t->same('home', $rows[0]['picked_name']);
        $t->same(29, $rows[0]['picked_bytes']);
    },

    'joins derived tables back to copied application rows' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, hot.rank AS rank_label FROM wp_options AS o JOIN (SELECT option_id AS option_id, 'rank-' || option_id AS rank FROM wp_options WHERE autoload = 'yes' AND bytes >= 16) AS hot ON hot.option_id = o.option_id ORDER BY hot.rank DESC",
            $tables,
        );

        $t->same(3, count($rows));
        $t->same(['theme_mods', 'home', 'siteurl'], array_column($rows, 'option_name'));
        $t->same(['rank-6', 'rank-2', 'rank-1'], array_column($rows, 'rank_label'));
        $t->same(['option_name', 'rank_label'], array_keys($rows[0]));
        $t->same('theme_mods', $rows[0]['option_name']);
        $t->same('rank-6', $rows[0]['rank_label']);
    },

    'left joins derived tables and preserves null extension' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, scoped.scope AS scope FROM wp_options AS o LEFT JOIN (SELECT option_id, meta_value AS scope FROM option_meta WHERE meta_key = 'scope' AND meta_value IN ('front', 'theme')) AS scoped ON scoped.option_id = o.option_id WHERE o.option_id IN (1, 3, 6) ORDER BY o.option_id",
            $tables,
        );

        $t->same(3, count($rows));
        $t->same(['siteurl', 'blogname', 'theme_mods'], array_column($rows, 'option_name'));
        $t->same(['front', null, 'theme'], array_column($rows, 'scope'));
        $t->same(['option_name', 'scope'], array_keys($rows[0]));
        $t->same(null, $rows[1]['scope']);
    },

    'groups over materialized derived rows' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT load_state, count(*) AS rows, sum(bucket) AS total_bucket FROM (SELECT autoload AS load_state, bytes / 10 AS bucket FROM wp_options WHERE bytes >= 10) AS buckets GROUP BY load_state HAVING sum(bucket) >= 3 ORDER BY load_state",
            $tables,
        );

        $t->same(2, count($rows));
        $t->same(['no', 'yes'], array_column($rows, 'load_state'));
        $t->same([2, 3], array_column($rows, 'rows'));
        $t->same([5.6, 6.9], array_map(static fn (float $value): float => round($value, 1), array_column($rows, 'total_bucket')));
        $t->same(['load_state', 'rows', 'total_bucket'], array_keys($rows[0]));
    },

    'composes nested derived tables with expression predicates' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT label FROM (SELECT option_id, option_name || ':' || autoload AS label, bytes FROM (SELECT option_id, option_name, autoload, bytes FROM wp_options WHERE option_id <= 5) AS base WHERE bytes BETWEEN 10 AND 30) AS labeled WHERE label LIKE '%:yes' ORDER BY option_id DESC",
            $tables,
        );

        $t->same(2, count($rows));
        $t->same(['home:yes', 'siteurl:yes'], array_column($rows, 'label'));
        $t->same(['label'], array_keys($rows[0]));
        $t->same('home:yes', $rows[0]['label']);
        $t->same('siteurl:yes', $rows[1]['label']);
    },

    'uses derived table rows inside scalar subqueries' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT option_name, (SELECT picked_label FROM (SELECT option_name AS picked_name, autoload || ':' || bytes AS picked_label FROM wp_options WHERE bytes >= 20) AS picked WHERE picked_name = option_name) AS label FROM wp_options WHERE option_id IN (1, 3, 5) ORDER BY option_id",
            $tables,
        );

        $t->same(3, count($rows));
        $t->same(['siteurl', 'blogname', '_site_transient_update_plugins'], array_column($rows, 'option_name'));
        $t->same(['yes:24', null, 'no:44'], array_column($rows, 'label'));
        $t->same(['option_name', 'label'], array_keys($rows[0]));
        $t->same(null, $rows[1]['label']);
    },

    'supports derived tables in common table expression bodies' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            "WITH picked AS (SELECT name, bytes FROM (SELECT option_name AS name, bytes FROM wp_options WHERE autoload = 'yes') AS inner_pick WHERE bytes >= 16) SELECT name FROM picked ORDER BY bytes DESC",
            $tables,
        );

        $t->same(3, count($rows));
        $t->same(['home', 'siteurl', 'theme_mods'], array_column($rows, 'name'));
        $t->same(['name'], array_keys($rows[0]));
        $t->same('home', $rows[0]['name']);
        $t->same('theme_mods', $rows[2]['name']);
    },

    'rejects malformed derived table aliases and column lists' => static function (TestRunner $t) use ($tables): void {
        $t->same(['siteurl'], array_column(SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options WHERE option_id = 1)", $tables), 'option_name'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options) AS 1bad", $tables));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT x FROM (SELECT option_name FROM wp_options) AS d(x) trailing", $tables));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT x FROM (SELECT option_name, bytes FROM wp_options) AS d(x)", $tables));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT x FROM (SELECT option_name FROM wp_options) AS d(1bad)", $tables));
    },
];
