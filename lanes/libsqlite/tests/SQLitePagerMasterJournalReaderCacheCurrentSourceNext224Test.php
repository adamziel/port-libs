<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next224.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next224-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-lease-token-next224';
$publication = 224;
$masterDigest = hash('sha256', 'next224-master-source');
$recoverySequence = 224;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2240:size=96:mtime=22400:generation=master-current';
$databaseToken = 'dev=8:ino=2249:size=4096:mtime=22499:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=22500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=224:opened-after-master-cleanup';
$oldLeaseToken = 'reader-lease:shared-cache:epoch=223:opened-before-master-cleanup';
$oldCleanupToken = 'master-cleanup:exists:mtime=22490:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2249:size=4096:mtime=22498:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 224), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503234), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next224 stale schema before reader lease recovery'),
    2 => $page('next224 stale wp_options root before reader lease recovery'),
    3 => $page('next224 stale active_plugins before reader lease recovery'),
    4 => $page('next224 stale usermeta before reader lease recovery'),
    5 => $page('next224 stale rewrite_rules before reader lease recovery'),
    6 => $page('next224 stale cron before reader lease recovery'),
    7 => $page('next224 stale comments before reader lease recovery'),
    8 => $page('next224 stale terms before reader lease recovery'),
];
$recovered = [
    1 => $formatPage('next224 current schema after reader lease recovery'),
    2 => $page('next224 current wp_options root after reader lease recovery'),
    3 => $page('next224 current active_plugins after reader lease recovery'),
    4 => $page('next224 current usermeta after reader lease recovery'),
    6 => $page('next224 current cron after reader lease recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 224, 0x57503234]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 223, 0x57503233]));
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
    $mainJournal => 'dev=8:ino=2241:size=4096:mtime=22401:generation=main-current',
    $usersJournal => 'dev=8:ino=2242:size=1024:mtime=22402:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-224'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-224'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-224'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 224,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-lease-token', $recovered[1]),
    2 => $cacheEntry('root-refreshed-lease-token', $before[2]),
    3 => $cacheEntry('active-stale-reader-lease', $recovered[3], ['reader_lease_token' => $oldLeaseToken]),
    4 => $cacheEntry('usermeta-stale-cleanup-token', $recovered[4], ['master_journal_cleanup_token' => $oldCleanupToken]),
    5 => $cacheEntry('rewrite-stale-database-token', $before[5], ['database_file_token' => $oldDatabaseToken]),
    6 => $cacheEntry('cron-stale-format', $recovered[6], ['format_signature' => $oldFormatSignature]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-lease-token', $before[8], ['dirty' => true]),
];
$reads = static fn (?string $readLeaseToken = null, ?string $readCleanupToken = null, ?string $readDatabaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 224,
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
        'reader_lease_token' => $readLeaseToken ?? $readerLeaseToken,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentLeaseToken = null,
    ?string $currentCleanupToken = null,
    ?string $currentDatabaseToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderCacheInvalidationFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    224,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $currentDatabaseToken ?? $databaseToken,
    $currentCleanupToken ?? $cleanupToken,
    $currentLeaseToken ?? $readerLeaseToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next224'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_reader_lease_before_current_source_reuse'],
    'current reader lease token' => [static fn (): mixed => $plan()['current_reader_lease_token'], $readerLeaseToken],
    'inherits cleanup token' => [static fn (): mixed => $plan()['current_master_journal_cleanup_token'], $cleanupToken],
    'inherits database token' => [static fn (): mixed => $plan()['current_database_file_token'], $databaseToken],
    'lease invalidated pages' => [static fn (): mixed => $plan()['reader_lease_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale lease' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count lease' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_reader_lease_after_current_source_next224'), 1],
    'dependency next224' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next224', $plan()['dependencies'], true), true],
    'dependency lease fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-reader-lease-fence', $plan()['dependencies'], true), true],
    'dependency next218 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next218', $plan()['dependencies'], true), true],
    'non overlap mentions next218' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next218 master-journal cleanup'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-lease-token')['reader_lease_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-lease-token')['reader_lease_token_reason'], 'reader_cache_reader_lease_token_matches_current_source'],
    'row retained cache lease' => [static fn (): mixed => $row('schema-retained-lease-token')['cache_reader_lease_token'], $readerLeaseToken],
    'row retained current lease' => [static fn (): mixed => $row('schema-retained-lease-token')['current_reader_lease_token'], $readerLeaseToken],
    'row retained lease matches' => [static fn (): mixed => $row('schema-retained-lease-token')['reader_lease_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-lease-token')['reader_lease_token_admitted'], true],
    'row lease stale admitted false' => [static fn (): mixed => $row('active-stale-reader-lease')['reader_lease_token_admitted'], false],
    'row lease stale reason' => [static fn (): mixed => $row('active-stale-reader-lease')['reader_lease_token_reason'], 'reader_cache_reader_lease_token_predates_master_journal_current_source'],
    'row lease stale cache token' => [static fn (): mixed => $row('active-stale-reader-lease')['cache_reader_lease_token'], $oldLeaseToken],
    'row lease stale current token' => [static fn (): mixed => $row('active-stale-reader-lease')['current_reader_lease_token'], $readerLeaseToken],
    'row lease stale mismatch' => [static fn (): mixed => $row('active-stale-reader-lease')['reader_lease_token_matches'], false],
    'row cleanup stale inherits reason' => [static fn (): mixed => $row('usermeta-stale-cleanup-token')['reader_lease_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database stale inherits reason' => [static fn (): mixed => $row('rewrite-stale-database-token')['reader_lease_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row format stale inherits reason' => [static fn (): mixed => $row('cron-stale-format')['reader_lease_token_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row header stale inherits reason' => [static fn (): mixed => $row('comments-stale-header')['reader_lease_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-lease-token')['reader_lease_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained lease current' => [static fn (): mixed => $read('read-1')['reader_lease_token_current'], true],
    'read retained lease token' => [static fn (): mixed => $read('read-1')['reader_lease_token'], $readerLeaseToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale lease cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale lease source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-reader-lease-fence-current-source-next224'],
    'read stale lease reason' => [static fn (): mixed => $read('read-3')['reader_lease_token_reason'], 'reader_cache_reopened_after_reader_lease_token_change'],
    'read inherited cleanup miss' => [static fn (): mixed => $read('read-4')['cache_hit'], false],
    'read inherited database miss' => [static fn (): mixed => $read('read-5')['cache_hit'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldLeaseToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldLeaseToken))['next_reads'][0]['reader_lease_token_reason'], 'reader_ticket_reader_lease_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldLeaseToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'current ticket with stale cache only reopens inherited readers' => [static fn (): mixed => $plan(null, $reads())['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldLeaseToken)), 'invalidate_reader_cache_reader_lease_after_current_source_next224'), 8],
    'stale read ticket lease current false' => [static fn (): mixed => $plan(null, $reads($oldLeaseToken))['next_reads'][1]['reader_lease_token_current'], false],
    'stale read ticket lease assigned current' => [static fn (): mixed => $plan(null, $reads($oldLeaseToken))['next_reads'][1]['reader_lease_token'], $readerLeaseToken],
    'stale cleanup ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldCleanupToken))['next_reads'][0]['master_journal_cleanup_token_reason'], 'reader_ticket_master_journal_cleanup_predates_current_source'],
    'stale database ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldDatabaseToken))['next_reads'][0]['database_file_token_reason'], 'reader_ticket_database_file_token_predates_current_source'],
    'all fresh no lease invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['reader_lease_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed lease token invalidates fresh pages' => [static fn (): mixed => $plan(null, null, 'reader-lease:shared-cache:epoch=225:opened-after-master-cleanup')['reader_lease_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed lease token keeps cleanup invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'reader-lease:shared-cache:epoch=225:opened-after-master-cleanup')['invalidated_cache_page_numbers'], true), true],
    'changed lease token surfaced' => [static fn (): mixed => $plan(null, null, 'reader-lease:shared-cache:epoch=225:opened-after-master-cleanup')['current_reader_lease_token'], 'reader-lease:shared-cache:epoch=225:opened-after-master-cleanup'],
    'changed cleanup token still inherited' => [static fn (): mixed => $plan(null, null, null, 'master-cleanup:deleted:mtime=22501:dirsync=ok')['master_journal_cleanup_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'changed database token still inherited' => [static fn (): mixed => $plan(null, null, null, null, 'dev=8:ino=2249:size=4096:mtime=22501:generation=database-new')['database_file_token_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next224 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty reader lease token rejected' => static fn () => $plan(null, null, ''),
    'cache missing reader lease token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['reader_lease_token' => true])]),
    'cache empty reader lease token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['reader_lease_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing reader lease token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_lease_token' => true])]),
    'read empty reader lease token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['reader_lease_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next224 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
