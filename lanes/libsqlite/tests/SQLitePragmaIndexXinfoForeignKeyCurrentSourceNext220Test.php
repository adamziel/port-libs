<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record220 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords220 = [
    $record220('table', 'wp_locale_terms', 'wp_locale_terms', 2, 'CREATE TABLE wp_locale_terms(slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, label TEXT, UNIQUE(slug, locale))', 1),
    $record220('index', 'wp_locale_terms_slug_locale_unique', 'wp_locale_terms', 3, 'CREATE UNIQUE INDEX wp_locale_terms_slug_locale_unique ON wp_locale_terms(slug COLLATE BINARY, locale COLLATE RTRIM)', 2),
    $record220('table', 'wp_options', 'wp_options', 4, "CREATE TABLE wp_options(
        option_id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        option_name TEXT NOT NULL,
        option_value TEXT,
        FOREIGN KEY(slug, locale) REFERENCES wp_locale_terms(slug, locale)
    )", 3),
    $record220('index', 'wp_options_locale_lookup', 'wp_options', 5, 'CREATE INDEX wp_options_locale_lookup ON wp_options(slug, locale, option_name)', 4),
];

$nextRecords220 = [
    $currentRecords220[0],
    $record220('index', 'wp_locale_terms_slug_locale_unique', 'wp_locale_terms', 3, 'CREATE UNIQUE INDEX wp_locale_terms_slug_locale_unique ON wp_locale_terms(slug COLLATE NOCASE, locale COLLATE RTRIM)', 2),
    $currentRecords220[2],
    $currentRecords220[3],
];

$missingNextRecords220 = [
    $currentRecords220[0],
    $currentRecords220[2],
    $currentRecords220[3],
];

