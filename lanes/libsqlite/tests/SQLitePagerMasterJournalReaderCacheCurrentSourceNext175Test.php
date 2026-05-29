<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next175.sqlite';
$masterPath = '/srv/wp-content/database/wp-next175.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next175-users.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next175-users.sqlite-journal");
$sourceId = 'next175-master-current-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$journalBytes = static function (array $pages, array $badChecksums = [], int $nonce = 0x175, int $initialPages = 7) use ($pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', count($pages), $nonce, $initialPages, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
    foreach ($pages as $pageNumber => $image) {
        $checksum = SQLiteRollbackJournal::pageChecksum($image, $nonce);
        if (in_array($pageNumber, $badChecksums, true)) {
            $checksum = ($checksum + 17) & 0xffffffff;
        }
        $bytes .= pack('N', $pageNumber) . $image . pack('N', $checksum);
    }
    return $bytes;
};
$journalDigest = static fn (string $bytes): string => hash('sha256', $databasePath . '-journal|' . strlen($bytes) . '|' . hash('sha256', $bytes));

$before = [
    1 => $page('next175 stale schema before checksum recovery'),
    2 => $page('next175 stale wp_options root before checksum recovery'),
    3 => $page('next175 stale active_plugins before checksum recovery'),
    4 => $page('next175 stale plugin settings before checksum recovery'),
    5 => $page('next175 unchanged comments before checksum recovery'),
    6 => $page('next175 stale cron before checksum recovery'),
    7 => $page('next175 stale optionmeta before checksum recovery'),
];
$recovered = [
    1 => $page('next175 recovered schema checksum valid'),
    2 => $page('next175 recovered wp_options root checksum valid'),
    3 => $page('next175 recovered active_plugins checksum valid'),
    4 => $page('next175 recovered plugin settings checksum corrupt'),
    6 => $page('next175 recovered cron checksum valid'),
];
$journal = $journalBytes($recovered, [4]);
$digest = $journalDigest($journal);
$cache = static fn (): array => [
    1 => ['reader_id' => 'schema', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => $digest],
    2 => ['reader_id' => 'root-refresh', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => $digest],
    3 => ['reader_id' => 'active-old-journal', 'image' => $recovered[3], 'source_id' => $sourceId, 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => hash('sha256', 'old')],
    4 => ['reader_id' => 'settings-corrupt', 'image' => $before[4], 'source_id' => $sourceId, 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => $digest],
    5 => ['reader_id' => 'comments-dirty', 'image' => $before[5], 'source_id' => $sourceId, 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => $digest, 'dirty' => true],
    6 => ['reader_id' => 'cron-pinned', 'image' => $before[6], 'source_id' => $sourceId, 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => $digest, 'pinned' => true],
    7 => ['reader_id' => 'optionmeta-old-source', 'image' => $before[7], 'source_id' => 'old-source', 'epoch' => 9, 'master_digest' => $masterDigest, 'journal_digest' => $digest],
];
$writes = [
    3 => $page('next175 rewritten active_plugins after checksum fence'),
    4 => $page('next175 blocked plugin settings write after checksum fence'),
];

