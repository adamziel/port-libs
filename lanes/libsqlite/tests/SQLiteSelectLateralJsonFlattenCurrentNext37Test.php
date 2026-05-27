<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$wpOptions = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"seo","rules":[{"name":"title","enabled":1,"priority":7},{"name":"meta","enabled":0,"priority":2}]},{"slug":"cache","rules":[{"name":"page","enabled":1,"priority":5},{"name":"object","enabled":1,"priority":9}]}],"flags":["network","beta"],"meta":{"owner":"site"}}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"forms","rules":[{"name":"contact","enabled":1,"priority":4},{"name":"captcha","enabled":0,"priority":1}]}],"flags":["theme"],"meta":{"owner":"theme"}}',
    ],
    [
        'option_id' => 3,
        'option_name' => 'empty_plugin_settings',
        'autoload' => 'no',
        'option_value' => '{"groups":[],"flags":[],"meta":{"owner":"empty"}}',
    ],
    [
        'option_id' => 4,
        'option_name' => 'jsonb_plugin_settings',
        'autoload' => 'yes',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'groups' => [
                [
                    'slug' => 'media',
                    'rules' => [
                        ['name' => 'images', 'enabled' => 1, 'priority' => 6],
                        ['name' => 'video', 'enabled' => 0, 'priority' => 3],
                    ],
                ],
            ],
            'flags' => ['jsonb'],
            'meta' => ['owner' => 'media'],
        ])),
    ],
    [
        'option_id' => 5,
        'option_name' => 'broken_plugin_settings',
        'autoload' => 'no',
        'option_value' => null,
    ],
];

$flagSql = "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o, json_each(o.option_value, '$.flags') AS f WHERE o.autoload = 'yes' ORDER BY option_name, flag";
$prioritySql = "SELECT o.option_name AS option_name, r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' ORDER BY priority DESC, option_name";
$ruleNameSql = "SELECT o.option_name AS option_name, r.atom AS rule_name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'name' ORDER BY option_name, rule_name";
$groupSql = "SELECT o.option_name AS option_name, g.fullkey AS group_path FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g WHERE g.type = 'object' ORDER BY option_name, group_path";

