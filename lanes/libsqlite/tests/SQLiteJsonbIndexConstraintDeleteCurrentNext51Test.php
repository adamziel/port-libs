<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;
use PortLibs\LibSqlite\SQLiteJsonB;

$tests = [];

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

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$rows = [
    ['option_id' => 10, 'option_name' => 'plugin_alpha', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'rank' => 40, 'enabled' => 1]]), 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_beta', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'rank' => 30, 'enabled' => 1]]), 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'plugin_gamma', 'option_value' => $jsonb(['plugin' => ['slug' => 'gamma', 'rank' => 20, 'enabled' => 0]]), 'autoload' => 'no'],
    ['option_id' => 13, 'option_name' => 'plugin_delta', 'option_value' => $jsonb(['plugin' => ['slug' => 'delta', 'rank' => 10]]), 'autoload' => 'no'],
    ['option_id' => 14, 'option_name' => 'plugin_empty', 'option_value' => $jsonb(['plugin' => ['rank' => 5, 'enabled' => 1]]), 'autoload' => 'yes'],
];

$indexes = [
    ['name' => 'idx_plugin_slug_unique', 'rootPage' => 31, 'unique' => true, 'sql' => 'CREATE UNIQUE INDEX idx_plugin_slug_unique ON wp_options(plugin_slug COLLATE NOCASE) WHERE plugin_slug IS NOT NULL'],
    ['name' => 'idx_plugin_rank_desc', 'rootPage' => 32, 'sql' => 'CREATE INDEX idx_plugin_rank_desc ON wp_options(plugin_rank DESC) WHERE plugin_rank IS NOT NULL'],
    ['name' => 'idx_plugin_enabled', 'rootPage' => 33, 'sql' => 'CREATE INDEX idx_plugin_enabled ON wp_options(plugin_enabled) WHERE plugin_enabled IS NOT NULL'],
];

$plan = static fn (array $deleteRowids = [10, 12], int $pageSize = 512): array => SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, $rows, $indexes, $deleteRowids, $pageSize);

$tests['jsonb index constraint delete current next51 generated metadata and row counts'] = static function (TestRunner $t) use ($plan): void {
    $data = $plan();

    $t->same('wp_options', $data['table']);
    $t->same(['plugin_slug', 'plugin_rank', 'plugin_enabled'], array_column($data['generated_columns'], 'name'));
    $t->same(5, count($data['current']));
    $t->same(3, count($data['next']));
    $t->same(2, $data['changes']);
    $t->same(512, $data['pageSize']);
};

$tests['jsonb index constraint delete current next51 deletes selected current rows only'] = static function (TestRunner $t) use ($plan): void {
    $data = $plan();

    $t->same([10, 12], array_column($data['deleted_rows'], 'option_id'));
    $t->same([11, 13, 14], array_column($data['next'], 'option_id'));
    $t->same([], $data['skipped_rowids']);
    $t->same(['alpha', 'gamma'], array_column($data['deleted_rows'], 'plugin_slug'));
};

$tests['jsonb index constraint delete current next51 emits current index deletes without inserts'] = static function (TestRunner $t) use ($plan): void {
    $deletes = $plan()['index_deletes'];

    $t->same(6, count($deletes));
    $t->same(['idx_plugin_slug_unique', 'idx_plugin_rank_desc', 'idx_plugin_enabled', 'idx_plugin_slug_unique', 'idx_plugin_rank_desc', 'idx_plugin_enabled'], array_column($deletes, 'index'));
    $t->same([10, 10, 10, 12, 12, 12], array_column($deletes, 'rowid'));
    $t->same(['alpha', 40, 1, 'gamma', 20, 0], array_column($deletes, 'current'));
    $t->same([true, true, true, true, true, true], array_column($deletes, 'delete'));
    $t->same([false, false, false, false, false, false], array_column($deletes, 'insert'));
};

