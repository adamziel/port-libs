<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next280',
    'database_path' => '/srv/www/wp-content/database/wp-next281.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next281.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next281.sqlite-wal',
    'source_token' => 'wp-next281-current-source',
    'database_digest' => $digest('next281 database header'),
    'page_cache_digest' => $digest('next281 page cache'),
    'commit_generation' => 281,
    'schema_cookie' => 1281,
    'checkpoint_frame' => 281,
    'operation_names' => ['verify_after_ready_checkpoint_generation_fence_next280'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next280'],
];
$receipt = [
    'name' => 'next281-schema-cookie-fence',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 281,
    'schema_cookie' => 1281,
    'checkpoint_frame' => 281,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointVerification($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next281 verifies schema cookie fence'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next281', $record['status']);
    $t->same('verify_after_ready_checkpoint_schema_cookie_fence_complete', $record['reason']);
    $t->same(['next281-schema-cookie-fence'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_schema_cookie_fence_next281', implode(',', $record['operation_names']));
};

$tests['wal hot journal savepoint checkpoint current source next281 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $badReceipt = array_replace($receipt, ['schema_cookie' => 1280]);
    $record = $plan(receipts: [$badReceipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next281', $record['status']);
    $t->contains('checkpoint_schema_cookie_mismatch', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next281 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-invalid'])));
};

return $tests;
