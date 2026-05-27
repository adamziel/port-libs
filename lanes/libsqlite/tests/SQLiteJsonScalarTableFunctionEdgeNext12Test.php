<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$settings = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{plugin:{enabled:true,modes:["sync","cache",],ttl:300,flags:{network:true,beta:false}}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_empty',
        'option_value' => '{"plugin":{"enabled":false,"modes":[],"ttl":0,"flags":{}}}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_null',
        'option_value' => null,
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_jsonb',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'modes' => ['forms', 'seo'],
                'ttl' => 600,
                'flags' => ['network' => false],
            ],
        ])),
        'autoload' => 'yes',
    ],
];

$normalize = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => SQLiteJsonB::decode($value->bytes)];
    }

    return $value;
};

$cases = [
    'select dispatches json canonical scalar' => static fn (TestRunner $t) => $t->same(
        '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300,"flags":{"network":true,"beta":false}}}',
        SQLiteSelectSql::execute("SELECT json(option_value) AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches jsonb canonical scalar' => static fn (TestRunner $t) => $t->same(
        ['blob' => ['plugin' => ['enabled' => true, 'modes' => ['sync', 'cache'], 'ttl' => 300, 'flags' => ['network' => true, 'beta' => false]]]],
        $normalize(SQLiteSelectSql::execute("SELECT jsonb(option_value) AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc']),
    ),
    'select dispatches json_quote scalar' => static fn (TestRunner $t) => $t->same(
        ['"plugin_settings"', 'null', '{"plugin":{"enabled":true,"modes":["forms","seo"],"ttl":600,"flags":{"network":false}}}'],
        [
            SQLiteSelectSql::execute("SELECT json_quote(option_name) AS q FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['q'],
            SQLiteSelectSql::execute("SELECT json_quote(option_value) AS q FROM wp_options WHERE option_id = 3", ['wp_options' => $settings])[0]['q'],
            SQLiteSelectSql::execute("SELECT json_quote(option_value) AS q FROM wp_options WHERE option_id = 4", ['wp_options' => $settings])[0]['q'],
        ],
    ),
    'select dispatches json_valid strict and json5 flags' => static fn (TestRunner $t) => $t->same(
        [['strict_ok' => 0, 'json5_ok' => 1], ['strict_ok' => 1, 'json5_ok' => 1]],
        SQLiteSelectSql::execute("SELECT json_valid(option_value) AS strict_ok, json_valid(option_value, 2) AS json5_ok FROM wp_options WHERE option_id IN (1, 2) ORDER BY option_id", ['wp_options' => $settings]),
    ),
    'select dispatches json_array scalar with nested json subtype' => static fn (TestRunner $t) => $t->same(
        '["plugin_settings",1,300]',
        SQLiteSelectSql::execute("SELECT json_array(option_name, json_extract(option_value, '$.plugin.enabled'), json_extract(option_value, '$.plugin.ttl')) AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches jsonb_array scalar' => static fn (TestRunner $t) => $t->same(
        ['blob' => ['plugin_jsonb', 600]],
        $normalize(SQLiteSelectSql::execute("SELECT jsonb_array(option_name, json_extract(option_value, '$.plugin.ttl')) AS doc FROM wp_options WHERE option_id = 4", ['wp_options' => $settings])[0]['doc']),
    ),
    'select dispatches json_object scalar' => static fn (TestRunner $t) => $t->same(
        '{"name":"plugin_settings","enabled":1,"ttl":300}',
        SQLiteSelectSql::execute("SELECT json_object('name', option_name, 'enabled', json_extract(option_value, '$.plugin.enabled'), 'ttl', json_extract(option_value, '$.plugin.ttl')) AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches jsonb_object scalar' => static fn (TestRunner $t) => $t->same(
        ['blob' => ['name' => 'plugin_jsonb', 'ttl' => 600]],
        $normalize(SQLiteSelectSql::execute("SELECT jsonb_object('name', option_name, 'ttl', json_extract(option_value, '$.plugin.ttl')) AS doc FROM wp_options WHERE option_id = 4", ['wp_options' => $settings])[0]['doc']),
    ),
    'select dispatches json_pretty scalar' => static fn (TestRunner $t) => $t->same(
        "{\n \"plugin\": {\n  \"enabled\": false,\n  \"modes\": [],\n  \"ttl\": 0,\n  \"flags\": {}\n }\n}",
        SQLiteSelectSql::execute("SELECT json_pretty(option_value, ' ') AS doc FROM wp_options WHERE option_id = 2", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches json_patch scalar' => static fn (TestRunner $t) => $t->same(
        '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":450,"flags":{"network":true,"beta":false}}}',
        SQLiteSelectSql::execute("SELECT json_patch(json(option_value), '{\"plugin\":{\"ttl\":450}}') AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches jsonb_patch scalar' => static fn (TestRunner $t) => $t->same(
        ['blob' => ['plugin' => ['enabled' => true, 'modes' => ['forms', 'seo'], 'ttl' => 900, 'flags' => ['network' => false]]]],
        $normalize(SQLiteSelectSql::execute("SELECT jsonb_patch(option_value, '{\"plugin\":{\"ttl\":900}}') AS doc FROM wp_options WHERE option_id = 4", ['wp_options' => $settings])[0]['doc']),
    ),
    'select dispatches json_set scalar' => static fn (TestRunner $t) => $t->same(
        '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300,"flags":{"network":true,"beta":false},"source":"wp"}}',
        SQLiteSelectSql::execute("SELECT json_set(json(option_value), '$.plugin.source', 'wp') AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches json_insert scalar without replacing existing path' => static fn (TestRunner $t) => $t->same(
        '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":300,"flags":{"network":true,"beta":false},"source":"wp"}}',
        SQLiteSelectSql::execute("SELECT json_insert(json(option_value), '$.plugin.ttl', 999, '$.plugin.source', 'wp') AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches json_replace scalar without inserting missing path' => static fn (TestRunner $t) => $t->same(
        '{"plugin":{"enabled":true,"modes":["sync","cache"],"ttl":120,"flags":{"network":true,"beta":false}}}',
        SQLiteSelectSql::execute("SELECT json_replace(json(option_value), '$.plugin.ttl', 120, '$.plugin.source', 'wp') AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'select dispatches json_remove scalar' => static fn (TestRunner $t) => $t->same(
        '{"plugin":{"enabled":true,"modes":["sync","cache"],"flags":{"network":true,"beta":false}}}',
        SQLiteSelectSql::execute("SELECT json_remove(json(option_value), '$.plugin.ttl') AS doc FROM wp_options WHERE option_id = 1", ['wp_options' => $settings])[0]['doc'],
    ),
    'json_each accepts json scalar source expression' => static fn (TestRunner $t) => $t->same(
        ['enabled', 'flags', 'modes', 'ttl'],
        array_column(SQLiteSelectSql::execute("SELECT key FROM json_each(json('{plugin:{enabled:true,modes:[\"sync\",\"cache\",],ttl:300,flags:{network:true}}}'), '$.plugin') ORDER BY key", []), 'key'),
    ),
    'json_tree accepts jsonb scalar source expression' => static fn (TestRunner $t) => $t->same(
        [300],
        array_column(SQLiteSelectSql::execute("SELECT atom FROM json_tree(jsonb('{plugin:{ttl:300,modes:[\"sync\"]}}'), '$.plugin') WHERE key = 'ttl'", []), 'atom'),
    ),
    'json_each accepts json_array scalar source expression' => static fn (TestRunner $t) => $t->same(
        ['plugin_settings', 'yes'],
        array_column(SQLiteSelectSql::execute("SELECT j.atom AS atom FROM wp_options AS o JOIN json_each(json_array(o.option_name, o.autoload)) AS j ON j.atom IS NOT NULL WHERE o.option_id = 1 ORDER BY j.key", ['wp_options' => $settings]), 'atom'),
    ),
    'json_tree accepts json_object scalar source expression from host row' => static fn (TestRunner $t) => $t->same(
        [['key' => 'name', 'atom' => 'plugin_settings'], ['key' => 'ttl', 'atom' => 300]],
        SQLiteSelectSql::execute("SELECT j.key AS key, j.atom AS atom FROM wp_options AS o JOIN json_tree(json_object('name', o.option_name, 'ttl', json_extract(o.option_value, '$.plugin.ttl'))) AS j ON j.atom IS NOT NULL WHERE o.option_id = 1 ORDER BY j.key", ['wp_options' => $settings]),
    ),
    'json_each accepts json_patch source expression' => static fn (TestRunner $t) => $t->same(
        ['enabled', 'source'],
        array_column(SQLiteSelectSql::execute("SELECT key FROM json_each(json_patch('{\"enabled\":true}', '{\"source\":\"wp\"}')) ORDER BY key", []), 'key'),
    ),
    'json_each accepts json_set source expression from host row' => static fn (TestRunner $t) => $t->same(
        ['key' => 'source', 'atom' => 'wp'],
        SQLiteSelectSql::execute("SELECT key, atom FROM json_each(json_set('{\"source\":null}', '$.source', 'wp'))", [])[0],
    ),
    'json_each accepts json_remove source expression' => static fn (TestRunner $t) => $t->same(
        ['enabled'],
        array_column(SQLiteSelectSql::execute("SELECT key FROM json_each(json_remove('{\"enabled\":true,\"ttl\":300}', '$.ttl')) ORDER BY key", []), 'key'),
    ),
    'json_each accepts nested json_extract array source expression' => static fn (TestRunner $t) => $t->same(
        ['sync', 'cache'],
        array_column(SQLiteSelectSql::execute("SELECT j.atom AS atom FROM wp_options AS o JOIN json_each(json_extract(json(o.option_value), '$.plugin.modes')) AS j ON j.atom IS NOT NULL WHERE o.option_id = 1 ORDER BY j.key", ['wp_options' => $settings]), 'atom'),
    ),
    'json_tree source expression can be filtered by json_valid predicate' => static fn (TestRunner $t) => $t->same(
        ['plugin_settings'],
        array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_valid(option_value, 2) = 1 AND json_array_length(json_extract(json(option_value), '$.plugin.modes')) = 2 ORDER BY option_id", ['wp_options' => $settings]), 'option_name'),
    ),
    'json scalar source expression can be used in nested in subquery' => static fn (TestRunner $t) => $t->same(
        ['plugin_settings'],
        array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE option_name IN (SELECT atom FROM json_each(json_array('plugin_settings', 'plugin_extra'))) ORDER BY option_id", ['wp_options' => $settings]), 'option_name'),
    ),
    'json scalar table expression supports left join null extension' => static fn (TestRunner $t) => $t->same(
        [['option_name' => 'plugin_null', 'json_key' => null]],
        SQLiteSelectSql::execute("SELECT o.option_name AS option_name, j.key AS json_key FROM wp_options AS o LEFT JOIN json_each(json(o.option_value), '$.plugin') AS j ON j.key IS NOT NULL WHERE o.option_id = 3", ['wp_options' => $settings]),
    ),
    'json scalar table expression preserves aliases in projection' => static fn (TestRunner $t) => $t->same(
        [['option_key' => 'modes', 'mode_type' => 'array']],
        SQLiteSelectSql::execute("SELECT j.key AS option_key, j.type AS mode_type FROM wp_options AS o JOIN json_each(json(o.option_value), '$.plugin') AS j ON j.key = 'modes' WHERE o.option_id = 1", ['wp_options' => $settings]),
    ),
    'json scalar table expression preserves order limit offset' => static fn (TestRunner $t) => $t->same(
        ['flags', 'modes'],
        array_column(SQLiteSelectSql::execute("SELECT key FROM json_each(json('{plugin:{enabled:true,modes:[\"sync\",\"cache\"],ttl:300,flags:{network:true}}}'), '$.plugin') ORDER BY key LIMIT 2 OFFSET 1", []), 'key'),
    ),
    'json scalar table expression rejects invalid json_object labels' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_object(1, 'bad') AS doc FROM wp_options LIMIT 1", ['wp_options' => $settings]),
    ),
    'json scalar table expression rejects invalid json_valid flags' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_valid(option_value, 99) AS ok FROM wp_options WHERE option_id = 1", ['wp_options' => $settings]),
    ),
    'json scalar table expression rejects malformed dynamic source' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT key FROM json_each(json('{bad'))", []),
    ),
    'json scalar table expression rejects non-jsonb blob quote' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_quote(X'0102') AS q FROM wp_options LIMIT 1", ['wp_options' => $settings]),
    ),
];

return $cases;
