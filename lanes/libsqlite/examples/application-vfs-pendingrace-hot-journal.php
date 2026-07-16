<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$profile = SQLiteVfsIoDynamicPlan::pendingHotJournalRollbackRaceProfile(
    pageCount: 20,
    cacheSize: 5,
    rowCount: 10,
    payloadBytes: 100,
    peerUnlockFailAt: 1
);

if (($argv[1] ?? null) === '--self-test') {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'pendingrace.test');
    assert($profile['peer_pending_lock_retained'] === true);
    assert($profile['primary_integrity_check'] === [1, 'database is locked']);
    assert(in_array('upstream-pendingrace-hot-journal-lock', $profile['dependencies'], true));

    echo "application-vfs-pendingrace-hot-journal self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-vfs-pendingrace-hot-journal',
    'applicationUse' => 'Model a hot rollback-journal recovery race where a failed peer unlock keeps a pending lock and the primary integrity check reports database is locked.',
    'source' => $profile['script'],
    'upstream' => $profile['upstream'],
    'status' => $profile['status'],
    'primaryResult' => $profile['primary_integrity_check'],
    'peerResultCode' => $profile['peer_result_code'],
    'pendingLockRetained' => $profile['peer_pending_lock_retained'],
    'hotJournalRollbackDeferred' => $profile['hot_journal_rollback_deferred'],
    'dependencies' => $profile['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
