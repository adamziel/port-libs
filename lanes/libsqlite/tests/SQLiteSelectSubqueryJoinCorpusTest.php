<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 16],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 8],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 120],
    ['option_id' => 5, 'option_name' => 'widget_recent', 'autoload' => 'no', 'bytes' => 44],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 64],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'scope', 'meta_value' => 'public', 'rank' => 10],
    ['meta_option_id' => 1, 'meta_key' => 'kind', 'meta_value' => 'url', 'rank' => 20],
    ['meta_option_id' => 2, 'meta_key' => 'scope', 'meta_value' => 'public', 'rank' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'kind', 'meta_value' => 'url', 'rank' => 20],
    ['meta_option_id' => 3, 'meta_key' => 'scope', 'meta_value' => 'public', 'rank' => 10],
    ['meta_option_id' => 4, 'meta_key' => 'scope', 'meta_value' => 'private', 'rank' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'ttl', 'meta_value' => 'short', 'rank' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'scope', 'meta_value' => 'admin', 'rank' => 30],
    ['meta_option_id' => 6, 'meta_key' => 'scope', 'meta_value' => 'public', 'rank' => 10],
    ['meta_option_id' => 6, 'meta_key' => 'kind', 'meta_value' => 'theme', 'rank' => 20],
];

$visibility = [
    ['site_id' => 1, 'option_id' => 1, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 2, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 3, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 4, 'visibility' => 'cron'],
    ['site_id' => 2, 'option_id' => 1, 'visibility' => 'network'],
    ['site_id' => 2, 'option_id' => 6, 'visibility' => 'theme'],
];

$tables = [
    'wp_options' => $options,
    'option_meta' => $metadata,
    'site_visibility' => $visibility,
];

