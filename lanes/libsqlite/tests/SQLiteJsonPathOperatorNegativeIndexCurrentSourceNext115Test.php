<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPath;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"rules":[{"name":"warm","priority":1},{"name":"serve","priority":9}],"channels":["alpha","stable"],"flags":[true,false,true]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'validate', 'priority' => 3],
                ['name' => 'submit', 'priority' => 6],
                ['name' => 'notify', 'priority' => 8],
            ],
            'channels' => ['beta'],
            'flags' => [false, true],
        ])),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[],"channels":[],"flags":[]}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_legacy_settings',
        'option_value' => '{"rules":[{"name":"legacy","priority":-1}],"channels":["legacy","stable"],"flags":[false]}',
        'autoload' => 'yes',
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
$jsonTextPath = static function (string $expression): ?string {
    $index = SQLiteCreateIndex::firstJsonTextOperatorExpression(
        'CREATE INDEX idx_json_text_path ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL'
    );

    return $index?->path;
};
$jsonValuePath = static function (string $expression): ?string {
    $index = SQLiteCreateIndex::firstJsonValueOperatorExpression(
        'CREATE INDEX idx_json_value_path ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL'
    );

    return $index?->path;
};

$tests = [
    'json path operator negative index current source next115 normalizes integer minus one' => static fn (TestRunner $t) => $t->same('$[#-1]', SQLiteJsonPath::normalizeOperatorPath(-1)),
    'json path operator negative index current source next115 normalizes integer minus two' => static fn (TestRunner $t) => $t->same('$[#-2]', SQLiteJsonPath::normalizeOperatorPath(-2)),
    'json path operator negative index current source next115 normalizes positive integer' => static fn (TestRunner $t) => $t->same('$[2]', SQLiteJsonPath::normalizeOperatorPath(2)),
    'json path operator negative index current source next115 normalizes bracket reverse string' => static fn (TestRunner $t) => $t->same('$[#-1]', SQLiteJsonPath::normalizeOperatorPath('[#-1]')),
    'json path operator negative index current source next115 keeps full reverse path' => static fn (TestRunner $t) => $t->same('$.rules[#-1].name', SQLiteJsonPath::normalizeOperatorPath('$.rules[#-1].name')),
    'json path operator negative index current source next115 rejects malformed reverse path' => static fn (TestRunner $t) => $t->same(null, SQLiteJsonPath::normalizeOperatorPath('$.rules[#-]')),
    'json path operator negative index current source next115 quotes negative string labels' => static fn (TestRunner $t) => $t->same('$."-1"', SQLiteJsonPath::normalizeOperatorPath('-1')),
    'json path operator negative index current source next115 blob bracket reverse path' => static fn (TestRunner $t) => $t->same('$[#-2]', SQLiteJsonPath::normalizeOperatorPath(new SQLiteBlobValue('[#-2]'))),
    'json path operator negative index current source next115 boolean true is label one' => static fn (TestRunner $t) => $t->same('$."1"', SQLiteJsonPath::normalizeOperatorPath(true)),
    'json path operator negative index current source next115 boolean false is label zero' => static fn (TestRunner $t) => $t->same('$."0"', SQLiteJsonPath::normalizeOperatorPath(false)),
    'json path operator negative index current source next115 null is rejected by normalizer' => static fn (TestRunner $t) => $t->same(null, SQLiteJsonPath::normalizeOperatorPath(null)),

    'json path operator negative index current source next115 select root last text row values' => static fn (TestRunner $t) => $t->same(
        ['stable', 'beta', null, 'stable'],
        $column("SELECT option_value ->> '$.channels' ->> -1 AS channel FROM wp_options ORDER BY option_id", 'channel'),
    ),
    'json path operator negative index current source next115 select root second last text row values' => static fn (TestRunner $t) => $t->same(
        ['alpha', null, null, 'legacy'],
        $column("SELECT option_value ->> '$.channels' ->> -2 AS channel FROM wp_options ORDER BY option_id", 'channel'),
    ),
    'json path operator negative index current source next115 value operator returns quoted last channel' => static fn (TestRunner $t) => $t->same(
        ['"stable"', '"beta"', null, '"stable"'],
        $column("SELECT option_value -> '$.channels' -> -1 AS channel_json FROM wp_options ORDER BY option_id", 'channel_json'),
    ),
    'json path operator negative index current source next115 text operator extracts nested last rule names' => static fn (TestRunner $t) => $t->same(
        ['serve', 'notify', null, 'legacy'],
        $column("SELECT option_value ->> '$.rules[#-1].name' AS rule_name FROM wp_options ORDER BY option_id", 'rule_name'),
    ),
    'json path operator negative index current source next115 value operator extracts nested last rule objects' => static fn (TestRunner $t) => $t->same(
        ['{"name":"serve","priority":9}', '{"name":"notify","priority":8}', null, '{"name":"legacy","priority":-1}'],
        $column("SELECT option_value -> '$.rules[#-1]' AS rule_json FROM wp_options ORDER BY option_id", 'rule_json'),
    ),
    'json path operator negative index current source next115 jsonb row supports root negative integer rhs' => static fn (TestRunner $t) => $t->same(
        'notify',
        $scalar("SELECT option_value -> '$.rules' -> -1 ->> 'name' AS rule_name FROM wp_options WHERE option_id = 2"),
    ),
    'json path operator negative index current source next115 out of range negative rhs returns null' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT option_value -> '$.channels' ->> -3 AS missing_channel FROM wp_options WHERE option_id = 1"),
    ),
    'json path operator negative index current source next115 root empty array negative rhs returns null' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT option_value -> '$.channels' ->> -1 AS missing_channel FROM wp_options WHERE option_id = 3"),
    ),
    'json path operator negative index current source next115 negative boolean truth filters last flags' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.flags' ->> -1 ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs composes with comparison' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.rules' -> -1 ->> 'priority' >= 8 ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs orders by extracted last channel' => static fn (TestRunner $t) => $t->same(
        ['plugin_empty_settings', 'plugin_forms_settings', 'plugin_cache_settings', 'plugin_legacy_settings'],
        $column("SELECT option_name FROM wp_options ORDER BY option_value -> '$.channels' ->> -1, option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 chained negative rhs reaches prior rule' => static fn (TestRunner $t) => $t->same(
        'submit',
        $scalar("SELECT option_value -> '$.rules' -> -2 ->> 'name' AS rule_name FROM wp_options WHERE option_id = 2"),
    ),
    'json path operator negative index current source next115 negative rhs equality filters stable last channel' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_legacy_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.channels' ->> -1 = 'stable' ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs inequality filters non stable last channel' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.channels' ->> -1 != 'stable' ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs in list filters channels' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms_settings', 'plugin_cache_settings', 'plugin_legacy_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.channels' ->> -1 IN ('beta', 'stable') ORDER BY option_value -> '$.channels' ->> -1, option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs not in filters legacy none' => static fn (TestRunner $t) => $t->same(
        [],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.channels' ->> -1 NOT IN ('beta', 'stable') ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs like filters stable channels' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_legacy_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.channels' ->> -1 LIKE 'sta%' ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs glob filters beta channel' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.channels' ->> -1 GLOB 'b*' ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs between filters priorities' => static fn (TestRunner $t) => $t->same(
        ['plugin_cache_settings', 'plugin_forms_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.rules' -> -1 ->> 'priority' BETWEEN 6 AND 9 ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 negative rhs not between filters legacy' => static fn (TestRunner $t) => $t->same(
        ['plugin_legacy_settings'],
        $column("SELECT option_name FROM wp_options WHERE option_value -> '$.rules' -> -1 ->> 'priority' NOT BETWEEN 0 AND 10 ORDER BY option_id", 'option_name'),
    ),
    'json path operator negative index current source next115 malformed explicit path still rejected' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT option_value -> '$.rules[#-]' AS bad FROM wp_options", ['wp_options' => $rows]),
    ),

    'json path operator negative index current source next115 create index text negative integer rhs' => static fn (TestRunner $t) => $t->same(
        '$[#-1]',
        $jsonTextPath('option_value ->> -1'),
    ),
    'json path operator negative index current source next115 create index value negative integer rhs' => static fn (TestRunner $t) => $t->same(
        '$[#-2]',
        $jsonValuePath('option_value -> -2'),
    ),
    'json path operator negative index current source next115 create index collated negative integer rhs' => static fn (TestRunner $t) => $t->same(
        '$[#-1]',
        $jsonTextPath('option_value ->> (-1 COLLATE nocase)'),
    ),
    'json path operator negative index current source next115 create index bracket negative rhs' => static fn (TestRunner $t) => $t->same(
        '$[#-1]',
        $jsonValuePath("option_value -> '[#-1]'"),
    ),
    'json path operator negative index current source next115 create index nested reverse path' => static fn (TestRunner $t) => $t->same(
        '$.rules[#-1].name',
        $jsonTextPath("option_value ->> '$.rules[#-1].name'"),
    ),
    'json path operator negative index current source next115 create index malformed reverse path rejected' => static fn (TestRunner $t) => $t->same(
        null,
        $jsonTextPath("option_value ->> '$.rules[#-]'"),
    ),
    'json path operator negative index current source next115 create index negative string is label' => static fn (TestRunner $t) => $t->same(
        '$."-1"',
        $jsonTextPath("option_value ->> '-1'"),
    ),
    'json path operator negative index current source next115 create index positive integer unchanged' => static fn (TestRunner $t) => $t->same(
        '$[1]',
        $jsonTextPath('option_value ->> 1'),
    ),
    'json path operator negative index current source next115 create index zero integer unchanged' => static fn (TestRunner $t) => $t->same(
        '$[0]',
        $jsonValuePath('option_value -> 0'),
    ),
    'json path operator negative index current source next115 create index text operator accepts reverse zero path' => static fn (TestRunner $t) => $t->same(
        '$[#-0]',
        $jsonTextPath("option_value ->> '[#-0]'"),
    ),
    'json path operator negative index current source next115 create index collated nested reverse path' => static fn (TestRunner $t) => $t->same(
        '$.rules[#-2].priority',
        $jsonTextPath("option_value ->> ('$.rules[#-2].priority' COLLATE nocase)"),
    ),
    'json path operator negative index current source next115 create index value collated nested reverse path' => static fn (TestRunner $t) => $t->same(
        '$.rules[#-1]',
        $jsonValuePath("option_value -> ('$.rules[#-1]' COLLATE nocase)"),
    ),
];

return $tests;
