<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointReplayPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$masterPath = '/wp-content/database/wp-master-journal82';
$journalPath = $databasePath . '-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanHeader = $page('clean schema before stale master current next82');
$cleanOptions = $page('clean active_plugins before stale master current next82');
$cleanIndex = $page('clean autoload index before stale master current next82');
$dirtyHeader = $page('dirty schema after crash current next82');
$dirtyOptions = $page('dirty active_plugins after crash current next82');
$dirtyIndex = $page('dirty autoload index after crash current next82');
$databaseBytes = $dirtyHeader . $dirtyOptions . $dirtyIndex;

$makeJournalBytes = static function (array $pages, int $initialPageCount = 3) use ($sectorSize, $pageSize): string {
    $nonce = 0x19820425;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x20260528;
    $salt2 = 0x00000082;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 82, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
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
]);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'wal schema retained before plugin current next82'],
    [2, 3, 'wal active_plugins retained commit current next82'],
    [3, 0, 'wal autoload draft after savepoint current next82'],
    [2, 3, 'wal plugin commit discarded current next82'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wordpress_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_batch');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);

    return $stack;
};

$currentMaster = $journalPath . "\n";
$nextMasterPresent = $journalPath . "\n";
$nextMasterMissing = null;

$present = static fn (): array => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
    $masterPath,
    $currentMaster,
    $nextMasterPresent,
    $journal,
    $databaseBytes,
    $journalBytes,
    $makeStack(),
    'plugin_batch',
    $wal,
    $walBytes,
    $databasePath,
    [1, 2, 3]
);

$stale = static fn (): array => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
    $masterPath,
    $currentMaster,
    $nextMasterMissing,
    $journal,
    $databaseBytes,
    $journalBytes,
    $makeStack(),
    'plugin_batch',
    $wal,
    $walBytes,
    $databasePath,
    [1, 2, 3]
);

$created = static fn (): array => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
    $masterPath,
    null,
    $nextMasterPresent,
    $journal,
    $databaseBytes,
    $journalBytes,
    $makeStack(),
    'plugin_batch',
    $wal,
    $walBytes,
    $databasePath,
    [2]
);

$reserved = static fn (): array => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
    $masterPath,
    $currentMaster,
    $nextMasterPresent,
    $journal,
    $databaseBytes,
    $journalBytes,
    $makeStack(),
    'plugin_batch',
    $wal,
    $walBytes,
    $databasePath,
    [1],
    true
);

