<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/wp/wp-content/database/wp-next134.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp/wp-content/database/wp-next134-master';
$otherJournalPath = '/srv/wp/wp-content/database/other-next134.sqlite-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanHeader = $page('next134 clean sqlite header from current master');
$cleanOptions = $page('next134 clean wp_options from current master');
$cleanAutoload = $page('next134 clean autoload index from current master');
$dirtyHeader = $page('next134 dirty sqlite header from crashed import');
$dirtyOptions = $page('next134 dirty active_plugins from crashed import');
$dirtyAutoload = $page('next134 dirty autoload index from crashed import');
$savepointOptions = $page('next134 savepoint writes active_plugins retry');
$savepointTransient = $page('next134 savepoint writes transient retry');

$databaseBytes = $dirtyHeader . $dirtyOptions . $dirtyAutoload;
$nonce = 0x2026134;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ([1 => $cleanHeader, 2 => $cleanOptions, 3 => $cleanAutoload] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$currentCache = [
    1 => $dirtyHeader,
    2 => $dirtyOptions,
    3 => $dirtyAutoload,
];
$savepointWrites = [
    2 => $savepointOptions,
    4 => $savepointTransient,
];
$cachedMaster = $otherJournalPath . "\n";
$currentMaster = $journalPath . "\n" . $otherJournalPath . "\n";
$nextMaster = $journalPath . "\n";

$plan = static fn (
    ?string $cached = null,
    ?string $current = null,
    ?string $next = null,
    ?string $database = null,
    ?string $master = null,
    array $cache = null,
    array $writes = null,
    array $reads = [1, 2, 3, 4],
    bool $reserved = false,
): array => SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan::plan(
    $database ?? $databasePath,
    $master ?? $masterPath,
    func_num_args() >= 1 ? $cached : $cachedMaster,
    func_num_args() >= 2 ? $current : $currentMaster,
    func_num_args() >= 3 ? $next : $nextMaster,
    $journal,
    $databaseBytes,
    $journalBytes,
    'plugin_batch_next134',
    $cache ?? $currentCache,
    $writes ?? $savepointWrites,
    $reads,
    $reserved,
);

$sameCachePlan = static fn (): array => $plan($currentMaster, $currentMaster, $nextMaster);
$blockedPlan = static fn (): array => $plan($currentMaster, $currentMaster, null);
$reservedPlan = static fn (): array => $plan($currentMaster, $currentMaster, $nextMaster, null, null, null, null, [1, 2], true);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_master_savepoint_current_source_next134'],
    'reason stale rejected' => [static fn (): mixed => $plan()['reason'], 'stale_master_cache_rejected_before_savepoint_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin_batch_next134'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'cached stale' => [static fn (): mixed => $plan()['cached_stale_rejected'], true],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], [$otherJournalPath]],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$journalPath, $otherJournalPath]],
    'next members' => [static fn (): mixed => $plan()['next_members'], [$journalPath]],
    'current member true' => [static fn (): mixed => $plan()['current_master_member'], true],
    'next member true' => [static fn (): mixed => $plan()['next_master_member'], true],
    'hot recovered' => [static fn (): mixed => $plan()['hot_recovered'], true],
    'hot reason' => [static fn (): mixed => $plan()['hot_journal_reason'], 'hot_journal_recovery_required'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'delete_journal_after_recovery'],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_pages'], [1, 2, 3]],
    'invalidated first reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][0]['reason'], 'hot_journal_recovered_page'],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3]],
    'savepoint written pages' => [static fn (): mixed => $plan()['savepoint_written_pages'], [2, 4]],
    'savepoint rollback pages' => [static fn (): mixed => $plan()['savepoint_rollback_pages'], [2, 4]],
    'read page numbers' => [static fn (): mixed => $plan()['read_page_numbers'], [1, 2, 3, 4]],
    'read sources' => [static fn (): mixed => $plan()['read_sources'], ['hot-journal-recovered-current-source', 'savepoint-rollback-before-image', 'hot-journal-recovered-current-source', 'savepoint-rollback-before-image']],
    'read label header' => [static fn (): mixed => $plan()['read_labels'][0], 'next134 clean sqlite header from current master'],
    'read label options restored' => [static fn (): mixed => $plan()['read_labels'][1], 'next134 clean wp_options from current master'],
    'read label autoload restored' => [static fn (): mixed => $plan()['read_labels'][2], 'next134 clean autoload index from current master'],
    'read label transient zero' => [static fn (): mixed => $plan()['reads'][3]['zero_filled_short_read'], false],
    'before image options' => [static fn (): mixed => $plan()['before_image_labels'][2], 'next134 clean wp_options from current master'],
    'before image transient blank' => [static fn (): mixed => $plan()['before_image_labels'][4], ''],
    'after write options' => [static fn (): mixed => $plan()['after_write_labels'][2], 'next134 savepoint writes active_plugins retry'],
    'after write transient' => [static fn (): mixed => $plan()['after_write_labels'][4], 'next134 savepoint writes transient retry'],
    'after rollback options' => [static fn (): mixed => $plan()['after_rollback_labels'][2], 'next134 clean wp_options from current master'],
    'after rollback transient' => [static fn (): mixed => $plan()['after_rollback_labels'][4], ''],
    'hot recovered label header' => [static fn (): mixed => $plan()['hot_recovered_labels'][1], 'next134 clean sqlite header from current master'],
    'payload present' => [static fn (): mixed => array_key_exists($databasePath . '#hot-journal-next134', $plan()['payloads']), true],
    'payload includes clean options' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#hot-journal-next134'], 'next134 clean wp_options'), true],
    'payload excludes dirty options' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#hot-journal-next134'], 'next134 dirty active_plugins'), false],
    'operation discard first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'discard_cached_master_journal_source'],
    'operation read second' => [static fn (): mixed => $plan()['operations'][1]['op'], 'read_current_master_journal'],
    'operation hot write third' => [static fn (): mixed => $plan()['operations'][2]['op'], 'write'],
    'operation delete fourth' => [static fn (): mixed => $plan()['operations'][3]['op'], 'delete'],
    'operation capture options' => [static fn (): mixed => $plan()['operations'][4]['op'], 'capture_savepoint_before_image'],
    'operation write options' => [static fn (): mixed => $plan()['operations'][5]['op'], 'write_savepoint_page'],
    'operation rollback options' => [static fn (): mixed => $plan()['operations'][8]['op'], 'rollback_savepoint_before_image'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 10],
    'same cache reason' => [static fn (): mixed => $sameCachePlan()['reason'], 'master_cache_matches_current_savepoint_source'],
    'same cache stale false' => [static fn (): mixed => $sameCachePlan()['cached_stale_rejected'], false],
    'same cache first op read' => [static fn (): mixed => $sameCachePlan()['operations'][0]['op'], 'read_current_master_journal'],
    'same cache op count' => [static fn (): mixed => count($sameCachePlan()['operations']), 9],
    'blocked status' => [static fn (): mixed => $blockedPlan()['status'], 'pager_hot_journal_master_savepoint_current_source_blocked_next134'],
    'blocked reason' => [static fn (): mixed => $blockedPlan()['reason'], 'missing_super_journal'],
    'blocked next member false' => [static fn (): mixed => $blockedPlan()['next_master_member'], false],
    'blocked hot false' => [static fn (): mixed => $blockedPlan()['hot_recovered'], false],
    'blocked journal preserved' => [static fn (): mixed => $blockedPlan()['journal_action'], 'preserve_journal'],
    'blocked no payload' => [static fn (): mixed => $blockedPlan()['payloads'], []],
    'reserved status' => [static fn (): mixed => $reservedPlan()['status'], 'pager_hot_journal_master_savepoint_current_source_blocked_next134'],
    'reserved reason' => [static fn (): mixed => $reservedPlan()['reason'], 'database_has_reserved_lock'],
    'reserved hot reason' => [static fn (): mixed => $reservedPlan()['hot_journal_reason'], 'database_has_reserved_lock'],
    'reserved reads subset' => [static fn (): mixed => $reservedPlan()['read_page_numbers'], [1, 2]],
    'dependency next134' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-master-savepoint-current-source-next134', $plan()['dependencies'], true), true],
    'dependency recovery' => [static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $plan()['dependencies'], true), true],
    'dependency recheck' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-recheck', $plan()['dependencies'], true), true],
    'dependency savepoint' => [static fn (): mixed => in_array('sqlite-savepoint-before-image-current-source', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal master savepoint current source next134 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, ''),
    'missing current master rejected' => static fn () => $plan($cachedMaster, null),
    'empty current master rejected' => static fn () => $plan($cachedMaster, ''),
    'current master missing journal rejected' => static fn () => $plan($cachedMaster, $otherJournalPath . "\n", $otherJournalPath . "\n"),
    'empty cache rejected' => static fn () => $plan(null, null, null, null, null, []),
    'empty writes rejected' => static fn () => $plan(null, null, null, null, null, null, []),
    'empty reads rejected' => static fn () => $plan(null, null, null, null, null, null, null, []),
    'bad cache page rejected' => static fn () => $plan(null, null, null, null, null, [0 => $dirtyHeader]),
    'bad cache image rejected' => static fn () => $plan(null, null, null, null, null, [1 => 'short']),
    'bad write page rejected' => static fn () => $plan(null, null, null, null, null, null, [0 => $savepointOptions]),
    'bad write image rejected' => static fn () => $plan(null, null, null, null, null, null, [2 => 'short']),
    'bad read page rejected' => static fn () => $plan(null, null, null, null, null, null, null, ['1']),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal master savepoint current source next134 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
