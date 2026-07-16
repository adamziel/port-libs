<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record210 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords210 = [
    $record210('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY, domain TEXT NOT NULL)', 1),
    $record210('table', 'wp_terms', 'wp_terms', 3, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL)', 2),
    $record210('table', 'wp_option_stage', 'wp_option_stage', 4, "CREATE TABLE wp_option_stage(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER DEFAULT 1 REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        term_id INTEGER REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT,
        fallback_site INTEGER DEFAULT 1,
        fallback_term INTEGER,
        option_name TEXT NOT NULL,
        FOREIGN KEY(fallback_site, fallback_term) REFERENCES wp_terms(site_id, term_id) ON UPDATE SET DEFAULT ON DELETE SET DEFAULT
    )", 3),
    $record210('index', 'wp_option_stage_lookup', 'wp_option_stage', 5, 'CREATE INDEX wp_option_stage_lookup ON wp_option_stage(site_id, term_id, option_name)', 4),
];

$nextRecords210 = [
    $currentRecords210[0],
    $record210('table', 'wp_terms', 'wp_terms', 3, 'CREATE TABLE wp_terms(site_id INTEGER DEFAULT 1, term_id INTEGER DEFAULT 0, slug TEXT NOT NULL, PRIMARY KEY(site_id, term_id)) WITHOUT ROWID', 2),
    $record210('table', 'wp_option_stage', 'wp_option_stage', 4, "CREATE TABLE wp_option_stage(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER DEFAULT 1 REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        term_id INTEGER DEFAULT 0 REFERENCES wp_terms(term_id) ON UPDATE SET DEFAULT,
        fallback_site INTEGER DEFAULT 1,
        fallback_term INTEGER DEFAULT 0,
        option_name TEXT NOT NULL,
        FOREIGN KEY(fallback_site, fallback_term) REFERENCES wp_terms(site_id, term_id) ON UPDATE SET DEFAULT ON DELETE SET DEFAULT
    )", 3),
    $currentRecords210[3],
];

$blockedNextRecords210 = [
    $currentRecords210[0],
    $nextRecords210[1],
    $currentRecords210[2],
    $currentRecords210[3],
];

