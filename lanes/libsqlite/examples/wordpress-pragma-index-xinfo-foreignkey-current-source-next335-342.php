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
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next335-342',
    'wordpressUse' => 'WordPress import previews can keep SET NULL and SET DEFAULT foreign-key action throughput blocked when PRAGMA index_xinfo shows only partial or expression child lookup indexes.',
    'next335_update_set_null_partial_child_lookup_index' => $scenarioCount($scenario(335, 'update', 'SET NULL', $partialIndex('wp_comments_335_post_partial', 'wp_comments_335', 'comment_post_ID', 3)), 'update_set_null_partial_child_lookup_index'),
    'next336_delete_set_null_partial_child_lookup_index' => $scenarioCount($scenario(336, 'delete', 'SET NULL', $partialIndex('wp_comments_336_post_partial', 'wp_comments_336', 'comment_post_ID', 3)), 'delete_set_null_partial_child_lookup_index'),
    'next337_update_set_null_expression_child_lookup_index' => $scenarioCount($scenario(337, 'update', 'SET NULL', $expressionIndex('wp_comments_337_post_expr', 'wp_comments_337', 'comment_post_ID', 3)), 'update_set_null_expression_child_lookup_index'),
    'next338_delete_set_null_expression_child_lookup_index' => $scenarioCount($scenario(338, 'delete', 'SET NULL', $expressionIndex('wp_comments_338_post_expr', 'wp_comments_338', 'comment_post_ID', 3)), 'delete_set_null_expression_child_lookup_index'),
    'next339_update_set_default_partial_child_lookup_index' => $scenarioCount($scenario(339, 'update', 'SET DEFAULT', $partialIndex('wp_comments_339_post_partial', 'wp_comments_339', 'comment_post_ID', 3)), 'update_set_default_partial_child_lookup_index'),
    'next340_delete_set_default_partial_child_lookup_index' => $scenarioCount($scenario(340, 'delete', 'SET DEFAULT', $partialIndex('wp_comments_340_post_partial', 'wp_comments_340', 'comment_post_ID', 3)), 'delete_set_default_partial_child_lookup_index'),
    'next341_update_set_default_expression_child_lookup_index' => $scenarioCount($scenario(341, 'update', 'SET DEFAULT', $expressionIndex('wp_comments_341_post_expr', 'wp_comments_341', 'comment_post_ID', 3)), 'update_set_default_expression_child_lookup_index'),
    'next342_delete_set_default_expression_child_lookup_index' => $scenarioCount($scenario(342, 'delete', 'SET DEFAULT', $expressionIndex('wp_comments_342_post_expr', 'wp_comments_342', 'comment_post_ID', 3)), 'delete_set_default_expression_child_lookup_index'),
    'implemented_pages' => array_values(array_filter(
        range(335, 342),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next335-342 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(335, 342)) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next335-342 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next335-342 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
