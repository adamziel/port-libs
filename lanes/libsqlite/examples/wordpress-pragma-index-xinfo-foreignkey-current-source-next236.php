<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords = [
    $record('table', 'WpTerms', 'WpTerms', 2, 'CREATE TABLE "WpTerms"("Term_ID" INTEGER PRIMARY KEY, "Slug" TEXT NOT NULL, "Taxonomy" TEXT NOT NULL)', 1),
    $record('index', 'WpTermsSlugTaxUnique', 'WpTerms', 3, 'CREATE UNIQUE INDEX "WpTermsSlugTaxUnique" ON "WpTerms"("Slug", "Taxonomy")', 2),
    $record('table', 'WpTermImport', 'WpTermImport', 4, 'CREATE TABLE "WpTermImport"(
        "Import_ID" INTEGER PRIMARY KEY,
        "Term_ID" INTEGER NOT NULL,
        "Slug" TEXT NOT NULL,
        "Taxonomy" TEXT NOT NULL,
        FOREIGN KEY("slug", "taxonomy") REFERENCES "WpTerms"("slug", "taxonomy"),
        FOREIGN KEY("term_id") REFERENCES "WpTerms"("term_id")
    )', 3),
];

$nextRecords = [
    $record('table', 'WpTerms', 'WpTerms', 2, 'CREATE TABLE "WpTerms"("term_id" INTEGER PRIMARY KEY, "slug" TEXT NOT NULL, "taxonomy" TEXT NOT NULL)', 1),
    $record('index', 'WpTermsSlugTaxUnique', 'WpTerms', 3, 'CREATE UNIQUE INDEX "WpTermsSlugTaxUnique" ON "WpTerms"("slug", "taxonomy")', 2),
    $currentRecords[2],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page236(
    $currentRecords,
    $nextRecords,
    'PRAGMA main.index_xinfo("WpTermsSlugTaxUnique")',
    'PRAGMA main.foreign_key_list("WpTermImport")',
);

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next236',
    'wordpressUse' => 'Copied taxonomy imports can preserve quoted mixed-case table definitions while PRAGMA foreign_key_list and index_xinfo still admit parent keys using SQLite identifier case-folding.',
    'status' => $page['status'],
    'current_casefold_parent_rows' => $page['current']['foreign_key_parent_quoted_case']['casefold_only'],
    'current_exact_parent_rows' => $page['current']['foreign_key_parent_quoted_case']['exact_name_match'],
    'next_casefold_parent_rows' => $page['next_counts']['foreign_key_parent_quoted_case']['casefold_only'],
    'next_exact_parent_rows' => $page['next_counts']['foreign_key_parent_quoted_case']['exact_name_match'],
    'quoted_case_repaired' => $page['delta']['foreign_key_parent_quoted_case_repaired'],
    'source' => $page['current_source']['foreign_key_parent_quoted_case_source'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current_casefold_parent_rows'] !== 3
        || $summary['current_exact_parent_rows'] !== 0
        || $summary['next_casefold_parent_rows'] !== 0
        || $summary['next_exact_parent_rows'] !== 3
        || $summary['quoted_case_repaired'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next236 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next236 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
