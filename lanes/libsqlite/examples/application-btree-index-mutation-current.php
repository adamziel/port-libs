<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeIndexMutationCurrent;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

$cell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));

$page = SQLiteIndexLeafPage::assemble([
    $cell(['_site_transient_update_plugins', 7]),
    $cell(['active_plugins', 2]),
    $cell(['blog_public', 3]),
    $cell(['stylesheet', 4]),
]);

$result = SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
    $page,
    ['active_plugins', 2],
    ['autoload', 2],
    secureDelete: true,
);

if (in_array('--self-test', $argv, true)) {
    if ($result['mutation_applied'] !== true) {
        fwrite(STDERR, "index mutation was not applied\n");
        exit(1);
    }
    if ($result['inserted_cell_offset'] !== $result['reused_freeblock_offset']) {
        fwrite(STDERR, "replacement cell did not reuse the deleted freeblock\n");
        exit(1);
    }
    if ($result['after_insert']['integrity_status'] !== 'ok') {
        fwrite(STDERR, "mutated index leaf freeblock integrity failed\n");
        exit(1);
    }
}

echo json_encode([
    'status' => $result['mutation_applied'] ? 'ok' : 'blocked',
    'deleted' => $result['deleted_record_values'],
    'inserted' => $result['inserted_record_values'],
    'reused_freeblock_offset' => $result['reused_freeblock_offset'],
    'inserted_cell_offset' => $result['inserted_cell_offset'],
    'cell_count' => $result['after_insert']['cell_count'],
    'freeblock_integrity' => $result['after_insert']['integrity_status'],
], JSON_PRETTY_PRINT) . PHP_EOL;
