<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record217 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords217 = [
    $record217('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER NOT NULL, domain TEXT COLLATE NOCASE NOT NULL, deleted INTEGER DEFAULT 0)', 1),
    $record217('index', 'wp_sites_site_domain_unique', 'wp_sites', 3, 'CREATE UNIQUE INDEX wp_sites_site_domain_unique ON wp_sites(site_id, domain)', 2),
    $record217('index', 'wp_sites_domain_partial_unique', 'wp_sites', 4, 'CREATE UNIQUE INDEX wp_sites_domain_partial_unique ON wp_sites(domain) WHERE deleted = 0', 3),
    $record217('table', 'wp_blog_options', 'wp_blog_options', 5, "CREATE TABLE wp_blog_options(
        option_id INTEGER PRIMARY KEY,
        domain TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(domain) REFERENCES wp_sites(domain) ON UPDATE CASCADE,
        FOREIGN KEY(site_id, domain) REFERENCES wp_sites(site_id, domain) ON DELETE CASCADE
    )", 4),
    $record217('index', 'wp_blog_options_lookup', 'wp_blog_options', 6, 'CREATE INDEX wp_blog_options_lookup ON wp_blog_options(domain, option_name)', 5),
];

$nextRecords217 = [
    $currentRecords217[0],
    $currentRecords217[1],
    $currentRecords217[2],
    $record217('index', 'wp_sites_domain_unique', 'wp_sites', 7, 'CREATE UNIQUE INDEX wp_sites_domain_unique ON wp_sites(domain)', 6),
    $currentRecords217[3],
    $currentRecords217[4],
];

$missingNextRecords217 = [
    $currentRecords217[0],
    $record217('index', 'wp_sites_deleted_unique', 'wp_sites', 8, 'CREATE UNIQUE INDEX wp_sites_deleted_unique ON wp_sites(deleted)', 6),
    $currentRecords217[3],
    $currentRecords217[4],
];

