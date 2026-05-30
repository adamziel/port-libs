<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$users = [
    ['name' => 'Alice', 'phone' => '"704-555-1000"', 'phoneb' => new SQLiteBlobValue(SQLiteJsonB::encode('704-555-1000'))],
    ['name' => 'Bob', 'phone' => '"919-555-2000"', 'phoneb' => new SQLiteBlobValue(SQLiteJsonB::encode('919-555-2000'))],
    ['name' => 'Cindy', 'phone' => '["336-555-1234","704-555-9999"]', 'phoneb' => new SQLiteBlobValue(SQLiteJsonB::encode(['336-555-1234', '704-555-9999']))],
    ['name' => 'Dave', 'phone' => '["336-555-8421","704-555-4321","803-911-4421"]', 'phoneb' => new SQLiteBlobValue(SQLiteJsonB::encode(['336-555-8421', '704-555-4321', '803-911-4421']))],
    ['name' => 'Eve', 'phone' => null, 'phoneb' => null],
];

$settings = [
    ['option_id' => 1, 'option_name' => 'site_plugin_settings', 'autoload' => 'yes', 'option_value' => '{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}],"flags":["network","beta"]}'],
    ['option_id' => 2, 'option_name' => 'theme_plugin_settings', 'autoload' => 'yes', 'option_value' => '{"rules":[{"name":"forms","priority":4},{"name":"media","priority":1}],"flags":["theme"]}'],
    ['option_id' => 3, 'option_name' => 'broken_settings', 'autoload' => 'no', 'option_value' => null],
];

$tests = [];

$tests['executes upstream json_each comma join over indexed phone rows'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT DISTINCT user.name AS name FROM user, json_each(user.phone) WHERE json_each.value LIKE '704-%' ORDER BY name",
        ['user' => $users],
    );
    $t->same(['Alice', 'Cindy', 'Dave'], array_column($rows, 'name'));
};

$tests['executes upstream json_each comma join over jsonb phone rows'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT DISTINCT user.name AS name FROM user, json_each(user.phoneb) WHERE json_each.value LIKE '704-%' ORDER BY name",
        ['user' => $users],
    );
    $t->same(['Alice', 'Cindy', 'Dave'], array_column($rows, 'name'));
};

$tests['plans comma json_each source as cross join with dynamic rows'] = static function (TestRunner $t) use ($users): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT user.name AS name, json_each.key AS phone_index FROM user, json_each(user.phone) WHERE json_each.value LIKE '704-%' ORDER BY name",
        ['user' => $users],
    );
    $t->same(['from', 'select', 'joins', 'where', 'orderBy'], array_keys($plan));
    $t->same(1, count($plan['joins']));
    $t->same('CROSS', $plan['joins'][0]['type']);
    $t->true(is_callable($plan['joins'][0]['dynamicRows']));
    $dynamicRows = ($plan['joins'][0]['dynamicRows'])($plan['from'][2]);
    $t->same([0, 1], array_column($dynamicRows, 'json_each.key'));
};

$tests['comma json_each join preserves qualified projection columns'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT user.name AS name, json_each.key AS phone_index, json_each.value AS phone FROM user, json_each(user.phone) WHERE json_each.value LIKE '704-%' ORDER BY name, phone_index",
        ['user' => $users],
    );
    $t->same(['Alice', 'Cindy', 'Dave'], array_column($rows, 'name'));
    $t->same([null, 1, 1], array_column($rows, 'phone_index'));
    $t->same(['704-555-1000', '704-555-9999', '704-555-4321'], array_column($rows, 'phone'));
};

$tests['comma json_each join supports alias qualified predicates'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT u.name AS name, p.value AS phone FROM user AS u, json_each(u.phone) AS p WHERE p.value GLOB '704-*' ORDER BY name",
        ['user' => $users],
    );
    $t->same(['Alice', 'Cindy', 'Dave'], array_column($rows, 'name'));
};

$tests['comma json_each join supports alias table star projection'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT p.* FROM user AS u, json_each(u.phone) AS p WHERE u.name = 'Cindy' ORDER BY key",
        ['user' => $users],
    );
    $t->same([0, 1], array_column($rows, 'key'));
    $t->same(['336-555-1234', '704-555-9999'], array_column($rows, 'value'));
};

$tests['comma json_each join preserves null json as empty dynamic rowset'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT user.name AS name, json_each.value AS phone FROM user, json_each(user.phone) WHERE user.name = 'Eve'",
        ['user' => $users],
    );
    $t->same([], $rows);
};

