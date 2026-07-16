<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next229.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next229-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-source-token-next229';
$publication = 229;
$masterDigest = hash('sha256', 'next229-master-source');
$recoverySequence = 229;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2290:size=96:mtime=22900:generation=master-current';
$databaseToken = 'dev=8:ino=2299:size=4096:mtime=22999:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23000:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=229:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=229:master-journal-recovery=complete';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=228:before-master-journal-recovery';
$oldLeaseToken = 'reader-lease:shared-cache:epoch=228:opened-before-master-cleanup';
$oldCleanupToken = 'master-cleanup:exists:mtime=22990:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2299:size=4096:mtime=22998:generation=database-prior';
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
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 229), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next229 stale schema before cache source recovery'),
    2 => $page('next229 stale wp_options root before cache source recovery'),
    3 => $page('next229 stale active_plugins before cache source recovery'),
    4 => $page('next229 stale usermeta before cache source recovery'),
    5 => $page('next229 stale rewrite_rules before cache source recovery'),
    6 => $page('next229 stale cron before cache source recovery'),
    7 => $page('next229 stale comments before cache source recovery'),
    8 => $page('next229 stale terms before cache source recovery'),
];
$recovered = [
    1 => $formatPage('next229 current schema after cache source recovery'),
    2 => $page('next229 current wp_options root after cache source recovery'),
    3 => $page('next229 current active_plugins after cache source recovery'),
    4 => $page('next229 current usermeta after cache source recovery'),
    6 => $page('next229 current cron after cache source recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 229, 0x57503239]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 228, 0x57503238]));
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
    $mainJournal => 'dev=8:ino=2291:size=4096:mtime=22901:generation=main-current',
    $usersJournal => 'dev=8:ino=2292:size=1024:mtime=22902:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-229'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-229'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-229'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 229,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-cache-source', $recovered[1]),
    2 => $cacheEntry('root-refreshed-cache-source', $before[2]),
    3 => $cacheEntry('active-stale-cache-source', $recovered[3], ['pager_cache_source_token' => $oldPagerCacheSourceToken]),
    4 => $cacheEntry('usermeta-stale-reader-lease', $recovered[4], ['reader_lease_token' => $oldLeaseToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-cache-source', $before[8], ['dirty' => true]),
];
$reads = static fn (?string $readPagerCacheSourceToken = null, ?string $readLeaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 229,
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
        'reader_lease_token' => $readLeaseToken ?? $readerLeaseToken,
        'pager_cache_source_token' => $readPagerCacheSourceToken ?? $pagerCacheSourceToken,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentPagerCacheSourceToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantCurrentSourceEpochFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    229,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $currentPagerCacheSourceToken ?? $pagerCacheSourceToken,
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
$opCount = static function (array $plan, string $op): int {
    return count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next229'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_pager_cache_source_before_current_source_reuse'],
    'current pager cache source token' => [static fn (): mixed => $plan()['current_pager_cache_source_token'], $pagerCacheSourceToken],
    'inherits reader lease token' => [static fn (): mixed => $plan()['current_reader_lease_token'], $readerLeaseToken],
    'inherits cleanup token' => [static fn (): mixed => $plan()['current_master_journal_cleanup_token'], $cleanupToken],
    'cache source invalidated pages' => [static fn (): mixed => $plan()['pager_cache_source_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale cache source' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count cache source' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_pager_cache_source_after_current_source_next229'), 1],
    'dependency next229' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next229', $plan()['dependencies'], true), true],
    'dependency cache source fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-source-generation-fence', $plan()['dependencies'], true), true],
    'dependency next224 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next224', $plan()['dependencies'], true), true],
    'non overlap mentions next224' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next224 reader-lease'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-cache-source')['pager_cache_source_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-cache-source')['pager_cache_source_token_reason'], 'reader_cache_pager_cache_source_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-cache-source')['cache_pager_cache_source_token'], $pagerCacheSourceToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-cache-source')['current_pager_cache_source_token'], $pagerCacheSourceToken],
    'row retained cache source matches' => [static fn (): mixed => $row('schema-retained-cache-source')['pager_cache_source_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-cache-source')['pager_cache_source_token_admitted'], true],
    'row cache source stale admitted false' => [static fn (): mixed => $row('active-stale-cache-source')['pager_cache_source_token_admitted'], false],
    'row cache source stale reason' => [static fn (): mixed => $row('active-stale-cache-source')['pager_cache_source_token_reason'], 'reader_cache_pager_cache_source_token_predates_master_journal_current_source'],
    'row cache source stale cache token' => [static fn (): mixed => $row('active-stale-cache-source')['cache_pager_cache_source_token'], $oldPagerCacheSourceToken],
    'row cache source stale current token' => [static fn (): mixed => $row('active-stale-cache-source')['current_pager_cache_source_token'], $pagerCacheSourceToken],
    'row cache source stale mismatch' => [static fn (): mixed => $row('active-stale-cache-source')['pager_cache_source_token_matches'], false],
    'row lease stale inherits reason' => [static fn (): mixed => $row('usermeta-stale-reader-lease')['pager_cache_source_token_reason'], 'reader_cache_reader_lease_token_predates_master_journal_current_source'],
    'row cleanup stale inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['pager_cache_source_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database stale inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['pager_cache_source_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header stale inherits reason' => [static fn (): mixed => $row('comments-stale-header')['pager_cache_source_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-cache-source')['pager_cache_source_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained cache source current' => [static fn (): mixed => $read('read-1')['pager_cache_source_token_current'], true],
    'read retained cache source token' => [static fn (): mixed => $read('read-1')['pager_cache_source_token'], $pagerCacheSourceToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale cache source cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale cache source source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-pager-cache-source-fence-current-source-next229'],
    'read stale cache source reason' => [static fn (): mixed => $read('read-3')['pager_cache_source_token_reason'], 'reader_cache_reopened_after_pager_cache_source_token_change'],
    'read inherited lease miss' => [static fn (): mixed => $read('read-4')['cache_hit'], false],
    'read inherited cleanup miss' => [static fn (): mixed => $read('read-5')['cache_hit'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldPagerCacheSourceToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldPagerCacheSourceToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'current ticket with stale cache only reopens inherited readers' => [static fn (): mixed => $plan(null, $reads())['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldPagerCacheSourceToken)), 'invalidate_reader_cache_pager_cache_source_after_current_source_next229'), 8],
    'stale read ticket cache source current false' => [static fn (): mixed => $plan(null, $reads($oldPagerCacheSourceToken))['next_reads'][1]['pager_cache_source_token_current'], false],
    'stale read ticket assigned current' => [static fn (): mixed => $plan(null, $reads($oldPagerCacheSourceToken))['next_reads'][1]['pager_cache_source_token'], $pagerCacheSourceToken],
    'stale lease ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldLeaseToken))['next_reads'][0]['reader_lease_token_reason'], 'reader_ticket_reader_lease_predates_current_source'],
    'all fresh no cache source invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['pager_cache_source_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed cache source invalidates fresh pages' => [static fn (): mixed => $plan(null, null, 'pager-cache-source:epoch=230:master-journal-recovery=complete')['pager_cache_source_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed cache source keeps lease invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'pager-cache-source:epoch=230:master-journal-recovery=complete')['invalidated_cache_page_numbers'], true), true],
    'changed cache source surfaced' => [static fn (): mixed => $plan(null, null, 'pager-cache-source:epoch=230:master-journal-recovery=complete')['current_pager_cache_source_token'], 'pager-cache-source:epoch=230:master-journal-recovery=complete'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next229 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty pager cache source token rejected' => static fn () => $plan(null, null, ''),
    'cache missing pager cache source token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['pager_cache_source_token' => true])]),
    'cache empty pager cache source token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['pager_cache_source_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing pager cache source token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['pager_cache_source_token' => true])]),
    'read empty pager cache source token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['pager_cache_source_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next229 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
