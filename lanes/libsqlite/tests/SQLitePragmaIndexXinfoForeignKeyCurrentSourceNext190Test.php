<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record190 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords190 = [
    $record190('table', 'wp_option_slug', 'wp_option_slug', 4, 'CREATE TABLE wp_option_slug(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, label TEXT)', 1),
    $record190('table', 'wp_option_alias', 'wp_option_alias', 5, 'CREATE TABLE wp_option_alias(alias TEXT, label TEXT)', 2),
    $record190('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, slug TEXT, locale TEXT, alias TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_option_slug(slug, locale), FOREIGN KEY(alias) REFERENCES wp_option_alias(alias))', 3),
    $record190('index', 'wp_option_slug_expr_unique', 'wp_option_slug', 7, 'CREATE UNIQUE INDEX wp_option_slug_expr_unique ON wp_option_slug(slug COLLATE NOCASE, lower(locale) COLLATE RTRIM)', 4),
    $record190('index', 'wp_option_alias_expr_unique', 'wp_option_alias', 8, 'CREATE UNIQUE INDEX wp_option_alias_expr_unique ON wp_option_alias(lower(alias))', 5),
    $record190('index', 'wp_options_fk_lookup', 'wp_options', 9, 'CREATE INDEX wp_options_fk_lookup ON wp_options(slug, locale, alias)', 6),
];
$nextRecords190 = [
    $currentRecords190[0],
    $currentRecords190[1],
    $currentRecords190[2],
    $currentRecords190[3],
    $record190('index', 'wp_option_slug_full_unique', 'wp_option_slug', 10, 'CREATE UNIQUE INDEX wp_option_slug_full_unique ON wp_option_slug(slug COLLATE NOCASE, locale COLLATE RTRIM)', 7),
    $currentRecords190[4],
    $record190('index', 'wp_option_alias_full_unique', 'wp_option_alias', 11, 'CREATE UNIQUE INDEX wp_option_alias_full_unique ON wp_option_alias(alias)', 8),
    $currentRecords190[5],
];

$currentTables190 = [
    'wp_option_slug' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home']],
    'wp_option_alias' => [['rowid' => 1, 'alias' => 'siteurl', 'label' => 'Site URL']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'alias' => 'siteurl', 'option_value' => 'https://example.test'],
        ['rowid' => 2, 'option_id' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'alias' => 'missing', 'option_value' => '1'],
    ],
];
$nextTables190 = [
    'wp_option_slug' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'label' => 'Home'],
        ['rowid' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'label' => 'Dashboard'],
    ],
    'wp_option_alias' => [
        ['rowid' => 1, 'alias' => 'siteurl', 'label' => 'Site URL'],
        ['rowid' => 2, 'alias' => 'missing', 'label' => 'Missing'],
    ],
    'wp_options' => $currentTables190['wp_options'],
];

