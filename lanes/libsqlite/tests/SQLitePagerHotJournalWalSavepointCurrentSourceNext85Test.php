<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$dirtySchema = $page('next85 dirty schema from crashed plugin import');
$dirtyOptions = $page('next85 dirty active_plugins from crashed plugin import');
$dirtyTransient = $page('next85 dirty transient from crashed plugin import');
$cleanSchema = $page('next85 clean schema before crashed plugin import');
$cleanOptions = $page('next85 clean active_plugins before crashed plugin import');
$cleanTransient = $page('next85 clean transient before crashed plugin import');
$databaseBytes = $dirtySchema . $dirtyOptions . $dirtyTransient;

$makeJournalBytes = static function () use ($sectorSize, $pageSize, $cleanSchema, $cleanOptions, $cleanTransient): string {
    $pages = [1 => $cleanSchema, 2 => $cleanOptions, 3 => $cleanTransient];
    $nonce = 0x85000085;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, 3, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, int $frames = 4) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $allFrames = [
        [1, 0, 'next85 wal retained schema draft after hot journal'],
        [2, 3, 'next85 wal retained active_plugins commit'],
        [2, 0, 'next85 wal rolled back active_plugins draft'],
        [3, 3, 'next85 wal rolled back transient commit'],
    ];

    foreach (array_slice($allFrames, 0, $frames) as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes();
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes(85, 0x85858585, 0x58585858);
$staleWalBytes = $makeWalBytes(85, 0x85858586, 0x58585858);
$shortWalBytes = $makeWalBytes(85, 0x85858585, 0x58585858, 3);

$savepoints = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application-import-next85');
    $stack->recordWalFrameWrite(1, 1, false);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next85');
    $stack->recordWalFrameWrite(3, 2, false);
    $stack->recordWalFrameWrite(4, 3, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', array $pages = [1, 2, 3]): array => SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext(
    $journal,
    $databaseBytes,
    $journalBytes,
    $walBytes,
    $databasePath,
    $savepoints(),
    'plugin-settings-next85',
    $pages,
    $mode,
    $pageSize,
    false,
    true,
    true
);
$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$singlePage = static fn (): array => $plan('restart', [2]);

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovery_then_wal_savepoint_current_source_checkpoint'],
    'restart database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'restart journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'restart wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'restart savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next85'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'restart journal action' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'restart wal recovery status' => [static fn (): mixed => $restart()['wal_recovery_status'], 'valid'],
    'restart base database bytes' => [static fn (): mixed => $restart()['base_database_bytes'], 1536],
    'restart valid wal bytes length' => [static fn (): mixed => $restart()['valid_wal_bytes_length'], 32 + (4 * (24 + $pageSize))],
    'restart before frame' => [static fn (): mixed => $restart()['before_reader_end_frame'], 4],
    'restart current frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart retained frames' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'restart discarded frames' => [static fn (): mixed => $restart()['discarded_frame_count'], 2],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'restart before sources' => [static fn (): mixed => $restart()['before_reader_sources'], ['wal', 'wal', 'wal']],
    'restart current sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['wal', 'wal', 'database']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database']],
    'restart before frame indexes' => [static fn (): mixed => $restart()['before_reader_frame_indexes'], [1, 3, 4]],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [1, 2, null]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null]],
    'restart before differs current' => [static fn (): mixed => $restart()['before_to_current_images_match'], false],
    'restart current matches next' => [static fn (): mixed => $restart()['current_to_next_images_match'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], true],
    'restart next not preserved wal' => [static fn (): mixed => $restart()['next_uses_preserved_wal'], false],
    'restart before sees rolled back active draft' => [static fn (): mixed => str_contains((string) $restart()['before_reader'][1]['image'], 'rolled back active_plugins draft'), true],
    'restart before sees rolled back transient commit' => [static fn (): mixed => str_contains((string) $restart()['before_reader'][2]['image'], 'rolled back transient commit'), true],
    'restart current sees retained active plugins' => [static fn (): mixed => str_contains((string) $restart()['current_reader'][1]['image'], 'retained active_plugins commit'), true],
    'restart current falls back to hot clean transient' => [static fn (): mixed => str_contains((string) $restart()['current_reader'][2]['image'], 'clean transient before crashed'), true],
    'restart next sees retained active plugins' => [static fn (): mixed => str_contains((string) $restart()['next_reader'][1]['image'], 'retained active_plugins commit'), true],
    'restart next excludes dirty transient' => [static fn (): mixed => str_contains((string) $restart()['next_reader'][2]['image'], 'dirty transient'), false],
    'restart hot recovery restored clean schema' => [static fn (): mixed => str_contains($restart()['hot_recovery']['payloads'][$databasePath . '#hot-journal'], 'clean schema before crashed'), true],
    'restart hot recovery dropped dirty schema' => [static fn (): mixed => str_contains($restart()['hot_recovery']['payloads'][$databasePath . '#hot-journal'], 'dirty schema'), false],
    'restart savepoint checkpoint original frames' => [static fn (): mixed => $restart()['savepoint_checkpoint']['original_frame_count'], 4],
    'restart savepoint checkpoint current wal bytes' => [static fn (): mixed => $restart()['savepoint_checkpoint']['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'restart checkpoint database includes retained schema draft' => [static fn (): mixed => str_contains($restart()['savepoint_checkpoint']['current_durable']['database_bytes'], 'retained schema draft'), true],
    'restart checkpoint database includes retained active plugins' => [static fn (): mixed => str_contains($restart()['savepoint_checkpoint']['current_durable']['database_bytes'], 'retained active_plugins commit'), true],
    'restart checkpoint database excludes rolled back active draft' => [static fn (): mixed => str_contains($restart()['savepoint_checkpoint']['current_durable']['database_bytes'], 'rolled back active_plugins draft'), false],
    'restart checkpoint database keeps clean transient' => [static fn (): mixed => str_contains($restart()['savepoint_checkpoint']['current_durable']['database_bytes'], 'clean transient before crashed'), true],
    'restart operation hot restore first' => [static fn (): mixed => $restart()['operations'][0]['reason'], 'restore_hot_journal_database_before_wal_recovery'],
    'restart operation checkpoint last' => [static fn (): mixed => $restart()['operations'][count($restart()['operations']) - 1]['reason'], 'checkpoint_retained_wal_prefix_after_savepoint_rollback'],
    'restart dependency marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-savepoint-current-source-next85', $restart()['dependencies'], true), true],
    'restart dependency hot wal' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-recovery', $restart()['dependencies'], true), true],
    'restart dependency savepoint current' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],
    'truncate status ready' => [static fn (): mixed => $truncate()['status'], 'ready'],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate next frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate current matches next' => [static fn (): mixed => $truncate()['current_to_next_images_match'], true],
    'single page before source' => [static fn (): mixed => $singlePage()['before_reader_sources'], ['wal']],
    'single page current source' => [static fn (): mixed => $singlePage()['current_reader_sources'], ['wal']],
    'single page next source' => [static fn (): mixed => $singlePage()['next_reader_sources'], ['database']],
    'single page images match current next' => [static fn (): mixed => $singlePage()['current_to_next_images_match'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal wal savepoint current source next85 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager hot journal wal savepoint current source next85 rejects empty savepoint'] = static function (TestRunner $t) use ($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints(), '', [1], 'restart', $pageSize));
};

$tests['pager hot journal wal savepoint current source next85 rejects empty pages'] = static function (TestRunner $t) use ($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints(), 'plugin-settings-next85', [], 'restart', $pageSize));
};

