<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

if (!function_exists('wal_after_current_checkpoint_stage')) {
    function wal_after_current_checkpoint_stage(int $stage, array $checkpointPlan, array $checkpointReceipts): array
    {
        return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($checkpointPlan, $checkpointReceipts, $stage);
    }
}

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base611 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next611',
    'database_path' => '/srv/www/wp-content/database/wp-next612.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next612.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next612.sqlite-wal',
    'source_token' => 'wp-next612-627-current-source',
    'database_digest' => $digest('next612-627 checkpoint database image'),
    'page_cache_digest' => $digest('next612-627 checkpoint page cache image'),
    'commit_generation' => 612,
    'schema_cookie' => 1612,
    'checkpoint_frame' => 412,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next596_603_next603',
        'seal_after_ready_checkpoint_current_source_next604_611_next611',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next611'],
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

$chain = static function () use ($base611, $receiptFor): array {
    $next612 = wal_after_current_checkpoint_stage(612, $base611, [$receiptFor($base611, 'next612-restart-salt-database-header')]);
    $next613 = wal_after_current_checkpoint_stage(613, $next612, [$receiptFor($next612, 'next613-reader-release-database-digest')]);
    $next614 = wal_after_current_checkpoint_stage(614, $next613, [$receiptFor($next613, 'next614-page-cache-source-token')]);
    $next615 = wal_after_current_checkpoint_stage(615, $next614, [$receiptFor($next614, 'next615-checkpoint-frame-commit-generation')]);
    $next616 = wal_after_current_checkpoint_stage(616, $next615, [$receiptFor($next615, 'next616-schema-cookie-wal-index')]);
    $next617 = wal_after_current_checkpoint_stage(617, $next616, [$receiptFor($next616, 'next617-hot-journal-reader-release')]);
    $next618 = wal_after_current_checkpoint_stage(618, $next617, [$receiptFor($next617, 'next618-database-header-page-cache')]);
    $next619 = wal_after_current_checkpoint_stage(619, $next618, [$receiptFor($next618, 'next619-current-source-seal')]);
    $next620 = wal_after_current_checkpoint_stage(620, $next619, [$receiptFor($next619, 'next620-restart-salt-source-token')]);
    $next621 = wal_after_current_checkpoint_stage(621, $next620, [$receiptFor($next620, 'next621-reader-release-schema-cookie')]);
    $next622 = wal_after_current_checkpoint_stage(622, $next621, [$receiptFor($next621, 'next622-database-digest-page-cache')]);
    $next623 = wal_after_current_checkpoint_stage(623, $next622, [$receiptFor($next622, 'next623-checkpoint-frame-database-header')]);
    $next624 = wal_after_current_checkpoint_stage(624, $next623, [$receiptFor($next623, 'next624-commit-generation-wal-index')]);
    $next625 = wal_after_current_checkpoint_stage(625, $next624, [$receiptFor($next624, 'next625-hot-journal-database-digest')]);
    $next626 = wal_after_current_checkpoint_stage(626, $next625, [$receiptFor($next625, 'next626-wal-index-page-cache')]);
    $next627 = wal_after_current_checkpoint_stage(627, $next626, [$receiptFor($next626, 'next627-current-source-seal')]);

    return [$next612, $next613, $next614, $next615, $next616, $next617, $next618, $next619, $next620, $next621, $next622, $next623, $next624, $next625, $next626, $next627];
};

$tests['wal hot journal savepoint checkpoint current source next612-627 chains after merged next596-611'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        612 => 'verify_after_ready_checkpoint_restart_salt_receipt_database_header_complete',
        613 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        614 => 'verify_after_ready_checkpoint_page_cache_digest_source_token_complete',
        615 => 'verify_after_ready_checkpoint_checkpoint_frame_commit_generation_complete',
        616 => 'verify_after_ready_checkpoint_schema_cookie_wal_index_salt_complete',
        617 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        618 => 'verify_after_ready_checkpoint_database_header_page_cache_complete',
        619 => 'seal_after_ready_checkpoint_current_source_next612_619_complete',
        620 => 'verify_after_ready_checkpoint_restart_salt_source_token_complete',
        621 => 'verify_after_ready_checkpoint_reader_mark_release_schema_cookie_complete',
        622 => 'verify_after_ready_checkpoint_database_digest_page_cache_complete',
        623 => 'verify_after_ready_checkpoint_checkpoint_frame_database_header_complete',
        624 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        625 => 'verify_after_ready_checkpoint_hot_journal_delete_database_digest_complete',
        626 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        627 => 'seal_after_ready_checkpoint_current_source_next620_627_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 612];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next627 = $chainRows[15];
    $t->same(['next627-current-source-seal'], $next627['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next604_611_next611', implode(',', $next627['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next612_619_next619', implode(',', $next627['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next611', implode(',', $next627['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next627', implode(',', $next627['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next612 blocks database header not synced'] = static function (TestRunner $t) use ($base611, $receiptFor): void {
    $receipt = $receiptFor($base611, 'next612-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = wal_after_current_checkpoint_stage(612, $base611, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next612', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next614 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next613] = $chain();
    $receipt = $receiptFor($next613, 'next614-stale-source-token');
    $receipt['source_token'] = 'stale-next614-current-source';
    $record = wal_after_current_checkpoint_stage(614, $next613, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next614', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next619 rejects missing next618 base'] = static function (TestRunner $t) use ($base611, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => wal_after_current_checkpoint_stage(619,
        array_replace($base611, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next617']),
        [$receiptFor($base611, 'next619-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next621 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , $next620] = $chain();
    $receipt = $receiptFor($next620, 'next621-stale-schema-cookie');
    $receipt['schema_cookie'] = 1611;
    $record = wal_after_current_checkpoint_stage(621, $next620, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next621', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next625 blocks hot journal still visible'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next624] = $chain();
    $receipt = $receiptFor($next624, 'next625-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = wal_after_current_checkpoint_stage(625, $next624, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next625', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next627 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next626] = $chain();
    $receipt = $receiptFor($next626, 'next627-duplicate-current-source-seal');
    $record = wal_after_current_checkpoint_stage(627, $next626, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next627', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next627-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;
