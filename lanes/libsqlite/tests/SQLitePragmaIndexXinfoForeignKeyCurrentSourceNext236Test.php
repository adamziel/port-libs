<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record236 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords236 = [
    $record236('table', 'WpTerms', 'WpTerms', 2, 'CREATE TABLE "WpTerms"("Term_ID" INTEGER PRIMARY KEY, "Slug" TEXT NOT NULL, "Taxonomy" TEXT NOT NULL, "Locale" TEXT NOT NULL)', 1),
    $record236('index', 'WpTermsSlugTaxUnique', 'WpTerms', 3, 'CREATE UNIQUE INDEX "WpTermsSlugTaxUnique" ON "WpTerms"("Slug", "Taxonomy")', 2),
    $record236('index', 'WpTermsLocaleUnique', 'WpTerms', 4, 'CREATE UNIQUE INDEX "WpTermsLocaleUnique" ON "WpTerms"("Locale")', 3),
    $record236('table', 'WpTermImport', 'WpTermImport', 5, 'CREATE TABLE "WpTermImport"(
        "Import_ID" INTEGER PRIMARY KEY,
        "Term_ID" INTEGER NOT NULL,
        "Slug" TEXT NOT NULL,
        "Taxonomy" TEXT NOT NULL,
        "Locale" TEXT NOT NULL,
        FOREIGN KEY("slug", "taxonomy") REFERENCES "WpTerms"("slug", "taxonomy"),
        FOREIGN KEY("locale") REFERENCES "WpTerms"("locale"),
        FOREIGN KEY("term_id") REFERENCES "WpTerms"("term_id")
    )', 4),
];

$nextRecords236 = [
    $record236('table', 'WpTerms', 'WpTerms', 2, 'CREATE TABLE "WpTerms"("term_id" INTEGER PRIMARY KEY, "slug" TEXT NOT NULL, "taxonomy" TEXT NOT NULL, "locale" TEXT NOT NULL)', 1),
    $record236('index', 'WpTermsSlugTaxUnique', 'WpTerms', 3, 'CREATE UNIQUE INDEX "WpTermsSlugTaxUnique" ON "WpTerms"("slug", "taxonomy")', 2),
    $record236('index', 'WpTermsLocaleUnique', 'WpTerms', 4, 'CREATE UNIQUE INDEX "WpTermsLocaleUnique" ON "WpTerms"("locale")', 3),
    $currentRecords236[3],
];

$missingNextRecords236 = [
    $currentRecords236[0],
    $record236('index', 'WpTermsTaxonomyUnique', 'WpTerms', 6, 'CREATE UNIQUE INDEX "WpTermsTaxonomyUnique" ON "WpTerms"("Taxonomy")', 2),
    $currentRecords236[3],
];

