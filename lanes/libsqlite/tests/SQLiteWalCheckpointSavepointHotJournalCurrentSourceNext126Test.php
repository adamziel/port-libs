<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite-next126';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce = 0x12600001) use ($pageSize, $sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (array $frames, int $checkpointSequence = 126, int $salt1 = 0x12612601, int $salt2 = 0x12612602) use ($pageSize, $page): string {
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
$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next126');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next126');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->savepoint('transient-retry-next126');
    $stack->recordWalFrameWrite(5, 2);

    return $stack;
};

$clean1 = $page('next126 clean schema before crashed plugin import');
$clean2 = $page('next126 clean wp_options before crashed plugin import');
$clean3 = $page('next126 clean autoload index before crashed plugin import');
$clean4 = $page('next126 clean transients before crashed plugin import');
$dirty1 = $page('next126 dirty schema from hot journal crash');
$dirty2 = $page('next126 dirty wp_options from hot journal crash');
$dirty3 = $page('next126 dirty autoload index from hot journal crash');
$dirty4 = $page('next126 dirty transient from hot journal crash');
$databaseBytes = $dirty1 . $dirty2 . $dirty3 . $dirty4;
$journalBytes = $makeJournal([
    1 => $clean1,
    2 => $clean2,
    3 => $clean3,
    4 => $clean4,
], 4);
$walBytes = $makeWal([
    [1, 0, 'next126 wal retained schema version after hot recovery'],
    [2, 4, 'next126 wal retained siteurl commit after hot recovery'],
    [3, 0, 'next126 wal discarded autoload draft in savepoint'],
    [4, 4, 'next126 wal discarded transient commit in savepoint'],
    [2, 0, 'next126 wal discarded retry tail after nested savepoint'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = static fn (string $mode = 'restart', ?int $reader = 1, bool $reserved = false, array $pages = [1, 2, 3, 4]): array => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan(
    $makeStack(),
    'plugin-batch-next126',
    $databasePath,
    $databaseBytes,
    $journalBytes,
    $wal,
    $walBytes,
    $pages,
    $mode,
    $reader,
    $reserved
);

$pinned = static fn (): array => $plan();
$released = static fn (): array => $plan('restart', null);
$truncate = static fn (): array => $plan('truncate', null);
$reserved = static fn (): array => $plan('restart', 1, true);

$cases = [
    'status' => [static fn (): mixed => $pinned()['status'], 'wal-checkpoint-savepoint-hot-journal-current-source-next126'],
    'reason' => [static fn (): mixed => $pinned()['reason'], 'hot_journal_recovery_then_savepoint_wal_prefix_checkpoint'],
    'database path' => [static fn (): mixed => $pinned()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $pinned()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $pinned()['wal_path'], $databasePath . '-wal'],
    'savepoint name' => [static fn (): mixed => $pinned()['savepoint'], 'plugin-batch-next126'],
    'mode' => [static fn (): mixed => $pinned()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $pinned()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $pinned()['reader_end_frame'], 1],
    'original frame count' => [static fn (): mixed => $pinned()['original_frame_count'], 5],
    'retained frame count' => [static fn (): mixed => $pinned()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $pinned()['discarded_frame_count'], 3],
    'truncate to bytes' => [static fn (): mixed => $pinned()['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'original bytes length' => [static fn (): mixed => $pinned()['original_wal_bytes_length'], strlen($walBytes)],
    'retained bytes length' => [static fn (): mixed => $pinned()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'hot recovered' => [static fn (): mixed => $pinned()['hot_recovered'], true],
    'hot reason' => [static fn (): mixed => $pinned()['hot_journal_reason'], 'hot_journal_recovery_required'],
    'journal action' => [static fn (): mixed => $pinned()['journal_action'], 'delete_journal_after_recovery'],
    'hot restored pages' => [static fn (): mixed => $pinned()['hot_restored_page_numbers'], [1, 2, 3, 4]],
    'journal pages' => [static fn (): mixed => $pinned()['journal_page_numbers'], [1, 2, 3, 4]],
    'checkpoint busy' => [static fn (): mixed => $pinned()['checkpoint_busy'], true],
    'checkpoint reason' => [static fn (): mixed => $pinned()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'checkpoint wal action' => [static fn (): mixed => $pinned()['checkpoint_wal_action'], 'preserve_wal'],
    'released busy' => [static fn (): mixed => $released()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $released()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $released()['released_wal_action'], 'restart_wal'],
    'checkpoint wal bytes length' => [static fn (): mixed => $pinned()['checkpoint_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released wal bytes length' => [static fn (): mixed => $released()['released_wal_bytes_length'], 32],
    'rolled reader sources' => [static fn (): mixed => $pinned()['rolled_reader_sources'], ['database', 'database', 'database', 'database']],
    'pinned next sources' => [static fn (): mixed => $pinned()['pinned_next_sources'], ['wal', 'wal', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $released()['released_next_sources'], ['database', 'database', 'database', 'database']],
    'rolled reader database count' => [static fn (): mixed => $pinned()['rolled_reader_source_counts']['database'], 4],
    'pinned next wal count' => [static fn (): mixed => $pinned()['pinned_next_source_counts']['wal'], 2],
    'released database count' => [static fn (): mixed => $released()['released_next_source_counts']['database'], 4],
    'row count' => [static fn (): mixed => count($pinned()['rows']), 4],
    'row pages' => [static fn (): mixed => array_column($pinned()['rows'], 'page_number'), [1, 2, 3, 4]],
    'row one dirty label' => [static fn (): mixed => $pinned()['rows'][0]['dirty_label'], 'next126 dirty schema from hot journal crash'],
    'row one hot label' => [static fn (): mixed => $pinned()['rows'][0]['hot_current_label'], 'next126 clean schema before crashed plugin import'],
    'row one rolled label' => [static fn (): mixed => $pinned()['rows'][0]['rolled_reader_label'], 'next126 clean schema before crashed plugin import'],
    'row two rolled label' => [static fn (): mixed => $pinned()['rows'][1]['rolled_reader_label'], 'next126 clean wp_options before crashed plugin import'],
    'row two pinned label' => [static fn (): mixed => $pinned()['rows'][1]['pinned_next_label'], 'next126 wal retained siteurl commit after hot recovery'],
    'row three released label' => [static fn (): mixed => $released()['rows'][2]['released_next_label'], 'next126 clean autoload index before crashed plugin import'],
    'row four discarded frame gone' => [static fn (): mixed => $released()['rows'][3]['released_next_label'], 'next126 clean transients before crashed plugin import'],
    'row one rolled frame null' => [static fn (): mixed => $pinned()['rows'][0]['rolled_reader_frame'], null],
    'row two pinned frame' => [static fn (): mixed => $pinned()['rows'][1]['pinned_next_frame'], 2],
    'row three pinned frame null' => [static fn (): mixed => $pinned()['rows'][2]['pinned_next_frame'], null],
    'row four released frame null' => [static fn (): mixed => $released()['rows'][3]['released_next_frame'], null],
    'source transitions' => [static fn (): mixed => $pinned()['source_transitions'], ['database>database>database>wal>database', 'database>database>database>wal>database', 'database>database>database>database>database', 'database>database>database>database>database']],
    'rolled reader uses hot source' => [static fn (): mixed => $pinned()['rolled_reader_uses_hot_current_source'], true],
    'checkpoint preserved rolled reader false' => [static fn (): mixed => $pinned()['checkpoint_preserved_rolled_reader_images'], false],
    'released preserved rolled reader true' => [static fn (): mixed => $released()['released_preserved_rolled_reader_images'], true],
    'reader release unblocked' => [static fn (): mixed => $released()['reader_release_unblocked_checkpoint'], true],
    'pinned current source verified' => [static fn (): mixed => $pinned()['current_source_verified'], true],
    'source digest length' => [static fn (): mixed => strlen($pinned()['source_digest']), 64],
    'discarded frame indexes' => [static fn (): mixed => array_column($pinned()['discarded_wal_frames'], 'frame_index'), [3, 4, 5]],
    'discarded page numbers' => [static fn (): mixed => array_column($pinned()['discarded_wal_frames'], 'page_number'), [3, 4, 2]],
    'discarded commit flags' => [static fn (): mixed => array_column($pinned()['discarded_wal_frames'], 'commit_frame'), [false, true, false]],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released bytes' => [static fn (): mixed => $truncate()['released_wal_bytes_length'], 0],
    'reserved blocked status' => [static fn (): mixed => $reserved()['status'], 'wal-checkpoint-savepoint-hot-journal-blocked-next126'],
    'reserved blocked reason' => [static fn (): mixed => $reserved()['reason'], 'database_has_reserved_lock'],
    'dependency next126' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-savepoint-hot-journal-current-source-next126', $pinned()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $pinned()['dependencies'], true), true],
    'dependency savepoint truncation' => [static fn (): mixed => in_array('sqlite-savepoint-wal-prefix-truncation', $pinned()['dependencies'], true), true],
    'dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-durable-checkpoint-result', $pinned()['dependencies'], true), true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint savepoint hot journal current source next126 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), '', $databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, [1]),
    'empty path rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', '', $databaseBytes, $journalBytes, $wal, $walBytes, [1]),
    'empty journal rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, $databaseBytes, '', $wal, $walBytes, [1]),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, []),
    'bad mode rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, [1], 'passive'),
    'unaligned database rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, 'short', $journalBytes, $wal, $walBytes, [1]),
    'stale wal bytes rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, $databaseBytes, $journalBytes, $wal, substr($walBytes, 0, -1) . 'x', [1]),
    'reader outside retained range rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, [1], 'restart', 3),
    'non integer page rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'plugin-batch-next126', $databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, ['1']),
    'missing savepoint rejected' => static fn () => SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan::plan($makeStack(), 'missing-next126', $databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint savepoint hot journal current source next126 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
