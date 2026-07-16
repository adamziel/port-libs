<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record229 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords229 = [
    $record229('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL)', 1),
    $record229('index', 'wp_terms_slug_taxonomy_unique', 'wp_terms', 3, 'CREATE UNIQUE INDEX wp_terms_slug_taxonomy_unique ON wp_terms(slug, taxonomy)', 2),
    $record229('index', 'wp_terms_locale_slug_taxonomy_unique', 'wp_terms', 4, 'CREATE UNIQUE INDEX wp_terms_locale_slug_taxonomy_unique ON wp_terms(locale, slug, taxonomy)', 3),
    $record229('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        taxonomy TEXT NOT NULL,
        locale TEXT NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_terms(slug),
        FOREIGN KEY(slug, taxonomy) REFERENCES wp_terms(slug, taxonomy),
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id)
    )", 4),
];

$nextRecords229 = [
    $currentRecords229[0],
    $record229('index', 'wp_terms_slug_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_unique ON wp_terms(slug)', 2),
    $currentRecords229[1],
    $currentRecords229[2],
    $currentRecords229[3],
];

$missingNextRecords229 = [
    $currentRecords229[0],
    $record229('index', 'wp_terms_taxonomy_unique', 'wp_terms', 7, 'CREATE UNIQUE INDEX wp_terms_taxonomy_unique ON wp_terms(taxonomy)', 2),
    $currentRecords229[3],
];

