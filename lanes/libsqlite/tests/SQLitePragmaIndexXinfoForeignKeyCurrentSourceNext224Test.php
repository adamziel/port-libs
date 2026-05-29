<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record224 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords224 = [
    $record224('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(site_id INTEGER NOT NULL, domain TEXT COLLATE NOCASE NOT NULL, path TEXT COLLATE RTRIM NOT NULL)', 1),
    $record224('index', 'wp_sites_domain_binary_unique', 'wp_sites', 3, 'CREATE UNIQUE INDEX wp_sites_domain_binary_unique ON wp_sites(domain COLLATE BINARY)', 2),
    $record224('index', 'wp_sites_site_domain_binary_unique', 'wp_sites', 4, 'CREATE UNIQUE INDEX wp_sites_site_domain_binary_unique ON wp_sites(site_id, domain COLLATE BINARY)', 3),
    $record224('index', 'wp_sites_path_binary_unique', 'wp_sites', 5, 'CREATE UNIQUE INDEX wp_sites_path_binary_unique ON wp_sites(path)', 4),
    $record224('table', 'wp_blog_options', 'wp_blog_options', 6, "CREATE TABLE wp_blog_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        domain TEXT NOT NULL,
        path TEXT NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(domain) REFERENCES wp_sites(domain) ON UPDATE CASCADE,
        FOREIGN KEY(site_id, domain) REFERENCES wp_sites(site_id, domain) ON DELETE CASCADE,
        FOREIGN KEY(path) REFERENCES wp_sites(path) ON DELETE RESTRICT
    )", 5),
];

$nextRecords224 = [
    $currentRecords224[0],
    $currentRecords224[1],
    $currentRecords224[2],
    $currentRecords224[3],
    $record224('index', 'wp_sites_domain_nocase_unique', 'wp_sites', 7, 'CREATE UNIQUE INDEX wp_sites_domain_nocase_unique ON wp_sites(domain COLLATE NOCASE)', 6),
    $record224('index', 'wp_sites_site_domain_nocase_unique', 'wp_sites', 8, 'CREATE UNIQUE INDEX wp_sites_site_domain_nocase_unique ON wp_sites(site_id, domain COLLATE NOCASE)', 7),
    $record224('index', 'wp_sites_path_rtrim_unique', 'wp_sites', 9, 'CREATE UNIQUE INDEX wp_sites_path_rtrim_unique ON wp_sites(path COLLATE RTRIM)', 8),
    $currentRecords224[4],
];

$missingNextRecords224 = [
    $currentRecords224[0],
    $record224('index', 'wp_sites_site_unique', 'wp_sites', 10, 'CREATE UNIQUE INDEX wp_sites_site_unique ON wp_sites(site_id)', 6),
    $currentRecords224[4],
];

