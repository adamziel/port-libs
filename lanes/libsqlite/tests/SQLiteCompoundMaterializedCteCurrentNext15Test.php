<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
];

$cases = [
    'materialized values cte feeds union all' => [
        "WITH picked(name) AS MATERIALIZED (VALUES ('siteurl'), ('home')) SELECT name FROM picked UNION ALL SELECT option_name AS name FROM wp_options WHERE autoload = 'no' ORDER BY name",
        ['wp_options' => $options],
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed'], ['name' => 'home'], ['name' => 'siteurl']],
    ],
    'not materialized values cte feeds union distinct' => [
        "WITH picked(name) AS NOT MATERIALIZED (VALUES ('siteurl'), ('home'), ('home')) SELECT name FROM picked UNION SELECT option_name AS name FROM wp_options WHERE option_id <= 2 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'materialized select cte feeds intersect' => [
        "WITH picked(name) AS MATERIALIZED (SELECT option_name AS name FROM wp_options WHERE autoload = 'yes') SELECT name FROM picked INTERSECT SELECT option_name AS name FROM wp_options WHERE bytes >= 24 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'not materialized select cte feeds except' => [
        "WITH picked(name) AS NOT MATERIALIZED (SELECT option_name AS name FROM wp_options WHERE autoload = 'yes') SELECT name FROM picked EXCEPT SELECT option_name AS name FROM wp_options WHERE bytes < 20 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'materialized compound cte can be selected' => [
        "WITH picked(name) AS MATERIALIZED (SELECT option_name AS name FROM wp_options WHERE option_id = 1 UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id = 2) SELECT name FROM picked ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'not materialized compound cte can feed final union' => [
        "WITH picked(name) AS NOT MATERIALIZED (SELECT option_name AS name FROM wp_options WHERE option_id = 1 UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id = 2) SELECT name FROM picked UNION SELECT option_name AS name FROM wp_options WHERE option_id = 3 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'blogname'], ['name' => 'home'], ['name' => 'siteurl']],
    ],
    'materialized cte can feed both compound arms' => [
        "WITH picked(name) AS MATERIALIZED (VALUES ('siteurl'), ('home')) SELECT name FROM picked WHERE name GLOB 's*' UNION ALL SELECT name FROM picked WHERE name GLOB 'h*' ORDER BY name",
        [],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'not materialized cte keeps duplicate rows for union all' => [
        "WITH picked(name) AS NOT MATERIALIZED (VALUES ('home'), ('home')) SELECT name FROM picked UNION ALL SELECT name FROM picked ORDER BY name",
        [],
        [['name' => 'home'], ['name' => 'home'], ['name' => 'home'], ['name' => 'home']],
    ],
    'materialized cte duplicate rows collapse through union' => [
        "WITH picked(name) AS MATERIALIZED (VALUES ('home'), ('home')) SELECT name FROM picked UNION SELECT name FROM picked ORDER BY name",
        [],
        [['name' => 'home']],
    ],
    'materialized cte participates in compound limit' => [
        "WITH picked(name) AS MATERIALIZED (VALUES ('siteurl'), ('home'), ('blogname')) SELECT name FROM picked UNION ALL SELECT option_name AS name FROM wp_options WHERE autoload = 'no' ORDER BY name LIMIT 3",
        ['wp_options' => $options],
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed'], ['name' => 'blogname']],
    ],
    'not materialized cte participates in compound offset' => [
        "WITH picked(name) AS NOT MATERIALIZED (VALUES ('siteurl'), ('home'), ('blogname')) SELECT name FROM picked UNION ALL SELECT option_name AS name FROM wp_options WHERE autoload = 'no' ORDER BY name LIMIT 2 OFFSET 2",
        ['wp_options' => $options],
        [['name' => 'blogname'], ['name' => 'home']],
    ],
    'materialized cte follows ordinary prior cte' => [
        "WITH raw(name) AS (VALUES ('siteurl'), ('home')), picked(name) AS MATERIALIZED (SELECT name FROM raw WHERE name = 'home') SELECT name FROM picked UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id = 3 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'blogname'], ['name' => 'home']],
    ],
    'not materialized cte follows materialized cte' => [
        "WITH raw(name) AS MATERIALIZED (VALUES ('siteurl'), ('home')), picked(name) AS NOT MATERIALIZED (SELECT name FROM raw WHERE name GLOB 's*') SELECT name FROM picked UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id = 2 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'materialized cte with explicit columns renames compound body' => [
        "WITH picked(label) AS MATERIALIZED (SELECT option_name AS name FROM wp_options WHERE option_id = 1 UNION ALL SELECT option_name AS name FROM wp_options WHERE option_id = 2) SELECT label FROM picked ORDER BY label",
        ['wp_options' => $options],
        [['label' => 'home'], ['label' => 'siteurl']],
    ],
    'not materialized cte with explicit columns renames values body' => [
        "WITH picked(label) AS NOT MATERIALIZED (VALUES ('siteurl'), ('home')) SELECT label FROM picked UNION SELECT option_name AS label FROM wp_options WHERE option_id = 3 ORDER BY label",
        ['wp_options' => $options],
        [['label' => 'blogname'], ['label' => 'home'], ['label' => 'siteurl']],
    ],
    'materialized cte can feed compound scalar projections' => [
        "WITH picked(name, bytes) AS MATERIALIZED (VALUES ('siteurl', 24), ('home', 24)) SELECT name || ':' || bytes AS label FROM picked UNION ALL SELECT option_name || ':' || bytes AS label FROM wp_options WHERE option_id = 3 ORDER BY label",
        ['wp_options' => $options],
        [['label' => 'blogname:9'], ['label' => 'home:24'], ['label' => 'siteurl:24']],
    ],
    'not materialized cte can feed compound where predicates' => [
        "WITH picked(name, bytes) AS NOT MATERIALIZED (VALUES ('siteurl', 24), ('home', 24), ('tiny', 3)) SELECT name FROM picked WHERE bytes > 10 UNION ALL SELECT option_name AS name FROM wp_options WHERE bytes < 10 ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'blogname'], ['name' => 'home'], ['name' => 'siteurl']],
    ],
    'materialized cte can feed compound in predicate' => [
        "WITH wanted(id) AS MATERIALIZED (VALUES (1), (3)) SELECT option_name AS name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) UNION SELECT 'manual' AS name ORDER BY name",
        ['wp_options' => $options],
        [['name' => 'blogname'], ['name' => 'manual'], ['name' => 'siteurl']],
    ],
    'not materialized cte can feed compound exists predicate' => [
        "WITH wanted(id) AS NOT MATERIALIZED (VALUES (2), (5)) SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT id FROM wanted WHERE id = option_id) UNION ALL SELECT 'manual' AS name ORDER BY name",
        ['wp_options' => $options],
        [['name' => '_site_transient_update_plugins'], ['name' => 'home'], ['name' => 'manual']],
    ],
    'materialized hint accepts mixed case keywords' => [
        "WITH picked(name) AS Materialized (VALUES ('siteurl')), next(name) AS Not Materialized (VALUES ('home')) SELECT name FROM picked UNION ALL SELECT name FROM next ORDER BY name",
        [],
        [['name' => 'home'], ['name' => 'siteurl']],
    ],
    'materialized recursive cte feeds compound current source' => [
        "WITH RECURSIVE seq(id) AS MATERIALIZED (VALUES (1) UNION ALL SELECT id + 1 FROM seq WHERE id < 3) SELECT id FROM seq UNION SELECT option_id AS id FROM wp_options WHERE option_id = 5 ORDER BY id",
        ['wp_options' => $options],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 5]],
    ],
    'not materialized recursive cte feeds compound current source' => [
        "WITH RECURSIVE seq(id) AS NOT MATERIALIZED (VALUES (2) UNION ALL SELECT id + 1 FROM seq WHERE id < 4) SELECT id FROM seq UNION ALL SELECT option_id AS id FROM wp_options WHERE option_id = 1 ORDER BY id",
        ['wp_options' => $options],
        [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
    ],
    'materialized compound cte participates in scalar subquery limit' => [
        "WITH picked(name) AS MATERIALIZED (SELECT option_name AS name FROM wp_options WHERE autoload = 'yes' UNION ALL SELECT option_name AS name FROM wp_options WHERE autoload = 'no') SELECT name FROM picked ORDER BY name LIMIT (SELECT 2 FROM picked WHERE name GLOB '_*')",
        ['wp_options' => $options],
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed']],
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $tables, $expected] = $case;
    $tests['compound materialized CTE current next15 ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

$errorCases = [
    'materialized hint still requires body' => "WITH picked AS MATERIALIZED SELECT 1 SELECT 1",
    'not materialized hint still requires body' => "WITH picked AS NOT MATERIALIZED SELECT 1 SELECT 1",
    'materialized body still must be select or values' => "WITH picked AS MATERIALIZED (DELETE FROM wp_options) SELECT 1",
];

foreach ($errorCases as $name => $sql) {
    $tests['compound materialized CTE current next15 rejects ' . $name] = static function (TestRunner $t) use ($sql, $options): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, ['wp_options' => $options]));
    };
}

return $tests;
