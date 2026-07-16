<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next138';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce = 0x13800001) use ($pageSize, $sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x13813801;
    $salt2 = 0x13813802;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 138, $salt1, $salt2);
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

$clean1 = $page('next138 clean schema before interrupted option import');
$clean2 = $page('next138 clean wp_options before interrupted option import');
$clean3 = $page('next138 clean autoload index before interrupted option import');
$clean4 = $page('next138 clean transient page before interrupted option import');
$clean5 = $page('next138 clean plugin page before interrupted option import');
$dirty1 = $page('next138 dirty schema from failed option import');
$dirty2 = $page('next138 dirty wp_options from failed option import');
$dirty3 = $page('next138 dirty autoload index from failed option import');
$dirty4 = $page('next138 dirty transient page from failed option import');
$dirty5 = $page('next138 dirty plugin page from failed option import');
$databaseBytes = $dirty1 . $dirty2 . $dirty3 . $dirty4 . $dirty5;
$journalBytes = $makeJournal([
    1 => $clean1,
    2 => $clean2,
    3 => $clean3,
    4 => $clean4,
    5 => $clean5,
], 5);
$walBytes = $makeWal([
    [1, 0, 'next138 retained schema wal draft'],
    [2, 5, 'next138 retained siteurl wal commit'],
    [3, 0, 'next138 discarded autoload wal draft'],
    [4, 5, 'next138 discarded transient wal commit'],
    [2, 5, 'next138 discarded option retry wal tail'],
    [5, 5, 'next138 discarded plugin wal tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next138');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next138');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 2, true);
    $stack->recordWalFrameWrite(6, 5, true);

    return $stack;
};

$plan = static function (?int $reader = 2, array $pages = [1, 2, 3, 4, 5], bool $reserved = false, bool $requiresSuper = false, ?bool $superExists = null) use ($databasePath, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes): array {
    return SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan(
        $databasePath,
        $databaseBytes,
        $journalBytes,
        $makeStack(),
        'plugin-batch-next138',
        $wal,
        $walBytes,
        $pages,
        $reader,
        $reserved,
        $requiresSuper,
        $superExists
    );
};

$pinned = static fn (): array => $plan();
$baseReader = static fn (): array => $plan(0);
$single = static fn (): array => $plan(2, [2]);
$reserved = static fn (): array => $plan(2, [1, 2], true);
$missingSuper = static fn (): array => $plan(2, [1, 2], false, true, false);
$presentSuper = static fn (): array => $plan(2, [1, 2], false, true, true);

$cases = [
    'status' => [static fn (): mixed => $pinned()['status'], 'wal-checkpoint-hot-journal-truncate-current-source-next138'],
    'reason' => [static fn (): mixed => $pinned()['reason'], 'hot_journal_recovery_precedes_savepoint_truncated_wal_checkpoint'],
    'database path' => [static fn (): mixed => $pinned()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $pinned()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $pinned()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $pinned()['savepoint'], 'plugin-batch-next138'],
    'mode' => [static fn (): mixed => $pinned()['mode'], 'truncate'],
    'page size' => [static fn (): mixed => $pinned()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $pinned()['reader_end_frame'], 2],
    'hot recovered' => [static fn (): mixed => $pinned()['hot_recovered'], true],
    'hot reason' => [static fn (): mixed => $pinned()['hot_journal_reason'], 'hot_journal_recovery_required'],
    'journal action' => [static fn (): mixed => $pinned()['journal_action'], 'delete_journal_after_recovery'],
    'journal pages' => [static fn (): mixed => $pinned()['journal_page_numbers'], [1, 2, 3, 4, 5]],
    'hot database bytes' => [static fn (): mixed => $pinned()['hot_database_bytes_length'], strlen($databaseBytes)],
    'original frame count' => [static fn (): mixed => $pinned()['original_frame_count'], 6],
    'retained frame count' => [static fn (): mixed => $pinned()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $pinned()['discarded_frame_count'], 4],
    'discarded frame indexes' => [static fn (): mixed => $pinned()['discarded_frame_indexes'], [3, 4, 5, 6]],
    'discarded page numbers' => [static fn (): mixed => $pinned()['discarded_page_numbers'], [3, 4, 2, 5]],
    'truncate to bytes' => [static fn (): mixed => $pinned()['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'retained wal bytes' => [static fn (): mixed => $pinned()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'retained sha length' => [static fn (): mixed => strlen($pinned()['retained_wal_sha256']), 64],
    'pinned busy' => [static fn (): mixed => $pinned()['pinned_checkpoint_busy'], true],
    'pinned reason' => [static fn (): mixed => $pinned()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $pinned()['pinned_wal_action'], 'preserve_wal'],
    'pinned wal bytes length' => [static fn (): mixed => $pinned()['pinned_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released busy' => [static fn (): mixed => $pinned()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $pinned()['released_checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'released wal action' => [static fn (): mixed => $pinned()['released_wal_action'], 'truncate_wal'],
    'released wal bytes length' => [static fn (): mixed => $pinned()['released_wal_bytes_length'], 0],
    'released database bytes length' => [static fn (): mixed => $pinned()['released_database_bytes_length'], strlen($databaseBytes)],
    'current sources' => [static fn (): mixed => $pinned()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $pinned()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $pinned()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current wal count' => [static fn (): mixed => $pinned()['current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $pinned()['current_source_counts']['database'], 3],
    'released database count' => [static fn (): mixed => $pinned()['released_next_source_counts']['database'], 5],
    'row count' => [static fn (): mixed => count($pinned()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($pinned()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row one dirty label' => [static fn (): mixed => $pinned()['rows'][0]['dirty_label'], 'next138 dirty schema from failed option import'],
    'row one hot label' => [static fn (): mixed => $pinned()['rows'][0]['hot_current_label'], 'next138 clean schema before interrupted option import'],
    'row one current label' => [static fn (): mixed => $pinned()['rows'][0]['current_label'], 'next138 retained schema wal draft'],
    'row two current label' => [static fn (): mixed => $pinned()['rows'][1]['current_label'], 'next138 retained siteurl wal commit'],
    'row three released label' => [static fn (): mixed => $pinned()['rows'][2]['released_next_label'], 'next138 clean autoload index before interrupted option import'],
    'row five released label' => [static fn (): mixed => $pinned()['rows'][4]['released_next_label'], 'next138 clean plugin page before interrupted option import'],
    'current frames' => [static fn (): mixed => array_column($pinned()['rows'], 'current_frame'), [1, 2, null, null, null]],
    'pinned frames' => [static fn (): mixed => array_column($pinned()['rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'released frames' => [static fn (): mixed => array_column($pinned()['rows'], 'released_next_frame'), [null, null, null, null, null]],
    'source transitions' => [static fn (): mixed => $pinned()['source_transitions'], ['database>database>wal>wal>database', 'database>database>wal>wal>database', 'database>database>database>database>database', 'database>database>database>database>database', 'database>database>database>database>database']],
    'hot replaced dirty' => [static fn (): mixed => $pinned()['hot_recovery_replaced_dirty_images'], true],
    'current uses hot source' => [static fn (): mixed => $pinned()['current_reader_uses_hot_current_source'], true],
    'pinned preserves current' => [static fn (): mixed => $pinned()['pinned_checkpoint_preserved_current_images'], true],
    'released preserves current images' => [static fn (): mixed => $pinned()['released_checkpoint_preserved_current_images'], true],
    'reader release unblocked truncate' => [static fn (): mixed => $pinned()['reader_release_unblocked_truncate'], true],
    'released uses checkpoint database' => [static fn (): mixed => $pinned()['released_reader_uses_checkpoint_database'], true],
    'current source verified' => [static fn (): mixed => $pinned()['current_source_verified'], true],
    'source digest length' => [static fn (): mixed => strlen($pinned()['source_digest']), 64],
    'base reader sources' => [static fn (): mixed => $baseReader()['current_sources'], ['database', 'database', 'database', 'database', 'database']],
    'base reader current label' => [static fn (): mixed => $baseReader()['rows'][1]['current_label'], 'next138 clean wp_options before interrupted option import'],
    'single page transition' => [static fn (): mixed => $single()['source_transitions'], ['database>database>wal>wal>database']],
    'single page released label' => [static fn (): mixed => $single()['rows'][0]['released_next_label'], 'next138 retained siteurl wal commit'],
    'reserved blocked status' => [static fn (): mixed => $reserved()['status'], 'wal-checkpoint-hot-journal-truncate-current-source-blocked-next138'],
    'reserved blocked reason' => [static fn (): mixed => $reserved()['reason'], 'database_has_reserved_lock'],
    'reserved current source false' => [static fn (): mixed => $reserved()['current_source_verified'], false],
    'missing super blocked reason' => [static fn (): mixed => $missingSuper()['reason'], 'missing_super_journal'],
    'present super status' => [static fn (): mixed => $presentSuper()['status'], 'wal-checkpoint-hot-journal-truncate-current-source-next138'],
    'dependency next138' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-truncate-current-source-next138', $pinned()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $pinned()['dependencies'], true), true],
    'dependency savepoint truncation' => [static fn (): mixed => in_array('sqlite-savepoint-wal-prefix-truncation', $pinned()['dependencies'], true), true],
    'dependency next open reader' => [static fn (): mixed => in_array('sqlite-wal-truncate-next-open-reader', $pinned()['dependencies'], true), true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint hot journal truncate current source next138 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan('', $databaseBytes, $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, $walBytes, [1]),
    'empty database rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, '', $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, $walBytes, [1]),
    'empty journal rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, '', $makeStack(), 'plugin-batch-next138', $wal, $walBytes, [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), '', $wal, $walBytes, [1]),
    'empty wal rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, '', [1]),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, $walBytes, []),
    'stale wal bytes rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, substr($walBytes, 0, -1) . 'x', [1]),
    'unaligned database rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, 'short', $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, $walBytes, [1]),
    'non integer page rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, $walBytes, ['1']),
    'reader outside range rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), 'plugin-batch-next138', $wal, $walBytes, [1], 3),
    'missing savepoint rejected' => static fn () => SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $journalBytes, $makeStack(), 'missing-next138', $wal, $walBytes, [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint hot journal truncate current source next138 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