$tests['comma json_each join supports chained ordinary joins'] = static function (TestRunner $t) use ($users): void {
    $regions = [
        ['prefix' => '704', 'region' => 'Charlotte'],
        ['prefix' => '336', 'region' => 'Triad'],
    ];
    $rows = SQLiteSelectSql::execute(
        "SELECT user.name AS name, r.region AS region FROM user, json_each(user.phone), regions AS r WHERE substr(json_each.value, 1, 3) = r.prefix AND r.region = 'Charlotte' ORDER BY name",
        ['user' => $users, 'regions' => $regions],
    );
    $t->same(['Alice', 'Cindy', 'Dave'], array_column($rows, 'name'));
};

$tests['comma json_each join composes with group by aggregate'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT user.name AS name, count(json_each.value) AS phones FROM user, json_each(user.phone) GROUP BY user.name HAVING count(json_each.value) >= 2 ORDER BY phones DESC, name",
        ['user' => $users],
    );
    $t->same(['Dave', 'Cindy'], array_column($rows, 'name'));
    $t->same([3, 2], array_column($rows, 'phones'));
};

$tests['comma json_each join composes with distinct result rows'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT DISTINCT substr(json_each.value, 1, 3) AS area FROM user, json_each(user.phone) WHERE json_each.value LIKE '%-%' ORDER BY area",
        ['user' => $users],
    );
    $t->same(['336', '704', '803', '919'], array_column($rows, 'area'));
};

$tests['comma json_each join supports comma limit after ordering'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT user.name AS name, json_each.value AS phone FROM user, json_each(user.phone) WHERE json_each.value LIKE '%-%' ORDER BY name, phone LIMIT 1, 3",
        ['user' => $users],
    );
    $t->same(['Bob', 'Cindy', 'Cindy'], array_column($rows, 'name'));
};

$tests['comma json_each join supports cte materialization'] = static function (TestRunner $t) use ($users): void {
    $rows = SQLiteSelectSql::execute(
        "WITH matched AS (SELECT user.name AS name, json_each.value AS phone FROM user, json_each(user.phone) WHERE json_each.value LIKE '704-%') SELECT name FROM matched ORDER BY name DESC",
        ['user' => $users],
    );
    $t->same(['Dave', 'Cindy', 'Alice'], array_column($rows, 'name'));
};

$tests['comma json_tree join walks wordpress option rules dynamically'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT wp_options.option_name AS option_name, json_tree.atom AS priority FROM wp_options, json_tree(wp_options.option_value, '$.rules') WHERE json_tree.key = 'priority' ORDER BY priority DESC",
        ['wp_options' => $settings],
    );
    $t->same(['site_plugin_settings', 'theme_plugin_settings', 'site_plugin_settings', 'theme_plugin_settings'], array_column($rows, 'option_name'));
    $t->same([7, 4, 2, 1], array_column($rows, 'priority'));
};

$tests['comma json_tree join supports jsonb dynamic option values'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT wp.option_name AS option_name, jt.atom AS priority FROM wp, json_tree(wp.option_value, '$.rules') AS jt WHERE jt.key = 'priority' ORDER BY priority",
        ['wp' => [
            ['option_name' => 'jsonb_settings', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['rules' => [['priority' => 3], ['priority' => 8]]]))],
        ]],
    );
    $t->same([3, 8], array_column($rows, 'priority'));
};

$tests['comma json_tree join supports root path from left row expression'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, jt.atom AS value FROM cfg, json_tree(cfg.payload, cfg.root) AS jt WHERE jt.type = 'integer' ORDER BY value DESC",
        ['cfg' => [['name' => 'runtime', 'payload' => '{"flags":{"a":1,"b":2}}', 'root' => '$.flags']]],
    );
    $t->same(['runtime', 'runtime'], array_column($rows, 'name'));
    $t->same([2, 1], array_column($rows, 'value'));
};

$tests['comma json_tree join raises malformed dynamic root paths'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT jt.key FROM cfg, json_tree(cfg.payload, cfg.root) AS jt",
        ['cfg' => [['payload' => '{"a":1}', 'root' => '$.bad[']]],
    ));
};

$tests['comma json_tree join raises malformed dynamic json text'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT jt.key FROM cfg, json_tree(cfg.payload) AS jt",
        ['cfg' => [['payload' => '{bad']]],
    ));
};

