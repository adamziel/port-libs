<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record182 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords182 = [
    $record182('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record182('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT COLLATE RTRIM, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record182('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record182('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, locale TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 4),
    $record182('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name, locale)', 5),
    $record182('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 9, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 6),
    $record182('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, locale, fallback_name)', 7),
];
$nextRecords182 = [
    $currentRecords182[0],
    $currentRecords182[1],
    $currentRecords182[2],
    $currentRecords182[3],
    $record182('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale COLLATE RTRIM)', 5),
    $currentRecords182[5],
    $currentRecords182[6],
];

$currentTables182 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US']],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'autoload' => 'yes'],
    ],
];
$nextTables182 = $currentTables182;

$page182 = static fn (
    int $offset = 0,
    int $limit = 182,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog182(
    $currentRecords182,
    $currentTables182,
    $nextRecords ?? $nextRecords182,
    $nextTables182,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt182 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default182 = static fn (): array => $page182();
$blocked182 = static fn (): array => $page182(nextRecords: $currentRecords182);
$collationRows182 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows182($currentRecords182);
$tableValued182 = static fn (): array => $page182(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases182 = [
    'status ok after collation repair' => [$default182, 'status', 'ok'],
    'default limit' => [$default182, 'limit', 182],
    'total rows adds collation rows' => [$default182, 'total', 38],
    'count rows adds collation rows' => [$default182, 'count', 38],
    'complete true' => [$default182, 'complete', true],
    'next null' => [$default182, 'next', null],
    'current collation source' => [$default182, 'current_source.foreign_key_parent_collation_source', 'create_table_column_collate_and_pragma_index_xinfo'],
    'next collation source' => [$default182, 'next_source.foreign_key_parent_collation_source', 'create_table_column_collate_and_pragma_index_xinfo'],
    'current collation rows' => [$default182, 'current.foreign_key_parent_collation_rows', 4],
    'next collation rows' => [$default182, 'next_counts.foreign_key_parent_collation_rows', 4],
    'current matched count' => [$default182, 'current.foreign_key_parent_collations.matched', 2],
    'current mismatch count' => [$default182, 'current.foreign_key_parent_collations.mismatch', 2],
    'current missing parent count' => [$default182, 'current.foreign_key_parent_collations.missing_parent_key', 0],
    'next matched count' => [$default182, 'next_counts.foreign_key_parent_collations.matched', 4],
    'next mismatch count' => [$default182, 'next_counts.foreign_key_parent_collations.mismatch', 0],
    'binary count' => [$default182, 'current.foreign_key_parent_collations.binary', 2],
    'nocase count' => [$default182, 'current.foreign_key_parent_collations.nocase', 1],
    'custom count' => [$default182, 'current.foreign_key_parent_collations.custom', 0],
    'rtrim count is one' => [$default182, 'current.foreign_key_parent_collations.rtrim', 1],
    'delta rows unchanged' => [$default182, 'delta.foreign_key_parent_collation_rows', 0],
    'delta mismatches repaired' => [$default182, 'delta.foreign_key_parent_collation_mismatches', -2],
    'delta repaired true' => [$default182, 'delta.foreign_key_parent_collation_repaired', true],
    'delta changed true' => [$default182, 'delta.foreign_key_parent_collation_changed', true],
    'next ready true' => [$default182, 'next_state.ready', true],
    'next blocking empty' => [$default182, 'next_state.blocking', []],
    'source summary captures current nocase mismatch' => [$default182, 'current_source.foreign_key_parent_collations.1', 'current:wp_options#1.0:wp_option_names.name:parent=NOCASE:index=BINARY:status=collation_mismatch'],
    'source summary captures next nocase repair' => [$default182, 'next_source.foreign_key_parent_collations.1', 'next:wp_options#1.0:wp_option_names.name:parent=NOCASE:index=NOCASE:status=ok'],
    'row22 inherited parent key has collation status' => [$default182, 'rows.22.parent_key_collation_status', 'ok'],
    'row23 inherited parent key mismatch' => [$default182, 'rows.23.parent_key_collation_status', 'collation_mismatch'],
    'row23 inherited parent collation' => [$default182, 'rows.23.parent_column_collation', 'NOCASE'],
    'row23 inherited collation bool false' => [$default182, 'rows.23.parent_key_collation_matches', false],
    'row24 inherited rtrim mismatch' => [$default182, 'rows.24.parent_key_collation_status', 'collation_mismatch'],
    'row25 inherited binary match' => [$default182, 'rows.25.parent_key_collation_status', 'ok'],
    'row28 next rtrim repaired' => [$default182, 'rows.28.parent_key_collation_status', 'ok'],
    'row30 current collation row kind' => [$default182, 'rows.30.kind', 'foreign_key_parent_collation'],
    'row30 rowid binary' => [$default182, 'rows.30.parent_column_collation', 'BINARY'],
    'row30 rowid matched' => [$default182, 'rows.30.collation_matches', true],
    'row31 nocase mismatch status' => [$default182, 'rows.31.status', 'collation_mismatch'],
    'row31 nocase index binary' => [$default182, 'rows.31.index_coll', 'BINARY'],
    'row31 mismatch message' => [$default182, 'rows.31.message', 'foreign key wp_options->wp_option_names column name uses BINARY index collation but parent column declares NOCASE'],
    'row32 rtrim mismatch status' => [$default182, 'rows.32.status', 'collation_mismatch'],
    'row33 current fallback binary match' => [$default182, 'rows.33.status', 'ok'],
    'blocked status' => [$blocked182, 'status', 'blocked'],
    'blocked next mismatch count' => [$blocked182, 'next_counts.foreign_key_parent_collations.mismatch', 2],
    'blocked next state false' => [$blocked182, 'next_state.ready', false],
    'blocked reason parent unique' => [$blocked182, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked reason collation' => [$blocked182, 'next_state.blocking.1', 'foreign_key_parent_collation'],
    'helper row count' => [$collationRows182, '0.kind', 'foreign_key_parent_collation'],
    'helper row0 status' => [$collationRows182, '0.status', 'ok'],
    'helper row1 expected nocase' => [$collationRows182, '1.parent_column_collation', 'NOCASE'],
    'helper row1 mismatch' => [$collationRows182, '1.collation_matches', false],
    'helper row2 rtrim mismatch' => [$collationRows182, '2.collation_matches', false],
    'helper row3 binary match' => [$collationRows182, '3.status', 'ok'],
    'table valued flag preserved' => [$tableValued182, 'current_source.table_valued_index_xinfo', true],
    'table valued source added' => [$tableValued182, 'current_source.foreign_key_parent_collation_source', 'create_table_column_collate_and_pragma_index_xinfo'],
];

$tests = [];
foreach ($cases182 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent collation current source next182 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt182): void {
        $t->same($expected, $valueAt182($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent collation current source next182 paginates into collation rows'] = static function (TestRunner $t) use ($page182): void {
    $first = $page182(0, 30);
    $second = $page182(30, 4, $first['next']);
    $third = $page182(34, 4, $second['next']);

    $t->same(30, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 30], $first['next']);
    $t->same('foreign_key_parent_collation', $second['rows'][0]['kind']);
    $t->same('collation_mismatch', $second['rows'][1]['status']);
    $t->same('ok', $third['rows'][0]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent collation current source next182 source changes with collation repair'] = static function (TestRunner $t) use ($page182, $currentRecords182): void {
    $changed = $page182();
    $same = $page182(nextRecords: $currentRecords182);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['current_source']['foreign_key_parent_collations'] !== $changed['next_source']['foreign_key_parent_collations']);
    $t->same(false, $same['delta']['foreign_key_parent_collation_repaired']);
};

$tests['pragma index xinfo foreignkey parent collation current source next182 rejects stale collation cursor'] = static function (TestRunner $t) use ($page182, $currentRecords182): void {
    $first = $page182(0, 30);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page182(30, 3, $first['next'], nextRecords: $currentRecords182));
};

$tests['pragma index xinfo foreignkey parent collation current source next182 rejects stale offset cursor'] = static function (TestRunner $t) use ($page182): void {
    $first = $page182(0, 30);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page182(31, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey parent collation current source next182 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows182([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent collation current source next182 rejects negative offset'] = static function (TestRunner $t) use ($page182): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page182(offset: -1));
};

$tests['pragma index xinfo foreignkey parent collation current source next182 rejects zero limit'] = static function (TestRunner $t) use ($page182): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page182(limit: 0));
};

return $tests;
