<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record200 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords200 = [
    $record200('table', 'wp_option_locale', 'wp_option_locale', 4, 'CREATE TABLE wp_option_locale(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, label TEXT, UNIQUE(slug, locale))', 1),
    $record200('table', 'wp_blogs', 'wp_blogs', 5, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 2),
    $record200('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, blog_id INTEGER, autoload TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_option_locale(slug, locale), FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id))', 3),
    $record200('index', 'sqlite_autoindex_wp_option_locale_1', 'wp_option_locale', 7, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_option_locale_1 ON wp_option_locale(slug COLLATE NOCASE, locale COLLATE RTRIM)', 4),
    $record200('index', 'wp_options_locale_slug_fk_wrong', 'wp_options', 8, 'CREATE INDEX wp_options_locale_slug_fk_wrong ON wp_options(locale, slug, option_id)', 5),
];
$nextRecords200 = [
    $currentRecords200[0],
    $currentRecords200[1],
    $currentRecords200[2],
    $currentRecords200[3],
    $record200('index', 'wp_options_slug_locale_fk', 'wp_options', 9, 'CREATE INDEX wp_options_slug_locale_fk ON wp_options(slug, locale, option_id)', 6),
];

$currentTables200 = [
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
$nextTables200 = [
    'wp_option_locale' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home'],
        ['rowid' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'label' => 'Dashboard'],
    ],
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_options' => $currentTables200['wp_options'],
];

$page200 = static fn (
    int $offset = 0,
    int $limit = 200,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_locale_slug_fk_wrong)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog200(
    $currentRecords200,
    $currentTables200,
    $nextRecords ?? $nextRecords200,
    $nextTables ?? $nextTables200,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt200 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$default200 = static fn (): array => $page200();
$blocked200 = static fn (): array => $page200(nextRecords: $currentRecords200, nextTables: $currentTables200);
$wrongOrderRows200 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::wrongOrderChildIndexRows200($currentRecords200);
$nextWrongOrderRows200 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::wrongOrderChildIndexRows200($nextRecords200, 'next');
$tableValued200 = static fn (): array => $page200(indexSql: "pragma_index_xinfo('wp_options_locale_slug_fk_wrong')", tableValued: true);

$cases200 = [
    'status ok with wrong order child diagnostic' => [$default200, 'status', 'ok'],
    'limit default' => [$default200, 'limit', 200],
    'complete true' => [$default200, 'complete', true],
    'next null' => [$default200, 'next', null],
    'source wrong order current' => [$default200, 'current_source.foreign_key_wrong_order_child_index_source', 'pragma_index_xinfo_child_key_same_set_wrong_order'],
    'source wrong order next' => [$default200, 'next_source.foreign_key_wrong_order_child_index_source', 'pragma_index_xinfo_child_key_same_set_wrong_order'],
    'current wrong order rows' => [$default200, 'current.foreign_key_wrong_order_child_index_rows', 2],
    'next wrong order rows cleared' => [$default200, 'next_counts.foreign_key_wrong_order_child_index_rows', 0],
    'current count rows' => [$default200, 'current.foreign_key_wrong_order_child_indexes.rows', 2],
    'current diagnostic count' => [$default200, 'current.foreign_key_wrong_order_child_indexes.wrong_order_child_index', 2],
    'current unique zero' => [$default200, 'current.foreign_key_wrong_order_child_indexes.unique', 0],
    'current nonunique rows' => [$default200, 'current.foreign_key_wrong_order_child_indexes.nonunique', 2],
    'current partial zero' => [$default200, 'current.foreign_key_wrong_order_child_indexes.partial', 0],
    'current extra key columns' => [$default200, 'current.foreign_key_wrong_order_child_indexes.extra_key_columns', 2],
    'current auxiliary columns' => [$default200, 'current.foreign_key_wrong_order_child_indexes.auxiliary_columns_ignored', 2],
    'next count zero' => [$default200, 'next_counts.foreign_key_wrong_order_child_indexes.wrong_order_child_index', 0],
    'next extra key zero' => [$default200, 'next_counts.foreign_key_wrong_order_child_indexes.extra_key_columns', 0],
    'delta wrong order rows negative' => [$default200, 'delta.foreign_key_wrong_order_child_index_rows', -2],
    'delta wrong order changed true' => [$default200, 'delta.foreign_key_wrong_order_child_index_changed', true],
    'delta wrong order repaired true' => [$default200, 'delta.foreign_key_wrong_order_child_index_repaired', true],
    'delta diagnostic true' => [$default200, 'delta.foreign_key_wrong_order_child_index_diagnostic_only', true],
    'delta cleared inherited true' => [$default200, 'delta.cleared', true],
    'next ready true' => [$default200, 'next_state.ready', true],
    'helper first kind' => [$wrongOrderRows200, '0.kind', 'foreign_key_wrong_order_child_index'],
    'helper first index' => [$wrongOrderRows200, '0.index', 'wp_options_locale_slug_fk_wrong'],
    'helper first status' => [$wrongOrderRows200, '0.status', 'diagnostic_only'],
    'helper first from slug' => [$wrongOrderRows200, '0.from', 'slug'],
    'helper first expected first slug' => [$wrongOrderRows200, '0.expected_child_columns.0', 'slug'],
    'helper first actual first locale' => [$wrongOrderRows200, '0.index_key_columns.0', 'locale'],
    'helper first actual second slug' => [$wrongOrderRows200, '0.index_key_columns.1', 'slug'],
    'helper first prefix false' => [$wrongOrderRows200, '0.prefix_order_matches', false],
    'helper first matched set true' => [$wrongOrderRows200, '0.matched_column_set', true],
    'helper second from locale' => [$wrongOrderRows200, '1.from', 'locale'],
    'helper next rows empty' => [static fn (): array => ['count' => count($nextWrongOrderRows200())], 'count', 0],
    'blocked status stays blocked by data' => [$blocked200, 'status', 'blocked'],
    'blocked next wrong order rows retained' => [$blocked200, 'next_counts.foreign_key_wrong_order_child_index_rows', 2],
    'blocked wrong order changed false' => [$blocked200, 'delta.foreign_key_wrong_order_child_index_changed', false],
    'blocked diagnostic true' => [$blocked200, 'delta.foreign_key_wrong_order_child_index_diagnostic_only', true],
    'table valued flag preserved' => [$tableValued200, 'current_source.table_valued_index_xinfo', true],
    'table valued wrong order rows preserved' => [$tableValued200, 'current.foreign_key_wrong_order_child_index_rows', 2],
];

$tests = [];
foreach ($cases200 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey wrong order child current source next200 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt200): void {
        $t->same($expected, $valueAt200($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey wrong order child current source next200 keeps diagnostic rows separate from inherited FK rows'] = static function (TestRunner $t) use ($page200): void {
    $page = $page200();
    $diagnostic = array_values(array_filter($page['rows'], static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_wrong_order_child_index'));

    $t->same(2, count($diagnostic));
    $t->same('slug', $diagnostic[0]['from']);
    $t->same(['locale', 'slug', 'option_id'], $diagnostic[0]['index_key_columns']);
    $t->same('diagnostic_only', $diagnostic[0]['status']);
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 appends rows after inherited page'] = static function (TestRunner $t) use ($page200): void {
    $page = $page200();
    $tail = array_slice($page['rows'], -2);

    $t->same('foreign_key_wrong_order_child_index', $tail[0]['kind']);
    $t->same('slug', $tail[0]['from']);
    $t->same('foreign_key_wrong_order_child_index', $tail[1]['kind']);
    $t->same('locale', $tail[1]['from']);
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 paginates wrong order rows'] = static function (TestRunner $t) use ($page200): void {
    $full = $page200();
    $first = $page200(0, $full['total'] - 2);
    $second = $page200($full['total'] - 2, 1, $first['next']);
    $third = $page200($full['total'] - 1, 1, $second['next']);

    $t->same($full['total'] - 2, $first['count']);
    $t->same(['source_id' => $full['source_id'], 'offset' => $full['total'] - 2], $first['next']);
    $t->same('foreign_key_wrong_order_child_index', $second['rows'][0]['kind']);
    $t->same('slug', $second['rows'][0]['from']);
    $t->same('locale', $third['rows'][0]['from']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 source changes with ordered child index repair'] = static function (TestRunner $t) use ($page200, $currentRecords200): void {
    $changed = $page200();
    $same = $page200(nextRecords: $currentRecords200);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_wrong_order_child_index_changed']);
    $t->same(false, $same['delta']['foreign_key_wrong_order_child_index_changed']);
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 rejects stale source cursor'] = static function (TestRunner $t) use ($page200, $currentRecords200): void {
    $full = $page200();
    $first = $page200(0, $full['total'] - 1);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page200($full['total'] - 1, 2, $first['next'], nextRecords: $currentRecords200));
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 rejects stale offset cursor'] = static function (TestRunner $t) use ($page200): void {
    $full = $page200();
    $first = $page200(0, $full['total'] - 1);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page200($full['total'], 2, $first['next']));
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::wrongOrderChildIndexRows200([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 rejects negative offset'] = static function (TestRunner $t) use ($page200): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page200(offset: -1));
};

$tests['pragma index xinfo foreignkey wrong order child current source next200 rejects zero limit'] = static function (TestRunner $t) use ($page200): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page200(limit: 0));
};

return $tests;