$cases = [
    'select lateral json flatten current next37 comma join expands current flags' => static function (TestRunner $t) use ($wpOptions, $flagSql): void {
        $rows = SQLiteSelectSql::execute($flagSql, ['wp_options' => $wpOptions]);
        $t->same(['jsonb', 'beta', 'network', 'theme'], array_column($rows, 'flag'));
    },
    'select lateral json flatten current next37 comma join preserves host option names' => static function (TestRunner $t) use ($wpOptions, $flagSql): void {
        $rows = SQLiteSelectSql::execute($flagSql, ['wp_options' => $wpOptions]);
        $t->same(['jsonb_plugin_settings', 'site_plugin_settings', 'site_plugin_settings', 'theme_plugin_settings'], array_column($rows, 'option_name'));
    },
    'select lateral json flatten current next37 comma join skips null host json' => static function (TestRunner $t) use ($wpOptions, $flagSql): void {
        $rows = SQLiteSelectSql::execute($flagSql, ['wp_options' => $wpOptions]);
        $t->same(false, in_array('broken_plugin_settings', array_column($rows, 'option_name'), true));
    },
    'select lateral json flatten current next37 comma join skips empty arrays' => static function (TestRunner $t) use ($wpOptions, $flagSql): void {
        $rows = SQLiteSelectSql::execute($flagSql, ['wp_options' => $wpOptions]);
        $t->same(false, in_array('empty_plugin_settings', array_column($rows, 'option_name'), true));
    },
    'select lateral json flatten current next37 priority flatten orders current rows' => static function (TestRunner $t) use ($wpOptions, $prioritySql): void {
        $rows = SQLiteSelectSql::execute($prioritySql, ['wp_options' => $wpOptions]);
        $t->same([9, 7, 6, 5, 4, 3, 2, 1], array_column($rows, 'priority'));
    },
    'select lateral json flatten current next37 priority flatten preserves source rows' => static function (TestRunner $t) use ($wpOptions, $prioritySql): void {
        $rows = SQLiteSelectSql::execute($prioritySql, ['wp_options' => $wpOptions]);
        $t->same(['site_plugin_settings', 'site_plugin_settings', 'jsonb_plugin_settings'], array_slice(array_column($rows, 'option_name'), 0, 3));
    },
    'select lateral json flatten current next37 rule names flatten by current group path' => static function (TestRunner $t) use ($wpOptions, $ruleNameSql): void {
        $rows = SQLiteSelectSql::execute($ruleNameSql, ['wp_options' => $wpOptions]);
        $t->same(['images', 'video', 'meta', 'object', 'page', 'title', 'captcha', 'contact'], array_column($rows, 'rule_name'));
    },
    'select lateral json flatten current next37 group paths are produced before child flatten' => static function (TestRunner $t) use ($wpOptions, $groupSql): void {
        $rows = SQLiteSelectSql::execute($groupSql, ['wp_options' => $wpOptions]);
        $t->same(['$.groups[0]', '$.groups[0].rules[0]', '$.groups[0].rules[1]', '$.groups[0]'], array_slice(array_column($rows, 'group_path'), 0, 4));
    },
    'select lateral json flatten current next37 grouped comma flatten counts rules per option' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute("SELECT o.option_name AS option_name, count(r.atom) AS rules FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'name' GROUP BY o.option_name ORDER BY option_name", ['wp_options' => $wpOptions]);
        $t->same([['option_name' => 'jsonb_plugin_settings', 'rules' => 2], ['option_name' => 'site_plugin_settings', 'rules' => 4], ['option_name' => 'theme_plugin_settings', 'rules' => 2]], $rows);
    },
    'select lateral json flatten current next37 grouped comma flatten sums priorities' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute("SELECT o.option_name AS option_name, sum(r.atom) AS priority_sum FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' GROUP BY o.option_name ORDER BY priority_sum DESC", ['wp_options' => $wpOptions]);
        $t->same([23, 9, 5], array_column($rows, 'priority_sum'));
    },
];

