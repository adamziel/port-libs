<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'scope' => 'core'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'scope' => 'core'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9, 'scope' => 'theme'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'scope' => 'cache'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110, 'scope' => 'cache'],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null, 'scope' => 'orphan'],
];

$meta = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'meta_value' => 'short', 'weight' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30],
    ['meta_option_id' => 5, 'meta_key' => 'ttl', 'meta_value' => 'long', 'weight' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'kind', 'meta_value' => 'update', 'weight' => 50],
];

$visibility = [
    ['option_id' => 1, 'site_id' => 1, 'visible' => 1],
    ['option_id' => 1, 'site_id' => 2, 'visible' => 1],
    ['option_id' => 2, 'site_id' => 1, 'visible' => 1],
    ['option_id' => 3, 'site_id' => 1, 'visible' => 1],
    ['option_id' => 4, 'site_id' => 1, 'visible' => 0],
    ['option_id' => 5, 'site_id' => 1, 'visible' => 0],
    ['option_id' => 5, 'site_id' => 2, 'visible' => 0],
];

$tables = ['wp_options' => $options, 'option_meta' => $meta, 'site_visibility' => $visibility];

$cases = [
    'scalar sum grouped by correlated option id' => [
        "SELECT option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 30) AS meta_weight FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'meta_weight' => 30], ['name' => 'home', 'meta_weight' => 30], ['name' => 'blogname', 'meta_weight' => null], ['name' => '_transient_feed', 'meta_weight' => 70], ['name' => '_site_transient_update_plugins', 'meta_weight' => 120], ['name' => 'orphaned', 'meta_weight' => null]],
    ],
    'scalar count value grouped by correlated option id' => [
        "SELECT option_name AS name, (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING count(weight) >= 2) AS meta_rows FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'meta_rows' => 2], ['name' => 'home', 'meta_rows' => 2], ['name' => 'blogname', 'meta_rows' => null], ['name' => '_transient_feed', 'meta_rows' => 2], ['name' => '_site_transient_update_plugins', 'meta_rows' => 3], ['name' => 'orphaned', 'meta_rows' => null]],
    ],
    'scalar avg grouped having compares correlated row bytes' => [
        "SELECT option_name AS name, (SELECT avg(weight) AS average FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING avg(weight) < bytes) AS average_weight FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'average_weight' => 15.0], ['name' => 'home', 'average_weight' => 15.0], ['name' => 'blogname', 'average_weight' => null], ['name' => '_transient_feed', 'average_weight' => null], ['name' => '_site_transient_update_plugins', 'average_weight' => 40.0], ['name' => 'orphaned', 'average_weight' => null]],
    ],
    'scalar min grouped having uses outer autoload predicate' => [
        "SELECT option_name AS name, (SELECT min(weight) AS smallest FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload = 'no' AND min(weight) >= 30) AS smallest_private_weight FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'smallest_private_weight' => null], ['name' => 'home', 'smallest_private_weight' => null], ['name' => 'blogname', 'smallest_private_weight' => null], ['name' => '_transient_feed', 'smallest_private_weight' => 30], ['name' => '_site_transient_update_plugins', 'smallest_private_weight' => 30], ['name' => 'orphaned', 'smallest_private_weight' => null]],
    ],
    'scalar max grouped having uses outer string predicate' => [
        "SELECT option_name AS name, (SELECT max(weight) AS largest FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING option_name GLOB '_*' AND max(weight) >= 40) AS largest_private_weight FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'largest_private_weight' => null], ['name' => 'home', 'largest_private_weight' => null], ['name' => 'blogname', 'largest_private_weight' => null], ['name' => '_transient_feed', 'largest_private_weight' => 40], ['name' => '_site_transient_update_plugins', 'largest_private_weight' => 50], ['name' => 'orphaned', 'largest_private_weight' => null]],
    ],
    'scalar total grouped having returns zero-compatible null miss' => [
        "SELECT option_name AS name, (SELECT total(weight) AS total_weight FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING total(weight) > 0.0) AS total_weight FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'total_weight' => 30.0], ['name' => 'home', 'total_weight' => 30.0], ['name' => 'blogname', 'total_weight' => 10.0], ['name' => '_transient_feed', 'total_weight' => 70.0], ['name' => '_site_transient_update_plugins', 'total_weight' => 120.0], ['name' => 'orphaned', 'total_weight' => null]],
    ],
    'exists grouped having filters aggregate threshold' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 70) ORDER BY option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'not exists grouped having filters missing high aggregate' => [
        "SELECT option_name AS name FROM wp_options WHERE NOT EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 70) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => 'orphaned']],
    ],
    'exists grouped having compares aggregate to outer bytes' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) > bytes) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'not exists grouped having compares aggregate to outer bytes' => [
        "SELECT option_name AS name FROM wp_options WHERE NOT EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) > bytes) ORDER BY option_id",
        [['name' => 'orphaned']],
    ],
    'in subquery grouped having returns correlated group key' => [
        "SELECT option_name AS name FROM wp_options WHERE (bytes + 10) IN (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 70) ORDER BY option_id",
        [['name' => '_site_transient_update_plugins']],
    ],
    'not in subquery grouped having returns correlated group key' => [
        "SELECT option_name AS name FROM wp_options WHERE (bytes + 10) NOT IN (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 70) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => 'orphaned']],
    ],
    'scalar grouped having inside where predicate' => [
        "SELECT option_name AS name FROM wp_options WHERE (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 30) >= 30 ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'scalar grouped having inside order expression' => [
        "SELECT option_name AS name FROM wp_options WHERE option_id <= 5 ORDER BY (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) > 0) DESC, option_id",
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed'], ['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname']],
    ],
    'joined outer row feeds grouped scalar subquery' => [
        "SELECT option_name AS name, visible, (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING count(weight) >= 2) AS meta_rows FROM wp_options JOIN site_visibility ON wp_options.option_id = site_visibility.option_id WHERE site_id = 1 ORDER BY wp_options.option_id",
        [['name' => 'siteurl', 'visible' => 1, 'meta_rows' => 2], ['name' => 'home', 'visible' => 1, 'meta_rows' => 2], ['name' => 'blogname', 'visible' => 1, 'meta_rows' => null], ['name' => '_transient_feed', 'visible' => 0, 'meta_rows' => 2], ['name' => '_site_transient_update_plugins', 'visible' => 0, 'meta_rows' => 3]],
    ],
    'joined outer row feeds grouped exists subquery' => [
        "SELECT option_name AS name FROM wp_options JOIN site_visibility ON wp_options.option_id = site_visibility.option_id WHERE site_id = 1 AND EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING count(weight) = 2) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed']],
    ],
    'outer left join null row can miss grouped subquery' => [
        "SELECT wp_options.option_name AS name FROM wp_options LEFT JOIN site_visibility ON wp_options.option_id = site_visibility.option_id WHERE site_visibility.option_id IS NULL AND NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING count(weight) >= 1)",
        [['name' => 'orphaned']],
    ],
    'grouped subquery cte source remains correlated' => [
        "WITH meta_copy(meta_option_id, weight) AS (SELECT meta_option_id, weight FROM option_meta) SELECT option_name AS name, (SELECT sum(weight) AS total FROM meta_copy WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 30) AS total FROM wp_options ORDER BY option_id",
        [['name' => 'siteurl', 'total' => 30], ['name' => 'home', 'total' => 30], ['name' => 'blogname', 'total' => null], ['name' => '_transient_feed', 'total' => 70], ['name' => '_site_transient_update_plugins', 'total' => 120], ['name' => 'orphaned', 'total' => null]],
    ],
    'grouped subquery values cte source remains correlated' => [
        "WITH thresholds(meta_option_id, minimum) AS (VALUES (1, 30), (2, 35), (4, 60), (5, 100)) SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(minimum) AS total FROM thresholds WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(minimum) <= bytes) ORDER BY option_id",
        [['name' => '_site_transient_update_plugins']],
    ],
    'correlated grouped subquery with between having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) BETWEEN 25 AND 75) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed']],
    ],
    'correlated grouped subquery with not between having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) NOT BETWEEN 25 AND 75) ORDER BY option_id",
        [['name' => 'blogname'], ['name' => '_site_transient_update_plugins']],
    ],
    'correlated grouped subquery with in having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING count(weight) IN (1, 3)) ORDER BY option_id",
        [['name' => 'blogname'], ['name' => '_site_transient_update_plugins']],
    ],
    'correlated grouped subquery with not in having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING count(weight) NOT IN (1, 3)) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed']],
    ],
    'correlated grouped subquery with like outer having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING option_name GLOB '_*') ORDER BY option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'correlated grouped subquery with null outer having' => [
        "SELECT option_name AS name FROM wp_options WHERE NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload IS NOT NULL) ORDER BY option_id",
        [['name' => 'orphaned']],
    ],
    'correlated grouped subquery with arithmetic having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) + count(weight) > bytes) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'correlated grouped subquery with division having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) / count(weight) = 15) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home']],
    ],
    'correlated grouped subquery with modulo having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) % count(weight) = 0) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'correlated grouped subquery with group concat having' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT group_concat(meta_value) AS packed FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING group_concat(meta_value) = 'public|url') ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home']],
    ],
    'correlated grouped subquery empty source returns null scalar' => [
        "SELECT option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'missing' GROUP BY meta_option_id HAVING sum(weight) > 0) AS missing_total FROM wp_options ORDER BY option_id LIMIT 2",
        [['name' => 'siteurl', 'missing_total' => null], ['name' => 'home', 'missing_total' => null]],
    ],
];

$tests = [];
foreach ($cases as $name => [$sql, $expected]) {
    $tests['select correlated aggregate group having current next15 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expected): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

$tests['select correlated aggregate group having current next15 plan preserves grouped subquery'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 30) AS total FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->same(['from', 'select', 'orderBy'], array_keys($plan));
    $t->same('subquery', $plan['select'][1]['type']);
    $rows = SQLiteSelectSql::execute(
        "SELECT option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING sum(weight) >= 30) AS total FROM wp_options ORDER BY option_id LIMIT 1",
        $tables,
    );
    $t->same([['name' => 'siteurl', 'total' => 30]], $rows);
};

return $tests;
