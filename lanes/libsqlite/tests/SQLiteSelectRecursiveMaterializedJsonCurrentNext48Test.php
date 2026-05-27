<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => '{"next":[2,3],"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false}]}'],
    ['option_id' => 2, 'option_name' => 'plugin_beta_settings', 'autoload' => 'yes', 'option_value' => '{"next":[4],"rules":[{"name":"forms","priority":4,"enabled":true},{"name":"media","priority":1,"enabled":false}]}'],
    ['option_id' => 3, 'option_name' => 'plugin_gamma_settings', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"name":"gallery","priority":5,"enabled":true},{"name":"seo","priority":3,"enabled":true}]}'],
    ['option_id' => 4, 'option_name' => 'plugin_delta_settings', 'autoload' => 'no', 'option_value' => '{"next":[5],"rules":[{"name":"cache","priority":9,"enabled":true},{"name":"forms","priority":6,"enabled":false}]}'],
    ['option_id' => 5, 'option_name' => 'plugin_epsilon_settings', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"name":"media","priority":8,"enabled":true},{"name":"seo","priority":10,"enabled":false}]}'],
    ['option_id' => 6, 'option_name' => 'plugin_orphan_settings', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"name":"orphan","priority":1,"enabled":false}]}'],
];

$tables = ['wp_options' => $options];
$sql = "WITH RECURSIVE wanted(option_id, depth, source) AS MATERIALIZED (
            VALUES (1, 0, 'seed')
            UNION ALL
            SELECT CAST(next.atom AS INTEGER), wanted.depth + 1, o.option_name
              FROM wanted
              JOIN wp_options AS o ON o.option_id = wanted.option_id
              JOIN json_each(o.option_value, '$.next') AS next ON next.type = 'integer'
             WHERE wanted.depth < 4
        )
        SELECT wanted.option_id AS option_id,
               wanted.depth AS depth,
               wanted.source AS source,
               o.option_name AS option_name,
               o.autoload AS autoload,
               jt.key AS attr,
               jt.atom AS atom,
               jt.type AS json_type,
               jt.fullkey AS fullkey,
               jt.path AS json_path
          FROM wanted
          JOIN wp_options AS o ON o.option_id = wanted.option_id
          JOIN json_tree(o.option_value, '$.rules') AS jt ON jt.type IN ('text', 'integer', 'true', 'false')
         ORDER BY wanted.depth, wanted.option_id, jt.fullkey";

$plan = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_name', 'attr'], ['fullkey']);
$pairs = static fn (string $name, string $attr): array => SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_name' => $name, 'attr' => $attr]);
$select = static fn (string $selectSql, string $column): array => array_column(SQLiteSelectSql::execute($selectSql, $tables), $column);