$cases = [
    'inner join filters exists public scope' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_value = 'public') ORDER BY name",
        'name',
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'inner join filters not exists ttl' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE NOT EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'ttl') ORDER BY name",
        'name',
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'inner join filters in public metadata' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id IN (SELECT meta_option_id FROM option_meta WHERE meta_value = 'public') ORDER BY name",
        'name',
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'inner join filters not in private metadata' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_value = 'private') ORDER BY name",
        'name',
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'inner join scalar subquery projects scope' => [
        "SELECT o.option_name AS name, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') AS scope FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'scope',
        ['private', 'public', 'public', 'public', 'public', 'public'],
    ],
    'inner join scalar subquery projects kind nullable' => [
        "SELECT o.option_name AS name, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'kind') AS kind FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'kind',
        [null, null, 'url', 'url', 'url', 'theme'],
    ],
    'inner join scalar subquery concatenates joined visibility' => [
        "SELECT o.option_name || ':' || (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') || ':' || v.visibility AS label FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY label",
        'label',
        ['_transient_feed:private:cron', 'blogname:public:front', 'home:public:front', 'siteurl:public:front'],
    ],
    'inner join scalar subquery in predicate' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') = 'public' ORDER BY name",
        'name',
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'inner join scalar subquery in order by' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') DESC, name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl'],
    ],
    'inner join correlated in uses joined site id' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id IN (SELECT site_id FROM site_visibility WHERE option_id = o.option_id AND visibility = v.visibility) ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'left join preserves rows when exists matches' => [
        "SELECT o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'left join filters missing joined row through subquery' => [
        "SELECT o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE NOT EXISTS (SELECT site_id FROM site_visibility WHERE option_id = o.option_id) ORDER BY name",
        'name',
        ['widget_recent'],
    ],
    'left join scalar subquery sees null joined column' => [
        "SELECT o.option_name || ':' || ifnull(v.visibility, 'none') AS label FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id NOT IN (SELECT option_id FROM site_visibility WHERE site_id = 1) ORDER BY label",
        'label',
        ['theme_mods:theme', 'widget_recent:none'],
    ],
    'cross join exists correlates both sources' => [
        "SELECT o.option_name || ':' || v.visibility AS label FROM wp_options AS o CROSS JOIN site_visibility AS v WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_value = 'public') AND o.option_id = v.option_id ORDER BY label",
        'label',
        ['blogname:front', 'home:front', 'siteurl:front', 'siteurl:network', 'theme_mods:theme'],
    ],
    'cross join in subquery limits joined pairs' => [
        "SELECT o.option_name || ':' || v.site_id AS label FROM wp_options AS o CROSS JOIN site_visibility AS v WHERE o.option_id = v.option_id AND o.option_id IN (SELECT option_id FROM site_visibility WHERE site_id = v.site_id AND option_id = o.option_id) ORDER BY label",
        'label',
        ['_transient_feed:1', 'blogname:1', 'home:1', 'siteurl:1', 'siteurl:2', 'theme_mods:2'],
    ],
    'join projection scalar first row is sqlite scalar subquery value' => [
        "SELECT o.option_name AS name, (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND rank = 10) AS first_key FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY name",
        'first_key',
        [null, 'scope', 'scope', 'scope'],
    ],
    'join projection scalar respects subquery order' => [
        "SELECT o.option_name AS name, (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND rank >= 20 LIMIT 1) AS last_key FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY name",
        'last_key',
        ['scope', null, 'kind', 'kind'],
    ],
    'join subquery with limit returns first matching metadata' => [
        "SELECT o.option_name AS name, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope' LIMIT 1) AS first_value FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY name",
        'first_value',
        ['private', 'public', 'public', 'public'],
    ],
    'join subquery with limit offset returns second metadata' => [
        "SELECT o.option_name AS name, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id LIMIT 1 OFFSET 1) AS second_value FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY name",
        'second_value',
        ['short', null, 'url', 'url'],
    ],
    'join subquery with comma limit returns later metadata' => [
        "SELECT o.option_name AS name, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id LIMIT 1, 1) AS second_value FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY name",
        'second_value',
        ['short', null, 'url', 'url'],
    ],
    'join not in empty subquery preserves rows' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_key = 'missing') ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join in empty subquery removes rows' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id IN (SELECT meta_option_id FROM option_meta WHERE meta_key = 'missing') ORDER BY name",
        'name',
        [],
    ],
    'join exists empty subquery removes rows' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'missing') ORDER BY name",
        'name',
        [],
    ],
    'join not exists empty subquery preserves rows' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE NOT EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'missing') ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join scalar missing subquery projects null' => [
        "SELECT o.option_name AS name, (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'missing') AS missing_value FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'missing_value',
        [null, null, null, null, null, null],
    ],
    'join scalar null compares with is null' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'missing') IS NULL ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join scalar null compares with is not null' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'kind') IS NOT NULL ORDER BY name",
        'name',
        ['home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join scalar numeric comparison filters bytes' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.bytes > (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'kind') ORDER BY name",
        'name',
        ['theme_mods'],
    ],
    'join scalar numeric expression filters transient' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.bytes / 2 > (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'ttl') ORDER BY name",
        'name',
        ['_transient_feed'],
    ],
    'join subquery value participates in concatenation' => [
        "SELECT (SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') || ':' || o.option_name AS label FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY label",
        'label',
        ['private:_transient_feed', 'public:blogname', 'public:home', 'public:siteurl'],
    ],
    'join subquery value participates in addition' => [
        "SELECT o.option_name AS name, o.bytes + (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') AS weighted FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY name",
        'weighted',
        [150, 18, 26, 28],
    ],
    'join subquery value participates in modulo' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (o.bytes + (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope')) % 2 = 0 ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join exists under and predicate' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 AND EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_value = 'public') ORDER BY name",
        'name',
        ['blogname', 'home', 'siteurl'],
    ],
    'join exists under or predicate' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.visibility = 'cron' OR EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_value = 'theme') ORDER BY name",
        'name',
        ['_transient_feed', 'theme_mods'],
    ],
    'join not exists under or predicate' => [
        "SELECT o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.visibility = 'theme' OR NOT EXISTS (SELECT site_id FROM site_visibility WHERE option_id = o.option_id) ORDER BY name",
        'name',
        ['theme_mods', 'widget_recent'],
    ],
    'join subquery in between lower bound' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.bytes BETWEEN (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope') AND 70 ORDER BY name",
        'name',
        ['home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join subquery in between upper bound' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.bytes BETWEEN 1 AND (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'ttl') ORDER BY name",
        'name',
        [],
    ],
    'join subquery in not between upper bound' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.bytes NOT BETWEEN 1 AND (SELECT rank FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'ttl') ORDER BY name",
        'name',
        ['_transient_feed'],
    ],
    'join subquery in like pattern' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_name LIKE (SELECT meta_value FROM option_meta WHERE meta_option_id = 5 AND meta_key = 'scope') || '%' ORDER BY name",
        'name',
        [],
    ],
    'join subquery in glob pattern' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_name GLOB (SELECT 'theme*' FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'kind') ORDER BY name",
        'name',
        ['theme_mods'],
    ],
    'join subquery in not glob pattern' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_name NOT GLOB (SELECT 'theme*' FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'kind') ORDER BY name",
        'name',
        ['home', 'siteurl', 'siteurl'],
    ],
    'join scalar function around subquery value' => [
        "SELECT upper((SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'scope')) AS scope FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.site_id = 1 ORDER BY scope",
        'scope',
        ['PRIVATE', 'PUBLIC', 'PUBLIC', 'PUBLIC'],
    ],
    'join scalar ifnull around subquery value' => [
        "SELECT o.option_name AS name, ifnull((SELECT meta_value FROM option_meta WHERE meta_option_id = o.option_id AND meta_key = 'kind'), 'none') AS kind FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'kind',
        ['none', 'none', 'url', 'url', 'url', 'theme', 'none'],
    ],
    'join subquery references right table column in exists' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT site_id FROM site_visibility WHERE site_id = v.site_id AND option_id = o.option_id AND visibility = v.visibility) ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join subquery references right table column in scalar' => [
        "SELECT o.option_name AS name, (SELECT visibility FROM site_visibility WHERE site_id = v.site_id AND option_id = o.option_id) AS seen FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'seen',
        ['cron', 'front', 'front', 'front', 'network', 'theme'],
    ],
    'join subquery references right table column in in list' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE v.visibility IN (SELECT visibility FROM site_visibility WHERE option_id = o.option_id) ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'join subquery with outer value and order limit' => [
        "SELECT o.option_name AS name, (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND rank > v.site_id LIMIT 1) AS key_after_site FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'key_after_site',
        ['scope', 'scope', 'scope', 'scope', 'scope', 'scope'],
    ],
    'join subquery with outer value and order offset' => [
        "SELECT o.option_name AS name, (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND rank > v.site_id LIMIT 1 OFFSET 1) AS key_after_site FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id ORDER BY name",
        'key_after_site',
        ['ttl', null, 'kind', 'kind', 'kind', 'kind'],
    ],
    'left join subquery detects only admin metadata' => [
        "SELECT o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id AND meta_value = 'admin') ORDER BY name",
        'name',
        ['widget_recent'],
    ],
    'left join not in subquery excludes mapped site options' => [
        "SELECT o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id NOT IN (SELECT option_id FROM site_visibility WHERE site_id = 1) ORDER BY name",
        'name',
        ['theme_mods', 'widget_recent'],
    ],
    'left join in subquery includes mapped site options with duplicates' => [
        "SELECT o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE o.option_id IN (SELECT option_id FROM site_visibility WHERE site_id = 1) ORDER BY name",
        'name',
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl'],
    ],
    'join subquery with final limit keeps ordered subset' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id) ORDER BY name LIMIT 3",
        'name',
        ['_transient_feed', 'blogname', 'home'],
    ],
    'join subquery with final offset keeps ordered tail' => [
        "SELECT o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE EXISTS (SELECT meta_key FROM option_meta WHERE meta_option_id = o.option_id) ORDER BY name LIMIT 3 OFFSET 3",
        'name',
        ['siteurl', 'siteurl', 'theme_mods'],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $column, $expected]) {
    $tests['upstream select subquery join corpus ' . $name] = static function (TestRunner $t) use ($sql, $tables, $column, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $t->same($expected, array_column($rows, $column));
    };
}

return $tests;