$page229 = static fn (
    int $offset = 0,
    int $limit = 120,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page229(
    $currentRecords229,
    $nextRecords ?? $nextRecords229,
    'PRAGMA main.index_xinfo(wp_terms_slug_taxonomy_unique)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt229 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default229 = static fn (): array => $page229();
$blocked229 = static fn (): array => $page229(nextRecords: $missingNextRecords229);
$currentArity229 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229($currentRecords229);
$nextArity229 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229($nextRecords229, 'next');

$cases229 = [
    'status ok' => [$default229, 'status', 'ok'],
    'operation marker' => [$default229, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next229'],
    'source id length' => [static fn (): array => ['len' => strlen($page229()['source_id'])], 'len', 64],
    'offset default' => [$default229, 'offset', 0],
    'limit default' => [$default229, 'limit', 120],
    'dependency appended' => [$default229, 'dependencies.9', 'sqlite-pragma-foreign-key-parent-key-exact-unique-arity'],
    'base collation retained' => [$default229, 'current.foreign_key_parent_key_collation.rows', 4],
    'exact arity source current' => [$default229, 'current_source.foreign_key_parent_key_exact_arity_source', 'pragma_foreign_key_list_parent_columns_plus_exact_unique_index_arity'],
    'exact arity source next' => [$default229, 'next_source.foreign_key_parent_key_exact_arity_source', 'pragma_foreign_key_list_parent_columns_plus_exact_unique_index_arity'],
    'current rows' => [$default229, 'current.foreign_key_parent_key_exact_arity.rows', 4],
    'current ok rows' => [$default229, 'current.foreign_key_parent_key_exact_arity.ok', 3],
    'current pk ok rows' => [$default229, 'current.foreign_key_parent_key_exact_arity.primary_key_ok', 1],
    'current blocked rows' => [$default229, 'current.foreign_key_parent_key_exact_arity.blocked', 1],
    'current wider row' => [$default229, 'current.foreign_key_parent_key_exact_arity.wider_parent_unique_index', 1],
    'current missing zero' => [$default229, 'current.foreign_key_parent_key_exact_arity.missing_parent_unique_index', 0],
    'current extra columns' => [$default229, 'current.foreign_key_parent_key_exact_arity.extra_index_columns', 1],
    'next rows' => [$default229, 'next_counts.foreign_key_parent_key_exact_arity.rows', 4],
    'next ok rows' => [$default229, 'next_counts.foreign_key_parent_key_exact_arity.ok', 4],
    'next pk ok rows' => [$default229, 'next_counts.foreign_key_parent_key_exact_arity.primary_key_ok', 1],
    'next blocked zero' => [$default229, 'next_counts.foreign_key_parent_key_exact_arity.blocked', 0],
    'next wider zero' => [$default229, 'next_counts.foreign_key_parent_key_exact_arity.wider_parent_unique_index', 0],
    'delta rows unchanged' => [$default229, 'delta.foreign_key_parent_key_exact_arity_rows', 0],
    'delta blockers negative' => [$default229, 'delta.foreign_key_parent_key_exact_arity_blockers', -1],
    'delta repaired true' => [$default229, 'delta.foreign_key_parent_key_exact_arity_repaired', true],
    'delta changed true' => [$default229, 'delta.foreign_key_parent_key_exact_arity_changed', true],
    'total includes exact arity rows' => [$default229, 'total', 50],
    'count complete' => [$default229, 'count', 50],
    'next complete null' => [$default229, 'next', null],
    'current summary slug wider' => [$default229, 'current_source.foreign_key_parent_key_exact_arity.0', 'current:wp_termmeta_import#0.0:slug->wp_terms.slug:wp_terms_slug_taxonomy_unique:parent=slug:index=slug,taxonomy:extra=taxonomy:wider_parent_unique_index'],
    'current summary composite ok' => [$default229, 'current_source.foreign_key_parent_key_exact_arity.1', 'current:wp_termmeta_import#1.0:slug->wp_terms.slug:wp_terms_slug_taxonomy_unique:parent=slug,taxonomy:index=slug,taxonomy:extra=:ok'],
    'current summary primary key' => [$default229, 'current_source.foreign_key_parent_key_exact_arity.3', 'current:wp_termmeta_import#2.0:term_id->wp_terms.term_id:sqlite_primary_key:parent=term_id:index=term_id:extra=:primary_key_ok'],
    'next summary slug repaired' => [$default229, 'next_source.foreign_key_parent_key_exact_arity.0', 'next:wp_termmeta_import#0.0:slug->wp_terms.slug:wp_terms_slug_unique:parent=slug:index=slug:extra=:ok'],
    'first appended row kind' => [$default229, 'rows.42.kind', 'foreign_key_parent_key_exact_arity'],
    'first appended row status' => [$default229, 'rows.42.status', 'wider_parent_unique_index'],
    'first parent column count' => [$default229, 'rows.42.parent_column_count', 1],
    'first index key count' => [$default229, 'rows.42.index_key_count', 2],
    'first extra column' => [$default229, 'rows.42.extra_index_columns.0', 'taxonomy'],
    'first wider index' => [$default229, 'rows.42.parent_unique_index', 'wp_terms_slug_taxonomy_unique'],
    'composite first exact ok' => [$default229, 'rows.43.status', 'ok'],
    'composite second index column' => [$default229, 'rows.44.index_column', 'taxonomy'],
    'primary key row status' => [$default229, 'rows.45.status', 'primary_key_ok'],
    'next slug repaired index' => [$default229, 'rows.46.parent_unique_index', 'wp_terms_slug_unique'],
    'next composite exact index' => [$default229, 'rows.47.parent_unique_index', 'wp_terms_slug_taxonomy_unique'],
    'next primary key status' => [$default229, 'rows.49.status', 'primary_key_ok'],
    'blocked next missing rows' => [$blocked229, 'next_counts.foreign_key_parent_key_exact_arity.missing_parent_unique_index', 3],
    'blocked next wider zero' => [$blocked229, 'next_counts.foreign_key_parent_key_exact_arity.wider_parent_unique_index', 0],
    'blocked repaired false' => [$blocked229, 'delta.foreign_key_parent_key_exact_arity_repaired', false],
    'helper current first kind' => [$currentArity229, '0.kind', 'foreign_key_parent_key_exact_arity'],
    'helper current first status' => [$currentArity229, '0.status', 'wider_parent_unique_index'],
    'helper current first extra' => [$currentArity229, '0.extra_index_columns.0', 'taxonomy'],
    'helper current composite ok' => [$currentArity229, '1.status', 'ok'],
    'helper current composite second to' => [$currentArity229, '2.to', 'taxonomy'],
    'helper current pk index' => [$currentArity229, '3.parent_unique_index', 'sqlite_primary_key'],
    'helper next first phase' => [$nextArity229, '0.phase', 'next'],
    'helper next first ok' => [$nextArity229, '0.status', 'ok'],
    'helper next first key count' => [$nextArity229, '0.index_key_count', 1],
];

$tests = [];
foreach ($cases229 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey exact parent arity current source next229 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt229): void {
        $t->same($expected, $valueAt229($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey exact parent arity current source next229 paginates exact arity rows'] = static function (TestRunner $t) use ($page229): void {
    $first = $page229(0, 42);
    $second = $page229(42, 4, $first['next']);
    $third = $page229(46, 4, $second['next']);

    $t->same(42, $first['count']);
    $t->same('foreign_key_parent_key_exact_arity', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 42], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('wider_parent_unique_index', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][0]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey exact parent arity current source next229 reports missing exact parent index'] = static function (TestRunner $t) use ($record229): void {
    $records = [
        $record229('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, locale TEXT)', 1),
        $record229('index', 'parent_locale_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_locale_unique ON parent(locale)', 2),
        $record229('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(0, $rows[0]['index_key_count']);
};

$tests['pragma index xinfo foreignkey exact parent arity current source next229 ignores partial wider indexes'] = static function (TestRunner $t) use ($record229): void {
    $records = [
        $record229('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, locale TEXT, active INTEGER)', 1),
        $record229('index', 'parent_code_locale_partial', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_locale_partial ON parent(code, locale) WHERE active = 1', 2),
        $record229('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['parent_unique_index']);
};

$tests['pragma index xinfo foreignkey exact parent arity current source next229 rejects stale cursor'] = static function (TestRunner $t) use ($page229, $missingNextRecords229): void {
    $first = $page229(0, 42);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page229(42, 4, $first['next'], $missingNextRecords229));
};

$tests['pragma index xinfo foreignkey exact parent arity current source next229 rejects stale offset'] = static function (TestRunner $t) use ($page229): void {
    $first = $page229(0, 42);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page229(43, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey exact parent arity current source next229 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyExactArityRows229([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey exact parent arity current source next229 rejects invalid bounds'] = static function (TestRunner $t) use ($page229): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page229(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page229(0, 0));
};

return $tests;
