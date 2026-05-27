<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$textRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"plugin":{"modes":["dark","light"],"enabled":true,"empty":null,"threshold":2.5},"autoload":["yes","no"]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_json5_settings',
        'option_value' => "{plugin:{modes:['sync','cache',],enabled:false,empty:null,threshold:.75},autoload:['yes']}",
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"plugin":{"modes":[],"enabled":false},"autoload":[]}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_null_settings',
        'option_value' => null,
        'autoload' => 'no',
    ],
];

$jsonbRows = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_jsonb_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'modes' => ['seo', 'forms', 'cache'],
                'enabled' => true,
                'empty' => null,
                'threshold' => 4,
            ],
            'autoload' => ['yes', 'maybe'],
        ])),
        'autoload' => 'yes',
    ],
    [
        'option_id' => 11,
        'option_name' => 'plugin_cast_text_blob',
        'option_value' => new SQLiteBlobValue('{"plugin":{"modes":["legacy"],"enabled":false},"autoload":["no"]}'),
        'autoload' => 'no',
    ],
    [
        'option_id' => 12,
        'option_name' => 'plugin_malformed_jsonb_settings',
        'option_value' => new SQLiteBlobValue("\x1c\x00"),
        'autoload' => 'no',
    ],
];

$scalar = static function (string $sql, array $tables) {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected one SQLite SELECT SQL result row');
    }

    return reset($rows[0]);
};

