<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base483 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next483',
    'database_path' => '/srv/www/wp-content/database/wp-next484.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next484.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next484.sqlite-wal',
    'source_token' => 'wp-next484-499-current-source',
    'database_digest' => $digest('next484-499 checkpoint database image'),
    'page_cache_digest' => $digest('next484-499 checkpoint page cache image'),
    'commit_generation' => 499,
    'schema_cookie' => 1499,
    'checkpoint_frame' => 299,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next476_483_next483'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next483'],
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

$chain = static function () use ($base483, $receiptFor): array {
    $next484 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base483, [$receiptFor($base483, 'next484-restart-salt-source-frame')], 484);
    $next485 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next484, [$receiptFor($next484, 'next485-reader-mark-source-frame')], 485);
    $next486 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next485, [$receiptFor($next485, 'next486-page-cache-database-header')], 486);
    $next487 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next486, [$receiptFor($next486, 'next487-schema-cookie-wal-index-salt')], 487);
    $next488 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next487, [$receiptFor($next487, 'next488-generation-reader-release')], 488);
    $next489 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next488, [$receiptFor($next488, 'next489-hot-journal-database-header')], 489);
    $next490 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next489, [$receiptFor($next489, 'next490-wal-index-page-cache')], 490);
    $next491 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next490, [$receiptFor($next490, 'next491-current-source-seal')], 491);
    $next492 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next491, [$receiptFor($next491, 'next492-restart-salt-schema-generation')], 492);
    $next493 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next492, [$receiptFor($next492, 'next493-reader-mark-schema-generation')], 493);
    $next494 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next493, [$receiptFor($next493, 'next494-page-cache-generation')], 494);
    $next495 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next494, [$receiptFor($next494, 'next495-schema-cookie-frame')], 495);
    $next496 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next495, [$receiptFor($next495, 'next496-generation-source-token')], 496);
    $next497 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next496, [$receiptFor($next496, 'next497-hot-journal-frame')], 497);
    $next498 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next497, [$receiptFor($next497, 'next498-wal-index-frame')], 498);
    $next499 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next498, [$receiptFor($next498, 'next499-current-source-seal')], 499);

    return [$next484, $next485, $next486, $next487, $next488, $next489, $next490, $next491, $next492, $next493, $next494, $next495, $next496, $next497, $next498, $next499];
};

$tests['wal hot journal savepoint checkpoint current source next484-499 chains after merged next468-483'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        484 => 'verify_after_ready_checkpoint_restart_salt_receipt_source_frame_complete',
        485 => 'verify_after_ready_checkpoint_reader_mark_release_source_frame_complete',
        486 => 'verify_after_ready_checkpoint_page_cache_digest_database_header_complete',
        487 => 'verify_after_ready_checkpoint_schema_cookie_wal_index_salt_complete',
        488 => 'verify_after_ready_checkpoint_commit_generation_reader_release_complete',
        489 => 'verify_after_ready_checkpoint_hot_journal_absence_database_header_complete',
        490 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_digest_complete',
        491 => 'seal_after_ready_checkpoint_current_source_next484_491_complete',
        492 => 'verify_after_ready_checkpoint_restart_salt_receipt_schema_generation_complete',
        493 => 'verify_after_ready_checkpoint_reader_mark_release_schema_generation_complete',
        494 => 'verify_after_ready_checkpoint_page_cache_digest_commit_generation_complete',
        495 => 'verify_after_ready_checkpoint_schema_cookie_checkpoint_frame_complete',
        496 => 'verify_after_ready_checkpoint_commit_generation_source_token_complete',
        497 => 'verify_after_ready_checkpoint_hot_journal_delete_checkpoint_frame_complete',
        498 => 'verify_after_ready_checkpoint_wal_index_salt_checkpoint_frame_complete',
        499 => 'seal_after_ready_checkpoint_current_source_next492_499_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 484];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next499 = $chainRows[15];
    $t->same(['next499-current-source-seal'], $next499['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next476_483_next483', implode(',', $next499['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next484_491_next491', implode(',', $next499['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next483', implode(',', $next499['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next499', implode(',', $next499['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next484 blocks source token mismatch'] = static function (TestRunner $t) use ($base483, $receiptFor): void {
    $receipt = $receiptFor($base483, 'next484-stale-source-token');
    $receipt['source_token'] = 'wp-next484-499-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base483, [$receipt], 484);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next484', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next486 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, $next485] = $chain();
    $receipt = $receiptFor($next485, 'next486-stale-page-cache');
    $receipt['page_cache_digest'] = $digest('stale page cache digest next486');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next485, [$receipt], 486);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next486', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next491 rejects missing next490 base'] = static function (TestRunner $t) use ($base483, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base483, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next489']),
        [$receiptFor($base483, 'next491-current-source-seal')]
    ), 491);
};

$tests['wal hot journal savepoint checkpoint current source next492 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , $next491] = $chain();
    $receipt = $receiptFor($next491, 'next492-database-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next491, [$receipt], 492);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next492', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next495 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next494] = $chain();
    $receipt = $receiptFor($next494, 'next495-stale-schema-cookie');
    $receipt['schema_cookie'] = 1498;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next494, [$receipt], 495);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next495', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next497 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next496] = $chain();
    $receipt = $receiptFor($next496, 'next497-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next496, [$receipt], 497);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next497', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next499 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next498] = $chain();
    $receipt = $receiptFor($next498, 'next499-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next498, [$receipt, $receipt], 499);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next499', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next499-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
