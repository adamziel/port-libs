<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}],"flags":["network","beta"],"meta":{"scope":"site"}}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"forms","priority":4},{"name":"media","priority":1}],"flags":["theme"],"meta":{"scope":"theme"}}',
    ],
    ['option_id' => 3, 'option_name' => 'broken_plugin_settings', 'autoload' => 'no', 'option_value' => null],
    ['option_id' => 4, 'option_name' => 'empty_plugin_settings', 'autoload' => 'yes', 'option_value' => '{"rules":[],"flags":[],"meta":{"scope":"empty"}}'],
];

$queries = [
    'comma json_tree priority count' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.rules') AS j WHERE j.key = 'priority' ORDER BY priority DESC", 'count', 4],
    'comma json_tree priority order' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.rules') AS j WHERE j.key = 'priority' ORDER BY priority DESC", 'column.priority', [7, 4, 2, 1]],
    'comma json_tree priority source rows' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o, json_tree(o.option_value, '$.rules') AS j WHERE j.key = 'priority' ORDER BY priority DESC", 'column.option_name', ['site_plugin_settings', 'theme_plugin_settings', 'site_plugin_settings', 'theme_plugin_settings']],
    'cross json_each flags count' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o CROSS JOIN json_each(o.option_value, '$.flags') AS f WHERE o.autoload = 'yes' ORDER BY option_name, flag", 'count', 3],
    'cross json_each flags order' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o CROSS JOIN json_each(o.option_value, '$.flags') AS f WHERE o.autoload = 'yes' ORDER BY option_name, flag", 'column.flag', ['beta', 'network', 'theme']],
    'cross json_each flags option order' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o CROSS JOIN json_each(o.option_value, '$.flags') AS f WHERE o.autoload = 'yes' ORDER BY option_name, flag", 'column.option_name', ['site_plugin_settings', 'site_plugin_settings', 'theme_plugin_settings']],
    'inner join json_tree object names' => ["SELECT o.option_name AS option_name, r.atom AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS r ON r.key = 'name' WHERE r.atom LIKE '%e%' ORDER BY option_name, rule_name", 'column.rule_name', ['cache', 'seo', 'media']],
    'inner join json_tree object name sources' => ["SELECT o.option_name AS option_name, r.atom AS rule_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS r ON r.key = 'name' WHERE r.atom LIKE '%e%' ORDER BY option_name, rule_name", 'column.option_name', ['site_plugin_settings', 'site_plugin_settings', 'theme_plugin_settings']],
    'left join null-extends null JSON source' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.key = 0 WHERE o.option_id = 3", 'rows', [['option_name' => 'broken_plugin_settings', 'flag' => null]]],
    'left join null-extends empty JSON array' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.key = 0 WHERE o.option_id = 4", 'rows', [['option_name' => 'empty_plugin_settings', 'flag' => null]]],
    'left join matches first flag' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.key = 0 WHERE o.option_id IN (1,2) ORDER BY option_name", 'rows', [['option_name' => 'site_plugin_settings', 'flag' => 'network'], ['option_name' => 'theme_plugin_settings', 'flag' => 'theme']]],
    'alias-qualified fullkey per current row' => ["SELECT o.option_name AS option_name, jt.fullkey AS fullkey FROM wp_options AS o JOIN json_tree(o.option_value, '$.meta') AS jt ON jt.key = 'scope' ORDER BY option_name", 'column.fullkey', ['$.meta.scope', '$.meta.scope', '$.meta.scope']],
    'alias-qualified atom per current row' => ["SELECT o.option_name AS option_name, jt.atom AS scope FROM wp_options AS o JOIN json_tree(o.option_value, '$.meta') AS jt ON jt.key = 'scope' ORDER BY option_name", 'column.scope', ['empty', 'site', 'theme']],
    'dynamic root expression returns empty rowset' => ["SELECT o.option_name AS option_name, e.atom AS value FROM wp_options AS o JOIN json_each(o.option_value, '$.' || o.autoload) AS e ON e.key = 0 ORDER BY option_name", 'rows', []],
    'current row path filters integer atoms' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.type = 'integer' WHERE j.atom >= 4 ORDER BY priority DESC", 'rows', [['option_name' => 'site_plugin_settings', 'priority' => 7], ['option_name' => 'theme_plugin_settings', 'priority' => 4]]],
    'current row parent ids are row local' => ["SELECT o.option_name AS option_name, j.parent AS parent_id FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' WHERE j.atom = 7", 'column.parent_id', [4]],
    'json_each rowids restart per host row' => ["SELECT o.option_name AS option_name, f.key AS flag_key FROM wp_options AS o JOIN json_each(o.option_value, '$.flags') AS f ON f.type = 'text' ORDER BY option_name, flag_key", 'column.flag_key', [0, 1, 0]],
    'rowid alias projects through joined JSON alias' => ["SELECT o.option_name AS option_name, f.rowid AS json_rowid FROM wp_options AS o JOIN json_each(o.option_value, '$.flags') AS f ON f.atom = 'theme'", 'rows', [['option_name' => 'theme_plugin_settings', 'json_rowid' => 1]]],
    'comma source feeds grouped aggregate' => ["SELECT o.autoload AS autoload, count(*) AS rows, max(j.atom) AS max_priority FROM wp_options AS o, json_tree(o.option_value, '$.rules') AS j WHERE j.key = 'priority' GROUP BY o.autoload ORDER BY autoload", 'rows', [['autoload' => 'yes', 'rows' => 4, 'max_priority' => 7]]],
    'current JSON join feeds distinct source names' => ["SELECT DISTINCT o.option_name AS option_name FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' ORDER BY option_name", 'column.option_name', ['site_plugin_settings', 'theme_plugin_settings']],
    'current JSON join supports limit offset' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' ORDER BY priority DESC LIMIT 1 OFFSET 1", 'rows', [['option_name' => 'theme_plugin_settings', 'priority' => 4]]],
    'current JSON join supports comma limit form' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' ORDER BY priority DESC LIMIT 2, 1", 'rows', [['option_name' => 'site_plugin_settings', 'priority' => 2]]],
    'current JSON join keeps projection key order' => ["SELECT o.option_id AS id, o.option_name AS name, j.key AS json_key, j.atom AS json_atom FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'name' ORDER BY id, json_atom LIMIT 1", 'keys.0', ['id', 'name', 'json_key', 'json_atom']],
    'current JSON join filters host after expansion' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o JOIN json_each(o.option_value, '$.flags') AS f ON f.type = 'text' WHERE o.option_name LIKE 'site_%' ORDER BY flag", 'column.flag', ['beta', 'network']],
    'current JSON join filters JSON after host predicate' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o JOIN json_each(o.option_value, '$.flags') AS f ON f.type = 'text' WHERE f.atom GLOB 't*' ORDER BY option_name", 'rows', [['option_name' => 'theme_plugin_settings', 'flag' => 'theme']]],
    'current JSON join supports no-match inner rowset' => ["SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' WHERE o.option_name = 'broken_plugin_settings'", 'rows', []],
    'current JSON join supports host expression argument' => ["SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o JOIN json_each(o.option_value || '', '$.flags') AS f ON f.key = 0 ORDER BY option_name", 'rows', [['option_name' => 'site_plugin_settings', 'flag' => 'network'], ['option_name' => 'theme_plugin_settings', 'flag' => 'theme']]],
];