$singleValueCases = [
    'select lateral json flatten current next37 having filters grouped flatten' => ["SELECT o.option_name AS option_name, max(r.atom) AS max_priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' GROUP BY o.option_name HAVING max(r.atom) >= 6 ORDER BY option_name", 'option_name', ['jsonb_plugin_settings', 'site_plugin_settings']],
    'select lateral json flatten current next37 distinct flattened keys' => ["SELECT DISTINCT r.key AS key_name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.path LIKE '%.rules[%]' ORDER BY key_name", 'key_name', ['enabled', 'name', 'priority']],
    'select lateral json flatten current next37 limit over flattened priorities' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' ORDER BY priority DESC LIMIT 3", 'priority', [9, 7, 6]],
    'select lateral json flatten current next37 comma limit over flattened priorities' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' ORDER BY priority DESC LIMIT 2, 3", 'priority', [6, 5, 4]],
    'select lateral json flatten current next37 current fullkey prefix filters flattened children' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.fullkey = '$.groups[0].rules' AND r.key = 'priority' ORDER BY priority", 'priority', [2, 7]],
    'select lateral json flatten current next37 host where filters before flatten' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 4 AND g.key = 'rules' AND r.key = 'priority' ORDER BY priority DESC", 'priority', [6, 3]],
    'select lateral json flatten current next37 jsonb rows flatten through comma sources' => ["SELECT r.atom AS rule_name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_name = 'jsonb_plugin_settings' AND g.key = 'rules' AND r.key = 'name' ORDER BY rule_name", 'rule_name', ['images', 'video']],
    'select lateral json flatten current next37 scalar labels compose current child values' => ["SELECT o.option_name || ':' || r.atom AS label FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' AND r.atom >= 7 ORDER BY label", 'label', ['site_plugin_settings:7', 'site_plugin_settings:9']],
    'select lateral json flatten current next37 scalar lower filters flattened names' => ["SELECT r.atom AS rule_name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'name' AND lower(r.atom) = 'video'", 'rule_name', ['video']],
    'select lateral json flatten current next37 json extract can use flattened fullkey' => ["SELECT json_extract(o.option_value, r.fullkey || '.name') AS rule_name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.fullkey = '$.groups[1].rules' AND r.type = 'object' ORDER BY rule_name", 'rule_name', ['object', 'page']],
    'select lateral json flatten current next37 json type can use flattened fullkey' => ["SELECT json_type(o.option_value, r.fullkey || '.enabled') AS enabled_type FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.fullkey = '$.groups[0].rules' AND r.type = 'object' ORDER BY enabled_type", 'enabled_type', ['integer', 'integer']],
    'select lateral json flatten current next37 rowid aliases survive comma flatten' => ["SELECT r.rowid AS rowid_value FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.fullkey = '$.groups[0].rules' AND r.type = 'object' ORDER BY rowid_value", 'rowid_value', [1, 5]],
    'select lateral json flatten current next37 fullkey glob filters flattened groups' => ["SELECT g.fullkey AS group_path FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.type = 'object' AND r.key = 'slug' AND g.fullkey GLOB '$.groups[[]1]'", 'group_path', ['$.groups[1]']],
    'select lateral json flatten current next37 path like filters flattened priorities' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.key = 'rules' AND r.key = 'priority' AND r.fullkey LIKE '$.groups[1]%' ORDER BY priority", 'priority', [5, 9]],
    'select lateral json flatten current next37 selected metadata root flattens current option' => ["SELECT r.atom AS priority FROM roots AS x, wp_options AS o, json_tree(o.option_value, x.root) AS r WHERE x.option_id = o.option_id AND r.key = 'priority' ORDER BY priority DESC", 'priority', [9, 7, 5, 4, 2, 1]],
    'select lateral json flatten current next37 current atom roots flatten child arrays' => ["SELECT child.atom AS value FROM cfg, json_each(cfg.roots) AS root, json_each(cfg.doc, root.atom) AS child WHERE root.type = 'text' AND child.type = 'integer' ORDER BY value", 'value', [1, 2, 3]],
    'select lateral json flatten current next37 current atom roots preserve child keys' => ["SELECT child.key AS key_name FROM cfg, json_each(cfg.roots) AS root, json_each(cfg.doc, root.atom) AS child WHERE root.type = 'text' AND child.type = 'integer' ORDER BY key_name", 'key_name', [0, 0, 1]],
    'select lateral json flatten current next37 current root from host column' => ["SELECT child.atom AS value FROM cfg, json_each(cfg.doc, cfg.root) AS child WHERE child.type = 'integer' ORDER BY value DESC", 'value', [3, 2, 1]],
    'select lateral json flatten current next37 missing current root yields empty flatten' => ["SELECT child.atom AS value FROM cfg, json_each(cfg.doc, '$.missing') AS child WHERE child.type = 'integer'", 'value', []],
    'select lateral json flatten current next37 null current root yields empty flatten' => ["SELECT child.atom AS value FROM cfg, json_each(cfg.doc, NULL) AS child WHERE child.type = 'integer'", 'value', []],
    'select lateral json flatten current next37 null host json yields empty flatten' => ["SELECT child.atom AS value FROM cfg, json_each(cfg.doc, '$.selected') AS child WHERE child.type = 'integer'", 'value', []],
    'select lateral json flatten current next37 malformed jsonb host yields empty flatten' => ["SELECT child.atom AS value FROM cfg, json_each(cfg.doc, cfg.root) AS child WHERE child.type = 'integer'", 'value', []],
    'select lateral json flatten current next37 cte wraps flattened rows' => ["WITH flat AS (SELECT o.option_name AS option_name, r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority') SELECT option_name FROM flat WHERE priority >= 7 ORDER BY option_name", 'option_name', ['site_plugin_settings', 'site_plugin_settings']],
    'select lateral json flatten current next37 joined metadata after flatten' => ["SELECT labels.label AS label FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r, labels WHERE o.option_id = 1 AND g.key = 'rules' AND r.key = 'priority' AND labels.priority = r.atom ORDER BY r.atom DESC", 'label', ['critical', 'high', 'medium']],
    'select lateral json flatten current next37 flattened owner metadata' => ["SELECT m.atom AS owner FROM wp_options AS o, json_each(o.option_value, '$.meta') AS m WHERE m.key = 'owner' ORDER BY owner", 'owner', ['empty', 'media', 'site', 'theme']],
    'select lateral json flatten current next37 flattened flag index order' => ["SELECT f.key AS flag_index FROM wp_options AS o, json_each(o.option_value, '$.flags') AS f WHERE o.option_id = 1 ORDER BY flag_index DESC", 'flag_index', [1, 0]],
    'select lateral json flatten current next37 flattened group slug order' => ["SELECT s.atom AS slug FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_each(o.option_value, g.fullkey) AS s WHERE o.option_id = 1 AND g.type = 'object' AND s.key = 'slug' ORDER BY slug", 'slug', ['cache', 'seo']],
    'select lateral json flatten current next37 flattened enabled flags' => ["SELECT r.atom AS enabled FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.key = 'rules' AND r.key = 'enabled' ORDER BY enabled DESC", 'enabled', [1, 1, 1, 0]],
    'select lateral json flatten current next37 flattened option id ordering' => ["SELECT o.option_id AS id FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' ORDER BY id DESC, r.atom DESC LIMIT 4", 'id', [4, 4, 2, 2]],
    'select lateral json flatten current next37 flattened child path labels' => ["SELECT g.fullkey || ':' || r.key AS label FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE o.option_id = 1 AND g.fullkey = '$.groups[1].rules' AND r.type = 'object' ORDER BY label", 'label', ['$.groups[1].rules:0', '$.groups[1].rules:1']],
    'select lateral json flatten current next37 no rows after filtered parent flatten' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'not_rules' AND r.key = 'priority'", 'priority', []],
    'select lateral json flatten current next37 no rows for missing child path' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_each(o.option_value, g.fullkey || '.missing') AS r WHERE g.type = 'object'", 'priority', []],
    'select lateral json flatten current next37 projection can read host and flattened aliases' => ["SELECT o.option_name || '/' || f.atom AS label FROM wp_options AS o, json_each(o.option_value, '$.flags') AS f WHERE o.autoload = 'yes' ORDER BY label", 'label', ['jsonb_plugin_settings/jsonb', 'site_plugin_settings/beta', 'site_plugin_settings/network', 'theme_plugin_settings/theme']],
    'select lateral json flatten current next37 filtered autoload preserves flattened count' => ["SELECT f.atom AS flag FROM wp_options AS o, json_each(o.option_value, '$.flags') AS f WHERE o.autoload = 'yes' ORDER BY flag", 'flag', ['beta', 'jsonb', 'network', 'theme']],
    'select lateral json flatten current next37 selected object keys from current group' => ["SELECT r.key AS key_name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_each(o.option_value, g.fullkey) AS r WHERE o.option_id = 4 AND g.type = 'object' AND g.path = '$.groups' ORDER BY key_name", 'key_name', ['rules', 'slug']],
    'select lateral json flatten current next37 flattened current root with equality predicate' => ["SELECT r.atom AS name FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.fullkey = '$.groups[0].rules' AND r.key = 'name' AND o.option_id = 2 ORDER BY name", 'name', ['captcha', 'contact']],
    'select lateral json flatten current next37 flattened current root with numeric predicate' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' AND r.atom BETWEEN 3 AND 6 ORDER BY priority", 'priority', [3, 4, 5, 6]],
    'select lateral json flatten current next37 flattened current root with not between predicate' => ["SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority' AND r.atom NOT BETWEEN 3 AND 7 ORDER BY priority", 'priority', [1, 2, 9]],
];

