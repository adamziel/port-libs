<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record194 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords194 = [
    $record194('table', 'wp_option_locale', 'wp_option_locale', 4, 'CREATE TABLE wp_option_locale(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, label TEXT, UNIQUE(slug, locale))', 1),
    $record194('table', 'wp_blogs', 'wp_blogs', 5, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 2),
    $record194('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, blog_id INTEGER, autoload TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_option_locale(slug, locale), FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id))', 3),
    $record194('index', 'sqlite_autoindex_wp_option_locale_1', 'wp_option_locale', 7, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_option_locale_1 ON wp_option_locale(slug COLLATE NOCASE, locale COLLATE RTRIM)', 4),
    $record194('index', 'wp_options_autoload_fk_partial', 'wp_options', 8, "CREATE INDEX wp_options_autoload_fk_partial ON wp_options(slug, locale, blog_id, option_id) WHERE autoload = 'yes'", 5),
];
$nextRecords194 = [
    $currentRecords194[0],
    $currentRecords194[1],
    $currentRecords194[2],
    $currentRecords194[3],
    $record194('index', 'wp_options_fk_full', 'wp_options', 9, 'CREATE INDEX wp_options_fk_full ON wp_options(slug, locale, blog_id, option_id)', 6),
];

$currentTables194 = [
    'wp_option_locale' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home'],
    ],
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'blog_id' => 1, 'autoload' => 'yes', 'option_value' => 'https://example.test'],
        ['rowid' => 2, 'option_id' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'blog_id' => 404, 'autoload' => 'no', 'option_value' => '1'],
    ],
];
$nextTables194 = [
    'wp_option_locale' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home'],
        ['rowid' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'label' => 'Dashboard'],
    ],
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_options' => $currentTables194['wp_options'],
];

