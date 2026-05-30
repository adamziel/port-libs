<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsSidecarPlan;

$database = 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix';
$archive = 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1&vfs=unix-none';
$nolock = 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&nolock=1&psow=0';

$plans = [
    'writableMainDatabase' => SQLiteVfsSidecarPlan::forFilename($database, true, true),
    'immutableArchive' => SQLiteVfsSidecarPlan::forFilename($archive, true, false),
    'nolockRepairPreview' => SQLiteVfsSidecarPlan::forFilename($nolock, true, true),
    'newCopyCreate' => SQLiteVfsSidecarPlan::forFilename('file:/srv/www/wp-content/database/import-copy.sqlite?mode=rwc', false, true),
];

echo json_encode([
    'scenario' => 'application-vfs-sidecar-preflight',
    'applicationUse' => 'Resolve SQLite main, WAL, SHM, rollback-journal, super-journal, and temp-file paths for copied Application databases before activating a full native VFS implementation.',
    'plans' => array_map(static fn (array $plan): array => [
        'status' => $plan['status'],
        'path' => $plan['path'],
        'walPath' => $plan['wal_path'],
        'shmPath' => $plan['shm_path'],
        'journalPath' => $plan['journal_path'],
        'superJournalGlob' => $plan['super_journal_glob'],
        'tempDirectory' => $plan['temp_directory'],
        'readOnly' => $plan['read_only'],
        'immutable' => $plan['immutable'],
        'nolock' => $plan['nolock'],
        'walReadable' => $plan['wal_readable'],
        'walWritable' => $plan['wal_writable'],
        'shmReadable' => $plan['shm_readable'],
        'shmWritable' => $plan['shm_writable'],
        'journalReadable' => $plan['journal_readable'],
        'journalWritable' => $plan['journal_writable'],
        'usesSharedMemory' => $plan['uses_shared_memory'],
        'dependencies' => $plan['dependencies'],
    ], $plans),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
