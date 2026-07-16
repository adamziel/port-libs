<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base579 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next579',
    'database_path' => '/srv/www/wp-content/database/wp-next580.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next580.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next580.sqlite-wal',
    'source_token' => 'wp-next580-595-current-source',
    'database_digest' => $digest('next580-595 checkpoint database image'),
    'page_cache_digest' => $digest('next580-595 checkpoint page cache image'),
    'commit_generation' => 579,
    'schema_cookie' => 1579,
    'checkpoint_frame' => 379,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next564_571_next571',
        'seal_after_ready_checkpoint_current_source_next572_579_next579',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next579'],
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

$chain = static function () use ($base579, $receiptFor): array {
    $next580 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base579, [$receiptFor($base579, 'next580-restart-salt-source-token')], 580);
    $next581 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next580, [$receiptFor($next580, 'next581-reader-release-database-digest')], 581);
    $next582 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next581, [$receiptFor($next581, 'next582-page-cache-schema-cookie')], 582);
    $next583 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next582, [$receiptFor($next582, 'next583-checkpoint-frame-wal-index')], 583);
    $next584 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next583, [$receiptFor($next583, 'next584-commit-generation-database-header')], 584);
    $next585 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next584, [$receiptFor($next584, 'next585-hot-journal-checkpoint-frame')], 585);
    $next586 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next585, [$receiptFor($next585, 'next586-wal-index-reader-release')], 586);
    $next587 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next586, [$receiptFor($next586, 'next587-current-source-seal')], 587);
    $next588 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next587, [$receiptFor($next587, 'next588-restart-salt-page-cache')], 588);
    $next589 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next588, [$receiptFor($next588, 'next589-reader-release-schema-cookie')], 589);
    $next590 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next589, [$receiptFor($next589, 'next590-database-digest-wal-index')], 590);
    $next591 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next590, [$receiptFor($next590, 'next591-checkpoint-frame-database-header')], 591);
    $next592 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next591, [$receiptFor($next591, 'next592-commit-generation-reader-release')], 592);
    $next593 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next592, [$receiptFor($next592, 'next593-hot-journal-source-token')], 593);
    $next594 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next593, [$receiptFor($next593, 'next594-wal-index-page-cache')], 594);
    $next595 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next594, [$receiptFor($next594, 'next595-current-source-seal')], 595);

    return [$next580, $next581, $next582, $next583, $next584, $next585, $next586, $next587, $next588, $next589, $next590, $next591, $next592, $next593, $next594, $next595];
};

$tests['wal hot journal savepoint checkpoint current source next580-595 chains after merged next564-579'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        580 => 'verify_after_ready_checkpoint_restart_salt_receipt_source_token_complete',
        581 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        582 => 'verify_after_ready_checkpoint_page_cache_digest_schema_cookie_complete',
        583 => 'verify_after_ready_checkpoint_checkpoint_frame_wal_index_salt_complete',
        584 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        585 => 'verify_after_ready_checkpoint_hot_journal_absence_checkpoint_frame_complete',
        586 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        587 => 'seal_after_ready_checkpoint_current_source_next580_587_complete',
        588 => 'verify_after_ready_checkpoint_restart_salt_receipt_page_cache_complete',
        589 => 'verify_after_ready_checkpoint_reader_mark_release_schema_cookie_complete',
        590 => 'verify_after_ready_checkpoint_database_digest_wal_index_salt_complete',
        591 => 'verify_after_ready_checkpoint_checkpoint_frame_database_header_complete',
        592 => 'verify_after_ready_checkpoint_commit_generation_reader_release_complete',
        593 => 'verify_after_ready_checkpoint_hot_journal_delete_source_token_complete',
        594 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_digest_complete',
        595 => 'seal_after_ready_checkpoint_current_source_next588_595_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 580];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next595 = $chainRows[15];
    $t->same(['next595-current-source-seal'], $next595['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next572_579_next579', implode(',', $next595['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next580_587_next587', implode(',', $next595['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next579', implode(',', $next595['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next595', implode(',', $next595['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next580 blocks source token mismatch'] = static function (TestRunner $t) use ($base579, $receiptFor): void {
    $receipt = $receiptFor($base579, 'next580-stale-source-token');
    $receipt['source_token'] = 'wp-next580-595-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base579, [$receipt], 580);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next580', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next582 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next581] = $chain();
    $receipt = $receiptFor($next581, 'next582-stale-page-cache');
    $receipt['page_cache_digest'] = hash('sha256', 'stale page cache image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next581, [$receipt], 582);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next582', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next587 rejects missing next586 base'] = static function (TestRunner $t) use ($base579, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base579, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next585'], 587),
        [$receiptFor($base579, 'next587-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next589 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , $next587] = $chain();
    $next588 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next587, [$receiptFor($next587, 'next588-restart-salt-page-cache')], 588);
    $receipt = $receiptFor($next588, 'next589-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next588, [$receipt], 589);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next589', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next591 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next590] = $chain();
    $receipt = $receiptFor($next590, 'next591-database-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next590, [$receipt], 591);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next591', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next595 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next594] = $chain();
    $receipt = $receiptFor($next594, 'next595-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next594, [$receipt, $receipt], 595);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next595', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next595-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
