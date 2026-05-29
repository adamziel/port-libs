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
$capability['file_controls']['size_limit'] = 8388608;
$capability['file_controls']['data_version'] = 19;

$state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
$sequence = $state->sqlFileControlSequence([
    "file_control(name_hint, 'wp-options-import')",
    'PRAGMA busy_timeout=2500',
    'PRAGMA reserve_bytes=32',
    'PRAGMA mmap_size=262144',
    'file_control(size_hint, 4194304)',
    'PRAGMA data_version',
]);

echo json_encode([
    'status' => $sequence['status'],
    'operations' => $sequence['count'],
    'applied' => $sequence['applied'],
    'ignored' => $sequence['ignored'],
    'changed' => $sequence['changed'],
    'lock_timeout' => $sequence['controls']['lock_timeout'],
    'reserve_bytes' => $sequence['controls']['reserve_bytes'],
    'mmap_size' => $sequence['controls']['mmap_size'],
    'data_version' => $sequence['pairs'][5]['result']['value'],
    'size_hint_status' => $sequence['pairs'][4]['result']['status'],
    'dependencies' => $sequence['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
