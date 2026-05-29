<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next290',
    'database_path' => '/srv/www/wp-content/database/wp-next291.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next291.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next291.sqlite-wal',
    'source_token' => 'wp-next291-current-source',
    'database_digest' => $digest('next291 database header'),
    'page_cache_digest' => $digest('next291 page cache'),
    'commit_generation' => 291,
    'schema_cookie' => 1291,
    'checkpoint_frame' => 291,
    'operation_names' => ['verify_after_ready_checkpoint_hot_journal_absent_next290'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next290'],
];
$receipt = [
    'name' => 'next291-current-source-delta-seal',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 291,
    'schema_cookie' => 1291,
    'checkpoint_frame' => 291,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next291AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next291 seals isolated delta'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next291', $record['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next_delta_complete', $record['reason']);
    $t->same(['next291-current-source-delta-seal'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next_delta_next291', implode(',', $record['operation_names']));
    $t->contains('next291 only advances the after-current WAL checkpoint receipt chain', $record['non_overlap']);
};

$tests['wal hot journal savepoint checkpoint current source next291 blocks stale page cache digest'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $badReceipt = array_replace($receipt, ['page_cache_digest' => hash('sha256', 'stale next291 page cache')]);
    $record = $plan(receipts: [$badReceipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next291', $record['status']);
    $t->contains('checkpoint_page_cache_digest_mismatch', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next291 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next289'])));
};

return $tests;
