<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record169 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords169 = [
    $record169('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record169('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE PRIMARY KEY, autoload TEXT)', 2),
    $record169('table', 'wp_option_scope', 'wp_option_scope', 6, 'CREATE TABLE wp_option_scope(site_key TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(site_key, locale)) WITHOUT ROWID', 3),
    $record169('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, site_key TEXT, locale TEXT, fallback_name TEXT REFERENCES wp_option_names(name) DEFERRABLE INITIALLY DEFERRED, option_value TEXT, FOREIGN KEY(blog_id) REFERENCES wp_sites(blog_id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT scope_fk FOREIGN KEY(site_key, locale) REFERENCES wp_option_scope(site_key, locale) DEFERRABLE INITIALLY IMMEDIATE)', 4),
    $record169('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 8, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_option_names_1 ON wp_option_names(name COLLATE NOCASE)', 5),
    $record169('index', 'wp_option_scope_lookup', 'wp_option_scope', 9, 'CREATE UNIQUE INDEX wp_option_scope_lookup ON wp_option_scope(site_key COLLATE NOCASE, locale)', 6),
    $record169('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 7),
];
$nextRecords169 = $currentRecords169;

$currentTables169 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'autoload' => 'yes'],
    ],
    'wp_option_scope' => [
        ['site_key' => 'main', 'locale' => 'en_US'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'site_key' => 'main', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'option_value' => 'https://example.test'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => 404, 'option_name' => 'home', 'site_key' => 'main', 'locale' => 'fr_FR', 'fallback_name' => 'missing_home', 'option_value' => 'https://archive.example.test'],
    ],
];
$nextTables169 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'name' => 'missing_home', 'autoload' => 'no'],
    ],
    'wp_option_scope' => [
        ['site_key' => 'main', 'locale' => 'en_US'],
        ['site_key' => 'main', 'locale' => 'fr_FR'],
    ],
    'wp_options' => $currentTables169['wp_options'],
];

