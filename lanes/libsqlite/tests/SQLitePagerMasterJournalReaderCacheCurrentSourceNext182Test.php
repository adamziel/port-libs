<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next182.sqlite';
$masterPath = '/srv/wp-content/database/wp-next182.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next182-users.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$masterDigest = hash('sha256', $mainJournal . "\n" . $usersJournal);
$sourceId = 'next182-current-source';
$nonce = 0x182;
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$before = [
    1 => $page('next182 stale schema before checksum journal'),
    2 => $page('next182 stale wp_options root before checksum journal'),
    3 => $page('next182 stale active_plugins before checksum journal'),
    4 => $page('next182 stale plugin settings before checksum journal'),
    5 => $page('next182 stale comments before checksum journal'),
    6 => $page('next182 stale transients before checksum journal'),
    7 => $page('next182 stale cron before checksum journal'),
    8 => $page('next182 stale optionmeta before checksum journal'),
];
$recovered = [
    1 => $page('next182 recovered schema from checksum journal'),
    2 => $page('next182 recovered wp_options root from checksum journal'),
    3 => $page('next182 recovered active_plugins from checksum journal'),
    4 => $page('next182 recovered plugin settings from checksum journal'),
    6 => $page('next182 recovered transients from checksum journal'),
    7 => $page('next182 recovered cron from checksum journal'),
];
$journalBytes = static function (array $pages, int $nonceValue = 0x182, int $declaredCount = SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT) use ($pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', $declaredCount, $nonceValue, 8, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonceValue));
    }

    return $bytes;
};
$journal = $journalBytes($recovered);
$journalDigest = static function (string $bytes, array $pageNumbers, int $nonceValue = 0x182, int $declaredCount = SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT) use ($mainJournal, $pageSize): string {
    return hash('sha256', implode('|', [
        $mainJournal,
        strlen($bytes),
        $declaredCount,
        $nonceValue,
        8,
        512,
        $pageSize,
        implode(',', $pageNumbers),
        hash('sha256', $bytes),
    ]));
};
$currentDigest = $journalDigest($journal, [1, 2, 3, 4, 6, 7]);
$cacheEntry = static fn (string $reader, string $image, array $extra = []): array => array_merge([
    'reader_id' => $reader,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 18,
    'master_digest' => $masterDigest,
    'journal_digest' => $currentDigest,
    'checksum_nonce' => $nonce,
    'journal_record_count' => 6,
    'journal_page_numbers' => [1, 2, 3, 4, 6, 7],
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $recovered[1]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('active-old-journal-digest', $recovered[3], ['journal_digest' => hash('sha256', 'old-journal')]),
    4 => $cacheEntry('settings-old-nonce', $recovered[4], ['checksum_nonce' => 0x181]),
    5 => $cacheEntry('comments-old-record-count', $before[5], ['journal_record_count' => 5]),
    6 => $cacheEntry('transients-old-page-set', $recovered[6], ['journal_page_numbers' => [1, 2, 3, 4, 6]]),
    7 => $cacheEntry('cron-pinned-stale-image', $before[7], ['pinned' => true]),
    8 => $cacheEntry('optionmeta-dirty', $before[8], ['dirty' => true]),
];
$writes = [
    3 => $page('next182 rewritten active_plugins after checksum source'),
    4 => $page('next182 rewritten plugin settings after checksum source'),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $reads = null,
    ?array $writePages = null,
    ?string $master = null,
    ?string $rollback = null,
    ?string $database = null,
    ?int $size = null,
    ?string $path = null,
    ?string $mjPath = null,
    ?string $source = null,
    int $epoch = 18,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext182(
    $path ?? $databasePath,
    $mjPath ?? $masterPath,
    $master ?? $masterBytes,
    $rollback ?? $journal,
    $database ?? implode('', $before),
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7, 8],
    $writePages ?? $writes,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next182'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'checksum-validated unknown-count rollback journal fences master-journal reader cache'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $mainJournal],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['master_members'], [$mainJournal, $usersJournal]],
    'master digest' => [static fn (): mixed => $plan()['master_digest'], $masterDigest],
    'unknown page count' => [static fn (): mixed => $plan()['unknown_page_count'], true],
    'record count' => [static fn (): mixed => $plan()['journal_record_count'], 6],
    'page numbers' => [static fn (): mixed => $plan()['journal_page_numbers'], [1, 2, 3, 4, 6, 7]],
    'journal digest' => [static fn (): mixed => $plan()['journal_digest'], $currentDigest],
    'checksum nonce' => [static fn (): mixed => $plan()['checksum_nonce'], 0x182],
    'current source' => [static fn (): mixed => $plan()['current_source'], ['id' => $sourceId, 'epoch' => 18]],
    'next source prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-journal-checksum-source:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 19],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_matches_checksum_validated_current_source'],
    'refreshed reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_checksum_validated_current_source'],
    'digest reason' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'reader_cache_journal_digest_mismatch_after_checksum_read'],
    'nonce reason' => [static fn (): mixed => $plan()['cache_rows'][3]['reason'], 'reader_cache_checksum_nonce_mismatch'],
    'record reason' => [static fn (): mixed => $plan()['cache_rows'][4]['reason'], 'reader_cache_journal_record_count_mismatch'],
    'page set reason' => [static fn (): mixed => $plan()['cache_rows'][5]['reason'], 'reader_cache_journal_page_set_mismatch_after_checksum_read'],
    'pinned reason' => [static fn (): mixed => $plan()['cache_rows'][6]['reason'], 'pinned_reader_cache_image_predates_checksum_recovery'],
    'dirty reason' => [static fn (): mixed => $plan()['cache_rows'][7]['reason'], 'dirty_reader_cache_cannot_cross_checksum_source'],
    'nonce before' => [static fn (): mixed => $plan()['cache_rows'][3]['checksum_nonce_before'], 0x181],
    'nonce current' => [static fn (): mixed => $plan()['cache_rows'][3]['checksum_nonce_current'], 0x182],
    'record before' => [static fn (): mixed => $plan()['cache_rows'][4]['journal_record_count_before'], 5],
    'record current' => [static fn (): mixed => $plan()['cache_rows'][4]['journal_record_count_current'], 6],
    'page set before' => [static fn (): mixed => $plan()['cache_rows'][5]['journal_page_numbers_before'], [1, 2, 3, 4, 6]],
    'page set current' => [static fn (): mixed => $plan()['cache_rows'][5]['journal_page_numbers_current'], [1, 2, 3, 4, 6, 7]],
    'master matches' => [static fn (): mixed => $plan()['cache_rows'][0]['master_digest_matches'], true],
    'journal matches false' => [static fn (): mixed => $plan()['cache_rows'][2]['journal_digest_matches'], false],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read one hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read three miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read two prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next182 recovered wp_options root from checksum journal'],
    'read seven source' => [static fn (): mixed => $plan()['next_reads'][6]['source'], 'checksum-validated-rollback-journal-current-source-next182'],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next182 recovered active_plugins from checksum journal'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next182 rewritten active_plugins after checksum source'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_checksum_validated_source'], true],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_checksum_reader_cache_next182'],
    'operation parse journal' => [static fn (): mixed => $plan()['operations'][1]['op'], 'parse_checksum_validated_rollback_journal_for_reader_cache_next182'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_reader_cache_checksum_source_next182'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_reader_cache_checksum_source_next182'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][4]['op'], 'invalidate_reader_cache_checksum_source_next182'],
    'operation read cache' => [static fn (): mixed => $plan()['operations'][10]['op'], 'next_read_uses_checksum_reader_cache_next182'],
    'operation read source' => [static fn (): mixed => $plan()['operations'][12]['op'], 'next_read_reopens_checksum_current_source_next182'],
    'operation write' => [static fn (): mixed => $plan()['operations'][18]['op'], 'capture_next_write_after_checksum_reader_cache_next182'],
    'final source three' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-checksum-reader-cache-next182'],
    'final prefix four' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next182 rewritten plugin settings after checksum source'],
    'final contains rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next182', $plan()['dependencies'], true), true],
    'dependency unknown count' => [static fn (): mixed => in_array('sqlite-rollback-journal-unknown-page-count-eof-scan', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'checksum nonce'), true],
    'all valid no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $recovered[1])], [1], [])['requires_reader_reopen'], false],
    'master digest mismatch reason' => [static fn (): mixed => $plan([1 => $cacheEntry('schema', $recovered[1], ['master_digest' => hash('sha256', 'old')])], [1], [])['cache_rows'][0]['reason'], 'reader_cache_master_digest_mismatch_after_checksum_read'],
    'source mismatch reason' => [static fn (): mixed => $plan([1 => $cacheEntry('schema', $recovered[1], ['source_id' => 'old'])], [1], [])['cache_rows'][0]['reason'], 'reader_cache_source_id_mismatch_after_checksum_read'],
    'epoch mismatch reason' => [static fn (): mixed => $plan([1 => $cacheEntry('schema', $recovered[1], ['epoch' => 17])], [1], [])['cache_rows'][0]['reason'], 'reader_cache_epoch_mismatch_after_checksum_read'],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, [1], [], $masterBytes . $mainJournal . "\n")['master_members'], [$mainJournal, $usersJournal]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next182 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$badChecksumJournal = substr_replace($journal, pack('N', 1), -4, 4);
