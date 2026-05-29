<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next282',
    'database_path' => '/srv/www/wp-content/database/wp-next283.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next283.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next283.sqlite-wal',
    'source_token' => 'wp-next283-current-source',
    'database_digest' => $digest('next283 database header'),
    'page_cache_digest' => $digest('next283 page cache'),
    'commit_generation' => 283,
    'schema_cookie' => 1283,
    'checkpoint_frame' => 283,
    'operation_names' => ['verify_after_ready_checkpoint_cache_digest_fence_next282'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next282'],
];
$receipt = [
    'name' => 'next283-receipt-chain-seal',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 283,
    'schema_cookie' => 1283,
    'checkpoint_frame' => 283,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next283AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next283 seals receipt chain'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next283', $record['status']);
    $t->same('seal_after_ready_checkpoint_receipt_chain_complete', $record['reason']);
    $t->same(['next283-receipt-chain-seal'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_receipt_chain_next283', implode(',', $record['operation_names']));
    $t->contains('next283 only advances the after-current WAL checkpoint receipt chain', $record['non_overlap']);
};

$tests['wal hot journal savepoint checkpoint current source next283 blocks visible hot journal'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $badReceipt = array_replace($receipt, ['hot_journal_visible' => true]);
    $record = $plan(receipts: [$badReceipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next283', $record['status']);
    $t->contains('checkpoint_hot_journal_visible', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next283 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next281'])));
};

return $tests;
