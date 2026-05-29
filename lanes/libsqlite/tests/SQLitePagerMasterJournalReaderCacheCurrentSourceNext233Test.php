<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next233.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next233-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-read-transaction-next233';
$publication = 233;
$masterDigest = hash('sha256', 'next233-master-source');
$recoverySequence = 233;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2330:size=96:mtime=23300:generation=master-current';
$databaseToken = 'dev=8:ino=2339:size=4096:mtime=23399:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23400:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=233:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=233:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=233:schema=93:change-counter=233:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=232:schema=92:change-counter=232:before-master-current';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=232:before-master-journal-recovery';
$oldLeaseToken = 'reader-lease:shared-cache:epoch=232:opened-before-master-cleanup';
$oldCleanupToken = 'master-cleanup:exists:mtime=23390:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2339:size=4096:mtime=23398:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 233), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503233), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next233 stale schema before read transaction recovery'),
    2 => $page('next233 stale wp_options root before read transaction recovery'),
    3 => $page('next233 stale active_plugins before read transaction recovery'),
    4 => $page('next233 stale usermeta before read transaction recovery'),
    5 => $page('next233 stale rewrite_rules before read transaction recovery'),
    6 => $page('next233 stale cron before read transaction recovery'),
    7 => $page('next233 stale comments before read transaction recovery'),
    8 => $page('next233 stale terms before read transaction recovery'),
];
$recovered = [
    1 => $formatPage('next233 current schema after read transaction recovery'),
    2 => $page('next233 current wp_options root after read transaction recovery'),
    3 => $page('next233 current active_plugins after read transaction recovery'),
    4 => $page('next233 current usermeta after read transaction recovery'),
    6 => $page('next233 current cron after read transaction recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 233, 0x57503233]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 232, 0x57503232]));
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
    $mainJournal => 'dev=8:ino=2331:size=4096:mtime=23301:generation=main-current',
    $usersJournal => 'dev=8:ino=2332:size=1024:mtime=23302:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-233'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-233'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-233'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 233,
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
    'read_transaction_token' => $readTransactionToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-read-transaction', $recovered[1]),
    2 => $cacheEntry('root-refreshed-read-transaction', $before[2]),
    3 => $cacheEntry('active-stale-read-transaction', $recovered[3], ['read_transaction_token' => $oldReadTransactionToken]),
    4 => $cacheEntry('usermeta-stale-cache-source', $recovered[4], ['pager_cache_source_token' => $oldPagerCacheSourceToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-read-transaction', $before[8], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
];
$reads = static fn (?string $readTransaction = null, ?string $pagerCache = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 233,
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
        'reader_lease_token' => $readerLeaseToken,
        'pager_cache_source_token' => $pagerCache ?? $pagerCacheSourceToken,
        'read_transaction_token' => $readTransaction ?? $readTransactionToken,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentReadTransactionToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext233(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    233,
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
    $currentReadTransactionToken ?? $readTransactionToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next233'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_read_transaction_before_current_source_reuse'],
    'current read transaction token' => [static fn (): mixed => $plan()['current_read_transaction_token'], $readTransactionToken],
    'inherits pager cache source token' => [static fn (): mixed => $plan()['current_pager_cache_source_token'], $pagerCacheSourceToken],
    'read transaction invalidated pages' => [static fn (): mixed => $plan()['read_transaction_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale read transaction' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale transaction cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_read_transaction_after_current_source_next233'), 1],
    'operation reopens stale transaction reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_read_transaction_after_current_source_next233'), 1],
    'dependency next233' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next233', $plan()['dependencies'], true), true],
    'dependency read transaction fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-read-transaction-fence', $plan()['dependencies'], true), true],
    'dependency next229 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next229', $plan()['dependencies'], true), true],
    'non overlap mentions next229' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next229 pager-cache source'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-read-transaction')['read_transaction_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-read-transaction')['read_transaction_token_reason'], 'reader_cache_read_transaction_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-read-transaction')['cache_read_transaction_token'], $readTransactionToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-read-transaction')['current_read_transaction_token'], $readTransactionToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-read-transaction')['read_transaction_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-read-transaction')['read_transaction_token_admitted'], true],
    'row transaction stale admitted false' => [static fn (): mixed => $row('active-stale-read-transaction')['read_transaction_token_admitted'], false],
    'row transaction stale reason' => [static fn (): mixed => $row('active-stale-read-transaction')['read_transaction_token_reason'], 'reader_cache_read_transaction_token_predates_master_journal_current_source'],
    'row transaction stale cache token' => [static fn (): mixed => $row('active-stale-read-transaction')['cache_read_transaction_token'], $oldReadTransactionToken],
    'row transaction stale current token' => [static fn (): mixed => $row('active-stale-read-transaction')['current_read_transaction_token'], $readTransactionToken],
    'row transaction stale mismatch' => [static fn (): mixed => $row('active-stale-read-transaction')['read_transaction_token_matches'], false],
    'row cache source inherits reason' => [static fn (): mixed => $row('usermeta-stale-cache-source')['read_transaction_token_reason'], 'reader_cache_pager_cache_source_token_predates_master_journal_current_source'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['read_transaction_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['read_transaction_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['read_transaction_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-read-transaction')['read_transaction_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained transaction current' => [static fn (): mixed => $read('read-1')['read_transaction_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['read_transaction_token'], $readTransactionToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale transaction cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale transaction source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-read-transaction-fence-current-source-next233'],
    'read stale transaction reason' => [static fn (): mixed => $read('read-3')['read_transaction_token_reason'], 'reader_cache_reopened_after_read_transaction_token_change'],
    'read inherited cache source miss' => [static fn (): mixed => $read('read-4')['cache_hit'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldReadTransactionToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldReadTransactionToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'current ticket with stale cache only reopens inherited readers' => [static fn (): mixed => $plan(null, $reads())['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldReadTransactionToken)), 'reopen_reader_for_read_transaction_after_current_source_next233'), 8],
    'stale read ticket current false' => [static fn (): mixed => $plan(null, $reads($oldReadTransactionToken))['next_reads'][1]['read_transaction_token_current'], false],
    'stale read ticket assigned current' => [static fn (): mixed => $plan(null, $reads($oldReadTransactionToken))['next_reads'][1]['read_transaction_token'], $readTransactionToken],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no transaction invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['read_transaction_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed transaction invalidates fresh pages' => [static fn (): mixed => $plan(null, null, 'read-transaction:epoch=234:schema=94:change-counter=234:master-current')['read_transaction_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed transaction keeps cache-source invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'read-transaction:epoch=234:schema=94:change-counter=234:master-current')['invalidated_cache_page_numbers'], true), true],
    'changed transaction surfaced' => [static fn (): mixed => $plan(null, null, 'read-transaction:epoch=234:schema=94:change-counter=234:master-current')['current_read_transaction_token'], 'read-transaction:epoch=234:schema=94:change-counter=234:master-current'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next233 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty read transaction token rejected' => static fn () => $plan(null, null, ''),
    'cache missing read transaction token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['read_transaction_token' => true])]),
    'cache empty read transaction token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['read_transaction_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing read transaction token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['read_transaction_token' => true])]),
    'read empty read transaction token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['read_transaction_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next233 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
