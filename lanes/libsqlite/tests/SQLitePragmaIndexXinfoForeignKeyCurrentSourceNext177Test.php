<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record177 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords177 = [
    $record177('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record177('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record177('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record177('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT fk_option_site REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, option_name TEXT, locale TEXT, fallback_name TEXT, autoload TEXT, CONSTRAINT fk_option_name_locale FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON UPDATE NO ACTION ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON UPDATE SET NULL ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $record177('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 5),
    $record177('index', 'wp_options_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 6),
    $record177('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
];
$nextRecords177 = [
    $currentRecords177[0],
    $currentRecords177[1],
    $currentRecords177[2],
    $record177('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT fk_option_site_next REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE, option_name TEXT, locale TEXT, fallback_name TEXT CONSTRAINT fk_option_default REFERENCES wp_defaults(default_name) ON UPDATE SET NULL ON DELETE SET DEFAULT NOT DEFERRABLE, autoload TEXT, CONSTRAINT fk_option_name_locale FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON UPDATE NO ACTION ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED)', 4),
    $currentRecords177[4],
    $currentRecords177[5],
    $currentRecords177[6],
];

$currentTables177 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'option_name' => 'home', 'locale' => 'en_US', 'fallback_name' => 'missing_default', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'blog_id' => '1', 'option_name' => 'missing', 'locale' => 'fr_FR', 'fallback_name' => 'siteurl', 'autoload' => 'no'],
    ],
];
$nextTables177 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'locale' => 'en_US'],
        ['name' => 'home', 'locale' => 'en_US'],
        ['name' => 'missing', 'locale' => 'fr_FR'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables177['wp_options'],
];

$page177 = static fn (
    int $offset = 0,
    int $limit = 177,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog177(
    $currentRecords177,
    $currentTables177,
    $nextRecords ?? $nextRecords177,
    $nextTables ?? $nextTables177,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt177 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default177 = static fn (): array => $page177();
$sameConstraints177 = static fn (): array => $page177(nextRecords: $currentRecords177);
$blocked177 = static fn (): array => $page177(nextTables: $currentTables177);
$constraints177 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::constraintRows177($currentRecords177);
$nextConstraints177 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::constraintRows177($nextRecords177);
$tableValued177 = static fn (): array => $page177(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases177 = [
    'status ok after repair' => [$default177, 'status', 'ok'],
    'default limit' => [$default177, 'limit', 177],
    'total rows' => [$default177, 'total', 16],
    'count rows' => [$default177, 'count', 16],
    'complete true' => [$default177, 'complete', true],
    'next null' => [$default177, 'next', null],
    'constraint source current' => [$default177, 'current_source.foreign_key_constraint_source', 'create_table_constraint_names_and_origins'],
    'constraint source next' => [$default177, 'next_source.foreign_key_constraint_source', 'create_table_constraint_names_and_origins'],
    'current constraint summary first' => [$default177, 'current_source.foreign_key_constraints.0', 'wp_options#0:name=fk_option_site,origin=column,child=blog_id,parent=wp_sites'],
    'current constraint summary second' => [$default177, 'current_source.foreign_key_constraints.1', 'wp_options#1:name=fk_option_name_locale,origin=table,child=option_name|locale,parent=wp_option_names'],
    'current constraint summary third' => [$default177, 'current_source.foreign_key_constraints.2', 'wp_options#2:name=,origin=table,child=fallback_name,parent=wp_defaults'],
    'next constraint summary first' => [$default177, 'next_source.foreign_key_constraints.0', 'wp_options#0:name=fk_option_site_next,origin=column,child=blog_id,parent=wp_sites'],
    'next constraint summary second' => [$default177, 'next_source.foreign_key_constraints.1', 'wp_options#1:name=fk_option_default,origin=column,child=fallback_name,parent=wp_defaults'],
    'next constraint summary third' => [$default177, 'next_source.foreign_key_constraints.2', 'wp_options#2:name=fk_option_name_locale,origin=table,child=option_name|locale,parent=wp_option_names'],
    'current named count' => [$default177, 'current.foreign_key_constraints.named', 2],
    'current unnamed count' => [$default177, 'current.foreign_key_constraints.unnamed', 1],
    'current table origin count' => [$default177, 'current.foreign_key_constraints.table_origin', 2],
    'current column origin count' => [$default177, 'current.foreign_key_constraints.column_origin', 1],
    'current composite count' => [$default177, 'current.foreign_key_constraints.composite_child_keys', 1],
    'next named count' => [$default177, 'next_counts.foreign_key_constraints.named', 3],
    'next unnamed count' => [$default177, 'next_counts.foreign_key_constraints.unnamed', 0],
    'next table origin count' => [$default177, 'next_counts.foreign_key_constraints.table_origin', 1],
    'next column origin count' => [$default177, 'next_counts.foreign_key_constraints.column_origin', 2],
    'next composite count' => [$default177, 'next_counts.foreign_key_constraints.composite_child_keys', 1],
    'constraint changes count' => [$default177, 'delta.foreign_key_constraint_changes', 6],
    'constraint changed true' => [$default177, 'delta.foreign_key_constraint_changed', true],
    'same constraint changed false' => [$sameConstraints177, 'delta.foreign_key_constraint_changed', false],
    'same constraint changes zero' => [$sameConstraints177, 'delta.foreign_key_constraint_changes', 0],
    'xinfo rows inherited' => [$default177, 'current.index_xinfo', 3],
    'admissions inherited' => [$default177, 'current.index_admissions', 3],
    'current fk violations inherited' => [$default177, 'current.foreign_key_violations', 4],
    'next fk violations clear' => [$default177, 'next_counts.foreign_key_violations', 0],
    'delta cleared true' => [$default177, 'delta.cleared', true],
    'next ready true' => [$default177, 'next_state.ready', true],
    'row3 named column admission' => [$default177, 'rows.3.constraint_name', 'fk_option_site'],
    'row3 column origin' => [$default177, 'rows.3.constraint_origin', 'column'],
    'row3 child column' => [$default177, 'rows.3.constraint_child_columns.0', 'blog_id'],
    'row3 parent table' => [$default177, 'rows.3.constraint_parent_table', 'wp_sites'],
    'row3 summary' => [$default177, 'rows.3.constraint_summary', 'fk_option_site/column/blog_id'],
    'row4 named table admission' => [$default177, 'rows.4.constraint_name', 'fk_option_name_locale'],
    'row4 table origin' => [$default177, 'rows.4.constraint_origin', 'table'],
    'row4 first child' => [$default177, 'rows.4.constraint_child_columns.0', 'option_name'],
    'row4 second child' => [$default177, 'rows.4.constraint_child_columns.1', 'locale'],
    'row4 summary' => [$default177, 'rows.4.constraint_summary', 'fk_option_name_locale/table/option_name,locale'],
    'row5 unnamed admission' => [$default177, 'rows.5.constraint_name', null],
    'row5 unnamed summary' => [$default177, 'rows.5.constraint_summary', '<unnamed>/table/fallback_name'],
    'row6 violation named column' => [$default177, 'rows.6.constraint_name', 'fk_option_site'],
    'row7 violation table origin' => [$default177, 'rows.7.constraint_origin', 'table'],
    'row9 violation unnamed' => [$default177, 'rows.9.constraint_name', null],
    'row10 next side starts' => [$default177, 'rows.10.side', 'next'],
    'row13 next renamed column' => [$default177, 'rows.13.constraint_name', 'fk_option_site_next'],
    'row13 next child column' => [$default177, 'rows.13.constraint_child_columns.0', 'blog_id'],
    'row14 next default column' => [$default177, 'rows.14.constraint_name', 'fk_option_default'],
    'row14 next default origin' => [$default177, 'rows.14.constraint_origin', 'column'],
    'row15 next composite table' => [$default177, 'rows.15.constraint_origin', 'table'],
    'blocked status' => [$blocked177, 'status', 'blocked'],
    'blocked next ready false' => [$blocked177, 'next_state.ready', false],
    'blocked next violations' => [$blocked177, 'next_counts.foreign_key_violations', 4],
    'constraints row0 name' => [$constraints177, '0.constraint_name', 'fk_option_site'],
    'constraints row0 origin' => [$constraints177, '0.origin', 'column'],
    'constraints row0 parent' => [$constraints177, '0.parent_table', 'wp_sites'],
    'constraints row1 name' => [$constraints177, '1.constraint_name', 'fk_option_name_locale'],
    'constraints row1 origin' => [$constraints177, '1.origin', 'table'],
    'constraints row1 child count' => [$constraints177, '1.child_columns.1', 'locale'],
    'constraints row2 unnamed' => [$constraints177, '2.constraint_name', null],
    'next constraints reordered default name' => [$nextConstraints177, '1.constraint_name', 'fk_option_default'],
    'table valued flag' => [$tableValued177, 'current_source.table_valued_index_xinfo', true],
    'table valued constraint source' => [$tableValued177, 'current_source.foreign_key_constraint_source', 'create_table_constraint_names_and_origins'],
];

$tests = [];
foreach ($cases177 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next177 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt177): void {
        $t->same($expected, $valueAt177($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next177 paginates constraint rows'] = static function (TestRunner $t) use ($page177): void {
    $first = $page177(0, 6);
    $second = $page177(6, 6, $first['next']);
    $third = $page177(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('fk_option_site', $second['rows'][0]['constraint_name']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('fk_option_default', $third['rows'][2]['constraint_name']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next177 source changes with constraint ddl'] = static function (TestRunner $t) use ($page177, $currentRecords177): void {
    $changed = $page177();
    $same = $page177(nextRecords: $currentRecords177);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['current_source']['foreign_key_constraints'] !== $changed['next_source']['foreign_key_constraints']);
    $t->same(false, $same['delta']['foreign_key_constraint_changed']);
};

$tests['pragma index xinfo foreignkey current source next177 rejects stale constraint cursor'] = static function (TestRunner $t) use ($page177, $currentRecords177): void {
    $first = $page177(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page177(6, 6, $first['next'], nextRecords: $currentRecords177));
};

$tests['pragma index xinfo foreignkey current source next177 rejects stale offset cursor'] = static function (TestRunner $t) use ($page177): void {
    $first = $page177(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page177(7, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next177 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::constraintRows177([['not' => 'schema']]));
};

return $tests;
