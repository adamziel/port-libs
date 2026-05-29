<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next264',
    'database_path' => '/srv/www/wp-content/database/wp-next265.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next265.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next265.sqlite-wal',
    'source_token' => 'wp-next265-current-source',
    'database_digest' => $digest('next265 database header'),
    'page_cache_digest' => $digest('next265 page cache'),
    'commit_generation' => 265,
    'schema_cookie' => 1265,
    'checkpoint_frame' => 55,
    'operation_names' => ['verify_checkpoint_db_header_after_retry_receipts_next264'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next264'],
];
$receipt = [
    'name' => 'next265-wal-index-salt',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 265,
    'schema_cookie' => 1265,
    'checkpoint_frame' => 55,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next265 admits wal-index salt receipt'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next265', $record['status']);
    $t->same('verify_wal_index_salt_after_checkpoint_header_complete', $record['reason']);
    $t->same(['next265-wal-index-salt'], $record['accepted_checkpoint_receipt_names']);
    $t->same([], $record['blocked_reasons']);
    $t->contains('verify_wal_index_salt_after_checkpoint_header_next265', implode(',', $record['operation_names']));
};

$tests['wal hot journal savepoint checkpoint current source next265 blocks stale salt receipt'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $record = $plan(receipts: [array_replace($receipt, ['wal_index_salt_synced' => false])]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next265', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next265 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-invalid'])));
};

return $tests;
