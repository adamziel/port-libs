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
    ['option_id' => 7, 'option_name' => 'orphaned_option', 'autoload' => 'no', 'bytes' => 6],
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
    ['meta_option_id' => 6, 'meta_key' => 'ttl', 'meta_value' => 'long', 'rank' => 50],
];

$visibility = [
    ['visible_option_id' => 1, 'site_id' => 1, 'visibility' => 'front'],
    ['visible_option_id' => 2, 'site_id' => 1, 'visibility' => 'front'],
    ['visible_option_id' => 3, 'site_id' => 1, 'visibility' => 'front'],
    ['visible_option_id' => 4, 'site_id' => 1, 'visibility' => 'cron'],
    ['visible_option_id' => 1, 'site_id' => 2, 'visibility' => 'network'],
    ['visible_option_id' => 6, 'site_id' => 2, 'visibility' => 'theme'],
];

$flags = [
    ['flag_key' => 'scope', 'flag_value' => 'public', 'flag_group' => 'read'],
    ['flag_key' => 'scope', 'flag_value' => 'private', 'flag_group' => 'read'],
    ['flag_key' => 'scope', 'flag_value' => 'admin', 'flag_group' => 'admin'],
    ['flag_key' => 'kind', 'flag_value' => 'url', 'flag_group' => 'kind'],
    ['flag_key' => 'kind', 'flag_value' => 'theme', 'flag_group' => 'kind'],
    ['flag_key' => 'ttl', 'flag_value' => 'short', 'flag_group' => 'ttl'],
];

$tables = [
    'wp_options' => $options,
    'option_meta' => $metadata,
    'site_visibility' => $visibility,
    'meta_flags' => $flags,
];

