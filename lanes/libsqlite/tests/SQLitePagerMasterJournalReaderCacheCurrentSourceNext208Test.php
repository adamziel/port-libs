<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next208.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next208-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-read-snapshot-next208';
$publication = 208;
$masterDigest = hash('sha256', 'next208-master-source');
$recoverySequence = 208;
$members = [$mainJournal, $usersJournal];
$oldMembers = [$usersJournal, $mainJournal];
$masterBytes = implode("\n", $members) . "\n";
$orderDigest = static fn (array $ordered): string => hash('sha256', implode("\n", $ordered));
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
    $page = substr_replace($page, pack('N', 208), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next208 stale schema before master snapshot reread'),
    2 => $page('next208 stale wp_options root before master snapshot reread'),
    3 => $page('next208 stale alloptions before master snapshot reread'),
    4 => $page('next208 stale usermeta before master snapshot reread'),
    5 => $page('next208 stale cron before master snapshot reread'),
    6 => $page('next208 stale rewrite rules before master snapshot reread'),
    7 => $page('next208 stale comments before master snapshot reread'),
];
$recovered = [
    1 => $formatPage('next208 current schema after master snapshot reread'),
    2 => $page('next208 current wp_options root after master snapshot reread'),
    3 => $page('next208 current alloptions after master snapshot reread'),
    4 => $page('next208 current usermeta after master snapshot reread'),
    5 => $page('next208 current cron after master snapshot reread'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 208, 0x57503238]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 207, 0x57503237]));
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
    $mainJournal => 'dev=8:ino=2080:size=4096:mtime=20800:generation=main-current',
    $usersJournal => 'dev=8:ino=2081:size=1024:mtime=20801:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-208'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-208'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-208'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentOrderDigest = $orderDigest($members);
