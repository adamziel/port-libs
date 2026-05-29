<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next279',
    'database_path' => '/srv/www/wp-content/database/wp-next280.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next280.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next280.sqlite-wal',
    'source_token' => 'wp-next280-current-source',
    'database_digest' => $digest('next280 database header'),
    'page_cache_digest' => $digest('next280 page cache'),
    'commit_generation' => 280,
    'schema_cookie' => 1280,
    'checkpoint_frame' => 280,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_handoff_next279'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next279'],
];
$receipt = [
    'name' => 'next280-generation-fence',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 280,
    'schema_cookie' => 1280,
    'checkpoint_frame' => 280,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next280AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next280 verifies generation fence'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next280', $record['status']);
    $t->same('verify_after_ready_checkpoint_generation_fence_complete', $record['reason']);
    $t->same(['next280-generation-fence'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_generation_fence_next280', implode(',', $record['operation_names']));
};

$tests['wal hot journal savepoint checkpoint current source next280 blocks generation mismatch'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $badReceipt = array_replace($receipt, ['commit_generation' => 279]);
    $record = $plan(receipts: [$badReceipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next280', $record['status']);
    $t->contains('checkpoint_generation_mismatch', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next280 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next278'])));
};

return $tests;