$page210 = static fn (
    int $offset = 0,
    int $limit = 90,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page210(
    $currentRecords210,
    $nextRecords ?? $nextRecords210,
    'PRAGMA main.index_xinfo(wp_option_stage_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt210 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default210 = static fn (): array => $page210();
$blocked210 = static fn (): array => $page210(nextRecords: $blockedNextRecords210);
$currentDefaults210 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::setDefaultChildRows210($currentRecords210);
$nextDefaults210 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::setDefaultChildRows210($nextRecords210, 'next');

$cases210 = [
    'status ok' => [$default210, 'status', 'ok'],
    'operation marker' => [$default210, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next210'],
    'next209 dependency retained' => [$default210, 'dependencies.6', 'sqlite-pragma-foreign-key-set-default-child-defaults'],
    'normalized index sql retained' => [$default210, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_option_stage_lookup")'],
    'normalized fk sql retained' => [$default210, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_option_stage")'],
    'set default source current' => [$default210, 'current_source.foreign_key_set_default_child_source', 'pragma_foreign_key_list_set_default_plus_table_info_child_defaults'],
    'set default source next' => [$default210, 'next_source.foreign_key_set_default_child_source', 'pragma_foreign_key_list_set_default_plus_table_info_child_defaults'],
    'base rowid retained' => [$default210, 'current.foreign_key_parent_rowid_alias.rows', 3],
    'base implicit pk retained' => [$default210, 'current.foreign_key_implicit_parent_primary_key.rows', 0],
    'current set default rows' => [$default210, 'current.foreign_key_set_default_child_columns.rows', 3],
    'current defaults present rows' => [$default210, 'current.foreign_key_set_default_child_columns.set_default_child_defaults_present', 1],
    'current missing default rows' => [$default210, 'current.foreign_key_set_default_child_columns.missing_child_default', 2],
    'current update set default count' => [$default210, 'current.foreign_key_set_default_child_columns.on_update_set_default', 2],
    'current delete set default count' => [$default210, 'current.foreign_key_set_default_child_columns.on_delete_set_default', 2],
    'current child column count' => [$default210, 'current.foreign_key_set_default_child_columns.child_columns', 4],
    'current missing column count' => [$default210, 'current.foreign_key_set_default_child_columns.missing_columns', 2],
    'next set default rows' => [$default210, 'next_counts.foreign_key_set_default_child_columns.rows', 3],
    'next defaults present rows' => [$default210, 'next_counts.foreign_key_set_default_child_columns.set_default_child_defaults_present', 3],
    'next missing default rows' => [$default210, 'next_counts.foreign_key_set_default_child_columns.missing_child_default', 0],
    'next update set default count' => [$default210, 'next_counts.foreign_key_set_default_child_columns.on_update_set_default', 2],
    'next delete set default count' => [$default210, 'next_counts.foreign_key_set_default_child_columns.on_delete_set_default', 2],
    'delta rows unchanged' => [$default210, 'delta.foreign_key_set_default_child_rows', 0],
    'delta missing repaired' => [$default210, 'delta.foreign_key_set_default_child_missing_defaults', -2],
    'delta repaired true' => [$default210, 'delta.foreign_key_set_default_child_repaired', true],
    'delta changed true' => [$default210, 'delta.foreign_key_set_default_child_changed', true],
    'total includes set default rows' => [$default210, 'total', 34],
    'count default' => [$default210, 'count', 34],
    'complete next null' => [$default210, 'next', null],
    'current source first summary' => [$default210, 'current_source.foreign_key_set_default_child_columns.0', 'current:wp_option_stage#0->wp_sites:child=site_id:defaults=site_id=1:actions=on_delete:set_default_child_defaults_present'],
    'current source missing summary' => [$default210, 'current_source.foreign_key_set_default_child_columns.1', 'current:wp_option_stage#1->wp_terms:child=term_id:defaults=term_id=NULL:actions=on_update:missing_child_default'],
    'next source repaired summary' => [$default210, 'next_source.foreign_key_set_default_child_columns.1', 'next:wp_option_stage#1->wp_terms:child=term_id:defaults=term_id=0:actions=on_update:set_default_child_defaults_present'],
    'first default row kind' => [$default210, 'rows.28.kind', 'foreign_key_set_default_child_default'],
    'first default row status' => [$default210, 'rows.28.status', 'set_default_child_defaults_present'],
    'first default row default value' => [$default210, 'rows.28.child_defaults.site_id', '1'],
    'second default row status missing' => [$default210, 'rows.29.status', 'missing_child_default'],
    'second default row missing column' => [$default210, 'rows.29.missing_child_defaults.0', 'term_id'],
    'second default row update action' => [$default210, 'rows.29.set_default_actions.0', 'on_update'],
    'third default row composite missing' => [$default210, 'rows.30.missing_child_defaults.0', 'fallback_term'],
    'third default row delete action' => [$default210, 'rows.30.set_default_actions.1', 'on_delete'],
    'next first default present' => [$default210, 'rows.31.status', 'set_default_child_defaults_present'],
    'next second default present' => [$default210, 'rows.32.status', 'set_default_child_defaults_present'],
    'next composite default present' => [$default210, 'rows.33.status', 'set_default_child_defaults_present'],
    'blocked next missing retained' => [$blocked210, 'next_counts.foreign_key_set_default_child_columns.missing_child_default', 2],
    'blocked repaired false' => [$blocked210, 'delta.foreign_key_set_default_child_repaired', false],
    'blocked row status' => [$blocked210, 'rows.33.status', 'missing_child_default'],
    'helper current count' => [$currentDefaults210, '0.kind', 'foreign_key_set_default_child_default'],
    'helper current missing' => [$currentDefaults210, '1.status', 'missing_child_default'],
    'helper current composite missing' => [$currentDefaults210, '2.missing_child_defaults.0', 'fallback_term'],
    'helper next phase' => [$nextDefaults210, '0.phase', 'next'],
    'helper next repaired' => [$nextDefaults210, '2.status', 'set_default_child_defaults_present'],
];

$tests = [];
foreach ($cases210 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey set default child defaults current source next210 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt210): void {
        $t->same($expected, $valueAt210($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey set default child defaults current source next210 paginates appended rows'] = static function (TestRunner $t) use ($page210): void {
    $first = $page210(0, 28);
    $second = $page210(28, 3, $first['next']);
    $third = $page210(31, 3, $second['next']);

    $t->same(28, $first['count']);
    $t->same('foreign_key_set_default_child_default', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 28], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('missing_child_default', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey set default child defaults current source next210 rejects stale cursor'] = static function (TestRunner $t) use ($page210, $blockedNextRecords210): void {
    $first = $page210(0, 28);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page210(28, 3, $first['next'], $blockedNextRecords210));
};

$tests['pragma index xinfo foreignkey set default child defaults current source next210 rejects stale offset'] = static function (TestRunner $t) use ($page210): void {
    $first = $page210(0, 28);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page210(29, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey set default child defaults current source next210 ignores non set default actions'] = static function (TestRunner $t) use ($record210): void {
    $records = [
        $record210('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record210('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(id) ON DELETE CASCADE ON UPDATE RESTRICT)', 2),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::setDefaultChildRows210($records));
};

$tests['pragma index xinfo foreignkey set default child defaults current source next210 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::setDefaultChildRows210([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey set default child defaults current source next210 rejects invalid bounds'] = static function (TestRunner $t) use ($page210): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page210(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page210(0, 0));
};

return $tests;
