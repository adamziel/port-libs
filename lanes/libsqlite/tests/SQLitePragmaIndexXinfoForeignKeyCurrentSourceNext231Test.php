<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record231 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords231 = [
    $record231('table', 'wp_parent_terms', 'wp_parent_terms', 2, 'CREATE TABLE wp_parent_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL)', 1),
    $record231('index', 'wp_parent_terms_lower_slug_unique', 'wp_parent_terms', 3, 'CREATE UNIQUE INDEX wp_parent_terms_lower_slug_unique ON wp_parent_terms(lower(slug))', 2),
    $record231('index', 'wp_parent_terms_lower_slug_tax_unique', 'wp_parent_terms', 4, 'CREATE UNIQUE INDEX wp_parent_terms_lower_slug_tax_unique ON wp_parent_terms(lower(slug), taxonomy)', 3),
    $record231('index', 'wp_parent_terms_locale_unique', 'wp_parent_terms', 5, 'CREATE UNIQUE INDEX wp_parent_terms_locale_unique ON wp_parent_terms(locale)', 4),
    $record231('table', 'wp_term_import_edges', 'wp_term_import_edges', 6, "CREATE TABLE wp_term_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        taxonomy TEXT NOT NULL,
        locale TEXT NOT NULL,
        FOREIGN KEY(slug) REFERENCES wp_parent_terms(slug),
        FOREIGN KEY(slug, taxonomy) REFERENCES wp_parent_terms(slug, taxonomy),
        FOREIGN KEY(locale) REFERENCES wp_parent_terms(locale),
        FOREIGN KEY(term_id) REFERENCES wp_parent_terms(term_id)
    )", 5),
];

$nextRecords231 = [
    $currentRecords231[0],
    $record231('index', 'wp_parent_terms_slug_unique', 'wp_parent_terms', 7, 'CREATE UNIQUE INDEX wp_parent_terms_slug_unique ON wp_parent_terms(slug)', 2),
    $record231('index', 'wp_parent_terms_slug_tax_unique', 'wp_parent_terms', 8, 'CREATE UNIQUE INDEX wp_parent_terms_slug_tax_unique ON wp_parent_terms(slug, taxonomy)', 3),
    $currentRecords231[1],
    $currentRecords231[2],
    $currentRecords231[3],
    $currentRecords231[4],
];

$missingNextRecords231 = [
    $currentRecords231[0],
    $record231('index', 'wp_parent_terms_taxonomy_unique', 'wp_parent_terms', 9, 'CREATE UNIQUE INDEX wp_parent_terms_taxonomy_unique ON wp_parent_terms(taxonomy)', 2),
    $currentRecords231[4],
];

