<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record193 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords193 = [
    $record193('table', 'wp_option_groups', 'wp_option_groups', 4, 'CREATE TABLE wp_option_groups(site_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, label TEXT, PRIMARY KEY(site_id, option_name))', 1),
    $record193('table', 'wp_options_import', 'wp_options_import', 5, 'CREATE TABLE wp_options_import(import_id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, option_value TEXT, FOREIGN KEY(site_id, option_name) REFERENCES wp_option_groups(site_id, option_name))', 2),
    $record193('index', 'wp_option_groups_name_site_unique', 'wp_option_groups', 6, 'CREATE UNIQUE INDEX wp_option_groups_name_site_unique ON wp_option_groups(option_name COLLATE NOCASE, site_id)', 3),
    $record193('index', 'wp_options_import_fk_lookup', 'wp_options_import', 7, 'CREATE INDEX wp_options_import_fk_lookup ON wp_options_import(site_id, option_name COLLATE NOCASE)', 4),
];
$nextRecords193 = [
    $currentRecords193[0],
    $currentRecords193[1],
    $record193('index', 'wp_option_groups_site_name_unique', 'wp_option_groups', 8, 'CREATE UNIQUE INDEX wp_option_groups_site_name_unique ON wp_option_groups(site_id, option_name COLLATE NOCASE)', 5),
    $currentRecords193[3],
];
$sameRecords193 = $currentRecords193;
$currentTables193 = [
    'wp_option_groups' => [
        ['rowid' => 1, 'site_id' => 1, 'option_name' => 'active_plugins', 'label' => 'plugins'],
        ['rowid' => 2, 'site_id' => 2, 'option_name' => 'stylesheet', 'label' => 'theme'],
    ],
    'wp_options_import' => [
        ['rowid' => 10, 'import_id' => 10, 'site_id' => 1, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'],
    ],
];
$nextTables193 = $currentTables193;

$page193 = static fn (
    int $offset = 0,
    int $limit = 193,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_option_groups_site_name_unique)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog193(
    $currentRecords193,
    $currentTables193,
    $nextRecords ?? $nextRecords193,
    $nextTables193,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt193 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default193 = static fn (): array => $page193();
$blocked193 = static fn (): array => $page193(nextRecords: $sameRecords193);
$orderRows193 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentUniqueOrderRows193($currentRecords193);
$nextOrderRows193 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentUniqueOrderRows193($nextRecords193, 'next');
$tableValued193 = static fn (): array => $page193(indexSql: "pragma_index_xinfo('wp_option_groups_site_name_unique')", tableValued: true);

$cases193 = [
    'status ok after ordered unique repair' => [$default193, 'status', 'ok'],
    'default limit' => [$default193, 'limit', 193],
    'complete true' => [$default193, 'complete', true],
    'next null' => [$default193, 'next', null],
    'next ready true' => [$default193, 'next_state.ready', true],
    'next blocking empty' => [$default193, 'next_state.blocking', []],
    'source id length' => [static fn (): array => ['len' => strlen($page193()['source_id'])], 'len', 64],
    'current source label' => [$default193, 'current_source.foreign_key_parent_unique_order_source', 'pragma_index_xinfo_parent_unique_column_order'],
    'next source label' => [$default193, 'next_source.foreign_key_parent_unique_order_source', 'pragma_index_xinfo_parent_unique_column_order'],
    'current source summary' => [$default193, 'current_source.foreign_key_parent_unique_order.0', 'current:wp_options_import#0->wp_option_groups:wp_option_groups_name_site_unique:column_order_mismatch'],
    'next source summary empty' => [$default193, 'next_source.foreign_key_parent_unique_order', []],
    'current order rows' => [$default193, 'current.foreign_key_parent_unique_order_rows', 1],
    'current order count rows' => [$default193, 'current.foreign_key_parent_unique_order.rows', 1],
    'current order mismatch count' => [$default193, 'current.foreign_key_parent_unique_order.order_mismatch', 1],
    'next order rows repaired' => [$default193, 'next_counts.foreign_key_parent_unique_order_rows', 0],
    'next order count rows repaired' => [$default193, 'next_counts.foreign_key_parent_unique_order.rows', 0],
    'next order mismatch repaired' => [$default193, 'next_counts.foreign_key_parent_unique_order.order_mismatch', 0],
    'delta order rows' => [$default193, 'delta.foreign_key_parent_unique_order_rows', -1],
    'delta order mismatches' => [$default193, 'delta.foreign_key_parent_unique_order_mismatches', -1],
    'delta order repaired' => [$default193, 'delta.foreign_key_parent_unique_order_repaired', true],
    'delta order changed' => [$default193, 'delta.foreign_key_parent_unique_order_changed', true],
    'delta cleared remains true' => [$default193, 'delta.cleared', true],
    'decorates missing parent key with wrong order index' => [$default193, 'rows.9.rejected_parent_unique_index', 'wp_option_groups_name_site_unique'],
    'decorates missing parent key with wrong order reason' => [$default193, 'rows.9.rejected_parent_unique_reason', 'column_order_mismatch'],
    'order row kind' => [$default193, 'rows.21.kind', 'foreign_key_parent_unique_order'],
    'order row table' => [$default193, 'rows.21.table', 'wp_options_import'],
    'order row parent' => [$default193, 'rows.21.parent', 'wp_option_groups'],
    'order row index' => [$default193, 'rows.21.index', 'wp_option_groups_name_site_unique'],
    'order row status' => [$default193, 'rows.21.status', 'order_mismatch'],
    'order row parent first column' => [$default193, 'rows.21.parent_columns.0', 'site_id'],
    'order row parent second column' => [$default193, 'rows.21.parent_columns.1', 'option_name'],
    'order row index first column' => [$default193, 'rows.21.index_key_columns.0', 'option_name'],
    'order row index second column' => [$default193, 'rows.21.index_key_columns.1', 'site_id'],
    'order row unique flag' => [$default193, 'rows.21.index_unique', 1],
    'order row partial flag' => [$default193, 'rows.21.index_partial', 0],
    'order row expression count' => [$default193, 'rows.21.index_expression_keys', 0],
    'order row key count' => [$default193, 'rows.21.index_key_count', 2],
    'order row message' => [$default193, 'rows.21.message', 'foreign key wp_options_import->wp_option_groups cannot use UNIQUE index wp_option_groups_name_site_unique because parent key columns are in a different order'],
    'blocked remains blocked' => [$blocked193, 'status', 'blocked'],
    'blocked next order rows' => [$blocked193, 'next_counts.foreign_key_parent_unique_order_rows', 1],
    'blocked next includes parent blocker' => [$blocked193, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked next includes order blocker' => [$blocked193, 'next_state.blocking.1', 'foreign_key_parent_unique_column_order'],
    'blocked repaired false' => [$blocked193, 'delta.foreign_key_parent_unique_order_repaired', false],
    'blocked changed false' => [$blocked193, 'delta.foreign_key_parent_unique_order_changed', false],
    'helper row side' => [$orderRows193, '0.side', 'current'],
    'helper row key set first' => [$orderRows193, '0.index_key_columns.0', 'option_name'],
    'helper row parent set first' => [$orderRows193, '0.parent_columns.0', 'site_id'],
    'helper next rows empty' => [$nextOrderRows193, '', []],
    'table valued flag preserved' => [$tableValued193, 'current_source.table_valued_index_xinfo', true],
];

$tests = [];
foreach ($cases193 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent unique order current source next193 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt193): void {
        $value = $factory();
        if ($path !== '') {
            $value = $valueAt193($value, $path);
        }
        $t->same($expected, $value);
    };
}

$tests['pragma index xinfo foreignkey parent unique order current source next193 paginates order rows'] = static function (TestRunner $t) use ($page193): void {
    $first = $page193(0, 21);
    $second = $page193(21, 1, $first['next']);

    $t->same(21, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 21], $first['next']);
    $t->same('foreign_key_parent_unique_order', $second['rows'][0]['kind']);
    $t->same('wp_option_groups_name_site_unique', $second['rows'][0]['index']);
    $t->same(null, $second['next']);
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 source changes with ordered repair'] = static function (TestRunner $t) use ($page193, $sameRecords193): void {
    $changed = $page193();
    $same = $page193(nextRecords: $sameRecords193);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_parent_unique_order_changed']);
    $t->same(false, $same['delta']['foreign_key_parent_unique_order_changed']);
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 ignores subset unique indexes'] = static function (TestRunner $t) use ($record193, $currentRecords193): void {
    $records = [
        $currentRecords193[0],
        $currentRecords193[1],
        $record193('index', 'wp_option_groups_site_only_unique', 'wp_option_groups', 9, 'CREATE UNIQUE INDEX wp_option_groups_site_only_unique ON wp_option_groups(site_id)', 9),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentUniqueOrderRows193($records));
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 ignores expression unique indexes'] = static function (TestRunner $t) use ($record193, $currentRecords193): void {
    $records = [
        $currentRecords193[0],
        $currentRecords193[1],
        $record193('index', 'wp_option_groups_expr_unique', 'wp_option_groups', 10, 'CREATE UNIQUE INDEX wp_option_groups_expr_unique ON wp_option_groups(lower(option_name), site_id)', 10),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentUniqueOrderRows193($records));
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 rejects stale cursor'] = static function (TestRunner $t) use ($page193, $sameRecords193): void {
    $first = $page193(0, 21);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page193(21, 1, $first['next'], nextRecords: $sameRecords193));
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 rejects stale offset cursor'] = static function (TestRunner $t) use ($page193): void {
    $first = $page193(0, 21);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page193(22, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentUniqueOrderRows193([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 rejects negative offset'] = static function (TestRunner $t) use ($page193): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page193(offset: -1));
};

$tests['pragma index xinfo foreignkey parent unique order current source next193 rejects zero limit'] = static function (TestRunner $t) use ($page193): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page193(limit: 0));
};

return $tests;
