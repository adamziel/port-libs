<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pragma = new SQLitePragmaSchemaDataVersion([
    'main' => ['schema_version' => 34, 'data_version' => 10, 'change_counter' => 10],
    'temp' => ['schema_version' => 4, 'data_version' => 2, 'change_counter' => 2],
]);

$before = [
    'schema_version' => $pragma->execute('PRAGMA schema_version')['value'],
    'data_version' => $pragma->execute('PRAGMA data_version')['value'],
];

$localImportCommit = $pragma->recordLocalCommit('main', 2, 'same_connection_wp_options_import');
$externalWriter = $pragma->recordExternalCommit('main', 1, 'other_connection_wp_options_update');
$schemaChange = $pragma->recordSchemaChange('temp', 1, 'temp_import_schema_rebuild');
$observedHeader = $pragma->observeHeader('main', 36, 20, 'reopened_header_after_checkpoint');

echo json_encode([
    'scenario' => 'copied wp_options pragma schema_version/data_version current next34',
    'applicationUse' => 'Distinguish same-connection import writes from another connection changing a copied SQLite database while preserving schema-cookie and file change-counter preflight rows.',
    'before' => $before,
    'localImportCommit' => [
        'data_version' => $localImportCommit['value'],
        'changed' => $localImportCommit['changed'],
        'header' => $localImportCommit['header'],
    ],
    'externalWriter' => [
        'data_version' => $externalWriter['value'],
        'changed' => $externalWriter['changed'],
        'header' => $externalWriter['header'],
    ],
    'tempSchemaChange' => [
        'schema_version' => $schemaChange['value'],
        'header' => $schemaChange['header'],
    ],
    'observedHeader' => [
        'data_version' => $observedHeader['value'],
        'changed' => $observedHeader['changed'],
        'header' => $observedHeader['header'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