$tablesFor = static function (string $name, string $sql) use ($wpOptions): array {
    if (str_contains($sql, 'FROM cfg')) {
        if (str_contains($name, 'malformed jsonb')) {
            return ['cfg' => [['doc' => new SQLiteBlobValue(hex2bin('1c00')), 'root' => '$.selected']]];
        }
        if (str_contains($name, 'null host json')) {
            return ['cfg' => [['doc' => null, 'root' => '$.selected']]];
        }
        if (str_contains($name, 'current atom roots')) {
            return ['cfg' => [['roots' => '["$.a","$.b"]', 'doc' => '{"a":[2],"b":[1,3]}']]];
        }

        return ['cfg' => [['doc' => '{"selected":[1,3,2],"other":[9]}', 'root' => '$.selected']]];
    }
    if (str_contains($name, 'selected metadata root')) {
        return [
            'wp_options' => $wpOptions,
            'roots' => [
                ['option_id' => 1, 'root' => '$.groups[0].rules'],
                ['option_id' => 1, 'root' => '$.groups[1].rules'],
                ['option_id' => 2, 'root' => '$.groups[0].rules'],
            ],
        ];
    }
    if (str_contains($name, 'joined metadata after flatten')) {
        return [
            'wp_options' => $wpOptions,
            'labels' => [
                ['priority' => 9, 'label' => 'critical'],
                ['priority' => 7, 'label' => 'high'],
                ['priority' => 5, 'label' => 'medium'],
            ],
        ];
    }

    return ['wp_options' => $wpOptions];
};

