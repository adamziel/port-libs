<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCapabilityPlan;
use PortLibs\LibSqlite\SQLiteVfsFileControlState;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$capability = SQLiteVfsCapabilityPlan::forFilename(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix',
    true,
    true,
    4096,
    ['safe_append', 'powersafe_overwrite'],
    'full',
    false,
    8192,
    0
);
$capability['file_controls']['size_limit'] = 1048576;
$capability['file_controls']['data_version'] = 12;

$state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
$sequence = $state->fileControlSnapshotSequence([
    ['op' => 'name_hint', 'value' => 'wp-options-bulk-import'],
    ['op' => 'lock_timeout', 'value' => 2500],
    ['op' => 'reserve_bytes', 'value' => 32],
    ['op' => 'size_limit', 'value' => 8388608],
    ['op' => 'data_version', 'value' => null],
    ['op' => 'tempfilename', 'value' => 'journal'],
]);

echo json_encode([
    'status' => $sequence['status'],
    'operations' => $sequence['count'],
    'size_limit' => $sequence['controls']['size_limit'],
    'reserve_bytes' => $sequence['controls']['reserve_bytes'],
    'lock_timeout' => $sequence['controls']['lock_timeout'],
    'data_version' => $sequence['controls']['data_version'],
    'tempfilename' => $sequence['pairs'][5]['result']['value'],
    'dependencies' => $sequence['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