$page224 = static fn (
    int $offset = 0,
    int $limit = 110,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page224(
    $currentRecords224,
    $nextRecords ?? $nextRecords224,
    'PRAGMA main.index_xinfo(wp_sites_site_domain_binary_unique)',
    'PRAGMA main.foreign_key_list(wp_blog_options)',
    $offset,
    $limit,
    $resume,
);

$valueAt224 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default224 = static fn (): array => $page224();
$blocked224 = static fn (): array => $page224(nextRecords: $missingNextRecords224);
$currentCollation224 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows224($currentRecords224);
$nextCollation224 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows224($nextRecords224, 'next');

$cases224 = [
    'status ok' => [$default224, 'status', 'ok'],
    'operation marker' => [$default224, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next224'],
    'source id length' => [static fn (): array => ['len' => strlen($page224()['source_id'])], 'len', 64],
    'offset default' => [$default224, 'offset', 0],
    'limit default' => [$default224, 'limit', 110],
    'dependency appended' => [$default224, 'dependencies.8', 'sqlite-pragma-foreign-key-parent-collation-match'],
    'base prefix retained' => [$default224, 'current.foreign_key_parent_key_prefix.rows', 4],
    'collation source current' => [$default224, 'current_source.foreign_key_parent_key_collation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collations'],
    'collation source next' => [$default224, 'next_source.foreign_key_parent_key_collation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_collations'],
    'current rows' => [$default224, 'current.foreign_key_parent_key_collation.rows', 4],
    'current ok site id' => [$default224, 'current.foreign_key_parent_key_collation.ok', 0],
    'current blocked rows' => [$default224, 'current.foreign_key_parent_key_collation.blocked', 4],
    'current collation mismatch rows' => [$default224, 'current.foreign_key_parent_key_collation.parent_key_collation_mismatch', 4],
    'current missing zero' => [$default224, 'current.foreign_key_parent_key_collation.missing_parent_unique_index', 0],
    'current mismatch columns count' => [$default224, 'current.foreign_key_parent_key_collation.mismatch_columns', 4],
    'next rows' => [$default224, 'next_counts.foreign_key_parent_key_collation.rows', 4],
    'next ok rows' => [$default224, 'next_counts.foreign_key_parent_key_collation.ok', 4],
    'next blockers clear' => [$default224, 'next_counts.foreign_key_parent_key_collation.blocked', 0],
    'next mismatch zero' => [$default224, 'next_counts.foreign_key_parent_key_collation.parent_key_collation_mismatch', 0],
    'delta rows unchanged' => [$default224, 'delta.foreign_key_parent_key_collation_rows', 0],
    'delta blockers negative' => [$default224, 'delta.foreign_key_parent_key_collation_blockers', -4],
    'delta repaired true' => [$default224, 'delta.foreign_key_parent_key_collation_repaired', true],
    'delta changed true' => [$default224, 'delta.foreign_key_parent_key_collation_changed', true],
    'total includes collation rows' => [$default224, 'total', 50],
    'count complete' => [$default224, 'count', 50],
    'next complete null' => [$default224, 'next', null],
    'current summary domain mismatch' => [$default224, 'current_source.foreign_key_parent_key_collation.0', 'current:wp_blog_options#0.0:domain->wp_sites.domain:wp_sites_domain_binary_unique:NOCASE!=BINARY:mismatch=domain:parent_key_collation_mismatch'],
    'current summary site id ok' => [$default224, 'current_source.foreign_key_parent_key_collation.1', 'current:wp_blog_options#1.0:site_id->wp_sites.site_id:wp_sites_site_domain_binary_unique:BINARY!=BINARY:mismatch=domain:parent_key_collation_mismatch'],
    'next summary repaired domain' => [$default224, 'next_source.foreign_key_parent_key_collation.0', 'next:wp_blog_options#0.0:domain->wp_sites.domain:wp_sites_domain_nocase_unique:NOCASE!=NOCASE:mismatch=:ok'],
    'first collation row kind' => [$default224, 'rows.42.kind', 'foreign_key_parent_key_collation'],
    'first collation row status' => [$default224, 'rows.42.status', 'parent_key_collation_mismatch'],
    'first parent collation' => [$default224, 'rows.42.parent_column_collation', 'NOCASE'],
    'first index collation' => [$default224, 'rows.42.index_column_collation', 'BINARY'],
    'first mismatch column' => [$default224, 'rows.42.mismatch_columns.0', 'domain'],
    'composite first status' => [$default224, 'rows.43.status', 'parent_key_collation_mismatch'],
    'composite first matching site id' => [$default224, 'rows.43.collation_matches', true],
    'composite second mismatch' => [$default224, 'rows.44.collation_matches', false],
    'path rtrim mismatch' => [$default224, 'rows.45.parent_column_collation', 'RTRIM'],
    'next domain repaired index' => [$default224, 'rows.46.parent_unique_index', 'wp_sites_domain_nocase_unique'],
    'next composite repaired index' => [$default224, 'rows.47.parent_unique_index', 'wp_sites_site_domain_nocase_unique'],
    'next path repaired index' => [$default224, 'rows.49.parent_unique_index', 'wp_sites_path_rtrim_unique'],
    'blocked missing rows' => [$blocked224, 'next_counts.foreign_key_parent_key_collation.missing_parent_unique_index', 4],
    'blocked mismatch zero' => [$blocked224, 'next_counts.foreign_key_parent_key_collation.parent_key_collation_mismatch', 0],
    'blocked repaired false' => [$blocked224, 'delta.foreign_key_parent_key_collation_repaired', false],
    'helper current first kind' => [$currentCollation224, '0.kind', 'foreign_key_parent_key_collation'],
    'helper current first status' => [$currentCollation224, '0.status', 'parent_key_collation_mismatch'],
    'helper current first index' => [$currentCollation224, '0.parent_unique_index', 'wp_sites_domain_binary_unique'],
    'helper current first match false' => [$currentCollation224, '0.collation_matches', false],
    'helper current composite first match true' => [$currentCollation224, '1.collation_matches', true],
    'helper current composite second column' => [$currentCollation224, '2.to', 'domain'],
    'helper current path collation' => [$currentCollation224, '3.parent_column_collation', 'RTRIM'],
    'helper next first phase' => [$nextCollation224, '0.phase', 'next'],
    'helper next first ok' => [$nextCollation224, '0.status', 'ok'],
    'helper next path actual rtrim' => [$nextCollation224, '3.index_column_collation', 'RTRIM'],
];

$tests = [];
foreach ($cases224 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent collation current source next224 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt224): void {
        $t->same($expected, $valueAt224($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent collation current source next224 paginates collation rows'] = static function (TestRunner $t) use ($page224): void {
    $first = $page224(0, 42);
    $second = $page224(42, 4, $first['next']);
    $third = $page224(46, 4, $second['next']);

    $t->same(42, $first['count']);
    $t->same('foreign_key_parent_key_collation', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 42], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('parent_key_collation_mismatch', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][3]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent collation current source next224 accepts quoted column collation'] = static function (TestRunner $t) use ($record224): void {
    $records = [
        $record224('table', 'parent', 'parent', 2, 'CREATE TABLE parent("Option Key" TEXT COLLATE "NOCASE")', 1),
        $record224('index', 'parent_option_key_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_option_key_unique ON parent("Option Key" COLLATE NOCASE)', 2),
        $record224('table', 'child', 'child', 4, 'CREATE TABLE child(option_key TEXT, FOREIGN KEY(option_key) REFERENCES parent("Option Key"))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows224($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('NOCASE', $rows[0]['parent_column_collation']);
    $t->same('NOCASE', $rows[0]['index_column_collation']);
};

$tests['pragma index xinfo foreignkey parent collation current source next224 reports missing unique index separately'] = static function (TestRunner $t) use ($record224): void {
    $records = [
        $record224('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT COLLATE NOCASE)', 1),
        $record224('table', 'child', 'child', 3, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows224($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(['code'], $rows[0]['mismatch_columns']);
};

$tests['pragma index xinfo foreignkey parent collation current source next224 rejects stale cursor'] = static function (TestRunner $t) use ($page224, $missingNextRecords224): void {
    $first = $page224(0, 42);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page224(42, 4, $first['next'], $missingNextRecords224));
};

$tests['pragma index xinfo foreignkey parent collation current source next224 rejects stale offset'] = static function (TestRunner $t) use ($page224): void {
    $first = $page224(0, 42);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page224(43, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey parent collation current source next224 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows224([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent collation current source next224 rejects invalid bounds'] = static function (TestRunner $t) use ($page224): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page224(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page224(0, 0));
};

return $tests;