foreach ($singleValueCases as $name => [$sql, $column, $expected]) {
    $cases[$name] = static function (TestRunner $t) use ($tablesFor, $name, $sql, $column, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, $tablesFor($name, $sql));
        $t->same($expected, array_column($rows, $column));
    };
}

$cases['select lateral json flatten current next37 plan rewrites comma sources to dynamic joins'] = static function (TestRunner $t) use ($wpOptions): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority'",
        ['wp_options' => $wpOptions],
    );
    $t->same(2, count($plan['joins']));
    $t->true(is_callable($plan['joins'][0]['dynamicRows']));
    $t->true(is_callable($plan['joins'][1]['dynamicRows']));
};

$cases['select lateral json flatten current next37 dynamic comma rows evaluate current merged row'] = static function (TestRunner $t) use ($wpOptions): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT r.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r WHERE g.key = 'rules' AND r.key = 'priority'",
        ['wp_options' => $wpOptions],
    );
    $groupRows = ($plan['joins'][0]['dynamicRows'])($plan['from'][0]);
    $rules = array_values(array_filter($groupRows, static fn (array $row): bool => ($row['g.key'] ?? null) === 'rules'))[0];
    $childRows = ($plan['joins'][1]['dynamicRows'])(array_merge($plan['from'][0], $rules));
    $t->same(['rules', 0, 'name', 'enabled', 'priority', 1, 'name', 'enabled', 'priority'], array_column($childRows, 'r.key'));
};

$cases['select lateral json flatten current next37 malformed root path still raises'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT child.key FROM cfg, json_each(cfg.doc, cfg.root) AS child WHERE child.type = 'integer'",
        ['cfg' => [['doc' => '{"selected":[1]}', 'root' => '$.bad[']]],
    ));
};

$cases['select lateral json flatten current next37 malformed dynamic json still raises'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT child.key FROM cfg, json_each(cfg.doc, cfg.root) AS child WHERE child.type = 'integer'",
        ['cfg' => [['doc' => '{bad', 'root' => '$.selected']]],
    ));
};

return $cases;
