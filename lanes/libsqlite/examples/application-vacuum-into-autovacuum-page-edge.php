<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteVacuumPageSizeAutoVacuumPlan.php';
require_once __DIR__ . '/../src/SQLiteVacuumBackupSerializePlan.php';

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVacuumBackupSerializePlan;

$pageSize = 512;
$pageCount = 106;
$first = str_repeat("\0", $pageSize);
$first = substr_replace($first, "SQLite format 3\0", 0, 16);
$first = substr_replace($first, pack('n', $pageSize), 16, 2);
$first[18] = "\x01";
$first[19] = "\x01";
$first[20] = "\x00";
$first[21] = "\x40";
$first[22] = "\x20";
$first[23] = "\x20";
$first = substr_replace($first, pack('N', 31), 24, 4);
$first = substr_replace($first, pack('N', $pageCount), 28, 4);
$first = substr_replace($first, pack('N', 1), 40, 4);
$first = substr_replace($first, pack('N', 1), 56, 4);

$pages = [$first];
for ($page = 2; $page <= $pageCount; $page++) {
    $pages[] = str_pad("wp_options-vacuum-into-page-{$page};", $pageSize, chr(65 + ($page % 26)));
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$target = sys_get_temp_dir() . '/application-vacuum-into-autovacuum-edge.sqlite';
@unlink($target);

$plan = SQLiteVacuumBackupSerializePlan::vacuumInto($database, $target, false, 512, 'incremental');

echo json_encode([
    'scenario' => 'application-vacuum-into-autovacuum-page-edge',
    'applicationUse' => 'Preview VACUUM INTO for a copied Application SQLite database where enabling incremental auto_vacuum creates pointer-map pages at page 2 and the next 512-byte stride edge without ext/sqlite.',
    'targetPath' => $plan['target_path'],
    'pageSize' => $plan['page_size'],
    'pageCount' => $plan['page_count'],
    'targetAutoVacuum' => $plan['target_auto_vacuum'],
    'incrementalVacuum' => $plan['incremental_vacuum'],
    'largestRootPage' => $plan['largest_root_page'],
    'pointerMapPages' => $plan['pointer_map_page_numbers'],
    'pointerMapEntryPageCount' => count($plan['pointer_map_entry_page_numbers']),
    'operations' => array_column($plan['operations'], 'op'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
