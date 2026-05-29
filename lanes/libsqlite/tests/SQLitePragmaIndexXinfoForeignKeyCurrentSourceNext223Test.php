<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record223 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords223 = [
    $record223('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, locale TEXT NOT NULL, UNIQUE(term_id, locale))', 1),
    $record223('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record223('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        locale TEXT NOT NULL,
        parent_term INTEGER,
        FOREIGN KEY(term_id, locale) REFERENCES wp_terms(term_id, locale) MATCH FULL ON UPDATE CASCADE,
        FOREIGN KEY(parent_term) REFERENCES wp_terms(term_id) MATCH SIMPLE ON DELETE SET NULL
    )", 3),
    $record223('index', 'wp_postmeta_import_term_locale', 'wp_postmeta_import', 5, 'CREATE INDEX wp_postmeta_import_term_locale ON wp_postmeta_import(term_id, locale)', 4),
];

$nextRecords223 = [
    $currentRecords223[0],
    $currentRecords223[1],
    $record223('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        locale TEXT NOT NULL,
        parent_term INTEGER,
        FOREIGN KEY(term_id, locale) REFERENCES wp_terms(term_id, locale) ON UPDATE CASCADE,
        FOREIGN KEY(parent_term) REFERENCES wp_terms(term_id) ON DELETE SET NULL
    )", 3),
    $currentRecords223[3],
];

$blockedNextRecords223 = [
    $currentRecords223[0],
    $currentRecords223[1],
    $record223('table', 'wp_postmeta_import', 'wp_postmeta_import', 4, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        locale TEXT NOT NULL,
        parent_term INTEGER,
        FOREIGN KEY(term_id, locale) REFERENCES wp_terms(term_id, locale) MATCH PARTIAL ON UPDATE CASCADE,
        FOREIGN KEY(parent_term) REFERENCES wp_terms(term_id) MATCH SIMPLE ON DELETE SET NULL
    )", 3),
    $currentRecords223[3],
];