$page236 = static fn (
    int $offset = 0,
    int $limit = 160,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page236(
    $currentRecords236,
    $nextRecords ?? $nextRecords236,
    'PRAGMA main.index_xinfo("WpTermsSlugTaxUnique")',
    'PRAGMA main.foreign_key_list("WpTermImport")',
    $offset,
    $limit,
    $resume,
);

$valueAt236 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default236 = static fn (): array => $page236();
$blocked236 = static fn (): array => $page236(nextRecords: $missingNextRecords236);
$currentQuoted236 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentQuotedCaseRows236($currentRecords236);
$nextQuoted236 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentQuotedCaseRows236($nextRecords236, 'next');
$currentPageQuoted236 = static fn (): array => array_values(array_filter(
    $page236()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_quoted_case' && ($row['phase'] ?? null) === 'current',
));
$nextPageQuoted236 = static fn (): array => array_values(array_filter(
    $page236()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_quoted_case' && ($row['phase'] ?? null) === 'next',
));

$cases236 = [
    'status ok' => [$default236, 'status', 'ok'],
    'operation marker' => [$default236, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next236'],
    'source id length' => [static fn (): array => ['len' => strlen($page236()['source_id'])], 'len', 64],
    'offset default' => [$default236, 'offset', 0],
    'limit default' => [$default236, 'limit', 160],
    'dependency appended' => [$default236, 'dependencies.11', 'sqlite-pragma-foreign-key-parent-quoted-casefold'],
    'base expression retained' => [$default236, 'current.foreign_key_parent_expression_unique.rows', 4],
    'quoted source current' => [$default236, 'current_source.foreign_key_parent_quoted_case_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_casefold'],
    'quoted source next' => [$default236, 'next_source.foreign_key_parent_quoted_case_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_casefold'],
    'current rows' => [$default236, 'current.foreign_key_parent_quoted_case.rows', 4],
    'current exact zero' => [$default236, 'current.foreign_key_parent_quoted_case.exact_name_match', 0],
    'current casefold rows' => [$default236, 'current.foreign_key_parent_quoted_case.casefold_only', 4],
    'current missing zero' => [$default236, 'current.foreign_key_parent_quoted_case.missing_parent_unique_index', 0],
    'current parent columns counted' => [$default236, 'current.foreign_key_parent_quoted_case.parent_columns', 6],
    'next rows' => [$default236, 'next_counts.foreign_key_parent_quoted_case.rows', 4],
    'next exact rows' => [$default236, 'next_counts.foreign_key_parent_quoted_case.exact_name_match', 4],
    'next casefold zero' => [$default236, 'next_counts.foreign_key_parent_quoted_case.casefold_only', 0],
    'delta rows unchanged' => [$default236, 'delta.foreign_key_parent_quoted_case_rows', 0],
    'delta exact positive' => [$default236, 'delta.foreign_key_parent_quoted_case_exact_name_delta', 4],
    'delta repaired true' => [$default236, 'delta.foreign_key_parent_quoted_case_repaired', true],
    'delta changed true' => [$default236, 'delta.foreign_key_parent_quoted_case_changed', true],
    'complete no next page' => [$default236, 'next', null],
    'current summary first casefold' => [$default236, 'current_source.foreign_key_parent_quoted_case.0', 'current:WpTermImport#0.0:slug->WpTerms.slug:WpTermsSlugTaxUnique:parent=slug,taxonomy:index=Slug,Taxonomy:column=Slug:casefold_name_match'],
    'current summary pk casefold' => [$default236, 'current_source.foreign_key_parent_quoted_case.3', 'current:WpTermImport#2.0:term_id->WpTerms.term_id:sqlite_primary_key:parent=term_id:index=Term_ID:column=Term_ID:casefold_name_match'],
    'next summary first exact' => [$default236, 'next_source.foreign_key_parent_quoted_case.0', 'next:WpTermImport#0.0:slug->WpTerms.slug:WpTermsSlugTaxUnique:parent=slug,taxonomy:index=slug,taxonomy:column=slug:exact_name_match'],
    'first appended row kind' => [$currentPageQuoted236, '0.kind', 'foreign_key_parent_quoted_case'],
    'first appended status' => [$currentPageQuoted236, '0.status', 'casefold_name_match'],
    'first appended exact false' => [$currentPageQuoted236, '0.exact_name_match', false],
    'first appended casefold true' => [$currentPageQuoted236, '0.casefold_name_match', true],
    'first appended index column preserves case' => [$currentPageQuoted236, '0.index_column', 'Slug'],
    'second appended taxonomy preserves case' => [$currentPageQuoted236, '1.index_column', 'Taxonomy'],
    'locale appended casefold' => [$currentPageQuoted236, '2.status', 'casefold_name_match'],
    'primary key appended casefold' => [$currentPageQuoted236, '3.parent_unique_index', 'sqlite_primary_key'],
    'next first exact' => [$nextPageQuoted236, '0.status', 'exact_name_match'],
    'next first exact bool' => [$nextPageQuoted236, '0.exact_name_match', true],
    'next first lower index column' => [$nextPageQuoted236, '0.index_column', 'slug'],
    'blocked next missing rows' => [$blocked236, 'next_counts.foreign_key_parent_quoted_case.missing_parent_unique_index', 3],
    'blocked next exact zero' => [$blocked236, 'next_counts.foreign_key_parent_quoted_case.exact_name_match', 0],
    'blocked repaired false' => [$blocked236, 'delta.foreign_key_parent_quoted_case_repaired', false],
    'helper current first kind' => [$currentQuoted236, '0.kind', 'foreign_key_parent_quoted_case'],
    'helper current first status' => [$currentQuoted236, '0.status', 'casefold_name_match'],
    'helper current first index column' => [$currentQuoted236, '0.index_column', 'Slug'],
    'helper current second taxonomy' => [$currentQuoted236, '1.index_column', 'Taxonomy'],
    'helper current pk index' => [$currentQuoted236, '3.parent_unique_index', 'sqlite_primary_key'],
    'helper next first phase' => [$nextQuoted236, '0.phase', 'next'],
    'helper next first exact' => [$nextQuoted236, '0.status', 'exact_name_match'],
    'helper next first column' => [$nextQuoted236, '0.index_column', 'slug'],
];

$tests = [];
foreach ($cases236 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey quoted case current source next236 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt236): void {
        $t->same($expected, $valueAt236($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey quoted case current source next236 paginates quoted case rows'] = static function (TestRunner $t) use ($page236): void {
    $full = $page236();
    $baseCount = $full['total'] - 8;
    $first = $page236(0, $baseCount);
    $second = $page236($baseCount, 4, $first['next']);
    $third = $page236($baseCount + 4, 4, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_quoted_case', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('casefold_name_match', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('exact_name_match', $third['rows'][0]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey quoted case current source next236 accepts casefolded quoted parent keys'] = static function (TestRunner $t) use ($currentQuoted236): void {
    $rows = $currentQuoted236();

    $t->same(4, count($rows));
    $t->same('casefold_name_match', $rows[0]['status']);
    $t->same(false, $rows[0]['exact_name_match']);
    $t->same(true, $rows[0]['casefold_name_match']);
    $t->same('Slug', $rows[0]['index_column']);
    $t->same('sqlite_primary_key', $rows[3]['parent_unique_index']);
};

$tests['pragma index xinfo foreignkey quoted case current source next236 reports missing casefolded parent key'] = static function (TestRunner $t) use ($record236): void {
    $records = [
        $record236('table', 'Parent', 'Parent', 2, 'CREATE TABLE "Parent"("Code" TEXT, "Locale" TEXT)', 1),
        $record236('index', 'ParentLocaleUnique', 'Parent', 3, 'CREATE UNIQUE INDEX "ParentLocaleUnique" ON "Parent"("Locale")', 2),
        $record236('table', 'Child', 'Child', 4, 'CREATE TABLE "Child"("code" TEXT, FOREIGN KEY("code") REFERENCES "Parent"("code"))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentQuotedCaseRows236($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['parent_unique_index']);
};

$tests['pragma index xinfo foreignkey quoted case current source next236 rejects stale cursor'] = static function (TestRunner $t) use ($page236, $missingNextRecords236): void {
    $full = $page236();
    $first = $page236(0, $full['total'] - 10);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page236($full['total'] - 10, 5, $first['next'], $missingNextRecords236));
};

$tests['pragma index xinfo foreignkey quoted case current source next236 rejects stale offset'] = static function (TestRunner $t) use ($page236): void {
    $full = $page236();
    $first = $page236(0, $full['total'] - 10);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page236($full['total'] - 9, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey quoted case current source next236 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentQuotedCaseRows236([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey quoted case current source next236 rejects invalid bounds'] = static function (TestRunner $t) use ($page236): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page236(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page236(0, 0));
};

return $tests;
