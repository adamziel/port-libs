<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/wp/content/database/wp-next126.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp/content/database/wp-next126-master';
$otherJournalPath = '/srv/wp/content/database/other-next126.sqlite-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanHeader = $page('next126 clean sqlite header after master recovery');
$cleanOptions = $page('next126 clean wp_options root after master recovery');
$cleanAutoload = $page('next126 clean autoload index after master recovery');
$dirtyHeader = $page('next126 dirty sqlite header crash cache');
$dirtyOptions = $page('next126 dirty active_plugins crash cache');
$dirtyAutoload = $page('next126 dirty autoload index crash cache');
$databaseBytes = $dirtyHeader . $dirtyOptions . $dirtyAutoload;

$makeJournalBytes = static function (array $pages, int $initialPageCount = 3) use ($sectorSize, $pageSize): string {
    $nonce = 0x2026126;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x20260528;
    $salt2 = 0x00000126;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 126, $salt1, $salt2);
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
    3 => $cleanAutoload,
]);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'next126 wal schema retained before savepoint'],
    [2, 3, 'next126 wal active_plugins retained commit'],
    [3, 0, 'next126 wal autoload draft inside savepoint'],
    [2, 3, 'next126 wal plugin activation discarded'],
    [3, 3, 'next126 wal transient discarded'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application_plugin_import_next126');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_batch_next126');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->recordWalFrameWrite(5, 3, true);

    return $stack;
};

$cachedMaster = $otherJournalPath . "\n";
$currentMaster = $journalPath . "\n" . $otherJournalPath . "\n";
$nextMaster = $journalPath . "\n";

$plan = static fn (
    ?string $cached = null,
    ?string $current = null,
    ?string $next = null,
    ?string $master = null,
    ?string $path = null,
    array $pages = [1, 2, 3],
    bool $reserved = false,
): array => SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan::plan(
    $master ?? $masterPath,
    func_num_args() >= 1 ? $cached : $cachedMaster,
    func_num_args() >= 2 ? $current : $currentMaster,
    func_num_args() >= 3 ? $next : $nextMaster,
    $journal,
    $databaseBytes,
    $journalBytes,
    $makeStack(),
    'plugin_batch_next126',
    $wal,
    $walBytes,
    $path ?? $databasePath,
    $pages,
    $reserved,
);

