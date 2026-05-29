<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next209.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next209-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-master-bytes-next209';
$publication = 209;
$masterDigest = hash('sha256', 'next209-master-source');
$recoverySequence = 209;
$members = [$mainJournal, $usersJournal];
$masterBytes = "  {$mainJournal}\n{$usersJournal}\n";
$oldMasterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$oldMasterBytesDigest = hash('sha256', $oldMasterBytes);
$currentMasterToken = 'dev=8:ino=2090:size=72:mtime=20900:generation=master-current';
$oldMasterToken = 'dev=8:ino=2090:size=72:mtime=20899:generation=master-prior';
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
    $page = substr_replace($page, pack('N', 209), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next209 stale schema before raw master bytes recovery'),
    2 => $page('next209 stale wp_options root before raw master bytes recovery'),
    3 => $page('next209 stale active_plugins before raw master bytes recovery'),
    4 => $page('next209 stale usermeta before raw master bytes recovery'),
    5 => $page('next209 stale rewrite_rules before raw master bytes recovery'),
    6 => $page('next209 stale cron before raw master bytes recovery'),
    7 => $page('next209 stale comments before raw master bytes recovery'),
];
$recovered = [
    1 => $formatPage('next209 current schema after raw master bytes recovery'),
    2 => $page('next209 current wp_options root after raw master bytes recovery'),
    3 => $page('next209 current active_plugins after raw master bytes recovery'),
    4 => $page('next209 current usermeta after raw master bytes recovery'),
    6 => $page('next209 current cron after raw master bytes recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 209, 0x57503239]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 208, 0x57503238]));
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
    $mainJournal => 'dev=8:ino=2091:size=4096:mtime=20901:generation=main-current',
    $usersJournal => 'dev=8:ino=2092:size=1024:mtime=20902:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-209'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-209'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-209'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$oldHeaderDigest = $mapDigest($oldHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 209,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-master-bytes', $recovered[1]),
    2 => $cacheEntry('root-refreshed-master-bytes', $before[2]),
    3 => $cacheEntry('active-stale-master-bytes', $recovered[3], ['master_journal_bytes_digest' => $oldMasterBytesDigest]),
    4 => $cacheEntry('usermeta-stale-header', $recovered[4], ['member_journal_header_digests' => $oldHeaders]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-master-token', $recovered[6], ['master_journal_file_token' => $oldMasterToken]),
    7 => $cacheEntry('comments-dirty-master-bytes', $before[7], ['dirty' => true]),
];
$reads = static fn (string $bytesDigest = null, string $masterToken = null, string $header = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 209,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $header ?? $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken ?? $currentMasterToken,
        'master_journal_bytes_digest' => $bytesDigest ?? $currentMasterBytesDigest,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $masterToken = null,
    ?array $headers = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext209(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    209,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $headers ?? $currentHeaders,
    $masterToken ?? $currentMasterToken,
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};
$opCount = static function (string $op) use ($plan): int {
    return count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next209'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_raw_master_journal_bytes_before_current_source_reuse'],
    'current master bytes digest' => [static fn (): mixed => $plan()['current_master_journal_bytes_digest'], $currentMasterBytesDigest],
    'inherits master file token' => [static fn (): mixed => $plan()['current_master_journal_file_token'], $currentMasterToken],
    'inherits order digest' => [static fn (): mixed => $plan()['current_master_member_order_digest'], $orderDigest],
    'inherits header digest' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'bytes invalidated page' => [static fn (): mixed => $plan()['master_journal_bytes_digest_invalidated_cache_page_numbers'], [3]],
    'file token invalidated page preserved' => [static fn (): mixed => $plan()['master_journal_file_token_invalidated_cache_page_numbers'], [6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema bytes admitted' => [static fn (): mixed => $row('schema-retained-master-bytes')['master_journal_bytes_digest_admitted'], true],
    'schema bytes reason' => [static fn (): mixed => $row('schema-retained-master-bytes')['master_journal_bytes_digest_reason'], 'reader_cache_master_journal_bytes_digest_matches_current_source'],
    'root refreshed bytes admitted' => [static fn (): mixed => $row('root-refreshed-master-bytes')['master_journal_bytes_digest_admitted'], true],
    'stale bytes rejected' => [static fn (): mixed => $row('active-stale-master-bytes')['master_journal_bytes_digest_admitted'], false],
    'stale bytes reason' => [static fn (): mixed => $row('active-stale-master-bytes')['master_journal_bytes_digest_reason'], 'reader_cache_master_journal_bytes_digest_changed'],
    'stale bytes cache digest' => [static fn (): mixed => $row('active-stale-master-bytes')['cache_master_journal_bytes_digest'], $oldMasterBytesDigest],
    'stale bytes current digest' => [static fn (): mixed => $row('active-stale-master-bytes')['current_master_journal_bytes_digest'], $currentMasterBytesDigest],
    'bytes mismatch flag' => [static fn (): mixed => $row('active-stale-master-bytes')['master_journal_bytes_digest_matches'], false],
    'header reason preserved' => [static fn (): mixed => $row('usermeta-stale-header')['master_journal_bytes_digest_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'format reason preserved' => [static fn (): mixed => $row('rewrite-stale-format')['master_journal_bytes_digest_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'file token reason preserved' => [static fn (): mixed => $row('cron-stale-master-token')['master_journal_bytes_digest_reason'], 'reader_cache_master_journal_file_token_changed'],
    'dirty reason preserved' => [static fn (): mixed => $row('comments-dirty-master-bytes')['master_journal_bytes_digest_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read bytes miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read stale header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read stale file-token miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-6'], false],
    'read master bytes current true' => [static fn (): mixed => $plan()['next_reads'][0]['master_journal_bytes_digest_current'], true],
    'read master bytes source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-bytes-digest-fence-current-source-next209'],
    'read master bytes reason' => [static fn (): mixed => $plan()['next_reads'][2]['master_journal_bytes_digest_reason'], 'reader_cache_reopened_after_master_journal_bytes_digest_change'],
    'read stale bytes ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldMasterBytesDigest))['read_cache_hits']['read-1'], false],
    'read stale bytes ticket reason' => [static fn (): mixed => $plan(null, $reads($oldMasterBytesDigest))['next_reads'][0]['master_journal_bytes_digest_reason'], 'reader_ticket_master_journal_bytes_digest_predates_current_source'],
    'read stale file token still uses file token reason' => [static fn (): mixed => $plan(null, $reads($currentMasterBytesDigest, $oldMasterToken))['next_reads'][0]['master_journal_file_token_reason'], 'reader_ticket_master_journal_file_token_predates_current_source'],
    'read stale header still uses header reason' => [static fn (): mixed => $plan(null, $reads($currentMasterBytesDigest, $currentMasterToken, $oldHeaderDigest))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation appended' => [static fn (): mixed => in_array('invalidate_reader_cache_master_journal_bytes_digest_after_current_source_next209', array_column($plan()['operations'], 'op'), true), true],
    'operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_master_journal_bytes_digest_after_current_source_next209'), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next209', $plan()['dependencies'], true), true],
    'dependency bytes marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-master-journal-raw-bytes-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next206', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next206'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'same member maps bytes differs' => [static fn (): mixed => $row('active-stale-master-bytes')['cache_member_journal_header_digest'], $currentHeaderDigest],
    'same member order bytes differs' => [static fn (): mixed => $row('active-stale-master-bytes')['master_member_order_digest_matches'], true],
    'same master token bytes differs' => [static fn (): mixed => $row('active-stale-master-bytes')['master_journal_file_token_matches'], true],
    'all current single read hits' => [static fn (): mixed => $plan([
        1 => $cacheEntry('schema-retained-master-bytes', $recovered[1]),
    ], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 209,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
    ]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next209 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing cache bytes digest rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['master_journal_bytes_digest' => true])], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 209,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
    ]]),
    'empty cache bytes digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['master_journal_bytes_digest' => ''])]),
    'missing read bytes digest rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 209,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
    ]]),
    'empty read bytes digest rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 209,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'master_journal_bytes_digest' => '',
    ]]),
    'empty current master token still rejected by base' => static fn () => $plan(null, null, ''),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next209 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
