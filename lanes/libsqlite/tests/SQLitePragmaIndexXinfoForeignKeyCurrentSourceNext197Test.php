<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record197 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords197 = [
    $record197('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE NOT NULL, locale TEXT NOT NULL, label TEXT)', 1),
    $record197('table', 'wp_options_import', 'wp_options_import', 5, 'CREATE TABLE wp_options_import(import_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE NOT NULL, locale TEXT NOT NULL, option_value TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale))', 2),
    $record197('index', 'wp_option_names_lookup', 'wp_option_names', 6, 'CREATE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 3),
    $record197('index', 'wp_options_import_fk_lookup', 'wp_options_import', 7, 'CREATE INDEX wp_options_import_fk_lookup ON wp_options_import(option_name COLLATE NOCASE, locale)', 4),
];
$nextRecords197 = [
    $currentRecords197[0],
    $currentRecords197[1],
    $record197('index', 'wp_option_names_unique', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_unique ON wp_option_names(name COLLATE NOCASE, locale)', 5),
    $currentRecords197[3],
];
$sameRecords197 = $currentRecords197;
$currentTables197 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'active_plugins', 'locale' => 'en_US', 'label' => 'plugins'],
        ['rowid' => 2, 'name' => 'stylesheet', 'locale' => 'en_US', 'label' => 'theme'],
    ],
    'wp_options_import' => [
        ['rowid' => 10, 'import_id' => 10, 'option_name' => 'active_plugins', 'locale' => 'en_US', 'option_value' => 'a:0:{}'],
    ],
];
$nextTables197 = $currentTables197;

