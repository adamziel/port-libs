<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => '_transient_feed', 'option_value' => 'stale', 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'option_value' => 'old rules', 'autoload' => 'no'],
];

$stagedRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'blogdescription', 'option_value' => 'Just another port', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'fresh', 'autoload' => 'no'],
];

$metadataRows = [
    ['option_name' => 'siteurl', 'kind' => 'url', 'priority' => 10],
    ['option_name' => 'home', 'kind' => 'url', 'priority' => 20],
    ['option_name' => 'blogname', 'kind' => 'label', 'priority' => 30],
    ['option_name' => 'active_plugins', 'kind' => 'plugin', 'priority' => 40],
    ['option_name' => 'blogdescription', 'kind' => 'label', 'priority' => 50],
    ['option_name' => '_site_transient_update_plugins', 'kind' => 'transient', 'priority' => 60],
];

$tables = [
    'wp_options' => $currentRows,
    'wp_options_stage' => $stagedRows,
    'wp_option_meta' => $metadataRows,
];

$select = static fn (string $sql, string $column): array => array_column(SQLiteSelectSql::execute($sql, $tables), $column);
$rows = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);

$cases = [
    'derived table filters autoloaded staged rows' => ["SELECT option_name FROM (SELECT option_name, autoload FROM wp_options_stage WHERE autoload = 'yes') AS staged ORDER BY option_name", 'option_name', ['blogdescription', 'blogname', 'home', 'siteurl']],
    'derived table preserves projected aliases' => ["SELECT name FROM (SELECT option_name AS name, option_value AS value FROM wp_options_stage WHERE autoload = 'yes') AS staged ORDER BY name", 'name', ['blogdescription', 'blogname', 'home', 'siteurl']],
    'derived table alias qualifies projected columns in join' => ["SELECT s.name AS name FROM (SELECT option_name AS name, option_value FROM wp_options_stage) AS s JOIN wp_option_meta AS m ON s.name = m.option_name WHERE m.kind = 'url' ORDER BY s.name", 'name', ['home', 'siteurl']],
    'derived table column list renames projected columns' => ["SELECT imported_name FROM (SELECT option_name, option_value FROM wp_options_stage) AS staged(imported_name, imported_value) WHERE imported_name LIKE 'blog%' ORDER BY imported_name", 'imported_name', ['blogdescription', 'blogname']],
    'derived table column list participates in join predicates' => ["SELECT staged.imported_name AS imported_name FROM (SELECT option_name, autoload FROM wp_options_stage) staged(imported_name, imported_autoload) JOIN wp_option_meta AS m ON staged.imported_name = m.option_name WHERE imported_autoload = 'no' ORDER BY m.priority", 'imported_name', ['active_plugins', '_site_transient_update_plugins']],
    'nested derived tables keep inner aliases' => ["SELECT name FROM (SELECT name, autoload FROM (SELECT option_name AS name, autoload FROM wp_options_stage) AS inner_stage WHERE autoload = 'yes') AS outer_stage ORDER BY name", 'name', ['blogdescription', 'blogname', 'home', 'siteurl']],
    'derived table can aggregate staged autoload groups' => ["SELECT flag, total FROM (SELECT autoload AS flag, count(option_id) AS total FROM wp_options_stage GROUP BY autoload) AS counts ORDER BY flag DESC", 'total', [4, 2]],
    'derived aggregate rows can be filtered by outer where' => ["SELECT flag FROM (SELECT autoload AS flag, count(option_id) AS total FROM wp_options_stage GROUP BY autoload) AS counts WHERE total >= 4", 'flag', ['yes']],
    'derived aggregate rows can be ordered by expression' => ["SELECT flag FROM (SELECT autoload AS flag, count(option_id) AS total FROM wp_options_stage GROUP BY autoload) AS counts ORDER BY total DESC", 'flag', ['yes', 'no']],
    'derived table can use inner order and outer limit' => ["SELECT option_name FROM (SELECT option_name, option_id FROM wp_options_stage ORDER BY option_id DESC) AS staged LIMIT 3", 'option_name', ['_site_transient_update_plugins', 'blogdescription', 'active_plugins']],
    'derived table can use inner limit before outer order' => ["SELECT option_name FROM (SELECT option_name, option_id FROM wp_options_stage ORDER BY option_id DESC LIMIT 3) AS staged ORDER BY option_name", 'option_name', ['_site_transient_update_plugins', 'active_plugins', 'blogdescription']],
    'derived table can use comma limit in inner select' => ["SELECT option_name FROM (SELECT option_name, option_id FROM wp_options_stage ORDER BY option_id LIMIT 2, 3) AS staged ORDER BY option_id", 'option_name', ['blogname', 'active_plugins', 'blogdescription']],
    'derived table can select distinct current autoload values' => ["SELECT flag FROM (SELECT DISTINCT autoload AS flag FROM wp_options_stage) AS flags ORDER BY flag DESC", 'flag', ['yes', 'no']],
    'derived table can wrap values source' => ["SELECT name FROM (SELECT column1 AS name, column2 AS weight FROM (VALUES ('plugin', 3), ('site', 1), ('theme', 2)) AS raw ORDER BY weight) AS ordered", 'name', ['site', 'theme', 'plugin']],
    'derived table can wrap cte materialization' => ["SELECT name FROM (WITH picked(name, weight) AS (VALUES ('siteurl', 1), ('home', 2)) SELECT name, weight FROM picked) AS picked ORDER BY weight DESC", 'name', ['home', 'siteurl']],
    'derived table can feed left join null extension' => ["SELECT staged.name AS name FROM (SELECT option_name AS name FROM wp_options_stage WHERE autoload = 'yes') AS staged LEFT JOIN wp_options AS current ON staged.name = current.option_name WHERE current.option_id IS NULL ORDER BY staged.name", 'name', ['blogdescription']],
    'derived table can feed inner join update candidates' => ["SELECT staged.name AS name FROM (SELECT option_name AS name, option_value AS value FROM wp_options_stage) AS staged JOIN wp_options AS current ON staged.name = current.option_name WHERE staged.value IS NOT current.option_value ORDER BY current.option_id", 'name', ['siteurl', 'home', 'blogname', 'active_plugins']],
    'derived table supports scalar expression projection' => ["SELECT label FROM (SELECT option_name || ':' || autoload AS label, option_id FROM wp_options_stage) AS labels ORDER BY option_id LIMIT 2", 'label', ['siteurl:yes', 'home:yes']],
    'derived table supports case expression projection' => ["SELECT bucket FROM (SELECT CASE autoload WHEN 'yes' THEN 'autoloaded' ELSE 'manual' END AS bucket, option_id FROM wp_options_stage) AS buckets ORDER BY option_id LIMIT 2", 'bucket', ['autoloaded', 'autoloaded']],
    'derived table supports cast expression projection' => ["SELECT text_id FROM (SELECT CAST(option_id AS TEXT) AS text_id FROM wp_options_stage WHERE option_id IN (1, 2)) AS ids ORDER BY text_id DESC", 'text_id', ['2', '1']],
    'derived table supports arithmetic expression projection' => ["SELECT next_id FROM (SELECT option_id + 100 AS next_id FROM wp_options_stage WHERE option_id IN (1, 2)) AS ids ORDER BY next_id", 'next_id', [101, 102]],
    'derived table supports outer predicate on expression alias' => ["SELECT name FROM (SELECT option_name AS name, length(option_value) AS bytes FROM wp_options_stage) AS sized WHERE bytes > 12 ORDER BY name", 'name', ['active_plugins', 'blogdescription', 'home', 'siteurl']],
    'derived table supports outer order by expression over alias' => ["SELECT name FROM (SELECT option_name AS name, option_value AS value FROM wp_options_stage) AS staged ORDER BY length(value), name LIMIT 3", 'name', ['_site_transient_update_plugins', 'blogname', 'blogdescription']],
    'derived table supports wildcard expansion' => ["SELECT name, flag FROM (SELECT option_name AS name, autoload AS flag FROM wp_options_stage WHERE option_id = 1) AS staged", 'flag', ['yes']],
    'derived table keeps null values from left joins' => ["SELECT missing FROM (SELECT staged.option_name AS name, current.option_id AS missing FROM wp_options_stage AS staged LEFT JOIN wp_options AS current ON staged.option_name = current.option_name WHERE current.option_id IS NULL) AS missing_rows", 'missing', [null, null]],
    'derived table can be joined to another derived table' => ["SELECT staged.name AS name FROM (SELECT option_name AS name FROM wp_options_stage WHERE autoload = 'yes') AS staged JOIN (SELECT option_name AS name FROM wp_option_meta WHERE kind = 'label') AS labels ON staged.name = labels.name ORDER BY staged.name", 'name', ['blogdescription', 'blogname']],
    'derived table can be cross joined to constants' => ["SELECT staged.name || ':' || flags.flag AS label FROM (SELECT option_name AS name FROM wp_options_stage WHERE option_id = 1) AS staged CROSS JOIN (SELECT 'import' AS flag) AS flags", 'label', ['siteurl:import']],
    'derived table can use no alias fallback' => ["SELECT option_name FROM (SELECT option_name FROM wp_options_stage WHERE option_id = 1)", 'option_name', ['siteurl']],
    'derived table preserves blob-like text payload' => ["SELECT value FROM (SELECT option_value AS value FROM wp_options_stage WHERE option_name = 'active_plugins') AS staged", 'value', ['a:1:{i:0;s:19:"plugin/plugin.php";}']],
    'derived table supports not in outer predicate' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options_stage) AS staged WHERE name NOT IN ('siteurl', 'home', 'blogname', 'active_plugins') ORDER BY name", 'name', ['_site_transient_update_plugins', 'blogdescription']],
    'derived table supports between outer predicate' => ["SELECT name FROM (SELECT option_name AS name, option_id AS id FROM wp_options_stage) AS staged WHERE id BETWEEN 2 AND 4 ORDER BY id", 'name', ['home', 'blogname', 'active_plugins']],
    'derived table supports glob outer predicate' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options_stage) AS staged WHERE name GLOB 'blog*' ORDER BY name", 'name', ['blogdescription', 'blogname']],
    'derived table supports collated outer order' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options_stage) AS staged ORDER BY name COLLATE NOCASE DESC LIMIT 2", 'name', ['siteurl', 'home']],
    'derived table supports grouped having inside derived source' => ["SELECT flag FROM (SELECT autoload AS flag, count(option_id) AS total FROM wp_options_stage GROUP BY autoload HAVING count(option_id) >= 2) AS grouped ORDER BY flag", 'flag', ['no', 'yes']],
    'derived table supports grouped having outside derived source' => ["SELECT flag FROM (SELECT autoload AS flag, count(option_id) AS total FROM wp_options_stage GROUP BY autoload) AS grouped WHERE total = 2", 'flag', ['no']],
    'derived table can wrap union results' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options WHERE option_id = 1 UNION ALL SELECT option_name AS name FROM wp_options_stage WHERE option_id = 7) AS imported ORDER BY name", 'name', ['blogdescription', 'siteurl']],
    'derived table can wrap union distinct results' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options WHERE option_id IN (1, 2) UNION SELECT option_name AS name FROM wp_options_stage WHERE option_id IN (1, 7)) AS imported ORDER BY name", 'name', ['blogdescription', 'home', 'siteurl']],
    'derived table can wrap intersect results' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options INTERSECT SELECT option_name AS name FROM wp_options_stage) AS imported ORDER BY name", 'name', ['active_plugins', 'blogname', 'home', 'siteurl']],
    'derived table can wrap except results' => ["SELECT name FROM (SELECT option_name AS name FROM wp_options_stage EXCEPT SELECT option_name AS name FROM wp_options) AS imported ORDER BY name", 'name', ['_site_transient_update_plugins', 'blogdescription']],
    'derived table can be grouped by outer query' => ["SELECT flag, count(name) AS total FROM (SELECT option_name AS name, autoload AS flag FROM wp_options_stage) AS staged GROUP BY flag ORDER BY flag DESC", 'total', [4, 2]],
    'derived table can be grouped after join' => ["SELECT kind, count(name) AS total FROM (SELECT s.option_name AS name, m.kind AS kind FROM wp_options_stage AS s JOIN wp_option_meta AS m ON s.option_name = m.option_name) AS joined GROUP BY kind ORDER BY kind", 'total', [2, 1, 1, 2]],
    'derived table can apply outer distinct' => ["SELECT DISTINCT flag FROM (SELECT autoload AS flag FROM wp_options_stage) AS staged ORDER BY flag", 'flag', ['no', 'yes']],
    'derived table can project qualified wildcard in join' => ["SELECT staged.name AS name FROM (SELECT option_name AS name FROM wp_options_stage) AS staged JOIN wp_option_meta AS m ON staged.name = m.option_name WHERE m.priority >= 50 ORDER BY m.priority", 'name', ['blogdescription', '_site_transient_update_plugins']],
    'derived table supports scalar subquery in outer projection' => ["SELECT name, (SELECT kind FROM wp_option_meta WHERE option_name = name) AS kind FROM (SELECT option_name AS name FROM wp_options_stage WHERE option_id = 1) AS staged", 'kind', ['url']],
    'derived table supports scalar subquery in inner projection' => ["SELECT name, kind FROM (SELECT option_name AS name, (SELECT kind FROM wp_option_meta WHERE option_name = 'siteurl') AS kind FROM wp_options_stage WHERE option_id IN (1, 4)) AS staged ORDER BY name", 'kind', ['url', 'url']],
    'derived table supports null scalar subquery result' => ["SELECT kind FROM (SELECT option_name AS name, (SELECT kind FROM wp_option_meta WHERE option_name = 'missing') AS kind FROM wp_options_stage WHERE option_id = 1) AS staged", 'kind', [null]],
    'derived table supports outer limit offset' => ["SELECT name FROM (SELECT option_name AS name, option_id AS id FROM wp_options_stage ORDER BY id) AS staged LIMIT 2 OFFSET 2", 'name', ['blogname', 'active_plugins']],
    'derived table supports outer comma limit' => ["SELECT name FROM (SELECT option_name AS name, option_id AS id FROM wp_options_stage ORDER BY id) AS staged LIMIT 3, 2", 'name', ['active_plugins', 'blogdescription']],
    'derived table supports inner and outer where composition' => ["SELECT name FROM (SELECT option_name AS name, autoload AS flag FROM wp_options_stage WHERE option_id >= 3) AS staged WHERE flag = 'yes' ORDER BY name", 'name', ['blogdescription', 'blogname']],
    'derived table supports imported current diff labels' => ["SELECT label FROM (SELECT staged.option_name || '=>' || current.option_value AS label, staged.option_id AS id FROM wp_options_stage AS staged JOIN wp_options AS current ON staged.option_name = current.option_name WHERE staged.option_value IS NOT current.option_value) AS diffs ORDER BY id LIMIT 2", 'label', ['siteurl=>https://old.example', 'home=>https://old.example']],
    'derived table supports staged insert candidates' => ["SELECT name FROM (SELECT staged.option_name AS name, current.option_id AS current_id FROM wp_options_stage AS staged LEFT JOIN wp_options AS current ON staged.option_name = current.option_name) AS candidates WHERE current_id IS NULL ORDER BY name", 'name', ['_site_transient_update_plugins', 'blogdescription']],
    'derived table supports current retain candidates' => ["SELECT name FROM (SELECT current.option_name AS name, staged.option_id AS staged_id FROM wp_options AS current LEFT JOIN wp_options_stage AS staged ON current.option_name = staged.option_name) AS candidates WHERE staged_id IS NULL ORDER BY name", 'name', ['_transient_feed', 'rewrite_rules']],
    'derived table supports import statement ordering' => ["SELECT op FROM (SELECT 'update' AS op, 1 AS rank UNION ALL SELECT 'insert' AS op, 2 AS rank UNION ALL SELECT 'delete' AS op, 3 AS rank) AS statements ORDER BY rank", 'op', ['update', 'insert', 'delete']],
    'derived table supports row count summary for import operations' => ["SELECT op, total FROM (SELECT op, count(name) AS total FROM (SELECT 'update' AS op, staged.option_name AS name FROM wp_options_stage AS staged JOIN wp_options AS current ON staged.option_name = current.option_name UNION ALL SELECT 'insert' AS op, staged.option_name AS name FROM wp_options_stage AS staged LEFT JOIN wp_options AS current ON staged.option_name = current.option_name WHERE current.option_id IS NULL) AS events GROUP BY op) AS summary ORDER BY op", 'total', [2, 4]],
];

