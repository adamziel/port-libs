<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => '_transient_feed', 'option_value' => 'stale', 'autoload' => 'no'],
];

$metadataRows = [
    ['option_name' => 'siteurl', 'kind' => 'url', 'priority' => 10],
    ['option_name' => 'home', 'kind' => 'url', 'priority' => 20],
    ['option_name' => 'blogname', 'kind' => 'label', 'priority' => 30],
    ['option_name' => 'blogdescription', 'kind' => 'label', 'priority' => 40],
    ['option_name' => 'active_plugins', 'kind' => 'plugin', 'priority' => 50],
];

$tables = [
    'wp_options' => $currentOptions,
    'wp_option_meta' => $metadataRows,
];

$select = static fn (string $sql, string $column, array $parameters = []): array => array_column(SQLiteSelectSql::execute($sql, $tables, $parameters), $column);
$rows = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);

$cases = [
    'renames inline import tuple columns' => ["SELECT name FROM (VALUES ('siteurl', 'https://new.example', 'yes'), ('blogname', 'New Site', 'yes')) AS staged(name, value, flag) ORDER BY name", 'name', ['blogname', 'siteurl']],
    'supports as keyword before values alias list' => ["SELECT flag FROM (VALUES ('siteurl', 'yes'), ('active_plugins', 'no')) AS staged(name, flag) ORDER BY name", 'flag', ['no', 'yes']],
    'filters renamed values source columns' => ["SELECT name FROM (VALUES ('siteurl', 'yes'), ('active_plugins', 'no'), ('blogdescription', 'yes')) AS staged(name, flag) WHERE flag = 'yes' ORDER BY name", 'name', ['blogdescription', 'siteurl']],
    'orders by renamed values source column' => ["SELECT name FROM (VALUES ('siteurl', 20), ('home', 10), ('blogname', 30)) AS staged(name, rank) ORDER BY rank", 'name', ['home', 'siteurl', 'blogname']],
    'orders by expression over renamed values column' => ["SELECT name FROM (VALUES ('aa', 2), ('b', 10), ('cccc', 1)) AS staged(name, rank) ORDER BY length(name), rank", 'name', ['b', 'aa', 'cccc']],
    'uses renamed values source in scalar projection' => ["SELECT name || ':' || flag AS label FROM (VALUES ('siteurl', 'yes'), ('active_plugins', 'no')) AS staged(name, flag) ORDER BY name", 'label', ['active_plugins:no', 'siteurl:yes']],
    'uses renamed values source in case projection' => ["SELECT CASE flag WHEN 'yes' THEN name ELSE 'manual' END AS label FROM (VALUES ('siteurl', 'yes'), ('active_plugins', 'no')) AS staged(name, flag) ORDER BY name", 'label', ['manual', 'siteurl']],
    'uses renamed values source in grouped count' => ["SELECT flag, count(name) AS total FROM (VALUES ('siteurl', 'yes'), ('home', 'yes'), ('active_plugins', 'no')) AS staged(name, flag) GROUP BY flag ORDER BY flag DESC", 'total', [2, 1]],
    'uses renamed values source in grouped aggregate projection' => ["SELECT flag, sum(rank) AS total FROM (VALUES ('siteurl', 'yes', 2), ('home', 'yes', 3), ('active_plugins', 'no', 5)) AS staged(name, flag, rank) GROUP BY flag ORDER BY total", 'total', [5, 5]],
    'uses renamed values source in distinct projection' => ["SELECT DISTINCT flag FROM (VALUES ('siteurl', 'yes'), ('home', 'yes'), ('active_plugins', 'no')) AS staged(name, flag) ORDER BY flag", 'flag', ['no', 'yes']],
    'uses renamed values source with current-row exists filter' => ["SELECT name FROM (VALUES ('siteurl', 'https://new.example'), ('blogname', 'New Site')) AS staged(name, value) WHERE EXISTS (SELECT 1 FROM wp_options WHERE option_name = name) ORDER BY name", 'name', ['blogname', 'siteurl']],
    'uses renamed values source to find insert candidates' => ["SELECT name FROM (VALUES ('siteurl', 'https://new.example'), ('blogdescription', 'Tagline')) AS staged(name, value) WHERE NOT EXISTS (SELECT 1 FROM wp_options WHERE option_name = name)", 'name', ['blogdescription']],
    'uses renamed values source to find retained current names' => ["SELECT option_name FROM wp_options WHERE option_name IN (SELECT name FROM (VALUES ('siteurl'), ('home'), ('blogname')) AS staged(name)) ORDER BY option_id", 'option_name', ['siteurl', 'home', 'blogname']],
    'uses renamed values source with unqualified alias references' => ["SELECT name FROM (VALUES ('siteurl', 1), ('home', 2)) AS staged(name, rank) WHERE rank = 2", 'name', ['home']],
    'uses renamed values source in metadata in predicate' => ["SELECT option_name FROM (VALUES ('siteurl', 1), ('missing', 2)) AS staged(option_name, rank) WHERE option_name IN ('siteurl', 'home') ORDER BY rank", 'option_name', ['siteurl']],
    'uses renamed values source in metadata not in predicate' => ["SELECT option_name FROM (VALUES ('siteurl', 1), ('missing', 2)) AS staged(option_name, rank) WHERE option_name NOT IN ('siteurl', 'home')", 'option_name', ['missing']],
    'uses renamed values source in cross join' => ["SELECT staged.name || ':' || marker.flag AS label FROM (VALUES ('siteurl')) AS staged(name) CROSS JOIN (VALUES ('import')) AS marker(flag)", 'label', ['siteurl:import']],
    'uses renamed values source as derived table body' => ["SELECT name FROM (SELECT name, rank FROM (VALUES ('siteurl', 2), ('home', 1)) AS staged(name, rank) ORDER BY rank) AS ordered", 'name', ['home', 'siteurl']],
    'uses renamed values source in cte body' => ["WITH staged(name, flag) AS (SELECT name, flag FROM (VALUES ('siteurl', 'yes'), ('active_plugins', 'no')) AS raw(name, flag)) SELECT name FROM staged WHERE flag = 'no'", 'name', ['active_plugins']],
    'uses renamed values source in union arm' => ["SELECT name FROM (VALUES ('siteurl')) AS staged(name) UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id = 2 ORDER BY name", 'name', ['home', 'siteurl']],
    'uses renamed values source in except arm' => ["SELECT name FROM (VALUES ('siteurl'), ('missing')) AS staged(name) EXCEPT SELECT option_name AS name FROM wp_options ORDER BY name", 'name', ['missing']],
    'uses renamed values source in intersect arm' => ["SELECT name FROM (VALUES ('siteurl'), ('missing')) AS staged(name) INTERSECT SELECT option_name AS name FROM wp_options ORDER BY name", 'name', ['siteurl']],
    'uses renamed values source with limit offset' => ["SELECT name FROM (VALUES ('siteurl', 1), ('home', 2), ('blogname', 3)) AS staged(name, rank) ORDER BY rank LIMIT 1 OFFSET 1", 'name', ['home']],
    'uses renamed values source with comma limit' => ["SELECT name FROM (VALUES ('siteurl', 1), ('home', 2), ('blogname', 3), ('active_plugins', 4)) AS staged(name, rank) ORDER BY rank LIMIT 2, 2", 'name', ['blogname', 'active_plugins']],
    'uses renamed values source with between predicate' => ["SELECT name FROM (VALUES ('siteurl', 1), ('home', 2), ('blogname', 3)) AS staged(name, rank) WHERE rank BETWEEN 2 AND 3 ORDER BY rank", 'name', ['home', 'blogname']],
    'uses renamed values source with in predicate' => ["SELECT name FROM (VALUES ('siteurl'), ('home'), ('blogname')) AS staged(name) WHERE name IN ('home', 'missing')", 'name', ['home']],
    'uses renamed values source with not in predicate' => ["SELECT name FROM (VALUES ('siteurl'), ('home'), ('blogname')) AS staged(name) WHERE name NOT IN ('home') ORDER BY name", 'name', ['blogname', 'siteurl']],
    'uses renamed values source with glob predicate' => ["SELECT name FROM (VALUES ('siteurl'), ('blogname'), ('blogdescription')) AS staged(name) WHERE name GLOB 'blog*' ORDER BY name", 'name', ['blogdescription', 'blogname']],
    'uses renamed values source with like predicate' => ["SELECT name FROM (VALUES ('siteurl'), ('_transient_feed'), ('_site_transient_update_plugins')) AS staged(name) WHERE name LIKE '\\_%' ESCAPE '\\' ORDER BY name", 'name', ['_site_transient_update_plugins', '_transient_feed']],
    'uses renamed values source with null tests' => ["SELECT name FROM (VALUES ('siteurl', 'yes'), ('orphaned', NULL)) AS staged(name, flag) WHERE flag IS NULL", 'name', ['orphaned']],
    'uses renamed values source with is not distinct' => ["SELECT name FROM (VALUES ('siteurl', NULL), ('home', 'yes')) AS staged(name, flag) WHERE flag IS NOT DISTINCT FROM NULL", 'name', ['siteurl']],
    'uses renamed values source with collated ordering' => ["SELECT name FROM (VALUES ('Beta'), ('alpha'), ('gamma')) AS staged(name) ORDER BY name COLLATE NOCASE", 'name', ['alpha', 'Beta', 'gamma']],
    'uses renamed values source with null ordering' => ["SELECT name FROM (VALUES ('siteurl', NULL), ('home', 2), ('blogname', 1)) AS staged(name, rank) ORDER BY rank NULLS LAST", 'name', ['blogname', 'home', 'siteurl']],
    'uses renamed values source with scalar function projection' => ["SELECT upper(name) AS upper_name FROM (VALUES ('siteurl'), ('home')) AS staged(name) ORDER BY name", 'upper_name', ['HOME', 'SITEURL']],
    'uses renamed values source with cast projection' => ["SELECT CAST(rank AS TEXT) AS text_rank FROM (VALUES ('siteurl', 7)) AS staged(name, rank)", 'text_rank', ['7']],
    'uses renamed values source with arithmetic projection' => ["SELECT rank + 100 AS next_rank FROM (VALUES ('siteurl', 7), ('home', 8)) AS staged(name, rank) ORDER BY rank", 'next_rank', [107, 108]],
    'uses renamed values source with scalar subquery projection' => ["SELECT name, (SELECT kind FROM wp_option_meta WHERE option_name = name) AS kind FROM (VALUES ('siteurl'), ('blogname')) AS staged(name) ORDER BY name", 'kind', ['label', 'url']],
    'uses renamed values source with exists subquery' => ["SELECT name FROM (VALUES ('siteurl'), ('missing')) AS staged(name) WHERE EXISTS (SELECT 1 FROM wp_options WHERE option_name = name) ORDER BY name", 'name', ['siteurl']],
    'uses renamed values source with not exists subquery' => ["SELECT name FROM (VALUES ('siteurl'), ('missing')) AS staged(name) WHERE NOT EXISTS (SELECT 1 FROM wp_options WHERE option_name = name) ORDER BY name", 'name', ['missing']],
    'uses renamed values source with window ranking' => ["SELECT name FROM (SELECT name, row_number() OVER (ORDER BY rank) AS rn FROM (VALUES ('siteurl', 2), ('home', 1)) AS staged(name, rank)) AS ranked WHERE rn = 1", 'name', ['home']],
    'uses renamed values source with aggregate output order' => ["SELECT flag, max(rank) AS max_rank FROM (VALUES ('siteurl', 'yes', 2), ('home', 'yes', 1), ('active_plugins', 'no', 5)) AS staged(name, flag, rank) GROUP BY flag ORDER BY max_rank DESC", 'flag', ['no', 'yes']],
    'uses renamed values source in import update candidates' => ["SELECT name FROM (VALUES ('siteurl', 'https://new.example'), ('home', 'https://old.example'), ('blogname', 'New Site')) AS staged(name, value) WHERE EXISTS (SELECT 1 FROM wp_options WHERE option_name = name AND option_value IS NOT value) ORDER BY name", 'name', ['blogname', 'siteurl']],
    'uses renamed values source in import insert candidates' => ["SELECT name FROM (VALUES ('siteurl', 'https://new.example'), ('blogdescription', 'New Tagline')) AS staged(name, value) WHERE NOT EXISTS (SELECT 1 FROM wp_options WHERE option_name = name)", 'name', ['blogdescription']],
    'uses renamed values source in import retained candidates' => ["SELECT option_name FROM wp_options WHERE option_name IN (SELECT name FROM (VALUES ('siteurl'), ('home'), ('blogname'), ('active_plugins')) AS staged(name)) ORDER BY option_id DESC LIMIT 1", 'option_name', ['active_plugins']],
    'uses renamed values source to rank import operations' => ["SELECT op FROM (VALUES ('update', 1), ('insert', 2), ('delete', 3)) AS plan(op, rank) ORDER BY rank", 'op', ['update', 'insert', 'delete']],
    'uses renamed values source to summarize import operations' => ["SELECT op, count(name) AS total FROM (VALUES ('update', 'siteurl'), ('update', 'blogname'), ('insert', 'blogdescription')) AS events(op, name) GROUP BY op ORDER BY op", 'total', [1, 2]],
    'uses renamed values source with boolean parameter binding' => ["SELECT name FROM (VALUES (:name, :autoload)) AS staged(name, flag) WHERE flag = 1", 'name', ['siteurl']],
    'uses renamed values source with positional parameter binding' => ["SELECT name FROM (VALUES (?1, ?2), (?3, ?4)) AS staged(name, rank) ORDER BY rank", 'name', ['home', 'siteurl']],
    'uses renamed values source with no as keyword alias' => ["SELECT name FROM (VALUES ('siteurl'), ('home')) staged(name) ORDER BY name", 'name', ['home', 'siteurl']],
];

