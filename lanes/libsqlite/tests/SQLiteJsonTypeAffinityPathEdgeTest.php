<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_seo',
        'option_value' => '{"plugin":{"enabled":true,"priority":7,"ratio":2.5,"modes":["seo","cache"],"empty":null,"label":"Alpha"}}',
        'path_blob' => new SQLiteBlobValue('$.plugin.enabled'),
        'path_text' => '$.plugin.priority',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => false,
                'priority' => 3,
                'ratio' => 1.25,
                'modes' => ['forms'],
                'empty' => null,
                'label' => 'Beta',
            ],
        ])),
        'path_blob' => new SQLiteBlobValue('$.plugin.modes'),
        'path_text' => '$.plugin.label',
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty',
        'option_value' => '{"plugin":{"enabled":false,"priority":0,"ratio":0,"modes":[],"empty":null,"label":"Gamma"}}',
        'path_blob' => new SQLiteBlobValue('$.plugin.empty'),
        'path_text' => '$.plugin.modes',
        'autoload' => 'no',
    ],
];

$scalar = static function (string $sql, array $tables = []) use ($rows): mixed {
    $result = SQLiteSelectSql::execute($sql, $tables === [] ? ['wp_options' => $rows] : $tables);
    if (count($result) !== 1) {
        throw new RuntimeException('Expected one SQLite SELECT SQL result row');
    }

    return reset($result[0]);
};