$tests = [
    'materializes recursive json rows for reachable options only' => static function (TestRunner $t) use ($plan): void {
        $t->same(30, count($plan()['rows']));
    },
    'omits orphan option outside recursive current source' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('plugin_orphan_settings', array_column($plan()['rows'], 'option_name'), true));
    },
    'records composite index columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['option_name', 'attr'], $plan()['indexColumns']);
    },
    'records json fullkey order column' => static function (TestRunner $t) use ($plan): void {
        $t->same(['fullkey'], $plan()['orderColumns']);
    },
    'creates one index key per reachable option attribute' => static function (TestRunner $t) use ($plan): void {
        $t->same(15, count($plan()['indexes']));
    },
    'keeps one typed key per materialized row' => static function (TestRunner $t) use ($plan): void {
        $t->same(30, count($plan()['keys']));
    },
    'exposes every row through current next pairs' => static function (TestRunner $t) use ($plan): void {
        $t->same(30, count($plan()['currentNext']));
    },
    'traces recursive current rows' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3, 4, 5], array_column($plan()['trace']['rows'], 'option_id'));
    },
    'trace records accepted next rows from json each' => static function (TestRunner $t) use ($plan): void {
        $t->same([2, 3], array_column($plan()['trace']['trace'][0]['accepted_next'], 'option_id'));
    },
    'trace keeps terminal current row without accepted next rows' => static function (TestRunner $t) use ($plan): void {
        $trace = $plan()['trace']['trace'];
        $t->same([], $trace[count($trace) - 1]['accepted_next']);
    },
    'records recursive json materialization dependencies' => static function (TestRunner $t) use ($plan): void {
        $t->true(in_array('sqlite-recursive-json-table-yield', $plan()['dependencies'], true));
    },
    'alpha name current next follows rules array order' => static function (TestRunner $t) use ($pairs): void {
        $t->same(['seo', 'cache'], [$pairs('plugin_alpha_settings', 'name')[0]['current']['atom'], $pairs('plugin_alpha_settings', 'name')[0]['next']['atom']]);
    },
    'alpha priority current next follows fullkey order' => static function (TestRunner $t) use ($pairs): void {
        $t->same([2, 7], [$pairs('plugin_alpha_settings', 'priority')[0]['current']['atom'], $pairs('plugin_alpha_settings', 'priority')[0]['next']['atom']]);
    },
    'beta enabled current next exposes sqlite booleans' => static function (TestRunner $t) use ($pairs): void {
        $t->same([1, 0], [$pairs('plugin_beta_settings', 'enabled')[0]['current']['atom'], $pairs('plugin_beta_settings', 'enabled')[0]['next']['atom']]);
    },
    'epsilon priority terminal row has no next' => static function (TestRunner $t) use ($pairs): void {
        $t->same(null, $pairs('plugin_epsilon_settings', 'priority')[1]['next']);
    },
    'current next lookup missing recursive option is empty' => static function (TestRunner $t) use ($pairs): void {
        $t->same([], $pairs('plugin_orphan_settings', 'name'));
    },
    'derived recursive sql can be filtered by attr' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name, atom FROM ({$sql}) AS derived WHERE attr = 'name' ORDER BY option_id, fullkey", $tables);
        $t->same(['seo', 'cache', 'forms', 'media', 'gallery', 'seo', 'cache', 'forms', 'media', 'seo'], array_column($rows, 'atom'));
    },
    'derived recursive sql can group reachable attributes' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT attr, count(atom) AS total FROM ({$sql}) AS derived GROUP BY attr ORDER BY attr", $tables);
        $t->same([10, 10, 10], array_column($rows, 'total'));
    },
    'recursive materialized source feeds json path filter' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name FROM ({$sql}) AS derived WHERE attr = 'priority' AND atom >= 8 ORDER BY atom", $tables);
        $t->same(['plugin_epsilon_settings', 'plugin_delta_settings', 'plugin_epsilon_settings'], array_column($rows, 'option_name'));
    },
    'recursive materialized source can be limited after json expansion' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT atom FROM ({$sql}) AS derived WHERE attr = 'name' ORDER BY depth, option_id, fullkey LIMIT 4", $tables);
        $t->same(['seo', 'cache', 'forms', 'media'], array_column($rows, 'atom'));
    },
    'recursive materialized source can use comma limit' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT atom FROM ({$sql}) AS derived WHERE attr = 'name' ORDER BY depth, option_id, fullkey LIMIT 2, 3", $tables);
        $t->same(['forms', 'media', 'gallery'], array_column($rows, 'atom'));
    },
    'rejects non recursive sql' => static function (TestRunner $t) use ($tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::materialize('SELECT 1 AS id', $tables, ['id']));
    },
    'rejects recursive sql without json source' => static function (TestRunner $t) use ($tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::materialize('WITH RECURSIVE r(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM r WHERE id < 2) SELECT id FROM r', $tables, ['id']));
    },
    'rejects empty index columns through derived index' => static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, []));
    },
    'rejects missing lookup key' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_name' => 'plugin_alpha_settings']));
    },
];

foreach (['plugin_alpha_settings' => ['seo', 'cache'], 'plugin_beta_settings' => ['forms', 'media'], 'plugin_gamma_settings' => ['gallery', 'seo'], 'plugin_delta_settings' => ['cache', 'forms'], 'plugin_epsilon_settings' => ['media', 'seo']] as $name => $expected) {
    $tests['recursive materialized json current next48 name lookup ' . $name] = static function (TestRunner $t) use ($pairs, $name, $expected): void {
        $t->same($expected, array_column(array_map(static fn (array $pair): array => $pair['current'], $pairs($name, 'name')), 'atom'));
    };
    $tests['recursive materialized json current next48 priority pair count ' . $name] = static function (TestRunner $t) use ($pairs, $name): void {
        $t->same(2, count($pairs($name, 'priority')));
    };
    $tests['recursive materialized json current next48 enabled pair count ' . $name] = static function (TestRunner $t) use ($pairs, $name): void {
        $t->same(2, count($pairs($name, 'enabled')));
    };
}

foreach (range(1, 20) as $limit) {
    $tests['recursive materialized json current next48 generated limit ' . $limit] = static function (TestRunner $t) use ($select, $limit, $sql): void {
        $rows = $select("SELECT atom FROM ({$sql}) AS derived WHERE attr = 'name' ORDER BY depth, option_id, fullkey LIMIT {$limit}", 'atom');
        $t->same(min($limit, 10), count($rows));
    };
}

return $tests;
