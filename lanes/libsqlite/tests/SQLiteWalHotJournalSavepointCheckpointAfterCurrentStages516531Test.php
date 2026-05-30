<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base515 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next515',
    'database_path' => '/srv/www/wp-content/database/wp-next516.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next516.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next516.sqlite-wal',
    'source_token' => 'wp-next516-531-current-source',
    'database_digest' => $digest('next516-531 checkpoint database image'),
    'page_cache_digest' => $digest('next516-531 checkpoint page cache image'),
    'commit_generation' => 515,
    'schema_cookie' => 1515,
    'checkpoint_frame' => 315,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next508_515_next515'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next515'],
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

$chain = static function () use ($base515, $receiptFor): array {
    $next516 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base515, [$receiptFor($base515, 'next516-restart-salt-schema-generation')], 516);
    $next517 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next516, [$receiptFor($next516, 'next517-reader-mark-schema-generation')], 517);
    $next518 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next517, [$receiptFor($next517, 'next518-page-cache-commit-generation')], 518);
    $next519 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next518, [$receiptFor($next518, 'next519-schema-cookie-checkpoint-frame')], 519);
    $next520 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next519, [$receiptFor($next519, 'next520-generation-source-token')], 520);
    $next521 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next520, [$receiptFor($next520, 'next521-hot-journal-checkpoint-frame')], 521);
    $next522 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next521, [$receiptFor($next521, 'next522-wal-index-checkpoint-frame')], 522);
    $next523 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next522, [$receiptFor($next522, 'next523-current-source-seal')], 523);
    $next524 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next523, [$receiptFor($next523, 'next524-restart-salt-database-header')], 524);
    $next525 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next524, [$receiptFor($next524, 'next525-reader-mark-wal-index-salt')], 525);
    $next526 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next525, [$receiptFor($next525, 'next526-page-cache-reader-release')], 526);
    $next527 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next526, [$receiptFor($next526, 'next527-schema-cookie-hot-journal')], 527);
    $next528 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next527, [$receiptFor($next527, 'next528-generation-database-header')], 528);
    $next529 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next528, [$receiptFor($next528, 'next529-hot-journal-wal-index-salt')], 529);
    $next530 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next529, [$receiptFor($next529, 'next530-wal-index-reader-release')], 530);
    $next531 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next530, [$receiptFor($next530, 'next531-current-source-seal')], 531);

    return [$next516, $next517, $next518, $next519, $next520, $next521, $next522, $next523, $next524, $next525, $next526, $next527, $next528, $next529, $next530, $next531];
};

$tests['wal hot journal savepoint checkpoint current source next516-531 chains after merged next500-515'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        516 => 'verify_after_ready_checkpoint_restart_salt_receipt_schema_generation_complete',
        517 => 'verify_after_ready_checkpoint_reader_mark_release_schema_generation_complete',
        518 => 'verify_after_ready_checkpoint_page_cache_digest_commit_generation_complete',
        519 => 'verify_after_ready_checkpoint_schema_cookie_checkpoint_frame_complete',
        520 => 'verify_after_ready_checkpoint_commit_generation_source_token_complete',
        521 => 'verify_after_ready_checkpoint_hot_journal_delete_checkpoint_frame_complete',
        522 => 'verify_after_ready_checkpoint_wal_index_salt_checkpoint_frame_complete',
        523 => 'seal_after_ready_checkpoint_current_source_next516_523_complete',
        524 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_header_complete',
        525 => 'verify_after_ready_checkpoint_reader_mark_release_wal_index_salt_complete',
        526 => 'verify_after_ready_checkpoint_page_cache_digest_reader_release_complete',
        527 => 'verify_after_ready_checkpoint_schema_cookie_hot_journal_absence_complete',
        528 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        529 => 'verify_after_ready_checkpoint_hot_journal_delete_wal_index_salt_complete',
        530 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        531 => 'seal_after_ready_checkpoint_current_source_next524_531_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 516];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next531 = $chainRows[15];
    $t->same(['next531-current-source-seal'], $next531['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next508_515_next515', implode(',', $next531['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next516_523_next523', implode(',', $next531['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next515', implode(',', $next531['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next531', implode(',', $next531['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next516 blocks source token mismatch'] = static function (TestRunner $t) use ($base515, $receiptFor): void {
    $receipt = $receiptFor($base515, 'next516-stale-source-token');
    $receipt['source_token'] = 'wp-next516-531-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base515, [$receipt], 516);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next516', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next518 blocks reader marks not released'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next517] = $chain();
    $receipt = $receiptFor($next517, 'next518-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next517, [$receipt], 518);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next518', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next523 rejects missing next522 base'] = static function (TestRunner $t) use ($base515, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base515, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next521'], 523),
        [$receiptFor($base515, 'next523-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next526 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next525] = $chain();
    $receipt = $receiptFor($next525, 'next526-database-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next525, [$receipt], 526);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next526', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next527 blocks wal index salt unsynced'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next526] = $chain();
    $receipt = $receiptFor($next526, 'next527-wal-index-salt-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next526, [$receipt], 527);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next527', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next529 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next528] = $chain();
    $receipt = $receiptFor($next528, 'next529-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next528, [$receipt], 529);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next529', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next531 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next530] = $chain();
    $receipt = $receiptFor($next530, 'next531-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next530, [$receipt, $receipt], 531);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next531', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next531-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
