<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next211.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next211-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-member-pages-next211';
$publication = 211;
$masterDigest = hash('sha256', 'next211-master-source');
$recoverySequence = 211;
$members = [$mainJournal, $usersJournal];
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2110:size=70:mtime=21100:generation=master-current';
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
    $page = substr_replace($page, pack('N', 211), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503231), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next211 stale schema before member page recovery'),
    2 => $page('next211 stale wp_options root before member page recovery'),
    3 => $page('next211 stale active_plugins before member page recovery'),
    4 => $page('next211 stale usermeta before member page recovery'),
    5 => $page('next211 stale rewrite_rules before member page recovery'),
    6 => $page('next211 stale cron before member page recovery'),
];
$recovered = [
    1 => $formatPage('next211 current schema after member page recovery'),
    2 => $page('next211 current wp_options root after member page recovery'),
    3 => $page('next211 current active_plugins after member page recovery'),
    4 => $page('next211 current usermeta after member page recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 211, 0x57503231]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 210, 0x57503230]));
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
    $mainJournal => 'dev=8:ino=2111:size=4096:mtime=21101:generation=main-current',
    $usersJournal => 'dev=8:ino=2112:size=2048:mtime=21102:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-211'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-211'),
];
$currentMemberRecovered = [
    $mainJournal => hash('sha256', 'main-pages:1,2,3:211'),
    $usersJournal => hash('sha256', 'users-pages:4:211'),
];
$oldMemberRecovered = [
    $mainJournal => $currentMemberRecovered[$mainJournal],
    $usersJournal => hash('sha256', 'users-pages:4-old:210'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-211'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($currentTokens);
$headerDigest = $mapDigest($currentHeaders);
$memberRecoveredDigest = $mapDigest($currentMemberRecovered);
$oldMemberRecoveredDigest = $mapDigest($oldMemberRecovered);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 211,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
    'member_journal_recovered_page_digests' => $currentMemberRecovered,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-member-pages', $recovered[1]),
    2 => $cacheEntry('root-refreshed-member-pages', $before[2]),
    3 => $cacheEntry('active-stale-member-pages', $recovered[3], ['member_journal_recovered_page_digests' => $oldMemberRecovered]),
    4 => $cacheEntry('usermeta-stale-header', $recovered[4], ['member_journal_header_digests' => $oldHeaders]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-dirty-member-pages', $before[6], ['dirty' => true]),
];
$reads = static fn (string $memberDigest = null, string $header = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 211,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $header ?? $headerDigest,
        'member_journal_recovered_page_digest' => $memberDigest ?? $memberRecoveredDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $memberRecovered = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext211(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    211,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $memberRecovered ?? $currentMemberRecovered,
    $masterToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next211'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_attached_member_recovered_page_sets_before_current_source_reuse'],
    'current member recovered digest' => [static fn (): mixed => $plan()['current_member_journal_recovered_page_digest'], $memberRecoveredDigest],
    'inherits master bytes digest' => [static fn (): mixed => $plan()['current_master_journal_bytes_digest'], $masterBytesDigest],
    'inherits file token' => [static fn (): mixed => $plan()['current_master_journal_file_token'], $masterToken],
    'inherits header digest' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $headerDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'member page invalidated page' => [static fn (): mixed => $plan()['member_recovered_page_invalidated_cache_page_numbers'], [3]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema admitted' => [static fn (): mixed => $row('schema-retained-member-pages')['member_recovered_page_admitted'], true],
    'schema reason' => [static fn (): mixed => $row('schema-retained-member-pages')['member_recovered_page_reason'], 'reader_cache_attached_member_recovered_page_sets_match_current_source'],
    'root refreshed admitted' => [static fn (): mixed => $row('root-refreshed-member-pages')['member_recovered_page_admitted'], true],
    'stale member pages rejected' => [static fn (): mixed => $row('active-stale-member-pages')['member_recovered_page_admitted'], false],
    'stale member pages reason' => [static fn (): mixed => $row('active-stale-member-pages')['member_recovered_page_reason'], 'reader_cache_attached_member_recovered_page_set_changed'],
    'stale member mismatch list' => [static fn (): mixed => $row('active-stale-member-pages')['mismatched_member_journal_recovered_pages'], [$usersJournal]],
    'stale member cache digest' => [static fn (): mixed => $row('active-stale-member-pages')['cache_member_journal_recovered_page_digest'], $oldMemberRecoveredDigest],
    'stale member current digest' => [static fn (): mixed => $row('active-stale-member-pages')['current_member_journal_recovered_page_digest'], $memberRecoveredDigest],
    'header reason preserved' => [static fn (): mixed => $row('usermeta-stale-header')['member_recovered_page_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'format reason preserved' => [static fn (): mixed => $row('rewrite-stale-format')['member_recovered_page_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'dirty reason preserved' => [static fn (): mixed => $row('cron-dirty-member-pages')['member_recovered_page_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read member pages miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read dirty miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-6'], false],
    'read member pages current true' => [static fn (): mixed => $plan()['next_reads'][0]['member_journal_recovered_page_current'], true],
    'read member pages source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-member-recovered-pages-fence-current-source-next211'],
    'read member pages reason' => [static fn (): mixed => $plan()['next_reads'][2]['member_recovered_page_reason'], 'reader_cache_reopened_after_attached_member_recovered_page_set_change'],
    'read stale member ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldMemberRecoveredDigest))['read_cache_hits']['read-1'], false],
    'read stale member ticket reason' => [static fn (): mixed => $plan(null, $reads($oldMemberRecoveredDigest))['next_reads'][0]['member_recovered_page_reason'], 'reader_ticket_attached_member_recovered_page_set_predates_current_source'],
    'read stale header still uses header reason' => [static fn (): mixed => $plan(null, $reads($memberRecoveredDigest, $mapDigest($oldHeaders)))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'operation appended' => [static fn (): mixed => in_array('invalidate_reader_cache_attached_member_recovered_pages_after_current_source_next211', array_column($plan()['operations'], 'op'), true), true],
    'operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_attached_member_recovered_pages_after_current_source_next211'), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next211', $plan()['dependencies'], true), true],
    'dependency member pages marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-attached-member-recovered-page-set-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next209', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next209'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'same master bytes digest differs only member pages' => [static fn (): mixed => $row('active-stale-member-pages')['master_journal_bytes_digest_matches'], true],
    'same member headers differs only member pages' => [static fn (): mixed => $row('active-stale-member-pages')['cache_member_journal_header_digest'], $headerDigest],
    'same member tokens differs only member pages' => [static fn (): mixed => $row('active-stale-member-pages')['cache_member_journal_token_digest'], $tokenDigest],
    'all current single read hits' => [static fn (): mixed => $plan([
        1 => $cacheEntry('schema-retained-member-pages', $recovered[1]),
    ], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 211,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'member_journal_recovered_page_digest' => $memberRecoveredDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
    ]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next211 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing current member recovered digest rejected' => static fn () => $plan(null, null, [$mainJournal => $currentMemberRecovered[$mainJournal]]),
    'empty current member recovered digest rejected' => static fn () => $plan(null, null, [$mainJournal => $currentMemberRecovered[$mainJournal], $usersJournal => '']),
    'missing cache member recovered digest rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['member_journal_recovered_page_digests' => true])]),
    'empty cache member recovered digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_recovered_page_digests' => [$mainJournal => $currentMemberRecovered[$mainJournal], $usersJournal => '']])]),
    'missing read member recovered digest rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 211,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
    ]]),
    'empty read member recovered digest rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 211,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'member_journal_recovered_page_digest' => '',
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
    ]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next211 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