$tests['pager hot journal wal savepoint current source next85 rejects passive mode'] = static function (TestRunner $t) use ($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints(), 'plugin-settings-next85', [1], 'passive', $pageSize));
};

$tests['pager hot journal wal savepoint current source next85 rejects non integer page'] = static function (TestRunner $t) use ($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $savepoints(), 'plugin-settings-next85', ['2'], 'restart', $pageSize));
};

$tests['pager hot journal wal savepoint current source next85 accepts self consistent alternate wal salts'] = static function (TestRunner $t) use ($journal, $databaseBytes, $journalBytes, $staleWalBytes, $databasePath, $savepoints, $pageSize): void {
    $alternate = SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $staleWalBytes, $databasePath, $savepoints(), 'plugin-settings-next85', [1], 'restart', $pageSize);
    $t->same('ready', $alternate['status']);
};

$tests['pager hot journal wal savepoint current source next85 short source still checkpoints retained prefix'] = static function (TestRunner $t) use ($journal, $databaseBytes, $journalBytes, $shortWalBytes, $databasePath, $savepoints, $pageSize): void {
    $short = SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $shortWalBytes, $databasePath, $savepoints(), 'plugin-settings-next85', [1], 'restart', $pageSize);
    $t->same(2, $short['retained_frame_count']);
};

return $tests;
