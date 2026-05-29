<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next170.sqlite';
$masterPath = '/srv/wp-content/database/wp-next170.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next170-meta.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next170-meta.sqlite-journal");
$sourceId = 'next170-before-current-rollback-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$journalBytes = static function (array $pages, int $pageCount = null, int $nonce = 0x170, int $initialPages = 8, int $sectorSize = 512) use ($pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', $pageCount ?? count($pages), $nonce, $initialPages, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0", STR_PAD_RIGHT);
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', 0);
    }
    return $bytes;
};
$journalDigest = static function (string $journalPath, string $bytes, array $pageNumbers, int $pageCount = null, int $nonce = 0x170, int $initialPages = 8, int $sectorSize = 512) use ($pageSize): string {
    return hash('sha256', implode('|', [
        $journalPath,
        strlen($bytes),
        $pageCount ?? count($pageNumbers),
        $nonce,
        $initialPages,
        $sectorSize,
        $pageSize,
        implode(',', $pageNumbers),
        hash('sha256', $bytes),
    ]));
};

$before = [
    1 => $page('next170 stale schema before current rollback journal'),
    2 => $page('next170 stale wp_options root before current rollback journal'),
    3 => $page('next170 stale active_plugins before current rollback journal'),
    4 => $page('next170 stale plugin settings before current rollback journal'),
    5 => $page('next170 unchanged comments before current rollback journal'),
    6 => $page('next170 stale transients before current rollback journal'),
    7 => $page('next170 stale cron before current rollback journal'),
    8 => $page('next170 stale optionmeta before current rollback journal'),
];
$recovered = [
    1 => $page('next170 recovered schema from current rollback journal'),
    2 => $page('next170 recovered wp_options root from current rollback journal'),
    3 => $page('next170 recovered active_plugins from current rollback journal'),
    4 => $page('next170 recovered plugin settings from current rollback journal'),
    6 => $page('next170 recovered transients from current rollback journal'),
    7 => $page('next170 recovered cron from current rollback journal'),
];
$databaseBytes = implode('', $before);
$currentJournal = $journalBytes($recovered);
$currentJournalDigest = $journalDigest($databasePath . '-journal', $currentJournal, [1, 2, 3, 4, 6, 7]);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 12,
    'master_journal_digest' => $masterDigest,
    'journal_source_digest' => $currentJournalDigest,
    'journal_page_count' => 6,
    'journal_initial_page_count' => 8,
    'journal_page_numbers' => [1, 2, 3, 4, 6, 7],
], $extra);
$readerCache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $recovered[1]),
    2 => $cacheEntry('root-refreshable', $before[2]),
    3 => $cacheEntry('active-stale-journal-digest', $recovered[3], ['journal_source_digest' => hash('sha256', 'old-journal')]),
    4 => $cacheEntry('settings-stale-page-count', $recovered[4], ['journal_page_count' => 5]),
    5 => $cacheEntry('comments-stale-initial-size', $before[5], ['journal_initial_page_count' => 7]),
    6 => $cacheEntry('transients-stale-page-set', $recovered[6], ['journal_page_numbers' => [1, 2, 3, 4, 6]]),
    7 => $cacheEntry('cron-pinned-stale-image', $before[7], ['pinned' => true]),
    8 => $cacheEntry('optionmeta-dirty', $before[8], ['dirty' => true]),
];
$nextWrites = [
    3 => $page('next170 rewritten active_plugins after rollback reader cache'),
    4 => $page('next170 rewritten plugin settings after rollback reader cache'),
];

