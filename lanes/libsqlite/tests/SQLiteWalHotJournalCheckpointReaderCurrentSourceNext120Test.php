<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next120.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next120 clean sqlite header before import'),
    2 => $page('next120 clean wp_options root before import'),
    3 => $page('next120 clean plugin settings before savepoint'),
    4 => $page('next120 clean autoload index before savepoint'),
    5 => $page('next120 clean transient reader baseline'),
];
$dirtyDatabase = $page('next120 dirty sqlite header after crash')
    . $page('next120 dirty wp_options root after crash')
    . $page('next120 dirty plugin settings after crash')
    . $page('next120 dirty autoload index after crash')
    . $page('next120 dirty transient reader baseline');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026120) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 0x20261200) use ($pageSize, $page): string {
    $salt1 = 0x20260528;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 120, $salt1, $salt2);
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
$frames = [
    [1, 0, 'next120 wal schema retained draft'],
    [2, 5, 'next120 wal options retained commit'],
    [3, 0, 'next120 wal plugin stale reader draft'],
    [4, 5, 'next120 wal autoload stale reader commit'],
    [5, 0, 'next120 wal transient stale reader draft'],
    [2, 5, 'next120 wal options stale reader tail'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$readerWalBytes = $walBytes;
$retainedReaderWalBytes = $makeWalBytes(array_slice($frames, 0, 2));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next120');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next120');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 5);
    $stack->recordWalFrameWrite(6, 2, true);

    return $stack;
};