$cases = [
    'dispatches json_type over strict text object roots from select sql' => static fn (TestRunner $t) => $t->same(
        ['object', 'object', 'object', null],
        array_column(SQLiteSelectSql::execute("SELECT option_id, json_type(option_value) AS type FROM wp_options ORDER BY option_id", ['wp_options' => $textRows]), 'type'),
    ),
    'dispatches json_type over json5 object roots from select sql' => static fn (TestRunner $t) => $t->same(
        'object',
        $scalar("SELECT json_type(option_value) AS type FROM wp_options WHERE option_name = 'plugin_json5_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_type over jsonb object roots from select sql' => static fn (TestRunner $t) => $t->same(
        'object',
        $scalar("SELECT json_type(option_value) AS type FROM wp_options WHERE option_name = 'plugin_jsonb_settings'", ['wp_options' => $jsonbRows]),
    ),
    'dispatches json_type over cast text blob roots from select sql' => static fn (TestRunner $t) => $t->same(
        'object',
        $scalar("SELECT json_type(option_value) AS type FROM wp_options WHERE option_name = 'plugin_cast_text_blob'", ['wp_options' => $jsonbRows]),
    ),
    'dispatches json_type path to text arrays from select sql' => static fn (TestRunner $t) => $t->same(
        ['array', 'array', 'array', null],
        array_column(SQLiteSelectSql::execute("SELECT option_id, json_type(option_value, '$.plugin.modes') AS type FROM wp_options ORDER BY option_id", ['wp_options' => $textRows]), 'type'),
    ),
    'dispatches json_type path to jsonb arrays from select sql' => static fn (TestRunner $t) => $t->same(
        'array',
        $scalar("SELECT json_type(option_value, '$.plugin.modes') AS type FROM wp_options WHERE option_name = 'plugin_jsonb_settings'", ['wp_options' => $jsonbRows]),
    ),
    'dispatches json_type path to true from select sql' => static fn (TestRunner $t) => $t->same(
        'true',
        $scalar("SELECT json_type(option_value, '$.plugin.enabled') AS type FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_type path to false from select sql' => static fn (TestRunner $t) => $t->same(
        'false',
        $scalar("SELECT json_type(option_value, '$.plugin.enabled') AS type FROM wp_options WHERE option_name = 'plugin_json5_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_type path to null from select sql' => static fn (TestRunner $t) => $t->same(
        'null',
        $scalar("SELECT json_type(option_value, '$.plugin.empty') AS type FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_type path to real from select sql' => static fn (TestRunner $t) => $t->same(
        'real',
        $scalar("SELECT json_type(option_value, '$.plugin.threshold') AS type FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_type path to integer from select sql' => static fn (TestRunner $t) => $t->same(
        'integer',
        $scalar("SELECT json_type(option_value, '$.plugin.threshold') AS type FROM wp_options WHERE option_name = 'plugin_jsonb_settings'", ['wp_options' => $jsonbRows]),
    ),
    'dispatches json_type missing paths as null from select sql' => static fn (TestRunner $t) => $t->same(
        [null, null, null, null],
        array_column(SQLiteSelectSql::execute("SELECT option_id, json_type(option_value, '$.plugin.missing') AS type FROM wp_options ORDER BY option_id", ['wp_options' => $textRows]), 'type'),
    ),
    'dispatches json_type null path as null from select sql' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT json_type(option_value, NULL) AS type FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_array_length over strict text arrays from select sql' => static fn (TestRunner $t) => $t->same(
        [2, 2, 0, null],
        array_column(SQLiteSelectSql::execute("SELECT option_id, json_array_length(option_value, '$.plugin.modes') AS len FROM wp_options ORDER BY option_id", ['wp_options' => $textRows]), 'len'),
    ),
    'dispatches json_array_length over json5 arrays from select sql' => static fn (TestRunner $t) => $t->same(
        2,
        $scalar("SELECT json_array_length(option_value, '$.plugin.modes') AS len FROM wp_options WHERE option_name = 'plugin_json5_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_array_length over jsonb arrays from select sql' => static fn (TestRunner $t) => $t->same(
        3,
        $scalar("SELECT json_array_length(option_value, '$.plugin.modes') AS len FROM wp_options WHERE option_name = 'plugin_jsonb_settings'", ['wp_options' => $jsonbRows]),
    ),
    'dispatches json_array_length over cast text blob arrays from select sql' => static fn (TestRunner $t) => $t->same(
        1,
        $scalar("SELECT json_array_length(option_value, '$.plugin.modes') AS len FROM wp_options WHERE option_name = 'plugin_cast_text_blob'", ['wp_options' => $jsonbRows]),
    ),
    'dispatches json_array_length non-array text values as zero from select sql' => static fn (TestRunner $t) => $t->same(
        [0, 0, 0],
        array_column(SQLiteSelectSql::execute("SELECT option_id, json_array_length(option_value, '$.plugin.enabled') AS len FROM wp_options WHERE option_value IS NOT NULL ORDER BY option_id", ['wp_options' => $textRows]), 'len'),
    ),
    'dispatches json_array_length root object as zero from select sql' => static fn (TestRunner $t) => $t->same(
        0,
        $scalar("SELECT json_array_length(option_value) AS len FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows]),
    ),
    'dispatches json_array_length missing paths as null from select sql' => static fn (TestRunner $t) => $t->same(
        [null, null, null, null],
        array_column(SQLiteSelectSql::execute("SELECT option_id, json_array_length(option_value, '$.plugin.missing') AS len FROM wp_options ORDER BY option_id", ['wp_options' => $textRows]), 'len'),
    ),
    'dispatches json_array_length null path as null from select sql' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT json_array_length(option_value, NULL) AS len FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows]),
    ),
    'filters select sql rows with json_type predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_settings', 'plugin_json5_settings', 'plugin_empty_settings'],
        array_column(SQLiteSelectSql::execute("SELECT option_id, option_name FROM wp_options WHERE json_type(option_value, '$.plugin.modes') = 'array' ORDER BY option_id", ['wp_options' => $textRows]), 'option_name'),
    ),
    'filters select sql rows with json_array_length predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_settings', 'plugin_json5_settings'],
        array_column(SQLiteSelectSql::execute("SELECT option_id, option_name FROM wp_options WHERE json_array_length(option_value, '$.plugin.modes') >= 2 ORDER BY option_id", ['wp_options' => $textRows]), 'option_name'),
    ),
    'orders select sql rows by json_array_length expression' => static fn (TestRunner $t) => $t->same(
        ['plugin_jsonb_settings', 'plugin_cast_text_blob'],
        array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE option_name != 'plugin_malformed_jsonb_settings' ORDER BY json_array_length(option_value, '$.plugin.modes') DESC, option_name", ['wp_options' => $jsonbRows]), 'option_name'),
    ),
    'projects json inspection functions with aliases from select sql' => static fn (TestRunner $t) => $t->same(
        ['mode_type' => 'array', 'mode_count' => 2],
        SQLiteSelectSql::execute("SELECT json_type(option_value, '$.plugin.modes') AS mode_type, json_array_length(option_value, '$.plugin.modes') AS mode_count FROM wp_options WHERE option_name = 'plugin_settings'", ['wp_options' => $textRows])[0],
    ),
    'dispatches uppercase json inspection function names from select sql' => static fn (TestRunner $t) => $t->same(
        ['mode_type' => 'array', 'mode_count' => 3],
        SQLiteSelectSql::execute("SELECT JSON_TYPE(option_value, '$.plugin.modes') AS mode_type, JSON_ARRAY_LENGTH(option_value, '$.plugin.modes') AS mode_count FROM wp_options WHERE option_name = 'plugin_jsonb_settings'", ['wp_options' => $jsonbRows])[0],
    ),
    'dispatches json inspection over sql blob literals from select sql' => static function (TestRunner $t) use ($textRows): void {
        $hex = bin2hex(SQLiteJsonB::encode(['plugin' => ['modes' => ['a', 'b', 'c']]]));
        $rows = SQLiteSelectSql::execute("SELECT json_type(X'{$hex}', '$.plugin.modes') AS mode_type, json_array_length(X'{$hex}', '$.plugin.modes') AS mode_count FROM wp_options LIMIT 1", ['wp_options' => $textRows]);

        $t->same(['mode_type' => 'array', 'mode_count' => 3], $rows[0]);
    },
    'rejects malformed superficial jsonb inspection in select sql' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_type(option_value, '$.plugin.modes') AS type FROM wp_options WHERE option_name = 'plugin_malformed_jsonb_settings'", ['wp_options' => $jsonbRows]),
    ),
    'rejects json inspection select sql with too few arguments' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT json_type() AS type FROM wp_options', ['wp_options' => $textRows]),
    ),
    'rejects json inspection select sql with too many arguments' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_array_length(option_value, '$.plugin.modes', '$.extra') AS len FROM wp_options", ['wp_options' => $textRows]),
    ),
    'rejects json inspection select sql with non-json argument' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_type(option_id) AS type FROM wp_options", ['wp_options' => $textRows]),
    ),
    'rejects json inspection select sql with non-text path' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_type(option_value, 7) AS type FROM wp_options", ['wp_options' => $textRows]),
    ),
    'rejects malformed json text inspection in select sql' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_array_length('{plugin:,}', '$.plugin') AS len FROM wp_options LIMIT 1", ['wp_options' => $textRows]),
    ),
];

return $cases;
