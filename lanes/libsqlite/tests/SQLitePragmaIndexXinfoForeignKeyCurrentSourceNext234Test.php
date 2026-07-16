<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record234 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords234 = [
    $record234('table', 'wp_slug_parent', 'wp_slug_parent', 2, 'CREATE TABLE wp_slug_parent(site_id INTEGER NOT NULL, slug TEXT NOT NULL, locale TEXT NOT NULL)', 1),
    $record234('index', 'wp_slug_parent_lower_slug_unique', 'wp_slug_parent', 3, 'CREATE UNIQUE INDEX wp_slug_parent_lower_slug_unique ON wp_slug_parent(lower(slug))', 2),
    $record234('index', 'wp_slug_parent_site_lower_slug_unique', 'wp_slug_parent', 4, 'CREATE UNIQUE INDEX wp_slug_parent_site_lower_slug_unique ON wp_slug_parent(site_id, lower(slug))', 3),
    $record234('index', 'wp_slug_parent_locale_lower_slug_unique', 'wp_slug_parent', 5, 'CREATE UNIQUE INDEX wp_slug_parent_locale_lower_slug_unique ON wp_slug_parent(locale, lower(slug))', 4),
    $record234('table', 'wp_import_slugmeta', 'wp_import_slugmeta', 6, "CREATE TABLE wp_import_slugmeta(
        meta_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_slug_parent(slug),
        FOREIGN KEY(site_id, slug) REFERENCES wp_slug_parent(site_id, slug) ON DELETE CASCADE,
        FOREIGN KEY(locale, slug) REFERENCES wp_slug_parent(locale, slug) ON UPDATE CASCADE
    )", 5),
];

$nextRecords234 = [
    $currentRecords234[0],
    $record234('index', 'wp_slug_parent_slug_unique', 'wp_slug_parent', 7, 'CREATE UNIQUE INDEX wp_slug_parent_slug_unique ON wp_slug_parent(slug)', 6),
    $record234('index', 'wp_slug_parent_site_slug_unique', 'wp_slug_parent', 8, 'CREATE UNIQUE INDEX wp_slug_parent_site_slug_unique ON wp_slug_parent(site_id, slug)', 7),
    $record234('index', 'wp_slug_parent_locale_slug_unique', 'wp_slug_parent', 9, 'CREATE UNIQUE INDEX wp_slug_parent_locale_slug_unique ON wp_slug_parent(locale, slug)', 8),
    $currentRecords234[4],
];

$missingNextRecords234 = [
    $currentRecords234[0],
    $record234('index', 'wp_slug_parent_lower_slug_partial', 'wp_slug_parent', 10, 'CREATE UNIQUE INDEX wp_slug_parent_lower_slug_partial ON wp_slug_parent(lower(slug)) WHERE locale = "en_US"', 6),
    $currentRecords234[4],
];

