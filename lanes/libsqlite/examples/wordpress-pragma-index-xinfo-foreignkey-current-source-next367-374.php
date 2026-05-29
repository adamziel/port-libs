<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario = static function (int $slice, string $actionColumn, string $action, string $indexKind) use ($record): array {
    $childColumns = $indexKind === 'collation' ? 'post_title' : 'site_id, post_id';
    $parentColumns = $indexKind === 'collation' ? 'title' : 'site_id, id';
    $childColumnSql = $indexKind === 'collation'
        ? 'post_title TEXT COLLATE nocase DEFAULT ""'
        : 'site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0';
    $parentColumnSql = $indexKind === 'collation'
        ? 'title TEXT COLLATE nocase PRIMARY KEY'
        : 'site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id)';
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    $indexSql = match ($indexKind) {
        'order' => "CREATE INDEX wp_comments_{$slice}_fk_order ON wp_comments_{$slice}(post_id, site_id)",
        'collation' => "CREATE INDEX wp_comments_{$slice}_fk_collation ON wp_comments_{$slice}(post_title COLLATE rtrim)",
        default => "CREATE INDEX wp_comments_{$slice}_fk_desc ON wp_comments_{$slice}(site_id DESC, post_id)",
    };
    $indexName = match ($indexKind) {
        'order' => "wp_comments_{$slice}_fk_order",
        'collation' => "wp_comments_{$slice}_fk_collation",
        default => "wp_comments_{$slice}_fk_desc",
    };

    return [
        $record('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}({$parentColumnSql})", 1),
        $record('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}({$childColumnSql}, FOREIGN KEY({$childColumns}) REFERENCES wp_posts_{$slice}({$parentColumns}) {$clause})", 2),
        $record('index', $indexName, "wp_comments_{$slice}", 4, $indexSql, 3),
    ];
};

$cases = [
    367 => ['update', 'NO ACTION', 'order', 'update_no_action_order_mismatch_child_lookup_index'],
    368 => ['delete', 'NO ACTION', 'order', 'delete_no_action_order_mismatch_child_lookup_index'],
    369 => ['update', 'NO ACTION', 'collation', 'update_no_action_collation_mismatch_child_lookup_index'],
    370 => ['delete', 'NO ACTION', 'collation', 'delete_no_action_collation_mismatch_child_lookup_index'],
    371 => ['update', 'CASCADE', 'desc', 'update_cascade_desc_mismatch_child_lookup_index'],
    372 => ['delete', 'CASCADE', 'desc', 'delete_cascade_desc_mismatch_child_lookup_index'],
    373 => ['update', 'RESTRICT', 'desc', 'update_restrict_desc_mismatch_child_lookup_index'],
    374 => ['delete', 'RESTRICT', 'desc', 'delete_restrict_desc_mismatch_child_lookup_index'],
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next367-374',
    'wordpressUse' => 'WordPress import previews can flag NO ACTION lookup-index order/collation mismatches and CASCADE/RESTRICT descending-key lookup indexes through PRAGMA index_xinfo before foreign-key actions are trusted.',
    'implemented_pages' => array_values(array_filter(
        range(367, 374),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

foreach ($cases as $slice => [$actionColumn, $action, $indexKind, $status]) {
    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311(
        $scenario($slice, $actionColumn, $action, $indexKind),
        'next',
        $status,
    );
    $summary["next{$slice}_{$status}"] = count($rows);
}

if (($argv[1] ?? null) === '--self-test') {
    foreach ($cases as $slice => [, , , $status]) {
        if (($summary["next{$slice}_{$status}"] ?? null) !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next367-374 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(367, 374)) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next367-374 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next367-374 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
