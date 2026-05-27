<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20, 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20, 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => 'Example Site', 'bytes' => 12, 'priority' => 10],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'option_value' => 'cached feed', 'bytes' => 48, 'priority' => 50],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'option_value' => 'plugin cache', 'bytes' => 96, 'priority' => 40],
    ['option_id' => 6, 'option_name' => 'theme_mods_twentytwentyfour', 'autoload' => 'yes', 'option_value' => 'theme mods', 'bytes' => 72, 'priority' => 60],
];

$tables = ['wp_options' => $rows];
$column = static fn (string $sql, string $column): array => array_column(SQLiteSelectSql::execute($sql, $tables), $column);

$cases = [
    'first projected column ascending' => ['SELECT option_name AS name, bytes FROM wp_options ORDER BY 1', 'name', ['_site_transient_update_plugins', '_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour']],
    'first projected column descending' => ['SELECT option_name AS name, bytes FROM wp_options ORDER BY 1 DESC', 'name', ['theme_mods_twentytwentyfour', 'siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins']],
    'second projected column ascending' => ['SELECT option_name AS name, bytes FROM wp_options ORDER BY 2, 1', 'name', ['blogname', 'home', 'siteurl', '_transient_feed', 'theme_mods_twentytwentyfour', '_site_transient_update_plugins']],
    'second projected column descending' => ['SELECT option_name AS name, bytes FROM wp_options ORDER BY 2 DESC, 1', 'name', ['_site_transient_update_plugins', 'theme_mods_twentytwentyfour', '_transient_feed', 'home', 'siteurl', 'blogname']],
    'third projected column chains before alias' => ['SELECT autoload, bytes, option_name AS name FROM wp_options ORDER BY 1, 2 DESC, 3', 'name', ['_site_transient_update_plugins', '_transient_feed', 'theme_mods_twentytwentyfour', 'home', 'siteurl', 'blogname']],
    'second projected renamed numeric column descending' => ['SELECT option_name AS name, priority AS sort_key FROM wp_options ORDER BY 2 DESC', 'name', ['theme_mods_twentytwentyfour', '_transient_feed', '_site_transient_update_plugins', 'siteurl', 'home', 'blogname']],
    'second projected renamed text column descending' => ['SELECT priority AS p, option_name AS name FROM wp_options ORDER BY 2 DESC', 'name', ['theme_mods_twentytwentyfour', 'siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins']],
    'alias collision still uses first projected ordinal' => ['SELECT option_name AS bytes, bytes AS name FROM wp_options ORDER BY 1', 'bytes', ['_site_transient_update_plugins', '_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour']],
    'named term can follow ordinal' => ['SELECT option_name AS name, autoload AS flag FROM wp_options ORDER BY 2, name', 'name', ['_site_transient_update_plugins', '_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour']],
    'named term can precede ordinal' => ['SELECT option_name AS name, autoload AS flag FROM wp_options ORDER BY flag DESC, 1', 'name', ['blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour', '_site_transient_update_plugins', '_transient_feed']],
    'ordinal under where filters before sorting' => ["SELECT option_name AS name, priority FROM wp_options WHERE autoload = 'yes' ORDER BY 2 DESC", 'name', ['theme_mods_twentytwentyfour', 'siteurl', 'home', 'blogname']],
    'ordinal with limit keeps sorted prefix' => ['SELECT option_name AS name, priority FROM wp_options ORDER BY 2 LIMIT 3', 'name', ['blogname', 'home', 'siteurl']],
    'ordinal with offset keeps sorted tail' => ['SELECT option_name AS name, priority FROM wp_options ORDER BY 2 LIMIT 2 OFFSET 3', 'name', ['_site_transient_update_plugins', '_transient_feed']],
    'ordinal with comma limit keeps sqlite offset count order' => ['SELECT option_name AS name, priority FROM wp_options ORDER BY 2 LIMIT 2, 3', 'name', ['siteurl', '_site_transient_update_plugins', '_transient_feed']],
    'distinct result column ordinal descending' => ['SELECT DISTINCT autoload AS flag FROM wp_options ORDER BY 1 DESC', 'flag', ['yes', 'no']],
    'collate nocase applies to ordinal column' => ['SELECT option_name AS name FROM wp_options ORDER BY 1 COLLATE NOCASE', 'name', ['_site_transient_update_plugins', '_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour']],
    'cast expression alias is reachable by ordinal' => ['SELECT cast(priority AS TEXT) AS text_priority, option_name AS name FROM wp_options ORDER BY 1 DESC', 'name', ['theme_mods_twentytwentyfour', '_transient_feed', '_site_transient_update_plugins', 'siteurl', 'home', 'blogname']],
    'scalar function alias is reachable by ordinal' => ['SELECT length(option_name) AS name_length, option_name AS name FROM wp_options ORDER BY 1, 2', 'name', ['home', 'siteurl', 'blogname', '_transient_feed', 'theme_mods_twentytwentyfour', '_site_transient_update_plugins']],
    'unary expression alias is reachable by ordinal' => ['SELECT -priority AS negative_priority, option_name AS name FROM wp_options ORDER BY 1', 'name', ['theme_mods_twentytwentyfour', '_transient_feed', '_site_transient_update_plugins', 'siteurl', 'home', 'blogname']],
    'json expression alias is reachable by ordinal' => ["SELECT json_extract('{\"priority\":4}', '$.priority') AS priority, option_name AS name FROM wp_options WHERE option_id IN (1, 2) ORDER BY 1 DESC, 2", 'name', ['home', 'siteurl']],
    'no from constant select accepts ordinal' => ['SELECT 2 AS n, 1 AS m ORDER BY 2', 'n', [2]],
    'no from expression select accepts ordinal' => ["SELECT 'wp_options' AS table_name, 42 AS rows ORDER BY 1 DESC", 'table_name', ['wp_options']],
    'recursive cte projection ordinal' => ['WITH RECURSIVE nums(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM nums WHERE n < 4) SELECT n, n * n AS square FROM nums ORDER BY 2 DESC LIMIT 2', 'n', [4, 3]],
    'values cte projection ordinal' => ["WITH pairs(name, weight) AS (VALUES ('theme', 3), ('site', 1), ('plugin', 2)) SELECT name, weight FROM pairs ORDER BY 2, 1", 'name', ['site', 'plugin', 'theme']],
    'joined projected column ordinal' => ["WITH meta(option_id, label) AS (VALUES (1, 'front'), (6, 'theme')) SELECT o.option_name AS name, m.label FROM wp_options AS o JOIN meta AS m ON o.option_id = m.option_id ORDER BY 2 DESC, 1", 'name', ['theme_mods_twentytwentyfour', 'siteurl']],
    'grouped sum result ordinal descending' => ['SELECT autoload AS flag, sum(bytes) AS total FROM wp_options GROUP BY autoload ORDER BY 2 DESC', 'flag', ['no', 'yes']],
    'grouped avg result ordinal descending' => ['SELECT autoload AS flag, avg(bytes) AS average_bytes FROM wp_options GROUP BY autoload ORDER BY 2 DESC', 'flag', ['no', 'yes']],
    'grouped min result ordinal ascending' => ['SELECT autoload AS flag, min(bytes) AS min_bytes FROM wp_options GROUP BY autoload ORDER BY 2, 1', 'flag', ['yes', 'no']],
    'grouped max result ordinal descending' => ['SELECT autoload AS flag, max(bytes) AS max_bytes FROM wp_options GROUP BY autoload ORDER BY 2 DESC', 'flag', ['no', 'yes']],
    'grouped having result ordinal' => ['SELECT autoload AS flag, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) >= 100 ORDER BY 1 DESC', 'flag', ['yes', 'no']],
    'collate rtrim applies to ordinal' => ["WITH labels(name) AS (VALUES ('cache  '), ('cache'), ('cache z')) SELECT name FROM labels ORDER BY 1 COLLATE RTRIM", 'name', ['cache  ', 'cache', 'cache z']],
    'descending collate nocase applies to ordinal' => ["WITH labels(name) AS (VALUES ('Alpha'), ('beta'), ('Cache')) SELECT name FROM labels ORDER BY 1 COLLATE NOCASE DESC", 'name', ['Cache', 'beta', 'Alpha']],
    'ordinal orders expression alias with where and limit' => ["SELECT option_name || ':' || priority AS label FROM wp_options WHERE bytes >= 20 ORDER BY 1 LIMIT 4", 'label', ['_site_transient_update_plugins:40', '_transient_feed:50', 'home:20', 'siteurl:30']],
    'ordinal orders second projected numeric alias with where and limit' => ["SELECT option_name AS name, priority AS sort_key FROM wp_options WHERE bytes >= 20 ORDER BY 2 DESC LIMIT 4", 'name', ['theme_mods_twentytwentyfour', '_transient_feed', '_site_transient_update_plugins', 'siteurl']],
    'ordinal preserves stable input order for tied terms' => ['SELECT autoload AS flag, option_name AS name FROM wp_options ORDER BY 1', 'name', ['_transient_feed', '_site_transient_update_plugins', 'siteurl', 'home', 'blogname', 'theme_mods_twentytwentyfour']],
    'ordinal tie breaker removes stable input ambiguity' => ['SELECT autoload AS flag, option_name AS name FROM wp_options ORDER BY 1, 2', 'name', ['_site_transient_update_plugins', '_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour']],
    'ordinal order uses projected storage class not source column name' => ['SELECT priority AS option_name, option_name AS label FROM wp_options ORDER BY 1', 'label', ['blogname', 'home', 'siteurl', '_site_transient_update_plugins', '_transient_feed', 'theme_mods_twentytwentyfour']],
    'ordinal order after hidden expression term remains stripped' => ['SELECT option_name AS name, autoload AS flag FROM wp_options ORDER BY 2, length(option_name)', 'name', ['_transient_feed', '_site_transient_update_plugins', 'home', 'siteurl', 'blogname', 'theme_mods_twentytwentyfour']],
    'ordinal order before hidden expression term remains stripped' => ['SELECT option_name AS name, autoload AS flag FROM wp_options ORDER BY 1, length(option_name)', 'name', ['_site_transient_update_plugins', '_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods_twentytwentyfour']],
    'ordinal orders projected blob alias by storage class' => ["SELECT zeroblob(priority / 20) AS blob_value, option_name AS name FROM wp_options ORDER BY 1, 2", 'name', ['blogname', 'home', 'siteurl', '_site_transient_update_plugins', '_transient_feed', 'theme_mods_twentytwentyfour']],
    'ordinal zero is rejected' => ['SELECT option_name AS name FROM wp_options ORDER BY 0', null, InvalidArgumentException::class],
    'ordinal out of range is rejected' => ['SELECT option_name AS name FROM wp_options ORDER BY 2', null, InvalidArgumentException::class],
    'ordinal wildcard target is rejected for bounded planner' => ['SELECT * FROM wp_options ORDER BY 1', null, InvalidArgumentException::class],
];

$tests = [];

foreach ($cases as $name => [$sql, $selectedColumn, $expected]) {
    $tests['sqlite select sql ordinal order next19 ' . $name] = static function (TestRunner $t) use ($column, $tables, $sql, $selectedColumn, $expected): void {
        if (is_string($expected) && class_exists($expected)) {
            $t->throws($expected, static fn () => SQLiteSelectSql::execute($sql, $tables));

            return;
        }

        $t->same($expected, $column($sql, (string) $selectedColumn));
    };
}

return $tests;
