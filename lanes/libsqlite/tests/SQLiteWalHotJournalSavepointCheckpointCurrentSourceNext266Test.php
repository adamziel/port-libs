<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next265',
    'database_path' => '/srv/www/wp-content/database/wp-next266.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next266.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next266.sqlite-wal',
    'source_token' => 'wp-next266-current-source',
    'database_digest' => $digest('next266 database header'),
    'page_cache_digest' => $digest('next266 page cache'),
    'commit_generation' => 266,
    'schema_cookie' => 1266,
    'checkpoint_frame' => 56,
    'operation_names' => ['verify_wal_index_salt_after_checkpoint_header_next265'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next265'],
];
$receipt = [
    'name' => 'next266-reader-marks',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 266,
    'schema_cookie' => 1266,
    'checkpoint_frame' => 56,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next266 admits reader mark receipt'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next266', $record['status']);
    $t->same('verify_reader_marks_after_wal_index_salt_complete', $record['reason']);
    $t->same(['next266-reader-marks'], $record['accepted_checkpoint_receipt_names']);
    $t->same([], $record['blocked_reasons']);
    $t->contains('verify_reader_marks_after_wal_index_salt_next266', implode(',', $record['operation_names']));
};

$tests['wal hot journal savepoint checkpoint current source next266 blocks pinned reader marks'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $record = $plan(receipts: [array_replace($receipt, ['reader_marks_released' => false])]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next266', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next266 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-invalid'])));
};

return $tests;
