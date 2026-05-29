<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next231.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next231-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-freelist-next231';
$publication = 231;
$masterDigest = hash('sha256', 'next231-master-source');
$recoverySequence = 231;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2310:size=96:mtime=23100:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2319:size=3072:mtime=23199:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=231;change-counter=63;version-valid-for=63;page-count=6;freelist=4/2');
$oldHeaderDigest = hash('sha256', 'schema-cookie=230;change-counter=62;version-valid-for=62;page-count=6;freelist=0/0');
$currentPageCount = 6;
$currentCounter = 63;
$oldCounter = 62;
$currentFreelistTrunk = 4;
$currentFreelistCount = 2;
$oldFreelistTrunk = 0;
$oldFreelistCount = 0;
$futureFreelistTrunk = 5;
$futureFreelistCount = 3;
$members = [$mainJournal, $usersJournal];
$orderDigest = hash('sha256', implode("\n", $members));
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize, $currentFreelistTrunk, $currentFreelistCount): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 231), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503231), 68, 4);
    $page = substr_replace($page, pack('N', 63), 24, 4);
    $page = substr_replace($page, pack('N', $currentFreelistTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $currentFreelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 63), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next231 stale schema before freelist recovery'),
    2 => $page('next231 stale wp_options root before freelist recovery'),
    3 => $page('next231 stale active_plugins before freelist recovery'),
    4 => $page('next231 stale freelist trunk before recovery'),
    5 => $page('next231 stale cron before freelist recovery'),
    6 => $page('next231 stale rewrite_rules before freelist recovery'),
];
$recovered = [
    1 => $formatPage('next231 current schema after freelist recovery'),
    2 => $page('next231 current wp_options root after freelist recovery'),
    3 => $page('next231 current active_plugins after freelist recovery'),
    4 => $page('next231 current freelist trunk after recovery'),
    5 => $page('next231 current cron after freelist recovery'),
    6 => $page('next231 current rewrite_rules after freelist recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 231, 0x57503231]));
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad fixture');
        }
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$currentTokens = [
    $mainJournal => 'dev=8:ino=2311:size=4096:mtime=23101:generation=main-current',
    $usersJournal => 'dev=8:ino=2312:size=1024:mtime=23102:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-231'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-231'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, int $trunk, int $count, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 231,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $currentMasterToken,
    'master_journal_bytes_digest' => $currentMasterBytesDigest,
    'database_file_token' => $currentDatabaseToken,
    'database_header_digest' => $currentHeaderDigest,
    'database_page_count' => $currentPageCount,
    'database_change_counter' => $currentCounter,
    'version_valid_for' => $currentCounter,
    'freelist_trunk_page' => $trunk,
    'freelist_page_count' => $count,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-freelist', $recovered[1], $currentFreelistTrunk, $currentFreelistCount),
    2 => $cacheEntry('root-refreshed-freelist', $before[2], $currentFreelistTrunk, $currentFreelistCount),
    3 => $cacheEntry('active-stale-freelist', $recovered[3], $oldFreelistTrunk, $oldFreelistCount),
    4 => $cacheEntry('trunk-incoherent-freelist', $recovered[4], 0, 2),
    5 => $cacheEntry('trunk-past-end-freelist', $recovered[5], 7, 1),
    6 => $cacheEntry('rewrite-stale-counter', $recovered[6], $currentFreelistTrunk, $currentFreelistCount, ['database_change_counter' => $oldCounter, 'version_valid_for' => $oldCounter]),
];
$reads = static fn (int $trunk = null, int $count = null, string $headerDigest = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 231,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentMemberHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
        'database_file_token' => $currentDatabaseToken,
        'database_header_digest' => $headerDigest ?? $currentHeaderDigest,
        'database_page_count' => $currentPageCount,
        'database_change_counter' => $currentCounter,
        'version_valid_for' => $currentCounter,
        'freelist_trunk_page' => $trunk ?? $currentFreelistTrunk,
        'freelist_page_count' => $count ?? $currentFreelistCount,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $trunk = null,
    ?int $count = null,
    ?string $databaseHeader = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext231(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    231,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $currentDatabaseToken,
    $databaseHeader ?? $currentHeaderDigest,
    $currentPageCount,
    $currentCounter,
    $currentCounter,
    $trunk ?? $currentFreelistTrunk,
    $count ?? $currentFreelistCount,
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};
$read = static function (string $readerId) use ($plan): array {
    foreach ($plan()['next_reads'] as $read) {
        if ($read['reader_id'] === $readerId) {
            return $read;
        }
    }
    throw new RuntimeException('missing read ' . $readerId);
};
$opCount = static function (string $op) use ($plan): int {
    return count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next231'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_freelist_header_before_current_source_reuse'],
    'current freelist trunk' => [static fn (): mixed => $plan()['current_freelist_trunk_page'], $currentFreelistTrunk],
    'current freelist count' => [static fn (): mixed => $plan()['current_freelist_page_count'], $currentFreelistCount],
    'inherits current page count' => [static fn (): mixed => $plan()['current_database_page_count'], $currentPageCount],
    'inherits current counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentCounter],
    'freelist invalidated pages' => [static fn (): mixed => $plan()['freelist_header_invalidated_cache_page_numbers'], [3, 4, 5]],
    'freelist incoherent pages' => [static fn (): mixed => $plan()['freelist_header_incoherent_cache_page_numbers'], [4, 5]],
    'freelist trunk past end pages' => [static fn (): mixed => $plan()['freelist_trunk_past_end_cache_page_numbers'], [5]],
    'all invalidated pages include counter fence' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale freelist' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit incoherent freelist' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'freelist invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_freelist_header_after_current_source_next231'), 3],
    'dependency next231' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next231', $plan()['dependencies'], true), true],
    'dependency freelist fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-freelist-header-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next226' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next226 header-counter admission'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-freelist')['freelist_header_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-freelist')['freelist_header_reason'], 'reader_cache_freelist_header_matches_current_source'],
    'row retained trunk' => [static fn (): mixed => $row('schema-retained-freelist')['cache_freelist_trunk_page'], $currentFreelistTrunk],
    'row retained count' => [static fn (): mixed => $row('schema-retained-freelist')['cache_freelist_page_count'], $currentFreelistCount],
    'row retained current trunk' => [static fn (): mixed => $row('schema-retained-freelist')['current_freelist_trunk_page'], $currentFreelistTrunk],
    'row retained current count' => [static fn (): mixed => $row('schema-retained-freelist')['current_freelist_page_count'], $currentFreelistCount],
    'row retained coherent' => [static fn (): mixed => $row('schema-retained-freelist')['freelist_header_coherent'], true],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-freelist')['freelist_header_matches'], true],
    'row retained trunk in range' => [static fn (): mixed => $row('schema-retained-freelist')['freelist_trunk_within_current_page_count'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-freelist')['freelist_header_admitted'], true],
    'row refreshed reason' => [static fn (): mixed => $row('root-refreshed-freelist')['freelist_header_reason'], 'reader_cache_freelist_header_matches_current_source'],
    'row stale freelist admitted false' => [static fn (): mixed => $row('active-stale-freelist')['freelist_header_admitted'], false],
    'row stale freelist reason' => [static fn (): mixed => $row('active-stale-freelist')['freelist_header_reason'], 'reader_cache_freelist_header_changed_after_master_journal_recovery'],
    'row stale freelist coherent' => [static fn (): mixed => $row('active-stale-freelist')['freelist_header_coherent'], true],
    'row stale freelist mismatch' => [static fn (): mixed => $row('active-stale-freelist')['freelist_header_matches'], false],
    'row incoherent admitted false' => [static fn (): mixed => $row('trunk-incoherent-freelist')['freelist_header_admitted'], false],
    'row incoherent reason' => [static fn (): mixed => $row('trunk-incoherent-freelist')['freelist_header_reason'], 'reader_cache_freelist_header_incoherent_after_master_journal_recovery'],
    'row incoherent coherent false' => [static fn (): mixed => $row('trunk-incoherent-freelist')['freelist_header_coherent'], false],
    'row past end reason' => [static fn (): mixed => $row('trunk-past-end-freelist')['freelist_header_reason'], 'reader_cache_freelist_trunk_exceeds_current_database_page_count_after_master_journal_recovery'],
    'row past end range false' => [static fn (): mixed => $row('trunk-past-end-freelist')['freelist_trunk_within_current_page_count'], false],
    'row stale counter inherits reason' => [static fn (): mixed => $row('rewrite-stale-counter')['freelist_header_reason'], 'reader_cache_header_counter_pair_changed_after_master_journal_recovery'],
    'read retained freelist current' => [static fn (): mixed => $read('read-1')['freelist_header_current'], true],
    'read retained freelist coherent' => [static fn (): mixed => $read('read-1')['freelist_header_coherent'], true],
    'read retained trunk value' => [static fn (): mixed => $read('read-1')['freelist_trunk_page'], $currentFreelistTrunk],
    'read retained count value' => [static fn (): mixed => $read('read-1')['freelist_page_count'], $currentFreelistCount],
    'read stale freelist source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-freelist-header-fence-current-source-next231'],
    'read stale freelist reason' => [static fn (): mixed => $read('read-3')['freelist_header_reason'], 'reader_cache_reopened_after_freelist_header_change'],
    'read incoherent freelist reason' => [static fn (): mixed => $read('read-4')['freelist_header_reason'], 'reader_cache_reopened_after_freelist_header_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldFreelistTrunk, $oldFreelistCount))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldFreelistTrunk, $oldFreelistCount))['next_reads'][0]['freelist_header_reason'], 'reader_ticket_freelist_header_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldFreelistTrunk, $oldFreelistCount))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'incoherent read ticket reason' => [static fn (): mixed => $plan(null, $reads(0, 2))['next_reads'][0]['freelist_header_reason'], 'reader_ticket_freelist_header_incoherent'],
    'past-end read ticket reason' => [static fn (): mixed => $plan(null, $reads(9, 1))['next_reads'][0]['freelist_header_reason'], 'reader_ticket_freelist_trunk_exceeds_current_database_page_count'],
    'future current freelist invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $futureFreelistTrunk, $futureFreelistCount)['freelist_header_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'future current freelist surfaces trunk' => [static fn (): mixed => $plan(null, null, $futureFreelistTrunk, $futureFreelistCount)['current_freelist_trunk_page'], $futureFreelistTrunk],
    'future current freelist surfaces count' => [static fn (): mixed => $plan(null, null, $futureFreelistTrunk, $futureFreelistCount)['current_freelist_page_count'], $futureFreelistCount],
    'changed header still inherited' => [static fn (): mixed => $plan(null, null, null, null, $oldHeaderDigest)['database_header_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'all fresh cache no freelist invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentFreelistTrunk, $currentFreelistCount)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['freelist_header_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentFreelistTrunk, $currentFreelistCount)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen($databaseBytes), $pageSize * 6],
    'master bytes digest current' => [static fn (): mixed => $currentMasterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $currentTokenDigest, $mapDigest($currentTokens)],
    'member header digest current' => [static fn (): mixed => $currentMemberHeaderDigest, $mapDigest($currentHeaders)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next231 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'negative current trunk rejected' => static fn () => $plan(null, null, -1, 0),
    'negative current count rejected' => static fn () => $plan(null, null, 0, -1),
    'count without trunk rejected' => static fn () => $plan(null, null, 0, 1),
    'current trunk past end rejected' => static fn () => $plan(null, null, 7, 1),
    'negative cache trunk rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], -1, 0)]),
    'negative cache count rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], 0, -1)]),
    'missing cache trunk rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1], 0, 0), ['freelist_trunk_page' => true])]),
    'missing cache count rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1], 0, 0), ['freelist_page_count' => true])]),
    'negative read trunk rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_number' => 1] + $reads(-1, 0)[0]]),
    'negative read count rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_number' => 1] + $reads(0, -1)[0]]),
    'missing read trunk rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['freelist_trunk_page' => true])]),
    'missing read count rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['freelist_page_count' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next231 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