$cases = [
    'present status recovers' => [static fn (): mixed => $present()['status'], 'master_journal_current_source_savepoint_wal_recovered'],
    'present reason allows replay' => [static fn (): mixed => $present()['reason'], 'next_master_journal_member_allows_savepoint_wal_replay'],
    'present next member true' => [static fn (): mixed => $present()['next_master_member'], true],
    'present stale current false' => [static fn (): mixed => $present()['stale_current_member'], false],
    'present replay recovered' => [static fn (): mixed => $present()['replay']['hot_recovered'], true],
    'present replay status' => [static fn (): mixed => $present()['replay']['status'], 'hot_journal_recovered_savepoint_wal_replayed'],
    'present replay reason' => [static fn (): mixed => $present()['replay']['reason'], 'hot_journal_recovered_before_savepoint_wal_replay'],
    'present cache current member' => [static fn (): mixed => $present()['master_cache']['journal_rechecks'][$journalPath]['current_member'], true],
    'present cache next member' => [static fn (): mixed => $present()['master_cache']['journal_rechecks'][$journalPath]['next_member'], true],
    'present cache action retained' => [static fn (): mixed => $present()['master_cache']['journal_rechecks'][$journalPath]['cache_action'], 'retain_cached_hot_journal'],
    'present hot reason ok' => [static fn (): mixed => $present()['replay']['hot_journal']['reason'], 'hot_journal_recovered'],
    'present journal action deletes' => [static fn (): mixed => $present()['replay']['journal_action'], 'delete_journal_after_recovery'],
    'present retained frame count' => [static fn (): mixed => $present()['replay']['retained_frame_count'], 2],
    'present discarded frame count' => [static fn (): mixed => $present()['replay']['discarded_frame_count'], 2],
    'present checkpoint can run' => [static fn (): mixed => $present()['replay']['can_checkpoint'], true],
    'present next uses checkpoint database' => [static fn (): mixed => $present()['replay']['next_uses_checkpoint_database'], true],
    'present current sources' => [static fn (): mixed => $present()['replay']['current_reader_sources'], ['wal', 'wal', 'database']],
    'present next sources' => [static fn (): mixed => $present()['replay']['next_reader_sources'], ['wal', 'wal', 'database']],
    'present current frames' => [static fn (): mixed => $present()['replay']['current_reader_frame_indexes'], [1, 2, null]],
    'present next frames' => [static fn (): mixed => $present()['replay']['next_reader_frame_indexes'], [1, 2, null]],
    'present images match' => [static fn (): mixed => $present()['replay']['images_match'], true],
    'present checkpoint includes clean journal fallback' => [static fn (): mixed => str_contains((string) $present()['replay']['wal_recovery']['checkpoint_database_bytes'], 'clean autoload index'), true],
    'present checkpoint includes retained wal' => [static fn (): mixed => str_contains((string) $present()['replay']['wal_recovery']['checkpoint_database_bytes'], 'wal active_plugins retained commit'), true],
    'present checkpoint excludes dirty option' => [static fn (): mixed => str_contains((string) $present()['replay']['wal_recovery']['checkpoint_database_bytes'], 'dirty active_plugins'), false],
    'present checkpoint excludes discarded plugin' => [static fn (): mixed => str_contains((string) $present()['replay']['wal_recovery']['checkpoint_database_bytes'], 'wal plugin commit discarded'), false],
    'present replay operations start with replay write' => [static fn (): mixed => $present()['replay']['operations'][0]['reason'], 'restore_hot_journal_database_before_savepoint_wal_replay'],
    'present replay operations delete journal' => [static fn (): mixed => $present()['replay']['operations'][2]['reason'], 'delete_hot_journal_before_savepoint_wal_replay'],
    'present payload includes hot database' => [static fn (): mixed => array_key_exists($databasePath . '#hot-journal', $present()['payloads']), true],
    'present payload includes checkpoint' => [static fn (): mixed => array_key_exists($databasePath . '#savepoint-wal-checkpoint', $present()['payloads']), true],
    'present payload includes wal' => [static fn (): mixed => array_key_exists($databasePath . '-wal', $present()['payloads']), true],
    'present dependency includes next82' => [static fn (): mixed => in_array('sqlite-wal-savepoint-master-journal-current-source-next82', $present()['dependencies'], true), true],
    'present dependency includes cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-cache-current-next77', $present()['dependencies'], true), true],
    'present dependency includes replay' => [static fn (): mixed => in_array('sqlite-hot-journal-savepoint-wal-replay-current-next', $present()['dependencies'], true), true],
    'stale status skips' => [static fn (): mixed => $stale()['status'], 'master_journal_current_source_savepoint_wal_skipped'],
    'stale reason rechecked' => [static fn (): mixed => $stale()['reason'], 'stale_current_master_journal_member_rechecked_before_replay'],
    'stale next member false' => [static fn (): mixed => $stale()['next_master_member'], false],
    'stale current member true' => [static fn (): mixed => $stale()['stale_current_member'], true],
    'stale replay not recovered' => [static fn (): mixed => $stale()['replay']['hot_recovered'], false],
    'stale hot reason missing super' => [static fn (): mixed => $stale()['replay']['hot_journal']['reason'], 'missing_super_journal'],
    'stale journal preserved' => [static fn (): mixed => $stale()['replay']['journal_action'], 'preserve_journal'],
    'stale cache invalidated' => [static fn (): mixed => $stale()['master_cache']['cache_invalidated'], true],
    'stale cache status cleared' => [static fn (): mixed => $stale()['master_cache']['status'], 'master_journal_cache_cleared_current_next'],
    'stale delta removed journal' => [static fn (): mixed => $stale()['master_cache']['member_delta']['removed'], [$journalPath]],
    'stale cache action clear' => [static fn (): mixed => $stale()['master_cache']['journal_rechecks'][$journalPath]['cache_action'], 'clear_cached_hot_journal'],
    'stale operations begin invalidation' => [static fn (): mixed => $stale()['operations'][0]['reason'], 'master_journal_membership_changed_between_current_and_next'],
    'stale replay first operation writes wal prefix' => [static fn (): mixed => $stale()['replay']['operations'][0]['reason'], 'checkpoint_retained_wal_prefix_after_hot_journal'],
    'stale payload lacks hot journal image' => [static fn (): mixed => array_key_exists($databasePath . '#hot-journal', $stale()['payloads']), false],
    'stale page three uses dirty fallback' => [static fn (): mixed => str_contains($stale()['replay']['current_reader'][2]['image'], 'dirty autoload index'), true],
    'stale still truncates savepoint wal' => [static fn (): mixed => $stale()['replay']['discarded_frame_count'], 2],
    'created status recovers' => [static fn (): mixed => $created()['status'], 'master_journal_current_source_savepoint_wal_recovered'],
    'created delta added journal' => [static fn (): mixed => $created()['master_cache']['member_delta']['added'], [$journalPath]],
    'created cache action candidate' => [static fn (): mixed => $created()['master_cache']['journal_rechecks'][$journalPath]['cache_action'], 'candidate_new_hot_journal'],
    'created single page source' => [static fn (): mixed => $created()['replay']['current_reader_sources'], ['wal']],
    'created single page frame' => [static fn (): mixed => $created()['replay']['current_reader_frame_indexes'], [2]],
    'reserved status skips' => [static fn (): mixed => $reserved()['status'], 'master_journal_current_source_savepoint_wal_skipped'],
    'reserved hot reason reserved lock' => [static fn (): mixed => $reserved()['replay']['hot_journal']['reason'], 'database_has_reserved_lock'],
    'reserved cache action refresh' => [static fn (): mixed => $reserved()['master_cache']['journal_rechecks'][$journalPath]['cache_action'], 'reuse_cached_non_hot_state'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint master journal current source next82 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint master journal current source next82 rejects empty master path'] = static function (TestRunner $t) use ($currentMaster, $journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext('', $currentMaster, null, $journal, $databaseBytes, $journalBytes, $makeStack(), 'plugin_batch', $wal, $walBytes, $databasePath, [1]));
};

$tests['wal savepoint master journal current source next82 rejects empty database path'] = static function (TestRunner $t) use ($masterPath, $currentMaster, $journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext($masterPath, $currentMaster, null, $journal, $databaseBytes, $journalBytes, $makeStack(), 'plugin_batch', $wal, $walBytes, '', [1]));
};

$tests['wal savepoint master journal current source next82 rejects missing savepoint'] = static function (TestRunner $t) use ($masterPath, $currentMaster, $journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext($masterPath, $currentMaster, null, $journal, $databaseBytes, $journalBytes, $makeStack(), 'missing', $wal, $walBytes, $databasePath, [1]));
};

return $tests;
