<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base547 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next547',
    'database_path' => '/srv/www/wp-content/database/wp-next548.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next548.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next548.sqlite-wal',
    'source_token' => 'wp-next548-563-current-source',
    'database_digest' => $digest('next548-563 checkpoint database image'),
    'page_cache_digest' => $digest('next548-563 checkpoint page cache image'),
    'commit_generation' => 547,
    'schema_cookie' => 1547,
    'checkpoint_frame' => 347,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next532_539_next539',
        'seal_after_ready_checkpoint_current_source_next540_547_next547',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next547'],
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

$chain = static function () use ($base547, $receiptFor): array {
    $next548 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base547, [$receiptFor($base547, 'next548-restart-salt-database-digest')], 548);
    $next549 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next548, [$receiptFor($next548, 'next549-reader-mark-source-token')], 549);
    $next550 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next549, [$receiptFor($next549, 'next550-page-cache-commit-generation')], 550);
    $next551 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next550, [$receiptFor($next550, 'next551-schema-cookie-checkpoint-frame')], 551);
    $next552 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next551, [$receiptFor($next551, 'next552-commit-generation-database-header')], 552);
    $next553 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next552, [$receiptFor($next552, 'next553-hot-journal-wal-index-salt')], 553);
    $next554 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next553, [$receiptFor($next553, 'next554-wal-index-reader-release')], 554);
    $next555 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next554, [$receiptFor($next554, 'next555-current-source-seal')], 555);
    $next556 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next555, [$receiptFor($next555, 'next556-restart-salt-checkpoint-frame')], 556);
    $next557 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next556, [$receiptFor($next556, 'next557-reader-mark-page-cache')], 557);
    $next558 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next557, [$receiptFor($next557, 'next558-page-cache-database-header')], 558);
    $next559 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next558, [$receiptFor($next558, 'next559-schema-cookie-wal-index-salt')], 559);
    $next560 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next559, [$receiptFor($next559, 'next560-commit-generation-reader-release')], 560);
    $next561 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next560, [$receiptFor($next560, 'next561-hot-journal-source-token')], 561);
    $next562 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next561, [$receiptFor($next561, 'next562-wal-index-database-digest')], 562);
    $next563 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next562, [$receiptFor($next562, 'next563-current-source-seal')], 563);

    return [$next548, $next549, $next550, $next551, $next552, $next553, $next554, $next555, $next556, $next557, $next558, $next559, $next560, $next561, $next562, $next563];
};

$tests['wal hot journal savepoint checkpoint current source next548-563 chains after merged next532-547'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        548 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_digest_complete',
        549 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        550 => 'verify_after_ready_checkpoint_page_cache_digest_commit_generation_complete',
        551 => 'verify_after_ready_checkpoint_schema_cookie_checkpoint_frame_complete',
        552 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        553 => 'verify_after_ready_checkpoint_hot_journal_delete_wal_index_salt_complete',
        554 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        555 => 'seal_after_ready_checkpoint_current_source_next548_555_complete',
        556 => 'verify_after_ready_checkpoint_restart_salt_receipt_checkpoint_frame_complete',
        557 => 'verify_after_ready_checkpoint_reader_mark_release_page_cache_digest_complete',
        558 => 'verify_after_ready_checkpoint_page_cache_digest_database_header_complete',
        559 => 'verify_after_ready_checkpoint_schema_cookie_wal_index_salt_complete',
        560 => 'verify_after_ready_checkpoint_commit_generation_reader_release_complete',
        561 => 'verify_after_ready_checkpoint_hot_journal_delete_source_token_complete',
        562 => 'verify_after_ready_checkpoint_wal_index_salt_database_digest_complete',
        563 => 'seal_after_ready_checkpoint_current_source_next556_563_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 548];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next563 = $chainRows[15];
    $t->same(['next563-current-source-seal'], $next563['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next540_547_next547', implode(',', $next563['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next548_555_next555', implode(',', $next563['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next547', implode(',', $next563['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next563', implode(',', $next563['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next548 blocks page cache mismatch'] = static function (TestRunner $t) use ($base547, $receiptFor): void {
    $receipt = $receiptFor($base547, 'next548-stale-page-cache');
    $receipt['page_cache_digest'] = hash('sha256', 'stale page cache');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base547, [$receipt], 548);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next548', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next551 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next550] = $chain();
    $receipt = $receiptFor($next550, 'next551-stale-checkpoint-frame');
    $receipt['checkpoint_frame'] = 346;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next550, [$receipt], 551);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next551', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next555 rejects missing next554 base'] = static function (TestRunner $t) use ($base547, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base547, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next553'], 555),
        [$receiptFor($base547, 'next555-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next558 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next557] = $chain();
    $receipt = $receiptFor($next557, 'next558-database-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next557, [$receipt], 558);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next558', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next561 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next560] = $chain();
    $receipt = $receiptFor($next560, 'next561-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next560, [$receipt], 561);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next561', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next563 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next562] = $chain();
    $receipt = $receiptFor($next562, 'next563-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next562, [$receipt, $receipt], 563);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next563', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next563-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
