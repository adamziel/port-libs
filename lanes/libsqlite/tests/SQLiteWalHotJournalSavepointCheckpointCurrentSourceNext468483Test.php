<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base467 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next467',
    'database_path' => '/srv/www/wp-content/database/wp-next468.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next468.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next468.sqlite-wal',
    'source_token' => 'wp-next468-483-current-source',
    'database_digest' => $digest('next468-483 checkpoint database image'),
    'page_cache_digest' => $digest('next468-483 checkpoint page cache image'),
    'commit_generation' => 483,
    'schema_cookie' => 1483,
    'checkpoint_frame' => 283,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next460_467_next467'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next467'],
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

$chain = static function () use ($base467, $receiptFor): array {
    $next468 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next468AfterCurrentCheckpoint($base467, [$receiptFor($base467, 'next468-restart-salt-frame-digest')]);
    $next469 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next469AfterCurrentCheckpoint($next468, [$receiptFor($next468, 'next469-reader-mark-frame-digest')]);
    $next470 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next470AfterCurrentCheckpoint($next469, [$receiptFor($next469, 'next470-page-cache-generation-frame')]);
    $next471 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next471AfterCurrentCheckpoint($next470, [$receiptFor($next470, 'next471-schema-cookie-database-digest')]);
    $next472 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next472AfterCurrentCheckpoint($next471, [$receiptFor($next471, 'next472-generation-schema-cookie')]);
    $next473 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next473AfterCurrentCheckpoint($next472, [$receiptFor($next472, 'next473-hot-journal-frame-digest')]);
    $next474 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next474AfterCurrentCheckpoint($next473, [$receiptFor($next473, 'next474-wal-index-source-token')]);
    $next475 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next475AfterCurrentCheckpoint($next474, [$receiptFor($next474, 'next475-current-source-seal')]);
    $next476 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next476AfterCurrentCheckpoint($next475, [$receiptFor($next475, 'next476-restart-salt-database-header')]);
    $next477 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next477AfterCurrentCheckpoint($next476, [$receiptFor($next476, 'next477-reader-mark-wal-index-salt')]);
    $next478 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next478AfterCurrentCheckpoint($next477, [$receiptFor($next477, 'next478-page-cache-reader-release')]);
    $next479 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next479AfterCurrentCheckpoint($next478, [$receiptFor($next478, 'next479-schema-cookie-hot-journal')]);
    $next480 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next480AfterCurrentCheckpoint($next479, [$receiptFor($next479, 'next480-generation-database-header')]);
    $next481 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next481AfterCurrentCheckpoint($next480, [$receiptFor($next480, 'next481-hot-journal-wal-index-salt')]);
    $next482 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next482AfterCurrentCheckpoint($next481, [$receiptFor($next481, 'next482-wal-index-reader-release')]);
    $next483 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next483AfterCurrentCheckpoint($next482, [$receiptFor($next482, 'next483-current-source-seal')]);

    return [$next468, $next469, $next470, $next471, $next472, $next473, $next474, $next475, $next476, $next477, $next478, $next479, $next480, $next481, $next482, $next483];
};

$tests['wal hot journal savepoint checkpoint current source next468-483 chains after merged next452-467'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        468 => 'verify_after_ready_checkpoint_restart_salt_receipt_frame_digest_complete',
        469 => 'verify_after_ready_checkpoint_reader_mark_release_frame_digest_complete',
        470 => 'verify_after_ready_checkpoint_page_cache_digest_generation_frame_complete',
        471 => 'verify_after_ready_checkpoint_schema_cookie_database_digest_complete',
        472 => 'verify_after_ready_checkpoint_commit_generation_schema_cookie_complete',
        473 => 'verify_after_ready_checkpoint_hot_journal_absence_frame_digest_complete',
        474 => 'verify_after_ready_checkpoint_wal_index_salt_source_token_complete',
        475 => 'seal_after_ready_checkpoint_current_source_next468_475_complete',
        476 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_header_complete',
        477 => 'verify_after_ready_checkpoint_reader_mark_release_wal_index_salt_complete',
        478 => 'verify_after_ready_checkpoint_page_cache_digest_reader_release_complete',
        479 => 'verify_after_ready_checkpoint_schema_cookie_hot_journal_absence_complete',
        480 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        481 => 'verify_after_ready_checkpoint_hot_journal_delete_wal_index_salt_complete',
        482 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        483 => 'seal_after_ready_checkpoint_current_source_next476_483_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 468];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next483 = $chainRows[15];
    $t->same(['next483-current-source-seal'], $next483['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next460_467_next467', implode(',', $next483['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next468_475_next475', implode(',', $next483['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next467', implode(',', $next483['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next483', implode(',', $next483['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next468 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($base467, $receiptFor): void {
    $receipt = $receiptFor($base467, 'next468-stale-frame');
    $receipt['checkpoint_frame'] = 284;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next468AfterCurrentCheckpoint($base467, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next468', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next471 blocks database digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, , $next470] = $chain();
    $receipt = $receiptFor($next470, 'next471-stale-database-digest');
    $receipt['database_digest'] = $digest('stale database digest next471');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next471AfterCurrentCheckpoint($next470, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next471', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next475 rejects missing next474 base'] = static function (TestRunner $t) use ($base467, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next475AfterCurrentCheckpoint(
        array_replace($base467, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next473']),
        [$receiptFor($base467, 'next475-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next477 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , $next476] = $chain();
    $receipt = $receiptFor($next476, 'next477-wal-index-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next477AfterCurrentCheckpoint($next476, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next477', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next478 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next477] = $chain();
    $receipt = $receiptFor($next477, 'next478-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next478AfterCurrentCheckpoint($next477, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next478', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next480 blocks commit generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , $next479] = $chain();
    $receipt = $receiptFor($next479, 'next480-stale-generation');
    $receipt['commit_generation'] = 482;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next480AfterCurrentCheckpoint($next479, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next480', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next483 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next482] = $chain();
    $receipt = $receiptFor($next482, 'next483-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next483AfterCurrentCheckpoint($next482, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next483', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next483-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
