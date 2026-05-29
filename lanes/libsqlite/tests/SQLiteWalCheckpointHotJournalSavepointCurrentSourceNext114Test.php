<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next114.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next114 clean sqlite header before plugin import'),
    2 => $page('next114 clean wp_options root before plugin import'),
    3 => $page('next114 clean plugin settings before savepoint'),
    4 => $page('next114 clean autoload index before savepoint'),
    5 => $page('next114 clean transient future page before import'),
];
$dirtyDatabase = $page('next114 dirty sqlite header after crashed import')
    . $page('next114 dirty wp_options root after crashed import')
    . $page('next114 dirty plugin settings after crashed import')
    . $page('next114 dirty autoload index after crashed import')
    . $page('next114 dirty transient future page after crashed import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026114) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 0x20261140) use ($pageSize, $page): string {
    $salt1 = 0x20260528;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 114, $salt1, $salt2);
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

$journalBytes = $makeJournalBytes($cleanPages);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'next114 wal schema draft from hot database'],
    [2, 5, 'next114 wal retained wp_options commit'],
    [3, 0, 'next114 wal plugin settings savepoint draft'],
    [4, 5, 'next114 wal autoload index savepoint commit'],
    [5, 0, 'next114 wal uncommitted transient tail ignored'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next114');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next114');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$plan = static fn (
    string $mode = 'restart',
    ?int $readerEndFrame = null,
    array $pages = [1, 2, 3, 4, 5],
    ?SQLiteRollbackJournal $journalInput = null,
    ?string $journalBytesInput = null,
    ?string $walBytesInput = null,
    bool $reservedLock = false,
    bool $requiresSuper = false,
    ?bool $superExists = null,
): array => SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan(
    $journalInput ?? $journal,
    $dirtyDatabase,
    $journalBytesInput ?? $journalBytes,
    $makeStack(),
    'plugin-settings-next114',
    SQLiteWal::parse($walBytesInput ?? $walBytes, $pageSize, true),
    $walBytesInput ?? $walBytes,
    $databasePath,
    $pages,
    $mode,
    $readerEndFrame,
    $pageSize,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$pinned = static fn (): array => $plan('restart', 2);
$blocked = static fn (): array => $plan('restart', null, [1, 2], null, null, null, false, true, false);

$cases = [
    'status restart' => [static fn (): mixed => $restart()['status'], 'hot-journal-savepoint-checkpoint-ready-next114'],
    'reason restart' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovered_before_savepoint_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next114'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'page numbers' => [static fn (): mixed => $restart()['page_numbers'], [1, 2, 3, 4, 5]],
    'dirty reader end frame' => [static fn (): mixed => $restart()['dirty_reader_end_frame'], 5],
    'hot reader end frame committed prefix' => [static fn (): mixed => $restart()['hot_reader_end_frame'], 4],
    'current reader end frame retained prefix' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'next reader end frame restart reset' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'journal action delete' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'wal status recovered prefix' => [static fn (): mixed => $restart()['wal_status'], 'recovered_committed_prefix'],
    'committed frame count' => [static fn (): mixed => $restart()['committed_frame_count'], 4],
    'valid tail count' => [static fn (): mixed => $restart()['discarded_valid_tail_frame_count'], 1],
    'corrupt tail count' => [static fn (): mixed => $restart()['discarded_corrupt_tail_frame_count'], 0],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'savepoint discarded frames' => [static fn (): mixed => $restart()['savepoint_discarded_frame_count'], 2],
    'checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'checkpoint reason restart' => [static fn (): mixed => $restart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'wal action restart' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'dirty sources' => [static fn (): mixed => $restart()['dirty_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'hot sources' => [static fn (): mixed => $restart()['hot_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'dirty frame indexes' => [static fn (): mixed => $restart()['dirty_frame_indexes'], [1, 2, 3, 4, null]],
    'hot frame indexes' => [static fn (): mixed => $restart()['hot_frame_indexes'], [1, 2, 3, 4, null]],
    'current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, null, null]],
    'next frame indexes' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, null, null, null, null]],
    'dirty hot images differ for database-only page restored by journal' => [static fn (): mixed => $restart()['dirty_to_hot_images_match'], false],
    'hot current images differ after savepoint rollback' => [static fn (): mixed => $restart()['hot_to_current_images_match'], false],
    'current next images match after checkpoint' => [static fn (): mixed => $restart()['current_to_next_images_match'], true],
    'next uses checkpoint database' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], true],
    'next not preserved wal' => [static fn (): mixed => $restart()['next_uses_preserved_wal'], false],
    'current uses hot database' => [static fn (): mixed => $restart()['current_uses_recovered_hot_database'], true],
    'current uses savepoint prefix' => [static fn (): mixed => $restart()['current_uses_savepoint_wal_prefix'], true],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal>database', 'wal>wal>wal>database', 'wal>wal>database>database', 'wal>wal>database>database', 'database>database>database>database']],
    'row page numbers' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row page one current frame' => [static fn (): mixed => $restart()['rows'][0]['current_frame'], 1],
    'row page two retained label' => [static fn (): mixed => $restart()['rows'][1]['current_label'], 'next114 wal retained wp_options commit'],
    'row page three rollback changed' => [static fn (): mixed => $restart()['rows'][2]['savepoint_rollback_changed_current'], true],
    'row page four rollback changed' => [static fn (): mixed => $restart()['rows'][3]['savepoint_rollback_changed_current'], true],
    'row page five hot journal restores database page' => [static fn (): mixed => $restart()['rows'][4]['hot_recovery_changed_current'], true],
    'row page three next clean label' => [static fn (): mixed => $restart()['rows'][2]['next_label'], 'next114 clean plugin settings before savepoint'],
    'row page four current clean label' => [static fn (): mixed => $restart()['rows'][3]['current_label'], 'next114 clean autoload index before savepoint'],
    'row page one checkpoint changed next' => [static fn (): mixed => $restart()['rows'][0]['checkpoint_changed_next'], false],
    'hot recovery payload has hot journal' => [static fn (): mixed => isset($restart()['hot_recovery']['payloads'][$databasePath . '#hot-journal']), true],
    'checkpoint current wal bytes retained prefix' => [static fn (): mixed => $restart()['checkpoint']['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'operation includes hot restore' => [static fn (): mixed => in_array('restore_hot_journal_database_before_wal_recovery', $restart()['operation_reasons'], true), true],
    'operation includes savepoint rollback' => [static fn (): mixed => in_array('rollback_savepoint_to_hot_journal_recovered_wal_prefix', $restart()['operation_reasons'], true), true],
    'operation includes checkpoint prefix' => [static fn (): mixed => in_array('checkpoint_recovered_savepoint_wal_prefix', $restart()['operation_reasons'], true), true],
    'operation includes restart action' => [static fn (): mixed => in_array('restart_wal_after_hot_journal_savepoint_checkpoint', $restart()['operation_reasons'], true), true],
    'digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'dependency next114' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-savepoint-current-source-next114', $restart()['dependencies'], true), true],
    'dependency hot before checkpoint' => [static fn (): mixed => in_array('sqlite-hot-journal-before-savepoint-checkpoint-current-source', $restart()['dependencies'], true), true],
    'dependency savepoint checkpoint' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],

    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate next frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate operation action' => [static fn (): mixed => in_array('truncate_wal_after_hot_journal_savepoint_checkpoint', $truncate()['operation_reasons'], true), true],
    'truncate next sources database' => [static fn (): mixed => $truncate()['next_sources'], ['database', 'database', 'database', 'database', 'database']],

    'pinned status busy' => [static fn (): mixed => $pinned()['status'], 'busy'],
    'pinned reason' => [static fn (): mixed => $pinned()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned action preserve' => [static fn (): mixed => $pinned()['wal_action'], 'preserve_wal'],
    'pinned next end frame' => [static fn (): mixed => $pinned()['next_reader_end_frame'], 2],
    'pinned next sources preserve prefix' => [static fn (): mixed => $pinned()['next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned operation preserves reset' => [static fn (): mixed => in_array('preserve_wal_reset_until_reader_releases_hot_journal_savepoint_checkpoint', $pinned()['operation_reasons'], true), true],

    'blocked status' => [static fn (): mixed => $blocked()['status'], 'hot-journal-savepoint-checkpoint-skipped-next114'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'hot_journal_not_hot_savepoint_checkpoint_current_source'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked journal action preserve' => [static fn (): mixed => $blocked()['journal_action'], 'preserve_journal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint hot journal savepoint current source next114 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), '', $wal, $walBytes, $databasePath, [1], 'restart', null, $pageSize),
    'empty path rejected' => static fn () => SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next114', $wal, $walBytes, '', [1], 'restart', null, $pageSize),
    'empty pages rejected' => static fn () => SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next114', $wal, $walBytes, $databasePath, [], 'restart', null, $pageSize),
    'zero page rejected' => static fn () => $plan('restart', null, [0]),
    'string page rejected' => static fn () => $plan('restart', null, ['1']),
    'bad mode rejected' => static fn () => $plan('passive'),
    'stale journal bytes rejected' => static fn () => $plan('restart', null, [1], null, substr($journalBytes, 0, -1) . 'x'),
    'stale parsed journal rejected' => static fn () => $plan('restart', null, [1], SQLiteRollbackJournal::parse($makeJournalBytes([1 => $page('next114 stale page')]), true)),
    'stale wal bytes rejected' => static fn () => SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next114', $wal, substr($walBytes, 0, -1) . 'x', $databasePath, [1], 'restart', null, $pageSize),
];

foreach ($throws as $name => $callback) {
    $tests['wal checkpoint hot journal savepoint current source next114 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
