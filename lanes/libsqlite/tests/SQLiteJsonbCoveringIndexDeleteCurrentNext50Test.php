<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  site_id INTEGER,
  plugin_slug TEXT GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.rank')) VIRTUAL,
  plugin_enabled INTEGER GENERATED ALWAYS AS (jsonb_extract(option_value, '$.plugin.enabled')) VIRTUAL
)
SQL;

$rows = [
    ['option_id' => 201, 'option_name' => 'plugin_alpha', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'alpha', 'rank' => 20, 'enabled' => 1]])), 'autoload' => 'yes', 'site_id' => 1],
    ['option_id' => 202, 'option_name' => 'plugin_beta', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'Beta', 'rank' => 10, 'enabled' => 1]])), 'autoload' => 'yes', 'site_id' => 1],
    ['option_id' => 203, 'option_name' => 'plugin_gamma', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'gamma', 'rank' => 30, 'enabled' => 0]])), 'autoload' => 'no', 'site_id' => 2],
    ['option_id' => 204, 'option_name' => 'plugin_delta', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['slug' => 'delta', 'rank' => 15]])), 'autoload' => 'yes', 'site_id' => 2],
];

$indexes = [
    ['name' => 'idx_plugin_slug_covering', 'rootPage' => 51, 'unique' => true, 'coveringColumns' => ['plugin_slug', 'autoload', 'site_id', 'option_id'], 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug_covering ON wp_options(plugin_slug COLLATE NOCASE, autoload, site_id, option_id) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_rank_covering', 'rootPage' => 52, 'coveringColumns' => ['plugin_rank', 'option_name', 'autoload', 'option_id'], 'sql' => 'CREATE INDEX idx_plugin_rank_covering ON wp_options(plugin_rank DESC, option_name, autoload, option_id) WHERE plugin_rank IS NOT NULL'],
    ['name' => 'idx_plugin_enabled_covering', 'rootPage' => 53, 'coveringColumns' => ['plugin_enabled', 'option_name', 'site_id', 'option_id'], 'sql' => 'CREATE INDEX idx_plugin_enabled_covering ON wp_options(plugin_enabled, option_name, site_id, option_id) WHERE plugin_enabled IS NOT NULL'],
];

$plan = static fn (): array => SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($createTableSql, $rows, $indexes, [202, 204, 999], 512);

$tests = [
    'jsonb covering index delete current next50 generated columns and row counts' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();

        $t->same('wp_options', $data['table']);
        $t->same(['plugin_slug', 'plugin_rank', 'plugin_enabled'], array_column($data['generated_columns'], 'name'));
        $t->same(['$.plugin.slug', '$.plugin.rank', '$.plugin.enabled'], array_column($data['generated_columns'], 'path'));
        $t->same(4, count($data['before']));
        $t->same(2, count($data['after']));
        $t->same(2, count($data['deleted_rows']));
        $t->same(2, $data['changes']);
        $t->same([999], $data['missing_rowids']);
        $t->same([201, 203], array_column($data['after'], 'option_id'));
        $t->same(['alpha', 'gamma'], array_column($data['after'], 'plugin_slug'));
        $t->same(512, $data['pageSize']);
    },
    'jsonb covering index delete current next50 keeps covering index leaf order' => static function (TestRunner $t) use ($plan): void {
        $indexes = $plan()['btree_indexes'];

        $t->same(['idx_plugin_slug_covering', 'idx_plugin_rank_covering', 'idx_plugin_enabled_covering'], array_keys($indexes));
        $t->same([51, 52, 53], array_column($indexes, 'rootPage'));
        $t->same(['plugin_slug', 'plugin_rank', 'plugin_enabled'], array_column($indexes, 'column'));
        $t->same(['NOCASE', 'BINARY', 'BINARY'], array_column($indexes, 'collation'));
        $t->same([false, true, false], array_column($indexes, 'descending'));
        $t->same([true, false, false], array_column($indexes, 'unique'));
        $t->same(['alpha', 'Beta', 'delta', 'gamma'], array_column($indexes['idx_plugin_slug_covering']['current_entries'], 'key'));
        $t->same(['alpha', 'gamma'], array_column($indexes['idx_plugin_slug_covering']['next_entries'], 'key'));
        $t->same([30, 20, 15, 10], array_column($indexes['idx_plugin_rank_covering']['current_entries'], 'key'));
        $t->same([30, 20], array_column($indexes['idx_plugin_rank_covering']['next_entries'], 'key'));
        $t->same([0, 1, 1], array_column($indexes['idx_plugin_enabled_covering']['current_entries'], 'key'));
        $t->same([0, 1], array_column($indexes['idx_plugin_enabled_covering']['next_entries'], 'key'));
    },
    'jsonb covering index delete current next50 emits current delete records only' => static function (TestRunner $t) use ($plan): void {
        $entries = $plan()['delete_entries'];

        $t->same(5, count($entries));
        $t->same(['idx_plugin_slug_covering', 'idx_plugin_slug_covering', 'idx_plugin_rank_covering', 'idx_plugin_rank_covering', 'idx_plugin_enabled_covering'], array_column($entries, 'index'));
        $t->same([202, 204, 202, 204, 202], array_column($entries, 'rowid'));
        $t->same(['Beta', 'delta', 10, 15, 1], array_column($entries, 'key'));
        $t->same(['delete-current', 'delete-current', 'delete-current', 'delete-current', 'delete-current'], array_column($entries, 'operation'));
        $t->same([['autoload' => 'yes', 'site_id' => 1], ['autoload' => 'yes', 'site_id' => 2], ['option_name' => 'plugin_beta', 'autoload' => 'yes'], ['option_name' => 'plugin_delta', 'autoload' => 'yes'], ['option_name' => 'plugin_beta', 'site_id' => 1]], array_column($entries, 'coveringValues'));
        $t->same([['Beta', 'yes', 1, 202], ['delta', 'yes', 2, 204], [10, 'plugin_beta', 'yes', 202], [15, 'plugin_delta', 'yes', 204], [1, 'plugin_beta', 1, 202]], array_column($entries, 'record'));
        $t->same(['NOCASE', 'NOCASE', 'BINARY', 'BINARY', 'BINARY'], array_column($entries, 'collation'));
        $t->same([false, false, true, true, false], array_column($entries, 'descending'));
        $t->true(strlen($entries[0]['record_hex']) > strlen($entries[0]['key']));
    },
    'jsonb covering index delete current next50 materializes changed pages and cells' => static function (TestRunner $t) use ($plan): void {
        $data = $plan();
        $actions = $data['btree_actions'];
        $indexes = $data['btree_indexes'];

        $t->same(5, $data['btree_action_count']);
        $t->same(5, count($actions));
        $t->same(['delete', 'delete', 'delete', 'delete', 'delete'], array_column($actions, 'action'));
        $t->same([512, 512, 512, 512, 512], array_column($actions, 'pageSize'));
        $t->same([51, 51, 52, 52, 53], array_column($actions, 'rootPage'));
        $t->true($actions[0]['cell_bytes'] > 0);
        $t->true($actions[4]['cell_bytes'] > 0);
        $t->same(bin2hex(hex2bin($actions[0]['cell_hex'])), $actions[0]['cell_hex']);
        $t->same(bin2hex(hex2bin($actions[3]['record_hex'])), $actions[3]['record_hex']);
        $t->same(1024, strlen($indexes['idx_plugin_slug_covering']['current_leaf_page_hex']));
        $t->same(1024, strlen($indexes['idx_plugin_slug_covering']['next_leaf_page_hex']));
        $t->true($indexes['idx_plugin_slug_covering']['leaf_page_changed']);
        $t->true($indexes['idx_plugin_rank_covering']['leaf_page_changed']);
        $t->true($indexes['idx_plugin_enabled_covering']['leaf_page_changed']);
    },
    'jsonb covering index delete current next50 validates guards' => static function (TestRunner $t) use ($createTableSql, $rows, $indexes): void {
        $noDelete = SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($createTableSql, $rows, $indexes, [999]);

        $t->same(0, $noDelete['changes']);
        $t->same([], $noDelete['delete_entries']);
        $t->same([], $noDelete['btree_actions']);
        $t->same([999], $noDelete['missing_rowids']);
        $t->same(4, count($noDelete['after']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($createTableSql, $rows, $indexes, [202], 128));
        $badRows = $rows;
        unset($badRows[1]['autoload']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($createTableSql, $badRows, $indexes, [202]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan($createTableSql, $rows, $indexes, [new stdClass()]));
    },
];

foreach ([
    ['idx_plugin_slug_covering', 4, 2, ['alpha', 'Beta', 'delta', 'gamma'], ['alpha', 'gamma']],
    ['idx_plugin_rank_covering', 4, 2, [30, 20, 15, 10], [30, 20]],
    ['idx_plugin_enabled_covering', 3, 2, [0, 1, 1], [0, 1]],
] as [$indexName, $currentCount, $nextCount, $currentKeys, $nextKeys]) {
    $tests["jsonb covering index delete current next50 index {$indexName} current count"] = static function (TestRunner $t) use ($plan, $indexName, $currentCount): void {
        $t->same($currentCount, $plan()['btree_indexes'][$indexName]['current_cell_count']);
    };
    $tests["jsonb covering index delete current next50 index {$indexName} next count"] = static function (TestRunner $t) use ($plan, $indexName, $nextCount): void {
        $t->same($nextCount, $plan()['btree_indexes'][$indexName]['next_cell_count']);
    };
    $tests["jsonb covering index delete current next50 index {$indexName} current keys"] = static function (TestRunner $t) use ($plan, $indexName, $currentKeys): void {
        $t->same($currentKeys, array_column($plan()['btree_indexes'][$indexName]['current_entries'], 'key'));
    };
    $tests["jsonb covering index delete current next50 index {$indexName} next keys"] = static function (TestRunner $t) use ($plan, $indexName, $nextKeys): void {
        $t->same($nextKeys, array_column($plan()['btree_indexes'][$indexName]['next_entries'], 'key'));
    };
    $tests["jsonb covering index delete current next50 index {$indexName} page changed"] = static function (TestRunner $t) use ($plan, $indexName): void {
        $t->true($plan()['btree_indexes'][$indexName]['leaf_page_changed']);
    };
}

foreach ([
    0 => ['idx_plugin_slug_covering', 202, ['Beta', 'yes', 1, 202], ['autoload' => 'yes', 'site_id' => 1]],
    1 => ['idx_plugin_slug_covering', 204, ['delta', 'yes', 2, 204], ['autoload' => 'yes', 'site_id' => 2]],
    2 => ['idx_plugin_rank_covering', 202, [10, 'plugin_beta', 'yes', 202], ['option_name' => 'plugin_beta', 'autoload' => 'yes']],
    3 => ['idx_plugin_rank_covering', 204, [15, 'plugin_delta', 'yes', 204], ['option_name' => 'plugin_delta', 'autoload' => 'yes']],
    4 => ['idx_plugin_enabled_covering', 202, [1, 'plugin_beta', 1, 202], ['option_name' => 'plugin_beta', 'site_id' => 1]],
] as $offset => [$indexName, $rowid, $record, $coveringValues]) {
    $tests["jsonb covering index delete current next50 action {$offset} index"] = static function (TestRunner $t) use ($plan, $offset, $indexName): void {
        $t->same($indexName, $plan()['btree_actions'][$offset]['index']);
    };
    $tests["jsonb covering index delete current next50 action {$offset} rowid"] = static function (TestRunner $t) use ($plan, $offset, $rowid): void {
        $t->same($rowid, $plan()['btree_actions'][$offset]['rowid']);
    };
    $tests["jsonb covering index delete current next50 action {$offset} record"] = static function (TestRunner $t) use ($plan, $offset, $record): void {
        $t->same($record, $plan()['btree_actions'][$offset]['record']);
    };
    $tests["jsonb covering index delete current next50 action {$offset} covering payload"] = static function (TestRunner $t) use ($plan, $offset, $coveringValues): void {
        $t->same($coveringValues, $plan()['btree_actions'][$offset]['coveringValues']);
    };
    $tests["jsonb covering index delete current next50 action {$offset} cell hex"] = static function (TestRunner $t) use ($plan, $offset): void {
        $cellHex = $plan()['btree_actions'][$offset]['cell_hex'];
        $t->same($cellHex, bin2hex(hex2bin($cellHex)));
    };
    $tests["jsonb covering index delete current next50 action {$offset} record hex"] = static function (TestRunner $t) use ($plan, $offset): void {
        $recordHex = $plan()['btree_actions'][$offset]['record_hex'];
        $t->same($recordHex, bin2hex(hex2bin($recordHex)));
    };
}

foreach ([201, 203] as $position => $rowid) {
    $tests["jsonb covering index delete current next50 surviving row {$rowid} remains in next image"] = static function (TestRunner $t) use ($plan, $position, $rowid): void {
        $t->same($rowid, $plan()['after'][$position]['option_id']);
    };
}

foreach ([202, 204] as $position => $rowid) {
    $tests["jsonb covering index delete current next50 deleted row {$rowid} is preserved as current image"] = static function (TestRunner $t) use ($plan, $position, $rowid): void {
        $t->same($rowid, $plan()['deleted_rows'][$position]['option_id']);
    };
}

return $tests;
