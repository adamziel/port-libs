<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = hash('sha256', 'application next240 checkpointed wp_options database');
$cacheDigest = hash('sha256', 'application next240 clean checkpoint page cache');
$finalizerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next236',
    'next_writer_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-next240-source',
    'next_writer_generation' => 240,
    'schema_cookie' => 640,
    'database_digest' => $digest,
    'page_cache_digest' => $cacheDigest,
    'wal_index_salt' => ['wp-next240-salt-a', 'wp-next240-salt-b'],
    'wal_index_mx_frame' => 18,
    'checkpoint_frame' => 14,
    'finalized_statement_names' => ['select-active-plugins', 'select-autoload-options', 'select-option-index'],
    'operation_names' => ['admit_next_wal_writer_after_checkpoint_finalizers_next236'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236'],
];

$receipt = static function (string $name, array $statements, array $dirtyPages, array $frames) use ($finalizerPlan, $digest, $cacheDigest): array {
    return [
        'name' => $name,
        'source_token' => $finalizerPlan['source_token'],
        'released_generation' => $finalizerPlan['next_writer_generation'],
        'commit_generation' => 241,
        'schema_cookie' => $finalizerPlan['schema_cookie'],
        'database_digest' => $digest,
        'page_cache_digest' => $cacheDigest,
        'wal_index_salt' => $finalizerPlan['wal_index_salt'],
        'wal_index_mx_frame' => $finalizerPlan['wal_index_mx_frame'],
        'checkpoint_frame' => $finalizerPlan['checkpoint_frame'],
        'covered_statement_names' => $statements,
        'dirty_pages' => $dirtyPages,
        'commit_frames' => $frames,
        'commit_mark_seen' => true,
        'writer_lock_released' => true,
        'wal_hook_receipt' => true,
        'autocheckpoint_receipt' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [
    $receipt('active-plugins-commit', ['select-active-plugins'], [2], [15]),
    $receipt('autoload-options-commit', ['select-autoload-options'], [3, 4], [16, 17]),
    $receipt('option-index-commit', ['select-option-index'], [7], [18]),
], 241);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next240');
    assert($plan['autocheckpoint_baseline_allowed'] === true);
    assert($plan['wal_index_action'] === 'publish_wal_index_baseline_for_autocheckpoint');
    assert($plan['dirty_pages'] === [2, 3, 4, 7]);
    assert(in_array('application-import-next-writer-autocheckpoint-after-hot-journal', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next240 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next240',
    'status' => $plan['status'],
    'autocheckpointBaselineAllowed' => $plan['autocheckpoint_baseline_allowed'],
    'writerAction' => $plan['writer_action'],
    'walIndexAction' => $plan['wal_index_action'],
    'dirtyPages' => $plan['dirty_pages'],
    'commitFrames' => $plan['commit_frames'],
    'applicationUse' => 'After a copied wp_options import recovers a hot journal, rolls back a WAL savepoint, checkpoints the current source, and finalizes readers, the next writer commit can publish a clean WAL-index/autocheckpoint baseline only when the checkpoint database and page-cache receipts still match.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
