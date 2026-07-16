<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$scenario = static function (int $slice, string $actionColumn, string $action) use ($record): array {
    $clause = $actionColumn === 'update' ? "ON UPDATE {$action}" : "ON DELETE {$action}";
    if ($slice === 381) {
        $clause = 'ON UPDATE SET NULL ON DELETE NO ACTION';
    }
    if ($slice === 382) {
        $clause = 'ON UPDATE NO ACTION ON DELETE SET DEFAULT';
    }

    return [
        $record('table', "wp_posts_{$slice}", "wp_posts_{$slice}", 2, "CREATE TABLE wp_posts_{$slice}(site_id INTEGER, id INTEGER, PRIMARY KEY(site_id, id))", 1),
        $record('table', "wp_comments_{$slice}", "wp_comments_{$slice}", 3, "CREATE TABLE wp_comments_{$slice}(site_id INTEGER DEFAULT 0, post_id INTEGER DEFAULT 0, FOREIGN KEY(site_id, post_id) REFERENCES wp_posts_{$slice}(site_id, id) {$clause})", 2),
        $record('index', "wp_comments_{$slice}_fk_desc", "wp_comments_{$slice}", 4, "CREATE INDEX wp_comments_{$slice}_fk_desc ON wp_comments_{$slice}(site_id DESC, post_id)", 3),
    ];
};

$cases = [
    375 => ['update', 'SET NULL', 'update_set_null_desc_mismatch_child_lookup_index'],
    376 => ['delete', 'SET NULL', 'delete_set_null_desc_mismatch_child_lookup_index'],
    377 => ['update', 'SET DEFAULT', 'update_set_default_desc_mismatch_child_lookup_index'],
    378 => ['delete', 'SET DEFAULT', 'delete_set_default_desc_mismatch_child_lookup_index'],
    379 => ['update', 'NO ACTION', 'update_no_action_desc_mismatch_child_lookup_index'],
    380 => ['delete', 'NO ACTION', 'delete_no_action_desc_mismatch_child_lookup_index'],
    381 => ['update', 'SET NULL', 'update_set_null_desc_mismatch_child_lookup_index'],
    382 => ['delete', 'SET DEFAULT', 'delete_set_default_desc_mismatch_child_lookup_index'],
];

$summary = [
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next375-382',
    'applicationUse' => 'Application import previews can flag descending child lookup indexes before SET NULL, SET DEFAULT, and NO ACTION foreign-key actions are trusted from PRAGMA index_xinfo.',
    'implemented_pages' => array_values(array_filter(
        range(375, 382),
        static fn (int $slice): bool => method_exists(SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::class, 'page' . $slice),
    )),
];

foreach ($cases as $slice => [$actionColumn, $action, $status]) {
    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311(
        $scenario($slice, $actionColumn, $action),
        'next',
        $status,
    );
    $summary["next{$slice}_{$status}"] = count($rows);
}

if (($argv[1] ?? null) === '--self-test') {
    foreach ($cases as $slice => [, , $status]) {
        if (($summary["next{$slice}_{$status}"] ?? null) !== 1) {
            fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next375-382 self-test failed\n");
            exit(1);
        }
    }
    if ($summary['implemented_pages'] !== range(375, 382)) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next375-382 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next375-382 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
