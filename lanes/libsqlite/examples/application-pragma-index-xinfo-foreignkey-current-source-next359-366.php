<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario = static function (int $slice, string $actionColumn, string $action, string $indexKind) use ($record): array {
    $childColumns = $indexKind === 'order' ? 'site_id, post_id' : 'post_title';
    $parentColumns = $indexKind === 'order' ? 'site_id, id' : 'title';
    $childColumnSql = $indexKind === 'order'
        ? 'site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0'
        : 'post_title TEXT COLLATE nocase DEFAULT ""';
    $parentColumnSql = $indexKind === 'order'
        ? 'site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id)'
        : 'title TEXT COLLATE nocase PRIMARY KEY';
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    $indexSql = $indexKind === 'order'
        ? "CREATE INDEX wp_comments_{$slice}_fk_order ON wp_comments_{$slice}(post_id, site_id)"
        : "CREATE INDEX wp_comments_{$slice}_fk_collation ON wp_comments_{$slice}(post_title COLLATE rtrim)";

    return [
        $record('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}({$parentColumnSql})", 1),
        $record('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}({$childColumnSql}, FOREIGN KEY({$childColumns}) REFERENCES wp_posts_{$slice}({$parentColumns}) {$clause})", 2),
        $record('index', $indexKind === 'order' ? "wp_comments_{$slice}_fk_order" : "wp_comments_{$slice}_fk_collation", "wp_comments_{$slice}", 4, $indexSql, 3),
    ];
};

$cases = [
    359 => ['update', 'CASCADE', 'order', 'update_cascade_order_mismatch_child_lookup_index'],
    360 => ['delete', 'CASCADE', 'order', 'delete_cascade_order_mismatch_child_lookup_index'],
    361 => ['update', 'CASCADE', 'collation', 'update_cascade_collation_mismatch_child_lookup_index'],
    362 => ['delete', 'CASCADE', 'collation', 'delete_cascade_collation_mismatch_child_lookup_index'],
    363 => ['update', 'RESTRICT', 'order', 'update_restrict_order_mismatch_child_lookup_index'],
    364 => ['delete', 'RESTRICT', 'order', 'delete_restrict_order_mismatch_child_lookup_index'],
    365 => ['update', 'RESTRICT', 'collation', 'update_restrict_collation_mismatch_child_lookup_index'],
    366 => ['delete', 'RESTRICT', 'collation', 'delete_restrict_collation_mismatch_child_lookup_index'],
];

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next359-366',
    'applicationUse' => 'Application import previews can flag CASCADE and RESTRICT foreign-key actions whose child lookup indexes are present but unusable because PRAGMA index_xinfo exposes key-order or collation mismatches.',
    'implemented_pages' => array_values(array_filter(
        range(359, 366),
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
            fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next359-366 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(359, 366)) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next359-366 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next359-366 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
