<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$cleanHeader = $page('clean header before interrupted plugin import');
$cleanOptions = $page('clean wp_options before interrupted plugin import');
$cleanIndex = $page('clean autoload index before interrupted plugin import');
$dirtyHeader = $page('dirty header after interrupted plugin import');
$dirtyOptions = $page('dirty wp_options after interrupted plugin import');
$dirtyIndex = $page('dirty autoload index after interrupted plugin import');
$dirtyTransient = $page('dirty transient page after interrupted plugin import');
$dirtyDatabase = $dirtyHeader . $dirtyOptions . $dirtyIndex . $dirtyTransient;

$makeJournalBytes = static function (array $pages, int $initialPageCount = 3) use ($sectorSize, $pageSize): string {
    $nonce = 0x27182818;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x11223344;
    $salt2 = 0x55667788;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 31, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes([
    1 => $cleanHeader,
    2 => $cleanOptions,
    3 => $cleanIndex,
], 3);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walCommittedPageTwo = $page('wal committed plugin option after hot journal recovery');
$walCommittedPageThree = $page('wal committed autoload index after hot journal recovery');
$walUncommittedPageFour = $page('wal uncommitted transient tail after hot journal recovery');
$walBytes = $makeWalBytes([
    [2, 0, $page('wal draft plugin option before commit marker')],
    [2, 3, $walCommittedPageTwo],
    [3, 3, $walCommittedPageThree],
    [4, 0, $walUncommittedPageFour],
]);
$corruptWalBytes = $walBytes . 'corrupt-tail';
$shortJournalBytes = substr($journalBytes, 0, 512);
$uncommittedWalBytes = $makeWalBytes([
    [2, 0, $page('wal only uncommitted plugin option')],
    [4, 0, $page('wal only uncommitted transient page')],
]);

$visibility = static fn (string $journalInput = null, string $walInput = null, array $pages = [1, 2, 3, 4], bool $reservedLock = false, bool $requiresSuper = false, ?bool $superExists = null): array => SQLitePagerHotJournalWalRecoveryPlan::currentNextVisibility(
    $journal,
    $dirtyDatabase,
    $journalInput ?? $journalBytes,
    $walInput ?? $walBytes,
    $databasePath,
    $pages,
    $pageSize,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$cases = [
    'status recovers hot journal and wal' => static fn (): mixed => $visibility()['status'],
    'reason is current next visibility' => static fn (): mixed => $visibility()['reason'],
    'database path preserved' => static fn (): mixed => $visibility()['database_path'],
    'journal path derived' => static fn (): mixed => $visibility()['journal_path'],
    'wal path derived' => static fn (): mixed => $visibility()['wal_path'],
    'current reader sees all original frames' => static fn (): mixed => $visibility()['current_reader_end_frame'],
    'next reader keeps committed prefix only' => static fn (): mixed => $visibility()['next_reader_end_frame'],
    'hot journal recovered flag' => static fn (): mixed => $visibility()['hot_recovered'],
    'next uses hot journal database' => static fn (): mixed => $visibility()['next_uses_hot_journal_database'],
    'next uses wal checkpoint database' => static fn (): mixed => $visibility()['next_uses_wal_checkpoint_database'],
    'discarded valid tail count' => static fn (): mixed => $visibility()['discarded_valid_tail_frame_count'],
    'discarded corrupt tail count' => static fn (): mixed => $visibility()['discarded_corrupt_tail_frame_count'],
    'current and next differ' => static fn (): mixed => $visibility()['current_images_match_next'],
    'current sources include database for page one' => static fn (): mixed => $visibility()['current_reader_sources'][0],
    'current sources include wal for page two' => static fn (): mixed => $visibility()['current_reader_sources'][1],
    'current sources include wal for page three' => static fn (): mixed => $visibility()['current_reader_sources'][2],
    'current sources include missing for page four' => static fn (): mixed => $visibility()['current_reader_sources'][3],
    'next sources include database for page one' => static fn (): mixed => $visibility()['next_reader_sources'][0],
    'next sources include wal for page two' => static fn (): mixed => $visibility()['next_reader_sources'][1],
    'next sources include wal for page three' => static fn (): mixed => $visibility()['next_reader_sources'][2],
    'next sources include missing for page four' => static fn (): mixed => $visibility()['next_reader_sources'][3],
    'current page two frame index is committed frame' => static fn (): mixed => $visibility()['current_reader_frame_indexes'][1],
    'current page three frame index is committed frame' => static fn (): mixed => $visibility()['current_reader_frame_indexes'][2],
    'current page four has no committed frame index' => static fn (): mixed => $visibility()['current_reader_frame_indexes'][3],
    'next page two frame index remains committed frame' => static fn (): mixed => $visibility()['next_reader_frame_indexes'][1],
    'next page three frame index remains committed frame' => static fn (): mixed => $visibility()['next_reader_frame_indexes'][2],
    'next page four has no committed frame index' => static fn (): mixed => $visibility()['next_reader_frame_indexes'][3],
    'current page one is dirty database header' => static fn (): mixed => str_contains($visibility()['current_reader'][0]['image'], 'dirty header'),
    'next page one is clean journal header' => static fn (): mixed => str_contains($visibility()['next_reader'][0]['image'], 'clean header'),
    'current page two sees wal committed option' => static fn (): mixed => str_contains($visibility()['current_reader'][1]['image'], 'wal committed plugin option'),
    'next page two sees wal committed option' => static fn (): mixed => str_contains($visibility()['next_reader'][1]['image'], 'wal committed plugin option'),
    'current page three sees wal committed index' => static fn (): mixed => str_contains($visibility()['current_reader'][2]['image'], 'wal committed autoload index'),
    'next page three sees wal committed index' => static fn (): mixed => str_contains($visibility()['next_reader'][2]['image'], 'wal committed autoload index'),
    'current page four reports missing after commit page count' => static fn (): mixed => $visibility()['current_reader'][3]['source'],
    'next page four reports missing after recovery' => static fn (): mixed => $visibility()['next_reader'][3]['source'],
    'current page four error recorded' => static fn (): mixed => count($visibility()['current_reader_errors']),
    'next page four error recorded' => static fn (): mixed => count($visibility()['next_reader_errors']),
    'recovery wal status' => static fn (): mixed => $visibility()['recovery']['wal_status'],
    'recovery committed frame count' => static fn (): mixed => $visibility()['recovery']['committed_frame_count'],
    'recovery wal bytes length' => static fn (): mixed => $visibility()['recovery']['wal_bytes'],
    'dependencies include hot wal recovery' => static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-recovery', $visibility()['dependencies'], true),
    'dependencies include current next visibility' => static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-current-next-visibility', $visibility()['dependencies'], true),
    'short journal skips hot recovery' => static fn (): mixed => $visibility($shortJournalBytes)['hot_recovered'],
    'short journal next does not use hot journal database' => static fn (): mixed => $visibility($shortJournalBytes)['next_uses_hot_journal_database'],
    'short journal next still uses wal checkpoint database' => static fn (): mixed => $visibility($shortJournalBytes)['next_uses_wal_checkpoint_database'],
    'short journal page one remains dirty' => static fn (): mixed => str_contains($visibility($shortJournalBytes)['next_reader'][0]['image'], 'dirty header'),
    'reserved lock skips hot recovery' => static fn (): mixed => $visibility(null, null, [1, 2], true)['hot_recovered'],
    'reserved lock page one remains dirty' => static fn (): mixed => str_contains($visibility(null, null, [1, 2], true)['next_reader'][0]['image'], 'dirty header'),
    'present super journal permits recovery' => static fn (): mixed => $visibility(null, null, [1], false, true, true)['hot_recovered'],
    'missing super journal skips recovery' => static fn (): mixed => $visibility(null, null, [1], false, true, false)['hot_recovered'],
    'uncommitted wal next keeps hot journal clean page two' => static fn (): mixed => str_contains($visibility(null, $uncommittedWalBytes, [2])['next_reader'][0]['image'], 'clean wp_options'),
    'uncommitted wal has header only next end frame' => static fn (): mixed => $visibility(null, $uncommittedWalBytes, [2])['next_reader_end_frame'],
    'uncommitted wal recovery status' => static fn (): mixed => $visibility(null, $uncommittedWalBytes, [2])['recovery']['wal_status'],
    'corrupt wal tail is counted' => static fn (): mixed => $visibility(null, $corruptWalBytes)['discarded_corrupt_tail_frame_count'],
    'corrupt wal current uses valid prefix' => static fn (): mixed => $visibility(null, $corruptWalBytes)['current_reader_end_frame'],
    'corrupt wal next still checkpoints committed page' => static fn (): mixed => str_contains($visibility(null, $corruptWalBytes)['next_reader'][1]['image'], 'wal committed plugin option'),
    'single page current next only returns one row' => static fn (): mixed => count($visibility(null, null, [2])['next_reader']),
    'empty page list rejected' => static function () use ($visibility): mixed {
        try {
            $visibility(null, null, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'non integer page rejected' => static function () use ($visibility): mixed {
        try {
            $visibility(null, null, [1, '2']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database path rejected' => static function () use ($journal, $dirtyDatabase, $journalBytes, $walBytes, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::currentNextVisibility($journal, $dirtyDatabase, $journalBytes, $walBytes, '', [1], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty wal rejected' => static function () use ($journal, $dirtyDatabase, $journalBytes, $databasePath, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::currentNextVisibility($journal, $dirtyDatabase, $journalBytes, '', $databasePath, [1], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'status recovers hot journal and wal' => 'hot_journal_recovered_wal_recovered',
    'reason is current next visibility' => 'current_dirty_reader_next_hot_journal_wal_recovery_visibility',
    'database path preserved' => $databasePath,
    'journal path derived' => $databasePath . '-journal',
    'wal path derived' => $databasePath . '-wal',
    'current reader sees all original frames' => 4,
    'next reader keeps committed prefix only' => 3,
    'hot journal recovered flag' => true,
    'next uses hot journal database' => true,
    'next uses wal checkpoint database' => true,
    'discarded valid tail count' => 1,
    'discarded corrupt tail count' => 0,
    'current and next differ' => false,
    'current sources include database for page one' => 'database',
    'current sources include wal for page two' => 'wal',
    'current sources include wal for page three' => 'wal',
    'current sources include missing for page four' => 'missing',
    'next sources include database for page one' => 'database',
    'next sources include wal for page two' => 'wal',
    'next sources include wal for page three' => 'wal',
    'next sources include missing for page four' => 'missing',
    'current page two frame index is committed frame' => 2,
    'current page three frame index is committed frame' => 3,
    'current page four has no committed frame index' => null,
    'next page two frame index remains committed frame' => 2,
    'next page three frame index remains committed frame' => 3,
    'next page four has no committed frame index' => null,
    'current page one is dirty database header' => true,
    'next page one is clean journal header' => true,
    'current page two sees wal committed option' => true,
    'next page two sees wal committed option' => true,
    'current page three sees wal committed index' => true,
    'next page three sees wal committed index' => true,
    'current page four reports missing after commit page count' => 'missing',
    'next page four reports missing after recovery' => 'missing',
    'current page four error recorded' => 1,
    'next page four error recorded' => 1,
    'recovery wal status' => 'recovered_committed_prefix',
    'recovery committed frame count' => 3,
    'recovery wal bytes length' => 1640,
    'dependencies include hot wal recovery' => true,
    'dependencies include current next visibility' => true,
    'short journal skips hot recovery' => false,
    'short journal next does not use hot journal database' => false,
    'short journal next still uses wal checkpoint database' => true,
    'short journal page one remains dirty' => true,
    'reserved lock skips hot recovery' => false,
    'reserved lock page one remains dirty' => true,
    'present super journal permits recovery' => true,
    'missing super journal skips recovery' => false,
    'uncommitted wal next keeps hot journal clean page two' => true,
    'uncommitted wal has header only next end frame' => 0,
    'uncommitted wal recovery status' => 'recovered_committed_prefix',
    'corrupt wal tail is counted' => 1,
    'corrupt wal current uses valid prefix' => 4,
    'corrupt wal next still checkpoints committed page' => true,
    'single page current next only returns one row' => 1,
    'empty page list rejected' => 'rejected',
    'non integer page rejected' => 'rejected',
    'empty database path rejected' => 'rejected',
    'empty wal rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['hot journal wal visibility current next36 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