$tests = [];
foreach ($cases as $name => [$sql, $column, $expected]) {
    $tests['sqlite select sql derived table current next24 ' . $name] = static function (TestRunner $t) use ($select, $sql, $column, $expected): void {
        $t->same($expected, $select($sql, $column));
    };
}

$tests['sqlite select sql derived table current next24 returns multiple derived columns'] = static function (TestRunner $t) use ($rows): void {
    $t->same(
        [['name' => 'siteurl', 'flag' => 'yes']],
        $rows("SELECT name, flag FROM (SELECT option_name AS name, autoload AS flag FROM wp_options_stage WHERE option_id = 1) AS staged")
    );
};

$tests['sqlite select sql derived table current next24 rejects mismatched column list'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT x FROM (SELECT option_name, autoload FROM wp_options_stage) AS staged(x)", $tables));
};

$tests['sqlite select sql derived table current next24 rejects malformed alias tail'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options_stage) AS staged trailing", $tables));
};

$tests['sqlite select sql derived table current next24 rejects empty column list'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options_stage) AS staged()", $tables));
};

$tests['sqlite select sql derived table current next24 rejects invalid column alias'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options_stage) AS staged(1bad)", $tables));
};

$tests['sqlite select sql derived table current next24 rejects invalid derived alias'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options_stage) AS 1bad", $tables));
};

return $tests;