$page190 = static fn (
    int $offset = 0,
    int $limit = 190,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_fk_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog190(
    $currentRecords190,
    $currentTables190,
    $nextRecords ?? $nextRecords190,
    $nextTables ?? $nextTables190,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt190 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$default190 = static fn (): array => $page190();
$blocked190 = static fn (): array => $page190(nextRecords: $currentRecords190, nextTables: $currentTables190);
$expressionRows190 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentIndexRows190($currentRecords190);
$nextExpressionRows190 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentIndexRows190($nextRecords190, 'next');
$tableValued190 = static fn (): array => $page190(indexSql: "pragma_index_xinfo('wp_options_fk_lookup')", tableValued: true);

$cases190 = [
    'status ok after full parent keys added' => [$default190, 'status', 'ok'],
    'limit default' => [$default190, 'limit', 190],
    'total rows include expression parent rows' => [$default190, 'total', 44],
    'count rows include expression parent rows' => [$default190, 'count', 44],
    'complete true' => [$default190, 'complete', true],
    'next null' => [$default190, 'next', null],
    'current expression source' => [$default190, 'current_source.foreign_key_expression_parent_index_source', 'pragma_index_xinfo_expression_parent_candidates'],
    'next expression source' => [$default190, 'next_source.foreign_key_expression_parent_index_source', 'pragma_index_xinfo_expression_parent_candidates'],
    'current expression rows' => [$default190, 'current.foreign_key_expression_parent_index_rows', 3],
    'next expression rows' => [$default190, 'next_counts.foreign_key_expression_parent_index_rows', 3],
    'current count rows' => [$default190, 'current.foreign_key_expression_parent_indexes.rows', 3],
    'current blockers' => [$default190, 'current.foreign_key_expression_parent_indexes.expression_parent_key', 3],
    'current shadowed zero' => [$default190, 'current.foreign_key_expression_parent_indexes.shadowed_by_full_parent_key', 0],
    'current expression term sum' => [$default190, 'current.foreign_key_expression_parent_indexes.expression_terms', 3],
    'current ordinary term sum' => [$default190, 'current.foreign_key_expression_parent_indexes.ordinary_terms', 2],
    'next blockers repaired' => [$default190, 'next_counts.foreign_key_expression_parent_indexes.expression_parent_key', 0],
    'next shadowed rows' => [$default190, 'next_counts.foreign_key_expression_parent_indexes.shadowed_by_full_parent_key', 3],
    'delta rows unchanged' => [$default190, 'delta.foreign_key_expression_parent_index_rows', 0],
    'delta blockers negative' => [$default190, 'delta.foreign_key_expression_parent_index_blockers', -3],
    'delta repaired true' => [$default190, 'delta.foreign_key_expression_parent_index_repaired', true],
    'delta changed true' => [$default190, 'delta.foreign_key_expression_parent_index_changed', true],
    'delta cleared true' => [$default190, 'delta.cleared', true],
    'next state ready' => [$default190, 'next_state.ready', true],
    'row38 first expression kind' => [$default190, 'rows.38.kind', 'foreign_key_expression_parent_index'],
    'row38 first expression status' => [$default190, 'rows.38.status', 'expression_parent_key'],
    'row38 first expression index' => [$default190, 'rows.38.index', 'wp_option_slug_expr_unique'],
    'row38 slug term ordinary' => [$default190, 'rows.38.index_name', 'slug'],
    'row38 expression terms count' => [$default190, 'rows.38.expression_terms', 1],
    'row39 locale expression cid' => [$default190, 'rows.39.index_cid', -2],
    'row39 locale expression name null' => [$default190, 'rows.39.index_name', null],
    'row40 alias expression index' => [$default190, 'rows.40.index', 'wp_option_alias_expr_unique'],
    'row40 alias expression terms' => [$default190, 'rows.40.expression_terms', 1],
    'row41 next slug shadowed' => [$default190, 'rows.41.status', 'shadowed_by_full_parent_key'],
    'row41 next full parent key' => [$default190, 'rows.41.full_parent_key', 'wp_option_slug_full_unique'],
    'row42 next locale side' => [$default190, 'rows.42.side', 'next'],
    'row43 next alias full parent key' => [$default190, 'rows.43.full_parent_key', 'wp_option_alias_full_unique'],
    'blocked status remains blocked' => [$blocked190, 'status', 'blocked'],
    'blocked next blockers remain' => [$blocked190, 'next_counts.foreign_key_expression_parent_indexes.expression_parent_key', 3],
    'blocked repaired false' => [$blocked190, 'delta.foreign_key_expression_parent_index_repaired', false],
    'helper first kind' => [$expressionRows190, '0.kind', 'foreign_key_expression_parent_index'],
    'helper first status' => [$expressionRows190, '0.status', 'expression_parent_key'],
    'helper second expression cid' => [$expressionRows190, '1.index_cid', -2],
    'helper alias expression terms' => [$expressionRows190, '2.expression_terms', 1],
    'helper next shadowed' => [$nextExpressionRows190, '0.status', 'shadowed_by_full_parent_key'],
    'helper next full parent' => [$nextExpressionRows190, '0.full_parent_key', 'wp_option_slug_full_unique'],
    'helper next alias full parent' => [$nextExpressionRows190, '2.full_parent_key', 'wp_option_alias_full_unique'],
    'table valued flag preserved' => [$tableValued190, 'current_source.table_valued_index_xinfo', true],
    'table valued expression rows preserved' => [$tableValued190, 'current.foreign_key_expression_parent_index_rows', 3],
];

$tests = [];
foreach ($cases190 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey expression parent current source next190 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt190): void {
        $t->same($expected, $valueAt190($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey expression parent current source next190 paginates expression rows'] = static function (TestRunner $t) use ($page190): void {
    $first = $page190(0, 38);
    $second = $page190(38, 3, $first['next']);
    $third = $page190(41, 3, $second['next']);

    $t->same(38, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 38], $first['next']);
    $t->same('foreign_key_expression_parent_index', $second['rows'][0]['kind']);
    $t->same('expression_parent_key', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('shadowed_by_full_parent_key', $third['rows'][2]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey expression parent current source next190 source changes with full unique repair'] = static function (TestRunner $t) use ($page190, $currentRecords190): void {
    $changed = $page190();
    $same = $page190(nextRecords: $currentRecords190);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_expression_parent_index_changed']);
    $t->same(false, $same['delta']['foreign_key_expression_parent_index_changed']);
};

$tests['pragma index xinfo foreignkey expression parent current source next190 source changes with expression index shape'] = static function (TestRunner $t) use ($page190, $nextRecords190, $record190): void {
    $changed = $nextRecords190;
    $changed[3] = $record190('index', 'wp_option_slug_expr_unique', 'wp_option_slug', 7, 'CREATE UNIQUE INDEX wp_option_slug_expr_unique ON wp_option_slug(upper(slug) COLLATE NOCASE, lower(locale) COLLATE RTRIM)', 4);

    $first = $page190();
    $second = $page190(nextRecords: $changed);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(2, $second['rows'][41]['expression_terms']);
    $t->same(0, $second['rows'][41]['ordinary_terms']);
};

$tests['pragma index xinfo foreignkey expression parent current source next190 rejects stale expression cursor'] = static function (TestRunner $t) use ($page190, $currentRecords190): void {
    $first = $page190(0, 38);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page190(38, 3, $first['next'], nextRecords: $currentRecords190));
};

$tests['pragma index xinfo foreignkey expression parent current source next190 rejects stale offset cursor'] = static function (TestRunner $t) use ($page190): void {
    $first = $page190(0, 38);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page190(39, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey expression parent current source next190 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentIndexRows190([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey expression parent current source next190 rejects negative offset'] = static function (TestRunner $t) use ($page190): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page190(offset: -1));
};

$tests['pragma index xinfo foreignkey expression parent current source next190 rejects zero limit'] = static function (TestRunner $t) use ($page190): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page190(limit: 0));
};

return $tests;
