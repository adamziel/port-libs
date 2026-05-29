<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record179 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords179 = [
    $record179('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record179('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record179('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record179('table', 'wp_options', 'wp_options', 7, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT 'fk site''blog' REFERENCES wp_sites(blog_id) ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED, option_name TEXT, locale TEXT, fallback_name TEXT, CONSTRAINT 'fk option locale' FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON DELETE SET DEFAULT NOT DEFERRABLE)", 4),
    $record179('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 5),
    $record179('index', 'wp_options_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 6),
    $record179('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
];
$nextRecords179 = [
    $currentRecords179[0],
    $currentRecords179[1],
    $currentRecords179[2],
    $record179('table', 'wp_options', 'wp_options', 7, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT CONSTRAINT 'fk blog site next' REFERENCES wp_sites(blog_id) ON UPDATE CASCADE DEFERRABLE INITIALLY IMMEDIATE, option_name TEXT, locale TEXT, fallback_name TEXT, CONSTRAINT 'fk option locale next' FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED, CONSTRAINT [fk fallback default] FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON DELETE SET DEFAULT NOT DEFERRABLE)", 4),
    $currentRecords179[4],
    $currentRecords179[5],
    $currentRecords179[6],
];

$currentTables179 = [
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
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'option_name' => 'home', 'locale' => 'en_US', 'fallback_name' => 'missing_default'],
        ['rowid' => 3, 'option_id' => 3, 'blog_id' => '1', 'option_name' => 'missing', 'locale' => 'fr_FR', 'fallback_name' => 'siteurl'],
    ],
];
$nextTables179 = [
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
    'wp_options' => $currentTables179['wp_options'],
];

$page179 = static fn (
    int $offset = 0,
    int $limit = 179,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179::currentNextPageFromCatalog(
    $currentRecords179,
    $currentTables179,
    $nextRecords ?? $nextRecords179,
    $nextTables ?? $nextTables179,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt179 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default179 = static fn (): array => $page179();
$sameNames179 = static fn (): array => $page179(nextRecords: $currentRecords179);
$blocked179 = static fn (): array => $page179(nextTables: $currentTables179);
$constraints179 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179::constraintRows($currentRecords179);
$tableValued179 = static fn (): array => $page179(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases179 = [
    'status ok after named constraint repair' => [$default179, 'status', 'ok'],
    'limit defaults to slice number' => [$default179, 'limit', 179],
    'total rows inherited' => [$default179, 'total', 16],
    'count rows inherited' => [$default179, 'count', 16],
    'complete true' => [$default179, 'complete', true],
    'next null' => [$default179, 'next', null],
    'current constraint source' => [$default179, 'current_source.foreign_key_constraint_source', 'create_table_constraint_names_single_quoted'],
    'next constraint source' => [$default179, 'next_source.foreign_key_constraint_source', 'create_table_constraint_names_single_quoted'],
    'current constraint summary inline single quoted escaped' => [$default179, 'current_source.foreign_key_constraints.0', 'wp_options#0:constraint=fk site\'blog,origin=column_constraint'],
    'current constraint summary table single quoted' => [$default179, 'current_source.foreign_key_constraints.1', 'wp_options#1:constraint=fk option locale,origin=table_constraint'],
    'current constraint summary anonymous' => [$default179, 'current_source.foreign_key_constraints.2', 'wp_options#2:constraint=<anonymous>,origin=table_constraint'],
    'next constraint summary inline single quoted' => [$default179, 'next_source.foreign_key_constraints.0', 'wp_options#0:constraint=fk blog site next,origin=column_constraint'],
    'next constraint summary table quoted' => [$default179, 'next_source.foreign_key_constraints.1', 'wp_options#1:constraint=fk option locale next,origin=table_constraint'],
    'next constraint summary bracketed' => [$default179, 'next_source.foreign_key_constraints.2', 'wp_options#2:constraint=fk fallback default,origin=table_constraint'],
    'current named count' => [$default179, 'current.foreign_key_constraints.named', 2],
    'current anonymous count' => [$default179, 'current.foreign_key_constraints.anonymous', 1],
    'current table constraint count' => [$default179, 'current.foreign_key_constraints.table_constraint', 2],
    'current column constraint count' => [$default179, 'current.foreign_key_constraints.column_constraint', 1],
    'next named count' => [$default179, 'next_counts.foreign_key_constraints.named', 3],
    'next anonymous count' => [$default179, 'next_counts.foreign_key_constraints.anonymous', 0],
    'constraint change count' => [$default179, 'delta.foreign_key_constraint_changes', 6],
    'constraint changed true' => [$default179, 'delta.foreign_key_constraint_changed', true],
    'same constraint changed false' => [$sameNames179, 'delta.foreign_key_constraint_changed', false],
    'same constraint changes zero' => [$sameNames179, 'delta.foreign_key_constraint_changes', 0],
    'xinfo rows inherited' => [$default179, 'current.index_xinfo', 3],
    'current admissions inherited' => [$default179, 'current.index_admissions', 3],
    'current violations inherited' => [$default179, 'current.foreign_key_violations', 4],
    'next violations clear' => [$default179, 'next_counts.foreign_key_violations', 0],
    'delta cleared true' => [$default179, 'delta.cleared', true],
    'next ready true' => [$default179, 'next_state.ready', true],
    'timing source preserved' => [$default179, 'current_source.foreign_key_timing_source', 'sqlite_schema_foreign_key_deferrable'],
    'timing still changed' => [$default179, 'delta.foreign_key_timing_changed', true],
    'row0 xinfo unchanged' => [$default179, 'rows.0.kind', 'index_xinfo'],
    'row3 inline constraint name' => [$default179, 'rows.3.constraint', 'fk site\'blog'],
    'row3 inline origin' => [$default179, 'rows.3.constraint_origin', 'column_constraint'],
    'row3 named bool' => [$default179, 'rows.3.constraint_named', true],
    'row3 timing preserved' => [$default179, 'rows.3.timing', 'deferrable_deferred'],
    'row4 table constraint name' => [$default179, 'rows.4.constraint', 'fk option locale'],
    'row4 table origin' => [$default179, 'rows.4.constraint_origin', 'table_constraint'],
    'row4 initially immediate preserved' => [$default179, 'rows.4.initially_deferred', false],
    'row5 anonymous table constraint' => [$default179, 'rows.5.constraint', null],
    'row5 anonymous named bool' => [$default179, 'rows.5.constraint_named', false],
    'row5 anonymous origin' => [$default179, 'rows.5.constraint_origin', 'table_constraint'],
    'row6 violation inline name' => [$default179, 'rows.6.constraint', 'fk site\'blog'],
    'row6 violation origin' => [$default179, 'rows.6.constraint_origin', 'column_constraint'],
    'row7 violation composite name' => [$default179, 'rows.7.constraint', 'fk option locale'],
    'row9 violation anonymous' => [$default179, 'rows.9.constraint', null],
    'row10 next side starts' => [$default179, 'rows.10.side', 'next'],
    'row13 next inline name' => [$default179, 'rows.13.constraint', 'fk blog site next'],
    'row13 next inline origin' => [$default179, 'rows.13.constraint_origin', 'column_constraint'],
    'row14 next quoted table name' => [$default179, 'rows.14.constraint', 'fk option locale next'],
    'row14 next timing preserved' => [$default179, 'rows.14.timing', 'deferrable_deferred'],
    'row15 next bracket table name' => [$default179, 'rows.15.constraint', 'fk fallback default'],
    'blocked status' => [$blocked179, 'status', 'blocked'],
    'blocked next ready false' => [$blocked179, 'next_state.ready', false],
    'blocked next violations' => [$blocked179, 'next_counts.foreign_key_violations', 4],
    'constraint row0 name' => [$constraints179, '0.constraint', 'fk site\'blog'],
    'constraint row0 origin' => [$constraints179, '0.origin', 'column_constraint'],
    'constraint row1 name' => [$constraints179, '1.constraint', 'fk option locale'],
    'constraint row2 anonymous' => [$constraints179, '2.constraint', null],
    'table valued xinfo flag' => [$tableValued179, 'current_source.table_valued_index_xinfo', true],
    'table valued constraint source' => [$tableValued179, 'current_source.foreign_key_constraint_source', 'create_table_constraint_names_single_quoted'],
];

$tests = [];
foreach ($cases179 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next179 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt179): void {
        $t->same($expected, $valueAt179($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next179 paginates constraint rows'] = static function (TestRunner $t) use ($page179): void {
    $first = $page179(0, 6);
    $second = $page179(6, 6, $first['next']);
    $third = $page179(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('fk site\'blog', $second['rows'][0]['constraint']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('fk option locale next', $third['rows'][2]['constraint']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next179 source changes with constraint names'] = static function (TestRunner $t) use ($page179, $currentRecords179): void {
    $changed = $page179();
    $same = $page179(nextRecords: $currentRecords179);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['current_source']['foreign_key_constraints'] !== $changed['next_source']['foreign_key_constraints']);
    $t->same(false, $same['delta']['foreign_key_constraint_changed']);
};

$tests['pragma index xinfo foreignkey current source next179 rejects stale constraint cursor'] = static function (TestRunner $t) use ($page179, $currentRecords179): void {
    $first = $page179(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page179(6, 6, $first['next'], nextRecords: $currentRecords179));
};

$tests['pragma index xinfo foreignkey current source next179 rejects stale offset cursor'] = static function (TestRunner $t) use ($page179): void {
    $first = $page179(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page179(7, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next179 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179::constraintRows([['not' => 'schema']]));
};

return $tests;