$oldOrderDigest = $orderDigest($oldMembers);
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$oldHeaderDigest = $mapDigest($oldHeaders);
$currentSnapshotDigest = hash('sha256', $master . '|offset=0|length=' . strlen($masterBytes) . '|bytes=' . $masterBytes . '|next208-current');
$oldSnapshotDigest = hash('sha256', $master . '|offset=0|length=' . strlen($masterBytes) . '|bytes=' . $masterBytes . '|next207-prior');
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 208,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
    'master_member_order_digest' => $currentOrderDigest,
    'master_read_snapshot_digest' => $currentSnapshotDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-snapshot', $recovered[1]),
    2 => $cacheEntry('root-refreshed-snapshot', $before[2]),
    3 => $cacheEntry('alloptions-stale-snapshot', $recovered[3], ['master_read_snapshot_digest' => $oldSnapshotDigest]),
    4 => $cacheEntry('usermeta-stale-order', $recovered[4], ['master_member_order_digest' => $oldOrderDigest]),
    5 => $cacheEntry('cron-stale-header', $recovered[5], ['member_journal_header_digests' => $oldHeaders]),
    6 => $cacheEntry('rewrite-stale-format', $before[6], ['format_signature' => $oldFormatSignature]),
    7 => $cacheEntry('comments-dirty-snapshot', $before[7], ['dirty' => true]),
];
$reads = static fn (string $snapshot = null, string $order = null, string $header = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 208,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $header ?? $currentHeaderDigest,
        'master_member_order_digest' => $order ?? $currentOrderDigest,
        'master_read_snapshot_digest' => $snapshot ?? $currentSnapshotDigest,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $snapshot = null,
    ?array $headers = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantRecoveredPageSetFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    208,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $headers ?? $currentHeaders,
    $snapshot ?? $currentSnapshotDigest,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next208'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_master_read_snapshot_before_current_source_reuse'],
    'current snapshot digest' => [static fn (): mixed => $plan()['current_master_read_snapshot_digest'], $currentSnapshotDigest],
    'current order digest retained' => [static fn (): mixed => $plan()['current_master_member_order_digest'], $currentOrderDigest],
    'current header digest retained' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'current token digest retained' => [static fn (): mixed => $plan()['current_member_journal_token_digest'], $currentTokenDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'snapshot invalidated page' => [static fn (): mixed => $plan()['master_read_snapshot_invalidated_cache_page_numbers'], [3]],
    'order invalidated page retained from base' => [static fn (): mixed => $plan()['master_member_order_invalidated_cache_page_numbers'], [4]],
    'header invalidated page retained from base' => [static fn (): mixed => $plan()['member_header_invalidated_cache_page_numbers'], [5]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema snapshot admitted' => [static fn (): mixed => $row('schema-retained-snapshot')['master_read_snapshot_admitted'], true],
    'schema snapshot reason' => [static fn (): mixed => $row('schema-retained-snapshot')['master_read_snapshot_reason'], 'reader_cache_master_read_snapshot_matches_current_source'],
    'root refreshed snapshot admitted' => [static fn (): mixed => $row('root-refreshed-snapshot')['master_read_snapshot_admitted'], true],
    'stale snapshot rejected' => [static fn (): mixed => $row('alloptions-stale-snapshot')['master_read_snapshot_admitted'], false],
    'stale snapshot reason' => [static fn (): mixed => $row('alloptions-stale-snapshot')['master_read_snapshot_reason'], 'reader_cache_master_journal_read_snapshot_changed'],
    'stale snapshot cache digest' => [static fn (): mixed => $row('alloptions-stale-snapshot')['cache_master_read_snapshot_digest'], $oldSnapshotDigest],
    'stale snapshot current digest' => [static fn (): mixed => $row('alloptions-stale-snapshot')['current_master_read_snapshot_digest'], $currentSnapshotDigest],
    'snapshot mismatch flag' => [static fn (): mixed => $row('alloptions-stale-snapshot')['master_read_snapshot_digest_matches'], false],
    'order reason preserved' => [static fn (): mixed => $row('usermeta-stale-order')['master_read_snapshot_reason'], 'reader_cache_master_member_order_changed'],
    'header reason preserved' => [static fn (): mixed => $row('cron-stale-header')['master_read_snapshot_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'format reason preserved' => [static fn (): mixed => $row('rewrite-stale-format')['master_read_snapshot_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'dirty reason preserved' => [static fn (): mixed => $row('comments-dirty-snapshot')['master_read_snapshot_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read snapshot miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read order miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], false],
    'read snapshot current true' => [static fn (): mixed => $plan()['next_reads'][0]['master_read_snapshot_current'], true],
    'read snapshot source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-read-snapshot-fence-current-source-next208'],
    'read snapshot reason' => [static fn (): mixed => $plan()['next_reads'][2]['master_read_snapshot_reason'], 'reader_cache_reopened_after_master_journal_read_snapshot_change'],
    'read stale snapshot ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldSnapshotDigest))['read_cache_hits']['read-1'], false],
    'read stale snapshot ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSnapshotDigest))['next_reads'][0]['master_read_snapshot_reason'], 'reader_ticket_master_journal_read_snapshot_predates_current_source'],
    'read stale order still uses order reason' => [static fn (): mixed => $plan(null, $reads($currentSnapshotDigest, $oldOrderDigest))['next_reads'][0]['master_member_order_reason'], 'reader_ticket_master_member_order_predates_current_source'],
    'read stale header still uses header reason' => [static fn (): mixed => $plan(null, $reads($currentSnapshotDigest, $currentOrderDigest, $oldHeaderDigest))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation appended' => [static fn (): mixed => in_array('invalidate_reader_cache_master_read_snapshot_after_current_source_next208', array_column($plan()['operations'], 'op'), true), true],
    'operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_master_read_snapshot_after_current_source_next208'), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next208', $plan()['dependencies'], true), true],
    'dependency snapshot marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-master-read-snapshot-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next203', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next203'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'same member maps different snapshot invalidates' => [static fn (): mixed => $row('alloptions-stale-snapshot')['cache_member_journal_header_digest'], $currentHeaderDigest],
    'same member order different snapshot invalidates' => [static fn (): mixed => $row('alloptions-stale-snapshot')['master_member_order_digest_matches'], true],
    'all current single read hits' => [static fn (): mixed => $plan([
        1 => $cacheEntry('schema-retained-snapshot', $recovered[1]),
    ], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 208,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $currentOrderDigest,
        'master_read_snapshot_digest' => $currentSnapshotDigest,
    ]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next208 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing cache snapshot rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['master_read_snapshot_digest' => true])], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 208,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $currentOrderDigest,
        'master_read_snapshot_digest' => $currentSnapshotDigest,
    ]]),
    'empty cache snapshot rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['master_read_snapshot_digest' => ''])]),
    'missing read snapshot rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 208,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $currentOrderDigest,
    ]]),
    'empty read snapshot rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 208,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $currentOrderDigest,
        'master_read_snapshot_digest' => '',
    ]]),
    'empty current snapshot rejected' => static fn () => $plan(null, null, ''),
    'missing current header still rejected by base' => static fn () => $plan(null, null, null, [$mainJournal => $currentHeaders[$mainJournal]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next208 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
