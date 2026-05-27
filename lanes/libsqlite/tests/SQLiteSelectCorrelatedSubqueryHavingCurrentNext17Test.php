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
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10, 'bytes' => 3],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20, 'bytes' => 4],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10, 'bytes' => 8],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'meta_value' => 'url', 'weight' => 20, 'bytes' => 9],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'public', 'weight' => 10, 'bytes' => 5],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30, 'bytes' => 11],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'meta_value' => 'short', 'weight' => 40, 'bytes' => 12],
    ['meta_option_id' => 5, 'meta_key' => 'scope', 'meta_value' => 'private', 'weight' => 30, 'bytes' => 14],
    ['meta_option_id' => 5, 'meta_key' => 'ttl', 'meta_value' => 'long', 'weight' => 40, 'bytes' => 15],
    ['meta_option_id' => 5, 'meta_key' => 'kind', 'meta_value' => 'update', 'weight' => 50, 'bytes' => 16],
];

$sites = [
    ['option_id' => 1, 'blog_id' => 1, 'enabled' => 1, 'minimum_weight' => 25],
    ['option_id' => 1, 'blog_id' => 2, 'enabled' => 1, 'minimum_weight' => 40],
    ['option_id' => 2, 'blog_id' => 1, 'enabled' => 1, 'minimum_weight' => 25],
    ['option_id' => 3, 'blog_id' => 1, 'enabled' => 1, 'minimum_weight' => 12],
    ['option_id' => 4, 'blog_id' => 1, 'enabled' => 0, 'minimum_weight' => 60],
    ['option_id' => 5, 'blog_id' => 1, 'enabled' => 0, 'minimum_weight' => 90],
    ['option_id' => 5, 'blog_id' => 2, 'enabled' => 0, 'minimum_weight' => 130],
];

$tables = ['wp_options' => $options, 'option_meta' => $meta, 'site_rules' => $sites];

