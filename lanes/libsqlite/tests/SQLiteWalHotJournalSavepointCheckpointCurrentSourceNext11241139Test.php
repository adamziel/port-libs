<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1123 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1123',
    'database_path' => '/srv/www/wp-content/database/wp-next1124.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1124.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1124.sqlite-wal',
    'source_token' => 'wp-next1124-1139-current-source',
    'database_digest' => $digest('next1124-1139 checkpoint database image'),
    'page_cache_digest' => $digest('next1124-1139 checkpoint page cache image'),
    'commit_generation' => 1124,
    'schema_cookie' => 2124,
    'checkpoint_frame' => 924,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1100_1107_next1107',
        'seal_after_ready_checkpoint_current_source_next1108_1115_next1115',
        'seal_after_ready_checkpoint_current_source_next1116_1123_next1123',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1107',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1123',
    ],
];

$receiptFor = static function (array $base, string $name): array {
    return [
        'name' => $name,
        'source_token' => $base['source_token'],
        'database_digest' => $base['database_digest'],
        'page_cache_digest' => $base['page_cache_digest'],
        'commit_generation' => $base['commit_generation'],
        'schema_cookie' => $base['schema_cookie'],
        'checkpoint_frame' => $base['checkpoint_frame'],
        'database_header_synced' => true,
        'wal_index_salt_synced' => true,
        'reader_marks_released' => true,
        'hot_journal_visible' => false,
    ];
};

$afterReadyCheckpoint = static fn (array $base, array $receipts, int $stage, string $step): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification($base, $receipts, $stage, $step);

$chain = static function () use ($base1123, $receiptFor): array {
    $afterReadyCheckpoint = static fn (array $base, array $receipts, int $stage, string $step): array =>
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification($base, $receipts, $stage, $step);

    $next1124 = $afterReadyCheckpoint($base1123, [$receiptFor($base1123, 'next1124-restart-salt-database-header')], 1124, 'verify_after_ready_checkpoint_restart_salt_database_header');
    $next1125 = $afterReadyCheckpoint($next1124, [$receiptFor($next1124, 'next1125-reader-release-source-token')], 1125, 'verify_after_ready_checkpoint_reader_mark_release_source_token');
    $next1126 = $afterReadyCheckpoint($next1125, [$receiptFor($next1125, 'next1126-page-cache-database-digest')], 1126, 'verify_after_ready_checkpoint_page_cache_database_digest');
    $next1127 = $afterReadyCheckpoint($next1126, [$receiptFor($next1126, 'next1127-checkpoint-frame-schema-cookie')], 1127, 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie');
    $next1128 = $afterReadyCheckpoint($next1127, [$receiptFor($next1127, 'next1128-commit-generation-checkpoint-frame')], 1128, 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame');
    $next1129 = $afterReadyCheckpoint($next1128, [$receiptFor($next1128, 'next1129-hot-journal-delete-page-cache')], 1129, 'verify_after_ready_checkpoint_hot_journal_delete_page_cache');
    $next1130 = $afterReadyCheckpoint($next1129, [$receiptFor($next1129, 'next1130-wal-index-reader-release')], 1130, 'verify_after_ready_checkpoint_wal_index_salt_reader_release');
    $next1131 = $afterReadyCheckpoint($next1130, [$receiptFor($next1130, 'next1131-current-source-seal')], 1131, 'seal_after_ready_checkpoint_current_source_next1124_1131');
    $next1132 = $afterReadyCheckpoint($next1131, [$receiptFor($next1131, 'next1132-restart-salt-database-digest')], 1132, 'verify_after_ready_checkpoint_restart_salt_database_digest');
    $next1133 = $afterReadyCheckpoint($next1132, [$receiptFor($next1132, 'next1133-reader-release-checkpoint-frame')], 1133, 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame');
    $next1134 = $afterReadyCheckpoint($next1133, [$receiptFor($next1133, 'next1134-page-cache-source-token')], 1134, 'verify_after_ready_checkpoint_page_cache_source_token');
    $next1135 = $afterReadyCheckpoint($next1134, [$receiptFor($next1134, 'next1135-schema-cookie-database-header')], 1135, 'verify_after_ready_checkpoint_schema_cookie_database_header');
    $next1136 = $afterReadyCheckpoint($next1135, [$receiptFor($next1135, 'next1136-commit-generation-wal-index')], 1136, 'verify_after_ready_checkpoint_commit_generation_wal_index_salt');
    $next1137 = $afterReadyCheckpoint($next1136, [$receiptFor($next1136, 'next1137-hot-journal-reader-release')], 1137, 'verify_after_ready_checkpoint_hot_journal_absence_reader_release');
    $next1138 = $afterReadyCheckpoint($next1137, [$receiptFor($next1137, 'next1138-wal-index-page-cache')], 1138, 'verify_after_ready_checkpoint_wal_index_salt_page_cache');
    $next1139 = $afterReadyCheckpoint($next1138, [$receiptFor($next1138, 'next1139-current-source-seal')], 1139, 'seal_after_ready_checkpoint_current_source_next1132_1139');

    return [$next1124, $next1125, $next1126, $next1127, $next1128, $next1129, $next1130, $next1131, $next1132, $next1133, $next1134, $next1135, $next1136, $next1137, $next1138, $next1139];
};

$tests['wal hot journal savepoint checkpoint current source next1124-1139 chains from next1123'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1124 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1125 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1126 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1127 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1128 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1129 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1130 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1131 => 'seal_after_ready_checkpoint_current_source_next1124_1131_complete',
        1132 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1133 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1134 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1135 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1136 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1137 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1138 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1139 => 'seal_after_ready_checkpoint_current_source_next1132_1139_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1124];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1124 = $chainRows[0];
    $next1139 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1123', $next1124['base_status']);
    $t->same(['next1139-current-source-seal'], $next1139['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1108_1115_next1115', implode(',', $next1139['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1116_1123_next1123', implode(',', $next1139['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1124_1131_next1131', implode(',', $next1139['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1123', implode(',', $next1139['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1139', implode(',', $next1139['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1124 rejects missing next1123 handoff'] = static function (TestRunner $t) use ($afterReadyCheckpoint, $base1123, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => $afterReadyCheckpoint(
        array_replace($base1123, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1122']),
        [$receiptFor($base1123, 'next1124-wrong-base')],
        1124,
        'verify_after_ready_checkpoint_restart_salt_database_header'
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1129 blocks visible hot journal'] = static function (TestRunner $t) use ($afterReadyCheckpoint, $chain, $receiptFor): void {
    [, , , , $next1128] = $chain();
    $receipt = $receiptFor($next1128, 'next1129-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = $afterReadyCheckpoint($next1128, [$receipt], 1129, 'verify_after_ready_checkpoint_hot_journal_delete_page_cache');

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1129', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1134 blocks source token mismatch'] = static function (TestRunner $t) use ($afterReadyCheckpoint, $chain, $receiptFor): void {
    [, , , , , , , , , $next1133] = $chain();
    $receipt = $receiptFor($next1133, 'next1134-source-token-mismatch');
    $receipt['source_token'] = 'stale-wal-current-source';
    $record = $afterReadyCheckpoint($next1133, [$receipt], 1134, 'verify_after_ready_checkpoint_page_cache_source_token');

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1134', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1139 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($afterReadyCheckpoint, $chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1138] = $chain();
    $receipt = $receiptFor($next1138, 'next1139-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = $afterReadyCheckpoint($next1138, [$receipt], 1139, 'seal_after_ready_checkpoint_current_source_next1132_1139');

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1139', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

return $tests;
