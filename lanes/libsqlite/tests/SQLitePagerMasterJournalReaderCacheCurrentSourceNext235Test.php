<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next235.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next235-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-change-counter-next235';
$publication = 235;
$masterDigest = hash('sha256', 'next235-master-source');
$recoverySequence = 235;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2350:size=96:mtime=23500:generation=master-current';
$databaseToken = 'dev=8:ino=2359:size=4096:mtime=23599:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23600:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=235:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=235:master-journal-recovery=complete';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=234:before-master-journal-recovery';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next235.sqlite';
$usersPathToken = 'db-path-token:users:/srv/wp-content/database/wp-next235-users.sqlite';
$oldPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next234.sqlite';
$currentChangeCounter = 235001;
$oldChangeCounter = 234999;
$nextChangeCounter = 235002;
$oldLeaseToken = 'reader-lease:shared-cache:epoch=234:opened-before-master-cleanup';
$oldCleanupToken = 'master-cleanup:exists:mtime=23590:dirsync=pending';
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
$formatPage = static function (string $label) use ($pageSize, $currentChangeCounter): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $currentChangeCounter), 24, 4);
    $page = substr_replace($page, pack('N', 235), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503235), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next235 stale schema before change counter recovery'),
    2 => $page('next235 stale wp_options root before change counter recovery'),
    3 => $page('next235 stale active_plugins before change counter recovery'),
    4 => $page('next235 stale usermeta attached database page'),
    5 => $page('next235 stale rewrite_rules cleanup page'),
    6 => $page('next235 stale comments dirty page'),
];
$recovered = [
    1 => $formatPage('next235 current schema after change counter recovery'),
    2 => $page('next235 current wp_options root after change counter recovery'),
    3 => $page('next235 current active_plugins after change counter recovery'),
    4 => $page('next235 current usermeta attached database page'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 235, 0x57503235]));
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
$tokens = [
    $mainJournal => 'dev=8:ino=2351:size=4096:mtime=23501:generation=main-current',
    $usersJournal => 'dev=8:ino=2352:size=1024:mtime=23502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-235'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-235'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 235,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $recoveredPageDigest,
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'database_path_token' => $mainPathToken,
    'database_change_counter' => $currentChangeCounter,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-current-counter', $recovered[1]),
    2 => $cacheEntry('root-refreshed-current-counter', $before[2]),
    3 => $cacheEntry('active-plugins-old-counter', $recovered[3], ['database_change_counter' => $oldChangeCounter]),
    4 => $cacheEntry('usermeta-users-path', $recovered[4], ['database_path_token' => $usersPathToken]),
    5 => $cacheEntry('rewrite-stale-cleanup', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('comments-dirty', $before[6], ['dirty' => true]),
];
$reads = static fn (?int $counter = null, ?string $pathToken = null, ?string $leaseToken = null, ?string $cacheSourceToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 235,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $recoveredPageDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
        'database_file_token' => $databaseToken,
        'master_journal_cleanup_token' => $cleanupToken,
        'reader_lease_token' => $leaseToken ?? $readerLeaseToken,
        'pager_cache_source_token' => $cacheSourceToken ?? $pagerCacheSourceToken,
        'database_path_token' => $pathToken ?? $mainPathToken,
        'database_change_counter' => $counter ?? $currentChangeCounter,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $currentCounter = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext235(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    235,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $mainPathToken,
    $currentCounter ?? $currentChangeCounter,
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
$opCount = static function (array $plan): int {
    return count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_database_change_counter_after_current_source_next235'));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next235'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_change_counter_before_current_source_reuse'],
    'current change counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentChangeCounter],
    'inherits path token' => [static fn (): mixed => $plan()['current_database_path_token'], $mainPathToken],
    'counter invalidated pages' => [static fn (): mixed => $plan()['database_change_counter_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read miss old counter' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count counter' => [static fn (): mixed => $opCount($plan()), 1],
    'dependency next235' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next235', $plan()['dependencies'], true), true],
    'dependency counter fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-change-counter-fence', $plan()['dependencies'], true), true],
    'dependency next232 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next232', $plan()['dependencies'], true), true],
    'non overlap mentions next232' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next232 database path'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-current-counter')['database_change_counter_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-current-counter')['database_change_counter_reason'], 'reader_cache_database_change_counter_matches_current_source'],
    'row retained cache counter' => [static fn (): mixed => $row('schema-retained-current-counter')['cache_database_change_counter'], $currentChangeCounter],
    'row retained current counter' => [static fn (): mixed => $row('schema-retained-current-counter')['current_database_change_counter'], $currentChangeCounter],
    'row retained counter matches' => [static fn (): mixed => $row('schema-retained-current-counter')['database_change_counter_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-current-counter')['database_change_counter_admitted'], true],
    'row old counter admitted false' => [static fn (): mixed => $row('active-plugins-old-counter')['database_change_counter_admitted'], false],
    'row old counter reason' => [static fn (): mixed => $row('active-plugins-old-counter')['database_change_counter_reason'], 'reader_cache_database_change_counter_predates_master_journal_current_source'],
    'row old counter value' => [static fn (): mixed => $row('active-plugins-old-counter')['cache_database_change_counter'], $oldChangeCounter],
    'row old counter mismatch' => [static fn (): mixed => $row('active-plugins-old-counter')['database_change_counter_matches'], false],
    'row users path not recounted' => [static fn (): mixed => $row('usermeta-users-path')['database_change_counter_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup')['database_change_counter_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty')['database_change_counter_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained counter current' => [static fn (): mixed => $read('read-1')['database_change_counter_current'], true],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read old counter cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read old counter source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-change-counter-fence-current-source-next235'],
    'read old counter reason' => [static fn (): mixed => $read('read-3')['database_change_counter_reason'], 'reader_cache_reopened_after_database_change_counter_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldChangeCounter))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldChangeCounter))['next_reads'][0]['database_change_counter_reason'], 'reader_ticket_database_change_counter_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldChangeCounter))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldChangeCounter))), 6],
    'stale path still inherited' => [static fn (): mixed => $plan([4 => $cacheEntry('stale-path', $recovered[4], ['database_path_token' => $usersPathToken])])['reader_rows'][0]['database_change_counter_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'stale cache source still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-cache-source', $recovered[3], ['pager_cache_source_token' => $oldPagerCacheSourceToken])])['reader_rows'][0]['database_change_counter_reason'], 'reader_cache_pager_cache_source_token_predates_master_journal_current_source'],
    'stale lease ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldLeaseToken))['next_reads'][0]['reader_lease_token_reason'], 'reader_ticket_reader_lease_predates_current_source'],
    'all fresh no counter invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_change_counter_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'current counter advanced invalidates old current cache' => [static fn (): mixed => $plan(null, null, $nextChangeCounter)['database_change_counter_invalidated_cache_page_numbers'], [1, 2, 3]],
    'advanced counter surfaced' => [static fn (): mixed => $plan(null, null, $nextChangeCounter)['current_database_change_counter'], $nextChangeCounter],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next235 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'zero current change counter rejected' => static fn () => $plan(null, null, 0),
    'cache missing change counter rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-counter', $recovered[1]), ['database_change_counter' => true])]),
    'cache zero change counter rejected' => static fn () => $plan([1 => $cacheEntry('zero-counter', $recovered[1], ['database_change_counter' => 0])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing change counter rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_change_counter' => true])]),
    'read zero change counter rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['database_change_counter' => 0])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next235 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
