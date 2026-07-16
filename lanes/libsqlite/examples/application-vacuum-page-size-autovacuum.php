<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteVacuumPageSizeAutoVacuumPlan.php';

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVacuumPageSizeAutoVacuumPlan;

$pageSize = 1024;
$first = str_repeat("\0", $pageSize);
$first = substr_replace($first, "SQLite format 3\0", 0, 16);
$first = substr_replace($first, pack('n', $pageSize), 16, 2);
$first[18] = "\x01";
$first[19] = "\x01";
$first[20] = "\x00";
$first[21] = "\x40";
$first[22] = "\x20";
$first[23] = "\x20";
$first = substr_replace($first, pack('N', 17), 24, 4);
$first = substr_replace($first, pack('N', 3), 28, 4);
$first = substr_replace($first, pack('N', 1), 40, 4);
$first = substr_replace($first, pack('N', 1), 56, 4);

$database = SQLiteDatabase::fromBytes(
    $first
    . str_pad('wp_options copied settings page', $pageSize, 'O')
    . str_pad('wp_postmeta copied settings page', $pageSize, 'M')
);

$plan = SQLiteVacuumPageSizeAutoVacuumPlan::plan($database, 4096, 'incremental');

echo json_encode([
    'scenario' => 'application-vacuum-page-size-autovacuum',
    'applicationUse' => 'Preview a copied Application SQLite database VACUUM that applies pending page_size and auto_vacuum settings before import on hosts without ext-sqlite.',
    'sourcePageSize' => $plan['source_page_size'],
    'targetPageSize' => $plan['target_page_size'],
    'sourcePageCount' => $plan['source_page_count'],
    'targetPageCount' => $plan['target_page_count'],
    'targetAutoVacuum' => $plan['target_auto_vacuum'],
    'incrementalVacuum' => $plan['incremental_vacuum'],
    'largestRootPage' => $plan['largest_root_page'],
    'operations' => array_column($plan['operations'], 'op'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
