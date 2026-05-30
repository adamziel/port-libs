<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base451 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next451',
    'database_path' => '/srv/www/wp-content/database/wp-next452.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next452.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next452.sqlite-wal',
    'source_token' => 'wp-next452-467-current-source',
    'database_digest' => $digest('next452-467 checkpoint database image'),
    'page_cache_digest' => $digest('next452-467 checkpoint page cache image'),
    'commit_generation' => 467,
    'schema_cookie' => 1467,
    'checkpoint_frame' => 267,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next444_451_next451'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next451'],
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

$chain = static function () use ($base451, $receiptFor): array {
    $next452 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base451, [$receiptFor($base451, 'next452-restart-salt-page-cache')], 452);
    $next453 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next452, [$receiptFor($next452, 'next453-reader-mark-page-cache')], 453);
    $next454 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next453, [$receiptFor($next453, 'next454-page-cache-source-frame')], 454);
    $next455 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next454, [$receiptFor($next454, 'next455-schema-cookie-source-digest')], 455);
    $next456 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next455, [$receiptFor($next455, 'next456-generation-page-cache')], 456);
    $next457 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next456, [$receiptFor($next456, 'next457-hot-journal-page-cache')], 457);
    $next458 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next457, [$receiptFor($next457, 'next458-wal-index-page-cache')], 458);
    $next459 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next458, [$receiptFor($next458, 'next459-current-source-seal')], 459);
    $next460 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next459, [$receiptFor($next459, 'next460-restart-salt-source-generation')], 460);
    $next461 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next460, [$receiptFor($next460, 'next461-reader-mark-source-generation')], 461);
    $next462 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next461, [$receiptFor($next461, 'next462-page-cache-schema-generation')], 462);
    $next463 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next462, [$receiptFor($next462, 'next463-schema-cookie-page-cache')], 463);
    $next464 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next463, [$receiptFor($next463, 'next464-generation-database-digest')], 464);
    $next465 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next464, [$receiptFor($next464, 'next465-hot-journal-delete-page-cache')], 465);
    $next466 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next465, [$receiptFor($next465, 'next466-wal-index-database-digest')], 466);
    $next467 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next466, [$receiptFor($next466, 'next467-current-source-seal')], 467);

    return [$next452, $next453, $next454, $next455, $next456, $next457, $next458, $next459, $next460, $next461, $next462, $next463, $next464, $next465, $next466, $next467];
};

$tests['wal hot journal savepoint checkpoint current source next452-467 chains after merged next436-451'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        452 => 'verify_after_ready_checkpoint_restart_salt_receipt_page_cache_digest_complete',
        453 => 'verify_after_ready_checkpoint_reader_mark_release_page_cache_digest_complete',
        454 => 'verify_after_ready_checkpoint_page_cache_digest_source_frame_complete',
        455 => 'verify_after_ready_checkpoint_schema_cookie_source_digest_complete',
        456 => 'verify_after_ready_checkpoint_commit_generation_page_cache_digest_complete',
        457 => 'verify_after_ready_checkpoint_hot_journal_absence_page_cache_digest_complete',
        458 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_digest_complete',
        459 => 'seal_after_ready_checkpoint_current_source_next452_459_complete',
        460 => 'verify_after_ready_checkpoint_restart_salt_receipt_source_generation_complete',
        461 => 'verify_after_ready_checkpoint_reader_mark_release_source_generation_complete',
        462 => 'verify_after_ready_checkpoint_page_cache_digest_schema_generation_complete',
        463 => 'verify_after_ready_checkpoint_schema_cookie_page_cache_digest_complete',
        464 => 'verify_after_ready_checkpoint_commit_generation_database_digest_complete',
        465 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_digest_complete',
        466 => 'verify_after_ready_checkpoint_wal_index_salt_database_digest_complete',
        467 => 'seal_after_ready_checkpoint_current_source_next460_467_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 452];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next467 = $chainRows[15];
    $t->same(['next467-current-source-seal'], $next467['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next444_451_next451', implode(',', $next467['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next452_459_next459', implode(',', $next467['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next451', implode(',', $next467['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next467', implode(',', $next467['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next452 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($base451, $receiptFor, $digest): void {
    $receipt = $receiptFor($base451, 'next452-stale-page-cache');
    $receipt['page_cache_digest'] = $digest('stale page cache digest next452');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base451, [$receipt], 452);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next452', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next455 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next454] = $chain();
    $receipt = $receiptFor($next454, 'next455-stale-source-token');
    $receipt['source_token'] = 'wp-next455-stale-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next454, [$receipt], 455);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next455', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next459 rejects missing next458 base'] = static function (TestRunner $t) use ($base451, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base451, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next457']),
        [$receiptFor($base451, 'next459-current-source-seal')]
    ), 459);
};

$tests['wal hot journal savepoint checkpoint current source next464 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , $next463] = $chain();
    $receipt = $receiptFor($next463, 'next464-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next463, [$receipt], 464);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next464', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next466 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , $next465] = $chain();
    $receipt = $receiptFor($next465, 'next466-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next465, [$receipt], 466);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next466', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next467 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next466] = $chain();
    $receipt = $receiptFor($next466, 'next467-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next466, [$receipt, $receipt], 467);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next467', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next467-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
