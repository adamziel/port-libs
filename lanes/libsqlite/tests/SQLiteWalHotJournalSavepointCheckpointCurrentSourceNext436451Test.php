<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base435 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next435',
    'database_path' => '/srv/www/wp-content/database/wp-next436.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next436.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next436.sqlite-wal',
    'source_token' => 'wp-next436-451-current-source',
    'database_digest' => $digest('next436-451 checkpoint database image'),
    'page_cache_digest' => $digest('next436-451 checkpoint page cache image'),
    'commit_generation' => 451,
    'schema_cookie' => 1451,
    'checkpoint_frame' => 251,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next428_435_next435'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next435'],
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

$chain = static function () use ($base435, $receiptFor): array {
    $next436 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next436AfterCurrentCheckpoint($base435, [$receiptFor($base435, 'next436-restart-salt-database-digest')]);
    $next437 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next437AfterCurrentCheckpoint($next436, [$receiptFor($next436, 'next437-reader-mark-database-digest')]);
    $next438 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next438AfterCurrentCheckpoint($next437, [$receiptFor($next437, 'next438-page-cache-source-token')]);
    $next439 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next439AfterCurrentCheckpoint($next438, [$receiptFor($next438, 'next439-schema-cookie-source-token')]);
    $next440 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next440AfterCurrentCheckpoint($next439, [$receiptFor($next439, 'next440-generation-frame-digest')]);
    $next441 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next441AfterCurrentCheckpoint($next440, [$receiptFor($next440, 'next441-hot-journal-absence-source')]);
    $next442 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next442AfterCurrentCheckpoint($next441, [$receiptFor($next441, 'next442-wal-index-frame-digest')]);
    $next443 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next443AfterCurrentCheckpoint($next442, [$receiptFor($next442, 'next443-current-source-seal')]);
    $next444 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next444AfterCurrentCheckpoint($next443, [$receiptFor($next443, 'next444-restart-salt-schema-cookie')]);
    $next445 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next445AfterCurrentCheckpoint($next444, [$receiptFor($next444, 'next445-reader-mark-schema-cookie')]);
    $next446 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next446AfterCurrentCheckpoint($next445, [$receiptFor($next445, 'next446-page-cache-frame')]);
    $next447 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next447AfterCurrentCheckpoint($next446, [$receiptFor($next446, 'next447-schema-cookie-frame')]);
    $next448 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next448AfterCurrentCheckpoint($next447, [$receiptFor($next447, 'next448-generation-source-frame')]);
    $next449 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next449AfterCurrentCheckpoint($next448, [$receiptFor($next448, 'next449-hot-journal-delete-source-frame')]);
    $next450 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next450AfterCurrentCheckpoint($next449, [$receiptFor($next449, 'next450-wal-index-source-frame')]);
    $next451 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next451AfterCurrentCheckpoint($next450, [$receiptFor($next450, 'next451-current-source-seal')]);

    return [$next436, $next437, $next438, $next439, $next440, $next441, $next442, $next443, $next444, $next445, $next446, $next447, $next448, $next449, $next450, $next451];
};

$tests['wal hot journal savepoint checkpoint current source next436-451 chains after merged next420-435'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        436 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_digest_complete',
        437 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        438 => 'verify_after_ready_checkpoint_page_cache_digest_source_token_complete',
        439 => 'verify_after_ready_checkpoint_schema_cookie_source_token_complete',
        440 => 'verify_after_ready_checkpoint_commit_generation_frame_digest_complete',
        441 => 'verify_after_ready_checkpoint_hot_journal_absence_source_token_complete',
        442 => 'verify_after_ready_checkpoint_wal_index_salt_frame_digest_complete',
        443 => 'seal_after_ready_checkpoint_current_source_next436_443_complete',
        444 => 'verify_after_ready_checkpoint_restart_salt_receipt_schema_cookie_complete',
        445 => 'verify_after_ready_checkpoint_reader_mark_release_schema_cookie_complete',
        446 => 'verify_after_ready_checkpoint_page_cache_digest_frame_complete',
        447 => 'verify_after_ready_checkpoint_schema_cookie_digest_frame_complete',
        448 => 'verify_after_ready_checkpoint_commit_generation_source_frame_complete',
        449 => 'verify_after_ready_checkpoint_hot_journal_delete_source_frame_complete',
        450 => 'verify_after_ready_checkpoint_wal_index_salt_source_frame_complete',
        451 => 'seal_after_ready_checkpoint_current_source_next444_451_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 436];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next451 = $chainRows[15];
    $t->same(['next451-current-source-seal'], $next451['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next428_435_next435', implode(',', $next451['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next436_443_next443', implode(',', $next451['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next435', implode(',', $next451['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next451', implode(',', $next451['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next436 blocks database digest mismatch'] = static function (TestRunner $t) use ($base435, $receiptFor, $digest): void {
    $receipt = $receiptFor($base435, 'next436-stale-database-digest');
    $receipt['database_digest'] = $digest('stale database digest next436');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next436AfterCurrentCheckpoint($base435, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next436', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next440 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , $next439] = $chain();
    $receipt = $receiptFor($next439, 'next440-stale-frame');
    $receipt['checkpoint_frame'] = 250;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next440AfterCurrentCheckpoint($next439, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next440', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next443 rejects missing next442 base'] = static function (TestRunner $t) use ($base435, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next443AfterCurrentCheckpoint(
        array_replace($base435, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next441']),
        [$receiptFor($base435, 'next443-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next445 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , $next443] = $chain();
    $next444 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next444AfterCurrentCheckpoint($next443, [$receiptFor($next443, 'next444-restart-salt-schema-cookie')]);
    $receipt = $receiptFor($next444, 'next445-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next445AfterCurrentCheckpoint($next444, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next445', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next448 blocks generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , $next447] = $chain();
    $receipt = $receiptFor($next447, 'next448-stale-generation');
    $receipt['commit_generation'] = 450;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next448AfterCurrentCheckpoint($next447, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next448', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next451 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next450] = $chain();
    $receipt = $receiptFor($next450, 'next451-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next451AfterCurrentCheckpoint($next450, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next451', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next451-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