$tests['jsonb index constraint delete current next51 materializes before and after leaf images'] = static function (TestRunner $t) use ($plan): void {
    $btree = $plan()['btree_indexes'];

    $t->same(['idx_plugin_slug_unique', 'idx_plugin_rank_desc', 'idx_plugin_enabled'], array_keys($btree));
    $t->same(['alpha', 'beta', 'delta', 'gamma'], array_column($btree['idx_plugin_slug_unique']['current_entries'], 'key'));
    $t->same(['beta', 'delta'], array_column($btree['idx_plugin_slug_unique']['next_entries'], 'key'));
    $t->same([40, 30, 20, 10, 5], array_column($btree['idx_plugin_rank_desc']['current_entries'], 'key'));
    $t->same([30, 10, 5], array_column($btree['idx_plugin_rank_desc']['next_entries'], 'key'));
    $t->true($btree['idx_plugin_slug_unique']['leaf_page_changed']);
    $t->true($btree['idx_plugin_rank_desc']['leaf_page_changed']);
    $t->true($btree['idx_plugin_enabled']['leaf_page_changed']);
};

$tests['jsonb index constraint delete current next51 emits delete btree cells'] = static function (TestRunner $t) use ($plan): void {
    $actions = $plan()['btree_actions'];

    $t->same(6, count($actions));
    $t->same(6, $plan()['btree_action_count']);
    $t->same(['delete', 'delete', 'delete', 'delete', 'delete', 'delete'], array_column($actions, 'action'));
    $t->same(['alpha', 40, 1, 'gamma', 20, 0], array_column($actions, 'key'));
    $t->same([31, 32, 33, 31, 32, 33], array_column($actions, 'rootPage'));
    $t->same([512, 512, 512, 512, 512, 512], array_column($actions, 'pageSize'));
    $t->true($actions[0]['cell_bytes'] > $actions[2]['cell_bytes']);
};

$tests['jsonb index constraint delete current next51 missing delete rowid is skipped'] = static function (TestRunner $t) use ($plan): void {
    $data = $plan([11, 99, '404']);

    $t->same([11], array_column($data['deleted_rows'], 'option_id'));
    $t->same([99, '404'], $data['skipped_rowids']);
    $t->same([10, 12, 13, 14], array_column($data['next'], 'option_id'));
    $t->same(3, count($data['index_deletes']));
};

$tests['jsonb index constraint delete current next51 unique index key is released for remaining rows'] = static function (TestRunner $t) use ($createTableSql, $rows, $indexes, $jsonb): void {
    $withConflict = $rows;
    $withConflict[] = ['option_id' => 15, 'option_name' => 'plugin_alpha_new', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'rank' => 1]]), 'autoload' => 'no'];

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, $withConflict, $indexes, [12]));
    $released = SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, $withConflict, $indexes, [10]);
    $t->same([10], array_column($released['deleted_rows'], 'option_id'));
    $t->same(['alpha', 'beta', 'delta', 'gamma'], array_column($released['btree_indexes']['idx_plugin_slug_unique']['next_entries'], 'key'));
};

$tests['jsonb index constraint delete current next51 invalid inputs are rejected'] = static function (TestRunner $t) use ($createTableSql, $rows, $indexes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, $rows, $indexes, [[]]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, $rows, $indexes, [10], 128));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan('CREATE TABLE t(a INTEGER)', $rows, $indexes, [10]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan($createTableSql, [['option_name' => 'missing rowid']], $indexes, [10]));
};

foreach ([10, 11, 12, 13, 14] as $rowid) {
    $tests["jsonb index constraint delete current next51 single row delete {$rowid}"] = static function (TestRunner $t) use ($plan, $rowid): void {
        $data = $plan([$rowid]);

        $t->same([$rowid], array_column($data['deleted_rows'], 'option_id'));
        $t->same(4, count($data['next']));
        $t->same(1, $data['changes']);
        $t->true($data['btree_action_count'] >= 2);
    };
}

foreach ([[10, 11], [10, 13], [11, 14], [12, 14], [10, 12, 14], [11, 12, 13]] as $set) {
    $label = implode('-', $set);
    $tests["jsonb index constraint delete current next51 multi row delete {$label}"] = static function (TestRunner $t) use ($plan, $set): void {
        $data = $plan($set);

        $t->same($set, array_column($data['deleted_rows'], 'option_id'));
        $t->same(count($set), $data['changes']);
        $t->same(5 - count($set), count($data['next']));
        $t->true($data['btree_action_count'] >= count($set) * 2);
    };
}

