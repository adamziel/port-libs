<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next232.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next232-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-database-path-next232';
$publication = 232;
$masterDigest = hash('sha256', 'next232-master-source');
$recoverySequence = 232;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2320:size=96:mtime=23200:generation=master-current';
$databaseToken = 'dev=8:ino=2329:size=4096:mtime=23299:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23300:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=232:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=232:master-journal-recovery=complete';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=231:before-master-journal-recovery';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next232.sqlite';
$usersPathToken = 'db-path-token:users:/srv/wp-content/database/wp-next232-users.sqlite';
$oldPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next231.sqlite';
$oldLeaseToken = 'reader-lease:shared-cache:epoch=231:opened-before-master-cleanup';
$oldCleanupToken = 'master-cleanup:exists:mtime=23290:dirsync=pending';
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
    $page = substr_replace($page, pack('N', 232), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503232), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next232 stale schema before database path recovery'),
    2 => $page('next232 stale wp_options root before database path recovery'),
    3 => $page('next232 stale active_plugins before database path recovery'),
    4 => $page('next232 stale usermeta attached database page'),
    5 => $page('next232 stale rewrite_rules cleanup page'),
    6 => $page('next232 stale comments dirty page'),
];
$recovered = [
    1 => $formatPage('next232 current schema after database path recovery'),
    2 => $page('next232 current wp_options root after database path recovery'),
    3 => $page('next232 current active_plugins after database path recovery'),
    4 => $page('next232 current usermeta attached database page'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 232, 0x57503232]));
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
    $mainJournal => 'dev=8:ino=2321:size=4096:mtime=23201:generation=main-current',
    $usersJournal => 'dev=8:ino=2322:size=1024:mtime=23202:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-232'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-232'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 232,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-main-path', $recovered[1]),
    2 => $cacheEntry('root-refreshed-main-path', $before[2]),
    3 => $cacheEntry('active-plugins-users-path', $recovered[3], ['database_path_token' => $usersPathToken]),
    4 => $cacheEntry('usermeta-old-main-path', $recovered[4], ['database_path_token' => $oldPathToken]),
    5 => $cacheEntry('rewrite-stale-cleanup', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('comments-dirty', $before[6], ['dirty' => true]),
];
$reads = static fn (?string $pathToken = null, ?string $leaseToken = null, ?string $cacheSourceToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 232,
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
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentPathToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext232(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    232,
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
    $currentPathToken ?? $mainPathToken,
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
    return count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_database_path_after_current_source_next232'));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next232'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_path_namespace_before_current_source_reuse'],
    'current path token' => [static fn (): mixed => $plan()['current_database_path_token'], $mainPathToken],
    'inherits pager cache source' => [static fn (): mixed => $plan()['current_pager_cache_source_token'], $pagerCacheSourceToken],
    'path invalidated pages' => [static fn (): mixed => $plan()['database_path_invalidated_cache_page_numbers'], [3, 4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read miss users path' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count path' => [static fn (): mixed => $opCount($plan()), 2],
    'dependency next232' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next232', $plan()['dependencies'], true), true],
    'dependency path fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-path-namespace-fence', $plan()['dependencies'], true), true],
    'dependency next229 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next229', $plan()['dependencies'], true), true],
    'non overlap mentions next229' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next229 pager-cache-source'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-main-path')['database_path_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-main-path')['database_path_token_reason'], 'reader_cache_database_path_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-main-path')['cache_database_path_token'], $mainPathToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-main-path')['current_database_path_token'], $mainPathToken],
    'row retained path matches' => [static fn (): mixed => $row('schema-retained-main-path')['database_path_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-main-path')['database_path_token_admitted'], true],
    'row users path admitted false' => [static fn (): mixed => $row('active-plugins-users-path')['database_path_token_admitted'], false],
    'row users path reason' => [static fn (): mixed => $row('active-plugins-users-path')['database_path_token_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'row users path cache token' => [static fn (): mixed => $row('active-plugins-users-path')['cache_database_path_token'], $usersPathToken],
    'row users path mismatch' => [static fn (): mixed => $row('active-plugins-users-path')['database_path_token_matches'], false],
    'row old main path false' => [static fn (): mixed => $row('usermeta-old-main-path')['database_path_token_admitted'], false],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup')['database_path_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty')['database_path_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained path current' => [static fn (): mixed => $read('read-1')['database_path_token_current'], true],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read users path cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read users path source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-database-path-fence-current-source-next232'],
    'read users path reason' => [static fn (): mixed => $read('read-3')['database_path_token_reason'], 'reader_cache_reopened_after_database_path_token_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($usersPathToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($usersPathToken))['next_reads'][0]['database_path_token_reason'], 'reader_ticket_database_path_token_crosses_database_slot'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($usersPathToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($usersPathToken))), 6],
    'stale cache source still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-cache-source', $recovered[3], ['pager_cache_source_token' => $oldPagerCacheSourceToken])])['reader_rows'][0]['database_path_token_reason'], 'reader_cache_pager_cache_source_token_predates_master_journal_current_source'],
    'stale lease ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldLeaseToken))['next_reads'][0]['reader_lease_token_reason'], 'reader_ticket_reader_lease_predates_current_source'],
    'all fresh no path invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_path_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'current token switched to users invalidates main' => [static fn (): mixed => $plan(null, null, $usersPathToken)['database_path_invalidated_cache_page_numbers'], [1, 2, 4]],
    'switched token surfaced' => [static fn (): mixed => $plan(null, null, $usersPathToken)['current_database_path_token'], $usersPathToken],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next232 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path token rejected' => static fn () => $plan(null, null, ''),
    'cache missing database path token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['database_path_token' => true])]),
    'cache empty database path token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['database_path_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing database path token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_path_token' => true])]),
    'read empty database path token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['database_path_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next232 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
