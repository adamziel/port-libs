<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCapabilityPlan;
use PortLibs\LibSqlite\SQLiteVfsFileControlState;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$capability = SQLiteVfsCapabilityPlan::forFilename(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
    true,
    true,
    4096,
    ['safe_append', 'powersafe_overwrite'],
    'full',
    false,
    8192,
    0
);

$state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
$applied = $state->applyMany([
    'persist_wal' => true,
    'chunk_size' => 32768,
    'mmap_size' => 65536,
    ['op' => 'name_hint', 'value' => 'wp-options-import'],
    ['op' => 'size_hint', 'value' => 131072],
]);

$archive = SQLiteVfsFileControlState::fromCapabilityPlan(SQLiteVfsCapabilityPlan::forFilename(
    'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1&vfs=unix-none',
    true,
    false,
    512,
    [],
    'normal',
    true,
    null,
    1048576
));
$archiveMmap = $archive->apply('mmap_size', 1048576);

echo json_encode([
    'scenario' => 'application-vfs-file-control-state',
    'applicationUse' => 'Apply SQLite xFileControl-style handle state for copied wp_options imports, including persist-WAL, chunk-size, mmap-size, name-hint, size-hint, and immutable archive guards without requiring ext/sqlite.',
    'path' => $capability['path'],
    'applied' => $applied['applied'],
    'changed' => $applied['changed'],
    'persistWal' => $applied['controls']['persist_wal'],
    'chunkSize' => $applied['controls']['chunk_size'],
    'mmapSize' => $applied['controls']['mmap_size'],
    'nameHint' => $applied['controls']['name_hint'],
    'archiveMmapStatus' => $archiveMmap['status'],
    'archiveMmapReason' => $archiveMmap['reason'],
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