$page169 = static fn (
    int $offset = 0,
    int $limit = 169,
    ?array $cursor = null,
    ?array $nextTables = null,
    ?array $records = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog169(
    $records ?? $currentRecords169,
    $currentTables169,
    $records ?? $nextRecords169,
    $nextTables ?? $nextTables169,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt169 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default169 = static fn (): array => $page169();
$blocked169 = static fn (): array => $page169(nextTables: $currentTables169);
$deferrals169 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::deferralMap169($currentRecords169);

$cases169 = [
    'status ok after deferrable repair' => [$default169, 'status', 'ok'],
    'limit default' => [$default169, 'limit', 169],
    'total rows' => [$default169, 'total', 15],
    'count rows' => [$default169, 'count', 15],
    'complete true' => [$default169, 'complete', true],
    'next null' => [$default169, 'next', null],
    'current xinfo rows' => [$default169, 'current.index_xinfo', 3],
    'next xinfo rows' => [$default169, 'next_counts.index_xinfo', 3],
    'current admissions' => [$default169, 'current.index_admissions', 3],
    'next admissions' => [$default169, 'next_counts.index_admissions', 3],
    'current violations' => [$default169, 'current.foreign_key_violations', 3],
    'next violations clear' => [$default169, 'next_counts.foreign_key_violations', 0],
    'delta cleared' => [$default169, 'delta.cleared', true],
    'delta blockers' => [$default169, 'delta.total_blockers', -3],
    'next ready' => [$default169, 'next_state.ready', true],
    'deferral source current' => [$default169, 'current_source.deferral_source', 'sqlite_schema_foreign_key_clause'],
    'deferral source next' => [$default169, 'next_source.deferral_source', 'sqlite_schema_foreign_key_clause'],
    'summary current immediate' => [$default169, 'current_source.deferral_summary.immediate', 1],
    'summary current deferrable immediate' => [$default169, 'current_source.deferral_summary.deferrable_immediate', 1],
    'summary current deferrable deferred' => [$default169, 'current_source.deferral_summary.deferrable_deferred', 1],
    'summary next immediate' => [$default169, 'next_source.deferral_summary.immediate', 1],
    'summary next deferrable immediate' => [$default169, 'next_source.deferral_summary.deferrable_immediate', 1],
    'summary next deferrable deferred' => [$default169, 'next_source.deferral_summary.deferrable_deferred', 1],
    'row3 deferred inline admission deferrable true' => [$default169, 'rows.3.deferrable', true],
    'row3 deferred inline admission initially true' => [$default169, 'rows.3.initially_deferred', true],
    'row3 deferred inline until commit' => [$default169, 'rows.3.deferred_until_commit', true],
    'row3 deferred inline summary' => [$default169, 'rows.3.deferral_summary', 'deferrable_deferred'],
    'row4 immediate admission deferrable false' => [$default169, 'rows.4.deferrable', false],
    'row4 immediate admission initially false' => [$default169, 'rows.4.initially_deferred', false],
    'row4 immediate admission summary' => [$default169, 'rows.4.deferral_summary', 'immediate'],
    'row5 table constraint admission deferrable true' => [$default169, 'rows.5.deferrable', true],
    'row5 table constraint initially false' => [$default169, 'rows.5.initially_deferred', false],
    'row5 table constraint until commit false' => [$default169, 'rows.5.deferred_until_commit', false],
    'row5 table constraint summary' => [$default169, 'rows.5.deferral_summary', 'deferrable_immediate'],
    'row6 violation deferred' => [$default169, 'rows.6.deferral_summary', 'deferrable_deferred'],
    'row7 violation immediate' => [$default169, 'rows.7.deferral_summary', 'immediate'],
    'row8 violation deferrable immediate' => [$default169, 'rows.8.deferral_summary', 'deferrable_immediate'],
    'row9 next side' => [$default169, 'rows.9.side', 'next'],
    'row12 next deferred' => [$default169, 'rows.12.deferral_summary', 'deferrable_deferred'],
    'row13 next immediate' => [$default169, 'rows.13.deferral_summary', 'immediate'],
    'row14 next deferrable immediate' => [$default169, 'rows.14.deferral_summary', 'deferrable_immediate'],
    'blocked status' => [$blocked169, 'status', 'blocked'],
    'blocked next ready false' => [$blocked169, 'next_state.ready', false],
    'blocked next blockers' => [$blocked169, 'next_state.blocking', ['foreign_key_check']],
    'blocked next violations' => [$blocked169, 'next_counts.foreign_key_violations', 3],
    'deferral map deferred deferrable' => [$deferrals169, 'wp_options#0.deferrable', true],
    'deferral map deferred initially' => [$deferrals169, 'wp_options#0.initially_deferred', true],
    'deferral map immediate' => [$deferrals169, 'wp_options#1.deferrable', false],
    'deferral map table constraint deferrable' => [$deferrals169, 'wp_options#2.deferrable', true],
    'deferral map table constraint initially' => [$deferrals169, 'wp_options#2.initially_deferred', false],
];

$tests = [];
foreach ($cases169 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey deferral current source next169 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt169): void {
        $t->same($expected, $valueAt169($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey deferral current source next169 paginates deferral rows'] = static function (TestRunner $t) use ($page169): void {
    $first = $page169(0, 6);
    $second = $page169(6, 6, $first['next']);
    $third = $page169(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('deferrable_deferred', $second['rows'][0]['deferral_summary']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('deferrable_deferred', $third['rows'][0]['deferral_summary']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey deferral current source next169 table-valued index xinfo keeps deferrals'] = static function (TestRunner $t) use ($page169): void {
    $result = $page169(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same('deferrable_deferred', $result['rows'][3]['deferral_summary']);
};

$tests['pragma index xinfo foreignkey deferral current source next169 rejects stale cursor'] = static function (TestRunner $t) use ($page169, $currentTables169): void {
    $first = $page169(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page169(6, 6, $first['next'], nextTables: $currentTables169));
};

$tests['pragma index xinfo foreignkey deferral current source next169 parses unqualified deferrable as immediate'] = static function (TestRunner $t) use ($record169): void {
    $records = [
        $record169('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record169('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent DEFERRABLE)', 2),
    ];

    $map = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::deferralMap169($records);

    $t->same(true, $map['child#0']['deferrable']);
    $t->same(false, $map['child#0']['initially_deferred']);
};

$tests['pragma index xinfo foreignkey deferral current source next169 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::deferralMap169([['not' => 'a record']]));
};

return $tests;