$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, ''),
    'empty master rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'empty journal rejected' => static fn () => $plan(null, null, null, null, ''),
    'bad checksum rejected' => static fn () => $plan(null, null, null, null, $badChecksumJournal),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, null, 500),
    'journal page size mismatch rejected' => static fn () => $plan(null, null, null, null, $journalBytes([1 => $recovered[1]], $nonce, SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT), null, 1024),
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, implode('', $before) . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty next work rejected' => static fn () => $plan(null, [], []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'journal page outside rejected' => static fn () => $plan(null, null, null, null, $journalBytes([9 => $page('outside')], $nonce)),
    'zero cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $recovered[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty reader id rejected' => static fn () => $plan([1 => $cacheEntry('', $recovered[1])]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['source_id' => ''])]),
    'empty master digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['master_digest' => ''])]),
    'empty journal digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_digest' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['epoch' => 0])]),
    'bad nonce rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['checksum_nonce' => -1])]),
    'bad record count rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_record_count' => -1])]),
    'missing page numbers rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['journal_page_numbers' => true])]),
    'bad page number rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['journal_page_numbers' => [0]])]),
    'cache outside rejected' => static fn () => $plan([9 => $cacheEntry('bad', $page('outside'))]),
    'bad read rejected' => static fn () => $plan(null, [0], []),
    'read outside rejected' => static fn () => $plan(null, [9], []),
    'bad write rejected' => static fn () => $plan(null, [], [0 => $writes[3]]),
    'short write rejected' => static fn () => $plan(null, [], [3 => 'short']),
    'write outside rejected' => static fn () => $plan(null, [], [9 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next182 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