$plan = static fn (
    string $mode = 'restart',
    ?int $readerEndFrame = 6,
    ?string $readerBytes = null,
    array $pages = [1, 2, 3, 4, 5],
    bool $reservedLock = false,
): array => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan(
    $journal,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-settings-next120',
    $wal,
    $walBytes,
    $readerBytes ?? $readerWalBytes,
    $databasePath,
    $pages,
    $mode,
    $readerEndFrame,
    $pageSize,
    $reservedLock
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$retainedReader = static fn (): array => $plan('restart', 2, $retainedReaderWalBytes);
$skipped = static fn (): array => $plan('restart', 6, null, [1, 2], true);
$single = static fn (): array => $plan('restart', 6, null, [2]);

$cases = [
    'status restart' => [static fn (): mixed => $restart()['status'], 'hot-journal-checkpoint-reader-current-source-next120'],
    'reason restart' => [static fn (): mixed => $restart()['reason'], 'reader_source_blocks_reset_until_release_while_checkpoint_uses_current_wal_prefix'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next120'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 6],
    'reader wal frame count' => [static fn (): mixed => $restart()['reader_wal_frame_count'], 6],
    'reader wal bytes length' => [static fn (): mixed => $restart()['reader_wal_bytes_length'], strlen($walBytes)],
    'current wal bytes length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'reader sha length' => [static fn (): mixed => strlen($restart()['reader_wal_sha256']), 64],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'reader source mismatch current' => [static fn (): mixed => $restart()['reader_source_matches_current'], false],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'journal action delete' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'pinned busy' => [static fn (): mixed => $restart()['pinned_checkpoint_busy'], true],
    'pinned reason' => [static fn (): mixed => $restart()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $restart()['pinned_wal_action'], 'preserve_wal'],
    'released ready' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['savepoint_discarded_frame_count'], 4],
    'reader sources' => [static fn (): mixed => $restart()['reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'reader frame indexes' => [static fn (): mixed => $restart()['reader_frame_indexes'], [1, 6, 3, 4, 5]],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned sources' => [static fn (): mixed => $restart()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, null, null]],
    'pinned frames' => [static fn (): mixed => $restart()['pinned_next_frame_indexes'], [1, 2, null, null, null]],
    'released frames' => [static fn (): mixed => $restart()['released_next_frame_indexes'], [null, null, null, null, null]],
    'reader uses stale tail' => [static fn (): mixed => $restart()['reader_uses_stale_tail'], true],
    'pinned preserves reader wal' => [static fn (): mixed => $restart()['pinned_preserves_reader_wal'], true],
    'released uses checkpoint db' => [static fn (): mixed => $restart()['released_uses_checkpoint_database'], true],
    'current released images match' => [static fn (): mixed => $restart()['current_to_released_images_match'], true],
    'reader current images mismatch' => [static fn (): mixed => $restart()['reader_to_current_images_match'], false],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal>database', 'wal>wal>wal>database', 'wal>database>database>database', 'wal>database>database>database', 'wal>database>database>database']],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'page one retained' => [static fn (): mixed => $restart()['rows'][0]['reader_tail_ignored_by_current'], false],
    'page two stale ignored' => [static fn (): mixed => $restart()['rows'][1]['reader_tail_ignored_by_current'], true],
    'page three stale ignored' => [static fn (): mixed => $restart()['rows'][2]['reader_tail_ignored_by_current'], true],
    'page four stale ignored' => [static fn (): mixed => $restart()['rows'][3]['reader_tail_ignored_by_current'], true],
    'page five stale ignored' => [static fn (): mixed => $restart()['rows'][4]['reader_tail_ignored_by_current'], true],
    'page two reader label stale' => [static fn (): mixed => $restart()['rows'][1]['reader_label'], 'next120 wal options stale reader tail'],
    'page two current label retained' => [static fn (): mixed => $restart()['rows'][1]['current_label'], 'next120 wal options retained commit'],
    'page three released label clean' => [static fn (): mixed => $restart()['rows'][2]['released_next_label'], 'next120 clean plugin settings before savepoint'],
    'page four current label clean' => [static fn (): mixed => $restart()['rows'][3]['current_label'], 'next120 clean autoload index before savepoint'],
    'page five released label clean' => [static fn (): mixed => $restart()['rows'][4]['released_next_label'], 'next120 clean transient reader baseline'],
    'pinned plan status busy' => [static fn (): mixed => $restart()['pinned']['status'], 'busy'],
    'released plan status ready' => [static fn (): mixed => $restart()['released']['status'], 'hot-journal-savepoint-checkpoint-ready-next114'],
    'dependency next120' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-checkpoint-reader-current-source-next120', $restart()['dependencies'], true), true],
    'dependency next114' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-savepoint-current-source-next114', $restart()['dependencies'], true), true],
    'dependency checkpoint current' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],

    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released action' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'truncate released sources' => [static fn (): mixed => $truncate()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],

    'retained reader matches current source' => [static fn (): mixed => $retainedReader()['reader_source_matches_current'], true],
    'retained reader images differ after hot journal recovery' => [static fn (): mixed => $retainedReader()['reader_to_current_images_match'], false],
    'retained reader still reflects pre recovery database' => [static fn (): mixed => $retainedReader()['reader_uses_stale_tail'], true],
    'retained reader frames' => [static fn (): mixed => $retainedReader()['reader_frame_indexes'], [1, 2, null, null, null]],

    'skipped status' => [static fn (): mixed => $skipped()['status'], 'hot-journal-checkpoint-reader-current-source-skipped-next120'],
    'skipped hot recovered false' => [static fn (): mixed => $skipped()['hot_recovered'], false],
    'skipped journal preserved' => [static fn (): mixed => $skipped()['journal_action'], 'preserve_journal'],

    'single row page' => [static fn (): mixed => array_column($single()['rows'], 'page_number'), [2]],
    'single row stale label' => [static fn (): mixed => $single()['rows'][0]['reader_label'], 'next120 wal options stale reader tail'],
    'single row current label' => [static fn (): mixed => $single()['rows'][0]['current_label'], 'next120 wal options retained commit'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal checkpoint reader current source next120 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty reader wal bytes rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next120', $wal, $walBytes, '', $databasePath, [1], 'restart', 1, $pageSize),
    'negative reader frame rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next120', $wal, $walBytes, $readerWalBytes, $databasePath, [1], 'restart', -1, $pageSize),
    'bad page rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next120', $wal, $walBytes, $readerWalBytes, $databasePath, [0], 'restart', 1, $pageSize),
    'string page rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next120', $wal, $walBytes, $readerWalBytes, $databasePath, ['1'], 'restart', 1, $pageSize),
    'invalid mode rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next120', $wal, $walBytes, $readerWalBytes, $databasePath, [1], 'passive', 1, $pageSize),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal checkpoint reader current source next120 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
