<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVacuumBackupSerializePlan;

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 40, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$databaseBytes = $firstPage
    . str_pad('wp_options root page with copied siteurl/home rows', $pageSize, 'R')
    . str_pad('wp_options backup page with serialized autoload options', $pageSize, 'B');

$database = SQLiteDatabase::fromBytes($databaseBytes);
$serialized = SQLiteVacuumBackupSerializePlan::serialize($database);
$deserialized = SQLiteVacuumBackupSerializePlan::deserialize($serialized['bytes'], 'main', true);
$backup = SQLiteVacuumBackupSerializePlan::backup($deserialized['database'], 'backup', 'main', 2);
$vacuum = SQLiteVacuumBackupSerializePlan::vacuumInto($database, 'application-copy-vacuum.sqlite');

echo json_encode([
    'scenario' => 'application-vacuum-backup-serialize',
    'applicationUse' => 'Serialize a copied wp_options SQLite database image, reopen it read-only, run a bounded backup page step, and plan a VACUUM INTO maintenance copy without requiring ext/sqlite.',
    'serializedBytes' => strlen($serialized['bytes']),
    'deserializedReadOnly' => $deserialized['readonly'],
    'backupStatus' => $backup['status'],
    'backupRemainingPages' => $backup['remaining'],
    'vacuumOperations' => array_column($vacuum['operations'], 'op'),
    'dependencies' => array_values(array_unique(array_merge(
        $serialized['dependencies'],
        $deserialized['dependencies'],
        $backup['dependencies'],
        $vacuum['dependencies'],
    ))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
