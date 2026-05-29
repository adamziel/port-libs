<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenarioCount = static function (array $records, string $status): int {
    return count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', $status));
};

$partialIndex = static fn (string $name, string $table, string $column, int $rowId): SQLiteSchemaRecord => $record(
    'index',
    $name,
    $table,
    $rowId + 10,
    "CREATE INDEX {$name} ON {$table}({$column}) WHERE {$column} IS NOT NULL",
    $rowId,
);
$expressionIndex = static fn (string $name, string $table, string $column, int $rowId): SQLiteSchemaRecord => $record(
    'index',
    $name,
    $table,
    $rowId + 10,
    "CREATE INDEX {$name} ON {$table}({$column} + 0)",
    $rowId,
);

$scenario = static function (int $slice, string $actionColumn, string $action, SQLiteSchemaRecord $index) use ($record): array {
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    $default = $action === 'SET DEFAULT' ? ' DEFAULT 0' : '';

    return [
        $record('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}(ID INTEGER PRIMARY KEY)", 1),
        $record('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}(comment_post_ID INTEGER{$default} REFERENCES wp_posts_{$slice}(ID) {$clause})", 2),
        $index,
    ];
};

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350',
    'wordpressUse' => 'WordPress import previews can carry the next335-342 SET NULL and SET DEFAULT PRAGMA index_xinfo action diagnostics forward as current-source next343-350 admission pages.',
    'next343_update_set_null_partial_child_lookup_index' => $scenarioCount($scenario(343, 'update', 'SET NULL', $partialIndex('wp_comments_343_post_partial', 'wp_comments_343', 'comment_post_ID', 3)), 'update_set_null_partial_child_lookup_index'),
    'next344_delete_set_null_partial_child_lookup_index' => $scenarioCount($scenario(344, 'delete', 'SET NULL', $partialIndex('wp_comments_344_post_partial', 'wp_comments_344', 'comment_post_ID', 3)), 'delete_set_null_partial_child_lookup_index'),
    'next345_update_set_null_expression_child_lookup_index' => $scenarioCount($scenario(345, 'update', 'SET NULL', $expressionIndex('wp_comments_345_post_expr', 'wp_comments_345', 'comment_post_ID', 3)), 'update_set_null_expression_child_lookup_index'),
    'next346_delete_set_null_expression_child_lookup_index' => $scenarioCount($scenario(346, 'delete', 'SET NULL', $expressionIndex('wp_comments_346_post_expr', 'wp_comments_346', 'comment_post_ID', 3)), 'delete_set_null_expression_child_lookup_index'),
    'next347_update_set_default_partial_child_lookup_index' => $scenarioCount($scenario(347, 'update', 'SET DEFAULT', $partialIndex('wp_comments_347_post_partial', 'wp_comments_347', 'comment_post_ID', 3)), 'update_set_default_partial_child_lookup_index'),
    'next348_delete_set_default_partial_child_lookup_index' => $scenarioCount($scenario(348, 'delete', 'SET DEFAULT', $partialIndex('wp_comments_348_post_partial', 'wp_comments_348', 'comment_post_ID', 3)), 'delete_set_default_partial_child_lookup_index'),
    'next349_update_set_default_expression_child_lookup_index' => $scenarioCount($scenario(349, 'update', 'SET DEFAULT', $expressionIndex('wp_comments_349_post_expr', 'wp_comments_349', 'comment_post_ID', 3)), 'update_set_default_expression_child_lookup_index'),
    'next350_delete_set_default_expression_child_lookup_index' => $scenarioCount($scenario(350, 'delete', 'SET DEFAULT', $expressionIndex('wp_comments_350_post_expr', 'wp_comments_350', 'comment_post_ID', 3)), 'delete_set_default_expression_child_lookup_index'),
    'implemented_pages' => array_values(array_filter(
        range(343, 350),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(343, 350)) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
