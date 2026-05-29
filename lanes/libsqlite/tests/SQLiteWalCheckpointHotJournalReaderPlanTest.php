<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-hot-journal-reader';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce = 0x12200001) use ($pageSize, $sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (array $frames, int $checkpointSequence = 122, int $salt1 = 0x12212201, int $salt2 = 0x12212202) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$clean1 = $page('hot-journal-reader clean schema before interrupted import');
$clean2 = $page('hot-journal-reader clean wp_options before interrupted import');
$clean3 = $page('hot-journal-reader clean autoload index before interrupted import');
$clean4 = $page('hot-journal-reader clean transients before interrupted import');
$dirty1 = $page('hot-journal-reader dirty schema from interrupted import');
$dirty2 = $page('hot-journal-reader dirty wp_options from interrupted import');
$dirty3 = $page('hot-journal-reader dirty autoload index from interrupted import');
$dirty4 = $page('hot-journal-reader dirty transients from interrupted import');
$databaseBytes = $dirty1 . $dirty2 . $dirty3 . $dirty4;
$journalBytes = $makeJournal([
    1 => $clean1,
    2 => $clean2,
    3 => $clean3,
    4 => $clean4,
], 4);
$walBytes = $makeWal([
    [2, 4, 'hot-journal-reader wal committed siteurl after hot recovery'],
    [3, 0, 'hot-journal-reader wal draft plugin autoload'],
    [4, 4, 'hot-journal-reader wal committed transient cleanup'],
    [2, 4, 'hot-journal-reader wal committed option retry tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = static fn (string $mode = 'restart', ?int $reader = 2, array $pages = [1, 2, 3, 4], bool $reserved = false, bool $requiresSuper = false, ?bool $superExists = null): array => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan(
    $databasePath,
    $databaseBytes,
    $journalBytes,
    $wal,
    $walBytes,
    $pages,
    $mode,
    $reader,
    $reserved,
    $requiresSuper,
    $superExists
);

$restart = static fn (): array => $plan();
$released = static fn (): array => $plan('restart', null);
$truncate = static fn (): array => $plan('truncate', null);
$reserved = static fn (): array => $plan('restart', 2, [1, 2, 3, 4], true);
$missingSuper = static fn (): array => $plan('restart', 2, [1, 2, 3, 4], false, true, false);
$presentSuper = static fn (): array => $plan('restart', 2, [1, 2, 3, 4], false, true, true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-checkpoint-hot-journal-reader-hot-journal-reader'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovery_precedes_wal_reader_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 2],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'hot reason' => [static fn (): mixed => $restart()['hot_journal_reason'], 'hot_journal_recovery_required'],
    'journal action' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'recovered database bytes length' => [static fn (): mixed => $restart()['recovered_database_bytes_length'], 2048],
    'hot restored pages' => [static fn (): mixed => $restart()['hot_restored_page_numbers'], [1, 2, 3, 4]],
    'journal pages' => [static fn (): mixed => $restart()['journal_page_numbers'], [1, 2, 3, 4]],
    'pinned busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'pinned wal bytes length' => [static fn (): mixed => $restart()['pinned_wal_bytes_length'], strlen($walBytes)],
    'released wal bytes length' => [static fn (): mixed => $restart()['released_wal_bytes_length'], 32],
    'reader sources' => [static fn (): mixed => $restart()['reader_sources'], ['database', 'wal', 'database', 'database']],
    'pinned sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['database', 'wal', 'wal', 'wal']],
    'released sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database']],
    'reader database count' => [static fn (): mixed => $restart()['reader_source_counts']['database'], 3],
    'reader wal count' => [static fn (): mixed => $restart()['reader_source_counts']['wal'], 1],
    'pinned database count' => [static fn (): mixed => $restart()['pinned_next_source_counts']['database'], 1],
    'released database count' => [static fn (): mixed => $restart()['released_next_source_counts']['database'], 4],
    'row count' => [static fn (): mixed => count($restart()['rows']), 4],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4]],
    'row one hot label' => [static fn (): mixed => $restart()['rows'][0]['hot_current_label'], 'hot-journal-reader clean schema before interrupted import'],
    'row one dirty label' => [static fn (): mixed => $restart()['rows'][0]['dirty_label'], 'hot-journal-reader dirty schema from interrupted import'],
    'row two reader label' => [static fn (): mixed => $restart()['rows'][1]['reader_label'], 'hot-journal-reader wal committed siteurl after hot recovery'],
    'row three reader label' => [static fn (): mixed => $restart()['rows'][2]['reader_label'], 'hot-journal-reader clean autoload index before interrupted import'],
    'row four released label' => [static fn (): mixed => $restart()['rows'][3]['released_next_label'], 'hot-journal-reader wal committed transient cleanup'],
    'row one hot replaced' => [static fn (): mixed => $restart()['rows'][0]['hot_replaced_dirty_image'], true],
    'row two reader frame' => [static fn (): mixed => $restart()['rows'][1]['reader_frame'], 1],
    'row three reader frame' => [static fn (): mixed => $restart()['rows'][2]['reader_frame'], null],
    'row four released frame null' => [static fn (): mixed => $restart()['rows'][3]['released_next_frame'], null],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>database>database>database', 'database>database>wal>wal>database', 'database>database>database>wal>database', 'database>database>database>wal>database']],
    'reader uses hot current' => [static fn (): mixed => $restart()['reader_uses_hot_current_source'], true],
    'pinned preserves reader false' => [static fn (): mixed => $restart()['pinned_checkpoint_preserved_reader_images'], false],
    'released preserves reader false' => [static fn (): mixed => $restart()['released_checkpoint_preserved_reader_images'], false],
    'reader release unblocked' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'dependency hot-journal-reader' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-reader-hot-journal-reader', $restart()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $restart()['dependencies'], true), true],
    'dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-current-source', $restart()['dependencies'], true), true],
    'released reader end frame' => [static fn (): mixed => $released()['reader_end_frame'], 4],
    'released reader sources' => [static fn (): mixed => $released()['reader_sources'], ['database', 'wal', 'wal', 'wal']],
    'released pinned busy true' => [static fn (): mixed => $released()['pinned_checkpoint_busy'], true],
    'released checkpoint unblocked true' => [static fn (): mixed => $released()['reader_release_unblocked_checkpoint'], true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released bytes' => [static fn (): mixed => $truncate()['released_wal_bytes_length'], 0],
    'reserved blocked status' => [static fn (): mixed => $reserved()['status'], 'wal-checkpoint-hot-journal-reader-hot-journal-reader-blocked'],
    'reserved blocked reason' => [static fn (): mixed => $reserved()['reason'], 'database_has_reserved_lock'],
    'reserved current source false' => [static fn (): mixed => $reserved()['current_source_verified'], false],
    'missing super blocked reason' => [static fn (): mixed => $missingSuper()['reason'], 'missing_super_journal'],
    'present super status' => [static fn (): mixed => $presentSuper()['status'], 'wal-checkpoint-hot-journal-reader-hot-journal-reader'],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint hot journal reader current source hot-journal-reader ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan('', $databaseBytes, $journalBytes, $wal, $walBytes, [1]),
    'empty journal rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, $databaseBytes, '', $wal, $walBytes, [1]),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, []),
    'bad mode rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, [1], 'passive'),
    'unaligned database rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, 'short', $journalBytes, $wal, $walBytes, [1]),
    'stale wal bytes rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, $databaseBytes, $journalBytes, $wal, substr($walBytes, 0, -1) . 'x', [1]),
    'reader outside range rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, [1], 'restart', 5),
    'non integer page rejected' => static fn () => SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan($databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, ['1']),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint hot journal reader current source hot-journal-reader ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