$page234 = static fn (
    int $offset = 0,
    int $limit = 160,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page234(
    $currentRecords234,
    $nextRecords ?? $nextRecords234,
    'PRAGMA main.index_xinfo(wp_slug_parent_site_lower_slug_unique)',
    'PRAGMA main.foreign_key_list(wp_import_slugmeta)',
    $offset,
    $limit,
    $resume,
);

$valueAt234 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default234 = static fn (): array => $page234();
$blocked234 = static fn (): array => $page234(nextRecords: $missingNextRecords234);
$currentExpression234 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($currentRecords234);
$nextExpression234 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($nextRecords234, 'next');

$cases234 = [
    'status ok' => [$default234, 'status', 'ok'],
    'operation marker' => [$default234, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next234'],
    'source id length' => [static fn (): array => ['len' => strlen($page234()['source_id'])], 'len', 64],
    'offset default' => [$default234, 'offset', 0],
    'limit default' => [$default234, 'limit', 160],
    'dependency appended' => [$default234, 'dependencies.11', 'sqlite-pragma-foreign-key-expression-parent-index-rejection'],
    'base child prefix retained' => [$default234, 'current.foreign_key_child_action_prefix.rows', 4],
    'expression source current' => [$default234, 'current_source.foreign_key_expression_parent_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_terms'],
    'expression source next' => [$default234, 'next_source.foreign_key_expression_parent_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_terms'],
    'current expression rows' => [$default234, 'current.foreign_key_expression_parent_key.rows', 5],
    'current ok zero' => [$default234, 'current.foreign_key_expression_parent_key.ok', 0],
    'current blocked rows' => [$default234, 'current.foreign_key_expression_parent_key.blocked', 5],
    'current expression blockers' => [$default234, 'current.foreign_key_expression_parent_key.expression_parent_unique_index', 5],
    'current missing zero' => [$default234, 'current.foreign_key_expression_parent_key.missing_parent_unique_index', 0],
    'current expression terms' => [$default234, 'current.foreign_key_expression_parent_key.expression_terms', 3],
    'current composite columns' => [$default234, 'current.foreign_key_expression_parent_key.composite_columns', 2],
    'next rows' => [$default234, 'next_counts.foreign_key_expression_parent_key.rows', 5],
    'next ok rows' => [$default234, 'next_counts.foreign_key_expression_parent_key.ok', 5],
    'next blocked zero' => [$default234, 'next_counts.foreign_key_expression_parent_key.blocked', 0],
    'next expression blockers zero' => [$default234, 'next_counts.foreign_key_expression_parent_key.expression_parent_unique_index', 0],
    'next expression terms zero' => [$default234, 'next_counts.foreign_key_expression_parent_key.expression_terms', 0],
    'delta rows unchanged' => [$default234, 'delta.foreign_key_expression_parent_key_rows', 0],
    'delta blockers negative' => [$default234, 'delta.foreign_key_expression_parent_key_blockers', -5],
    'delta repaired true' => [$default234, 'delta.foreign_key_expression_parent_key_repaired', true],
    'delta changed true' => [$default234, 'delta.foreign_key_expression_parent_key_changed', true],
    'total includes expression rows' => [$default234, 'total', 81],
    'count complete' => [$default234, 'count', 81],
    'next complete null' => [$default234, 'next', null],
    'current summary single expression' => [$default234, 'current_source.foreign_key_expression_parent_key.0', 'current:wp_import_slugmeta#0.0:slug->wp_slug_parent.slug:column=:expression=wp_slug_parent_lower_slug_unique:cid=-2:expression_parent_unique_index'],
    'current summary composite first column' => [$default234, 'current_source.foreign_key_expression_parent_key.1', 'current:wp_import_slugmeta#1.0:site_id->wp_slug_parent.site_id:column=:expression=wp_slug_parent_site_lower_slug_unique:cid=0:expression_parent_unique_index'],
    'current summary composite expression' => [$default234, 'current_source.foreign_key_expression_parent_key.2', 'current:wp_import_slugmeta#1.1:slug->wp_slug_parent.slug:column=:expression=wp_slug_parent_site_lower_slug_unique:cid=-2:expression_parent_unique_index'],
    'next summary single column' => [$default234, 'next_source.foreign_key_expression_parent_key.0', 'next:wp_import_slugmeta#0.0:slug->wp_slug_parent.slug:column=wp_slug_parent_slug_unique:expression=:cid=1:ok'],
    'first appended row kind' => [$default234, 'rows.71.kind', 'foreign_key_expression_parent_key'],
    'first appended expression index' => [$default234, 'rows.71.expression_unique_index', 'wp_slug_parent_lower_slug_unique'],
    'first appended cid expression' => [$default234, 'rows.71.index_column_cid', -2],
    'first appended expression true' => [$default234, 'rows.71.index_column_is_expression', true],
    'composite first cid column' => [$default234, 'rows.72.index_column_cid', 0],
    'composite second cid expression' => [$default234, 'rows.73.index_column_cid', -2],
    'composite expression positions second' => [$default234, 'rows.73.expression_positions.0', 1],
    'next repaired index' => [$default234, 'rows.76.parent_unique_index', 'wp_slug_parent_slug_unique'],
    'next repaired cid' => [$default234, 'rows.76.index_column_cid', 1],
    'blocked next missing rows' => [$blocked234, 'next_counts.foreign_key_expression_parent_key.missing_parent_unique_index', 5],
    'blocked next ok zero' => [$blocked234, 'next_counts.foreign_key_expression_parent_key.ok', 0],
    'blocked repaired false' => [$blocked234, 'delta.foreign_key_expression_parent_key_repaired', false],
    'helper current first kind' => [$currentExpression234, '0.kind', 'foreign_key_expression_parent_key'],
    'helper current first status' => [$currentExpression234, '0.status', 'expression_parent_unique_index'],
    'helper current first message' => [$currentExpression234, '0.message', 'foreign key wp_import_slugmeta->wp_slug_parent cannot use expression UNIQUE index wp_slug_parent_lower_slug_unique as a parent key'],
    'helper current first expression count' => [$currentExpression234, '0.expression_key_count', 1],
    'helper current composite first expression count' => [$currentExpression234, '1.expression_key_count', 1],
    'helper current composite first expression false' => [$currentExpression234, '1.index_column_is_expression', false],
    'helper current composite second expression true' => [$currentExpression234, '2.index_column_is_expression', true],
    'helper next first phase' => [$nextExpression234, '0.phase', 'next'],
    'helper next first status' => [$nextExpression234, '0.status', 'ok'],
    'helper next first column index' => [$nextExpression234, '0.parent_unique_index', 'wp_slug_parent_slug_unique'],
];

$tests = [];
foreach ($cases234 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey expression parent key current source next234 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt234): void {
        $t->same($expected, $valueAt234($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey expression parent key current source next234 paginates expression rows'] = static function (TestRunner $t) use ($page234): void {
    $first = $page234(0, 71);
    $second = $page234(71, 5, $first['next']);
    $third = $page234(76, 5, $second['next']);

    $t->same(71, $first['count']);
    $t->same('foreign_key_expression_parent_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 71], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('expression_parent_unique_index', $second['rows'][0]['status']);
    $t->same('expression_parent_unique_index', $second['rows'][4]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][4]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 reports pure missing parent key'] = static function (TestRunner $t) use ($record234): void {
    $records = [
        $record234('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record234('index', 'parent_other_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_other_unique ON parent(other_column)', 2),
        $record234('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['expression_unique_index']);
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 ignores partial expression indexes'] = static function (TestRunner $t) use ($record234): void {
    $records = [
        $record234('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, active INTEGER)', 1),
        $record234('index', 'parent_lower_code_partial', 'parent', 3, 'CREATE UNIQUE INDEX parent_lower_code_partial ON parent(lower(code)) WHERE active = 1', 2),
        $record234('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['expression_unique_index']);
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 prefers valid column key over expression candidate'] = static function (TestRunner $t) use ($record234): void {
    $records = [
        $record234('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record234('index', 'parent_lower_code_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_lower_code_unique ON parent(lower(code))', 2),
        $record234('index', 'parent_code_unique', 'parent', 4, 'CREATE UNIQUE INDEX parent_code_unique ON parent(code)', 3),
        $record234('table', 'child', 'child', 5, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('parent_code_unique', $rows[0]['parent_unique_index']);
    $t->same('parent_lower_code_unique', $rows[0]['expression_unique_index']);
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 rejects stale cursor'] = static function (TestRunner $t) use ($page234, $missingNextRecords234): void {
    $first = $page234(0, 71);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page234(71, 5, $first['next'], $missingNextRecords234));
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 rejects stale offset'] = static function (TestRunner $t) use ($page234): void {
    $first = $page234(0, 71);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page234(72, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows234([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey expression parent key current source next234 rejects invalid bounds'] = static function (TestRunner $t) use ($page234): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page234(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page234(0, 0));
};

return $tests;