foreach ([
    'idx_plugin_slug_unique' => [31, 'NOCASE', false, true],
    'idx_plugin_rank_desc' => [32, 'BINARY', true, false],
    'idx_plugin_enabled' => [33, 'BINARY', false, false],
] as $indexName => [$rootPage, $collation, $descending, $unique]) {
    $tests["jsonb index constraint delete current next51 btree metadata {$indexName}"] = static function (TestRunner $t) use ($plan, $indexName, $rootPage, $collation, $descending, $unique): void {
        $index = $plan()['btree_indexes'][$indexName];

        $t->same($rootPage, $index['rootPage']);
        $t->same($collation, $index['collation']);
        $t->same($descending, $index['descending']);
        $t->same($unique, $index['unique']);
        $t->same(2, $index['deleted_cell_count']);
    };
}

foreach ([512, 1024, 2048, 4096] as $pageSize) {
    $tests["jsonb index constraint delete current next51 page size {$pageSize}"] = static function (TestRunner $t) use ($plan, $pageSize): void {
        $data = $plan([10, 12], $pageSize);

        $t->same($pageSize, $data['pageSize']);
        $t->same($pageSize * 2, strlen($data['btree_indexes']['idx_plugin_slug_unique']['current_leaf_page_hex']));
        $t->same($pageSize * 2, strlen($data['btree_indexes']['idx_plugin_slug_unique']['next_leaf_page_hex']));
    };
}

foreach ([
    [10, 'idx_plugin_slug_unique', 'alpha', ['alpha', 10]],
    [10, 'idx_plugin_rank_desc', 40, [40, 10]],
    [10, 'idx_plugin_enabled', 1, [1, 10]],
    [12, 'idx_plugin_slug_unique', 'gamma', ['gamma', 12]],
    [12, 'idx_plugin_rank_desc', 20, [20, 12]],
    [12, 'idx_plugin_enabled', 0, [0, 12]],
] as [$rowid, $indexName, $key, $record]) {
    $tests["jsonb index constraint delete current next51 action {$rowid} {$indexName}"] = static function (TestRunner $t) use ($plan, $rowid, $indexName, $key, $record): void {
        $matches = array_values(array_filter($plan()['btree_actions'], static fn (array $action): bool => $action['rowid'] === $rowid && $action['index'] === $indexName));

        $t->same(1, count($matches));
        $t->same($key, $matches[0]['key']);
        $t->same($record, $matches[0]['record']);
        $t->same(bin2hex(hex2bin($matches[0]['cell_hex'])), $matches[0]['cell_hex']);
    };
}

foreach ([
    [10, ['beta', 'delta', 'gamma']],
    [11, ['alpha', 'delta', 'gamma']],
    [12, ['alpha', 'beta', 'delta']],
    [13, ['alpha', 'beta', 'gamma']],
    [14, ['alpha', 'beta', 'delta', 'gamma']],
] as [$rowid, $remainingSlugs]) {
    $tests["jsonb index constraint delete current next51 remaining slug keys after {$rowid}"] = static function (TestRunner $t) use ($plan, $rowid, $remainingSlugs): void {
        $data = $plan([$rowid]);

        $t->same($remainingSlugs, array_column($data['btree_indexes']['idx_plugin_slug_unique']['next_entries'], 'key'));
    };
}

foreach ([
    [10, [30, 20, 10, 5]],
    [11, [40, 20, 10, 5]],
    [12, [40, 30, 10, 5]],
    [13, [40, 30, 20, 5]],
    [14, [40, 30, 20, 10]],
] as [$rowid, $remainingRanks]) {
    $tests["jsonb index constraint delete current next51 remaining rank keys after {$rowid}"] = static function (TestRunner $t) use ($plan, $rowid, $remainingRanks): void {
        $data = $plan([$rowid]);

        $t->same($remainingRanks, array_column($data['btree_indexes']['idx_plugin_rank_desc']['next_entries'], 'key'));
    };
}

foreach ([
    [10, [0, 1, 1]],
    [11, [0, 1, 1]],
    [12, [1, 1, 1]],
    [13, [0, 1, 1, 1]],
    [14, [0, 1, 1]],
] as [$rowid, $remainingEnabled]) {
    $tests["jsonb index constraint delete current next51 remaining enabled keys after {$rowid}"] = static function (TestRunner $t) use ($plan, $rowid, $remainingEnabled): void {
        $data = $plan([$rowid]);

        $t->same($remainingEnabled, array_column($data['btree_indexes']['idx_plugin_enabled']['next_entries'], 'key'));
    };
}

return $tests;
