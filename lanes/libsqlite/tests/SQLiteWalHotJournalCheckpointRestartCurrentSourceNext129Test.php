<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next129.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next129 clean sqlite header before crashed import'),
    2 => $page('next129 clean wp_options root before crashed import'),
    3 => $page('next129 clean active_plugins before crashed import'),
    4 => $page('next129 clean autoload index before crashed import'),
    5 => $page('next129 clean rewrite rules before crashed import'),
];
$dirtyDatabase = $page('next129 dirty sqlite header after crashed import')
    . $page('next129 dirty wp_options root after crashed import')
    . $page('next129 dirty active_plugins after crashed import')
    . $page('next129 dirty autoload index after crashed import')
    . $page('next129 dirty rewrite rules after crashed import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026129) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 129, int $salt1 = 0x12912901, int $salt2 = 0x12912902) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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
$frames = [
    [1, 0, 'next129 wal schema retained draft'],
    [2, 5, 'next129 wal options retained commit'],
    [3, 0, 'next129 wal active_plugins reader draft'],
    [4, 5, 'next129 wal autoload reader commit'],
    [2, 5, 'next129 wal options reader tail commit'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = static fn (
    ?int $readerEndFrame = 5,
    array $pages = [1, 2, 3, 4, 5],
    bool $reservedLock = false,
): array => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $wal,
    $walBytes,
    $pages,
    $readerEndFrame,
    $reservedLock
);

$restart = static fn (): array => $plan();
$released = static fn (): array => $plan(null);
$skipped = static fn (): array => $plan(5, [1, 2], true);
$single = static fn (): array => $plan(5, [2]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-checkpoint-restart-current-source-next129'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovery_precedes_restart_checkpoint_current_source_boundary'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'hot journal reason' => [static fn (): mixed => $restart()['hot_journal_reason'], 'hot_journal_recovery_required'],
    'journal action' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'hot restored pages' => [static fn (): mixed => $restart()['hot_restored_page_numbers'], [1, 2, 3, 4, 5]],
    'journal pages' => [static fn (): mixed => $restart()['journal_page_numbers'], [1, 2, 3, 4, 5]],
    'pinned busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'released wal header length' => [static fn (): mixed => $restart()['released_wal_bytes_length'], 32],
    'pinned wal full length' => [static fn (): mixed => $restart()['pinned_wal_bytes_length'], 32 + (5 * (24 + $pageSize))],
    'released checkpoint sequence' => [static fn (): mixed => $restart()['released_restart_checkpoint_sequence'], 130],
    'reader sources' => [static fn (): mixed => $restart()['reader_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'pinned next sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'reader frame indexes' => [static fn (): mixed => $restart()['reader_frame_indexes'], [1, 5, 3, 4, null]],
    'pinned frame indexes' => [static fn (): mixed => $restart()['pinned_next_frame_indexes'], [1, 5, 3, 4, null]],
    'released frame indexes' => [static fn (): mixed => $restart()['released_next_frame_indexes'], [null, null, null, null, null]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['database>database>wal>wal>database', 'database>database>wal>wal>database', 'database>database>wal>wal>database', 'database>database>wal>wal>database', 'database>database>database>database>database']],
    'pinned preserves reader images' => [static fn (): mixed => $restart()['pinned_preserved_reader_images'], true],
    'released uses checkpoint images' => [static fn (): mixed => $restart()['released_restart_uses_checkpoint_images'], true],
    'reader release unblocked restart' => [static fn (): mixed => $restart()['reader_release_unblocked_restart'], true],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_source_wal_sha256']), 64],
    'released sha length' => [static fn (): mixed => strlen($restart()['released_wal_sha256']), 64],
    'hot db sha length' => [static fn (): mixed => strlen($restart()['hot_database_sha256']), 64],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'operation sequence' => [static fn (): mixed => array_column($restart()['operations'], 'op'), ['write', 'delete', 'preserve', 'write', 'write']],
    'operation reasons' => [static fn (): mixed => $restart()['operation_reasons'], [
        'restore_hot_journal_database_before_restart_checkpoint_next129',
        'delete_hot_journal_after_recovery_next129',
        'preserve_current_wal_while_reader_pins_restart_next129',
        'write_released_restart_checkpoint_database_next129',
        'write_released_restart_header_wal_next129',
    ]],
    'page two dirty label' => [static fn (): mixed => $restart()['rows'][1]['dirty_label'], 'next129 dirty wp_options root after crashed import'],
    'page two hot label' => [static fn (): mixed => $restart()['rows'][1]['hot_current_label'], 'next129 clean wp_options root before crashed import'],
    'page two reader label' => [static fn (): mixed => $restart()['rows'][1]['reader_label'], 'next129 wal options reader tail commit'],
    'page two released label' => [static fn (): mixed => $restart()['rows'][1]['released_next_label'], 'next129 wal options reader tail commit'],
    'page five released clean label' => [static fn (): mixed => $restart()['rows'][4]['released_next_label'], 'next129 clean rewrite rules before crashed import'],
    'released default reader frame' => [static fn (): mixed => $released()['reader_end_frame'], 5],
    'skipped status' => [static fn (): mixed => $skipped()['status'], 'wal-hot-journal-checkpoint-restart-current-source-blocked-next129'],
    'skipped reason' => [static fn (): mixed => $skipped()['reason'], 'database_has_reserved_lock'],
    'skipped journal action' => [static fn (): mixed => $skipped()['journal_action'], 'preserve_journal'],
    'single restored pages' => [static fn (): mixed => $single()['hot_restored_page_numbers'], [2]],
    'single source transitions' => [static fn (): mixed => $single()['source_transitions'], ['database>database>wal>wal>database']],
    'dependency next129' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-checkpoint-restart-current-source-next129', $restart()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $restart()['dependencies'], true), true],
    'dependency restart boundary' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-restart-current-source-boundary', $restart()['dependencies'], true), true],
    'dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restart()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal checkpoint restart current source next129 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan('', $dirtyDatabase, $journalBytes, $wal, $walBytes, [1], 1),
    'empty database rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, '', $journalBytes, $wal, $walBytes, [1], 1),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, '', $wal, $walBytes, [1], 1),
    'empty wal rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, '', [1], 1),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [], 1),
    'source mismatch rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, substr_replace($walBytes, 'x', 120, 1), [1], 1),
    'unaligned database rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase . 'x', $journalBytes, $wal, $walBytes, [1], 1),
    'negative reader rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [1], -1),
    'reader past wal rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [1], 6),
    'zero page rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, [0], 1),
    'string page rejected' => static fn () => SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $wal, $walBytes, ['1'], 1),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal checkpoint restart current source next129 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