$page231 = static fn (
    int $offset = 0,
    int $limit = 140,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page231(
    $currentRecords231,
    $nextRecords ?? $nextRecords231,
    'PRAGMA main.index_xinfo(wp_parent_terms_lower_slug_tax_unique)',
    'PRAGMA main.foreign_key_list(wp_term_import_edges)',
    $offset,
    $limit,
    $resume,
);

$valueAt231 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default231 = static fn (): array => $page231();
$blocked231 = static fn (): array => $page231(nextRecords: $missingNextRecords231);
$currentExpression231 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231($currentRecords231);
$nextExpression231 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231($nextRecords231, 'next');
$currentPageExpression231 = static fn (): array => array_values(array_filter(
    $page231()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_expression_unique' && ($row['phase'] ?? null) === 'current',
));
$nextPageExpression231 = static fn (): array => array_values(array_filter(
    $page231()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_expression_unique' && ($row['phase'] ?? null) === 'next',
));

$cases231 = [
    'status ok' => [$default231, 'status', 'ok'],
    'operation marker' => [$default231, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next231'],
    'source id length' => [static fn (): array => ['len' => strlen($page231()['source_id'])], 'len', 64],
    'offset default' => [$default231, 'offset', 0],
    'limit default' => [$default231, 'limit', 140],
    'dependency appended' => [$default231, 'dependencies.10', 'sqlite-pragma-foreign-key-parent-expression-unique-index'],
    'base exact arity retained' => [$default231, 'current.foreign_key_parent_key_exact_arity.rows', 5],
    'expression source current' => [$default231, 'current_source.foreign_key_parent_expression_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_terms'],
    'expression source next' => [$default231, 'next_source.foreign_key_parent_expression_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_terms'],
    'current rows' => [$default231, 'current.foreign_key_parent_expression_unique.rows', 5],
    'current ok rows' => [$default231, 'current.foreign_key_parent_expression_unique.ok', 2],
    'current pk ok rows' => [$default231, 'current.foreign_key_parent_expression_unique.primary_key_ok', 1],
    'current blocked rows' => [$default231, 'current.foreign_key_parent_expression_unique.blocked', 3],
    'current expression blockers' => [$default231, 'current.foreign_key_parent_expression_unique.expression_unique_index', 3],
    'current missing zero' => [$default231, 'current.foreign_key_parent_expression_unique.missing_parent_unique_index', 0],
    'current expression terms' => [$default231, 'current.foreign_key_parent_expression_unique.expression_terms', 3],
    'next rows' => [$default231, 'next_counts.foreign_key_parent_expression_unique.rows', 5],
    'next ok rows' => [$default231, 'next_counts.foreign_key_parent_expression_unique.ok', 5],
    'next pk ok rows' => [$default231, 'next_counts.foreign_key_parent_expression_unique.primary_key_ok', 1],
    'next blocked zero' => [$default231, 'next_counts.foreign_key_parent_expression_unique.blocked', 0],
    'next expression blockers zero' => [$default231, 'next_counts.foreign_key_parent_expression_unique.expression_unique_index', 0],
    'delta rows unchanged' => [$default231, 'delta.foreign_key_parent_expression_unique_rows', 0],
    'delta blockers negative' => [$default231, 'delta.foreign_key_parent_expression_unique_blockers', -3],
    'delta repaired true' => [$default231, 'delta.foreign_key_parent_expression_unique_repaired', true],
    'delta changed true' => [$default231, 'delta.foreign_key_parent_expression_unique_changed', true],
    'complete no next page' => [$default231, 'next', null],
    'current summary slug expression' => [$default231, 'current_source.foreign_key_parent_expression_unique.0', 'current:wp_term_import_edges#0.0:slug->wp_parent_terms.slug:wp_parent_terms_lower_slug_unique:parent=slug:index=<expr>:expr=seqno-0:expression_unique_index'],
    'current summary composite expression first' => [$default231, 'current_source.foreign_key_parent_expression_unique.1', 'current:wp_term_import_edges#1.0:slug->wp_parent_terms.slug:wp_parent_terms_lower_slug_tax_unique:parent=slug,taxonomy:index=<expr>,taxonomy:expr=seqno-0:expression_unique_index'],
    'current summary locale ok' => [$default231, 'current_source.foreign_key_parent_expression_unique.3', 'current:wp_term_import_edges#2.0:locale->wp_parent_terms.locale:wp_parent_terms_locale_unique:parent=locale:index=locale:expr=:ok'],
    'next summary slug ok' => [$default231, 'next_source.foreign_key_parent_expression_unique.0', 'next:wp_term_import_edges#0.0:slug->wp_parent_terms.slug:wp_parent_terms_slug_unique:parent=slug:index=slug:expr=:ok'],
    'first appended row kind' => [$currentPageExpression231, '0.kind', 'foreign_key_parent_expression_unique'],
    'first appended row status' => [$currentPageExpression231, '0.status', 'expression_unique_index'],
    'first appended expression true' => [$currentPageExpression231, '0.index_column_is_expression', true],
    'first appended expression label' => [$currentPageExpression231, '0.index_expression_terms.0', 'seqno-0'],
    'first appended index column null' => [$currentPageExpression231, '0.index_column', null],
    'composite second expression blocker' => [$currentPageExpression231, '2.status', 'expression_unique_index'],
    'locale plain unique ok' => [$currentPageExpression231, '3.status', 'ok'],
    'primary key ok' => [$currentPageExpression231, '4.status', 'primary_key_ok'],
    'next slug repaired index' => [$nextPageExpression231, '0.parent_unique_index', 'wp_parent_terms_slug_unique'],
    'next composite repaired index' => [$nextPageExpression231, '1.parent_unique_index', 'wp_parent_terms_slug_tax_unique'],
    'next locale plain unique' => [$nextPageExpression231, '3.status', 'ok'],
    'next primary key status' => [$nextPageExpression231, '4.status', 'primary_key_ok'],
    'blocked next missing rows' => [$blocked231, 'next_counts.foreign_key_parent_expression_unique.missing_parent_unique_index', 4],
    'blocked next ok pk only' => [$blocked231, 'next_counts.foreign_key_parent_expression_unique.ok', 1],
    'blocked repaired false' => [$blocked231, 'delta.foreign_key_parent_expression_unique_repaired', false],
    'helper current first kind' => [$currentExpression231, '0.kind', 'foreign_key_parent_expression_unique'],
    'helper current first status' => [$currentExpression231, '0.status', 'expression_unique_index'],
    'helper current first expression term' => [$currentExpression231, '0.index_expression_terms.0', 'seqno-0'],
    'helper current composite second to' => [$currentExpression231, '2.to', 'taxonomy'],
    'helper current locale ok' => [$currentExpression231, '3.status', 'ok'],
    'helper current pk index' => [$currentExpression231, '4.parent_unique_index', 'sqlite_primary_key'],
    'helper next first phase' => [$nextExpression231, '0.phase', 'next'],
    'helper next first ok' => [$nextExpression231, '0.status', 'ok'],
    'helper next first column' => [$nextExpression231, '0.index_column', 'slug'],
];

$tests = [];
foreach ($cases231 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey expression unique parent current source next231 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt231): void {
        $t->same($expected, $valueAt231($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey expression unique parent current source next231 paginates expression rows'] = static function (TestRunner $t) use ($page231): void {
    $full = $page231();
    $baseCount = $full['total'] - 10;
    $first = $page231(0, $baseCount);
    $second = $page231($baseCount, 5, $first['next']);
    $third = $page231($baseCount + 5, 5, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_expression_unique', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('expression_unique_index', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][0]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 ignores expression unique index as parent key'] = static function (TestRunner $t) use ($currentExpression231): void {
    $rows = $currentExpression231();

    $t->same(5, count($rows));
    $t->same('expression_unique_index', $rows[0]['status']);
    $t->same(true, $rows[0]['index_column_is_expression']);
    $t->same(['seqno-0'], $rows[1]['index_expression_terms']);
    $t->same('ok', $rows[3]['status']);
    $t->same('primary_key_ok', $rows[4]['status']);
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 reports missing after ignoring expression indexes'] = static function (TestRunner $t) use ($record231): void {
    $records = [
        $record231('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, locale TEXT)', 1),
        $record231('index', 'parent_expr_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_expr_unique ON parent(lower(code), locale)', 2),
        $record231('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, locale TEXT, FOREIGN KEY(code, locale) REFERENCES parent(code, locale))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231($records);
    $t->same(2, count($rows));
    $t->same('expression_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['index_column']);
    $t->same('locale', $rows[1]['index_column']);
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 accepts plain unique before later expression index'] = static function (TestRunner $t) use ($record231): void {
    $records = [
        $record231('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record231('index', 'parent_code_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_unique ON parent(code)', 2),
        $record231('index', 'parent_lower_code_unique', 'parent', 4, 'CREATE UNIQUE INDEX parent_lower_code_unique ON parent(lower(code))', 3),
        $record231('table', 'child', 'child', 5, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('parent_code_unique', $rows[0]['parent_unique_index']);
    $t->same([], $rows[0]['index_expression_terms']);
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 rejects stale cursor'] = static function (TestRunner $t) use ($page231, $missingNextRecords231): void {
    $full = $page231();
    $first = $page231(0, $full['total'] - 10);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page231($full['total'] - 10, 5, $first['next'], $missingNextRecords231));
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 rejects stale offset'] = static function (TestRunner $t) use ($page231): void {
    $full = $page231();
    $first = $page231(0, $full['total'] - 10);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page231($full['total'] - 9, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentExpressionUniqueRows231([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey expression unique parent current source next231 rejects invalid bounds'] = static function (TestRunner $t) use ($page231): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page231(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page231(0, 0));
};

return $tests;
