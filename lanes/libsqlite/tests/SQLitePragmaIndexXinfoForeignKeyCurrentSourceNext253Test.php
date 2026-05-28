<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record253 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords253 = [
    $record253('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 1),
    $record253('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record253('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_term_id INTEGER NOT NULL,
        slug_ref TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms(slug) ON DELETE SET NULL,
        term_ref INTEGER GENERATED ALWAYS AS (raw_term_id) STORED NOT NULL,
        nullable_slug TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL REFERENCES wp_terms(slug) ON DELETE SET NULL,
        FOREIGN KEY(term_ref) REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT
    )", 3),
];

$nextRecords253 = [
    $currentRecords253[0],
    $currentRecords253[1],
    $record253('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_term_id INTEGER NOT NULL,
        slug_ref TEXT REFERENCES wp_terms(slug) ON DELETE SET NULL,
        term_ref INTEGER DEFAULT 0 REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT,
        nullable_slug TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL REFERENCES wp_terms(slug) ON DELETE SET NULL
    )", 3),
];

$blockedNextRecords253 = $currentRecords253;

$page253 = static fn (
    int $offset = 0,
    int $limit = 360,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::page(
    $currentRecords253,
    $nextRecords ?? $nextRecords253,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt253 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default253 = static fn (): array => $page253();
$blocked253 = static fn (): array => $page253(nextRecords: $blockedNextRecords253);
$currentRows253 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::generatedChildActionRows($currentRecords253);
$nextRows253 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::generatedChildActionRows($nextRecords253, 'next');
$currentPageRows253 = static fn (): array => array_values(array_filter(
    $page253()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_generated_child_action'
        && ($row['phase'] ?? null) === 'current',
));

$cases253 = [
    'status ok' => [$default253, 'status', 'ok'],
    'operation marker' => [$default253, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next253'],
    'source id length' => [static fn (): array => ['len' => strlen($page253()['source_id'])], 'len', 64],
    'offset default' => [$default253, 'offset', 0],
    'limit default' => [$default253, 'limit', 360],
    'base generated child retained' => [$default253, 'current.foreign_key_generated_child_columns.rows', 3],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-table-xinfo-generated-child-actions', $page253()['dependencies'], true)], 'has', true],
    'action source current' => [$default253, 'current_source.foreign_key_generated_child_action_source', 'pragma_foreign_key_list_actions_plus_pragma_table_xinfo_generated_child_columns'],
    'action source next' => [$default253, 'next_source.foreign_key_generated_child_action_source', 'pragma_foreign_key_list_actions_plus_pragma_table_xinfo_generated_child_columns'],
    'current action rows' => [$default253, 'current.foreign_key_generated_child_actions.rows', 3],
    'current blockers' => [$default253, 'current.foreign_key_generated_child_actions.blocked', 2],
    'current ok nullable' => [$default253, 'current.foreign_key_generated_child_actions.ok', 1],
    'current set null rows' => [$default253, 'current.foreign_key_generated_child_actions.set_null', 2],
    'current set default rows' => [$default253, 'current.foreign_key_generated_child_actions.set_default', 1],
    'current virtual rows' => [$default253, 'current.foreign_key_generated_child_actions.virtual', 2],
    'current stored rows' => [$default253, 'current.foreign_key_generated_child_actions.stored', 1],
    'current not null rows' => [$default253, 'current.foreign_key_generated_child_actions.notnull', 2],
    'next action rows' => [$default253, 'next_counts.foreign_key_generated_child_actions.rows', 1],
    'next blockers cleared' => [$default253, 'next_counts.foreign_key_generated_child_actions.blocked', 0],
    'next ok generated nullable' => [$default253, 'next_counts.foreign_key_generated_child_actions.ok', 1],
    'delta rows decreased' => [$default253, 'delta.foreign_key_generated_child_action_rows', -2],
    'delta blockers decreased' => [$default253, 'delta.foreign_key_generated_child_action_blockers', -2],
    'delta repaired true' => [$default253, 'delta.foreign_key_generated_child_action_repaired', true],
    'delta changed true' => [$default253, 'delta.foreign_key_generated_child_action_changed', true],
    'complete next null' => [$default253, 'next', null],
    'current summary first' => [$default253, 'current_source.foreign_key_generated_child_actions.0', 'current:wp_termmeta_import#0.0:slug_ref->wp_terms.slug:SET NULL:hidden=2:storage=virtual:notnull=1:set_null_generated_notnull_child'],
    'current summary nullable' => [$default253, 'current_source.foreign_key_generated_child_actions.1', 'current:wp_termmeta_import#1.0:nullable_slug->wp_terms.slug:SET NULL:hidden=2:storage=virtual:notnull=0:ok'],
    'current summary stored default' => [$default253, 'current_source.foreign_key_generated_child_actions.2', 'current:wp_termmeta_import#2.0:term_ref->wp_terms.term_id:SET DEFAULT:hidden=3:storage=stored:notnull=1:set_default_generated_null_child'],
    'first row kind' => [$currentPageRows253, '0.kind', 'foreign_key_generated_child_action'],
    'first row action' => [$currentPageRows253, '0.action', 'SET NULL'],
    'first row status' => [$currentPageRows253, '0.status', 'set_null_generated_notnull_child'],
    'first row hidden' => [$currentPageRows253, '0.child_hidden', 2],
    'first row notnull' => [$currentPageRows253, '0.child_notnull', true],
    'stored row status' => [$currentPageRows253, '2.status', 'set_default_generated_null_child'],
    'stored row storage' => [$currentPageRows253, '2.child_generated_storage', 'stored'],
    'stored row default' => [$currentPageRows253, '2.child_default', null],
    'helper current count' => [static fn (): array => ['count' => count($currentRows253())], 'count', 3],
    'helper current message' => [$currentRows253, '0.message', 'foreign key wp_termmeta_import.slug_ref SET NULL action targets a generated NOT NULL child column only visible through PRAGMA table_xinfo'],
    'helper next count' => [static fn (): array => ['count' => count($nextRows253())], 'count', 1],
    'helper next status' => [$nextRows253, '0.status', 'ok'],
    'blocked next blockers remain' => [$blocked253, 'next_counts.foreign_key_generated_child_actions.blocked', 2],
    'blocked repaired false' => [$blocked253, 'delta.foreign_key_generated_child_action_repaired', false],
];

$tests = [];
foreach ($cases253 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey generated child action current source next253 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt253): void {
        $t->same($expected, $valueAt253($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey generated child action current source next253 paginates appended rows'] = static function (TestRunner $t) use ($page253): void {
    $full = $page253();
    $baseCount = $full['total'] - 4;
    $first = $page253(0, $baseCount);
    $second = $page253($baseCount, 2, $first['next']);
    $third = $page253($baseCount + 2, 3, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_generated_child_action', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('set_null_generated_notnull_child', $second['rows'][0]['status']);
    $t->same('set_default_generated_null_child', $third['rows'][0]['status']);
    $t->same('next', $third['rows'][1]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey generated child action current source next253 ignores visible child actions'] = static function (TestRunner $t) use ($record253): void {
    $records = [
        $record253('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record253('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record253('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT NOT NULL REFERENCES parent(slug) ON DELETE SET NULL)', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::generatedChildActionRows($records));
};

$tests['pragma index xinfo foreignkey generated child action current source next253 ignores generated child without set action'] = static function (TestRunner $t) use ($record253): void {
    $records = [
        $record253('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record253('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record253('table', 'child', 'child', 4, 'CREATE TABLE child(raw_slug TEXT, slug_ref TEXT AS (lower(raw_slug)) STORED REFERENCES parent(slug) ON DELETE CASCADE)', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::generatedChildActionRows($records));
};

$tests['pragma index xinfo foreignkey generated child action current source next253 rejects stale cursor'] = static function (TestRunner $t) use ($page253, $blockedNextRecords253): void {
    $full = $page253();
    $first = $page253(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page253($full['total'] - 4, 2, $first['next'], $blockedNextRecords253));
};

$tests['pragma index xinfo foreignkey generated child action current source next253 rejects stale offset'] = static function (TestRunner $t) use ($page253): void {
    $full = $page253();
    $first = $page253(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page253($full['total'] - 3, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey generated child action current source next253 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253::generatedChildActionRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey generated child action current source next253 rejects invalid bounds'] = static function (TestRunner $t) use ($page253): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page253(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page253(0, 0));
};

return $tests;