$page223 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page223(
    $currentRecords223,
    $nextRecords ?? $nextRecords223,
    'PRAGMA main.index_xinfo(wp_postmeta_import_term_locale)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt223 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default223 = static fn (): array => $page223();
$blocked223 = static fn (): array => $page223(nextRecords: $blockedNextRecords223);
$currentMatch223 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::matchRows223($currentRecords223);
$nextMatch223 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::matchRows223($nextRecords223, 'next');

$cases223 = [
    'status ok' => [$default223, 'status', 'ok'],
    'operation marker' => [$default223, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next223'],
    'source id length' => [static fn (): array => ['len' => strlen($page223()['source_id'])], 'len', 64],
    'offset default' => [$default223, 'offset', 0],
    'limit default' => [$default223, 'limit', 100],
    'dependency appended' => [$default223, 'dependencies.8', 'sqlite-pragma-foreign-key-match-clause'],
    'source current marker' => [$default223, 'current_source.foreign_key_match_clause_source', 'pragma_foreign_key_list_match_column'],
    'source next marker' => [$default223, 'next_source.foreign_key_match_clause_source', 'pragma_foreign_key_list_match_column'],
    'base restrict dependency retained' => [$default223, 'dependencies.7', 'sqlite-pragma-foreign-key-restrict-deferral-timing'],
    'base child lookup rows retained' => [$default223, 'current.foreign_key_child_action_lookup.rows', 3],
    'current match rows' => [$default223, 'current.foreign_key_match_clause.rows', 3],
    'current default match rows' => [$default223, 'current.foreign_key_match_clause.default_match', 1],
    'current custom match rows' => [$default223, 'current.foreign_key_match_clause.custom_match', 2],
    'current composite match rows' => [$default223, 'current.foreign_key_match_clause.composite_columns', 1],
    'next match rows repaired' => [$default223, 'next_counts.foreign_key_match_clause.rows', 3],
    'next default match rows repaired' => [$default223, 'next_counts.foreign_key_match_clause.default_match', 3],
    'next custom match rows repaired' => [$default223, 'next_counts.foreign_key_match_clause.custom_match', 0],
    'delta custom negative' => [$default223, 'delta.foreign_key_match_clause_custom', -2],
    'delta rows unchanged' => [$default223, 'delta.foreign_key_match_clause_rows', 0],
    'delta repaired true' => [$default223, 'delta.foreign_key_match_clause_repaired', true],
    'delta changed true' => [$default223, 'delta.foreign_key_match_clause_changed', true],
    'total includes match rows' => [$default223, 'total', 32],
    'count complete' => [$default223, 'count', 32],
    'next complete null' => [$default223, 'next', null],
    'current summary custom full' => [$default223, 'current_source.foreign_key_match_clause.0', 'current:wp_postmeta_import#0.0:term_id->wp_terms.term_id:FULL:custom_match_name'],
    'current summary custom composite' => [$default223, 'current_source.foreign_key_match_clause.1', 'current:wp_postmeta_import#0.1:locale->wp_terms.locale:FULL:custom_match_name'],
    'current summary simple default' => [$default223, 'current_source.foreign_key_match_clause.2', 'current:wp_postmeta_import#1.0:parent_term->wp_terms.term_id:SIMPLE:default_match_semantics'],
    'next summary repaired none' => [$default223, 'next_source.foreign_key_match_clause.0', 'next:wp_postmeta_import#0.0:term_id->wp_terms.term_id:NONE:default_match_semantics'],
    'blocked next custom retained' => [$blocked223, 'next_counts.foreign_key_match_clause.custom_match', 2],
    'blocked next default simple retained' => [$blocked223, 'next_counts.foreign_key_match_clause.default_match', 1],
    'blocked repaired false' => [$blocked223, 'delta.foreign_key_match_clause_repaired', false],
    'helper current first kind' => [$currentMatch223, '0.kind', 'foreign_key_match_clause'],
    'helper current first match' => [$currentMatch223, '0.match', 'FULL'],
    'helper current first status' => [$currentMatch223, '0.status', 'custom_match_name'],
    'helper current first default false' => [$currentMatch223, '0.uses_default_match', false],
    'helper current first message' => [$currentMatch223, '0.message', 'foreign key wp_postmeta_import->wp_terms declares MATCH FULL; SQLite records the name but still uses built-in MATCH SIMPLE semantics'],
    'helper current composite seq one' => [$currentMatch223, '1.seq', 1],
    'helper simple match default true' => [$currentMatch223, '2.uses_default_match', true],
    'helper simple match message' => [$currentMatch223, '2.message', 'foreign key wp_postmeta_import->wp_terms uses SQLite default MATCH semantics'],
    'helper next first default' => [$nextMatch223, '0.status', 'default_match_semantics'],
    'helper next first match none' => [$nextMatch223, '0.match', 'NONE'],
    'helper next parent term default' => [$nextMatch223, '2.uses_default_match', true],
];

$tests = [];
foreach ($cases223 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey match current source next223 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt223): void {
        $t->same($expected, $valueAt223($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey match current source next223 paginates match rows'] = static function (TestRunner $t) use ($page223): void {
    $first = $page223(0, 26);
    $second = $page223(26, 2, $first['next']);
    $third = $page223(28, 2, $second['next']);

    $t->same(26, $first['count']);
    $t->same('foreign_key_match_clause', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 26], $first['next']);
    $t->same('FULL', $second['rows'][0]['match']);
    $t->same('custom_match_name', $second['rows'][1]['status']);
    $t->same(2, $third['count']);
    $t->same('SIMPLE', $third['rows'][0]['match']);
    $t->same('next', $third['rows'][1]['phase']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 30], $third['next']);
};

$tests['pragma index xinfo foreignkey match current source next223 keeps simple match as default semantics'] = static function (TestRunner $t) use ($currentMatch223): void {
    $rows = $currentMatch223();

    $t->same(3, count($rows));
    $t->same(['FULL', 'FULL', 'SIMPLE'], array_column($rows, 'match'));
    $t->same(['custom_match_name', 'custom_match_name', 'default_match_semantics'], array_column($rows, 'status'));
};

$tests['pragma index xinfo foreignkey match current source next223 rejects stale cursor'] = static function (TestRunner $t) use ($page223, $blockedNextRecords223): void {
    $first = $page223(0, 29);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page223(29, 2, $first['next'], $blockedNextRecords223));
};

$tests['pragma index xinfo foreignkey match current source next223 rejects stale offset'] = static function (TestRunner $t) use ($page223): void {
    $first = $page223(0, 29);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page223(30, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey match current source next223 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::matchRows223([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey match current source next223 rejects invalid bounds'] = static function (TestRunner $t) use ($page223): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page223(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page223(0, 0));
};

return $tests;
