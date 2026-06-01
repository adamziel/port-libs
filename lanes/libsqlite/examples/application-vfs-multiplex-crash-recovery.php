<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$profile = SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(7, [
    'aux_name' => 'app_aux.db',
    'main_name' => 'app_main.db',
    'row_count' => 1000,
]);

if (($argv[1] ?? null) === '--self-test') {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'crashM.test');
    assert($profile['main_integrity_check'] === 'ok');
    assert($profile['aux_integrity_check'] === 'ok');
    assert($profile['transaction_atomic_across_attached_databases'] === true);
    assert($profile['database_image_stable_after_recovery'] === true);

    echo "application-vfs-multiplex-crash-recovery self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-vfs-multiplex-crash-recovery',
    'applicationUse' => 'Recover an attached pair of multiplexed 8.3-name SQLite database images after a crash during a cross-database update transaction.',
    'source' => $profile['script'],
    'upstream' => $profile['upstream'],
    'status' => $profile['status'],
    'mainIntegrity' => $profile['main_integrity_check'],
    'auxIntegrity' => $profile['aux_integrity_check'],
    'chunkCountPerDatabase' => $profile['chunk_count_per_database'],
    'rollbackJournals' => $profile['rollback_journal_files'],
    'masterJournal' => $profile['master_journal_file'],
    'dependencies' => $profile['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