$page194 = static fn (
    int $offset = 0,
    int $limit = 194,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_autoload_fk_partial)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog194(
    $currentRecords194,
    $currentTables194,
    $nextRecords ?? $nextRecords194,
    $nextTables ?? $nextTables194,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt194 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$default194 = static fn (): array => $page194();
$blocked194 = static fn (): array => $page194(nextRecords: $currentRecords194, nextTables: $currentTables194);
$partialRows194 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialChildIndexRows194($currentRecords194);
$nextPartialRows194 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialChildIndexRows194($nextRecords194, 'next');
$tableValued194 = static fn (): array => $page194(indexSql: "pragma_index_xinfo('wp_options_autoload_fk_partial')", tableValued: true);

$cases194 = [
    'status ok with partial child index diagnostic' => [$default194, 'status', 'ok'],
    'limit default' => [$default194, 'limit', 194],
    'total rows include partial child diagnostics' => [$default194, 'total', 37],
    'count rows include partial child diagnostics' => [$default194, 'count', 37],
    'complete true' => [$default194, 'complete', true],
    'next null' => [$default194, 'next', null],
    'source partial child current' => [$default194, 'current_source.foreign_key_partial_child_index_source', 'pragma_index_list_partial_child_indexes_and_pragma_index_xinfo_prefixes'],
    'source partial child next' => [$default194, 'next_source.foreign_key_partial_child_index_source', 'pragma_index_list_partial_child_indexes_and_pragma_index_xinfo_prefixes'],
    'current partial child rows' => [$default194, 'current.foreign_key_partial_child_index_rows', 2],
    'next partial child rows cleared' => [$default194, 'next_counts.foreign_key_partial_child_index_rows', 0],
    'current partial count rows' => [$default194, 'current.foreign_key_partial_child_indexes.rows', 2],
    'current partial diagnostic count' => [$default194, 'current.foreign_key_partial_child_indexes.partial_child_index', 2],
    'current partial unique zero' => [$default194, 'current.foreign_key_partial_child_indexes.unique', 0],
    'current partial nonunique rows' => [$default194, 'current.foreign_key_partial_child_indexes.nonunique', 2],
    'current partial extra key columns' => [$default194, 'current.foreign_key_partial_child_indexes.extra_key_columns', 4],
    'current partial auxiliary columns' => [$default194, 'current.foreign_key_partial_child_indexes.auxiliary_columns_ignored', 2],
    'next partial count zero' => [$default194, 'next_counts.foreign_key_partial_child_indexes.partial_child_index', 0],
    'next extra key zero' => [$default194, 'next_counts.foreign_key_partial_child_indexes.extra_key_columns', 0],
    'delta partial rows negative' => [$default194, 'delta.foreign_key_partial_child_index_rows', -2],
    'delta partial changed true' => [$default194, 'delta.foreign_key_partial_child_index_changed', true],
    'delta partial cleared true' => [$default194, 'delta.foreign_key_partial_child_index_cleared', true],
    'delta diagnostic true' => [$default194, 'delta.foreign_key_partial_child_index_diagnostic_only', true],
    'delta cleared inherited true' => [$default194, 'delta.cleared', true],
    'next ready true' => [$default194, 'next_state.ready', true],
    'partial row first kind' => [$default194, 'rows.35.kind', 'foreign_key_partial_child_index'],
    'partial row first status' => [$default194, 'rows.35.status', 'diagnostic_only'],
    'partial row first index' => [$default194, 'rows.35.index', 'wp_options_autoload_fk_partial'],
    'partial row first column' => [$default194, 'rows.35.from', 'slug'],
    'partial row first covered' => [$default194, 'rows.35.covered_prefix_columns', 2],
    'partial row first extra' => [$default194, 'rows.35.extra_key_columns', 2],
    'partial row first auxiliary' => [$default194, 'rows.35.auxiliary_columns_ignored', 1],
    'partial row second column' => [$default194, 'rows.36.from', 'locale'],
    'blocked status stays blocked by data' => [$blocked194, 'status', 'blocked'],
    'blocked next partial rows retained' => [$blocked194, 'next_counts.foreign_key_partial_child_index_rows', 2],
    'blocked partial changed false' => [$blocked194, 'delta.foreign_key_partial_child_index_changed', false],
    'blocked diagnostic true' => [$blocked194, 'delta.foreign_key_partial_child_index_diagnostic_only', true],
    'helper first kind' => [$partialRows194, '0.kind', 'foreign_key_partial_child_index'],
    'helper first index' => [$partialRows194, '0.index', 'wp_options_autoload_fk_partial'],
    'helper first status' => [$partialRows194, '0.status', 'diagnostic_only'],
    'helper second column' => [$partialRows194, '1.from', 'locale'],
    'helper next rows empty' => [static fn (): array => ['count' => count($nextPartialRows194())], 'count', 0],
    'table valued flag preserved' => [$tableValued194, 'current_source.table_valued_index_xinfo', true],
    'table valued partial rows preserved' => [$tableValued194, 'current.foreign_key_partial_child_index_rows', 2],
];

$tests = [];
foreach ($cases194 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey partial child current source next194 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt194): void {
        $t->same($expected, $valueAt194($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey partial child current source next194 paginates partial child rows'] = static function (TestRunner $t) use ($page194): void {
    $first = $page194(0, 35);
    $second = $page194(35, 1, $first['next']);
    $third = $page194(36, 1, $second['next']);

    $t->same(35, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 35], $first['next']);
    $t->same('foreign_key_partial_child_index', $second['rows'][0]['kind']);
    $t->same('slug', $second['rows'][0]['from']);
    $t->same('locale', $third['rows'][0]['from']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey partial child current source next194 source changes with full child index repair'] = static function (TestRunner $t) use ($page194, $currentRecords194): void {
    $changed = $page194();
    $same = $page194(nextRecords: $currentRecords194);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_partial_child_index_changed']);
    $t->same(false, $same['delta']['foreign_key_partial_child_index_changed']);
};

$tests['pragma index xinfo foreignkey partial child current source next194 rejects stale source cursor'] = static function (TestRunner $t) use ($page194, $currentRecords194): void {
    $first = $page194(0, 35);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page194(35, 2, $first['next'], nextRecords: $currentRecords194));
};

$tests['pragma index xinfo foreignkey partial child current source next194 rejects stale offset cursor'] = static function (TestRunner $t) use ($page194): void {
    $first = $page194(0, 35);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page194(36, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey partial child current source next194 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialChildIndexRows194([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey partial child current source next194 rejects negative offset'] = static function (TestRunner $t) use ($page194): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page194(offset: -1));
};

$tests['pragma index xinfo foreignkey partial child current source next194 rejects zero limit'] = static function (TestRunner $t) use ($page194): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page194(limit: 0));
};

return $tests;