return [
    'applies blob path affinity to json_type literals' => static fn (TestRunner $t) => $t->same(
        'integer',
        $scalar("SELECT json_type(option_value, CAST('$.plugin.priority' AS BLOB)) AS type FROM wp_options WHERE option_id = 1"),
    ),
    'applies blob path affinity to json_array_length literals' => static fn (TestRunner $t) => $t->same(
        2,
        $scalar("SELECT json_array_length(option_value, CAST('$.plugin.modes' AS BLOB)) AS len FROM wp_options WHERE option_id = 1"),
    ),
    'applies blob path affinity to json_extract literals' => static fn (TestRunner $t) => $t->same(
        7,
        $scalar("SELECT json_extract(option_value, CAST('$.plugin.priority' AS BLOB)) AS priority FROM wp_options WHERE option_id = 1"),
    ),
    'applies blob path affinity to jsonb_extract literals' => static function (TestRunner $t) use ($scalar): void {
        $value = $scalar("SELECT jsonb_extract(option_value, CAST('$.plugin.modes' AS BLOB)) AS modes FROM wp_options WHERE option_id = 2");
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same(['forms'], SQLiteJsonB::decode($value->bytes));
    },
    'applies row blob path affinity to json_type' => static fn (TestRunner $t) => $t->same(
        ['true', 'array', 'null'],
        array_column(SQLiteSelectSql::execute('SELECT json_type(option_value, path_blob) AS type FROM wp_options ORDER BY option_id', ['wp_options' => $rows]), 'type'),
    ),
    'applies row blob path affinity to json_extract' => static fn (TestRunner $t) => $t->same(
        [1, '["forms"]', null],
        array_column(SQLiteSelectSql::execute('SELECT json_extract(option_value, path_blob) AS value FROM wp_options ORDER BY option_id', ['wp_options' => $rows]), 'value'),
    ),
    'applies concatenated blob path affinity to json_type' => static fn (TestRunner $t) => $t->same(
        'text',
        $scalar("SELECT json_type(option_value, CAST('$.plugin.' AS BLOB) || 'label') AS type FROM wp_options WHERE option_id = 1"),
    ),
    'applies concatenated blob path affinity to json_extract' => static fn (TestRunner $t) => $t->same(
        'Alpha',
        $scalar("SELECT json_extract(option_value, CAST('$.plugin.' AS BLOB) || 'label') AS label FROM wp_options WHERE option_id = 1"),
    ),
    'applies cast integer text path affinity before path validation' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_type(option_value, CAST(1 AS INTEGER)) AS type FROM wp_options WHERE option_id = 1", ['wp_options' => $rows]),
    ),
    'applies cast real text path affinity before path validation' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_array_length(option_value, CAST(1.5 AS REAL)) AS len FROM wp_options WHERE option_id = 1", ['wp_options' => $rows]),
    ),
    'returns null for json_type null path operands' => static fn (TestRunner $t) => $t->same(
        [null, null, null],
        array_column(SQLiteSelectSql::execute('SELECT json_type(option_value, NULL) AS type FROM wp_options ORDER BY option_id', ['wp_options' => $rows]), 'type'),
    ),
    'returns null for json_array_length null path operands' => static fn (TestRunner $t) => $t->same(
        [null, null, null],
        array_column(SQLiteSelectSql::execute('SELECT json_array_length(option_value, NULL) AS len FROM wp_options ORDER BY option_id', ['wp_options' => $rows]), 'len'),
    ),
    'returns null for json_extract null path operands' => static fn (TestRunner $t) => $t->same(
        [null, null, null],
        array_column(SQLiteSelectSql::execute('SELECT json_extract(option_value, NULL) AS value FROM wp_options ORDER BY option_id', ['wp_options' => $rows]), 'value'),
    ),
    'returns null for json_extract mixed path list with null path' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT json_extract(option_value, '$.plugin.priority', NULL) AS value FROM wp_options WHERE option_id = 1"),
    ),
    'uses blob path affinity in where json_type predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_forms'],
        array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_type(option_value, CAST('$.plugin.modes' AS BLOB)) = 'array' AND json_array_length(option_value, CAST('$.plugin.modes' AS BLOB)) = 1", ['wp_options' => $rows]), 'option_name'),
    ),
    'uses blob path affinity in where json_extract numeric predicates' => static fn (TestRunner $t) => $t->same(
        ['plugin_seo'],
        array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options WHERE json_extract(option_value, CAST('$.plugin.priority' AS BLOB)) > 5", ['wp_options' => $rows]), 'option_name'),
    ),
    'uses blob path affinity in order by json_extract expressions' => static fn (TestRunner $t) => $t->same(
        ['plugin_seo', 'plugin_forms', 'plugin_empty'],
        array_column(SQLiteSelectSql::execute("SELECT option_name FROM wp_options ORDER BY json_extract(option_value, CAST('$.plugin.priority' AS BLOB)) DESC, option_name", ['wp_options' => $rows]), 'option_name'),
    ),
    'uses row text paths without regressing ordinary text affinity' => static fn (TestRunner $t) => $t->same(
        ['integer', 'text', 'array'],
        array_column(SQLiteSelectSql::execute('SELECT json_type(option_value, path_text) AS type FROM wp_options ORDER BY option_id', ['wp_options' => $rows]), 'type'),
    ),
    'uses quoted blob paths for object labels with spaces' => static fn (TestRunner $t) => $t->same(
        'text',
        $scalar("SELECT json_type('{\"plugin settings\":{\"mode\":\"cache\"}}', CAST('$.' || '\"plugin settings\"' || '.mode' AS BLOB)) AS type FROM wp_options LIMIT 1"),
    ),
    'uses blob paths for dotted quoted labels' => static fn (TestRunner $t) => $t->same(
        'integer',
        $scalar("SELECT json_type('{\"plugin.key\":{\"count\":2}}', CAST('$.\"plugin.key\".count' AS BLOB)) AS type FROM wp_options LIMIT 1"),
    ),
    'uses blob path affinity for reverse array index extraction' => static fn (TestRunner $t) => $t->same(
        'cache',
        $scalar("SELECT json_extract(option_value, CAST('$.plugin.modes[#-1]' AS BLOB)) AS mode FROM wp_options WHERE option_id = 1"),
    ),
    'uses blob path affinity for json_type reverse array index' => static fn (TestRunner $t) => $t->same(
        'text',
        $scalar("SELECT json_type(option_value, CAST('$.plugin.modes[#-1]' AS BLOB)) AS type FROM wp_options WHERE option_id = 2"),
    ),
    'keeps missing blob paths as null results' => static fn (TestRunner $t) => $t->same(
        [null, null, null],
        array_column(SQLiteSelectSql::execute("SELECT json_type(option_value, CAST('$.plugin.missing' AS BLOB)) AS type FROM wp_options ORDER BY option_id", ['wp_options' => $rows]), 'type'),
    ),
    'rejects malformed blob path bytes after affinity conversion' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_type(option_value, X'0102') AS type FROM wp_options WHERE option_id = 1", ['wp_options' => $rows]),
    ),
    'rejects array-valued path operands without scalar affinity' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute("SELECT json_type(option_value, json_extract(option_value, '$.plugin.modes')) AS type FROM wp_options WHERE option_id = 1", ['wp_options' => $rows]),
    ),
];
