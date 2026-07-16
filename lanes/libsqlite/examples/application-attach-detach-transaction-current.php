<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachDetachTransactionPlan;

$schemas = [
    'main' => [
        'file' => '/srv/wp/current.sqlite',
        'journal_mode' => 'wal',
    ],
    'import' => [
        'file' => '/srv/wp/import-copy.sqlite',
        'journal_mode' => 'wal',
        'lock' => 'shared',
    ],
    'archive' => [
        'file' => '/srv/wp/archive.sqlite',
        'journal_mode' => 'delete',
        'dirty_pages' => [2, 7],
        'savepoint_depth' => 1,
        'lock' => 'reserved',
    ],
];

$importDetach = SQLiteAttachDetachTransactionPlan::currentNext($schemas, 'import');
$archiveDetach = SQLiteAttachDetachTransactionPlan::currentNext($schemas, 'archive');

$summary = [
    'importStatus' => $importDetach['status'],
    'importSidecarCleanup' => $importDetach['sidecar_cleanup'],
    'importNextDatabases' => array_column($importDetach['next_database_list'], 'name'),
    'archiveStatus' => $archiveDetach['status'],
    'archiveBlockedReasons' => $archiveDetach['blocked_reasons'],
    'archiveError' => $archiveDetach['sqlite_error'],
    'dependencies' => $importDetach['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['importStatus'] !== 'detached' || $summary['importSidecarCleanup'] !== ['import-wal', 'import-shm']) {
        fwrite(STDERR, "unexpected import DETACH summary\n");
        exit(1);
    }
    if ($summary['archiveStatus'] !== 'blocked' || !in_array('open_savepoint', $summary['archiveBlockedReasons'], true)) {
        fwrite(STDERR, "dirty archive DETACH was not blocked\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
