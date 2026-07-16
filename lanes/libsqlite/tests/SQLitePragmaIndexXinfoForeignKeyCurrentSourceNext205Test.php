<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record205 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords205 = [
    $record205('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, locale))', 1),
    $record205('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record205('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY, domain TEXT)', 3),
    $record205('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        locale TEXT COLLATE RTRIM NOT NULL,
        site_id INTEGER NOT NULL REFERENCES wp_sites(site_id),
        meta_key TEXT,
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale)
    )", 4),
    $record205('index', 'wp_termmeta_lookup_bad', 'wp_termmeta_import', 6, 'CREATE INDEX wp_termmeta_lookup_bad ON wp_termmeta_import(term_slug COLLATE BINARY DESC, locale COLLATE BINARY, meta_key)', 5),
];

$nextRecords205 = [
    $currentRecords205[0],
    $currentRecords205[1],
    $currentRecords205[2],
    $currentRecords205[3],
    $record205('index', 'wp_termmeta_lookup_good', 'wp_termmeta_import', 6, 'CREATE INDEX wp_termmeta_lookup_good ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM, meta_key)', 5),
    $record205('index', 'wp_termmeta_site_lookup', 'wp_termmeta_import', 7, 'CREATE INDEX wp_termmeta_site_lookup ON wp_termmeta_import(site_id)', 6),
];

$missingNextRecords205 = [
    $currentRecords205[0],
    $currentRecords205[1],
    $currentRecords205[2],
    $currentRecords205[3],
    $currentRecords205[4],
];

