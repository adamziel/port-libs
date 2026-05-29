<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base307 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next307',
    'database_path' => '/srv/www/wp-content/database/wp-next308.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next308.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next308.sqlite-wal',
    'source_token' => 'wp-next308-311-current-source',
    'database_digest' => $digest('next308-311 checkpoint database image'),
    'page_cache_digest' => $digest('next308-311 checkpoint page cache image'),
    'commit_generation' => 311,
    'schema_cookie' => 1311,
    'checkpoint_frame' => 111,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next304_307_next307'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next307'],
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

$chain = static function () use ($base307, $receiptFor): array {
    $next308 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next308AfterCurrentCheckpoint(
        $base307,
        [$receiptFor($base307, 'next308-wal-frame-epoch')]
    );
    $next309 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next309AfterCurrentCheckpoint(
        $next308,
        [$receiptFor($next308, 'next309-reader-epoch-release')]
    );
    $next310 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next310AfterCurrentCheckpoint(
        $next309,
        [$receiptFor($next309, 'next310-savepoint-hot-journal-absence')]
    );
    $next311 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next311AfterCurrentCheckpoint(
        $next310,
        [$receiptFor($next310, 'next311-current-source-seal')]
    );

    return [$next308, $next309, $next310, $next311];
};

$tests['wal hot journal savepoint checkpoint current source next308-311 chains after ready next304-307'] = static function (TestRunner $t) use ($chain): void {
    [$next308, $next309, $next310, $next311] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next308', $next308['status']);
    $t->same('verify_after_ready_checkpoint_wal_frame_epoch_complete', $next308['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next309', $next309['status']);
    $t->same('verify_after_ready_checkpoint_reader_epoch_release_complete', $next309['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next310', $next310['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_hot_journal_absence_complete', $next310['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next311', $next311['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next308_311_complete', $next311['reason']);
    $t->same(['next311-current-source-seal'], $next311['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_frame_epoch_next308', implode(',', $next311['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next307', implode(',', $next311['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next311', implode(',', $next311['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next308 blocks generation mismatch'] = static function (TestRunner $t) use ($base307, $receiptFor): void {
    $receipt = $receiptFor($base307, 'next308-generation-mismatch');
    $receipt['commit_generation'] = 310;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next308AfterCurrentCheckpoint($base307, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next308', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next310 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next309] = $chain();
    $receipt = $receiptFor($next309, 'next310-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next310AfterCurrentCheckpoint($next309, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next310', $record['status']);
    $t->contains('checkpoint_reader_marks_not_released', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next311 rejects missing next310 base'] = static function (TestRunner $t) use ($base307, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next311AfterCurrentCheckpoint(
        array_replace($base307, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next309']),
        [$receiptFor($base307, 'next311-current-source-seal')]
    ));
};

return $tests;
