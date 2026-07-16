<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$cases = [
    'default column names' => [
        'WITH seed AS (VALUES (1, \'siteurl\'), (2, \'home\')) SELECT column1, column2 FROM seed ORDER BY column1',
        [],
        [['column1' => 1, 'column2' => 'siteurl'], ['column1' => 2, 'column2' => 'home']],
    ],
    'explicit column names' => [
        'WITH seed(id, name) AS (VALUES (2, \'home\'), (1, \'siteurl\')) SELECT id, name FROM seed ORDER BY id',
        [],
        [['id' => 1, 'name' => 'siteurl'], ['id' => 2, 'name' => 'home']],
    ],
    'literal storage classes' => [
        'WITH seed(i, r, t, n) AS (VALUES (7, 2.5, \'cache\', NULL)) SELECT i, r, t, n FROM seed',
        [],
        [['i' => 7, 'r' => 2.5, 't' => 'cache', 'n' => null]],
    ],
    'negative integer literal' => [
        'WITH seed(v) AS (VALUES (-3), (4)) SELECT v FROM seed ORDER BY v',
        [],
        [['v' => -3], ['v' => 4]],
    ],
    'quoted apostrophe literal' => [
        'WITH seed(label) AS (VALUES (\'canary\'\'s\'), (\'cache\')) SELECT label FROM seed ORDER BY label',
        [],
        [['label' => 'cache'], ['label' => "canary's"]],
    ],
    'where equality' => [
        'WITH seed(name, autoload) AS (VALUES (\'siteurl\', \'yes\'), (\'transient\', \'no\')) SELECT name FROM seed WHERE autoload = \'yes\'',
        [],
        [['name' => 'siteurl']],
    ],
    'where not equality' => [
        'WITH seed(name, autoload) AS (VALUES (\'siteurl\', \'yes\'), (\'transient\', \'no\')) SELECT name FROM seed WHERE autoload != \'yes\'',
        [],
        [['name' => 'transient']],
    ],
    'where greater than' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'blogname\', 9), (\'tiny\', 3)) SELECT name, bytes FROM seed WHERE bytes > 9 ORDER BY bytes',
        [],
        [['name' => 'siteurl', 'bytes' => 24]],
    ],
    'where between' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'blogname\', 9), (\'tiny\', 3)) SELECT name, bytes FROM seed WHERE bytes BETWEEN 4 AND 24 ORDER BY bytes',
        [],
        [['name' => 'blogname', 'bytes' => 9], ['name' => 'siteurl', 'bytes' => 24]],
    ],
    'where not between' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'blogname\', 9), (\'tiny\', 3)) SELECT name FROM seed WHERE bytes NOT BETWEEN 4 AND 24',
        [],
        [['name' => 'tiny']],
    ],
    'where in list' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\'), (\'blogname\')) SELECT name FROM seed WHERE name IN (\'home\', \'blogname\') ORDER BY name',
        [],
        [['name' => 'blogname'], ['name' => 'home']],
    ],
    'where not in list' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\'), (\'blogname\')) SELECT name FROM seed WHERE name NOT IN (\'home\', \'blogname\')',
        [],
        [['name' => 'siteurl']],
    ],
    'where is null' => [
        'WITH seed(name, autoload) AS (VALUES (\'orphan\', NULL), (\'siteurl\', \'yes\')) SELECT name FROM seed WHERE autoload IS NULL',
        [],
        [['name' => 'orphan']],
    ],
    'where is not null' => [
        'WITH seed(name, autoload) AS (VALUES (\'orphan\', NULL), (\'siteurl\', \'yes\')) SELECT name FROM seed WHERE autoload IS NOT NULL',
        [],
        [['name' => 'siteurl']],
    ],
    'like predicate' => [
        'WITH seed(name) AS (VALUES (\'_transient_feed\'), (\'siteurl\'), (\'_site_transient_update_plugins\')) SELECT name FROM seed WHERE name LIKE \'%transient%\' ORDER BY name',
        [],
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed']],
    ],
    'glob predicate' => [
        'WITH seed(name) AS (VALUES (\'cache_key\'), (\'Cache_Key\'), (\'siteurl\')) SELECT name FROM seed WHERE name GLOB \'cache_*\'',
        [],
        [['name' => 'cache_key']],
    ],
    'and predicate' => [
        'WITH seed(name, autoload, bytes) AS (VALUES (\'siteurl\', \'yes\', 24), (\'tiny\', \'yes\', 3), (\'feed\', \'no\', 12)) SELECT name FROM seed WHERE autoload = \'yes\' AND bytes > 10',
        [],
        [['name' => 'siteurl']],
    ],
    'or predicate' => [
        'WITH seed(name, autoload, bytes) AS (VALUES (\'siteurl\', \'yes\', 24), (\'tiny\', \'yes\', 3), (\'feed\', \'no\', 12)) SELECT name FROM seed WHERE autoload = \'no\' OR bytes < 4 ORDER BY name',
        [],
        [['name' => 'feed'], ['name' => 'tiny']],
    ],
    'distinct values' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\'), (\'siteurl\')) SELECT DISTINCT name FROM seed ORDER BY name',
        [],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'order descending' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'blogname\', 9), (\'tiny\', 3)) SELECT name, bytes FROM seed ORDER BY bytes DESC',
        [],
        [['name' => 'siteurl', 'bytes' => 24], ['name' => 'blogname', 'bytes' => 9], ['name' => 'tiny', 'bytes' => 3]],
    ],
    'limit offset' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'blogname\', 9), (\'tiny\', 3)) SELECT name, bytes FROM seed ORDER BY bytes DESC LIMIT 1 OFFSET 1',
        [],
        [['name' => 'blogname', 'bytes' => 9]],
    ],
    'comma limit' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'blogname\', 9), (\'tiny\', 3)) SELECT name, bytes FROM seed ORDER BY bytes DESC LIMIT 1, 2',
        [],
        [['name' => 'blogname', 'bytes' => 9], ['name' => 'tiny', 'bytes' => 3]],
    ],
    'scalar projection' => [
        'WITH seed(name) AS (VALUES (\'SiteURL\')) SELECT lower(name) AS lowered, length(name) AS bytes FROM seed',
        [],
        [['lowered' => 'siteurl', 'bytes' => 7]],
    ],
    'binary expression projection' => [
        'WITH seed(name, bytes) AS (VALUES (\'siteurl\', 24), (\'feed\', 12)) SELECT name, bytes + 1 AS adjusted FROM seed ORDER BY name',
        [],
        [['name' => 'feed', 'adjusted' => 13], ['name' => 'siteurl', 'adjusted' => 25]],
    ],
    'group count' => [
        'WITH seed(autoload, name) AS (VALUES (\'yes\', \'siteurl\'), (\'yes\', \'home\'), (\'no\', \'feed\')) SELECT autoload, count(name) AS total FROM seed GROUP BY autoload ORDER BY autoload',
        [],
        [['autoload' => 'no', 'total' => 1], ['autoload' => 'yes', 'total' => 2]],
    ],
    'group sum having' => [
        'WITH seed(autoload, bytes) AS (VALUES (\'yes\', 24), (\'yes\', 9), (\'no\', 12)) SELECT autoload, sum(bytes) AS total FROM seed GROUP BY autoload HAVING sum(bytes) > 12 ORDER BY total DESC',
        [],
        [['autoload' => 'yes', 'total' => 33]],
    ],
    'group concat' => [
        'WITH seed(autoload, name) AS (VALUES (\'yes\', \'siteurl\'), (\'yes\', \'home\'), (\'no\', \'feed\')) SELECT autoload, group_concat(name) AS names FROM seed GROUP BY autoload ORDER BY autoload',
        [],
        [['autoload' => 'no', 'names' => 'feed'], ['autoload' => 'yes', 'names' => 'siteurl|home']],
    ],
    'join values cte to table' => [
        'WITH wanted(id, weight) AS (VALUES (1, 10), (3, 30)) SELECT wp_options.option_name AS name, wanted.weight AS weight FROM wp_options JOIN wanted ON wp_options.option_id = wanted.id ORDER BY weight DESC',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['name' => 'blogname', 'weight' => 30], ['name' => 'siteurl', 'weight' => 10]],
    ],
    'left join values cte' => [
        'WITH wanted(id, label) AS (VALUES (1, \'core\'), (4, \'missing\')) SELECT wanted.id AS id, wp_options.option_name AS name FROM wanted LEFT JOIN wp_options ON wanted.id = wp_options.option_id ORDER BY id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home']]],
        [['id' => 1, 'name' => 'siteurl'], ['id' => 4, 'name' => null]],
    ],
    'multiple values ctes' => [
        'WITH ids(id) AS (VALUES (1), (3)), labels(id, label) AS (VALUES (1, \'core\'), (3, \'theme\')) SELECT ids.id AS id, labels.label AS label FROM ids JOIN labels ON ids.id = labels.id ORDER BY id',
        [],
        [['id' => 1, 'label' => 'core'], ['id' => 3, 'label' => 'theme']],
    ],
    'values cte feeds select cte' => [
        'WITH raw(id, name) AS (VALUES (1, \'siteurl\'), (2, \'home\')), filtered AS (SELECT id, name FROM raw WHERE id = 2) SELECT name FROM filtered',
        [],
        [['name' => 'home']],
    ],
    'select cte feeds values join' => [
        'WITH wanted(id) AS (VALUES (2), (3)), picked AS (SELECT option_id, option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted)) SELECT option_id, option_name FROM picked ORDER BY option_id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']],
    ],
    'in subquery from values cte' => [
        'WITH wanted(id) AS (VALUES (1), (3)) SELECT option_id, option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) ORDER BY option_id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 3, 'option_name' => 'blogname']],
    ],
    'exists subquery from values cte' => [
        'WITH wanted(id) AS (VALUES (2), (4)) SELECT option_name FROM wp_options WHERE EXISTS (SELECT id FROM wanted WHERE id = option_id)',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['option_name' => 'home']],
    ],
    'not exists subquery from values cte' => [
        'WITH wanted(id) AS (VALUES (2), (4)) SELECT option_id, option_name FROM wp_options WHERE NOT EXISTS (SELECT id FROM wanted WHERE id = option_id) ORDER BY option_id',
        ['wp_options' => [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 2, 'option_name' => 'home'], ['option_id' => 3, 'option_name' => 'blogname']]],
        [['option_id' => 1, 'option_name' => 'siteurl'], ['option_id' => 3, 'option_name' => 'blogname']],
    ],
    'bind positional values' => [
        'WITH seed(id, name) AS (VALUES (?1, ?2), (?3, ?4)) SELECT id, name FROM seed WHERE id > ?5 ORDER BY id',
        [],
        [['id' => 2, 'name' => 'home']],
        [1 => 1, 2 => 'siteurl', 3 => 2, 4 => 'home', 5 => 1],
    ],
    'bind named values' => [
        'WITH seed(id, name) AS (VALUES (:one, :site), (:two, :home)) SELECT id, name FROM seed WHERE id IN (:one, :two) ORDER BY id DESC',
        [],
        [['id' => 2, 'name' => 'home'], ['id' => 1, 'name' => 'siteurl']],
        [':one' => 1, ':site' => 'siteurl', ':two' => 2, ':home' => 'home'],
    ],
    'compound select from values cte' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\')) SELECT name FROM seed UNION SELECT option_name AS name FROM wp_options ORDER BY name',
        ['wp_options' => [['option_name' => 'home'], ['option_name' => 'blogname']]],
        [['name' => 'blogname'], ['name' => 'home'], ['name' => 'siteurl']],
    ],
    'union all values cte' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\')) SELECT name FROM seed UNION ALL SELECT name FROM seed ORDER BY name',
        [],
        [['name' => 'home'], ['name' => 'home'], ['name' => 'siteurl'], ['name' => 'siteurl']],
    ],
    'intersect values cte' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\')) SELECT name FROM seed INTERSECT SELECT option_name AS name FROM wp_options',
        ['wp_options' => [['option_name' => 'home'], ['option_name' => 'blogname']]],
        [['name' => 'home']],
    ],
    'except values cte' => [
        'WITH seed(name) AS (VALUES (\'siteurl\'), (\'home\')) SELECT name FROM seed EXCEPT SELECT option_name AS name FROM wp_options',
        ['wp_options' => [['option_name' => 'home'], ['option_name' => 'blogname']]],
        [['name' => 'siteurl']],
    ],
    'blob literal value' => [
        'WITH seed(payload) AS (VALUES (X\'4142\')) SELECT quote(payload) AS quoted FROM seed',
        [],
        [['quoted' => "X'4142'"]],
    ],
    'zeroblob value expression' => [
        'WITH seed(payload) AS (VALUES (zeroblob(3))) SELECT length(payload) AS bytes, quote(payload) AS quoted FROM seed',
        [],
        [['bytes' => 3, 'quoted' => "X'000000'"]],
    ],
    'json scalar from values' => [
        'WITH seed(doc) AS (VALUES (\'{"enabled":true,"name":"cache"}\')) SELECT json_extract(doc, \'$.name\') AS name FROM seed',
        [],
        [['name' => 'cache']],
    ],
    'json filter from values' => [
        'WITH seed(doc) AS (VALUES (\'{"enabled":true,"name":"cache"}\'), (\'{"enabled":false,"name":"seo"}\')) SELECT json_extract(doc, \'$.name\') AS name FROM seed WHERE json_extract(doc, \'$.enabled\') = 1',
        [],
        [['name' => 'cache']],
    ],
    'qualified values projection' => [
        'WITH seed(id, name) AS (VALUES (1, \'siteurl\')) SELECT id AS option_id, name AS option_name FROM seed',
        [],
        [['option_id' => 1, 'option_name' => 'siteurl']],
    ],
    'values cte with all rows filtered' => [
        'WITH seed(id, name) AS (VALUES (1, \'siteurl\'), (2, \'home\')) SELECT name FROM seed WHERE id > 5',
        [],
        [],
    ],
    'values cte final order expression' => [
        'WITH seed(name) AS (VALUES (\'aaa\'), (\'b\'), (\'cc\')) SELECT name FROM seed ORDER BY length(name), name',
        [],
        [['name' => 'b'], ['name' => 'cc'], ['name' => 'aaa']],
    ],
    'values cte null not in behavior' => [
        'WITH seed(v) AS (VALUES (1), (NULL)) SELECT v FROM seed WHERE v NOT IN (2, NULL)',
        [],
        [],
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $tables, $expected] = $case;
    $parameters = $case[3] ?? [];
    $tests['values clause CTE corpus ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected, $parameters): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables, $parameters));
    };
}

$errorCases = [
    'empty column list' => 'WITH seed() AS (VALUES (1)) SELECT column1 FROM seed',
    'width mismatch in values rows' => 'WITH seed(a, b) AS (VALUES (1, 2), (3)) SELECT a FROM seed',
    'column list mismatch' => 'WITH seed(a) AS (VALUES (1, 2)) SELECT a FROM seed',
    'missing row parentheses' => 'WITH seed AS (VALUES 1, 2) SELECT column1 FROM seed',
    'unsupported expression in values' => 'WITH seed(v) AS (VALUES ((SELECT 1))) SELECT v FROM seed',
];

foreach ($errorCases as $name => $sql) {
    $tests['values clause CTE corpus rejects ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, []));
    };
}

return $tests;