$page217 = static fn (
    int $offset = 0,
    int $limit = 90,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page217(
    $currentRecords217,
    $nextRecords ?? $nextRecords217,
    'PRAGMA main.index_xinfo(wp_sites_site_domain_unique)',
    'PRAGMA main.foreign_key_list(wp_blog_options)',
    $offset,
    $limit,
    $resume,
);

$valueAt217 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default217 = static fn (): array => $page217();
$blocked217 = static fn (): array => $page217(nextRecords: $missingNextRecords217);
$currentPrefix217 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyPrefixRows217($currentRecords217);
$nextPrefix217 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyPrefixRows217($nextRecords217, 'next');

$cases217 = [
    'status ok' => [$default217, 'status', 'ok'],
    'operation marker' => [$default217, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next217'],
    'source id length' => [static fn (): array => ['len' => strlen($page217()['source_id'])], 'len', 64],
    'offset default' => [$default217, 'offset', 0],
    'limit default' => [$default217, 'limit', 90],
    'dependency appended' => [$default217, 'dependencies.7', 'sqlite-pragma-foreign-key-parent-unique-prefix'],
    'base action lookup retained' => [$default217, 'current.foreign_key_child_action_lookup.rows', 3],
    'prefix source current' => [$default217, 'current_source.foreign_key_parent_key_prefix_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_prefix'],
    'prefix source next' => [$default217, 'next_source.foreign_key_parent_key_prefix_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_prefix'],
    'current prefix rows' => [$default217, 'current.foreign_key_parent_key_prefix.rows', 3],
    'current ok composite rows' => [$default217, 'current.foreign_key_parent_key_prefix.ok', 2],
    'current blocked suffix row' => [$default217, 'current.foreign_key_parent_key_prefix.blocked', 1],
    'current suffix blockers' => [$default217, 'current.foreign_key_parent_key_prefix.suffix_parent_unique_index', 1],
    'current partial blockers zero' => [$default217, 'current.foreign_key_parent_key_prefix.partial_parent_unique_index', 0],
    'current missing blockers zero' => [$default217, 'current.foreign_key_parent_key_prefix.missing_parent_unique_index', 0],
    'current matched suffix columns' => [$default217, 'current.foreign_key_parent_key_prefix.matched_suffix_columns', 1],
    'current covered columns' => [$default217, 'current.foreign_key_parent_key_prefix.covered_parent_columns', 5],
    'next prefix rows' => [$default217, 'next_counts.foreign_key_parent_key_prefix.rows', 3],
    'next ok rows' => [$default217, 'next_counts.foreign_key_parent_key_prefix.ok', 3],
    'next blockers cleared' => [$default217, 'next_counts.foreign_key_parent_key_prefix.blocked', 0],
    'next suffix cleared' => [$default217, 'next_counts.foreign_key_parent_key_prefix.suffix_parent_unique_index', 0],
    'delta rows unchanged' => [$default217, 'delta.foreign_key_parent_key_prefix_rows', 0],
    'delta blockers negative' => [$default217, 'delta.foreign_key_parent_key_prefix_blockers', -1],
    'delta repaired true' => [$default217, 'delta.foreign_key_parent_key_prefix_repaired', true],
    'delta changed true' => [$default217, 'delta.foreign_key_parent_key_prefix_changed', true],
    'total includes prefix rows' => [$default217, 'total', 32],
    'count complete' => [$default217, 'count', 32],
    'next complete null' => [$default217, 'next', null],
    'current suffix summary' => [$default217, 'current_source.foreign_key_parent_key_prefix.0', 'current:wp_blog_options#0.0:domain->wp_sites.domain:parent=domain:missing-full:wp_sites_site_domain_unique:wp_sites_domain_partial_unique:offset=1:suffix_parent_unique_index'],
    'current composite first summary' => [$default217, 'current_source.foreign_key_parent_key_prefix.1', 'current:wp_blog_options#1.0:site_id->wp_sites.site_id:parent=site_id,domain:wp_sites_site_domain_unique:missing-suffix:missing-partial:offset=0:ok'],
    'next domain repaired summary' => [$default217, 'next_source.foreign_key_parent_key_prefix.0', 'next:wp_blog_options#0.0:domain->wp_sites.domain:parent=domain:wp_sites_domain_unique:wp_sites_site_domain_unique:wp_sites_domain_partial_unique:offset=0:ok'],
    'first appended row kind' => [$default217, 'rows.26.kind', 'foreign_key_parent_key_prefix'],
    'first appended row status suffix' => [$default217, 'rows.26.status', 'suffix_parent_unique_index'],
    'first appended matched index' => [$default217, 'rows.26.matched_index', 'wp_sites_site_domain_unique'],
    'first appended offset' => [$default217, 'rows.26.matched_index_offset', 1],
    'first appended seqno' => [$default217, 'rows.26.matched_index_seqno', 1],
    'first appended column' => [$default217, 'rows.26.matched_index_column', 'domain'],
    'composite first ok index' => [$default217, 'rows.27.parent_unique_index', 'wp_sites_site_domain_unique'],
    'composite first offset' => [$default217, 'rows.27.matched_index_offset', 0],
    'composite second seq' => [$default217, 'rows.28.seq', 1],
    'next first repaired index' => [$default217, 'rows.29.parent_unique_index', 'wp_sites_domain_unique'],
    'blocked next missing rows' => [$blocked217, 'next_counts.foreign_key_parent_key_prefix.missing_parent_unique_index', 3],
    'blocked next suffix zero' => [$blocked217, 'next_counts.foreign_key_parent_key_prefix.suffix_parent_unique_index', 0],
    'blocked repaired false' => [$blocked217, 'delta.foreign_key_parent_key_prefix_repaired', false],
    'helper current first kind' => [$currentPrefix217, '0.kind', 'foreign_key_parent_key_prefix'],
    'helper current first status' => [$currentPrefix217, '0.status', 'suffix_parent_unique_index'],
    'helper current first suffix index' => [$currentPrefix217, '0.suffix_unique_index', 'wp_sites_site_domain_unique'],
    'helper current first partial candidate' => [$currentPrefix217, '0.partial_unique_index', 'wp_sites_domain_partial_unique'],
    'helper current composite first ok' => [$currentPrefix217, '1.status', 'ok'],
    'helper current composite second to' => [$currentPrefix217, '2.to', 'domain'],
    'helper next first phase' => [$nextPrefix217, '0.phase', 'next'],
    'helper next first ok' => [$nextPrefix217, '0.status', 'ok'],
    'helper next first full index' => [$nextPrefix217, '0.parent_unique_index', 'wp_sites_domain_unique'],
    'helper next composite full index' => [$nextPrefix217, '1.parent_unique_index', 'wp_sites_site_domain_unique'],
];

$tests = [];
foreach ($cases217 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent key prefix current source next217 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt217): void {
        $t->same($expected, $valueAt217($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent key prefix current source next217 paginates parent rows'] = static function (TestRunner $t) use ($page217): void {
    $first = $page217(0, 26);
    $second = $page217(26, 3, $first['next']);
    $third = $page217(29, 3, $second['next']);

    $t->same(26, $first['count']);
    $t->same('foreign_key_parent_key_prefix', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 26], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('suffix_parent_unique_index', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][2]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent key prefix current source next217 accepts explicit parent primary key'] = static function (TestRunner $t) use ($record217): void {
    $records = [
        $record217('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record217('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyPrefixRows217($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('sqlite_primary_key', $rows[0]['parent_unique_index']);
};

$tests['pragma index xinfo foreignkey parent key prefix current source next217 reports partial-only unique parent key'] = static function (TestRunner $t) use ($record217): void {
    $records = [
        $record217('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, active INTEGER)', 1),
        $record217('index', 'parent_code_partial', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_partial ON parent(code) WHERE active = 1', 2),
        $record217('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyPrefixRows217($records);
    $t->same(1, count($rows));
    $t->same('partial_parent_unique_index', $rows[0]['status']);
    $t->same('parent_code_partial', $rows[0]['partial_unique_index']);
};

$tests['pragma index xinfo foreignkey parent key prefix current source next217 rejects stale cursor'] = static function (TestRunner $t) use ($page217, $missingNextRecords217): void {
    $first = $page217(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page217(26, 3, $first['next'], $missingNextRecords217));
};

$tests['pragma index xinfo foreignkey parent key prefix current source next217 rejects stale offset'] = static function (TestRunner $t) use ($page217): void {
    $first = $page217(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page217(27, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey parent key prefix current source next217 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyPrefixRows217([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent key prefix current source next217 rejects invalid bounds'] = static function (TestRunner $t) use ($page217): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page217(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page217(0, 0));
};

return $tests;