$tests['comma json_each join treats malformed jsonb dynamic rows as empty'] = static function (TestRunner $t): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, je.key AS key FROM cfg, json_each(cfg.payload) AS je",
        ['cfg' => [['name' => 'broken', 'payload' => new SQLiteBlobValue(hex2bin('1c00'))]]],
    );
    $t->same([], $rows);
};

$tests['comma json_each join supports multiple comma sources'] = static function (TestRunner $t) use ($users): void {
    $tags = [
        ['tag' => 'mobile'],
        ['tag' => 'support'],
    ];
    $rows = SQLiteSelectSql::execute(
        "SELECT user.name AS name, json_each.value AS phone, tags.tag AS tag FROM user, json_each(user.phone), tags WHERE user.name = 'Alice' ORDER BY tag",
        ['user' => $users, 'tags' => $tags],
    );
    $t->same(['Alice', 'Alice'], array_column($rows, 'name'));
    $t->same(['mobile', 'support'], array_column($rows, 'tag'));
};

$tests['comma json_each join can appear before later dynamic json source'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT wp_options.option_name AS option_name, flags.atom AS flag, rules.atom AS priority FROM wp_options, json_each(wp_options.option_value, '$.flags') AS flags, json_tree(wp_options.option_value, '$.rules') AS rules WHERE rules.key = 'priority' AND flags.atom = 'network' ORDER BY priority",
        ['wp_options' => $settings],
    );
    $t->same(['site_plugin_settings', 'site_plugin_settings'], array_column($rows, 'option_name'));
    $t->same([2, 7], array_column($rows, 'priority'));
};

$tests['comma json_each join supports left join after comma source'] = static function (TestRunner $t) use ($settings): void {
    $labels = [
        ['flag' => 'network', 'label' => 'Network enabled'],
        ['flag' => 'theme', 'label' => 'Theme local'],
    ];
    $rows = SQLiteSelectSql::execute(
        "SELECT wp_options.option_name AS option_name, flags.atom AS flag, labels.label AS label FROM wp_options, json_each(wp_options.option_value, '$.flags') AS flags, labels WHERE flags.atom = labels.flag ORDER BY option_name, flag",
        ['wp_options' => $settings, 'labels' => $labels],
    );
    $t->same(['site_plugin_settings', 'theme_plugin_settings'], array_column($rows, 'option_name'));
    $t->same(['network', 'theme'], array_column($rows, 'flag'));
    $t->same(['Network enabled', 'Theme local'], array_column($rows, 'label'));
};

$tests['comma json_each join preserves where predicates on both sides'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT wp_options.option_name AS option_name, flags.atom AS flag FROM wp_options, json_each(wp_options.option_value, '$.flags') AS flags WHERE wp_options.autoload = 'yes' AND flags.atom NOT IN ('beta') ORDER BY flag DESC",
        ['wp_options' => $settings],
    );
    $t->same(['theme_plugin_settings', 'site_plugin_settings'], array_column($rows, 'option_name'));
    $t->same(['theme', 'network'], array_column($rows, 'flag'));
};

$tests['comma json_tree join supports expression order by over dynamic rows'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT wp_options.option_name AS option_name, json_tree.atom AS priority FROM wp_options, json_tree(wp_options.option_value, '$.rules') WHERE json_tree.key = 'priority' ORDER BY (json_tree.atom + wp_options.option_id) DESC LIMIT 2",
        ['wp_options' => $settings],
    );
    $t->same(['site_plugin_settings', 'theme_plugin_settings'], array_column($rows, 'option_name'));
    $t->same([7, 4], array_column($rows, 'priority'));
};

$tests['comma json_each join supports constant json table after ordinary row source'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT wp_options.option_name AS option_name, constants.value AS marker FROM wp_options, json_each('[\"audit\"]') AS constants WHERE wp_options.option_id = 1",
        ['wp_options' => $settings],
    );
    $t->same([['option_name' => 'site_plugin_settings', 'marker' => 'audit']], $rows);
};

$tests['comma json_each join supports ordinary source after constant json table'] = static function (TestRunner $t) use ($settings): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT constants.value AS marker, wp_options.option_name AS option_name FROM json_each('[\"audit\"]') AS constants, wp_options WHERE wp_options.option_id = 2",
        ['wp_options' => $settings],
    );
    $t->same([['marker' => 'audit', 'option_name' => 'theme_plugin_settings']], $rows);
};

$tests['comma json_each join rejects empty source terms'] = static function (TestRunner $t) use ($users): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT user.name FROM user, , json_each(user.phone)",
        ['user' => $users],
    ));
};

return $tests;