$plan = static fn (
    ?array $readerCache = null,
    ?array $reads = null,
    ?array $writePages = null,
    ?string $master = null,
    ?string $journalBytesInput = null,
    ?string $database = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 9,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext175(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $journalBytesInput ?? $journal,
    $database ?? implode('', $before),
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7],
    $writePages ?? $writes,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next175'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'rollback journal page checksums fence reader-cache reuse after master-journal recovery'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'master members' => [static fn (): mixed => $plan()['master_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next175-users.sqlite-journal']],
    'master digest' => [static fn (): mixed => $plan()['master_digest'], $masterDigest],
    'journal digest' => [static fn (): mixed => $plan()['journal_digest'], $digest],
    'header page count' => [static fn (): mixed => $plan()['journal_header']['page_count'], 5],
    'header nonce' => [static fn (): mixed => $plan()['journal_header']['checksum_nonce'], 0x175],
    'valid pages' => [static fn (): mixed => $plan()['valid_journal_page_numbers'], [1, 2, 3, 6]],
    'corrupt pages' => [static fn (): mixed => $plan()['corrupt_journal_page_numbers'], [4]],
    'journal row count' => [static fn (): mixed => count($plan()['journal_rows']), 5],
    'journal row valid' => [static fn (): mixed => $plan()['journal_rows'][0]['checksum_valid'], true],
    'journal row corrupt' => [static fn (): mixed => $plan()['journal_rows'][3]['checksum_valid'], false],
    'journal expected differs' => [static fn (): mixed => $plan()['journal_rows'][3]['stored_checksum'] !== $plan()['journal_rows'][3]['expected_checksum'], true],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 7],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_matches_checksum_verified_current_source'],
    'refreshed reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_checksum_verified_current_source'],
    'journal mismatch reason' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'reader_cache_journal_digest_mismatch_after_checksum_recovery'],
    'checksum quarantine reason' => [static fn (): mixed => $plan()['cache_rows'][3]['reason'], 'reader_cache_page_quarantined_by_rollback_journal_checksum'],
    'dirty reason' => [static fn (): mixed => $plan()['cache_rows'][4]['reason'], 'dirty_reader_cache_cannot_cross_checksum_verified_journal'],
    'pinned reason' => [static fn (): mixed => $plan()['cache_rows'][5]['reason'], 'pinned_reader_cache_image_mismatch_after_checksum_recovery'],
    'source reason' => [static fn (): mixed => $plan()['cache_rows'][6]['reason'], 'reader_cache_source_id_mismatch_after_checksum_recovery'],
    'checksum false on corrupt cache row' => [static fn (): mixed => $plan()['cache_rows'][3]['checksum_valid'], false],
    'journal digest match false' => [static fn (): mixed => $plan()['cache_rows'][2]['journal_digest_matches'], false],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read three miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read four quarantined' => [static fn (): mixed => $plan()['next_reads'][3]['quarantined'], true],
    'read four source' => [static fn (): mixed => $plan()['next_reads'][3]['source'], 'rollback-journal-checksum-quarantine-next175'],
    'read two prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next175 recovered wp_options root checksum valid'],
    'read six prefix database source' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next175 recovered cron checksum valid'],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active allowed' => [static fn (): mixed => $plan()['next_writes'][0]['allowed'], true],
    'write active reason' => [static fn (): mixed => $plan()['next_writes'][0]['reason'], 'before_image_from_checksum_verified_current_source'],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next175 recovered active_plugins checksum valid'],
    'write settings blocked' => [static fn (): mixed => $plan()['next_writes'][1]['allowed'], false],
    'write settings reason' => [static fn (): mixed => $plan()['next_writes'][1]['reason'], 'write_blocked_until_checksum_verified_before_image'],
    'final page three source' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-checksum-reader-cache-next175'],
    'final page four source unchanged' => [static fn (): mixed => $plan()['final_sources'][4], 'database-before-checksum-recovery-next175'],
    'final page three prefix' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next175 rewritten active_plugins after checksum fence'],
    'operation master read' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal_for_reader_cache_checksum_next175'],
    'operation checksum verify' => [static fn (): mixed => $plan()['operations'][1]['op'], 'verify_current_rollback_journal_page_checksums_next175'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_reader_cache_checksum_current_source_next175'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_reader_cache_checksum_current_source_next175'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][4]['op'], 'invalidate_reader_cache_checksum_next175'],
    'operation block read' => [static fn (): mixed => $plan()['operations'][12]['op'], 'next_read_blocks_on_rollback_journal_checksum_next175'],
    'operation write capture' => [static fn (): mixed => $plan()['operations'][16]['op'], 'capture_next_write_after_checksum_reader_cache_next175'],
    'operation write block' => [static fn (): mixed => $plan()['operations'][17]['op'], 'block_next_write_after_checksum_reader_cache_next175'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rollback-journal-page-checksum-current-source-fence', $plan()['dependencies'], true), true],
    'non overlap mentions checksum' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'checksum admission'), true],
    'all valid no reopen' => [static function () use ($plan, $cache, $journalBytes, $recovered, $journalDigest): mixed {
        $validJournal = $journalBytes([1 => $recovered[1]]);
        return $plan([1 => array_merge($cache()[1], ['journal_digest' => $journalDigest($validJournal)])], [1], [], null, $validJournal, null)['requires_reader_reopen'];
    }, false],
    'all valid retained' => [static function () use ($plan, $cache, $journalBytes, $recovered, $journalDigest): mixed {
        $validJournal = $journalBytes([1 => $recovered[1]]);
        return $plan([1 => array_merge($cache()[1], ['journal_digest' => $journalDigest($validJournal)])], [1], [], null, $validJournal, null)['retained_page_numbers'];
    }, [1]],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => array_merge($cache()[1], ['epoch' => 8])], [1], [])['cache_rows'][0]['reason'], 'reader_cache_epoch_mismatch_after_checksum_recovery'],
    'master mismatch invalidates' => [static fn (): mixed => $plan([1 => array_merge($cache()[1], ['master_digest' => hash('sha256', 'old')])], [1], [])['cache_rows'][0]['reason'], 'reader_cache_master_digest_mismatch_after_checksum_recovery'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next175 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty master bytes rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'empty journal rejected' => static fn () => $plan(null, null, null, null, ''),
    'bad journal header rejected' => static fn () => $plan(null, null, null, null, 'bad'),
    'journal page size mismatch rejected' => static fn () => $plan(null, null, null, null, $journalBytes([1 => $recovered[1]]), null, 1024),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, implode('', $before) . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty next work rejected' => static fn () => $plan(null, [], []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'journal outside rejected' => static fn () => $plan(null, null, null, null, $journalBytes([8 => $page('outside')])),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'short cache image rejected' => static fn () => $plan([1 => array_merge($cache()[1], ['image' => 'short'])]),
    'empty cache source rejected' => static fn () => $plan([1 => array_merge($cache()[1], ['source_id' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => array_merge($cache()[1], ['epoch' => 0])]),
    'empty master digest rejected' => static fn () => $plan([1 => array_merge($cache()[1], ['master_digest' => ''])]),
    'empty journal digest rejected' => static fn () => $plan([1 => array_merge($cache()[1], ['journal_digest' => ''])]),
    'cache outside rejected' => static fn () => $plan([8 => array_merge($cache()[1], ['image' => $page('outside')])]),
    'bad read page rejected' => static fn () => $plan(null, [0], []),
    'read outside rejected' => static fn () => $plan(null, [8], []),
    'bad write page rejected' => static fn () => $plan(null, [], [0 => $writes[3]]),
    'short write page rejected' => static fn () => $plan(null, [], [3 => 'short']),
    'write outside rejected' => static fn () => $plan(null, [], [8 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next175 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
