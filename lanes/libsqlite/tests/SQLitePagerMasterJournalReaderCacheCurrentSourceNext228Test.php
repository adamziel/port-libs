<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next228.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next228-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-payload-digest-next228';
$publication = 228;
$masterDigest = hash('sha256', 'next228-master-source');
$recoverySequence = 228;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2280:size=96:mtime=22800:generation=master-current';
$databaseToken = 'dev=8:ino=2289:size=4096:mtime=22899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=22900:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=228:opened-after-master-cleanup';
$oldPayloadDigest = hash('sha256', 'old-page-payload');
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
    $page = substr_replace($page, pack('N', 228), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next228 stale schema before payload digest recovery'),
    2 => $page('next228 stale wp_options root before payload digest recovery'),
    3 => $page('next228 stale active_plugins before payload digest recovery'),
    4 => $page('next228 stale usermeta before payload digest recovery'),
    5 => $page('next228 stale rewrite_rules before payload digest recovery'),
    6 => $page('next228 stale cron before payload digest recovery'),
    7 => $page('next228 stale comments before payload digest recovery'),
    8 => $page('next228 stale terms before payload digest recovery'),
];
$recovered = [
    1 => $formatPage('next228 current schema after payload digest recovery'),
    2 => $page('next228 current wp_options root after payload digest recovery'),
    3 => $page('next228 current active_plugins after payload digest recovery'),
    4 => $page('next228 current usermeta after payload digest recovery'),
    6 => $page('next228 current cron after payload digest recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 228, 0x57503238]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 227, 0x57503237]));
$currentDigest = static function (int $pageNumber) use ($before, $recovered): string {
    return hash('sha256', $recovered[$pageNumber] ?? $before[$pageNumber]);
};
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
    $mainJournal => 'dev=8:ino=2281:size=4096:mtime=22801:generation=main-current',
    $usersJournal => 'dev=8:ino=2282:size=1024:mtime=22802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-228'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-228'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$orderDigest = hash('sha256', implode("\n", $members));
$cacheEntry = static fn (string $label, string $image, int $pageNumber, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 228,
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
    'page_payload_digest' => $currentDigest($pageNumber),
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-payload', $recovered[1], 1),
    2 => $cacheEntry('root-refreshed-payload', $before[2], 2),
    3 => $cacheEntry('active-stale-payload', $recovered[3], 3, ['page_payload_digest' => $oldPayloadDigest]),
    4 => $cacheEntry('usermeta-stale-lease', $recovered[4], 4, ['reader_lease_token' => 'reader-lease:old']),
    5 => $cacheEntry('rewrite-stale-database-token', $before[5], 5, ['database_file_token' => 'dev=old']),
    6 => $cacheEntry('cron-stale-format', $recovered[6], 6, ['format_signature' => $oldFormatSignature]),
    7 => $cacheEntry('comments-stale-payload', $before[7], 7, ['page_payload_digest' => $oldPayloadDigest]),
    8 => $cacheEntry('terms-dirty-payload', $before[8], 8, ['dirty' => true]),
];
$reads = static fn (?string $payloadDigest = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 228,
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
        'page_payload_digest' => $payloadDigest ?? $currentDigest($pageNumber),
    ],
    range(1, 8),
);
$plan = static fn (?array $readerCache = null, ?array $readList = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext228(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    228,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next228'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_page_payload_digest_before_current_source_reuse'],
    'payload invalidated pages' => [static fn (): mixed => $plan()['page_payload_invalidated_cache_page_numbers'], [3, 7]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale payload' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'current digest count' => [static fn (): mixed => count($plan()['current_page_payload_digests']), 8],
    'current digest page one' => [static fn (): mixed => $plan()['current_page_payload_digests'][1], $currentDigest(1)],
    'current digest page five from database' => [static fn (): mixed => $plan()['current_page_payload_digests'][5], $currentDigest(5)],
    'operation payload invalidations' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_page_payload_digest_after_current_source_next228'), 2],
    'operation reopen payload' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_page_payload_digest_after_current_source_next228'), 2],
    'dependency next228' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next228', $plan()['dependencies'], true), true],
    'dependency payload fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-page-payload-digest-fence', $plan()['dependencies'], true), true],
    'dependency next224 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next224', $plan()['dependencies'], true), true],
    'non overlap mentions next224' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next224 reader-lease'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-payload')['page_payload_digest_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-payload')['page_payload_digest_reason'], 'reader_cache_page_payload_digest_matches_current_source'],
    'row retained cache digest' => [static fn (): mixed => $row('schema-retained-payload')['cache_page_payload_digest'], $currentDigest(1)],
    'row retained current digest' => [static fn (): mixed => $row('schema-retained-payload')['current_page_payload_digest'], $currentDigest(1)],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-payload')['page_payload_digest_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-payload')['page_payload_digest_admitted'], true],
    'row stale payload admitted false' => [static fn (): mixed => $row('active-stale-payload')['page_payload_digest_admitted'], false],
    'row stale payload reason' => [static fn (): mixed => $row('active-stale-payload')['page_payload_digest_reason'], 'reader_cache_page_payload_digest_predates_current_master_journal_source'],
    'row stale payload cache digest' => [static fn (): mixed => $row('active-stale-payload')['cache_page_payload_digest'], $oldPayloadDigest],
    'row stale payload current digest' => [static fn (): mixed => $row('active-stale-payload')['current_page_payload_digest'], $currentDigest(3)],
    'row stale payload mismatch' => [static fn (): mixed => $row('active-stale-payload')['page_payload_digest_matches'], false],
    'row stale lease inherits reason' => [static fn (): mixed => $row('usermeta-stale-lease')['page_payload_digest_reason'], 'reader_cache_reader_lease_token_predates_master_journal_current_source'],
    'row stale database inherits reason' => [static fn (): mixed => $row('rewrite-stale-database-token')['page_payload_digest_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row stale format inherits reason' => [static fn (): mixed => $row('cron-stale-format')['page_payload_digest_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row unchanged stale payload admitted false' => [static fn (): mixed => $row('comments-stale-payload')['page_payload_digest_admitted'], false],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-payload')['page_payload_digest_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained payload current' => [static fn (): mixed => $read('read-1')['page_payload_digest_current'], true],
    'read retained payload digest' => [static fn (): mixed => $read('read-1')['page_payload_digest'], $currentDigest(1)],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale payload cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale payload source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-page-payload-fence-current-source-next228'],
    'read stale payload reason' => [static fn (): mixed => $read('read-3')['page_payload_digest_reason'], 'reader_cache_reopened_after_page_payload_digest_change'],
    'read unchanged stale payload source' => [static fn (): mixed => $read('read-7')['source'], 'master-journal-reader-cache-page-payload-fence-current-source-next228'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldPayloadDigest))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldPayloadDigest))['next_reads'][0]['page_payload_digest_reason'], 'reader_ticket_page_payload_digest_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldPayloadDigest))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldPayloadDigest)), 'reopen_reader_for_page_payload_digest_after_current_source_next228'), 8],
    'all fresh no payload invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], 1)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['page_payload_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], 1)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next228 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing cache payload digest rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1], 1), ['page_payload_digest' => true])]),
    'empty cache payload digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], 1, ['page_payload_digest' => ''])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $recovered[1], 1)]),
    'missing read payload digest rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['page_payload_digest' => true])]),
    'empty read payload digest rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_payload_digest' => ''] + $reads()[0]]),
    'empty read id rejected' => static fn () => $plan(null, [['reader_id' => '', 'page_payload_digest' => $currentDigest(1)] + $reads()[0]]),
    'bad page size rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext228($database, $master, $masterBytes, implode('', $before), 500, $recovered, $cache(), $reads(), $sourceId, 228, $publication, $masterDigest, $recoverySequence, $tokens, $headers, $masterToken, $databaseToken, $cleanupToken, $readerLeaseToken),
    'unaligned database rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext228($database, $master, $masterBytes, implode('', $before) . 'x', $pageSize, $recovered, $cache(), $reads(), $sourceId, 228, $publication, $masterDigest, $recoverySequence, $tokens, $headers, $masterToken, $databaseToken, $cleanupToken, $readerLeaseToken),
    'bad recovered page number rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext228($database, $master, $masterBytes, implode('', $before), $pageSize, [0 => $recovered[1]], $cache(), $reads(), $sourceId, 228, $publication, $masterDigest, $recoverySequence, $tokens, $headers, $masterToken, $databaseToken, $cleanupToken, $readerLeaseToken),
    'short recovered page rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext228($database, $master, $masterBytes, implode('', $before), $pageSize, [1 => 'short'], $cache(), $reads(), $sourceId, 228, $publication, $masterDigest, $recoverySequence, $tokens, $headers, $masterToken, $databaseToken, $cleanupToken, $readerLeaseToken),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next228 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