$page205 = static fn (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page205(
    $currentRecords205,
    $nextRecords ?? $nextRecords205,
    'PRAGMA main.index_xinfo(wp_termmeta_lookup_bad)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt205 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default205 = static fn (): array => $page205();
$blocked205 = static fn (): array => $page205(nextRecords: $missingNextRecords205);
$currentQuality205 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childPrefixQualityRows205($currentRecords205);
$nextQuality205 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childPrefixQualityRows205($nextRecords205, 'next');

$cases205 = [
    'status ok' => [$default205, 'status', 'ok'],
    'operation marker' => [$default205, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next205'],
    'source id length' => [static fn (): array => ['len' => strlen($page205()['source_id'])], 'len', 64],
    'offset default' => [$default205, 'offset', 0],
    'limit default' => [$default205, 'limit', 50],
    'dependency appended' => [$default205, 'dependencies.4', 'sqlite-pragma-foreign-key-child-index-prefix-quality'],
    'quality source label' => [$default205, 'current_source.foreign_key_child_prefix_quality_source', 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_collation_desc'],
    'next quality source label' => [$default205, 'next_source.foreign_key_child_prefix_quality_source', 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_collation_desc'],
    'current rows count' => [$default205, 'current.foreign_key_child_prefix_quality.rows', 3],
    'current ok count' => [$default205, 'current.foreign_key_child_prefix_quality.ok', 0],
    'current mismatch count' => [$default205, 'current.foreign_key_child_prefix_quality.mismatched', 3],
    'current missing child prefix' => [$default205, 'current.foreign_key_child_prefix_quality.missing_child_prefix', 1],
    'current collation mismatches' => [$default205, 'current.foreign_key_child_prefix_quality.collation_mismatch', 2],
    'current descending prefix' => [$default205, 'current.foreign_key_child_prefix_quality.descending_prefix', 1],
    'current extra columns' => [$default205, 'current.foreign_key_child_prefix_quality.extra_key_columns', 2],
    'next rows count' => [$default205, 'next_counts.foreign_key_child_prefix_quality.rows', 3],
    'next ok count' => [$default205, 'next_counts.foreign_key_child_prefix_quality.ok', 3],
    'next mismatches cleared' => [$default205, 'next_counts.foreign_key_child_prefix_quality.mismatched', 0],
    'next missing cleared' => [$default205, 'next_counts.foreign_key_child_prefix_quality.missing_child_prefix', 0],
    'next collation mismatches cleared' => [$default205, 'next_counts.foreign_key_child_prefix_quality.collation_mismatch', 0],
    'next descending cleared' => [$default205, 'next_counts.foreign_key_child_prefix_quality.descending_prefix', 0],
    'next extra columns retained' => [$default205, 'next_counts.foreign_key_child_prefix_quality.extra_key_columns', 2],
    'delta rows unchanged' => [$default205, 'delta.foreign_key_child_prefix_quality_rows', 0],
    'delta mismatches negative' => [$default205, 'delta.foreign_key_child_prefix_quality_mismatches', -3],
    'delta repaired true' => [$default205, 'delta.foreign_key_child_prefix_quality_repaired', true],
    'delta changed true' => [$default205, 'delta.foreign_key_child_prefix_quality_changed', true],
    'base parent coverage retained' => [$default205, 'current.foreign_key_parent_coverage.covered', 1],
    'base foreign key list retained' => [$default205, 'current.foreign_key_list', 3],
    'total includes quality rows' => [$default205, 'total', 20],
    'first quality row kind' => [$default205, 'rows.14.kind', 'foreign_key_child_prefix_quality'],
    'first quality row phase' => [$default205, 'rows.14.phase', 'current'],
    'first quality site missing' => [$default205, 'rows.14.status', 'missing_child_prefix'],
    'first quality index null' => [$default205, 'rows.14.child_index', null],
    'second quality status mismatch' => [$default205, 'rows.15.status', 'mismatched_child_prefix'],
    'second quality index bad' => [$default205, 'rows.15.child_index', 'wp_termmeta_lookup_bad'],
    'second declared nocase' => [$default205, 'rows.15.child_declared_collation', 'NOCASE'],
    'second index binary' => [$default205, 'rows.15.child_index_collation', 'BINARY'],
    'second descending' => [$default205, 'rows.15.child_index_desc', 1],
    'second collation false' => [$default205, 'rows.15.collation_matches', false],
    'second ascending false' => [$default205, 'rows.15.ascending_prefix', false],
    'third declared rtrim' => [$default205, 'rows.16.child_declared_collation', 'RTRIM'],
    'third index binary' => [$default205, 'rows.16.child_index_collation', 'BINARY'],
    'third ascending true' => [$default205, 'rows.16.ascending_prefix', true],
    'next first site repaired' => [$default205, 'rows.17.child_index', 'wp_termmeta_site_lookup'],
    'next first site status' => [$default205, 'rows.17.status', 'ok'],
    'next second repaired status' => [$default205, 'rows.18.status', 'ok'],
    'next second repaired index' => [$default205, 'rows.18.child_index', 'wp_termmeta_lookup_good'],
    'next second repaired collation' => [$default205, 'rows.18.child_index_collation', 'NOCASE'],
    'next second repaired desc' => [$default205, 'rows.18.child_index_desc', 0],
    'next third repaired rtrim' => [$default205, 'rows.19.child_index_collation', 'RTRIM'],
    'blocked next mismatch remains' => [$blocked205, 'next_counts.foreign_key_child_prefix_quality.mismatched', 3],
    'blocked repaired false' => [$blocked205, 'delta.foreign_key_child_prefix_quality_repaired', false],
    'blocked status still ok from parent coverage' => [$blocked205, 'status', 'ok'],
    'helper current first missing' => [$currentQuality205, '0.status', 'missing_child_prefix'],
    'helper current first index null' => [$currentQuality205, '0.child_index', null],
    'helper current second status' => [$currentQuality205, '1.status', 'mismatched_child_prefix'],
    'helper current third mismatch' => [$currentQuality205, '2.status', 'mismatched_child_prefix'],
    'helper next first phase' => [$nextQuality205, '0.phase', 'next'],
    'helper next first ok' => [$nextQuality205, '0.status', 'ok'],
    'helper next second ok' => [$nextQuality205, '1.status', 'ok'],
    'helper next third ok' => [$nextQuality205, '2.status', 'ok'],
    'current summary includes desc' => [$default205, 'current_source.foreign_key_child_prefix_quality.1', 'current:wp_termmeta_import#1.0:term_slug->wp_terms.slug:wp_termmeta_lookup_bad:NOCASE:BINARY:1:mismatched_child_prefix'],
    'next summary includes repair' => [$default205, 'next_source.foreign_key_child_prefix_quality.1', 'next:wp_termmeta_import#1.0:term_slug->wp_terms.slug:wp_termmeta_lookup_good:NOCASE:NOCASE:0:ok'],
];

$tests = [];
foreach ($cases205 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child prefix quality current source next205 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt205): void {
        $t->same($expected, $valueAt205($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child prefix quality current source next205 paginates quality rows'] = static function (TestRunner $t) use ($page205): void {
    $first = $page205(0, 14);
    $second = $page205(14, 3, $first['next']);
    $third = $page205(17, 3, $second['next']);

    $t->same(14, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 14], $first['next']);
    $t->same('foreign_key_child_prefix_quality', $first['next_row']['kind']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('missing_child_prefix', $second['rows'][0]['status']);
    $t->same('mismatched_child_prefix', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][2]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child prefix quality current source next205 source changes with repair'] = static function (TestRunner $t) use ($page205, $missingNextRecords205): void {
    $first = $page205();
    $second = $page205(nextRecords: $missingNextRecords205);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['delta']['foreign_key_child_prefix_quality_changed']);
    $t->same(false, $second['delta']['foreign_key_child_prefix_quality_changed']);
};

$tests['pragma index xinfo foreignkey child prefix quality current source next205 rejects stale cursor'] = static function (TestRunner $t) use ($page205, $missingNextRecords205): void {
    $first = $page205(0, 14);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page205(14, 3, $first['next'], $missingNextRecords205));
};

$tests['pragma index xinfo foreignkey child prefix quality current source next205 rejects stale offset'] = static function (TestRunner $t) use ($page205): void {
    $first = $page205(0, 14);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page205(15, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey child prefix quality current source next205 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childPrefixQualityRows205([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child prefix quality current source next205 rejects invalid bounds'] = static function (TestRunner $t) use ($page205): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page205(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page205(0, 0));
};

return $tests;