$cases = [
    'scalar current bytes threshold' => [
        "SELECT wp_options.option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= wp_options.bytes) AS total FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY wp_options.option_id",
        [['name' => 'siteurl', 'total' => 30], ['name' => 'home', 'total' => 30], ['name' => 'blogname', 'total' => 10], ['name' => '_transient_feed', 'total' => 70], ['name' => '_site_transient_update_plugins', 'total' => 120]],
    ],
    'scalar current bytes strict miss' => [
        "SELECT wp_options.option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) > wp_options.bytes + 100) AS total FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY wp_options.option_id",
        [['name' => 'siteurl', 'total' => null], ['name' => 'home', 'total' => null], ['name' => 'blogname', 'total' => null], ['name' => '_transient_feed', 'total' => null], ['name' => '_site_transient_update_plugins', 'total' => null]],
    ],
    'exists current autoload no' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload = 'no' AND count(weight) >= 2) ORDER BY option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'not exists current autoload yes' => [
        "SELECT option_name AS name FROM wp_options WHERE NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload = 'yes' AND count(weight) >= 2) ORDER BY option_id",
        [['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins'], ['name' => 'orphaned']],
    ],
    'exists current null autoload' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload IS NULL) ORDER BY option_id",
        [],
    ],
    'not exists current null autoload' => [
        "SELECT option_name AS name FROM wp_options WHERE NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload IS NULL) ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins'], ['name' => 'orphaned']],
    ],
    'exists current scope cache' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING scope = 'cache' AND sum(weight) >= 60) ORDER BY option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'exists current option glob cache prefix' => [
        "SELECT option_name AS name FROM wp_options WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING option_name GLOB '_*' AND sum(weight) >= 60) ORDER BY option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'in current grouped sum matches bytes plus six' => [
        "SELECT option_name AS name FROM wp_options WHERE bytes + 6 IN (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload = 'yes') ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home']],
    ],
    'not in current grouped sum matches bytes plus six' => [
        "SELECT option_name AS name FROM wp_options WHERE bytes + 6 NOT IN (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING autoload = 'yes') ORDER BY option_id",
        [['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins'], ['name' => 'orphaned']],
    ],
    'where scalar current scope cache' => [
        "SELECT option_name AS name FROM wp_options WHERE (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING scope = 'cache') >= 70 ORDER BY option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'where scalar current scope core' => [
        "SELECT option_name AS name FROM wp_options WHERE (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = option_id GROUP BY meta_option_id HAVING scope = 'core') = 30 ORDER BY option_id",
        [['name' => 'siteurl'], ['name' => 'home']],
    ],
    'order scalar current threshold' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= wp_options.bytes) DESC, wp_options.option_name",
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed'], ['name' => 'home'], ['name' => 'siteurl'], ['name' => 'blogname']],
    ],
    'joined current qualified minimum weight' => [
        "SELECT wp_options.option_name AS name, site_rules.blog_id AS blog FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) ORDER BY wp_options.option_id, site_rules.blog_id",
        [['name' => 'siteurl', 'blog' => 1], ['name' => 'home', 'blog' => 1], ['name' => '_transient_feed', 'blog' => 1], ['name' => '_site_transient_update_plugins', 'blog' => 1]],
    ],
    'joined current qualified minimum miss' => [
        "SELECT wp_options.option_name AS name, site_rules.blog_id AS blog FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE NOT EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) ORDER BY wp_options.option_id, site_rules.blog_id",
        [['name' => 'siteurl', 'blog' => 2], ['name' => 'blogname', 'blog' => 1], ['name' => '_site_transient_update_plugins', 'blog' => 2]],
    ],
    'joined current enabled private rule' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING site_rules.enabled = 0 AND count(weight) >= 2) ORDER BY wp_options.option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'joined current enabled public miss' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING site_rules.enabled = 1 AND count(weight) >= 2) ORDER BY wp_options.option_id",
        [['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'joined scalar returns current rule total' => [
        "SELECT wp_options.option_name AS name, site_rules.blog_id AS blog, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) AS total FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id ORDER BY wp_options.option_id, site_rules.blog_id",
        [['name' => 'siteurl', 'blog' => 1, 'total' => 30], ['name' => 'siteurl', 'blog' => 2, 'total' => null], ['name' => 'home', 'blog' => 1, 'total' => 30], ['name' => 'blogname', 'blog' => 1, 'total' => null], ['name' => '_transient_feed', 'blog' => 1, 'total' => 70], ['name' => '_site_transient_update_plugins', 'blog' => 1, 'total' => 120], ['name' => '_site_transient_update_plugins', 'blog' => 2, 'total' => null]],
    ],
    'joined left current null extension misses' => [
        "SELECT wp_options.option_name AS name FROM wp_options LEFT JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.option_id IS NULL AND NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING site_rules.enabled = 1) ORDER BY wp_options.option_id",
        [['name' => 'orphaned']],
    ],
    'current qualified outer bytes wins duplicate inner bytes' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) > wp_options.bytes) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => 'blogname'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current qualified rule threshold wins duplicate inner bytes' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) > site_rules.minimum_weight) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current unqualified duplicate uses inner bytes' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT max(bytes) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING max(bytes) = bytes) ORDER BY wp_options.option_id",
        [['name' => 'blogname'], ['name' => '_transient_feed']],
    ],
    'current qualified outer scope with inner scope name absent' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING wp_options.scope IN ('core', 'cache')) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current qualified outer scope not in theme' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND NOT EXISTS (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING wp_options.scope IN ('core', 'cache')) ORDER BY wp_options.option_id",
        [['name' => 'blogname']],
    ],
    'current qualified outer expression arithmetic' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) - site_rules.minimum_weight >= 10) ORDER BY wp_options.option_id",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current qualified outer expression modulo' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING (sum(weight) - site_rules.minimum_weight) % 5 = 0) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current qualified outer between' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) BETWEEN site_rules.minimum_weight AND site_rules.minimum_weight + 35) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current qualified outer not between' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) NOT BETWEEN site_rules.minimum_weight AND site_rules.minimum_weight + 35) ORDER BY wp_options.option_id",
        [['name' => 'blogname']],
    ],
    'current qualified outer in scalar projection' => [
        "SELECT wp_options.option_name AS name, (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING site_rules.enabled IN (0, 1)) AS meta_rows FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY wp_options.option_id",
        [['name' => 'siteurl', 'meta_rows' => 2], ['name' => 'home', 'meta_rows' => 2], ['name' => 'blogname', 'meta_rows' => 1], ['name' => '_transient_feed', 'meta_rows' => 2], ['name' => '_site_transient_update_plugins', 'meta_rows' => 3]],
    ],
    'current qualified outer not in scalar projection' => [
        "SELECT wp_options.option_name AS name, (SELECT count(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING site_rules.enabled NOT IN (0, 1)) AS meta_rows FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY wp_options.option_id",
        [['name' => 'siteurl', 'meta_rows' => null], ['name' => 'home', 'meta_rows' => null], ['name' => 'blogname', 'meta_rows' => null], ['name' => '_transient_feed', 'meta_rows' => null], ['name' => '_site_transient_update_plugins', 'meta_rows' => null]],
    ],
    'current qualified outer order expression' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) DESC, wp_options.option_name",
        [['name' => '_site_transient_update_plugins'], ['name' => '_transient_feed'], ['name' => 'home'], ['name' => 'siteurl'], ['name' => 'blogname']],
    ],
    'current qualified outer limit keeps correlation' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) ORDER BY wp_options.option_id LIMIT 2",
        [['name' => 'siteurl'], ['name' => 'home']],
    ],
    'current qualified outer offset keeps correlation' => [
        "SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE EXISTS (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) ORDER BY wp_options.option_id LIMIT 2 OFFSET 2",
        [['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
    'current qualified outer cte subquery source' => [
        "WITH meta_copy(meta_option_id, weight) AS (SELECT meta_option_id, weight FROM option_meta) SELECT wp_options.option_name AS name FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 AND EXISTS (SELECT sum(weight) AS total FROM meta_copy WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) ORDER BY wp_options.option_id",
        [['name' => 'siteurl'], ['name' => 'home'], ['name' => '_transient_feed'], ['name' => '_site_transient_update_plugins']],
    ],
];

$tests = [];
foreach ($cases as $name => [$sql, $expected]) {
    $tests['select correlated subquery having current next17 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expected): void {
        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

$tests['select correlated subquery having current next17 plan keeps scalar grouped subquery'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT wp_options.option_name AS name, (SELECT sum(weight) AS total FROM option_meta WHERE meta_option_id = wp_options.option_id GROUP BY meta_option_id HAVING sum(weight) >= site_rules.minimum_weight) AS total FROM wp_options JOIN site_rules ON wp_options.option_id = site_rules.option_id WHERE site_rules.blog_id = 1 ORDER BY wp_options.option_id",
        $tables,
    );

    $t->same(['from', 'select', 'joins', 'where', 'orderBy'], array_keys($plan));
    $t->same('subquery', $plan['select'][1]['type']);
    $t->same('total', $plan['select'][1]['alias']);
};

return $tests;