$cases = [
    'exists joined flag public scope' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND f.flag_value = 'public') ORDER BY option_name",
        ['blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'exists joined flag private scope' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND f.flag_value = 'private') ORDER BY option_name",
        ['_transient_feed'],
    ],
    'exists joined flag admin scope' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND f.flag_group = 'admin') ORDER BY option_name",
        ['widget_recent'],
    ],
    'not exists joined flag ttl long missing flag' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND m.meta_key = 'ttl' AND m.meta_value = 'long') ORDER BY option_name",
        ['_transient_feed', 'blogname', 'home', 'orphaned_option', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'exists joined visibility front public' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND m.meta_value = 'public' AND v.visibility = 'front') ORDER BY option_name",
        ['blogname', 'home', 'siteurl'],
    ],
    'exists joined visibility network public' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND m.meta_value = 'public' AND v.visibility = 'network') ORDER BY option_name",
        ['siteurl'],
    ],
    'exists joined visibility theme public' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND m.meta_value = 'public' AND v.visibility = 'theme') ORDER BY option_name",
        ['theme_mods'],
    ],
    'not exists joined visibility any route' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT v.site_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id) ORDER BY option_name",
        ['orphaned_option', 'widget_recent'],
    ],
    'exists joined flag distinct does not multiply outer rows' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT DISTINCT m.meta_value FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND m.meta_key = 'scope') ORDER BY option_name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'exists joined flag limit keeps true first match' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.rank AS rank FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id ORDER BY rank LIMIT 1) ORDER BY option_name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'exists joined flag limit offset finds second metadata' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.rank AS rank FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id ORDER BY rank LIMIT 1 OFFSET 1) ORDER BY option_name",
        ['_transient_feed', 'home', 'siteurl', 'theme_mods'],
    ],
    'not exists joined flag limit offset excludes only second metadata owners' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.rank AS rank FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id ORDER BY rank LIMIT 1 OFFSET 1) ORDER BY option_name",
        ['blogname', 'orphaned_option', 'widget_recent'],
    ],
    'exists joined subquery references outer autoload' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND autoload = 'yes' AND v.site_id = 1) ORDER BY option_name",
        ['blogname', 'home', 'siteurl'],
    ],
    'exists joined subquery references outer bytes' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND bytes > m.rank) ORDER BY option_name",
        ['_transient_feed', 'home', 'siteurl', 'theme_mods'],
    ],
    'not exists joined subquery references outer bytes' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND bytes > m.rank) ORDER BY option_name",
        ['blogname', 'orphaned_option', 'widget_recent'],
    ],
    'exists joined subquery with outer like guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND option_name LIKE 'theme%') ORDER BY option_name",
        ['theme_mods'],
    ],
    'exists joined subquery with outer glob guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND option_name GLOB '*transient*') ORDER BY option_name",
        ['_transient_feed'],
    ],
    'exists joined subquery with outer between guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND bytes BETWEEN m.rank AND 70) ORDER BY option_name",
        ['home', 'siteurl', 'theme_mods'],
    ],
    'exists joined subquery with outer not between guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND bytes NOT BETWEEN m.rank AND 70) ORDER BY option_name",
        ['_transient_feed', 'blogname', 'home', 'siteurl'],
    ],
    'exists joined subquery under outer and' => [
        "SELECT option_name FROM wp_options WHERE autoload = 'yes' AND EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND v.site_id = 2) ORDER BY option_name",
        ['siteurl', 'theme_mods'],
    ],
    'exists joined subquery under outer or' => [
        "SELECT option_name FROM wp_options WHERE option_name = 'orphaned_option' OR EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND f.flag_group = 'admin') ORDER BY option_name",
        ['orphaned_option', 'widget_recent'],
    ],
    'not exists joined subquery under outer or' => [
        "SELECT option_name FROM wp_options WHERE option_name = 'theme_mods' OR NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id) ORDER BY option_name",
        ['orphaned_option', 'theme_mods', 'widget_recent'],
    ],
    'in joined subquery public ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT m.meta_option_id FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE f.flag_value = 'public') ORDER BY option_name",
        ['blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'not in joined subquery private ids' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT m.meta_option_id FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE f.flag_value = 'private') ORDER BY option_name",
        ['blogname', 'home', 'orphaned_option', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'in joined subquery front ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE v.visibility = 'front') ORDER BY option_name",
        ['blogname', 'home', 'siteurl'],
    ],
    'not in joined subquery routed ids' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id) ORDER BY option_name",
        ['orphaned_option', 'widget_recent'],
    ],
    'in joined subquery with outer autoload' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE autoload = 'yes' AND v.site_id = 1) ORDER BY option_name",
        ['blogname', 'home', 'siteurl'],
    ],
    'not in joined subquery with outer autoload' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE autoload = 'yes' AND v.site_id = 1) ORDER BY option_name",
        ['_transient_feed', 'orphaned_option', 'theme_mods', 'widget_recent'],
    ],
    'in joined distinct subquery ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT DISTINCT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_key = 'scope') ORDER BY option_name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'in joined limit subquery first ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id LIMIT 2) ORDER BY option_name",
        ['siteurl'],
    ],
    'in joined limit offset subquery later ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT m.meta_option_id FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id LIMIT 2 OFFSET 2) ORDER BY option_name",
        ['siteurl'],
    ],
    'exists joined subquery with left join finds unrouted metadata' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m LEFT JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND v.visible_option_id IS NULL) ORDER BY option_name",
        ['widget_recent'],
    ],
    'not exists joined subquery with left join no metadata' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m LEFT JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id) ORDER BY option_name",
        ['orphaned_option'],
    ],
    'exists joined subquery with cross join flag guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m CROSS JOIN meta_flags AS f WHERE m.meta_option_id = option_id AND m.meta_key = f.flag_key AND m.meta_value = f.flag_value AND f.flag_group = 'ttl') ORDER BY option_name",
        ['_transient_feed'],
    ],
    'not exists joined subquery with cross join flag guard' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m CROSS JOIN meta_flags AS f WHERE m.meta_option_id = option_id AND m.meta_key = f.flag_key AND m.meta_value = f.flag_value AND f.flag_group = 'ttl') ORDER BY option_name",
        ['blogname', 'home', 'orphaned_option', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'exists joined subquery keeps outer left join multiplicity' => [
        "SELECT o.option_name FROM wp_options AS o LEFT JOIN site_visibility AS outer_v ON o.option_id = outer_v.visible_option_id WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = o.option_id AND f.flag_value = 'public') ORDER BY o.option_name",
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'not exists joined subquery keeps outer left join null extension' => [
        "SELECT o.option_name FROM wp_options AS o LEFT JOIN site_visibility AS outer_v ON o.option_id = outer_v.visible_option_id WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = o.option_id) ORDER BY o.option_name",
        ['orphaned_option'],
    ],
    'exists joined subquery references outer joined site id' => [
        "SELECT o.option_name FROM wp_options AS o JOIN site_visibility AS outer_v ON o.option_id = outer_v.visible_option_id WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS inner_v ON m.meta_option_id = inner_v.visible_option_id WHERE m.meta_option_id = o.option_id AND inner_v.site_id = outer_v.site_id) ORDER BY o.option_name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'not exists joined subquery references outer joined site id' => [
        "SELECT o.option_name FROM wp_options AS o JOIN site_visibility AS outer_v ON o.option_id = outer_v.visible_option_id WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS inner_v ON m.meta_option_id = inner_v.visible_option_id WHERE m.meta_option_id = o.option_id AND inner_v.site_id = outer_v.site_id AND inner_v.visibility = 'network') ORDER BY o.option_name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'exists joined subquery respects aliased outer id' => [
        "SELECT option_name FROM wp_options AS o WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND v.visibility = 'front') ORDER BY option_name",
        ['blogname', 'home', 'siteurl'],
    ],
    'not exists joined subquery respects aliased outer id' => [
        "SELECT option_name FROM wp_options AS o WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND v.visibility = 'front') ORDER BY option_name",
        ['_transient_feed', 'orphaned_option', 'theme_mods', 'widget_recent'],
    ],
    'exists joined subquery respects final outer limit' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id) ORDER BY option_name LIMIT 3",
        ['_transient_feed', 'blogname', 'home'],
    ],
    'exists joined subquery respects final outer offset' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id) ORDER BY option_name LIMIT 3 OFFSET 3",
        ['siteurl', 'theme_mods', 'widget_recent'],
    ],
    'exists joined subquery in scalar function operand' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND upper(f.flag_group) = 'KIND') ORDER BY option_name",
        ['home', 'siteurl', 'theme_mods'],
    ],
    'not exists joined subquery in scalar function operand' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND upper(f.flag_group) = 'KIND') ORDER BY option_name",
        ['_transient_feed', 'blogname', 'orphaned_option', 'widget_recent'],
    ],
    'exists joined subquery with numeric rank comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND m.rank >= 20) ORDER BY option_name",
        ['_transient_feed', 'home', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'not exists joined subquery with numeric rank comparison' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = option_id AND m.rank >= 20) ORDER BY option_name",
        ['blogname', 'orphaned_option'],
    ],
    'exists joined subquery with ifnull outer value' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND ifnull(autoload, 'no') = 'no') ORDER BY option_name",
        ['_transient_feed'],
    ],
    'not exists joined subquery with ifnull outer value' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND ifnull(autoload, 'no') = 'no') ORDER BY option_name",
        ['blogname', 'home', 'orphaned_option', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'exists joined subquery preserves duplicate outer route rows' => [
        "SELECT o.option_name FROM wp_options AS o JOIN site_visibility AS outer_v ON o.option_id = outer_v.visible_option_id WHERE EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = o.option_id AND f.flag_value = 'public') ORDER BY o.option_name",
        ['blogname', 'home', 'siteurl', 'siteurl', 'theme_mods'],
    ],
    'not exists joined subquery filters duplicate outer route rows' => [
        "SELECT o.option_name FROM wp_options AS o JOIN site_visibility AS outer_v ON o.option_id = outer_v.visible_option_id WHERE NOT EXISTS (SELECT m.meta_key FROM option_meta AS m JOIN meta_flags AS f ON m.meta_key = f.flag_key AND m.meta_value = f.flag_value WHERE m.meta_option_id = o.option_id AND f.flag_value = 'public') ORDER BY o.option_name",
        ['_transient_feed'],
    ],
    'exists joined subquery with selected literal' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND v.site_id = 2) ORDER BY option_name",
        ['siteurl', 'theme_mods'],
    ],
    'not exists joined subquery with selected literal' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM option_meta AS m JOIN site_visibility AS v ON m.meta_option_id = v.visible_option_id WHERE m.meta_option_id = option_id AND v.site_id = 2) ORDER BY option_name",
        ['_transient_feed', 'blogname', 'home', 'orphaned_option', 'widget_recent'],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['upstream subquery flattening exists corpus ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $column = array_key_first($rows[0] ?? ['option_name' => null]);
        $t->same($expected, array_column($rows, $column));
    };
}

return $tests;
