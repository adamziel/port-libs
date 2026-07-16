<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record180 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords180 = [
    $record180('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record180('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT, PRIMARY KEY(name, blog_id))', 2),
    $record180('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record180('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites(blog_id), option_name TEXT, blog_id TEXT, fallback_name TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 4),
    $record180('index', 'wp_option_names_nonunique', 'wp_option_names', 8, 'CREATE INDEX wp_option_names_nonunique ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
    $record180('index', 'wp_option_names_partial_unique', 'wp_option_names', 9, "CREATE UNIQUE INDEX wp_option_names_partial_unique ON wp_option_names(name COLLATE NOCASE, blog_id) WHERE locale = 'en_US'", 6),
    $record180('index', 'wp_option_names_reversed_unique', 'wp_option_names', 10, 'CREATE UNIQUE INDEX wp_option_names_reversed_unique ON wp_option_names(blog_id, name COLLATE NOCASE)', 7),
    $record180('index', 'wp_option_names_binary_unique', 'wp_option_names', 11, 'CREATE UNIQUE INDEX wp_option_names_binary_unique ON wp_option_names(name COLLATE BINARY, blog_id)', 8),
    $record180('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 12, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 9),
    $record180('index', 'wp_options_lookup', 'wp_options', 13, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 10),
];
$nextRecords180 = [
    $currentRecords180[0],
    $currentRecords180[1],
    $currentRecords180[2],
    $currentRecords180[3],
    $currentRecords180[4],
    $currentRecords180[5],
    $currentRecords180[6],
    $currentRecords180[7],
    $record180('index', 'wp_option_names_lookup_unique', 'wp_option_names', 14, 'CREATE UNIQUE INDEX wp_option_names_lookup_unique ON wp_option_names(name COLLATE NOCASE, blog_id)', 11),
    $currentRecords180[8],
    $currentRecords180[9],
];

$currentTables180 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US'],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1, 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'fallback_name' => 'siteurl'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '99', 'option_name' => 'home', 'blog_id' => '1', 'fallback_name' => 'missing_default'],
        ['rowid' => 3, 'option_id' => 3, 'site_id' => '1', 'option_name' => 'missing', 'blog_id' => '2', 'fallback_name' => 'siteurl'],
    ],
];
$nextTables180 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'archive.example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US'],
        ['rowid' => 2, 'name' => 'home', 'blog_id' => 1, 'locale' => 'en_US'],
        ['rowid' => 3, 'name' => 'missing', 'blog_id' => 2, 'locale' => 'fr_FR'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables180['wp_options'],
];

