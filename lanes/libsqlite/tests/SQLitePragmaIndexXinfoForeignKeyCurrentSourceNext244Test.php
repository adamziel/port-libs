<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record244 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords244 = [
    $record244('table', 'wp_parent_terms', 'wp_parent_terms', 2, 'CREATE TABLE wp_parent_terms(site_id INTEGER NOT NULL, slug TEXT NOT NULL, locale TEXT NOT NULL, name TEXT NOT NULL)', 1),
    $record244('index', 'wp_parent_terms_expr_unique', 'wp_parent_terms', 3, 'CREATE UNIQUE INDEX wp_parent_terms_expr_unique ON wp_parent_terms(site_id, lower(slug))', 2),
    $record244('index', 'wp_parent_terms_name_expr_unique', 'wp_parent_terms', 4, 'CREATE UNIQUE INDEX wp_parent_terms_name_expr_unique ON wp_parent_terms(lower(name))', 3),
    $record244('table', 'wp_term_import_edges', 'wp_term_import_edges', 5, "CREATE TABLE wp_term_import_edges(
        edge_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        FOREIGN KEY(site_id, slug) REFERENCES wp_parent_terms(site_id, slug)
    )", 4),
];

$nextRecords244 = [
    $currentRecords244[0],
    $record244('index', 'wp_parent_terms_exact_unique', 'wp_parent_terms', 3, 'CREATE UNIQUE INDEX wp_parent_terms_exact_unique ON wp_parent_terms(site_id, slug)', 2),
    $currentRecords244[2],
    $currentRecords244[3],
];

$unrelatedOnlyRecords244 = [
    $currentRecords244[0],
    $currentRecords244[2],
    $currentRecords244[3],
];

