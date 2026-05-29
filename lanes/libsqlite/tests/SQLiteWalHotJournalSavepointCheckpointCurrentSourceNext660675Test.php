<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base659 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next659',
    'database_path' => '/srv/www/wp-content/database/wp-next660.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next660.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next660.sqlite-wal',
    'source_token' => 'wp-next660-675-current-source',
    'database_digest' => $digest('next660-675 checkpoint database image'),
    'page_cache_digest' => $digest('next660-675 checkpoint page cache image'),
    'commit_generation' => 660,
    'schema_cookie' => 1660,
    'checkpoint_frame' => 460,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next644_651_next651',
        'seal_after_ready_checkpoint_current_source_next652_659_next659',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next659'],
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

$chain = static function () use ($base659, $receiptFor): array {
    $next660 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next660AfterCurrentCheckpoint($base659, [$receiptFor($base659, 'next660-restart-salt-page-cache')]);
    $next661 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next661AfterCurrentCheckpoint($next660, [$receiptFor($next660, 'next661-reader-release-schema-cookie')]);
    $next662 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next662AfterCurrentCheckpoint($next661, [$receiptFor($next661, 'next662-database-digest-wal-index')]);
    $next663 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next663AfterCurrentCheckpoint($next662, [$receiptFor($next662, 'next663-commit-generation-source-token')]);
    $next664 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next664AfterCurrentCheckpoint($next663, [$receiptFor($next663, 'next664-checkpoint-frame-database-header')]);
    $next665 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next665AfterCurrentCheckpoint($next664, [$receiptFor($next664, 'next665-hot-journal-wal-index')]);
    $next666 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next666AfterCurrentCheckpoint($next665, [$receiptFor($next665, 'next666-page-cache-reader-release')]);
    $next667 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next667AfterCurrentCheckpoint($next666, [$receiptFor($next666, 'next667-current-source-seal')]);
    $next668 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next668AfterCurrentCheckpoint($next667, [$receiptFor($next667, 'next668-restart-salt-commit-generation')]);
    $next669 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next669AfterCurrentCheckpoint($next668, [$receiptFor($next668, 'next669-reader-release-page-cache')]);
    $next670 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next670AfterCurrentCheckpoint($next669, [$receiptFor($next669, 'next670-database-header-source-token')]);
    $next671 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next671AfterCurrentCheckpoint($next670, [$receiptFor($next670, 'next671-schema-cookie-checkpoint-frame')]);
    $next672 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next672AfterCurrentCheckpoint($next671, [$receiptFor($next671, 'next672-wal-index-database-digest')]);
    $next673 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next673AfterCurrentCheckpoint($next672, [$receiptFor($next672, 'next673-hot-journal-reader-release')]);
    $next674 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next674AfterCurrentCheckpoint($next673, [$receiptFor($next673, 'next674-page-cache-commit-generation')]);
    $next675 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next675AfterCurrentCheckpoint($next674, [$receiptFor($next674, 'next675-current-source-seal')]);

    return [$next660, $next661, $next662, $next663, $next664, $next665, $next666, $next667, $next668, $next669, $next670, $next671, $next672, $next673, $next674, $next675];
};

$tests['wal hot journal savepoint checkpoint current source next660-675 chains after merged next644-659'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        660 => 'verify_after_ready_checkpoint_restart_salt_page_cache_complete',
        661 => 'verify_after_ready_checkpoint_reader_mark_release_schema_cookie_complete',
        662 => 'verify_after_ready_checkpoint_database_digest_wal_index_salt_complete',
        663 => 'verify_after_ready_checkpoint_commit_generation_source_token_complete',
        664 => 'verify_after_ready_checkpoint_checkpoint_frame_database_header_complete',
        665 => 'verify_after_ready_checkpoint_hot_journal_absence_wal_index_salt_complete',
        666 => 'verify_after_ready_checkpoint_page_cache_reader_release_complete',
        667 => 'seal_after_ready_checkpoint_current_source_next660_667_complete',
        668 => 'verify_after_ready_checkpoint_restart_salt_commit_generation_complete',
        669 => 'verify_after_ready_checkpoint_reader_mark_release_page_cache_complete',
        670 => 'verify_after_ready_checkpoint_database_header_source_token_complete',
        671 => 'verify_after_ready_checkpoint_schema_cookie_checkpoint_frame_complete',
        672 => 'verify_after_ready_checkpoint_wal_index_salt_database_digest_complete',
        673 => 'verify_after_ready_checkpoint_hot_journal_delete_reader_release_complete',
        674 => 'verify_after_ready_checkpoint_page_cache_commit_generation_complete',
        675 => 'seal_after_ready_checkpoint_current_source_next668_675_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 660];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next675 = $chainRows[15];
    $t->same(['next675-current-source-seal'], $next675['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next652_659_next659', implode(',', $next675['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next660_667_next667', implode(',', $next675['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next659', implode(',', $next675['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next675', implode(',', $next675['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next660 blocks visible hot journal'] = static function (TestRunner $t) use ($base659, $receiptFor): void {
    $receipt = $receiptFor($base659, 'next660-visible-hot-journal');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next660AfterCurrentCheckpoint($base659, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next660', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next662 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next661] = $chain();
    $receipt = $receiptFor($next661, 'next662-stale-page-cache-digest');
    $receipt['page_cache_digest'] = hash('sha256', 'stale next662 page cache digest');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next662AfterCurrentCheckpoint($next661, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next662', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next667 rejects missing next666 base'] = static function (TestRunner $t) use ($base659, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next667AfterCurrentCheckpoint(
        array_replace($base659, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next665']),
        [$receiptFor($base659, 'next667-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next669 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , $next668] = $chain();
    $receipt = $receiptFor($next668, 'next669-stale-schema-cookie');
    $receipt['schema_cookie'] = 6699;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next669AfterCurrentCheckpoint($next668, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next669', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next673 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next672] = $chain();
    $receipt = $receiptFor($next672, 'next673-stale-checkpoint-frame');
    $receipt['checkpoint_frame'] = 6739;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next673AfterCurrentCheckpoint($next672, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next673', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next675 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next674] = $chain();
    $receipt = $receiptFor($next674, 'next675-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next675AfterCurrentCheckpoint($next674, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next675', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next675-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
