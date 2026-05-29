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

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next323-326',
    'wordpressUse' => 'WordPress import previews can flag RESTRICT and NO ACTION child lookup indexes that are visible through PRAGMA index_xinfo but partial, so FK action probes cannot rely on them for every child row.',
    'next323_update_restrict_partial_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_323', 'wp_posts_323', 2, 'CREATE TABLE wp_posts_323(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_323', 'wp_comments_323', 3, 'CREATE TABLE wp_comments_323(comment_post_ID INTEGER REFERENCES wp_posts_323(ID) ON UPDATE RESTRICT)', 2),
        $partialIndex('wp_comments_323_post_partial', 'wp_comments_323', 'comment_post_ID', 3),
    ], 'update_restrict_partial_child_lookup_index'),
    'next324_delete_restrict_partial_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_324', 'wp_posts_324', 2, 'CREATE TABLE wp_posts_324(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_324', 'wp_comments_324', 3, 'CREATE TABLE wp_comments_324(comment_post_ID INTEGER REFERENCES wp_posts_324(ID) ON DELETE RESTRICT)', 2),
        $partialIndex('wp_comments_324_post_partial', 'wp_comments_324', 'comment_post_ID', 3),
    ], 'delete_restrict_partial_child_lookup_index'),
    'next325_update_no_action_partial_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_325', 'wp_posts_325', 2, 'CREATE TABLE wp_posts_325(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_325', 'wp_comments_325', 3, 'CREATE TABLE wp_comments_325(comment_post_ID INTEGER REFERENCES wp_posts_325(ID) ON UPDATE NO ACTION)', 2),
        $partialIndex('wp_comments_325_post_partial', 'wp_comments_325', 'comment_post_ID', 3),
    ], 'update_no_action_partial_child_lookup_index'),
    'next326_delete_no_action_partial_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_326', 'wp_posts_326', 2, 'CREATE TABLE wp_posts_326(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_326', 'wp_comments_326', 3, 'CREATE TABLE wp_comments_326(comment_post_ID INTEGER REFERENCES wp_posts_326(ID) ON DELETE NO ACTION)', 2),
        $partialIndex('wp_comments_326_post_partial', 'wp_comments_326', 'comment_post_ID', 3),
    ], 'delete_no_action_partial_child_lookup_index'),
    'implemented_pages' => array_values(array_filter(
        range(323, 326),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next323-326 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [323, 324, 325, 326]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next323-326 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next323-326 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