$page244 = static fn (
    int $offset = 0,
    int $limit = 240,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page244(
    $currentRecords244,
    $nextRecords ?? $nextRecords244,
    'PRAGMA main.index_xinfo(wp_parent_terms_expr_unique)',
    'PRAGMA main.foreign_key_list(wp_term_import_edges)',
    $offset,
    $limit,
    $resume,
);

$valueAt244 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default244 = static fn (): array => $page244();
$currentExpression244 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows244($currentRecords244);
$nextExpression244 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows244($nextRecords244, 'next');
$unrelatedExpression244 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows244($unrelatedOnlyRecords244);

$cases244 = [
    'status ok' => [$default244, 'status', 'ok'],
    'operation marker' => [$default244, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next244'],
    'source id length' => [static fn (): array => ['len' => strlen($page244()['source_id'])], 'len', 64],
    'offset default' => [$default244, 'offset', 0],
    'limit default' => [$default244, 'limit', 240],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-index-xinfo-expression-parent-key-admission', $page244()['dependencies'], true)], 'has', true],
    'base implicit retained' => [$default244, 'current.foreign_key_implicit_parent_references.rows', 2],
    'expression source current' => [$default244, 'current_source.foreign_key_parent_expression_index_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_key_rows'],
    'expression source next' => [$default244, 'next_source.foreign_key_parent_expression_index_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_key_rows'],
    'current expression rows' => [$default244, 'current.foreign_key_parent_expression_indexes.rows', 2],
    'current blocked rows' => [$default244, 'current.foreign_key_parent_expression_indexes.blocked', 1],
    'current unusable rows' => [$default244, 'current.foreign_key_parent_expression_indexes.expression_parent_key_unusable', 1],
    'current unrelated rows' => [$default244, 'current.foreign_key_parent_expression_indexes.expression_unique_index_unrelated', 1],
    'current expression key column count' => [$default244, 'current.foreign_key_parent_expression_indexes.expression_key_columns', 2],
    'next expression rows' => [$default244, 'next_counts.foreign_key_parent_expression_indexes.rows', 1],
    'next blocked rows zero' => [$default244, 'next_counts.foreign_key_parent_expression_indexes.blocked', 0],
    'next unrelated rows' => [$default244, 'next_counts.foreign_key_parent_expression_indexes.expression_unique_index_unrelated', 1],
    'delta rows negative' => [$default244, 'delta.foreign_key_parent_expression_index_rows', -1],
    'delta blockers negative' => [$default244, 'delta.foreign_key_parent_expression_index_blockers', -1],
    'delta repaired true' => [$default244, 'delta.foreign_key_parent_expression_index_repaired', true],
    'delta changed true' => [$default244, 'delta.foreign_key_parent_expression_index_changed', true],
    'current summary unusable' => [$default244, 'current_source.foreign_key_parent_expression_indexes.0', 'current:wp_term_import_edges#0->wp_parent_terms(site_id,slug):index=wp_parent_terms_expr_unique:key=site_id:expr=1:expression_parent_key_unusable'],
    'current summary unrelated' => [$default244, 'current_source.foreign_key_parent_expression_indexes.1', 'current:wp_term_import_edges#0->wp_parent_terms(site_id,slug):index=wp_parent_terms_name_expr_unique:key=:expr=1:expression_unique_index_unrelated'],
    'next summary unrelated' => [$default244, 'next_source.foreign_key_parent_expression_indexes.0', 'next:wp_term_import_edges#0->wp_parent_terms(site_id,slug):index=wp_parent_terms_name_expr_unique:key=:expr=1:expression_unique_index_unrelated'],
    'complete no next page' => [$default244, 'next', null],
    'helper current count' => [static fn (): array => ['count' => count($currentExpression244())], 'count', 2],
    'helper current first kind' => [$currentExpression244, '0.kind', 'foreign_key_parent_expression_index'],
    'helper current first phase' => [$currentExpression244, '0.phase', 'current'],
    'helper current first table' => [$currentExpression244, '0.table', 'wp_term_import_edges'],
    'helper current first parent' => [$currentExpression244, '0.parent', 'wp_parent_terms'],
    'helper current first parent col 0' => [$currentExpression244, '0.parent_columns.0', 'site_id'],
    'helper current first parent col 1' => [$currentExpression244, '0.parent_columns.1', 'slug'],
    'helper current first index' => [$currentExpression244, '0.index', 'wp_parent_terms_expr_unique'],
    'helper current first key col' => [$currentExpression244, '0.index_key_columns.0', 'site_id'],
    'helper current first key arity' => [$currentExpression244, '0.index_key_arity', 2],
    'helper current first expression count' => [$currentExpression244, '0.expression_key_columns', 1],
    'helper current first status' => [$currentExpression244, '0.status', 'expression_parent_key_unusable'],
    'helper current first blocked' => [$currentExpression244, '0.blocked', true],
    'helper current first message' => [$currentExpression244, '0.message', 'foreign key wp_term_import_edges->wp_parent_terms cannot use UNIQUE index wp_parent_terms_expr_unique because PRAGMA index_xinfo reports expression key columns'],
    'helper current second status' => [$currentExpression244, '1.status', 'expression_unique_index_unrelated'],
    'helper current second blocked' => [$currentExpression244, '1.blocked', false],
    'helper current second expression count' => [$currentExpression244, '1.expression_key_columns', 1],
    'helper current second key arity' => [$currentExpression244, '1.index_key_arity', 1],
    'helper current second message' => [$currentExpression244, '1.message', 'UNIQUE index wp_parent_terms_name_expr_unique on parent wp_parent_terms has expression key columns but does not match foreign key wp_term_import_edges parent columns'],
    'helper next count' => [static fn (): array => ['count' => count($nextExpression244())], 'count', 1],
    'helper next phase' => [$nextExpression244, '0.phase', 'next'],
    'helper next status unrelated' => [$nextExpression244, '0.status', 'expression_unique_index_unrelated'],
    'helper unrelated only count' => [static fn (): array => ['count' => count($unrelatedExpression244())], 'count', 1],
    'helper unrelated only blocked false' => [$unrelatedExpression244, '0.blocked', false],
];

$tests = [];
foreach ($cases244 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey expression parent key current source next244 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt244): void {
        $t->same($expected, $valueAt244($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey expression parent key current source next244 paginates appended expression rows'] = static function (TestRunner $t) use ($page244): void {
    $full = $page244();
    $baseCount = $full['total'] - 3;
    $first = $page244(0, $baseCount);
    $second = $page244($baseCount, 2, $first['next']);
    $third = $page244($baseCount + 2, 1, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_expression_index', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('expression_parent_key_unusable', $second['rows'][0]['status']);
    $t->same('expression_unique_index_unrelated', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey expression parent key current source next244 skips exact non expression unique parent index'] = static function (TestRunner $t) use ($nextExpression244): void {
    $rows = $nextExpression244();

    $t->same(1, count($rows));
    $t->same('wp_parent_terms_name_expr_unique', $rows[0]['index']);
    $t->same('expression_unique_index_unrelated', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey expression parent key current source next244 ignores partial expression indexes'] = static function (TestRunner $t) use ($record244): void {
    $records = [
        $record244('table', 'parent', 'parent', 2, 'CREATE TABLE parent(site_id INTEGER, slug TEXT)', 1),
        $record244('index', 'parent_partial_expr', 'parent', 3, 'CREATE UNIQUE INDEX parent_partial_expr ON parent(site_id, lower(slug)) WHERE slug IS NOT NULL', 2),
        $record244('table', 'child', 'child', 4, 'CREATE TABLE child(site_id INTEGER, slug TEXT, FOREIGN KEY(site_id, slug) REFERENCES parent(site_id, slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows244($records));
};

$tests['pragma index xinfo foreignkey expression parent key current source next244 rejects stale cursor'] = static function (TestRunner $t) use ($page244, $unrelatedOnlyRecords244): void {
    $full = $page244();
    $first = $page244(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page244($full['total'] - 3, 2, $first['next'], $unrelatedOnlyRecords244));
};

$tests['pragma index xinfo foreignkey expression parent key current source next244 rejects stale offset'] = static function (TestRunner $t) use ($page244): void {
    $full = $page244();
    $first = $page244(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page244($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey expression parent key current source next244 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::expressionParentKeyRows244([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey expression parent key current source next244 rejects invalid bounds'] = static function (TestRunner $t) use ($page244): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page244(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page244(0, 0));
};

return $tests;
