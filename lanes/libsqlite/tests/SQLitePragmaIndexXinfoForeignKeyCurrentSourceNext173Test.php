<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record173 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords173 = [
    $record173('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record173('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record173('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record173('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT, option_name TEXT, locale TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON UPDATE NO ACTION ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON UPDATE SET NULL ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $record173('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale)', 5),
    $record173('index', 'wp_options_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 6),
    $record173('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
];
$nextRecords173 = [
    $currentRecords173[0],
    $currentRecords173[1],
    $currentRecords173[2],
    $record173('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT, option_name TEXT, locale TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale) ON UPDATE NO ACTION ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON UPDATE SET NULL ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $currentRecords173[4],
    $currentRecords173[5],
    $currentRecords173[6],
];

$currentTables173 = [
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
$nextTables173 = [
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
    'wp_options' => $currentTables173['wp_options'],
];

$page173 = static fn (
    int $offset = 0,
    int $limit = 173,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog173(
    $currentRecords173,
    $currentTables173,
    $nextRecords ?? $nextRecords173,
    $nextTables ?? $nextTables173,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt173 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default173 = static fn (): array => $page173();
$sameDeferral173 = static fn (): array => $page173(nextRecords: $currentRecords173);
$blocked173 = static fn (): array => $page173(nextTables: $currentTables173);
$deferrals173 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::deferralRows173($currentRecords173);
$tableValued173 = static fn (): array => $page173(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases173 = [
    'status ok after deferred repair' => [$default173, 'status', 'ok'],
    'default limit' => [$default173, 'limit', 173],
    'total rows' => [$default173, 'total', 16],
    'count rows' => [$default173, 'count', 16],
    'complete true' => [$default173, 'complete', true],
    'next null' => [$default173, 'next', null],
    'deferral source current' => [$default173, 'current_source.foreign_key_deferral_source', 'create_table_foreign_key_clause'],
    'deferral source next' => [$default173, 'next_source.foreign_key_deferral_source', 'create_table_foreign_key_clause'],
    'current deferral summary first' => [$default173, 'current_source.foreign_key_deferrals.0', 'wp_options#0:DEFERRABLE,initially=DEFERRED'],
    'current deferral summary second' => [$default173, 'current_source.foreign_key_deferrals.1', 'wp_options#1:DEFERRABLE,initially=IMMEDIATE'],
    'current deferral summary third' => [$default173, 'current_source.foreign_key_deferrals.2', 'wp_options#2:NOT DEFERRABLE,initially=IMMEDIATE'],
    'next deferral summary first' => [$default173, 'next_source.foreign_key_deferrals.0', 'wp_options#0:DEFERRABLE,initially=IMMEDIATE'],
    'next deferral summary second' => [$default173, 'next_source.foreign_key_deferrals.1', 'wp_options#1:DEFERRABLE,initially=DEFERRED'],
    'current deferrable count' => [$default173, 'current.foreign_key_deferrals.deferrable', 2],
    'current not deferrable count' => [$default173, 'current.foreign_key_deferrals.not_deferrable', 1],
    'current initially deferred count' => [$default173, 'current.foreign_key_deferrals.initially_deferred', 1],
    'current initially immediate count' => [$default173, 'current.foreign_key_deferrals.initially_immediate', 2],
    'current deferred runtime count' => [$default173, 'current.foreign_key_deferrals.deferred_runtime', 1],
    'next initially deferred count' => [$default173, 'next_counts.foreign_key_deferrals.initially_deferred', 1],
    'next initially immediate count' => [$default173, 'next_counts.foreign_key_deferrals.initially_immediate', 2],
    'deferral changes count' => [$default173, 'delta.foreign_key_deferral_changes', 4],
    'deferral changed true' => [$default173, 'delta.foreign_key_deferral_changed', true],
    'same deferral changed false' => [$sameDeferral173, 'delta.foreign_key_deferral_changed', false],
    'same deferral changes zero' => [$sameDeferral173, 'delta.foreign_key_deferral_changes', 0],
    'xinfo rows inherited' => [$default173, 'current.index_xinfo', 3],
    'admissions inherited' => [$default173, 'current.index_admissions', 3],
    'current fk violations inherited' => [$default173, 'current.foreign_key_violations', 4],
    'next fk violations clear' => [$default173, 'next_counts.foreign_key_violations', 0],
    'delta cleared true' => [$default173, 'delta.cleared', true],
    'next ready true' => [$default173, 'next_state.ready', true],
    'row0 xinfo unchanged' => [$default173, 'rows.0.kind', 'index_xinfo'],
    'row3 admission deferrable' => [$default173, 'rows.3.deferrable', 'DEFERRABLE'],
    'row3 admission initially deferred' => [$default173, 'rows.3.initially', 'DEFERRED'],
    'row3 admission deferred bool' => [$default173, 'rows.3.deferred', true],
    'row3 admission summary' => [$default173, 'rows.3.deferral_summary', 'DEFERRABLE/INITIALLY DEFERRED'],
    'row4 composite initially immediate' => [$default173, 'rows.4.initially', 'IMMEDIATE'],
    'row4 composite deferred bool' => [$default173, 'rows.4.deferred', false],
    'row5 default not deferrable' => [$default173, 'rows.5.deferrable', 'NOT DEFERRABLE'],
    'row5 default summary' => [$default173, 'rows.5.deferral_summary', 'NOT DEFERRABLE/INITIALLY IMMEDIATE'],
    'row6 violation keeps deferred' => [$default173, 'rows.6.deferred', true],
    'row7 violation immediate' => [$default173, 'rows.7.initially', 'IMMEDIATE'],
    'row9 violation not deferrable' => [$default173, 'rows.9.deferrable', 'NOT DEFERRABLE'],
    'row10 next side starts' => [$default173, 'rows.10.side', 'next'],
    'row13 next initially immediate' => [$default173, 'rows.13.initially', 'IMMEDIATE'],
    'row13 next deferred false' => [$default173, 'rows.13.deferred', false],
    'row14 next initially deferred' => [$default173, 'rows.14.initially', 'DEFERRED'],
    'row14 next deferred true' => [$default173, 'rows.14.deferred', true],
    'blocked status' => [$blocked173, 'status', 'blocked'],
    'blocked next ready false' => [$blocked173, 'next_state.ready', false],
    'blocked next violations' => [$blocked173, 'next_counts.foreign_key_violations', 4],
    'deferrals row0 table' => [$deferrals173, '0.table', 'wp_options'],
    'deferrals row0 id' => [$deferrals173, '0.fkid', 0],
    'deferrals row0 deferred' => [$deferrals173, '0.deferred', true],
    'deferrals row1 initially' => [$deferrals173, '1.initially', 'IMMEDIATE'],
    'deferrals row2 deferrable' => [$deferrals173, '2.deferrable', 'NOT DEFERRABLE'],
    'table valued flag' => [$tableValued173, 'current_source.table_valued_index_xinfo', true],
    'table valued deferral source' => [$tableValued173, 'current_source.foreign_key_deferral_source', 'create_table_foreign_key_clause'],
];

$tests = [];
foreach ($cases173 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next173 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt173): void {
        $t->same($expected, $valueAt173($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next173 paginates deferral rows'] = static function (TestRunner $t) use ($page173): void {
    $first = $page173(0, 6);
    $second = $page173(6, 6, $first['next']);
    $third = $page173(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('DEFERRABLE/INITIALLY DEFERRED', $second['rows'][0]['deferral_summary']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('DEFERRABLE/INITIALLY IMMEDIATE', $third['rows'][1]['deferral_summary']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next173 source changes with deferral ddl'] = static function (TestRunner $t) use ($page173, $currentRecords173): void {
    $changed = $page173();
    $same = $page173(nextRecords: $currentRecords173);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['current_source']['foreign_key_deferrals'] !== $changed['next_source']['foreign_key_deferrals']);
    $t->same(false, $same['delta']['foreign_key_deferral_changed']);
};

$tests['pragma index xinfo foreignkey current source next173 rejects stale deferral cursor'] = static function (TestRunner $t) use ($page173, $currentRecords173): void {
    $first = $page173(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page173(6, 6, $first['next'], nextRecords: $currentRecords173));
};

$tests['pragma index xinfo foreignkey current source next173 rejects stale offset cursor'] = static function (TestRunner $t) use ($page173): void {
    $first = $page173(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page173(7, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next173 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::deferralRows173([['not' => 'schema']]));
};

return $tests;
