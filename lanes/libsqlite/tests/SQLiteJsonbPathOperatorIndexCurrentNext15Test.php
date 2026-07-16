<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"channel":"stable","plugin":{"name":"cache","enabled":true,"priority":7,"channel":"stable","rules":[{"name":"warm","enabled":false},{"name":"serve","enabled":true}],"limits":{"daily":25},"empty":null},"settings.v1":{"mode":"dark"}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'channel' => 'beta',
            'plugin' => [
                'name' => 'forms',
                'enabled' => false,
                'priority' => 3,
                'channel' => 'beta',
                'rules' => [
                    ['name' => 'validate', 'enabled' => true],
                ],
                'limits' => ['daily' => 10],
                'empty' => null,
            ],
            'settings.v1' => ['mode' => 'light'],
        ])),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"channel":"dev","plugin":{"name":"empty","enabled":false,"priority":0,"channel":"dev","rules":[],"limits":{"daily":0},"empty":null},"settings.v1":{"mode":"none"}}',
        'autoload' => 'no',
    ],
];

$select = static fn (string $sql): array => SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
$column = static fn (string $sql, string $column): array => array_column($select($sql), $column);
$scalar = static function (string $sql) use ($select): mixed {
    $result = $select($sql);
    if (count($result) !== 1) {
        throw new RuntimeException('Expected one SQLite SELECT SQL result row');
    }

    return reset($result[0]);
};

