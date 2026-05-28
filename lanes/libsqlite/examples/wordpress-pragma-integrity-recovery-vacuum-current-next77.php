<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaIntegrityRecoveryVacuumYield;

$integrityRows = [
    [
        'kind' => 'integrity_check',
        'source' => 'freelist',
        'page' => 17,
        'pointer_map_page' => 2,
        'message' => 'freelist trunk chain loops at page 17',
    ],
    [
        'kind' => 'integrity_check',
        'source' => 'pointer_map',
        'page' => 40,
        'pointer_map_page' => 2,
        'message' => 'pointer-map parent page 0 for btree-page page 40 is not valid',
    ],
    [
        'kind' => 'integrity_check',
        'source' => 'btree',
        'page' => 55,
        'pointer_map_page' => 2,
        'message' => 'btree page 55 cell content area is corrupt',
    ],
];

$plan = SQLitePragmaIntegrityRecoveryVacuumYield::page(
    'not-a-sqlite-database',
    0,
    77,
    'PRAGMA integrity_check',
    $integrityRows,
);

echo json_encode([
    'status' => $plan['status'],
    'ready_for_vacuum' => $plan['next']['ready_for_vacuum'],
    'blocking' => $plan['next']['blocking'],
    'actions' => $plan['next']['actions'],
    'first_recovery' => $plan['rows'][0]['recovery'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
