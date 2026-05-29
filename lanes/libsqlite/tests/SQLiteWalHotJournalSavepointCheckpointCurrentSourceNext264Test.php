<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next263',
    'database_path' => '/srv/www/wp-content/database/wp-next264.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next264.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next264.sqlite-wal',
    'source_token' => 'wp-next264-current-source',
    'database_digest' => $digest('next264 database header'),
    'page_cache_digest' => $digest('next264 page cache'),
    'commit_generation' => 264,
    'schema_cookie' => 1264,
    'checkpoint_frame' => 54,
    'operation_names' => ['seal_retry_read_receipts_after_reader_cache_fence_next263'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next263'],
];
$receipt = [
    'name' => 'next264-db-header-sync',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 264,
    'schema_cookie' => 1264,
    'checkpoint_frame' => 54,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next264AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next264 admits db header checkpoint receipt'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next264', $record['status']);
    $t->same('verify_checkpoint_db_header_after_retry_receipts_complete', $record['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next263', $record['base_status']);
    $t->same(['next264-db-header-sync'], $record['accepted_checkpoint_receipt_names']);
    $t->same([], $record['blocked_reasons']);
    $t->same(true, $record['after_current_checkpoint_admitted']);
    $t->contains('verify_checkpoint_db_header_after_retry_receipts_next264', implode(',', $record['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next264', implode(',', $record['dependencies']));
    $t->same(64, strlen($record['seal_digest']));
};

$tests['wal hot journal savepoint checkpoint current source next264 blocks stale checkpoint receipts'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $record = $plan(receipts: [array_replace($receipt, [
        'database_header_synced' => false,
        'hot_journal_visible' => true,
    ])]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next264', $record['status']);
    $t->same(['next264-db-header-sync'], $record['blocked_checkpoint_receipt_names']);
    $t->contains('checkpoint_database_header_not_synced', implode(',', $record['blocked_reasons']));
    $t->contains('checkpoint_hot_journal_visible', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next264 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next262'])));
};

return $tests;
