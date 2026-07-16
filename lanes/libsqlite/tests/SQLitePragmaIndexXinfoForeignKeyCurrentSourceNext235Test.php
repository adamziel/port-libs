<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record235 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords235 = [
    $record235('table', 'wp_taxonomy_keys', 'wp_taxonomy_keys', 2, 'CREATE TABLE wp_taxonomy_keys(slug TEXT NOT NULL, locale TEXT NOT NULL, blog_id INTEGER NOT NULL, term_id INTEGER PRIMARY KEY)', 1),
    $record235('index', 'wp_taxonomy_slug_desc_unique', 'wp_taxonomy_keys', 3, 'CREATE UNIQUE INDEX wp_taxonomy_slug_desc_unique ON wp_taxonomy_keys(slug DESC)', 2),
    $record235('index', 'wp_taxonomy_locale_blog_desc_unique', 'wp_taxonomy_keys', 4, 'CREATE UNIQUE INDEX wp_taxonomy_locale_blog_desc_unique ON wp_taxonomy_keys(locale COLLATE NOCASE DESC, blog_id ASC)', 3),
    $record235('table', 'wp_term_import_queue', 'wp_term_import_queue', 5, "CREATE TABLE wp_term_import_queue(
        queue_id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        blog_id INTEGER NOT NULL,
        term_id INTEGER NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_taxonomy_keys(slug),
        FOREIGN KEY(locale, blog_id) REFERENCES wp_taxonomy_keys(locale, blog_id),
        FOREIGN KEY(term_id) REFERENCES wp_taxonomy_keys(term_id)
    )", 4),
];

$nextRecords235 = [
    $currentRecords235[0],
    $record235('index', 'wp_taxonomy_slug_unique', 'wp_taxonomy_keys', 6, 'CREATE UNIQUE INDEX wp_taxonomy_slug_unique ON wp_taxonomy_keys(slug)', 2),
    $record235('index', 'wp_taxonomy_locale_blog_unique', 'wp_taxonomy_keys', 7, 'CREATE UNIQUE INDEX wp_taxonomy_locale_blog_unique ON wp_taxonomy_keys(locale COLLATE NOCASE, blog_id)', 3),
    $currentRecords235[3],
];

$blockedNextRecords235 = [
    $currentRecords235[0],
    $currentRecords235[1],
    $currentRecords235[2],
    $currentRecords235[3],
];

