<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'application-import-current-source-next234', 'epoch' => 234];
$walDigest = $digest('application next234 reopened wal');
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 41,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'next_source_epoch' => 235,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import'],
    'reopen_reader_names' => ['object-cache-reader'],
];
$scopePages = [1, 2, 8];
$pageDigests = [];
foreach ($scopePages as $page) {
    $pageDigests[$page] = $digest('wp-options-savepoint:page:' . $page);
}
$finalized = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [[
    'name' => 'wp-options-savepoint',
    'savepoint_depth' => 0,
    'released' => true,
    'rollback_generation' => 234,
    'checkpoint_frame' => 41,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'journal_delete_receipt' => true,
    'wal_reset_frame' => 41,
    'reader_names' => ['wp-options-import'],
    'page_digests' => $pageDigests,
]]);
$published = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($finalized, [[
    'scope_name' => 'wp-options-savepoint',
    'source_token_id' => $token['id'],
    'source_epoch' => 234,
    'checkpoint_frame' => 41,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'journal_delete_receipt' => true,
    'page_digests' => $pageDigests,
    'next_source_epoch' => 235,
]]);
$readmarks = ['object-cache-reader' => 41, 'wp-options-import' => 41];
$reopened = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($published, [[
    'name' => 'application-shm-reopen-next234',
    'scope_names' => ['wp-options-savepoint'],
    'source_token_id' => $token['id'],
    'source_epoch' => 234,
    'next_source_epoch' => 235,
    'checkpoint_frame' => 41,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'wal_digest' => $walDigest,
    'salt_1' => 23401,
    'salt_2' => 23402,
    'checksum_digest' => hash('sha256', json_encode([23401, 23402, 41, 41, $readmarks, $walDigest], JSON_THROW_ON_ERROR)),
    'mx_frame' => 41,
    'backfill_frame' => 41,
    'readmark_frames' => $readmarks,
    'readers_reopened' => true,
    'shm_synced' => true,
]], $walDigest);
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next234VerifyDurableHandoff($reopened, [[
    'name' => 'application-durable-handoff-next234',
    'scope_names' => ['wp-options-savepoint'],
    'source_token_id' => $token['id'],
    'source_epoch' => 234,
    'next_source_epoch' => 235,
    'checkpoint_frame' => 41,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'wal_digest' => $walDigest,
    'database_digest' => $digest('application checkpointed database'),
    'shm_digest' => $digest('application synced shm'),
    'sync_order' => ['database_sync', 'wal_sync', 'shm_sync', 'journal_unlink', 'directory_sync'],
    'database_synced' => true,
    'wal_synced' => true,
    'shm_synced' => true,
    'journal_unlinked' => true,
    'directory_synced' => true,
    'reader_cache_clean' => true,
    'writer_generation' => 235,
]]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next234');
    assert($plan['can_serve_durable_current_source'] === true);
    assert($plan['receipt_rows'][0]['sync_order'] === ['database_sync', 'wal_sync', 'shm_sync', 'journal_unlink', 'directory_sync']);
    assert(in_array('application-import-durable-wal-current-source-handoff', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next234 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next234',
    'status' => $plan['status'],
    'canServeDurableCurrentSource' => $plan['can_serve_durable_current_source'],
    'checkpointFrame' => $plan['checkpoint_frame'],
    'syncOrder' => $plan['receipt_rows'][0]['sync_order'],
    'applicationUse' => 'A copied wp_options import serves the repaired current source only after the checkpointed database, restarted WAL, synced SHM, unlinked hot journal, and containing directory receipts all match the reopened WAL-index source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
