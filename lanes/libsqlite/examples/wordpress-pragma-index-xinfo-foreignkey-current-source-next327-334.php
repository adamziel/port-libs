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

    return [
        $record('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}(ID INTEGER PRIMARY KEY)", 1),
        $record('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}(comment_post_ID INTEGER REFERENCES wp_posts_{$slice}(ID) {$clause})", 2),
        $index,
    ];
};

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next327-334',
    'wordpressUse' => 'WordPress import previews can distinguish child lookup indexes that PRAGMA index_xinfo exposes as partial or expression-based, keeping FK action throughput blocked only for the affected current-source slice.',
    'next327_update_cascade_partial_child_lookup_index' => $scenarioCount($scenario(327, 'update', 'CASCADE', $partialIndex('wp_comments_327_post_partial', 'wp_comments_327', 'comment_post_ID', 3)), 'update_cascade_partial_child_lookup_index'),
    'next328_delete_cascade_partial_child_lookup_index' => $scenarioCount($scenario(328, 'delete', 'CASCADE', $partialIndex('wp_comments_328_post_partial', 'wp_comments_328', 'comment_post_ID', 3)), 'delete_cascade_partial_child_lookup_index'),
    'next329_update_cascade_expression_child_lookup_index' => $scenarioCount($scenario(329, 'update', 'CASCADE', $expressionIndex('wp_comments_329_post_expr', 'wp_comments_329', 'comment_post_ID', 3)), 'update_cascade_expression_child_lookup_index'),
    'next330_delete_cascade_expression_child_lookup_index' => $scenarioCount($scenario(330, 'delete', 'CASCADE', $expressionIndex('wp_comments_330_post_expr', 'wp_comments_330', 'comment_post_ID', 3)), 'delete_cascade_expression_child_lookup_index'),
    'next331_update_restrict_expression_child_lookup_index' => $scenarioCount($scenario(331, 'update', 'RESTRICT', $expressionIndex('wp_comments_331_post_expr', 'wp_comments_331', 'comment_post_ID', 3)), 'update_restrict_expression_child_lookup_index'),
    'next332_delete_restrict_expression_child_lookup_index' => $scenarioCount($scenario(332, 'delete', 'RESTRICT', $expressionIndex('wp_comments_332_post_expr', 'wp_comments_332', 'comment_post_ID', 3)), 'delete_restrict_expression_child_lookup_index'),
    'next333_update_no_action_expression_child_lookup_index' => $scenarioCount($scenario(333, 'update', 'NO ACTION', $expressionIndex('wp_comments_333_post_expr', 'wp_comments_333', 'comment_post_ID', 3)), 'update_no_action_expression_child_lookup_index'),
    'next334_delete_no_action_expression_child_lookup_index' => $scenarioCount($scenario(334, 'delete', 'NO ACTION', $expressionIndex('wp_comments_334_post_expr', 'wp_comments_334', 'comment_post_ID', 3)), 'delete_no_action_expression_child_lookup_index'),
    'implemented_pages' => array_values(array_filter(
        range(327, 334),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next327-334 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(327, 334)) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next327-334 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next327-334 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
