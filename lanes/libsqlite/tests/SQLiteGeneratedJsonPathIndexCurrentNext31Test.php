<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;

return [
    'plans current and next index entries for generated json_extract columns' => static function (TestRunner $t): void {
        $createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT,
  autoload TEXT,
  plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(option_value, '$.plugin.enabled')) STORED,
  plugin_priority INTEGER AS (json_extract(option_value, '$.plugin.priority')) VIRTUAL
)
SQL;

        $rows = [
            ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"enabled":0,"priority":2}}', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => '{"plugin":{"enabled":1,"priority":5}}', 'autoload' => 'no'],
            ['option_id' => 3, 'option_name' => 'plugin_empty', 'option_value' => '{"plugin":{"priority":9}}', 'autoload' => 'yes'],
        ];

        $plan = SQLiteGeneratedJsonPathIndexPlan::plan($createTableSql, $rows, [
            ['name' => 'idx_plugin_enabled', 'rootPage' => 7, 'sql' => 'CREATE INDEX idx_plugin_enabled ON wp_options(plugin_enabled) WHERE plugin_enabled IS NOT NULL'],
            ['name' => 'idx_plugin_priority', 'rootPage' => 8, 'sql' => 'CREATE INDEX idx_plugin_priority ON wp_options(plugin_priority DESC) WHERE plugin_priority IS NOT NULL'],
        ], [
            ['rowid' => 1, 'mutations' => [
                ['function' => 'json_set', 'path' => '$.plugin.enabled', 'value' => 1],
                ['function' => 'json_set', 'path' => '$.plugin.priority', 'value' => 7],
            ]],
            ['rowid' => 3, 'mutations' => [
                ['function' => 'json_set', 'path' => '$.plugin.enabled', 'value' => 0],
            ]],
        ]);

        $t->same('wp_options', $plan['table']);
        $t->same(2, count($plan['generated_columns']));
        $t->same(['plugin_enabled', 'plugin_priority'], array_column($plan['generated_columns'], 'name'));
        $t->same(['$.plugin.enabled', '$.plugin.priority'], array_column($plan['generated_columns'], 'path'));
        $t->same(['STORED', 'VIRTUAL'], array_column($plan['generated_columns'], 'storage'));
        $t->same(3, count($plan['before']));
        $t->same(3, count($plan['after']));
        $t->same(2, $plan['changes']);
        $t->same(2, count($plan['changed_rows']));
        $t->same([0, 1, null], array_column($plan['before'], 'plugin_enabled'));
        $t->same([2, 5, 9], array_column($plan['before'], 'plugin_priority'));
        $t->same([1, 1, 0], array_column($plan['after'], 'plugin_enabled'));
        $t->same([7, 5, 9], array_column($plan['after'], 'plugin_priority'));
        $t->same('{"plugin":{"enabled":1,"priority":7}}', $plan['after'][0]['option_value']);
        $t->same('{"plugin":{"priority":9,"enabled":0}}', $plan['after'][2]['option_value']);
        $t->same(3, count($plan['index_updates']));

        $enabledAlpha = $plan['index_updates'][0];
        $t->same('idx_plugin_enabled', $enabledAlpha['index']);
        $t->same(7, $enabledAlpha['rootPage']);
        $t->same(1, $enabledAlpha['rowid']);
        $t->same('plugin_enabled', $enabledAlpha['column']);
        $t->same('$.plugin.enabled', $enabledAlpha['path']);
        $t->same(0, $enabledAlpha['current']);
        $t->same(1, $enabledAlpha['next']);
        $t->same(true, $enabledAlpha['delete']);
        $t->same(true, $enabledAlpha['insert']);
        $t->same(true, $enabledAlpha['partial']);
        $t->same('BINARY', $enabledAlpha['collation']);
        $t->same(false, $enabledAlpha['descending']);

        $priorityAlpha = $plan['index_updates'][1];
        $t->same('idx_plugin_priority', $priorityAlpha['index']);
        $t->same(8, $priorityAlpha['rootPage']);
        $t->same('plugin_priority', $priorityAlpha['column']);
        $t->same(2, $priorityAlpha['current']);
        $t->same(7, $priorityAlpha['next']);
        $t->same(true, $priorityAlpha['descending']);

        $enabledEmpty = $plan['index_updates'][2];
        $t->same('idx_plugin_enabled', $enabledEmpty['index']);
        $t->same(3, $enabledEmpty['rowid']);
        $t->same(null, $enabledEmpty['current']);
        $t->same(0, $enabledEmpty['next']);
        $t->same(false, $enabledEmpty['delete']);
        $t->same(true, $enabledEmpty['insert']);
    },

    'handles generated JSONB sources unique conflicts and ignored non generated indexes' => static function (TestRunner $t): void {
        $createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) VIRTUAL
)
SQL;

        $alpha = new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'alpha']]));
        $beta = new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'beta']]));
        $gamma = new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'gamma']]));
        $rows = [
            ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => $alpha],
            ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => $beta],
        ];
        $indexes = [
            ['name' => 'idx_plugin_slug', 'rootPage' => 9, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
            ['name' => 'idx_option_name', 'rootPage' => 10, 'sql' => 'CREATE INDEX idx_option_name ON wp_options(option_name)'],
        ];

        $plan = SQLiteGeneratedJsonPathIndexPlan::plan($createTableSql, $rows, $indexes, [
            ['rowid' => 1, 'mutations' => [
                ['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'gamma'],
            ]],
        ]);

        $t->same(1, $plan['changes']);
        $t->same(['alpha', 'beta'], array_column($plan['before'], 'plugin_slug'));
        $t->same(['gamma', 'beta'], array_column($plan['after'], 'plugin_slug'));
        $t->same(1, count($plan['index_updates']));
        $t->same('idx_plugin_slug', $plan['index_updates'][0]['index']);
        $t->same(9, $plan['index_updates'][0]['rootPage']);
        $t->same('plugin_slug', $plan['index_updates'][0]['column']);
        $t->same('$.plugin.slug', $plan['index_updates'][0]['path']);
        $t->same('alpha', $plan['index_updates'][0]['current']);
        $t->same('gamma', $plan['index_updates'][0]['next']);
        $t->same('NOCASE', $plan['index_updates'][0]['collation']);
        $t->same(true, $plan['index_updates'][0]['unique']);
        $t->same(SQLiteJsonCanonical::encodeDecodedJson(['plugin' => ['slug' => 'gamma']]), $plan['after'][0]['option_value']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::plan($createTableSql, $rows, $indexes, [
            ['rowid' => 1, 'mutations' => [
                ['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'beta'],
            ]],
        ]));
    },

    'rejects malformed generated JSON paths and missing index coverage' => static function (TestRunner $t): void {
        $rows = [
            ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"enabled":1}}'],
        ];

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::plan(
            "CREATE TABLE wp_options(option_id INTEGER, option_value TEXT, bad INTEGER AS (json_extract(option_value, '$.bad[')))",
            $rows,
            [['name' => 'idx_bad', 'sql' => 'CREATE INDEX idx_bad ON wp_options(bad)']],
            [],
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::plan(
            "CREATE TABLE wp_options(option_id INTEGER, option_value TEXT, enabled INTEGER AS (json_extract(option_value, '$.plugin.enabled')))",
            $rows,
            [['name' => 'idx_option_name', 'sql' => 'CREATE INDEX idx_option_name ON wp_options(option_name)']],
            [],
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::plan(
            "CREATE TABLE wp_options(option_id INTEGER, option_value TEXT, enabled INTEGER AS (json_extract(option_value, '$.plugin.enabled')), enabled2 INTEGER AS (enabled + 1))",
            $rows,
            [['name' => 'idx_enabled', 'sql' => 'CREATE INDEX idx_enabled ON wp_options(enabled)']],
            [['rowid' => 1, 'mutations' => [['function' => 'json_set', 'path' => 7, 'value' => 1]]]],
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::plan(
            "CREATE TABLE wp_options(option_id INTEGER, option_value TEXT, a INTEGER AS (b), b INTEGER AS (a))",
            $rows,
            [['name' => 'idx_a', 'sql' => 'CREATE INDEX idx_a ON wp_options(a)']],
            [],
        ));
    },
];
