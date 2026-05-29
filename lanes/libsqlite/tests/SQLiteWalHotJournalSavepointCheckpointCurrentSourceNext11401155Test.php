<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base1139 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1139',
    'database_path' => '/srv/www/wp-content/database/wp-next1140.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next1140.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next1140.sqlite-wal',
    'source_token' => 'wp-next1140-1155-current-source',
    'database_digest' => $digest('next1140-1155 checkpoint database image'),
    'page_cache_digest' => $digest('next1140-1155 checkpoint page cache image'),
    'commit_generation' => 1140,
    'schema_cookie' => 2140,
    'checkpoint_frame' => 940,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next1116_1123_next1123',
        'seal_after_ready_checkpoint_current_source_next1124_1131_next1131',
        'seal_after_ready_checkpoint_current_source_next1132_1139_next1139',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1123',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1139',
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

$chain = static function () use ($base1139, $receiptFor): array {
    $next1140 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1140AfterCurrentCheckpoint($base1139, [$receiptFor($base1139, 'next1140-restart-salt-database-header')]);
    $next1141 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1141AfterCurrentCheckpoint($next1140, [$receiptFor($next1140, 'next1141-reader-release-source-token')]);
    $next1142 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1142AfterCurrentCheckpoint($next1141, [$receiptFor($next1141, 'next1142-page-cache-database-digest')]);
    $next1143 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1143AfterCurrentCheckpoint($next1142, [$receiptFor($next1142, 'next1143-checkpoint-frame-schema-cookie')]);
    $next1144 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1144AfterCurrentCheckpoint($next1143, [$receiptFor($next1143, 'next1144-commit-generation-checkpoint-frame')]);
    $next1145 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1145AfterCurrentCheckpoint($next1144, [$receiptFor($next1144, 'next1145-hot-journal-delete-page-cache')]);
    $next1146 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1146AfterCurrentCheckpoint($next1145, [$receiptFor($next1145, 'next1146-wal-index-reader-release')]);
    $next1147 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1147AfterCurrentCheckpoint($next1146, [$receiptFor($next1146, 'next1147-current-source-seal')]);
    $next1148 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1148AfterCurrentCheckpoint($next1147, [$receiptFor($next1147, 'next1148-restart-salt-database-digest')]);
    $next1149 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1149AfterCurrentCheckpoint($next1148, [$receiptFor($next1148, 'next1149-reader-release-checkpoint-frame')]);
    $next1150 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1150AfterCurrentCheckpoint($next1149, [$receiptFor($next1149, 'next1150-page-cache-source-token')]);
    $next1151 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1151AfterCurrentCheckpoint($next1150, [$receiptFor($next1150, 'next1151-schema-cookie-database-header')]);
    $next1152 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1152AfterCurrentCheckpoint($next1151, [$receiptFor($next1151, 'next1152-commit-generation-wal-index')]);
    $next1153 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1153AfterCurrentCheckpoint($next1152, [$receiptFor($next1152, 'next1153-hot-journal-reader-release')]);
    $next1154 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1154AfterCurrentCheckpoint($next1153, [$receiptFor($next1153, 'next1154-wal-index-page-cache')]);
    $next1155 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1155AfterCurrentCheckpoint($next1154, [$receiptFor($next1154, 'next1155-current-source-seal')]);

    return [$next1140, $next1141, $next1142, $next1143, $next1144, $next1145, $next1146, $next1147, $next1148, $next1149, $next1150, $next1151, $next1152, $next1153, $next1154, $next1155];
};

$tests['wal hot journal savepoint checkpoint current source next1140-1155 chains from next1139'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        1140 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        1141 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        1142 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        1143 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        1144 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        1145 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        1146 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        1147 => 'seal_after_ready_checkpoint_current_source_next1140_1147_complete',
        1148 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        1149 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        1150 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        1151 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        1152 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        1153 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        1154 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        1155 => 'seal_after_ready_checkpoint_current_source_next1148_1155_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 1140];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next1140 = $chainRows[0];
    $next1155 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next1139', $next1140['base_status']);
    $t->same(['next1155-current-source-seal'], $next1155['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next1124_1131_next1131', implode(',', $next1155['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1132_1139_next1139', implode(',', $next1155['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next1140_1147_next1147', implode(',', $next1155['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1139', implode(',', $next1155['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next1155', implode(',', $next1155['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next1140 rejects missing next1139 handoff'] = static function (TestRunner $t) use ($base1139, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1140AfterCurrentCheckpoint(
        array_replace($base1139, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next1138']),
        [$receiptFor($base1139, 'next1140-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next1145 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next1144] = $chain();
    $receipt = $receiptFor($next1144, 'next1145-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1145AfterCurrentCheckpoint($next1144, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1145', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1150 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next1149] = $chain();
    $receipt = $receiptFor($next1149, 'next1150-source-token-mismatch');
    $receipt['source_token'] = 'stale-wal-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1150AfterCurrentCheckpoint($next1149, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1150', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next1155 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next1154] = $chain();
    $receipt = $receiptFor($next1154, 'next1155-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next1155AfterCurrentCheckpoint($next1154, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next1155', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

return $tests;