$page220 = static fn (
    int $offset = 0,
    int $limit = 120,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page220(
    $currentRecords220,
    $nextRecords ?? $nextRecords220,
    'PRAGMA main.index_xinfo(wp_locale_terms_slug_locale_unique)',
    'PRAGMA main.foreign_key_list(wp_options)',
    $offset,
    $limit,
    $resume,
);

$valueAt220 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default220 = static fn (): array => $page220();
$blocked220 = static fn (): array => $page220(nextRecords: $missingNextRecords220);
$currentCollations220 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCollationRows220($currentRecords220);
$nextCollations220 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCollationRows220($nextRecords220, 'next');

$cases220 = [
    'status ok' => [$default220, 'status', 'ok'],
    'operation marker' => [$default220, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next220'],
    'source id length' => [static fn (): array => ['len' => strlen($page220()['source_id'])], 'len', 64],
    'offset default' => [$default220, 'offset', 0],
    'limit default' => [$default220, 'limit', 120],
    'dependency appended' => [$default220, 'dependencies.8', 'sqlite-pragma-foreign-key-parent-index-collation'],
    'base parent prefix retained' => [$default220, 'current.foreign_key_parent_key_prefix.rows', 2],
    'collation source current' => [$default220, 'current_source.foreign_key_parent_collation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collation'],
    'collation source next' => [$default220, 'next_source.foreign_key_parent_collation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collation'],
    'current collation rows' => [$default220, 'current.foreign_key_parent_collations.rows', 2],
    'current mismatch rows' => [$default220, 'current.foreign_key_parent_collations.mismatch', 1],
    'current ok rows' => [$default220, 'current.foreign_key_parent_collations.ok', 1],
    'current nocase rows' => [$default220, 'current.foreign_key_parent_collations.nocase', 1],
    'current rtrim rows' => [$default220, 'current.foreign_key_parent_collations.rtrim', 1],
    'current binary rows zero' => [$default220, 'current.foreign_key_parent_collations.binary', 0],
    'next collation rows' => [$default220, 'next_counts.foreign_key_parent_collations.rows', 2],
    'next mismatch cleared' => [$default220, 'next_counts.foreign_key_parent_collations.mismatch', 0],
    'next ok rows' => [$default220, 'next_counts.foreign_key_parent_collations.ok', 2],
    'next blocked zero' => [$default220, 'next_counts.foreign_key_parent_collations.blocked', 0],
    'delta rows unchanged' => [$default220, 'delta.foreign_key_parent_collation_rows', 0],
    'delta mismatch negative' => [$default220, 'delta.foreign_key_parent_collation_mismatches', -1],
    'delta repaired true' => [$default220, 'delta.foreign_key_parent_collation_repaired', true],
    'delta changed true' => [$default220, 'delta.foreign_key_parent_collation_changed', true],
    'count complete' => [$default220, 'count', 22],
    'total includes collation rows' => [$default220, 'total', 22],
    'next null' => [$default220, 'next', null],
    'current summary mismatch' => [$default220, 'current_source.foreign_key_parent_collations.0', 'current:wp_options#0.0:slug->wp_locale_terms.slug:parent=slug,locale:wp_locale_terms_slug_locale_unique:column=NOCASE:index=BINARY:parent_collation_mismatch'],
    'current summary ok' => [$default220, 'current_source.foreign_key_parent_collations.1', 'current:wp_options#0.1:locale->wp_locale_terms.locale:parent=slug,locale:wp_locale_terms_slug_locale_unique:column=RTRIM:index=RTRIM:ok'],
    'next summary repaired' => [$default220, 'next_source.foreign_key_parent_collations.0', 'next:wp_options#0.0:slug->wp_locale_terms.slug:parent=slug,locale:wp_locale_terms_slug_locale_unique:column=NOCASE:index=NOCASE:ok'],
    'first appended kind' => [$default220, 'rows.18.kind', 'foreign_key_parent_collation'],
    'first appended status' => [$default220, 'rows.18.status', 'parent_collation_mismatch'],
    'first appended parent index' => [$default220, 'rows.18.parent_unique_index', 'wp_locale_terms_slug_locale_unique'],
    'first appended parent column collation' => [$default220, 'rows.18.parent_column_collation', 'NOCASE'],
    'first appended parent index collation' => [$default220, 'rows.18.parent_index_collation', 'BINARY'],
    'second appended ok' => [$default220, 'rows.19.status', 'ok'],
    'next first repaired ok' => [$default220, 'rows.20.status', 'ok'],
    'next first repaired collation' => [$default220, 'rows.20.parent_index_collation', 'NOCASE'],
    'blocked missing parent index rows' => [$blocked220, 'next_counts.foreign_key_parent_collations.missing_parent_unique_index', 2],
    'blocked next mismatches zero' => [$blocked220, 'next_counts.foreign_key_parent_collations.mismatch', 0],
    'blocked repaired false' => [$blocked220, 'delta.foreign_key_parent_collation_repaired', false],
    'helper current first kind' => [$currentCollations220, '0.kind', 'foreign_key_parent_collation'],
    'helper current first status' => [$currentCollations220, '0.status', 'parent_collation_mismatch'],
    'helper current first matches false' => [$currentCollations220, '0.collation_matches', false],
    'helper current first declared nocase' => [$currentCollations220, '0.parent_column_collation', 'NOCASE'],
    'helper current first index binary' => [$currentCollations220, '0.parent_index_collation', 'BINARY'],
    'helper current second matches true' => [$currentCollations220, '1.collation_matches', true],
    'helper current second collation rtrim' => [$currentCollations220, '1.parent_column_collation', 'RTRIM'],
    'helper next first phase' => [$nextCollations220, '0.phase', 'next'],
    'helper next first status' => [$nextCollations220, '0.status', 'ok'],
    'helper next second index collation' => [$nextCollations220, '1.parent_index_collation', 'RTRIM'],
];

$tests = [];
foreach ($cases220 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent collation current source next220 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt220): void {
        $t->same($expected, $valueAt220($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent collation current source next220 paginates appended rows'] = static function (TestRunner $t) use ($page220): void {
    $first = $page220(0, 18);
    $second = $page220(18, 2, $first['next']);
    $third = $page220(20, 2, $second['next']);

    $t->same(18, $first['count']);
    $t->same('foreign_key_parent_collation', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 18], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('parent_collation_mismatch', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][1]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent collation current source next220 accepts primary key parent collation'] = static function (TestRunner $t) use ($record220): void {
    $records = [
        $record220('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT COLLATE NOCASE PRIMARY KEY)', 1),
        $record220('table', 'child', 'child', 3, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCollationRows220($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('sqlite_primary_key', $rows[0]['parent_unique_index']);
    $t->same('NOCASE', $rows[0]['parent_column_collation']);
    $t->same('NOCASE', $rows[0]['parent_index_collation']);
};

$tests['pragma index xinfo foreignkey parent collation current source next220 reports missing parent unique index'] = static function (TestRunner $t) use ($record220): void {
    $records = [
        $record220('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT COLLATE RTRIM)', 1),
        $record220('table', 'child', 'child', 3, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCollationRows220($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['parent_unique_index']);
    $t->same('RTRIM', $rows[0]['parent_column_collation']);
};

$tests['pragma index xinfo foreignkey parent collation current source next220 rejects stale cursor'] = static function (TestRunner $t) use ($page220, $missingNextRecords220): void {
    $first = $page220(0, 18);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page220(18, 2, $first['next'], $missingNextRecords220));
};

$tests['pragma index xinfo foreignkey parent collation current source next220 rejects stale offset'] = static function (TestRunner $t) use ($page220): void {
    $first = $page220(0, 18);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page220(19, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey parent collation current source next220 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCollationRows220([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent collation current source next220 rejects invalid bounds'] = static function (TestRunner $t) use ($page220): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page220(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page220(0, 0));
};

return $tests;