$page197 = static fn (
    int $offset = 0,
    int $limit = 197,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_import_fk_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog197(
    $currentRecords197,
    $currentTables197,
    $nextRecords ?? $nextRecords197,
    $nextTables197,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt197 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default197 = static fn (): array => $page197();
$blocked197 = static fn (): array => $page197(nextRecords: $sameRecords197);
$nonUniqueRows197 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nonUniqueParentIndexRows197($currentRecords197);
$nextNonUniqueRows197 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nonUniqueParentIndexRows197($nextRecords197, 'next');
$tableValued197 = static fn (): array => $page197(indexSql: "pragma_index_xinfo('wp_options_import_fk_lookup')", tableValued: true);

$cases197 = [
    'status ok after unique parent repair' => [$default197, 'status', 'ok'],
    'default limit' => [$default197, 'limit', 197],
    'complete true' => [$default197, 'complete', true],
    'next null' => [$default197, 'next', null],
    'next ready true' => [$default197, 'next_state.ready', true],
    'next blocking empty' => [$default197, 'next_state.blocking', []],
    'source id length' => [static fn (): array => ['len' => strlen($page197()['source_id'])], 'len', 64],
    'current source label' => [$default197, 'current_source.foreign_key_parent_non_unique_source', 'pragma_index_xinfo_matching_non_unique_parent_indexes'],
    'next source label' => [$default197, 'next_source.foreign_key_parent_non_unique_source', 'pragma_index_xinfo_matching_non_unique_parent_indexes'],
    'current source summary' => [$default197, 'current_source.foreign_key_parent_non_unique.0', 'current:wp_options_import#0->wp_option_names:wp_option_names_lookup:non_unique_matching_parent'],
    'next source summary empty' => [$default197, 'next_source.foreign_key_parent_non_unique', []],
    'current non unique rows' => [$default197, 'current.foreign_key_parent_non_unique_rows', 1],
    'current non unique count rows' => [$default197, 'current.foreign_key_parent_non_unique.rows', 1],
    'current non unique blockers' => [$default197, 'current.foreign_key_parent_non_unique.non_unique_matching_parent', 1],
    'next non unique rows repaired' => [$default197, 'next_counts.foreign_key_parent_non_unique_rows', 0],
    'next non unique count rows repaired' => [$default197, 'next_counts.foreign_key_parent_non_unique.rows', 0],
    'next non unique blockers repaired' => [$default197, 'next_counts.foreign_key_parent_non_unique.non_unique_matching_parent', 0],
    'delta non unique rows' => [$default197, 'delta.foreign_key_parent_non_unique_rows', -1],
    'delta non unique blockers' => [$default197, 'delta.foreign_key_parent_non_unique_blockers', -1],
    'delta non unique repaired' => [$default197, 'delta.foreign_key_parent_non_unique_repaired', true],
    'delta non unique changed' => [$default197, 'delta.foreign_key_parent_non_unique_changed', true],
    'delta cleared remains true' => [$default197, 'delta.cleared', true],
    'decorates missing parent key with non unique index' => [$default197, 'rows.12.rejected_parent_unique_index', 'wp_option_names_lookup'],
    'decorates missing parent key with non unique reason' => [$default197, 'rows.12.rejected_parent_unique_reason', 'non_unique_matching_parent'],
    'non unique row kind' => [$default197, 'rows.24.kind', 'foreign_key_parent_non_unique'],
    'non unique row table' => [$default197, 'rows.24.table', 'wp_options_import'],
    'non unique row parent' => [$default197, 'rows.24.parent', 'wp_option_names'],
    'non unique row index' => [$default197, 'rows.24.index', 'wp_option_names_lookup'],
    'non unique row status' => [$default197, 'rows.24.status', 'non_unique_matching_parent'],
    'non unique row parent first column' => [$default197, 'rows.24.parent_columns.0', 'name'],
    'non unique row parent second column' => [$default197, 'rows.24.parent_columns.1', 'locale'],
    'non unique row index first column' => [$default197, 'rows.24.index_key_columns.0', 'name'],
    'non unique row index second column' => [$default197, 'rows.24.index_key_columns.1', 'locale'],
    'non unique row unique flag' => [$default197, 'rows.24.index_unique', 0],
    'non unique row partial flag' => [$default197, 'rows.24.index_partial', 0],
    'non unique row expression count' => [$default197, 'rows.24.index_expression_keys', 0],
    'non unique row key count' => [$default197, 'rows.24.index_key_count', 2],
    'non unique row message' => [$default197, 'rows.24.message', 'foreign key wp_options_import->wp_option_names cannot use non-UNIQUE index wp_option_names_lookup as a parent key'],
    'blocked remains blocked' => [$blocked197, 'status', 'blocked'],
    'blocked next non unique rows' => [$blocked197, 'next_counts.foreign_key_parent_non_unique_rows', 1],
    'blocked next includes parent blocker' => [$blocked197, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked next includes non unique blocker' => [$blocked197, 'next_state.blocking.1', 'foreign_key_parent_non_unique_index'],
    'blocked repaired false' => [$blocked197, 'delta.foreign_key_parent_non_unique_repaired', false],
    'blocked changed false' => [$blocked197, 'delta.foreign_key_parent_non_unique_changed', false],
    'helper row side' => [$nonUniqueRows197, '0.side', 'current'],
    'helper row key first' => [$nonUniqueRows197, '0.index_key_columns.0', 'name'],
    'helper row parent first' => [$nonUniqueRows197, '0.parent_columns.0', 'name'],
    'helper next rows empty' => [$nextNonUniqueRows197, '', []],
    'table valued flag preserved' => [$tableValued197, 'current_source.table_valued_index_xinfo', true],
];

$tests = [];
foreach ($cases197 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey non unique parent current source next197 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt197): void {
        $value = $factory();
        if ($path !== '') {
            $value = $valueAt197($value, $path);
        }
        $t->same($expected, $value);
    };
}

$tests['pragma index xinfo foreignkey non unique parent current source next197 paginates appended rows'] = static function (TestRunner $t) use ($page197): void {
    $first = $page197(0, 24);
    $second = $page197(24, 1, $first['next']);

    $t->same(24, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 24], $first['next']);
    $t->same('foreign_key_parent_non_unique', $second['rows'][0]['kind']);
    $t->same('wp_option_names_lookup', $second['rows'][0]['index']);
    $t->same(null, $second['next']);
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 source changes with unique repair'] = static function (TestRunner $t) use ($page197, $sameRecords197): void {
    $changed = $page197();
    $same = $page197(nextRecords: $sameRecords197);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_parent_non_unique_changed']);
    $t->same(false, $same['delta']['foreign_key_parent_non_unique_changed']);
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 ignores wrong order non unique indexes'] = static function (TestRunner $t) use ($record197, $currentRecords197): void {
    $records = [
        $currentRecords197[0],
        $currentRecords197[1],
        $record197('index', 'wp_option_names_locale_name_lookup', 'wp_option_names', 9, 'CREATE INDEX wp_option_names_locale_name_lookup ON wp_option_names(locale, name COLLATE NOCASE)', 9),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nonUniqueParentIndexRows197($records));
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 ignores expression non unique indexes'] = static function (TestRunner $t) use ($record197, $currentRecords197): void {
    $records = [
        $currentRecords197[0],
        $currentRecords197[1],
        $record197('index', 'wp_option_names_expr_lookup', 'wp_option_names', 10, 'CREATE INDEX wp_option_names_expr_lookup ON wp_option_names(lower(name), locale)', 10),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nonUniqueParentIndexRows197($records));
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 rejects stale cursor'] = static function (TestRunner $t) use ($page197, $sameRecords197): void {
    $first = $page197(0, 24);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page197(24, 1, $first['next'], nextRecords: $sameRecords197));
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 rejects stale offset cursor'] = static function (TestRunner $t) use ($page197): void {
    $first = $page197(0, 24);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page197(25, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nonUniqueParentIndexRows197([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 rejects negative offset'] = static function (TestRunner $t) use ($page197): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page197(offset: -1));
};

$tests['pragma index xinfo foreignkey non unique parent current source next197 rejects zero limit'] = static function (TestRunner $t) use ($page197): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page197(limit: 0));
};

return $tests;
