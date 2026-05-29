<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next218.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next218-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-cleanup-token-next218';
$publication = 218;
$masterDigest = hash('sha256', 'next218-master-source');
$recoverySequence = 218;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2180:size=96:mtime=21800:generation=master-current';
$databaseToken = 'dev=8:ino=2189:size=3584:mtime=21899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=21900:dirsync=ok';
$oldCleanupToken = 'master-cleanup:exists:mtime=21890:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2189:size=3584:mtime=21898:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 218), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next218 stale schema before cleanup recovery'),
    2 => $page('next218 stale wp_options root before cleanup recovery'),
    3 => $page('next218 stale active_plugins before cleanup recovery'),
    4 => $page('next218 stale usermeta before cleanup recovery'),
    5 => $page('next218 stale rewrite_rules before cleanup recovery'),
    6 => $page('next218 stale cron before cleanup recovery'),
    7 => $page('next218 stale comments before cleanup recovery'),
];
$recovered = [
    1 => $formatPage('next218 current schema after cleanup recovery'),
    2 => $page('next218 current wp_options root after cleanup recovery'),
    3 => $page('next218 current active_plugins after cleanup recovery'),
    4 => $page('next218 current usermeta after cleanup recovery'),
    6 => $page('next218 current cron after cleanup recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 218, 0x57503238]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 217, 0x57503237]));
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
    $mainJournal => 'dev=8:ino=2181:size=4096:mtime=21801:generation=main-current',
    $usersJournal => 'dev=8:ino=2182:size=1024:mtime=21802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-218'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-218'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-218'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 218,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-cleanup-token', $recovered[1]),
    2 => $cacheEntry('root-refreshed-cleanup-token', $before[2]),
    3 => $cacheEntry('active-stale-cleanup-token', $recovered[3], ['master_journal_cleanup_token' => $oldCleanupToken]),
    4 => $cacheEntry('usermeta-stale-database-token', $recovered[4], ['database_file_token' => $oldDatabaseToken]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-header', $recovered[6], ['member_journal_header_digests' => $oldHeaders]),
    7 => $cacheEntry('comments-dirty-cleanup-token', $before[7], ['dirty' => true]),
];
$reads = static fn (string $readCleanupToken = null, string $readDatabaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 218,
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
        'database_file_token' => $readDatabaseToken ?? $databaseToken,
        'master_journal_cleanup_token' => $readCleanupToken ?? $cleanupToken,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentCleanupToken = null,
    ?string $currentDatabaseToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext218(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    218,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $currentDatabaseToken ?? $databaseToken,
    $currentCleanupToken ?? $cleanupToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next218'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_cleanup_token_before_current_source_reuse'],
    'cleanup token' => [static fn (): mixed => $plan()['current_master_journal_cleanup_token'], $cleanupToken],
    'inherits database token' => [static fn (): mixed => $plan()['current_database_file_token'], $databaseToken],
    'inherits master bytes digest' => [static fn (): mixed => $plan()['current_master_journal_bytes_digest'], $masterBytesDigest],
    'cleanup invalidated pages' => [static fn (): mixed => $plan()['master_journal_cleanup_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit cleanup stale' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count cleanup' => [static fn (): mixed => $opCount('invalidate_reader_cache_master_journal_cleanup_after_current_source_next218'), 1],
    'dependency next218' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next218', $plan()['dependencies'], true), true],
    'dependency cleanup fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-master-journal-cleanup-token-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next212' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next212 database file-token'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-cleanup-token')['master_journal_cleanup_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-cleanup-token')['master_journal_cleanup_token_reason'], 'reader_cache_master_journal_cleanup_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-cleanup-token')['cache_master_journal_cleanup_token'], $cleanupToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-cleanup-token')['current_master_journal_cleanup_token'], $cleanupToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-cleanup-token')['master_journal_cleanup_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-cleanup-token')['master_journal_cleanup_token_admitted'], true],
    'row cleanup stale admitted false' => [static fn (): mixed => $row('active-stale-cleanup-token')['master_journal_cleanup_token_admitted'], false],
    'row cleanup stale reason' => [static fn (): mixed => $row('active-stale-cleanup-token')['master_journal_cleanup_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row cleanup stale cache token' => [static fn (): mixed => $row('active-stale-cleanup-token')['cache_master_journal_cleanup_token'], $oldCleanupToken],
    'row cleanup stale current token' => [static fn (): mixed => $row('active-stale-cleanup-token')['current_master_journal_cleanup_token'], $cleanupToken],
    'row cleanup stale mismatch' => [static fn (): mixed => $row('active-stale-cleanup-token')['master_journal_cleanup_token_matches'], false],
    'row database stale inherits reason' => [static fn (): mixed => $row('usermeta-stale-database-token')['master_journal_cleanup_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row format stale inherits reason' => [static fn (): mixed => $row('rewrite-stale-format')['master_journal_cleanup_token_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row header stale inherits reason' => [static fn (): mixed => $row('cron-stale-header')['master_journal_cleanup_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty-cleanup-token')['master_journal_cleanup_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained cleanup current' => [static fn (): mixed => $read('read-1')['master_journal_cleanup_token_current'], true],
    'read retained cleanup token' => [static fn (): mixed => $read('read-1')['master_journal_cleanup_token'], $cleanupToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale cleanup cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale cleanup source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-cleanup-token-fence-current-source-next218'],
    'read stale cleanup reason' => [static fn (): mixed => $read('read-3')['master_journal_cleanup_token_reason'], 'reader_cache_reopened_after_master_journal_cleanup'],
    'read inherited database miss' => [static fn (): mixed => $read('read-4')['cache_hit'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldCleanupToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldCleanupToken))['next_reads'][0]['master_journal_cleanup_token_reason'], 'reader_ticket_master_journal_cleanup_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldCleanupToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'current ticket with stale cache only reopens inherited readers' => [static fn (): mixed => $plan(null, $reads())['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'all fresh no cleanup invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['master_journal_cleanup_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed cleanup token invalidates fresh pages' => [static fn (): mixed => $plan(null, null, 'master-cleanup:deleted:mtime=21901:dirsync=ok')['master_journal_cleanup_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed cleanup token keeps database invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'master-cleanup:deleted:mtime=21901:dirsync=ok')['invalidated_cache_page_numbers'], true), true],
    'changed cleanup token surfaced' => [static fn (): mixed => $plan(null, null, 'master-cleanup:deleted:mtime=21901:dirsync=ok')['current_master_journal_cleanup_token'], 'master-cleanup:deleted:mtime=21901:dirsync=ok'],
    'changed database token still inherited' => [static fn (): mixed => $plan(null, null, null, 'dev=8:ino=2189:size=3584:mtime=21901:generation=database-new')['database_file_token_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'stale ticket operation count' => [static fn (): mixed => count(array_filter($plan(null, $reads($oldCleanupToken))['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_master_journal_cleanup_after_current_source_next218')), 7],
    'stale ticket cleanup current false' => [static fn (): mixed => $plan(null, $reads($oldCleanupToken))['next_reads'][1]['master_journal_cleanup_token_current'], false],
    'stale ticket cleanup token assigned current' => [static fn (): mixed => $plan(null, $reads($oldCleanupToken))['next_reads'][1]['master_journal_cleanup_token'], $cleanupToken],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next218 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty cleanup token rejected' => static fn () => $plan(null, null, ''),
    'cache missing cleanup token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['master_journal_cleanup_token' => true])]),
    'cache empty cleanup token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['master_journal_cleanup_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing cleanup token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['master_journal_cleanup_token' => true])]),
    'read empty cleanup token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['master_journal_cleanup_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next218 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
