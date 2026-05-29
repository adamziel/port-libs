<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario = static function (int $slice, string $updateAction, string $deleteAction, string $indexKind, bool $validLookup) use ($record): array {
    $childColumns = $indexKind === 'collation' ? 'post_title' : 'site_id, post_id';
    $parentColumns = $indexKind === 'collation' ? 'title' : 'site_id, id';
    $childColumnSql = $indexKind === 'collation'
        ? 'post_title TEXT COLLATE nocase DEFAULT ""'
        : 'site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0';
    $parentColumnSql = $indexKind === 'collation'
        ? 'title TEXT COLLATE nocase PRIMARY KEY'
        : 'site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id)';
    $indexSql = match (true) {
        $validLookup && $indexKind === 'collation' => "CREATE INDEX wp_comments_{$slice}_fk_ok ON wp_comments_{$slice}(post_title)",
        $validLookup => "CREATE INDEX wp_comments_{$slice}_fk_ok ON wp_comments_{$slice}(site_id, post_id)",
        $indexKind === 'order' => "CREATE INDEX wp_comments_{$slice}_fk_order ON wp_comments_{$slice}(post_id, site_id)",
        $indexKind === 'collation' => "CREATE INDEX wp_comments_{$slice}_fk_collation ON wp_comments_{$slice}(post_title COLLATE rtrim)",
        default => "CREATE INDEX wp_comments_{$slice}_fk_desc ON wp_comments_{$slice}(site_id DESC, post_id)",
    };
    $indexName = match (true) {
        $validLookup => "wp_comments_{$slice}_fk_ok",
        $indexKind === 'order' => "wp_comments_{$slice}_fk_order",
        $indexKind === 'collation' => "wp_comments_{$slice}_fk_collation",
        default => "wp_comments_{$slice}_fk_desc",
    };

    return [
        $record('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}({$parentColumnSql})", 1),
        $record('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}({$childColumnSql}, FOREIGN KEY({$childColumns}) REFERENCES wp_posts_{$slice}({$parentColumns}) ON UPDATE {$updateAction} ON DELETE {$deleteAction})", 2),
        $record('index', $indexName, "wp_comments_{$slice}", 4, $indexSql, 3),
    ];
};

$cases = [
    879 => ['CASCADE', 'RESTRICT', 'order', 'update_cascade_order_mismatch_child_lookup_index'],
    880 => ['CASCADE', 'RESTRICT', 'order', 'delete_restrict_order_mismatch_child_lookup_index'],
    881 => ['CASCADE', 'RESTRICT', 'collation', 'update_cascade_collation_mismatch_child_lookup_index'],
    882 => ['CASCADE', 'RESTRICT', 'collation', 'delete_restrict_collation_mismatch_child_lookup_index'],
    883 => ['NO ACTION', 'SET NULL', 'order', 'update_no_action_order_mismatch_child_lookup_index'],
    884 => ['NO ACTION', 'SET NULL', 'order', 'delete_set_null_order_mismatch_child_lookup_index'],
    885 => ['NO ACTION', 'SET NULL', 'collation', 'update_no_action_collation_mismatch_child_lookup_index'],
    886 => ['NO ACTION', 'SET NULL', 'collation', 'delete_set_null_collation_mismatch_child_lookup_index'],
    887 => ['SET DEFAULT', 'CASCADE', 'order', 'update_set_default_order_mismatch_child_lookup_index'],
    888 => ['SET DEFAULT', 'CASCADE', 'order', 'delete_cascade_order_mismatch_child_lookup_index'],
    889 => ['SET DEFAULT', 'CASCADE', 'collation', 'update_set_default_collation_mismatch_child_lookup_index'],
    890 => ['SET DEFAULT', 'CASCADE', 'collation', 'delete_cascade_collation_mismatch_child_lookup_index'],
    891 => ['RESTRICT', 'NO ACTION', 'desc', 'update_restrict_desc_mismatch_child_lookup_index'],
    892 => ['RESTRICT', 'NO ACTION', 'desc', 'delete_no_action_desc_mismatch_child_lookup_index'],
    893 => ['SET NULL', 'SET DEFAULT', 'desc', 'update_set_null_desc_mismatch_child_lookup_index'],
    894 => ['SET NULL', 'SET DEFAULT', 'desc', 'delete_set_default_desc_mismatch_child_lookup_index'],
];

$summary = [
    'scenario' => 'wordpress-pragma-index-xinfo-foreignkey-current-source-next879-894',
    'wordpressUse' => 'WordPress import previews can page the staged next879-894 foreign-key action relationship diagnostics after next863-878 while preserving order, collation, and DESC child lookup mismatch coverage.',
    'implemented_pages' => array_values(array_filter(
        range(879, 894),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

foreach ($cases as $slice => [$updateAction, $deleteAction, $indexKind, $status]) {
    $currentRows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311(
        $scenario($slice, $updateAction, $deleteAction, $indexKind, true),
        'current',
        $status,
    );
    $nextRows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311(
        $scenario($slice, $updateAction, $deleteAction, $indexKind, false),
        'next',
        $status,
    );
    $summary["next{$slice}_current_{$status}"] = count($currentRows);
    $summary["next{$slice}_next_{$status}"] = count($nextRows);
}

if (($argv[1] ?? null) === '--self-test') {
    foreach ($cases as $slice => [, , , $status]) {
        if (($summary["next{$slice}_current_{$status}"] ?? null) !== 0 || ($summary["next{$slice}_next_{$status}"] ?? null) !== 1) {
            fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next879-894 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(879, 894)) {
        fwrite(STDERR, "wordpress-pragma-index-xinfo-foreignkey-current-source-next879-894 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-xinfo-foreignkey-current-source-next879-894 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
