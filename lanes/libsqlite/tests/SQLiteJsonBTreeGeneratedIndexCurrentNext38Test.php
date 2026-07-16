<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.enabled')) VIRTUAL
)
SQL;

$rows = [
    ['option_id' => 10, 'option_name' => 'plugin_alpha', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'Alpha', 'rank' => 20, 'enabled' => 1]])), 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_beta', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'beta', 'rank' => 10, 'enabled' => 0]])), 'autoload' => 'no'],
    ['option_id' => 12, 'option_name' => 'plugin_gamma', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'gamma', 'rank' => 30]])), 'autoload' => 'yes'],
];

$indexes = [
    ['name' => 'idx_plugin_slug', 'rootPage' => 22, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_rank', 'rootPage' => 23, 'sql' => 'CREATE INDEX idx_plugin_rank ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
    ['name' => 'idx_plugin_enabled', 'rootPage' => 24, 'sql' => 'CREATE INDEX idx_plugin_enabled ON wp_options(plugin_enabled) WHERE plugin_enabled IS NOT NULL'],
];

$plan = static fn (): array => SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $rows, $indexes, [
    ['rowid' => 10, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.slug', 'value' => 'delta'],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 40],
    ]],
    ['rowid' => 12, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.enabled', 'value' => 1],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 5],
    ]],
], 512);

