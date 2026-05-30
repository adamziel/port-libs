<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next185.sqlite';
$master = '/srv/wp-content/database/wp-next185.sqlite-mj';
$journal = $database . '-journal';
$sourceId = 'pager-reader-cache-finite-truncate-next185';
$nonce = 0x185;
$masterBytes = $journal . "\n/srv/wp-content/database/wp-next185-users.sqlite-journal\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journalPages = [
    1 => $page('next185 recovered schema after finite rollback'),
    2 => $page('next185 recovered wp_options root after finite rollback'),
    4 => $page('next185 recovered active_plugins after finite rollback'),
    7 => $page('next185 ignored tail page beyond original size'),
];
$journalBytes = static function (int $declaredRecords = 4, int $initialPages = 5) use ($journalPages, $nonce, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', $declaredRecords, $nonce, $initialPages, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0", STR_PAD_RIGHT);
    foreach (array_slice($journalPages, 0, $declaredRecords, true) as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$masterDigest = hash('sha256', $journal . "\n/srv/wp-content/database/wp-next185-users.sqlite-journal");
$digest = static fn (string $bytes, array $pageNumbers = [1, 2, 4, 7], int $records = 4, int $initialPages = 5): string => hash('sha256', implode('|', [
    $journal,
    strlen($bytes),
    $records,
    $nonce,
    $initialPages,
    512,
    $pageSize,
    implode(',', $pageNumbers),
    hash('sha256', $bytes),
]));

$before = [
    1 => $page('next185 stale schema before finite rollback'),
    2 => $page('next185 stale wp_options root before finite rollback'),
    3 => $page('next185 unchanged comments before finite rollback'),
    4 => $page('next185 stale active_plugins before finite rollback'),
    5 => $page('next185 unchanged autoload index before finite rollback'),
    6 => $page('next185 tail transient page to truncate'),
    7 => $page('next185 tail overflow page to truncate'),
];
$databaseBytes = implode('', $before);
$currentJournalBytes = $journalBytes();
$journalDigest = $digest($currentJournalBytes);
$cacheEntry = static fn (string $reader, string $image, array $extra = []): array => array_merge([
    'reader_id' => $reader,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 185,
    'master_digest' => $masterDigest,
    'journal_digest' => $journalDigest,
    'initial_database_page_count' => 5,
    'journal_page_numbers' => [1, 2, 4, 7],
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $journalPages[1]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('comments-dirty', $before[3], ['dirty' => true]),
    4 => $cacheEntry('active-stale-journal', $journalPages[4], ['journal_digest' => hash('sha256', 'old-journal')]),
    5 => $cacheEntry('autoload-pinned-stale', $page('next185 old autoload pinned image'), ['pinned' => true]),
    6 => $cacheEntry('tail-truncated-cache', $before[6]),
    7 => $cacheEntry('overflow-truncated-cache', $before[7]),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $reads = null,
    ?array $writes = null,
    ?string $rollbackBytes = null,
    ?string $masterJournalBytes = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 185,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planFiniteOriginalSizeTruncationFence(
    $path ?? $database,
    $masterPath ?? $master,
    $masterJournalBytes ?? $masterBytes,
    $rollbackBytes ?? $currentJournalBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7],
    $writes ?? [
        4 => $page('next185 rewritten active_plugins after finite rollback'),
        6 => $page('next185 blocked tail write after finite rollback'),
    ],
    $source ?? $sourceId,
    $epoch,
);
$row = static function (int $pageNumber) use ($plan): array {
    foreach ($plan()['cache_rows'] as $row) {
        if ($row['page_number'] === $pageNumber) {
            return $row;
        }
    }
    throw new RuntimeException('missing cache row ' . $pageNumber);
};
$opExists = static function (string $op) use ($plan): bool {
    foreach ($plan()['operations'] as $operation) {
        if ($operation['op'] === $op) {
            return true;
        }
    }

    return false;
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next185'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'finite rollback-journal original database size truncates reader-cache current source before next reads'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journal],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['master_members'], [$journal, '/srv/wp-content/database/wp-next185-users.sqlite-journal']],
    'master digest' => [static fn (): mixed => $plan()['master_digest'], $masterDigest],
    'journal digest' => [static fn (): mixed => $plan()['journal_digest'], $journalDigest],
    'record count' => [static fn (): mixed => $plan()['journal_record_count'], 4],
    'initial page count' => [static fn (): mixed => $plan()['initial_database_page_count'], 5],
    'journal pages' => [static fn (): mixed => $plan()['journal_page_numbers'], [1, 2, 4, 7]],
    'ignored journal pages' => [static fn (): mixed => $plan()['ignored_journal_page_numbers'], [7]],
    'truncated pages' => [static fn (): mixed => $plan()['truncated_page_numbers'], [6, 7]],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 185],
    'next source id' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-journal-finite-truncate-source:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 186],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7]],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'dirty_reader_cache_cannot_cross_finite_master_journal_recovery'],
    'journal mismatch reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_journal_digest_mismatch_after_finite_master_read'],
    'pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'pinned_reader_cache_image_predates_finite_recovery'],
    'truncated reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'reader_cache_page_truncated_by_finite_master_journal_recovery'],
    'row retained reason' => [static fn (): mixed => $row(1)['reason'], 'reader_cache_matches_finite_recovered_current_source'],
    'row refreshed reason' => [static fn (): mixed => $row(2)['reason'], 'reader_cache_refreshed_from_finite_recovered_current_source'],
    'row truncated flag' => [static fn (): mixed => $row(6)['truncated'], true],
    'row untruncated flag' => [static fn (): mixed => $row(2)['truncated'], false],
    'row master digest matches' => [static fn (): mixed => $row(1)['master_digest_matches'], true],
    'row journal digest mismatch' => [static fn (): mixed => $row(4)['journal_digest_matches'], false],
    'row initial count before' => [static fn (): mixed => $row(1)['initial_database_page_count_before'], 5],
    'row initial count current' => [static fn (): mixed => $row(1)['initial_database_page_count_current'], 5],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'blocked reads' => [static fn (): mixed => $plan()['blocked_read_page_numbers'], [6, 7]],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read four reopened' => [static fn (): mixed => $plan()['next_reads'][3]['reason'], 'next_read_reopens_finite_recovered_current_source'],
    'read six blocked' => [static fn (): mixed => $plan()['next_reads'][5]['blocked'], true],
    'read six prefix null' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], null],
    'blocked writes' => [static fn (): mixed => $plan()['blocked_write_page_numbers'], [6]],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next185 recovered active_plugins after finite rollback'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next185 rewritten active_plugins after finite rollback'],
    'write tail blocked' => [static fn (): mixed => $plan()['next_writes'][1]['blocked'], true],
    'write tail reason' => [static fn (): mixed => $plan()['next_writes'][1]['reason'], 'next_write_page_truncated_by_finite_master_journal_recovery'],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_finite_truncate_reader_cache_next185'],
    'operation apply rollback' => [static fn (): mixed => $opExists('apply_finite_rollback_journal_before_reader_cache_next185'), true],
    'operation truncate' => [static fn (): mixed => $opExists('truncate_tail_page_before_reader_cache_next185'), true],
    'operation ignore beyond original' => [static fn (): mixed => $opExists('ignore_journal_page_beyond_finite_database_size_next185'), true],
    'operation invalidate' => [static fn (): mixed => $opExists('invalidate_reader_cache_finite_truncate_next185'), true],
    'operation refresh' => [static fn (): mixed => $opExists('refresh_reader_cache_finite_truncate_next185'), true],
    'operation retain' => [static fn (): mixed => $opExists('retain_reader_cache_finite_truncate_next185'), true],
    'operation block read' => [static fn (): mixed => $opExists('block_next_read_of_truncated_page_next185'), true],
    'operation block write' => [static fn (): mixed => $opExists('block_next_write_of_truncated_page_next185'), true],
    'final source page one' => [static fn (): mixed => $plan()['final_sources'][1], 'finite-master-journal-recovered-current-source-next185'],
    'final source write page' => [static fn (): mixed => $plan()['final_sources'][4], 'next-write-after-finite-master-journal-reader-cache-next185'],
    'final page count' => [static fn (): mixed => strlen($plan()['final_database_bytes']) / 512, 5],
    'final excludes tail' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'tail transient page'), false],
    'final includes active write' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'final prefix page two' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next185 recovered wp_options root after finite rollback'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next185', $plan()['dependencies'], true), true],
    'dependency finite truncation' => [static fn (): mixed => in_array('sqlite-rollback-journal-finite-original-page-count-truncation', $plan()['dependencies'], true), true],
    'dependency next182' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next182', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'avoids next182 unknown-page-count'), true],
    'source mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('source-old', $journalPages[1], ['source_id' => 'old-source'])], [1], [])['invalidated_reasons'][1], 'reader_cache_source_id_mismatch_after_finite_recovery'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('epoch-old', $journalPages[1], ['epoch' => 184])], [1], [])['invalidated_reasons'][1], 'reader_cache_epoch_mismatch_after_finite_recovery'],
    'initial count mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('count-old', $journalPages[1], ['initial_database_page_count' => 4])], [1], [])['invalidated_reasons'][1], 'reader_cache_initial_page_count_mismatch_after_finite_recovery'],
    'page set mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('set-old', $journalPages[1], ['journal_page_numbers' => [1, 2, 4]])], [1], [])['invalidated_reasons'][1], 'reader_cache_journal_page_set_mismatch_after_finite_recovery'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next185 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'blank master rejected' => static fn () => $plan(null, null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, null, '/tmp/other.sqlite-journal'),
    'empty rollback rejected' => static fn () => $plan(null, null, null, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty work rejected' => static fn () => $plan(null, [], []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 0),
    'unknown page count rejected' => static fn () => $plan(null, null, null, $journalBytes(SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT)),
    'initial page count too small rejected' => static fn () => $plan(null, null, null, $journalBytes(4, 0)),
    'initial page count too large rejected' => static fn () => $plan(null, null, null, $journalBytes(4, 99)),
    'wrong rollback page size rejected' => static fn () => $plan(null, null, null, str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', 0, $nonce, 5, 512, 1024), 512, "\0", STR_PAD_RIGHT)),
    'bad checksum rejected' => static fn () => $plan(null, null, null, substr_replace($currentJournalBytes, "\0\0\0\0", -4)),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $journalPages[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $journalPages[1], ['source_id' => ''])]),
    'empty cache digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $journalPages[1], ['journal_digest' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $journalPages[1], ['epoch' => 0])]),
    'bad cache initial count rejected' => static fn () => $plan([1 => $cacheEntry('bad', $journalPages[1], ['initial_database_page_count' => 0])]),
    'bad cache page set rejected' => static fn () => $plan([1 => $cacheEntry('bad', $journalPages[1], ['journal_page_numbers' => ['x']])]),
    'bad read page rejected' => static fn () => $plan(null, [0], []),
    'bad write page rejected' => static fn () => $plan(null, [], [0 => $page('bad')]),
    'short write page rejected' => static fn () => $plan(null, [], [1 => 'short']),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next185 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
