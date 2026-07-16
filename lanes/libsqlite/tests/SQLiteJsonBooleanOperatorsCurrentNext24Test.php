<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugin":{"enabled":true,"indexed":true,"network":false,"priority":7,"ttl":3600,"flag_text":"1ready","zero_text":"0disabled","empty_text":"","null_flag":null,"label":"cache"}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => false,
                'indexed' => true,
                'network' => true,
                'priority' => 3,
                'ttl' => 0,
                'flag_text' => '2ready',
                'zero_text' => '0',
                'empty_text' => '',
                'null_flag' => null,
                'label' => 'forms',
            ],
        ])),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"plugin":{"enabled":false,"indexed":false,"network":false,"priority":0,"ttl":0,"flag_text":"no","zero_text":"0disabled","empty_text":"","null_flag":null,"label":"empty"}}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_legacy_settings',
        'option_value' => '{"plugin":{"enabled":true,"indexed":false,"network":true,"priority":-1,"ttl":-5,"flag_text":"-2legacy","zero_text":"00legacy","empty_text":"0","null_flag":null,"label":"legacy"}}',
        'autoload' => 'yes',
    ],
];

$select = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
$column = static fn (string $sql, string $column): array => array_column($select($sql), $column);

$cases = [
    'bare json boolean true filters rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' ORDER BY option_id", ['plugin_cache_settings', 'plugin_legacy_settings']],
    'not bare json boolean filters false rows' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.enabled' ORDER BY option_id", ['plugin_forms_settings', 'plugin_empty_settings']],
    'parenthesized not json boolean filters false rows' => ["SELECT option_name FROM wp_options WHERE NOT (option_value ->> '$.plugin.enabled') ORDER BY option_id", ['plugin_forms_settings', 'plugin_empty_settings']],
    'json boolean and true json boolean keeps cache only' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.indexed' ORDER BY option_id", ['plugin_cache_settings']],
    'json boolean and false operand removes all rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.missing' ORDER BY option_id", []],
    'json boolean or combines true branches' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' OR option_value ->> '$.plugin.network' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_legacy_settings']],
    'json boolean or with null branch keeps true rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.missing' OR option_value ->> '$.plugin.network' ORDER BY option_id", ['plugin_forms_settings', 'plugin_legacy_settings']],
    'json boolean and not network keeps enabled local rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND NOT option_value ->> '$.plugin.network' ORDER BY option_id", ['plugin_cache_settings']],
    'json boolean or not indexed keeps indexed or legacy empty rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.indexed' OR NOT option_value ->> '$.plugin.enabled' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings']],
    'json boolean precedence keeps sqlite and before or' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.network' OR option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.indexed' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_legacy_settings']],
    'parentheses override json boolean precedence' => ["SELECT option_name FROM wp_options WHERE (option_value ->> '$.plugin.network' OR option_value ->> '$.plugin.enabled') AND option_value ->> '$.plugin.indexed' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings']],
    'double not json boolean preserves true rows' => ["SELECT option_name FROM wp_options WHERE NOT NOT option_value ->> '$.plugin.enabled' ORDER BY option_id", ['plugin_cache_settings', 'plugin_legacy_settings']],
    'json numeric truth filters nonzero priorities' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_legacy_settings']],
    'not json numeric truth filters zero priority' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.priority' ORDER BY option_id", ['plugin_empty_settings']],
    'json negative numeric truth is true' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.ttl' ORDER BY option_id", ['plugin_cache_settings', 'plugin_legacy_settings']],
    'json zero numeric truth is false' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.ttl' ORDER BY option_id", ['plugin_forms_settings', 'plugin_empty_settings']],
    'json text numeric prefix truth filters ready values' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.flag_text' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_legacy_settings']],
    'json text nonnumeric prefix truth is false' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.flag_text' ORDER BY option_id", ['plugin_empty_settings']],
    'json text zero prefix truth is false' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.zero_text' ORDER BY option_id", []],
    'json text zero prefix not truth includes every row' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.zero_text' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings', 'plugin_legacy_settings']],
    'json empty text truth is false' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.empty_text' ORDER BY option_id", []],
    'json null truth is filtered out' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.null_flag' ORDER BY option_id", []],
    'json null not truth is filtered out by sql null' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.null_flag' ORDER BY option_id", []],
    'json missing not truth is filtered out by sql null' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.missing' ORDER BY option_id", []],
    'json comparison and boolean truth compose' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.priority' > 0 ORDER BY option_id", ['plugin_cache_settings']],
    'json comparison or boolean truth compose' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' > 5 OR option_value ->> '$.plugin.network' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_legacy_settings']],
    'json not comparison composes with boolean truth' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.priority' > 0 AND NOT option_value ->> '$.plugin.network' ORDER BY option_id", ['plugin_empty_settings']],
    'json bare boolean filters before grouped rows' => ["SELECT autoload, sum(option_id) AS c FROM wp_options WHERE option_value ->> '$.plugin.enabled' GROUP BY autoload ORDER BY autoload", ['yes']],
    'json not boolean filters before grouped rows' => ["SELECT autoload, sum(option_id) AS c FROM wp_options WHERE NOT option_value ->> '$.plugin.enabled' GROUP BY autoload ORDER BY autoload", ['no']],
    'json boolean controls projected case labels' => ["SELECT CASE WHEN option_value ->> '$.plugin.enabled' THEN option_name ELSE 'disabled' END AS label FROM wp_options ORDER BY option_id", ['plugin_cache_settings', 'disabled', 'disabled', 'plugin_legacy_settings']],
    'json not boolean controls projected case labels' => ["SELECT CASE WHEN NOT option_value ->> '$.plugin.enabled' THEN option_name ELSE 'enabled' END AS label FROM wp_options ORDER BY option_id", ['enabled', 'plugin_forms_settings', 'plugin_empty_settings', 'enabled']],
    'json boolean truth orders with case expressions' => ["SELECT option_name FROM wp_options ORDER BY CASE WHEN option_value ->> '$.plugin.enabled' THEN 0 ELSE 1 END, option_id", ['plugin_cache_settings', 'plugin_legacy_settings', 'plugin_forms_settings', 'plugin_empty_settings']],
    'json text numeric prefix truth controls case expressions' => ["SELECT CASE WHEN option_value ->> '$.plugin.flag_text' THEN 'truthy' ELSE 'falsey' END AS label FROM wp_options ORDER BY option_id", ['truthy', 'truthy', 'falsey', 'truthy']],
    'json null truth controls case false branch' => ["SELECT CASE WHEN option_value ->> '$.plugin.null_flag' THEN 'truthy' ELSE 'falsey' END AS label FROM wp_options ORDER BY option_id", ['falsey', 'falsey', 'falsey', 'falsey']],
    'json boolean truth survives jsonb right operand row' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.indexed' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings']],
    'jsonb false boolean participates in not' => ["SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.enabled' AND option_name = 'plugin_forms_settings'", ['plugin_forms_settings']],
    'json boolean truth with between narrows enabled rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.priority' BETWEEN -1 AND 7 ORDER BY option_id", ['plugin_cache_settings', 'plugin_legacy_settings']],
    'json boolean truth with not between excludes high cache' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.priority' NOT BETWEEN 1 AND 9 ORDER BY option_id", ['plugin_legacy_settings']],
    'json boolean truth with in list keeps enabled indexed rows' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.label' IN ('cache', 'legacy') ORDER BY option_id", ['plugin_cache_settings', 'plugin_legacy_settings']],
    'json boolean truth with not in list excludes cache' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.label' NOT IN ('cache') ORDER BY option_id", ['plugin_legacy_settings']],
    'json boolean truth with like keeps enabled cache' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.label' LIKE 'ca%' ORDER BY option_id", ['plugin_cache_settings']],
    'json boolean truth with glob keeps enabled legacy' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.label' GLOB 'leg*' ORDER BY option_id", ['plugin_legacy_settings']],
    'json boolean truth with collated comparison keeps cache' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND (option_value ->> '$.plugin.label') COLLATE NOCASE = 'CACHE' ORDER BY option_id", ['plugin_cache_settings']],
    'json or null and false follows sqlite three valued logic' => ["SELECT option_name FROM wp_options WHERE (option_value ->> '$.plugin.missing' OR option_value ->> '$.plugin.enabled') AND option_value ->> '$.plugin.indexed' ORDER BY option_id", ['plugin_cache_settings']],
    'json false or null is not selected' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.network' OR option_value ->> '$.plugin.missing' ORDER BY option_id", ['plugin_forms_settings', 'plugin_legacy_settings']],
    'json false and null is false not null selected by outer or' => ["SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.missing' OR option_name = 'plugin_empty_settings' ORDER BY option_id", ['plugin_empty_settings']],
    'json not parenthesized or keeps only both false local row' => ["SELECT option_name FROM wp_options WHERE NOT (option_value ->> '$.plugin.enabled' OR option_value ->> '$.plugin.network') ORDER BY option_id", ['plugin_empty_settings']],
    'json not parenthesized and keeps rows missing either truthy flag' => ["SELECT option_name FROM wp_options WHERE NOT (option_value ->> '$.plugin.enabled' AND option_value ->> '$.plugin.indexed') ORDER BY option_id", ['plugin_forms_settings', 'plugin_empty_settings', 'plugin_legacy_settings']],
    'json truth predicate accepts blob numeric prefix literal' => ["SELECT option_name FROM wp_options WHERE X'20317265616479' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings', 'plugin_legacy_settings']],
    'json truth predicate treats blob zero prefix literal as false' => ["SELECT option_name FROM wp_options WHERE NOT X'303064697361626c6564' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings', 'plugin_legacy_settings']],
    'json truth predicate accepts quoted numeric literal' => ["SELECT option_name FROM wp_options WHERE '  +1ready' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings', 'plugin_legacy_settings']],
    'json truth predicate rejects quoted nonnumeric literal as false' => ["SELECT option_name FROM wp_options WHERE NOT 'ready' ORDER BY option_id", ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings', 'plugin_legacy_settings']],
];

$tests = [];
foreach ($cases as $name => [$sql, $expected]) {
    $tests[$name] = static function (TestRunner $t) use ($column, $sql, $expected): void {
        $projection = str_starts_with($sql, 'SELECT autoload')
            ? 'autoload'
            : (str_starts_with($sql, 'SELECT CASE') ? 'label' : 'option_name');
        $t->same($expected, $column($sql, $projection));
    };
}

return $tests;
