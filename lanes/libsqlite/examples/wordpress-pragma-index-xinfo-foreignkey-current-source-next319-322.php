<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenarioCount = static function (array $records, string $status): int {
    return count(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311($records, 'next', $status));
};

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next319-322',
    'wordpressUse' => 'WordPress import previews can flag action-column-specific RESTRICT and NO ACTION child lookup blockers before replaying PRAGMA foreign_key_list output.',
    'next319_update_restrict_without_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_319', 'wp_posts_319', 2, 'CREATE TABLE wp_posts_319(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_319', 'wp_comments_319', 3, 'CREATE TABLE wp_comments_319(comment_post_ID INTEGER REFERENCES wp_posts_319(ID) ON UPDATE RESTRICT)', 2),
    ], 'update_restrict_without_child_lookup_index'),
    'next320_delete_restrict_without_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_320', 'wp_posts_320', 2, 'CREATE TABLE wp_posts_320(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_320', 'wp_comments_320', 3, 'CREATE TABLE wp_comments_320(comment_post_ID INTEGER REFERENCES wp_posts_320(ID) ON DELETE RESTRICT)', 2),
    ], 'delete_restrict_without_child_lookup_index'),
    'next321_update_no_action_without_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_321', 'wp_posts_321', 2, 'CREATE TABLE wp_posts_321(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_321', 'wp_comments_321', 3, 'CREATE TABLE wp_comments_321(comment_post_ID INTEGER REFERENCES wp_posts_321(ID) ON UPDATE NO ACTION)', 2),
    ], 'update_no_action_without_child_lookup_index'),
    'next322_delete_no_action_without_child_lookup_index' => $scenarioCount([
        $record('table', 'wp_posts_322', 'wp_posts_322', 2, 'CREATE TABLE wp_posts_322(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_comments_322', 'wp_comments_322', 3, 'CREATE TABLE wp_comments_322(comment_post_ID INTEGER REFERENCES wp_posts_322(ID) ON DELETE NO ACTION)', 2),
    ], 'delete_no_action_without_child_lookup_index'),
    'implemented_pages' => array_values(array_filter(
        range(319, 322),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach (array_keys($summary) as $key) {
        if (str_starts_with($key, 'next') && $summary[$key] !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next319-322 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== [319, 320, 321, 322]) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next319-322 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next319-322 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
