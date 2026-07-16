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
        'option_value' => '{"groups":[{"slug":"seo","rules":[{"name":"title","enabled":1,"priority":7},{"name":"meta","enabled":0,"priority":2}]},{"slug":"cache","rules":[{"name":"page","enabled":1,"priority":5},{"name":"object","enabled":1,"priority":9}]}],"flags":["network","beta"]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"forms","rules":[{"name":"contact","enabled":1,"priority":4},{"name":"captcha","enabled":0,"priority":1}]}],"flags":["theme"]}',
    ],
    [
        'option_id' => 3,
        'option_name' => 'empty_plugin_settings',
        'autoload' => 'no',
        'option_value' => '{"groups":[],"flags":[]}',
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
        ])),
    ],
    [
        'option_id' => 5,
        'option_name' => 'missing_plugin_settings',
        'autoload' => 'no',
        'option_value' => '{"flags":["missing"]}',
    ],
];

$cases = [
    'json tree root can reference current group fullkey' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT g.fullkey AS group_path, r.key AS rule_index FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey || '.rules') AS r ON r.type = 'object' WHERE o.option_id = 1 ORDER BY group_path, rule_index",
            ['wp_options' => $wpOptions],
        );
        $t->same(['$.groups[0]', '$.groups[0]', '$.groups[1]', '$.groups[1]'], array_column($rows, 'group_path'));
        $t->same([0, 1, 0, 1], array_column($rows, 'rule_index'));
    },
    'json tree lateral root extracts rule names from current rule path' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'name' WHERE o.option_id = 1 ORDER BY name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['meta', 'object', 'page', 'title'], array_column($rows, 'name'));
    },
    'json tree lateral root preserves host option names' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, leaf.atom AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'name' ORDER BY option_name, rule_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['jsonb_plugin_settings', 'jsonb_plugin_settings', 'site_plugin_settings', 'site_plugin_settings', 'site_plugin_settings', 'site_plugin_settings', 'theme_plugin_settings', 'theme_plugin_settings'], array_column($rows, 'option_name'));
    },
    'json tree lateral root works with jsonb host values' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' WHERE o.option_id = 4 ORDER BY priority DESC",
            ['wp_options' => $wpOptions],
        );
        $t->same([6, 3], array_column($rows, 'priority'));
    },
    'json each lateral root can scan current object path' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT child.key AS key_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey) AS child ON child.key = 'slug' WHERE o.option_id = 1 ORDER BY child.atom",
            ['wp_options' => $wpOptions],
        );
        $t->same(['slug', 'slug'], array_column($rows, 'key_name'));
    },
    'json each lateral root returns current object slug atoms' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT child.atom AS slug FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey) AS child ON child.key = 'slug' WHERE o.option_id = 1 ORDER BY slug",
            ['wp_options' => $wpOptions],
        );
        $t->same(['cache', 'seo'], array_column($rows, 'slug'));
    },
    'json each lateral root composes root from current fullkey concatenation' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT rule.key AS rule_index FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey || '.rules') AS rule ON rule.type = 'object' WHERE o.option_id = 2 ORDER BY rule_index",
            ['wp_options' => $wpOptions],
        );
        $t->same([0, 1], array_column($rows, 'rule_index'));
    },
    'json tree lateral root supports left join null extension for missing groups' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS id, leaf.key AS key_name FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(o.option_value, g.fullkey || '.rules') AS leaf ON leaf.type = 'object' WHERE o.option_id IN (3, 5) ORDER BY id",
            ['wp_options' => $wpOptions],
        );
        $t->same([3, 5], array_column($rows, 'id'));
        $t->same([null, null], array_column($rows, 'key_name'));
    },
    'json tree lateral root keeps empty current root as empty rowset' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.key AS key_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'array' JOIN json_each(o.option_value, g.fullkey || '.missing') AS leaf ON leaf.type = 'object' WHERE o.option_id = 1",
            ['wp_options' => $wpOptions],
        );
        $t->same([], $rows);
    },
    'json tree lateral root filters by current parent id' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.key AS field_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.parent = 1 WHERE o.option_id = 1 ORDER BY field_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['enabled', 'enabled', 'name', 'name', 'priority', 'priority'], array_column($rows, 'field_name'));
    },
    'json tree lateral root supports aggregate over current roots' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, sum(leaf.atom) AS priority_sum FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' GROUP BY o.option_name ORDER BY priority_sum DESC",
            ['wp_options' => $wpOptions],
        );
        $t->same(['site_plugin_settings', 'jsonb_plugin_settings', 'theme_plugin_settings'], array_column($rows, 'option_name'));
        $t->same([23, 9, 5], array_column($rows, 'priority_sum'));
    },
    'json tree lateral root supports having after aggregate' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, max(leaf.atom) AS max_priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' GROUP BY o.option_name HAVING max(leaf.atom) >= 6 ORDER BY max_priority DESC",
            ['wp_options' => $wpOptions],
        );
        $t->same(['site_plugin_settings', 'jsonb_plugin_settings'], array_column($rows, 'option_name'));
    },
    'json tree lateral root supports distinct current-root outputs' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT DISTINCT leaf.key AS key_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey) AS leaf ON leaf.type = 'text' ORDER BY key_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['name', 'slug'], array_column($rows, 'key_name'));
    },
    'json tree lateral root supports final limit over ordered roots' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' ORDER BY priority DESC LIMIT 3",
            ['wp_options' => $wpOptions],
        );
        $t->same([9, 7, 6], array_column($rows, 'priority'));
    },
    'json tree lateral root supports comma limit over ordered roots' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' ORDER BY priority DESC LIMIT 2, 3",
            ['wp_options' => $wpOptions],
        );
        $t->same([6, 5, 4], array_column($rows, 'priority'));
    },
    'json tree lateral root supports cte wrapping' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "WITH priorities AS (SELECT o.option_name AS option_name, leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority') SELECT option_name FROM priorities WHERE priority >= 7 ORDER BY option_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['site_plugin_settings', 'site_plugin_settings'], array_column($rows, 'option_name'));
    },
    'json tree lateral root supports current path equality predicates' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[1].rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'name' WHERE o.option_id = 1 ORDER BY rule_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['object', 'page'], array_column($rows, 'rule_name'));
    },
    'json tree lateral root supports current fullkey glob predicates' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT g.fullkey AS group_path FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey || '.rules') AS r ON r.type = 'object' WHERE g.fullkey GLOB '$.groups[[]1]' ORDER BY group_path",
            ['wp_options' => $wpOptions],
        );
        $t->same(['$.groups[1]', '$.groups[1]'], array_column($rows, 'group_path'));
    },
    'json tree lateral root preserves rowid aliases from child source' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT r.rowid AS rowid_value FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[0].rules' JOIN json_each(o.option_value, g.fullkey) AS r ON r.type = 'object' WHERE o.option_id = 1 ORDER BY rowid_value",
            ['wp_options' => $wpOptions],
        );
        $t->same([1, 2], array_column($rows, 'rowid_value'));
    },
    'json tree lateral root supports key lookup inside current child object' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT field.atom AS enabled FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[0].rules' JOIN json_each(o.option_value, g.fullkey) AS r ON r.type = 'object' JOIN json_each(o.option_value, r.fullkey) AS field ON field.key = 'enabled' WHERE o.option_id = 1 ORDER BY enabled DESC",
            ['wp_options' => $wpOptions],
        );
        $t->same([1, 0], array_column($rows, 'enabled'));
    },
    'json tree lateral root supports three-level current root chain' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT field.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[1].rules' JOIN json_each(o.option_value, g.fullkey) AS r ON r.type = 'object' JOIN json_each(o.option_value, r.fullkey) AS field ON field.key = 'priority' WHERE o.option_id = 1 ORDER BY priority",
            ['wp_options' => $wpOptions],
        );
        $t->same([5, 9], array_column($rows, 'priority'));
    },
    'json tree lateral root supports aliases at each level' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT f.atom AS name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS parent ON parent.key = 'rules' JOIN json_each(o.option_value, parent.fullkey) AS child ON child.type = 'object' JOIN json_each(o.option_value, child.fullkey) AS f ON f.key = 'name' WHERE o.option_id = 2 ORDER BY name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['captcha', 'contact'], array_column($rows, 'name'));
    },
    'json tree lateral root handles ON predicates from left and right rows' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT f.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS f ON f.key = 'priority' AND f.atom >= o.option_id ORDER BY priority",
            ['wp_options' => $wpOptions],
        );
        $t->same([2, 4, 5, 6, 7, 9], array_column($rows, 'priority'));
    },
    'json tree lateral root supports not exists style anti filter through left join' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' WHERE g.key IS NULL ORDER BY option_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['empty_plugin_settings', 'missing_plugin_settings'], array_column($rows, 'option_name'));
    },
    'json tree lateral root keeps sql null root rows empty' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT r.key AS key_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, NULL) AS r ON r.type = 'object'",
            ['wp_options' => $wpOptions],
        );
        $t->same([], $rows);
    },
    'json tree lateral root treats missing current root as no rows' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT r.key AS key_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey || '.missing') AS r ON r.type = 'object'",
            ['wp_options' => $wpOptions],
        );
        $t->same([], $rows);
    },
    'json tree lateral root preserves sibling option filters' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT f.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS f ON f.key = 'priority' WHERE o.autoload = 'yes' ORDER BY priority DESC LIMIT 4",
            ['wp_options' => $wpOptions],
        );
        $t->same([9, 7, 6, 5], array_column($rows, 'priority'));
    },
    'json tree lateral root composes with scalar json extract on host row' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_extract(o.option_value, '$.flags[0]') AS first_flag, f.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS f ON f.key = 'priority' WHERE o.option_id = 1 ORDER BY priority DESC LIMIT 1",
            ['wp_options' => $wpOptions],
        );
        $t->same([['first_flag' => 'network', 'priority' => 9]], $rows);
    },
    'json tree lateral root supports scalar root expression from host row' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS value FROM cfg JOIN json_each(cfg.doc, cfg.root) AS leaf ON leaf.type = 'integer' ORDER BY value DESC",
            ['cfg' => [['doc' => '{"selected":[1,3,2],"other":[9]}', 'root' => '$.selected']]],
        );
        $t->same([3, 2, 1], array_column($rows, 'value'));
    },
    'json tree lateral root supports scalar root expression from previous json row' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT child.atom AS value FROM cfg JOIN json_tree(cfg.doc, '$.roots') AS root ON root.type = 'text' JOIN json_each(cfg.doc, root.atom) AS child ON child.type = 'integer' ORDER BY value",
            ['cfg' => [['doc' => '{"roots":["$.a","$.b"],"a":[2],"b":[1,3]}']]],
        );
        $t->same([1, 2, 3], array_column($rows, 'value'));
    },
    'json tree lateral root supports current json row atom as root' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT child.key AS key_name FROM cfg JOIN json_each(cfg.roots) AS root ON root.type = 'text' JOIN json_each(cfg.doc, root.atom) AS child ON child.type = 'integer' ORDER BY key_name",
            ['cfg' => [['roots' => '["$.first","$.second"]', 'doc' => '{"first":[10],"second":[20,30]}']]],
        );
        $t->same([0, 0, 1], array_column($rows, 'key_name'));
    },
    'json tree lateral root supports current path from child path column' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT next.atom AS value FROM cfg JOIN json_tree(cfg.doc, '$.items') AS item ON item.key = 'values' JOIN json_each(cfg.doc, item.fullkey) AS next ON next.type = 'integer' ORDER BY value DESC",
            ['cfg' => [['doc' => '{"items":[{"values":[1,4]},{"values":[3]}]}']]],
        );
        $t->same([4, 3, 1], array_column($rows, 'value'));
    },
    'json tree lateral root supports current root with grouped counts' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT root.atom AS root_path, count(child.key) AS child_count FROM cfg JOIN json_each(cfg.roots) AS root ON root.type = 'text' JOIN json_each(cfg.doc, root.atom) AS child ON child.type = 'integer' GROUP BY root.atom ORDER BY root.atom",
            ['cfg' => [['roots' => '["$.a","$.b"]', 'doc' => '{"a":[2],"b":[1,3]}']]],
        );
        $t->same([['root_path' => '$.a', 'child_count' => 1], ['root_path' => '$.b', 'child_count' => 2]], $rows);
    },
    'json tree lateral root supports current root with count having' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT root.atom AS root_path, count(child.key) AS child_count FROM cfg JOIN json_each(cfg.roots) AS root ON root.type = 'text' JOIN json_each(cfg.doc, root.atom) AS child ON child.type = 'integer' GROUP BY root.atom HAVING count(child.key) > 1 ORDER BY root.atom",
            ['cfg' => [['roots' => '["$.a","$.b"]', 'doc' => '{"a":[2],"b":[1,3]}']]],
        );
        $t->same([['root_path' => '$.b', 'child_count' => 2]], $rows);
    },
    'json tree lateral root supports null host json as empty child source' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT child.key AS key_name FROM cfg JOIN json_each(cfg.doc, cfg.root) AS child ON child.type = 'integer'",
            ['cfg' => [['doc' => null, 'root' => '$.selected']]],
        );
        $t->same([], $rows);
    },
    'json tree lateral root supports malformed jsonb host as empty source' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT child.key AS key_name FROM cfg JOIN json_each(cfg.doc, cfg.root) AS child ON child.type = 'integer'",
            ['cfg' => [['doc' => new SQLiteBlobValue(hex2bin('1c00')), 'root' => '$.selected']]],
        );
        $t->same([], $rows);
    },
    'json tree lateral root rejects malformed current root path' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT child.key FROM cfg JOIN json_each(cfg.doc, cfg.root) AS child ON child.type = 'integer'",
            ['cfg' => [['doc' => '{"selected":[1]}', 'root' => '$.bad[']]],
        ));
    },
    'json tree lateral root rejects malformed dynamic json text' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT child.key FROM cfg JOIN json_each(cfg.doc, cfg.root) AS child ON child.type = 'integer'",
            ['cfg' => [['doc' => '{bad', 'root' => '$.selected']]],
        ));
    },
    'json tree lateral root plan records dynamic child source' => static function (TestRunner $t) use ($wpOptions): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority'",
            ['wp_options' => $wpOptions],
        );
        $t->same(2, count($plan['joins']));
        $t->true(is_callable($plan['joins'][1]['dynamicRows']));
    },
    'json tree lateral root dynamic plan evaluates against current merged row' => static function (TestRunner $t) use ($wpOptions): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority'",
            ['wp_options' => $wpOptions],
        );
        $firstJoinRows = ($plan['joins'][0]['dynamicRows'])($plan['from'][0]);
        $ruleRoot = array_values(array_filter($firstJoinRows, static fn (array $row): bool => ($row['g.key'] ?? null) === 'rules'))[0];
        $childRows = ($plan['joins'][1]['dynamicRows'])(array_merge($plan['from'][0], $ruleRoot));
        $t->same(['rules', 0, 'name', 'enabled', 'priority', 1, 'name', 'enabled', 'priority'], array_column($childRows, 'leaf.key'));
    },
    'json tree lateral root reports qualified child columns for left joins' => static function (TestRunner $t) use ($wpOptions): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT o.option_name AS option_name, leaf.key AS key_name FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.missing') AS leaf ON leaf.key = 'priority'",
            ['wp_options' => $wpOptions],
        );
        $t->true(in_array('leaf.key', $plan['joins'][0]['rightColumns'], true));
        $t->true(in_array('leaf.fullkey', $plan['joins'][0]['rightColumns'], true));
    },
    'json tree lateral root supports multiple host rows with current roots' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS id, count(leaf.atom) AS priorities FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' GROUP BY o.option_id ORDER BY id",
            ['wp_options' => $wpOptions],
        );
        $t->same([[ 'id' => 1, 'priorities' => 4 ], [ 'id' => 2, 'priorities' => 2 ], [ 'id' => 4, 'priorities' => 2 ]], $rows);
    },
    'json tree lateral root supports current root from joined metadata table' => static function (TestRunner $t) use ($wpOptions): void {
        $roots = [
            ['option_id' => 1, 'root' => '$.groups[1].rules'],
            ['option_id' => 2, 'root' => '$.groups[0].rules'],
        ];
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, leaf.atom AS priority FROM wp_options AS o JOIN roots AS r ON r.option_id = o.option_id JOIN json_tree(o.option_value, r.root) AS leaf ON leaf.key = 'priority' ORDER BY option_name, priority",
            ['wp_options' => $wpOptions, 'roots' => $roots],
        );
        $t->same([5, 9, 1, 4], array_column($rows, 'priority'));
    },
    'json tree lateral root supports joined current roots with explicit predicates' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' WHERE o.option_id = 1 ORDER BY priority",
            ['wp_options' => $wpOptions],
        );
        $t->same([2, 5, 7, 9], array_column($rows, 'priority'));
    },
    'json tree lateral root supports repeated joined current-root scans' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' WHERE o.option_id = 1 ORDER BY priority DESC",
            ['wp_options' => $wpOptions],
        );
        $t->same([9, 7, 5, 2], array_column($rows, 'priority'));
    },
    'json tree lateral root supports later ordinary join after current-root source' => static function (TestRunner $t) use ($wpOptions): void {
        $labels = [
            ['priority' => 9, 'label' => 'critical'],
            ['priority' => 7, 'label' => 'high'],
            ['priority' => 5, 'label' => 'medium'],
        ];
        $rows = SQLiteSelectSql::execute(
            "SELECT labels.label AS label FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' JOIN labels ON labels.priority = leaf.atom WHERE o.option_id = 1 ORDER BY leaf.atom DESC",
            ['wp_options' => $wpOptions, 'labels' => $labels],
        );
        $t->same(['critical', 'high', 'medium'], array_column($rows, 'label'));
    },
    'json tree lateral root supports json extract against current child fullkey' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_extract(o.option_value, r.fullkey || '.name') AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[1].rules' JOIN json_each(o.option_value, g.fullkey) AS r ON r.type = 'object' WHERE o.option_id = 1 ORDER BY rule_name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['object', 'page'], array_column($rows, 'rule_name'));
    },
    'json tree lateral root supports json type against current child fullkey' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_type(o.option_value, r.fullkey || '.enabled') AS enabled_type FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[0].rules' JOIN json_each(o.option_value, g.fullkey) AS r ON r.type = 'object' WHERE o.option_id = 1 ORDER BY enabled_type",
            ['wp_options' => $wpOptions],
        );
        $t->same(['integer', 'integer'], array_column($rows, 'enabled_type'));
    },
    'json tree lateral root can order by current root path' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT g.fullkey AS root_path FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey || '.rules') AS r ON r.type = 'object' WHERE o.option_id = 1 ORDER BY g.fullkey DESC, r.key",
            ['wp_options' => $wpOptions],
        );
        $t->same(['$.groups[1]', '$.groups[1]', '$.groups[0]', '$.groups[0]'], array_column($rows, 'root_path'));
    },
    'json tree lateral root rejects unsupported hidden root projection' => static function (TestRunner $t) use ($wpOptions): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT r.root AS root_path FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[0].rules' JOIN json_each(o.option_value, g.fullkey) AS r ON r.type = 'object' WHERE o.option_id = 1 ORDER BY r.key",
            ['wp_options' => $wpOptions],
        ));
    },
    'json tree lateral root rejects unsupported function names' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT key FROM cfg JOIN json_group_array(cfg.doc, cfg.root) AS child ON child.key = 0",
            ['cfg' => [['doc' => '[1]', 'root' => '$']]],
        ));
    },
    'json tree lateral root rejects too many arguments' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT key FROM cfg JOIN json_each(cfg.doc, cfg.root, '$.extra') AS child ON child.key = 0",
            ['cfg' => [['doc' => '[1]', 'root' => '$']]],
        ));
    },
    'json tree lateral root rejects malformed aliases' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            "SELECT key FROM cfg JOIN json_each(cfg.doc, cfg.root) AS 1bad ON key = 0",
            ['cfg' => [['doc' => '[1]', 'root' => '$']]],
        ));
    },
    'json tree lateral root exposes current dynamic rows in plan after joined source' => static function (TestRunner $t) use ($wpOptions): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority'",
            ['wp_options' => $wpOptions],
        );
        $t->true(is_callable($plan['joins'][0]['dynamicRows']));
        $t->true(is_callable($plan['joins'][1]['dynamicRows']));
    },
    'json tree lateral root preserves no-row result for filtered current parent' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'not_rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority'",
            ['wp_options' => $wpOptions],
        );
        $t->same([], $rows);
    },
    'json tree lateral root supports root path selected from current metadata join' => static function (TestRunner $t) use ($wpOptions): void {
        $roots = [
            ['root_name' => 'first_site', 'option_id' => 1, 'root' => '$.groups[0].rules'],
            ['root_name' => 'media', 'option_id' => 4, 'root' => '$.groups[0].rules'],
        ];
        $rows = SQLiteSelectSql::execute(
            "SELECT r.root_name AS root_name, max(leaf.atom) AS max_priority FROM roots AS r JOIN wp_options AS o ON o.option_id = r.option_id JOIN json_tree(o.option_value, r.root) AS leaf ON leaf.key = 'priority' GROUP BY r.root_name ORDER BY max_priority DESC",
            ['wp_options' => $wpOptions, 'roots' => $roots],
        );
        $t->same([['root_name' => 'first_site', 'max_priority' => 7], ['root_name' => 'media', 'max_priority' => 6]], $rows);
    },
    'json tree lateral root supports current atom root with left join fallback' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT root.atom AS root_path, child.key AS child_key FROM cfg JOIN json_each(cfg.roots) AS root ON root.type = 'text' LEFT JOIN json_each(cfg.doc, root.atom) AS child ON child.type = 'integer' ORDER BY root_path, child_key",
            ['cfg' => [['roots' => '["$.present","$.missing"]', 'doc' => '{"present":[5]}']]],
        );
        $t->same([['root_path' => '$.missing', 'child_key' => null], ['root_path' => '$.present', 'child_key' => 0]], $rows);
    },
    'json tree lateral root supports current root under selected jsonb row' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT f.atom AS name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS f ON f.key = 'name' WHERE o.option_name = 'jsonb_plugin_settings' ORDER BY name",
            ['wp_options' => $wpOptions],
        );
        $t->same(['images', 'video'], array_column($rows, 'name'));
    },
    'json tree lateral root supports current root for text leaf scalar' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT s.atom AS slug FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.type = 'object' JOIN json_each(o.option_value, g.fullkey) AS s ON s.key = 'slug' WHERE o.option_id = 4",
            ['wp_options' => $wpOptions],
        );
        $t->same(['media'], array_column($rows, 'slug'));
    },
    'json tree lateral root supports current fullkey prefix filtering' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT f.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS f ON f.key = 'priority' WHERE f.fullkey LIKE '$.groups[0]%' AND o.option_id = 1 ORDER BY priority",
            ['wp_options' => $wpOptions],
        );
        $t->same([2, 7], array_column($rows, 'priority'));
    },
    'json tree lateral root supports current-root values in projection labels' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT g.fullkey || ':' || leaf.atom AS label FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.fullkey = '$.groups[1].rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'name' WHERE o.option_id = 1 ORDER BY label",
            ['wp_options' => $wpOptions],
        );
        $t->same(['$.groups[1].rules:object', '$.groups[1].rules:page'], array_column($rows, 'label'));
    },
    'json tree lateral root supports current-root priority labels' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name || ':' || leaf.atom AS label FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' WHERE leaf.atom >= 7 ORDER BY label",
            ['wp_options' => $wpOptions],
        );
        $t->same(['site_plugin_settings:7', 'site_plugin_settings:9'], array_column($rows, 'label'));
    },
    'json tree lateral root supports current-root scalar lower predicate' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT leaf.atom AS name FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'name' WHERE lower(leaf.atom) = 'video'",
            ['wp_options' => $wpOptions],
        );
        $t->same(['video'], array_column($rows, 'name'));
    },
    'json tree lateral root supports current-root option id ordering' => static function (TestRunner $t) use ($wpOptions): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS id, leaf.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules' JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority' ORDER BY id DESC, priority DESC LIMIT 4",
            ['wp_options' => $wpOptions],
        );
        $t->same([4, 4, 2, 2], array_column($rows, 'id'));
    },
];

return $cases;
