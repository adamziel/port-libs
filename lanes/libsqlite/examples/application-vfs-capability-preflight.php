<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsCapabilityPlan;

$plans = [
    'writableMainDatabase' => SQLiteVfsCapabilityPlan::forFilename(
        'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix&psow=0',
        true,
        true,
        4096,
        ['safe_append', 'sequential', 'powersafe_overwrite'],
        'full',
        true,
        65536,
        1048576
    ),
    'immutableArchive' => SQLiteVfsCapabilityPlan::forFilename(
        'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1&vfs=unix-none',
        true,
        false,
        512,
        ['safe_append', 'powersafe_overwrite'],
        'normal',
        true,
        null,
        2097152
    ),
    'memoryImportScratch' => SQLiteVfsCapabilityPlan::forFilename(
        'file::memory:?mode=memory',
        false,
        false,
        1024,
        ['atomic1k'],
        'off',
        false,
        null,
        null
    ),
];

echo json_encode([
    'scenario' => 'application-vfs-capability-preflight',
    'applicationUse' => 'Resolve bounded SQLite VFS file-control and device-capability decisions for copied Application databases before enabling native writes, WAL persistence, mmap previews, chunked growth, and rollback-journal sync policy.',
    'plans' => array_map(static fn (array $plan): array => [
        'status' => $plan['status'],
        'path' => $plan['path'],
        'vfs' => $plan['vfs'],
        'readOnly' => $plan['read_only'],
        'immutable' => $plan['immutable'],
        'sectorSize' => $plan['sector_size'],
        'deviceFlags' => $plan['device_flags'],
        'syncMode' => $plan['sync_mode'],
        'requiresFullSync' => $plan['requires_full_sync'],
        'requiresDirectorySync' => $plan['requires_directory_sync'],
        'usesPowersafeOverwrite' => $plan['uses_powersafe_overwrite'],
        'journalHeaderPadding' => $plan['journal_header_padding'],
        'persistWal' => $plan['persist_wal'],
        'chunkSize' => $plan['chunk_size'],
        'mmapAllowed' => $plan['mmap_allowed'],
        'mmapSize' => $plan['file_controls']['mmap_size'],
        'dependencies' => $plan['dependencies'],
    ], $plans),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