$sameCachePlan = static fn (): array => $plan($currentMaster, $currentMaster, $nextMaster);
$blockedPlan = static fn (): array => $plan($cachedMaster, $currentMaster, null);
$reservedPlan = static fn (): array => $plan($currentMaster, $currentMaster, $nextMaster, null, null, [1, 2], true);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_wal_savepoint_master_journal_current_source_next126'],
    'reason stale rejected' => [static fn (): mixed => $plan()['reason'], 'stale_cached_master_journal_rejected_before_wal_savepoint_replay'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin_batch_next126'],
    'page numbers' => [static fn (): mixed => $plan()['page_numbers'], [1, 2, 3]],
    'cached stale true' => [static fn (): mixed => $plan()['cached_stale_rejected'], true],
    'cached members only other' => [static fn (): mixed => $plan()['cached_members'], [$otherJournalPath]],
    'current members include journal' => [static fn (): mixed => $plan()['current_members'], [$journalPath, $otherJournalPath]],
    'next members journal only' => [static fn (): mixed => $plan()['next_members'], [$journalPath]],
    'current member true' => [static fn (): mixed => $plan()['current_master_member'], true],
    'next member true' => [static fn (): mixed => $plan()['next_master_member'], true],
    'cached skipped' => [static fn (): mixed => $plan()['cached_status'], 'master_journal_current_source_savepoint_wal_skipped'],
    'current recovered' => [static fn (): mixed => $plan()['current_status'], 'master_journal_current_source_savepoint_wal_recovered'],
    'cached hot false' => [static fn (): mixed => $plan()['cached_hot_recovered'], false],
    'current hot true' => [static fn (): mixed => $plan()['current_hot_recovered'], true],
    'rollback frame' => [static fn (): mixed => $plan()['rollback_to_frame'], 2],
    'retained frames' => [static fn (): mixed => $plan()['retained_frame_count'], 2],
    'discarded frames' => [static fn (): mixed => $plan()['discarded_frame_count'], 3],
    'current reader sources' => [static fn (): mixed => $plan()['current_reader_sources'], ['wal', 'wal', 'database']],
    'next reader sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['wal', 'wal', 'database']],
    'current reader frames' => [static fn (): mixed => $plan()['current_reader_frame_indexes'], [1, 2, null]],
    'next reader frames' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [1, 2, null]],
    'images match' => [static fn (): mixed => $plan()['images_match'], true],
    'checkpoint bytes include clean autoload' => [static fn (): mixed => str_contains((string) $plan()['checkpoint_database_bytes'], 'next126 clean autoload index'), true],
    'checkpoint bytes include retained wal' => [static fn (): mixed => str_contains((string) $plan()['checkpoint_database_bytes'], 'next126 wal active_plugins retained commit'), true],
    'checkpoint bytes exclude dirty active plugins' => [static fn (): mixed => str_contains((string) $plan()['checkpoint_database_bytes'], 'next126 dirty active_plugins'), false],
    'checkpoint bytes exclude discarded activation' => [static fn (): mixed => str_contains((string) $plan()['checkpoint_database_bytes'], 'next126 wal plugin activation discarded'), false],
    'cached replay next member false' => [static fn (): mixed => $plan()['cached_replay']['next_master_member'], false],
    'current replay next member true' => [static fn (): mixed => $plan()['current_replay']['next_master_member'], true],
    'current replay payload hot journal' => [static fn (): mixed => array_key_exists($databasePath . '#hot-journal', $plan()['payloads']), true],
    'current replay payload checkpoint' => [static fn (): mixed => array_key_exists($databasePath . '#savepoint-wal-checkpoint', $plan()['payloads']), true],
    'current replay payload wal' => [static fn (): mixed => array_key_exists($databasePath . '-wal', $plan()['payloads']), true],
    'operation discard cached first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'discard_cached_master_journal_wal_savepoint_source'],
    'operation discard reason' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'cached_master_journal_members_do_not_match_current_source_next126'],
    'operation read current second' => [static fn (): mixed => $plan()['operations'][1]['op'], 'read_current_master_journal'],
    'operation read reason' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'read_current_master_journal_before_wal_savepoint_replay_next126'],
    'operation replay reasons decorated' => [static fn (): mixed => str_ends_with($plan()['operations'][2]['reason'], '_after_current_master_source_next126'), true],
    'operation count stale' => [static fn (): mixed => count($plan()['operations']), 12],
    'dependency next126' => [static fn (): mixed => in_array('sqlite-pager-wal-savepoint-master-journal-current-source-next126', $plan()['dependencies'], true), true],
    'dependency recheck' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-recheck-before-wal-savepoint', $plan()['dependencies'], true), true],
    'dependency stale cache' => [static fn (): mixed => in_array('sqlite-stale-master-journal-cache-rejected-before-wal-replay', $plan()['dependencies'], true), true],
    'same cache reason' => [static fn (): mixed => $sameCachePlan()['reason'], 'cached_master_journal_matches_current_wal_savepoint_source'],
    'same cache stale false' => [static fn (): mixed => $sameCachePlan()['cached_stale_rejected'], false],
    'same cache operation first read' => [static fn (): mixed => $sameCachePlan()['operations'][0]['op'], 'read_current_master_journal'],
    'same cache operation count' => [static fn (): mixed => count($sameCachePlan()['operations']), 11],
    'blocked status' => [static fn (): mixed => $blockedPlan()['status'], 'pager_wal_savepoint_master_journal_current_source_blocked_next126'],
    'blocked reason' => [static fn (): mixed => $blockedPlan()['reason'], 'current_master_journal_missing_from_next_source_blocks_hot_recovery'],
    'blocked next member false' => [static fn (): mixed => $blockedPlan()['next_master_member'], false],
    'blocked current hot false' => [static fn (): mixed => $blockedPlan()['current_hot_recovered'], false],
    'blocked journal action preserved' => [static fn (): mixed => $blockedPlan()['current_replay']['replay']['journal_action'], 'preserve_journal'],
    'blocked no hot payload' => [static fn (): mixed => array_key_exists($databasePath . '#hot-journal', $blockedPlan()['payloads']), false],
    'reserved status blocked' => [static fn (): mixed => $reservedPlan()['status'], 'pager_wal_savepoint_master_journal_current_source_blocked_next126'],
    'reserved reason cache same' => [static fn (): mixed => $reservedPlan()['reason'], 'cached_master_journal_matches_current_wal_savepoint_source'],
    'reserved hot reason' => [static fn (): mixed => $reservedPlan()['current_replay']['replay']['hot_journal']['reason'], 'database_has_reserved_lock'],
    'reserved page subset' => [static fn (): mixed => $reservedPlan()['page_numbers'], [1, 2]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager wal savepoint master journal current source next126 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty master path rejected' => static fn () => $plan(null, null, null, ''),
    'empty database path rejected' => static fn () => $plan(null, null, null, null, ''),
    'missing current master rejected' => static fn () => $plan($cachedMaster, null),
    'empty current master rejected' => static fn () => $plan($cachedMaster, ''),
    'empty page numbers rejected' => static fn () => $plan(null, null, null, null, null, []),
    'current master missing database journal rejected' => static fn () => $plan($cachedMaster, $otherJournalPath . "\n", $otherJournalPath . "\n"),
];

foreach ($throws as $name => $callback) {
    $tests['pager wal savepoint master journal current source next126 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
