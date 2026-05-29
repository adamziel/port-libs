<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next284',
    'database_path' => '/srv/www/wp-content/database/wp-next285.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next285.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next285.sqlite-wal',
    'source_token' => 'wp-next285-current-source',
    'database_digest' => $digest('next285 database header'),
    'page_cache_digest' => $digest('next285 page cache'),
    'commit_generation' => 285,
    'schema_cookie' => 1285,
    'checkpoint_frame' => 285,
    'operation_names' => ['verify_after_ready_checkpoint_wal_index_publish_next284'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next284'],
];
$receipt = [
    'name' => 'next285-reader-mark-release',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 285,
    'schema_cookie' => 1285,
    'checkpoint_frame' => 285,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next285 verifies reader mark release'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next285', $record['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_release_complete', $record['reason']);
    $t->same(['next285-reader-mark-release'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_reader_mark_release_next285', implode(',', $record['operation_names']));
};

$tests['wal hot journal savepoint checkpoint current source next285 blocks retained reader marks'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $badReceipt = array_replace($receipt, ['reader_marks_released' => false]);
    $record = $plan(receipts: [$badReceipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next285', $record['status']);
    $t->contains('checkpoint_reader_marks_not_released', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next285 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-invalid'])));
};

return $tests;
