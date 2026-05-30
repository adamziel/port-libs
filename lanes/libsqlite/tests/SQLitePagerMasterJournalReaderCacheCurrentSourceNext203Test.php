<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next203.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next203-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-member-order-next203';
$publication = 203;
$masterDigest = hash('sha256', 'next203-master-source');
$recoverySequence = 203;
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
    $page = substr_replace($page, pack('N', 203), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503230), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next203 stale schema before ordered member recovery'),
    2 => $page('next203 stale wp_options root before ordered member recovery'),
    3 => $page('next203 stale active_plugins before ordered member recovery'),
    4 => $page('next203 stale usermeta before ordered member recovery'),
    5 => $page('next203 stale rewrite_rules before ordered member recovery'),
    6 => $page('next203 stale cron before ordered member recovery'),
    7 => $page('next203 stale comments before ordered member recovery'),
];
$recovered = [
    1 => $formatPage('next203 current schema after ordered member recovery'),
    2 => $page('next203 current wp_options root after ordered member recovery'),
    3 => $page('next203 current active_plugins after ordered member recovery'),
    4 => $page('next203 current usermeta after ordered member recovery'),
    6 => $page('next203 current cron after ordered member recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 203, 0x57503230]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 202, 0x57503229]));
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
    $mainJournal => 'dev=8:ino=2030:size=4096:mtime=20300:generation=main-current',
    $usersJournal => 'dev=8:ino=2031:size=1024:mtime=20301:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-203'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-203'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-203'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentOrderDigest = $orderDigest($members);
$oldOrderDigest = $orderDigest($oldMembers);
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$oldHeaderDigest = $mapDigest($oldHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 203,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
    'master_member_order_digest' => $currentOrderDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-order', $recovered[1]),
    2 => $cacheEntry('root-refreshed-order', $before[2]),
    3 => $cacheEntry('active-stale-member-order', $recovered[3], ['master_member_order_digest' => $oldOrderDigest]),
    4 => $cacheEntry('usermeta-stale-header', $recovered[4], ['member_journal_header_digests' => $oldHeaders]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-pinned-order', $before[6], ['pinned' => true]),
    7 => $cacheEntry('comments-dirty-order', $before[7], ['dirty' => true]),
];
$reads = static fn (string $order = null, string $header = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 203,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $header ?? $currentHeaderDigest,
        'master_member_order_digest' => $order ?? $currentOrderDigest,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $headers = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMemberOrderDigestFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    203,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $headers ?? $currentHeaders,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next203'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_member_order_before_current_source_reuse'],
    'current order digest' => [static fn (): mixed => $plan()['current_master_member_order_digest'], $currentOrderDigest],
    'inherits header digest' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'inherits token digest' => [static fn (): mixed => $plan()['current_member_journal_token_digest'], $currentTokenDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'order invalidated page' => [static fn (): mixed => $plan()['master_member_order_invalidated_cache_page_numbers'], [3]],
    'header invalidated page preserved' => [static fn (): mixed => $plan()['member_header_invalidated_cache_page_numbers'], [4]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema order admitted' => [static fn (): mixed => $row('schema-retained-order')['master_member_order_admitted'], true],
    'schema order reason' => [static fn (): mixed => $row('schema-retained-order')['master_member_order_reason'], 'reader_cache_master_member_order_matches_current_source'],
    'root refreshed order admitted' => [static fn (): mixed => $row('root-refreshed-order')['master_member_order_admitted'], true],
    'stale order rejected' => [static fn (): mixed => $row('active-stale-member-order')['master_member_order_admitted'], false],
    'stale order reason' => [static fn (): mixed => $row('active-stale-member-order')['master_member_order_reason'], 'reader_cache_master_member_order_changed'],
    'stale order cache digest' => [static fn (): mixed => $row('active-stale-member-order')['cache_master_member_order_digest'], $oldOrderDigest],
    'stale order current digest' => [static fn (): mixed => $row('active-stale-member-order')['current_master_member_order_digest'], $currentOrderDigest],
    'order mismatch flag' => [static fn (): mixed => $row('active-stale-member-order')['master_member_order_digest_matches'], false],
    'header reason preserved' => [static fn (): mixed => $row('usermeta-stale-header')['master_member_order_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'format reason preserved' => [static fn (): mixed => $row('rewrite-stale-format')['master_member_order_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'pinned reason preserved' => [static fn (): mixed => $row('cron-pinned-order')['master_member_order_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'dirty reason preserved' => [static fn (): mixed => $row('comments-dirty-order')['master_member_order_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read order miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read stale header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read order current true' => [static fn (): mixed => $plan()['next_reads'][0]['master_member_order_current'], true],
    'read order source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-member-order-fence-current-source-next203'],
    'read order reason' => [static fn (): mixed => $plan()['next_reads'][2]['master_member_order_reason'], 'reader_cache_reopened_after_master_member_order_change'],
    'read stale order ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldOrderDigest))['read_cache_hits']['read-1'], false],
    'read stale order ticket reason' => [static fn (): mixed => $plan(null, $reads($oldOrderDigest))['next_reads'][0]['master_member_order_reason'], 'reader_ticket_master_member_order_predates_current_source'],
    'read stale header still uses header reason' => [static fn (): mixed => $plan(null, $reads($currentOrderDigest, $oldHeaderDigest))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation appended' => [static fn (): mixed => in_array('invalidate_reader_cache_master_member_order_after_current_source_next203', array_column($plan()['operations'], 'op'), true), true],
    'operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_master_member_order_after_current_source_next203'), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next203', $plan()['dependencies'], true), true],
    'dependency order marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-master-member-order-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next196', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next196'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'same sorted maps different order invalidates' => [static fn (): mixed => $row('active-stale-member-order')['cache_member_journal_header_digest'], $currentHeaderDigest],
    'same sorted maps order still differs' => [static fn (): mixed => $row('active-stale-member-order')['master_member_order_digest_matches'], false],
    'all current single read hits' => [static fn (): mixed => $plan([
        1 => $cacheEntry('schema-retained-order', $recovered[1]),
    ], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 203,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $currentOrderDigest,
    ]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next203 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing cache order rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['master_member_order_digest' => true])], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 203,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $currentOrderDigest,
    ]]),
    'empty cache order rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['master_member_order_digest' => ''])]),
    'missing read order rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 203,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
    ]]),
    'empty read order rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 203,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => '',
    ]]),
    'missing current header still rejected by base' => static fn () => $plan(null, null, [$mainJournal => $currentHeaders[$mainJournal]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next203 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