return [
    'jsonb generated index btree yield current next38 generated column metadata' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same('wp_options', $data['table']);
        $t->same(3, count($data['generated_columns']));
        $t->same(['plugin_slug', 'plugin_rank', 'plugin_enabled'], array_column($data['generated_columns'], 'name'));
        $t->same(['$.plugin.slug', '$.plugin.rank', '$.plugin.enabled'], array_column($data['generated_columns'], 'path'));
        $t->same(['STORED', 'VIRTUAL', 'VIRTUAL'], array_column($data['generated_columns'], 'storage'));
        $t->same(3, count($data['before']));
        $t->same(3, count($data['after']));
        $t->same(2, $data['changes']);
        $t->same(2, count($data['changed_rows']));
        $t->same(512, $data['pageSize']);
    },
    'jsonb generated index btree yield current next38 before and after generated values' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same(['Alpha', 'beta', 'gamma'], array_column($data['before'], 'plugin_slug'));
        $t->same([20, 10, 30], array_column($data['before'], 'plugin_rank'));
        $t->same([1, 0, null], array_column($data['before'], 'plugin_enabled'));
        $t->same(['delta', 'beta', 'gamma'], array_column($data['after'], 'plugin_slug'));
        $t->same([40, 10, 5], array_column($data['after'], 'plugin_rank'));
        $t->same([1, 0, 1], array_column($data['after'], 'plugin_enabled'));
        $t->same(SQLiteJsonCanonical::encodeDecodedJson(['plugin' => ['slug' => 'delta', 'rank' => 40, 'enabled' => 1]]), $data['after'][0]['option_value']);
        $t->same(SQLiteJsonCanonical::encodeDecodedJson(['plugin' => ['slug' => 'gamma', 'rank' => 5, 'enabled' => 1]]), $data['after'][2]['option_value']);
    },
    'jsonb generated index btree yield current next38 logical index updates' => static function (TestRunner $t) use ($plan): void {
        $updates = $plan()['index_updates'];

        $t->same(4, count($updates));
        $t->same(['idx_plugin_slug', 'idx_plugin_rank', 'idx_plugin_rank', 'idx_plugin_enabled'], array_column($updates, 'index'));
        $t->same([10, 10, 12, 12], array_column($updates, 'rowid'));
        $t->same(['Alpha', 20, 30, null], array_column($updates, 'current'));
        $t->same(['delta', 40, 5, 1], array_column($updates, 'next'));
        $t->same([true, true, true, false], array_column($updates, 'delete'));
        $t->same([true, true, true, true], array_column($updates, 'insert'));
        $t->same(['NOCASE', 'BINARY', 'BINARY', 'BINARY'], array_column($updates, 'collation'));
        $t->same([false, true, true, false], array_column($updates, 'descending'));
        $t->same(['plugin_slug', 'plugin_rank', 'plugin_rank', 'plugin_enabled'], array_column($updates, 'column'));
        $t->same(['$.plugin.slug', '$.plugin.rank', '$.plugin.rank', '$.plugin.enabled'], array_column($updates, 'path'));
    },
    'jsonb generated index btree yield current next38 materializes ordered leaf images' => static function (TestRunner $t) use ($plan): void {
        $indexes = $plan()['btree_indexes'];

        $t->same(['idx_plugin_slug', 'idx_plugin_rank', 'idx_plugin_enabled'], array_keys($indexes));
        $t->same(22, $indexes['idx_plugin_slug']['rootPage']);
        $t->same(23, $indexes['idx_plugin_rank']['rootPage']);
        $t->same(24, $indexes['idx_plugin_enabled']['rootPage']);
        $t->same(['Alpha', 'beta', 'gamma'], array_column($indexes['idx_plugin_slug']['current_entries'], 'key'));
        $t->same(['beta', 'delta', 'gamma'], array_column($indexes['idx_plugin_slug']['next_entries'], 'key'));
        $t->same([30, 20, 10], array_column($indexes['idx_plugin_rank']['current_entries'], 'key'));
        $t->same([40, 10, 5], array_column($indexes['idx_plugin_rank']['next_entries'], 'key'));
        $t->same([0, 1], array_column($indexes['idx_plugin_enabled']['current_entries'], 'key'));
        $t->same([0, 1, 1], array_column($indexes['idx_plugin_enabled']['next_entries'], 'key'));
        $t->same(3, $indexes['idx_plugin_slug']['current_cell_count']);
        $t->same(3, $indexes['idx_plugin_slug']['next_cell_count']);
        $t->same(2, $indexes['idx_plugin_enabled']['current_cell_count']);
        $t->same(3, $indexes['idx_plugin_enabled']['next_cell_count']);
        $t->true($indexes['idx_plugin_slug']['leaf_page_changed']);
        $t->true($indexes['idx_plugin_rank']['leaf_page_changed']);
        $t->true($indexes['idx_plugin_enabled']['leaf_page_changed']);
        $t->same(1024, strlen($indexes['idx_plugin_slug']['current_leaf_page_hex']));
        $t->same(1024, strlen($indexes['idx_plugin_slug']['next_leaf_page_hex']));
        $t->true($indexes['idx_plugin_rank']['current_leaf_page_hex'] !== $indexes['idx_plugin_rank']['next_leaf_page_hex']);
        $t->same(['plugin_slug', 'plugin_rank', 'plugin_enabled'], array_column($indexes, 'column'));
        $t->same(['NOCASE', 'BINARY', 'BINARY'], array_column($indexes, 'collation'));
        $t->same([false, true, false], array_column($indexes, 'descending'));
    },
    'jsonb generated index btree yield current next38 emits delete and insert cells' => static function (TestRunner $t) use ($plan): void {
        $actions = $plan()['btree_actions'];

        $t->same(7, count($actions));
        $t->same(7, $plan()['btree_action_count']);
        $t->same(['delete', 'insert', 'delete', 'insert', 'delete', 'insert', 'insert'], array_column($actions, 'action'));
        $t->same(['idx_plugin_slug', 'idx_plugin_slug', 'idx_plugin_rank', 'idx_plugin_rank', 'idx_plugin_rank', 'idx_plugin_rank', 'idx_plugin_enabled'], array_column($actions, 'index'));
        $t->same(['Alpha', 'delta', 20, 40, 30, 5, 1], array_column($actions, 'key'));
        $t->same([['Alpha', 10], ['delta', 10], [20, 10], [40, 10], [30, 12], [5, 12], [1, 12]], array_column($actions, 'record'));
        $t->same([22, 22, 23, 23, 23, 23, 24], array_column($actions, 'rootPage'));
        $t->same([512, 512, 512, 512, 512, 512, 512], array_column($actions, 'pageSize'));
        $t->true($actions[0]['cell_bytes'] > 0);
        $t->true($actions[1]['cell_bytes'] > $actions[6]['cell_bytes']);
        $t->same(bin2hex(hex2bin($actions[2]['cell_hex'])), $actions[2]['cell_hex']);
        $t->same(bin2hex(hex2bin($actions[3]['record_hex'])), $actions[3]['record_hex']);
        $t->same(['NOCASE', 'NOCASE', 'BINARY', 'BINARY', 'BINARY', 'BINARY', 'BINARY'], array_column($actions, 'collation'));
        $t->same([false, false, true, true, true, true, false], array_column($actions, 'descending'));
    },
    'jsonb generated index btree yield current next38 keeps unchanged rows and validates conflicts' => static function (TestRunner $t) use ($createTableSql, $rows, $indexes): void {
        $unchanged = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $rows, $indexes, [
            ['rowid' => 11, 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.plugin.slug', 'value' => 'beta'],
            ]],
            ['rowid' => 99, 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.plugin.slug', 'value' => 'ignored'],
            ]],
        ]);

        $t->same(1, $unchanged['changes']);
        $t->same([], $unchanged['index_updates']);
        $t->same([], $unchanged['btree_actions']);
        $t->same(0, $unchanged['btree_action_count']);
        $t->same(['Alpha', 'beta', 'gamma'], array_column($unchanged['btree_indexes']['idx_plugin_slug']['next_entries'], 'key'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $rows, $indexes, [
            ['rowid' => 10, 'mutations' => [
                ['function' => 'jsonb_set', 'path' => '$.plugin.slug', 'value' => 'beta'],
            ]],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $rows, $indexes, [], 128));
    },
];