$tests = [];
foreach ($cases as $name => [$sql, $column, $expected]) {
    $tests['sqlite select sql values alias current next44 ' . $name] = static function (TestRunner $t) use ($select, $sql, $column, $expected): void {
        $parameters = str_contains($sql, ':name')
            ? ['name' => 'siteurl', 'autoload' => true]
            : (str_contains($sql, '?1') ? [1 => 'siteurl', 2 => 2, 3 => 'home', 4 => 1] : []);
        $t->same($expected, $select($sql, $column, $parameters));
    };
}

$tests['sqlite select sql values alias current next44 returns renamed row dictionaries'] = static function (TestRunner $t) use ($rows): void {
    $t->same(
        [['name' => 'siteurl', 'value' => 'https://new.example', 'flag' => 'yes']],
        $rows("SELECT name, value, flag FROM (VALUES ('siteurl', 'https://new.example', 'yes')) AS staged(name, value, flag)")
    );
};

$tests['sqlite select sql values alias current next44 rejects mismatched column list'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT name FROM (VALUES ('siteurl', 'yes')) AS staged(name)", $tables));
};

$tests['sqlite select sql values alias current next44 rejects empty column list'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT name FROM (VALUES ('siteurl')) AS staged()", $tables));
};

$tests['sqlite select sql values alias current next44 rejects invalid column alias'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT name FROM (VALUES ('siteurl')) AS staged(1bad)", $tables));
};

$tests['sqlite select sql values alias current next44 rejects malformed alias tail'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT name FROM (VALUES ('siteurl')) AS staged(name) trailing", $tables));
};

return $tests;
