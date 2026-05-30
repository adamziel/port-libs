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

$state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
$sequence = $state->transactionFileControlSequence([
    ['op' => 'write_hint', 'value' => 16384],
    ['op' => 'begin_atomic_write'],
    ['op' => 'overwrite', 'value' => 1],
    ['op' => 'overwrite', 'value' => 4],
    ['op' => 'sync', 'value' => 'full|dataonly'],
    ['op' => 'commit_atomic_write'],
    ['op' => 'commit_phase_two'],
]);

echo json_encode([
    'status' => $sequence['status'],
    'operations' => $sequence['count'],
    'write_hint_bytes' => $sequence['controls']['write_hint_bytes'],
    'overwrite_pages' => $sequence['controls']['overwrite_pages'],
    'last_sync_flags' => $sequence['controls']['last_sync_flags'],
    'sync_count' => $sequence['controls']['sync_count'],
    'atomic_write_generation' => $sequence['controls']['atomic_write_generation'],
    'commit_phase_two_count' => $sequence['controls']['commit_phase_two_count'],
    'dependencies' => $sequence['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
