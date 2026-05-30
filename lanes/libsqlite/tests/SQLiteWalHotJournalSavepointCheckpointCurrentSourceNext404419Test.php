<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base403 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next403',
    'database_path' => '/srv/www/wp-content/database/wp-next404.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next404.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next404.sqlite-wal',
    'source_token' => 'wp-next404-419-current-source',
    'database_digest' => $digest('next404-419 checkpoint database image'),
    'page_cache_digest' => $digest('next404-419 checkpoint page cache image'),
    'commit_generation' => 419,
    'schema_cookie' => 1419,
    'checkpoint_frame' => 219,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next396_403_next403'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next403'],
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

$chain = static function () use ($base403, $receiptFor): array {
    $next404 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base403, [$receiptFor($base403, 'next404-restart-salt-database-digest')], 404);
    $next405 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next404, [$receiptFor($next404, 'next405-reader-mark-database-digest')], 405);
    $next406 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next405, [$receiptFor($next405, 'next406-page-cache-digest-source-token')], 406);
    $next407 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next406, [$receiptFor($next406, 'next407-schema-cookie-source-token')], 407);
    $next408 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next407, [$receiptFor($next407, 'next408-commit-generation-frame-digest')], 408);
    $next409 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next408, [$receiptFor($next408, 'next409-hot-journal-absence-source-token')], 409);
    $next410 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next409, [$receiptFor($next409, 'next410-wal-index-salt-frame-digest')], 410);
    $next411 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next410, [$receiptFor($next410, 'next411-current-source-seal')], 411);
    $next412 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next411, [$receiptFor($next411, 'next412-restart-salt-schema-cookie')], 412);
    $next413 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next412, [$receiptFor($next412, 'next413-reader-mark-schema-cookie')], 413);
    $next414 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next413, [$receiptFor($next413, 'next414-page-cache-digest-frame')], 414);
    $next415 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next414, [$receiptFor($next414, 'next415-schema-cookie-digest-frame')], 415);
    $next416 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next415, [$receiptFor($next415, 'next416-commit-generation-source-frame')], 416);
    $next417 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next416, [$receiptFor($next416, 'next417-hot-journal-delete-source-frame')], 417);
    $next418 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next417, [$receiptFor($next417, 'next418-wal-index-salt-source-frame')], 418);
    $next419 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next418, [$receiptFor($next418, 'next419-current-source-seal')], 419);

    return [$next404, $next405, $next406, $next407, $next408, $next409, $next410, $next411, $next412, $next413, $next414, $next415, $next416, $next417, $next418, $next419];
};

$tests['wal hot journal savepoint checkpoint current source next404-419 chains after merged next388-403'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        404 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_digest_complete',
        405 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        406 => 'verify_after_ready_checkpoint_page_cache_digest_source_token_complete',
        407 => 'verify_after_ready_checkpoint_schema_cookie_source_token_complete',
        408 => 'verify_after_ready_checkpoint_commit_generation_frame_digest_complete',
        409 => 'verify_after_ready_checkpoint_hot_journal_absence_source_token_complete',
        410 => 'verify_after_ready_checkpoint_wal_index_salt_frame_digest_complete',
        411 => 'seal_after_ready_checkpoint_current_source_next404_411_complete',
        412 => 'verify_after_ready_checkpoint_restart_salt_receipt_schema_cookie_complete',
        413 => 'verify_after_ready_checkpoint_reader_mark_release_schema_cookie_complete',
        414 => 'verify_after_ready_checkpoint_page_cache_digest_frame_complete',
        415 => 'verify_after_ready_checkpoint_schema_cookie_digest_frame_complete',
        416 => 'verify_after_ready_checkpoint_commit_generation_source_frame_complete',
        417 => 'verify_after_ready_checkpoint_hot_journal_delete_source_frame_complete',
        418 => 'verify_after_ready_checkpoint_wal_index_salt_source_frame_complete',
        419 => 'seal_after_ready_checkpoint_current_source_next412_419_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 404];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next419 = $chainRows[15];
    $t->same(['next419-current-source-seal'], $next419['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_restart_salt_receipt_database_digest_next404', implode(',', $next419['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next404_411_next411', implode(',', $next419['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next403', implode(',', $next419['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next419', implode(',', $next419['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next404 blocks database digest mismatch'] = static function (TestRunner $t) use ($base403, $receiptFor, $digest): void {
    $receipt = $receiptFor($base403, 'next404-stale-database-digest');
    $receipt['database_digest'] = $digest('stale database digest next404');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base403, [$receipt], 404);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next404', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next406 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next405] = $chain();
    $receipt = $receiptFor($next405, 'next406-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next405, [$receipt], 406);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next406', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next408 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , $next407] = $chain();
    $receipt = $receiptFor($next407, 'next408-stale-frame');
    $receipt['checkpoint_frame'] = 218;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next407, [$receipt], 408);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next408', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next411 rejects missing next410 base'] = static function (TestRunner $t) use ($base403, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base403, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next409']),
        [$receiptFor($base403, 'next411-current-source-seal')],
        411
    ));
};

$tests['wal hot journal savepoint checkpoint current source next413 blocks reader marks not released'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , $next412] = $chain();
    $receipt = $receiptFor($next412, 'next413-reader-mark-still-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next412, [$receipt], 413);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next413', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next416 blocks commit generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , $next415] = $chain();
    $receipt = $receiptFor($next415, 'next416-stale-commit-generation');
    $receipt['commit_generation'] = 418;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next415, [$receipt], 416);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next416', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next419 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next418] = $chain();
    $receipt = $receiptFor($next418, 'next419-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next418, [$receipt, $receipt], 419);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next419', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next419-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