return [
    'select sql json text operator extracts object member scalars' => static fn (TestRunner $t) => $t->same(
        ['cache', 'forms', 'empty'],
        $column("SELECT option_value ->> '$.plugin.name' AS name FROM wp_options ORDER BY option_id", 'name'),
    ),
    'select sql json text operator extracts booleans as sqlite integers' => static fn (TestRunner $t) => $t->same(
        [1, 0, 0],
        $column("SELECT option_value ->> '$.plugin.enabled' AS enabled FROM wp_options ORDER BY option_id", 'enabled'),
    ),
    'select sql json text operator extracts numeric values from jsonb inputs' => static fn (TestRunner $t) => $t->same(
        [7, 3, 0],
        $column("SELECT option_value ->> '$.plugin.priority' AS priority FROM wp_options ORDER BY option_id", 'priority'),
    ),
    'select sql json value operator returns canonical object fragments' => static fn (TestRunner $t) => $t->same(
        ['{"daily":25}', '{"daily":10}', '{"daily":0}'],
        $column("SELECT option_value -> '$.plugin.limits' AS limits FROM wp_options ORDER BY option_id", 'limits'),
    ),
    'select sql json value operator returns quoted text fragments' => static fn (TestRunner $t) => $t->same(
        ['"stable"', '"beta"', '"dev"'],
        $column("SELECT option_value -> '$.plugin.channel' AS channel FROM wp_options ORDER BY option_id", 'channel'),
    ),
    'select sql json value operator preserves json null fragments' => static fn (TestRunner $t) => $t->same(
        'null',
        $scalar("SELECT option_value -> '$.plugin.empty' AS empty_json FROM wp_options WHERE option_id = 1"),
    ),
    'select sql json text operator returns sql null for json null fragments' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT option_value ->> '$.plugin.empty' AS empty_sql FROM wp_options WHERE option_id = 1"),
    ),
    'select sql json operators return sql null for missing paths' => static function (TestRunner $t) use ($scalar): void {
        $t->same(null, $scalar("SELECT option_value -> '$.plugin.missing' AS missing_json FROM wp_options WHERE option_id = 1"));
        $t->same(null, $scalar("SELECT option_value ->> '$.plugin.missing' AS missing_sql FROM wp_options WHERE option_id = 1"));
    },
    'select sql json operator normalizes positive integer rhs to root array slot' => static fn (TestRunner $t) => $t->same(
        'stable',
        $scalar("SELECT '[\"dev\",\"stable\"]' ->> 1 AS channel FROM wp_options WHERE option_id = 1"),
    ),
    'select sql json operator normalizes negative integer rhs to reverse array slot' => static fn (TestRunner $t) => $t->same(
        'serve',
        $scalar("SELECT option_value ->> '$.plugin.rules[#-1].name' AS last_rule FROM wp_options WHERE option_id = 1"),
    ),
    'select sql json operator normalizes bracket string rhs to root array slot' => static fn (TestRunner $t) => $t->same(
        'dev',
        $scalar("SELECT '[\"dev\",\"stable\"]' ->> '[0]' AS channel FROM wp_options WHERE option_id = 1"),
    ),
    'select sql json operator quotes dotted labels when rhs is not a full path' => static fn (TestRunner $t) => $t->same(
        ['{"mode":"dark"}', '{"mode":"light"}', '{"mode":"none"}'],
        $column("SELECT option_value ->> 'settings.v1' AS settings FROM wp_options ORDER BY option_id", 'settings'),
    ),
    'select sql json operator accepts full quoted paths for dotted labels' => static fn (TestRunner $t) => $t->same(
        ['dark', 'light', 'none'],
        $column("SELECT option_value ->> '$.\"settings.v1\".mode' AS mode FROM wp_options ORDER BY option_id", 'mode'),
    ),
    'select sql json text operator filters where predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' = 1 ORDER BY option_name", 'option_name'),
    ),
    'select sql json text operator filters numeric ranges' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' >= 3 ORDER BY option_value ->> '$.plugin.priority' DESC", 'option_name'),
    ),
    'select sql json value operator filters canonical object fragments' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.plugin.limits' = '{\"daily\":10}'", 'option_name'),
    ),
    'select sql json operators compose with concatenation' => static fn (TestRunner $t) => $t->same(
        ['cache:stable', 'forms:beta', 'empty:dev'],
        $column("SELECT (option_value ->> '$.plugin.name') || ':' || (option_value ->> '$.plugin.channel') AS label FROM wp_options ORDER BY option_id", 'label'),
    ),
    'select sql json operators share concat precedence for left associative json fragments' => static fn (TestRunner $t) => $t->same(
        ['"stable":cache', '"beta":forms', '"dev":empty'],
        $column("SELECT (option_value -> '$.plugin.channel') || ':' || (option_value ->> '$.plugin.name') AS label FROM wp_options ORDER BY option_id", 'label'),
    ),
    'select sql json operators let parentheses override concat precedence' => static fn (TestRunner $t) => $t->same(
        ['"stable"', '"beta"', '"dev"'],
        $column("SELECT option_value -> ('$.plugin.channel' || '') AS channel_json FROM wp_options ORDER BY option_id", 'channel_json'),
    ),
    'select sql json concat before json operator follows sqlite left associativity' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT option_value || '{\"extra\":1}' ->> '$.plugin.name' AS bad FROM wp_options", ['wp_options' => $rows]),
    ),
    'select sql json repeated concat and operators follow sqlite left associativity' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT option_value ->> '$.plugin.name' || ':' || option_value ->> '$.plugin.channel' AS bad FROM wp_options", ['wp_options' => $rows]),
    ),
    'select sql json operator can consume explicitly parenthesized concat rhs path' => static fn (TestRunner $t) => $t->same(
        ['cache', 'forms', 'empty'],
        $column("SELECT option_value ->> ('$.plugin.' || 'name') AS name FROM wp_options ORDER BY option_id", 'name'),
    ),
    'select sql json operators compose with arithmetic' => static fn (TestRunner $t) => $t->same(
        [8, 4, 1],
        $column("SELECT (option_value ->> '$.plugin.priority') + 1 AS bumped FROM wp_options ORDER BY option_id", 'bumped'),
    ),
    'select sql json operators order by extracted text' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms_settings', 'plugin_empty_settings', 'plugin_cache_settings'],
        $column("SELECT option_name FROM wp_options ORDER BY option_value ->> '$.plugin.channel', option_name", 'option_name'),
    ),
    'select sql json operators order by extracted numeric values' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings', 'plugin_empty_settings'],
        $column("SELECT option_name FROM wp_options ORDER BY option_value ->> '$.plugin.priority' DESC, option_name", 'option_name'),
    ),
    'select sql json operator preserves canonical array fragments' => static fn (TestRunner $t) => $t->same(
        ['[{"name":"warm","enabled":false},{"name":"serve","enabled":true}]', '[{"name":"validate","enabled":true}]', '[]'],
        $column("SELECT option_value -> '$.plugin.rules' AS rules FROM wp_options ORDER BY option_id", 'rules'),
    ),
    'select sql json operators support in-list predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.channel' IN ('stable', 'beta') ORDER BY option_id", 'option_name'),
    ),
    'select sql json operators support not in predicates with jsonb rows' => static fn (TestRunner $t) => $t->same(
        ['plugin_empty_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.channel' NOT IN ('stable', 'beta') ORDER BY option_id", 'option_name'),
    ),
    'select sql json operators support between predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' BETWEEN 3 AND 7 ORDER BY option_id", 'option_name'),
    ),
    'select sql json operators support not between predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_empty_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' NOT BETWEEN 1 AND 9 ORDER BY option_id", 'option_name'),
    ),
    'select sql json operators support like predicates over extracted text' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.name' LIKE 'ca%' ORDER BY option_id", 'option_name'),
    ),
    'select sql json operators support glob predicates over extracted text' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.name' GLOB 'f*' ORDER BY option_id", 'option_name'),
    ),
    'select sql json operators support collated ordering over extracted text' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_empty_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options ORDER BY (option_value ->> '$.plugin.name') COLLATE NOCASE", 'option_name'),
    ),
    'select sql json operators reject malformed full paths' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT option_value -> '$.plugin[#-]' AS bad FROM wp_options", ['wp_options' => $rows]),
    ),
    'select sql json operators return null for json-subtype object path operands' => static fn (TestRunner $t) => $t->same(
        [['bad' => null], ['bad' => null], ['bad' => null]],
        SQLiteSelectSql::execute("SELECT option_value -> json('{\"path\":\"$.plugin\"}') AS bad FROM wp_options", ['wp_options' => $rows]),
    ),
    'select sql json operators reject non-json left operands' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT option_id ->> 'plugin.name' AS bad FROM wp_options", ['wp_options' => $rows]),
    ),
];
