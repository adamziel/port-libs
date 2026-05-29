<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base531 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next531',
    'database_path' => '/srv/www/wp-content/database/wp-next532.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next532.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next532.sqlite-wal',
    'source_token' => 'wp-next532-547-current-source',
    'database_digest' => $digest('next532-547 checkpoint database image'),
    'page_cache_digest' => $digest('next532-547 checkpoint page cache image'),
    'commit_generation' => 531,
    'schema_cookie' => 1531,
    'checkpoint_frame' => 331,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next524_531_next531'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next531'],
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

$chain = static function () use ($base531, $receiptFor): array {
    $next532 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next532AfterCurrentCheckpoint($base531, [$receiptFor($base531, 'next532-restart-salt-source-token')]);
    $next533 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next533AfterCurrentCheckpoint($next532, [$receiptFor($next532, 'next533-reader-mark-database-digest')]);
    $next534 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next534AfterCurrentCheckpoint($next533, [$receiptFor($next533, 'next534-page-cache-schema-cookie')]);
    $next535 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next535AfterCurrentCheckpoint($next534, [$receiptFor($next534, 'next535-schema-cookie-commit-generation')]);
    $next536 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next536AfterCurrentCheckpoint($next535, [$receiptFor($next535, 'next536-generation-checkpoint-frame')]);
    $next537 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next537AfterCurrentCheckpoint($next536, [$receiptFor($next536, 'next537-hot-journal-database-digest')]);
    $next538 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next538AfterCurrentCheckpoint($next537, [$receiptFor($next537, 'next538-wal-index-source-token')]);
    $next539 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next539AfterCurrentCheckpoint($next538, [$receiptFor($next538, 'next539-current-source-seal')]);
    $next540 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next540AfterCurrentCheckpoint($next539, [$receiptFor($next539, 'next540-restart-salt-reader-release')]);
    $next541 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next541AfterCurrentCheckpoint($next540, [$receiptFor($next540, 'next541-reader-mark-checkpoint-frame')]);
    $next542 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next542AfterCurrentCheckpoint($next541, [$receiptFor($next541, 'next542-page-cache-wal-index-salt')]);
    $next543 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next543AfterCurrentCheckpoint($next542, [$receiptFor($next542, 'next543-schema-cookie-database-header')]);
    $next544 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next544AfterCurrentCheckpoint($next543, [$receiptFor($next543, 'next544-generation-hot-journal')]);
    $next545 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next545AfterCurrentCheckpoint($next544, [$receiptFor($next544, 'next545-hot-journal-reader-release')]);
    $next546 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next546AfterCurrentCheckpoint($next545, [$receiptFor($next545, 'next546-wal-index-database-header')]);
    $next547 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next547AfterCurrentCheckpoint($next546, [$receiptFor($next546, 'next547-current-source-seal')]);

    return [$next532, $next533, $next534, $next535, $next536, $next537, $next538, $next539, $next540, $next541, $next542, $next543, $next544, $next545, $next546, $next547];
};

$tests['wal hot journal savepoint checkpoint current source next532-547 chains after merged next516-531'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        532 => 'verify_after_ready_checkpoint_restart_salt_receipt_source_token_complete',
        533 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        534 => 'verify_after_ready_checkpoint_page_cache_digest_schema_cookie_complete',
        535 => 'verify_after_ready_checkpoint_schema_cookie_commit_generation_complete',
        536 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        537 => 'verify_after_ready_checkpoint_hot_journal_delete_database_digest_complete',
        538 => 'verify_after_ready_checkpoint_wal_index_salt_source_token_complete',
        539 => 'seal_after_ready_checkpoint_current_source_next532_539_complete',
        540 => 'verify_after_ready_checkpoint_restart_salt_receipt_reader_release_complete',
        541 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        542 => 'verify_after_ready_checkpoint_page_cache_digest_wal_index_salt_complete',
        543 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        544 => 'verify_after_ready_checkpoint_commit_generation_hot_journal_absence_complete',
        545 => 'verify_after_ready_checkpoint_hot_journal_delete_reader_release_complete',
        546 => 'verify_after_ready_checkpoint_wal_index_salt_database_header_complete',
        547 => 'seal_after_ready_checkpoint_current_source_next540_547_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 532];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next547 = $chainRows[15];
    $t->same(['next547-current-source-seal'], $next547['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next524_531_next531', implode(',', $next547['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next532_539_next539', implode(',', $next547['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next531', implode(',', $next547['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next547', implode(',', $next547['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next532 blocks database digest mismatch'] = static function (TestRunner $t) use ($base531, $receiptFor): void {
    $receipt = $receiptFor($base531, 'next532-stale-database-digest');
    $receipt['database_digest'] = hash('sha256', 'stale database');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next532AfterCurrentCheckpoint($base531, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next532', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next535 blocks commit generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next534] = $chain();
    $receipt = $receiptFor($next534, 'next535-stale-commit-generation');
    $receipt['commit_generation'] = 530;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next535AfterCurrentCheckpoint($next534, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next535', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next539 rejects missing next538 base'] = static function (TestRunner $t) use ($base531, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next539AfterCurrentCheckpoint(
        array_replace($base531, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next537']),
        [$receiptFor($base531, 'next539-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next542 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , $next541] = $chain();
    $receipt = $receiptFor($next541, 'next542-wal-index-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next542AfterCurrentCheckpoint($next541, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next542', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next543 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next542] = $chain();
    $receipt = $receiptFor($next542, 'next543-database-header-not-synced');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next543AfterCurrentCheckpoint($next542, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next543', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next545 blocks reader marks not released'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next544] = $chain();
    $receipt = $receiptFor($next544, 'next545-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next545AfterCurrentCheckpoint($next544, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next545', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next547 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next546] = $chain();
    $receipt = $receiptFor($next546, 'next547-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next547AfterCurrentCheckpoint($next546, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next547', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next547-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