$valueAt = static function (array $rows, string $path): mixed {
    if ($path === 'count') {
        return count($rows);
    }
    if ($path === 'rows') {
        return $rows;
    }
    if (str_starts_with($path, 'column.')) {
        return array_column($rows, substr($path, 7));
    }
    if (str_starts_with($path, 'keys.')) {
        return array_keys($rows[(int) substr($path, 5)]);
    }

    throw new InvalidArgumentException("Unknown result path {$path}");
};

$tests = [];
foreach ($queries as $name => [$sql, $path, $expected]) {
    $tests['sqlite json table join source current next16 ' . $name] = static function (TestRunner $t) use ($sql, $path, $expected, $options, $valueAt): void {
        $t->same($expected, $valueAt(SQLiteSelectSql::execute($sql, ['wp_options' => $options]), $path));
    };
}

$tests['sqlite json table join source current next16 rejects non-text dynamic root'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT o.option_name AS option_name, j.key AS json_key FROM wp_options AS o JOIN json_tree(o.option_value, o.option_id) AS j ON j.key = 'priority'",
        ['wp_options' => $options],
    ));
};

$tests['sqlite json table join source current next16 rejects malformed dynamic JSON'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT o.option_name AS option_name, j.key AS json_key FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority'",
        ['wp_options' => [['option_name' => 'bad', 'option_value' => '{"rules":[']]],
    ));
};

$tests['sqlite json table join source current next16 plan advertises dynamic right columns'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority'",
        ['wp_options' => $options],
    );
    $t->same(['j.key', 'j.value', 'j.type', 'j.atom', 'j.id', 'j.parent', 'j.fullkey', 'j.path', 'j.rowid', 'j._rowid_', 'j.oid'], $plan['joins'][0]['rightColumns']);
};

$tests['sqlite json table join source current next16 plan callback uses supplied current row'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT o.option_name AS option_name, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority'",
        ['wp_options' => $options],
    );
    $rows = ($plan['joins'][0]['dynamicRows'])($plan['from'][0]);
    $t->same([null, null, 'seo', 2, null, 'cache', 7], array_column($rows, 'j.atom'));
};

return $tests;