$page180 = static fn (
    int $offset = 0,
    int $limit = 180,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog180(
    $currentRecords180,
    $currentTables180,
    $nextRecords ?? $nextRecords180,
    $nextTables ?? $nextTables180,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt180 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default180 = static fn (): array => $page180();
$blocked180 = static fn (): array => $page180(nextRecords: $currentRecords180, nextTables: $currentTables180);
$same180 = static fn (): array => $page180(nextRecords: $currentRecords180);
$diagnostics180 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentIndexDiagnostics180($currentRecords180);
$nextDiagnostics180 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentIndexDiagnostics180($nextRecords180);
$tableValued180 = static fn (): array => $page180(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases180 = [
    'status ok after parent index and data repair' => [$default180, 'status', 'ok'],
    'default limit' => [$default180, 'limit', 180],
    'total rows inherited' => [$default180, 'total', 15],
    'complete true' => [$default180, 'complete', true],
    'next null' => [$default180, 'next', null],
    'parent index source current' => [$default180, 'current_source.foreign_key_parent_index_source', 'pragma_index_list_index_xinfo_candidate_diagnostics'],
    'parent index source next' => [$default180, 'next_source.foreign_key_parent_index_source', 'pragma_index_list_index_xinfo_candidate_diagnostics'],
    'current summary rowid first' => [$default180, 'current_source.foreign_key_parent_indexes.0', 'wp_options#0:parent=wp_sites,columns=blog_id,accepted=rowid-primary-key,reason=accepted_rowid_primary_key,candidates=rowid-primary-key:accepted_rowid_primary_key:blog_id:BINARY'],
    'current summary blocked composite' => [$default180, 'current_source.foreign_key_parent_indexes.1', 'wp_options#1:parent=wp_option_names,columns=name|blog_id,accepted=<none>,reason=missing_matching_unique_index,candidates=wp_option_names_binary_unique:collation:name|blog_id:BINARY|BINARY|wp_option_names_nonunique:non_unique:name|blog_id:NOCASE|BINARY|wp_option_names_partial_unique:partial_unique:name|blog_id:NOCASE|BINARY|wp_option_names_reversed_unique:column_order:blog_id|name:BINARY|NOCASE'],
    'next summary accepted composite' => [$default180, 'next_source.foreign_key_parent_indexes.1', 'wp_options#1:parent=wp_option_names,columns=name|blog_id,accepted=wp_option_names_lookup_unique,reason=accepted_unique_index,candidates=wp_option_names_binary_unique:collation:name|blog_id:BINARY|BINARY|wp_option_names_lookup_unique:accepted_unique_index:name|blog_id:NOCASE|BINARY|wp_option_names_nonunique:non_unique:name|blog_id:NOCASE|BINARY|wp_option_names_partial_unique:partial_unique:name|blog_id:NOCASE|BINARY|wp_option_names_reversed_unique:column_order:blog_id|name:BINARY|NOCASE'],
    'current accepted count' => [$default180, 'current.foreign_key_parent_indexes.accepted', 2],
    'current blocked count' => [$default180, 'current.foreign_key_parent_indexes.blocked', 1],
    'current rowid count' => [$default180, 'current.foreign_key_parent_indexes.rowid_primary_key', 1],
    'current unique count' => [$default180, 'current.foreign_key_parent_indexes.unique_index', 1],
    'current partial rejected' => [$default180, 'current.foreign_key_parent_indexes.partial_unique_rejected', 1],
    'current non unique rejected' => [$default180, 'current.foreign_key_parent_indexes.non_unique_rejected', 1],
    'current column order rejected' => [$default180, 'current.foreign_key_parent_indexes.column_order_rejected', 1],
    'current collation rejected' => [$default180, 'current.foreign_key_parent_indexes.collation_rejected', 1],
    'next accepted count' => [$default180, 'next_counts.foreign_key_parent_indexes.accepted', 3],
    'next blocked count' => [$default180, 'next_counts.foreign_key_parent_indexes.blocked', 0],
    'next unique count' => [$default180, 'next_counts.foreign_key_parent_indexes.unique_index', 2],
    'next partial rejected' => [$default180, 'next_counts.foreign_key_parent_indexes.partial_unique_rejected', 1],
    'parent index changes' => [$default180, 'delta.foreign_key_parent_index_changes', 2],
    'parent index changed true' => [$default180, 'delta.foreign_key_parent_index_changed', true],
    'same parent index changed false' => [$same180, 'delta.foreign_key_parent_index_changed', false],
    'same parent index changes zero' => [$same180, 'delta.foreign_key_parent_index_changes', 0],
    'xinfo inherited' => [$default180, 'current.index_xinfo', 3],
    'admissions inherited current' => [$default180, 'current.index_admissions', 3],
    'current index blockers inherited' => [$default180, 'current.index_blockers', 1],
    'next index blockers clear' => [$default180, 'next_counts.index_blockers', 0],
    'current fk violations inherited' => [$default180, 'current.foreign_key_violations', 3],
    'next fk violations clear' => [$default180, 'next_counts.foreign_key_violations', 0],
    'delta cleared true' => [$default180, 'delta.cleared', true],
    'next ready true' => [$default180, 'next_state.ready', true],
    'row3 site rowid accepted' => [$default180, 'rows.3.parent_index', 'rowid-primary-key'],
    'row3 site reason' => [$default180, 'rows.3.parent_index_reason', 'accepted_rowid_primary_key'],
    'row3 candidate count' => [$default180, 'rows.3.parent_index_candidates', 1],
    'row4 blocked composite no index' => [$default180, 'rows.4.parent_index', null],
    'row4 blocked reason' => [$default180, 'rows.4.parent_index_reason', 'missing_matching_unique_index'],
    'row4 partial rejection' => [$default180, 'rows.4.parent_index_rejections.partial_unique', 1],
    'row4 non unique rejection' => [$default180, 'rows.4.parent_index_rejections.non_unique', 1],
    'row4 column order rejection' => [$default180, 'rows.4.parent_index_rejections.column_order', 1],
    'row4 collation rejection' => [$default180, 'rows.4.parent_index_rejections.collation', 1],
    'row5 default unique accepted' => [$default180, 'rows.5.parent_index', 'sqlite_autoindex_wp_defaults_1'],
    'row6 violation rowid accepted' => [$default180, 'rows.6.parent_index_reason', 'accepted_rowid_primary_key'],
    'row7 violation blocked composite' => [$default180, 'rows.7.parent_index_reason', 'missing_matching_unique_index'],
    'row10 next side starts' => [$default180, 'rows.10.side', 'next'],
    'row13 next composite accepted' => [$default180, 'rows.13.parent_index', 'wp_option_names_lookup_unique'],
    'row13 next reason' => [$default180, 'rows.13.parent_index_reason', 'accepted_unique_index'],
    'row13 next candidate count' => [$default180, 'rows.13.parent_index_candidates', 5],
    'blocked status remains blocked' => [$blocked180, 'status', 'blocked'],
    'blocked next ready false' => [$blocked180, 'next_state.ready', false],
    'blocked next blocker count' => [$blocked180, 'next_counts.foreign_key_parent_indexes.blocked', 1],
    'diagnostic row0 rowid' => [$diagnostics180, '0.accepted_index', 'rowid-primary-key'],
    'diagnostic row1 blocked' => [$diagnostics180, '1.accepted', false],
    'diagnostic row1 candidates' => [$diagnostics180, '1.candidate_count', 4],
    'diagnostic row1 rejected collation' => [$diagnostics180, '1.rejected.collation', 1],
    'diagnostic row2 default index' => [$diagnostics180, '2.accepted_index', 'sqlite_autoindex_wp_defaults_1'],
    'next diagnostic row1 accepted' => [$nextDiagnostics180, '1.accepted_index', 'wp_option_names_lookup_unique'],
    'table valued xinfo flag' => [$tableValued180, 'current_source.table_valued_index_xinfo', true],
    'table valued parent index source' => [$tableValued180, 'current_source.foreign_key_parent_index_source', 'pragma_index_list_index_xinfo_candidate_diagnostics'],
];

$tests = [];
foreach ($cases180 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next180 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt180): void {
        $t->same($expected, $valueAt180($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next180 paginates parent index diagnostics'] = static function (TestRunner $t) use ($page180): void {
    $first = $page180(0, 6);
    $second = $page180(6, 6, $first['next']);
    $third = $page180(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('accepted_rowid_primary_key', $second['rows'][0]['parent_index_reason']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('wp_option_names_lookup_unique', $third['rows'][1]['parent_index']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next180 source changes with parent index repair'] = static function (TestRunner $t) use ($page180, $currentRecords180): void {
    $changed = $page180();
    $same = $page180(nextRecords: $currentRecords180);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['current_source']['foreign_key_parent_indexes'] !== $changed['next_source']['foreign_key_parent_indexes']);
    $t->same(false, $same['delta']['foreign_key_parent_index_changed']);
};

$tests['pragma index xinfo foreignkey current source next180 rejects stale parent index cursor'] = static function (TestRunner $t) use ($page180, $currentRecords180): void {
    $first = $page180(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page180(6, 6, $first['next'], nextRecords: $currentRecords180));
};

$tests['pragma index xinfo foreignkey current source next180 rejects stale offset cursor'] = static function (TestRunner $t) use ($page180): void {
    $first = $page180(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page180(7, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next180 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentIndexDiagnostics180([['not' => 'schema']]));
};

return $tests;