$page235 = static fn (
    int $offset = 0,
    int $limit = 160,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page235(
    $currentRecords235,
    $nextRecords ?? $nextRecords235,
    'PRAGMA main.index_xinfo(wp_taxonomy_locale_blog_desc_unique)',
    'PRAGMA main.foreign_key_list(wp_term_import_queue)',
    $offset,
    $limit,
    $resume,
);

$valueAt235 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default235 = static fn (): array => $page235();
$blocked235 = static fn (): array => $page235(nextRecords: $blockedNextRecords235);
$currentDesc235 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($currentRecords235);
$nextDesc235 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($nextRecords235, 'next');
$blockedDesc235 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($blockedNextRecords235, 'next');

$cases235 = [
    'status ok' => [$default235, 'status', 'ok'],
    'operation marker' => [$default235, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next235'],
    'source id length' => [static fn (): array => ['len' => strlen($page235()['source_id'])], 'len', 64],
    'offset default' => [$default235, 'offset', 0],
    'limit default' => [$default235, 'limit', 160],
    'dependency appended' => [$default235, 'dependencies.12', 'sqlite-pragma-foreign-key-parent-desc-unique-index'],
    'base child expression retained' => [$default235, 'current.foreign_key_child_expression_prefix.rows', 0],
    'desc source current' => [$default235, 'current_source.foreign_key_parent_desc_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_terms'],
    'desc source next' => [$default235, 'next_source.foreign_key_parent_desc_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_terms'],
    'current desc rows' => [$default235, 'current.foreign_key_parent_desc_unique.rows', 3],
    'current desc ok' => [$default235, 'current.foreign_key_parent_desc_unique.ok', 3],
    'current desc foreign keys' => [$default235, 'current.foreign_key_parent_desc_unique.foreign_keys', 2],
    'current desc parents' => [$default235, 'current.foreign_key_parent_desc_unique.parent_tables', 1],
    'current desc terms' => [$default235, 'current.foreign_key_parent_desc_unique.descending_terms', 3],
    'current single rows' => [$default235, 'current.foreign_key_parent_desc_unique.single_column', 1],
    'current composite rows' => [$default235, 'current.foreign_key_parent_desc_unique.composite', 2],
    'next desc rows cleared' => [$default235, 'next_counts.foreign_key_parent_desc_unique.rows', 0],
    'next desc terms cleared' => [$default235, 'next_counts.foreign_key_parent_desc_unique.descending_terms', 0],
    'delta rows' => [$default235, 'delta.foreign_key_parent_desc_unique_rows', -3],
    'delta terms' => [$default235, 'delta.foreign_key_parent_desc_unique_terms', -3],
    'delta changed true' => [$default235, 'delta.foreign_key_parent_desc_unique_changed', true],
    'current summary slug' => [$default235, 'current_source.foreign_key_parent_desc_unique.0', 'current:wp_term_import_queue#0.0:slug->wp_taxonomy_keys.slug:wp_taxonomy_slug_desc_unique:columns=slug:desc=1:descending=slug:ok'],
    'current summary composite first' => [$default235, 'current_source.foreign_key_parent_desc_unique.1', 'current:wp_term_import_queue#1.0:locale->wp_taxonomy_keys.locale:wp_taxonomy_locale_blog_desc_unique:columns=locale,blog_id:desc=1,0:descending=locale:ok'],
    'current summary composite second' => [$default235, 'current_source.foreign_key_parent_desc_unique.2', 'current:wp_term_import_queue#1.1:blog_id->wp_taxonomy_keys.blog_id:wp_taxonomy_locale_blog_desc_unique:columns=locale,blog_id:desc=1,0:descending=locale:ok'],
    'next summary empty' => [$default235, 'next_source.foreign_key_parent_desc_unique', []],
    'blocked next rows remain' => [$blocked235, 'next_counts.foreign_key_parent_desc_unique.rows', 3],
    'blocked next terms remain' => [$blocked235, 'next_counts.foreign_key_parent_desc_unique.descending_terms', 3],
    'blocked delta rows zero' => [$blocked235, 'delta.foreign_key_parent_desc_unique_rows', 0],
    'blocked changed false' => [$blocked235, 'delta.foreign_key_parent_desc_unique_changed', false],
    'helper current first kind' => [$currentDesc235, '0.kind', 'foreign_key_parent_desc_unique'],
    'helper current first index' => [$currentDesc235, '0.parent_unique_index', 'wp_taxonomy_slug_desc_unique'],
    'helper current first desc true' => [$currentDesc235, '0.index_column_desc', true],
    'helper current first desc column' => [$currentDesc235, '0.descending_key_columns.0', 'slug'],
    'helper composite first collated column' => [$currentDesc235, '1.index_column', 'locale'],
    'helper composite first desc true' => [$currentDesc235, '1.index_column_desc', true],
    'helper composite second desc false' => [$currentDesc235, '2.index_column_desc', false],
    'helper next repaired empty' => [static fn (): array => ['count' => count($nextDesc235())], 'count', 0],
    'helper blocked rows count' => [static fn (): array => ['count' => count($blockedDesc235())], 'count', 3],
    'helper blocked phase next' => [$blockedDesc235, '0.phase', 'next'],
];

$tests = [];
foreach ($cases235 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey desc parent unique current source next235 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt235): void {
        $t->same($expected, $valueAt235($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey desc parent unique current source next235 paginates desc rows'] = static function (TestRunner $t) use ($page235): void {
    $full = $page235();
    $descOffset = $full['total'] - 3;
    $first = $page235(0, $descOffset);
    $second = $page235($descOffset, 2, $first['next']);
    $third = $page235($descOffset + 2, 1, $second['next']);

    $t->same($descOffset, $first['count']);
    $t->same('foreign_key_parent_desc_unique', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $descOffset], $first['next']);
    $t->same('slug', $second['rows'][0]['from']);
    $t->same(true, $second['rows'][0]['index_column_desc']);
    $t->same('locale', $second['rows'][1]['from']);
    $t->same('blog_id', $third['rows'][0]['from']);
    $t->same(false, $third['rows'][0]['index_column_desc']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 ignores descending nonunique parent index'] = static function (TestRunner $t) use ($record235): void {
    $records = [
        $record235('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record235('index', 'parent_code_desc', 'parent', 3, 'CREATE INDEX parent_code_desc ON parent(code DESC)', 2),
        $record235('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($records));
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 ignores partial descending unique parent index'] = static function (TestRunner $t) use ($record235): void {
    $records = [
        $record235('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, active INTEGER)', 1),
        $record235('index', 'parent_code_desc_partial', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_desc_partial ON parent(code DESC) WHERE active = 1', 2),
        $record235('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($records));
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 ignores expression descending parent index'] = static function (TestRunner $t) use ($record235): void {
    $records = [
        $record235('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record235('index', 'parent_lower_code_desc_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_lower_code_desc_unique ON parent(lower(code) DESC)', 2),
        $record235('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($records));
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 reports mixed descending composite flags'] = static function (TestRunner $t) use ($record235): void {
    $records = [
        $record235('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a TEXT, b TEXT)', 1),
        $record235('index', 'parent_ab_desc_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_ab_desc_unique ON parent(a ASC, b DESC)', 2),
        $record235('table', 'child', 'child', 4, 'CREATE TABLE child(a TEXT, b TEXT, FOREIGN KEY(a, b) REFERENCES parent(a, b))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235($records);
    $t->same(2, count($rows));
    $t->same([0, 1], $rows[0]['index_desc_flags']);
    $t->same(['b'], $rows[0]['descending_key_columns']);
    $t->same(false, $rows[0]['index_column_desc']);
    $t->same(true, $rows[1]['index_column_desc']);
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 rejects stale cursor'] = static function (TestRunner $t) use ($page235, $blockedNextRecords235): void {
    $full = $page235();
    $first = $page235(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page235($full['total'] - 3, 2, $first['next'], $blockedNextRecords235));
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 rejects stale offset'] = static function (TestRunner $t) use ($page235): void {
    $full = $page235();
    $first = $page235(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page235($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentDescendingUniqueRows235([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey desc parent unique current source next235 rejects invalid bounds'] = static function (TestRunner $t) use ($page235): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page235(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page235(0, 0));
};

return $tests;