$plan = static fn (
    ?array $cache = null,
    ?array $reads = null,
    ?array $writes = null,
    ?string $master = null,
    ?string $journal = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 12,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext170(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $journal ?? $currentJournal,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $cache ?? $readerCache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7, 8],
    $writes ?? $nextWrites,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next170'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'current_rollback_journal_source_rebases_reader_cache_after_master_journal_membership'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next170-meta.sqlite-journal']],
    'master digest' => [static fn (): mixed => $plan()['current_master_journal_digest'], $masterDigest],
    'journal digest' => [static fn (): mixed => $plan()['current_journal_source_digest'], $currentJournalDigest],
    'journal header page count' => [static fn (): mixed => $plan()['current_journal_header']['page_count'], 6],
    'journal header nonce' => [static fn (): mixed => $plan()['current_journal_header']['checksum_nonce'], 0x170],
    'journal header initial size' => [static fn (): mixed => $plan()['current_journal_header']['initial_database_page_count'], 8],
    'journal page numbers' => [static fn (): mixed => $plan()['current_journal_page_numbers'], [1, 2, 3, 4, 6, 7]],
    'input source id' => [static fn (): mixed => $plan()['input_source']['id'], $sourceId],
    'input epoch' => [static fn (): mixed => $plan()['input_source']['epoch'], 12],
    'recovered source prefix' => [static fn (): mixed => str_starts_with($plan()['recovered_source']['id'], 'master-reader-journal-source:'), true],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 13],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_cache_matches_current_rollback_journal_source'],
    'refreshed reason' => [static fn (): mixed => $plan()['reader_rows'][1]['reason'], 'reader_cache_refreshed_from_current_rollback_journal_source'],
    'journal digest mismatch reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reason'], 'reader_cache_rollback_journal_source_digest_mismatch'],
    'page count mismatch reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reason'], 'reader_cache_rollback_journal_page_count_mismatch'],
    'initial size mismatch reason' => [static fn (): mixed => $plan()['reader_rows'][4]['reason'], 'reader_cache_rollback_journal_initial_size_mismatch'],
    'page set mismatch reason' => [static fn (): mixed => $plan()['reader_rows'][5]['reason'], 'reader_cache_rollback_journal_page_set_mismatch'],
    'pinned reason' => [static fn (): mixed => $plan()['reader_rows'][6]['reason'], 'pinned_reader_cache_image_predates_current_rollback_source'],
    'dirty reason' => [static fn (): mixed => $plan()['reader_rows'][7]['reason'], 'dirty_reader_cache_from_aborted_current_journal_source'],
    'row digest match retained' => [static fn (): mixed => $plan()['reader_rows'][0]['journal_source_digest_matches_current'], true],
    'row digest mismatch flagged' => [static fn (): mixed => $plan()['reader_rows'][2]['journal_source_digest_matches_current'], false],
    'row page count before' => [static fn (): mixed => $plan()['reader_rows'][3]['journal_page_count_before'], 5],
    'row page count current' => [static fn (): mixed => $plan()['reader_rows'][3]['journal_page_count_current'], 6],
    'row initial before' => [static fn (): mixed => $plan()['reader_rows'][4]['journal_initial_page_count_before'], 7],
    'row page set before' => [static fn (): mixed => $plan()['reader_rows'][5]['journal_page_numbers_before'], [1, 2, 3, 4, 6]],
    'row page set current' => [static fn (): mixed => $plan()['reader_rows'][5]['journal_page_numbers_current'], [1, 2, 3, 4, 6, 7]],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read invalidated cache miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read unchanged cache miss prefix' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next170 unchanged comments before current rollback journal'],
    'read recovered prefix' => [static fn (): mixed => $plan()['next_reads'][6]['prefix'], 'next170 recovered cron from current rollback journal'],
    'read source digest' => [static fn (): mixed => $plan()['next_reads'][0]['journal_source_digest'], $currentJournalDigest],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next170 recovered active_plugins from current rollback journal'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next170 rewritten active_plugins after rollback reader cache'],
    'write settings before' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next170 recovered plugin settings from current rollback journal'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_current_rollback_source'], true],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_rollback_reader_cache'],
    'operation parse journal' => [static fn (): mixed => $plan()['operations'][1]['op'], 'parse_current_rollback_journal_for_reader_cache_source'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_reader_cache_after_current_rollback_journal_source_check'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_reader_cache_from_current_rollback_journal_source'],
    'operation invalidate digest' => [static fn (): mixed => $plan()['operations'][4]['op'], 'invalidate_reader_cache_after_rollback_journal_source_recheck'],
    'operation first read' => [static fn (): mixed => $plan()['operations'][10]['op'], 'next_read_uses_rebased_rollback_reader_cache'],
    'operation cache miss read' => [static fn (): mixed => $plan()['operations'][12]['op'], 'next_read_uses_current_rollback_journal_source'],
    'operation write capture' => [static fn (): mixed => $plan()['operations'][18]['op'], 'capture_next_write_before_image_after_rollback_reader_cache_rebase'],
    'final source page two journal' => [static fn (): mixed => $plan()['final_sources'][2], 'rollback-journal-current-source-before-reader-cache'],
    'final source page three write' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-rollback-journal-reader-cache'],
    'final prefix page four write' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next170 rewritten plugin settings after rollback reader cache'],
    'final bytes contain rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'final bytes exclude stale active' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale active_plugins'), false],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next170', $plan()['dependencies'], true), true],
    'dependency journal source fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-rollback-journal-source-fence-next170', $plan()['dependencies'], true), true],
    'all cache valid no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $recovered[1])], [1], [])['requires_reader_reopen'], false],
    'matching image stale source digest invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $recovered[1], ['journal_source_digest' => hash('sha256', 'old')])], [1], [])['invalidated_cache_page_numbers'], [1]],
    'matching image stale source digest misses cache' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $recovered[1], ['journal_source_digest' => hash('sha256', 'old')])], [1], [])['next_reads'][0]['cache_hit'], false],
    'source id mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $recovered[1], ['source_id' => 'old-source'])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_source_id_predates_current_rollback_source'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $recovered[1], ['epoch' => 11])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_epoch_predates_current_rollback_source'],
    'duplicate master members collapsed' => [static fn (): mixed => $plan(null, [1], [], $masterBytes . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next170-meta.sqlite-journal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next170 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'missing master rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'empty journal rejected' => static fn () => $plan(null, null, null, null, ''),
    'bad journal header rejected' => static fn () => $plan(null, null, null, null, 'bad'),
    'journal page size mismatch rejected' => static fn () => $plan(null, null, null, null, $journalBytes([1 => $recovered[1]], null, 0x170, 8, 512), null, 1024),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty next work rejected' => static fn () => $plan(null, [], []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'journal page outside rejected' => static fn () => $plan(null, null, null, null, $journalBytes([9 => $page('outside')])),
    'zero cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $recovered[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['source_id' => ''])]),
    'empty master digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['master_journal_digest' => ''])]),
    'empty journal digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_source_digest' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['epoch' => 0])]),
    'bad cache journal count rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_page_count' => -1])]),
    'bad initial page count rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_initial_page_count' => -1])]),
    'missing journal page numbers rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['journal_page_numbers' => true])]),
    'bad journal page number rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_page_numbers' => [0]])]),
    'cache outside rejected' => static fn () => $plan([9 => $cacheEntry('bad', $page('outside'))]),
    'bad read page rejected' => static fn () => $plan(null, [0], []),
    'read outside rejected' => static fn () => $plan(null, [9], []),
    'zero write page rejected' => static fn () => $plan(null, [], [0 => $nextWrites[3]]),
    'short write page rejected' => static fn () => $plan(null, [], [3 => 'short']),
    'write outside rejected' => static fn () => $plan(null, [], [9 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next170 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
