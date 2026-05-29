<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next219.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next219-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-page-count-next219';
$publication = 219;
$masterDigest = hash('sha256', 'next219-master-source');
$recoverySequence = 219;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2190:size=96:mtime=21900:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2199:size=2560:mtime=21999:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=219;change-counter=46;version-valid-for=46;page-count=5');
$oldHeaderDigest = hash('sha256', 'schema-cookie=218;change-counter=45;version-valid-for=45;page-count=6');
$currentPageCount = 5;
$oldPageCount = 6;
$expandedPageCount = 7;
$oldDatabaseToken = 'dev=8:ino=2199:size=3072:mtime=21998:generation=database-prior';
$members = [$mainJournal, $usersJournal];
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
    $page = substr_replace($page, pack('N', 219), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next219 stale schema before page-count recovery'),
    2 => $page('next219 stale wp_options root before page-count recovery'),
    3 => $page('next219 stale active_plugins before page-count recovery'),
    4 => $page('next219 stale usermeta before page-count recovery'),
    5 => $page('next219 stale cron before page-count recovery'),
    6 => $page('next219 truncated comments before page-count recovery'),
];
$recovered = [
    1 => $formatPage('next219 current schema after page-count recovery'),
    2 => $page('next219 current wp_options root after page-count recovery'),
    3 => $page('next219 current active_plugins after page-count recovery'),
    4 => $page('next219 current usermeta after page-count recovery'),
    5 => $page('next219 current cron after page-count recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 219, 0x57503239]));
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
    $mainJournal => 'dev=8:ino=2191:size=4096:mtime=21901:generation=main-current',
    $usersJournal => 'dev=8:ino=2192:size=1024:mtime=21902:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-219'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-219'),
];
$oldMemberHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-219'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 219,
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
    'database_file_token' => $currentDatabaseToken,
    'database_header_digest' => $currentHeaderDigest,
    'database_page_count' => $currentPageCount,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-page-count', $recovered[1]),
    2 => $cacheEntry('root-refreshed-page-count', $before[2]),
    3 => $cacheEntry('active-stale-page-count', $recovered[3], ['database_page_count' => $oldPageCount]),
    4 => $cacheEntry('usermeta-stale-header', $recovered[4], ['database_header_digest' => $oldHeaderDigest]),
    5 => $cacheEntry('cron-stale-member-header', $recovered[5], ['member_journal_header_digests' => $oldMemberHeaders]),
    6 => $cacheEntry('comments-truncated-page-count', $before[6], ['database_page_count' => $oldPageCount]),
];
$reads = static fn (int $pageCount = null, string $headerDigest = null, string $databaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 219,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentMemberHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
        'database_file_token' => $databaseToken ?? $currentDatabaseToken,
        'database_header_digest' => $headerDigest ?? $currentHeaderDigest,
        'database_page_count' => $pageCount ?? $currentPageCount,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $pageCount = null,
    ?string $databaseHeader = null,
    ?string $databaseToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext219(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    219,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $databaseToken ?? $currentDatabaseToken,
    $databaseHeader ?? $currentHeaderDigest,
    $pageCount ?? $currentPageCount,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next219'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_page_count_before_current_source_reuse'],
    'current page count' => [static fn (): mixed => $plan()['current_database_page_count'], $currentPageCount],
    'inherits header digest' => [static fn (): mixed => $plan()['current_database_header_digest'], $currentHeaderDigest],
    'inherits database token' => [static fn (): mixed => $plan()['current_database_file_token'], $currentDatabaseToken],
    'page-count invalidated pages' => [static fn (): mixed => $plan()['database_page_count_invalidated_cache_page_numbers'], [3, 6]],
    'truncated pages' => [static fn (): mixed => $plan()['database_page_count_truncated_cache_page_numbers'], [6]],
    'all invalidated pages include inherited fences' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale page count' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit truncated page' => [static fn (): mixed => $plan()['read_cache_hits']['read-6'], false],
    'page count invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_database_page_count_after_current_source_next219'), 2],
    'dependency next219' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next219', $plan()['dependencies'], true), true],
    'dependency page count fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-page-count-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next217' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next217 database header admission'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-page-count')['database_page_count_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-page-count')['database_page_count_reason'], 'reader_cache_database_page_count_matches_current_source'],
    'row retained cache count' => [static fn (): mixed => $row('schema-retained-page-count')['cache_database_page_count'], $currentPageCount],
    'row retained current count' => [static fn (): mixed => $row('schema-retained-page-count')['current_database_page_count'], $currentPageCount],
    'row retained count matches' => [static fn (): mixed => $row('schema-retained-page-count')['database_page_count_matches'], true],
    'row retained within count' => [static fn (): mixed => $row('schema-retained-page-count')['page_number_within_current_page_count'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-page-count')['database_page_count_admitted'], true],
    'row refreshed reason' => [static fn (): mixed => $row('root-refreshed-page-count')['database_page_count_reason'], 'reader_cache_database_page_count_matches_current_source'],
    'row stale count admitted false' => [static fn (): mixed => $row('active-stale-page-count')['database_page_count_admitted'], false],
    'row stale count reason' => [static fn (): mixed => $row('active-stale-page-count')['database_page_count_reason'], 'reader_cache_database_page_count_changed_after_master_journal_recovery'],
    'row stale count cache count' => [static fn (): mixed => $row('active-stale-page-count')['cache_database_page_count'], $oldPageCount],
    'row stale count current count' => [static fn (): mixed => $row('active-stale-page-count')['current_database_page_count'], $currentPageCount],
    'row stale count mismatch' => [static fn (): mixed => $row('active-stale-page-count')['database_page_count_matches'], false],
    'row stale count within current count' => [static fn (): mixed => $row('active-stale-page-count')['page_number_within_current_page_count'], true],
    'row truncated admitted false' => [static fn (): mixed => $row('comments-truncated-page-count')['database_page_count_admitted'], false],
    'row truncated reason' => [static fn (): mixed => $row('comments-truncated-page-count')['database_page_count_reason'], 'reader_cache_page_number_exceeds_current_database_page_count'],
    'row truncated within current count false' => [static fn (): mixed => $row('comments-truncated-page-count')['page_number_within_current_page_count'], false],
    'row stale header inherits reason' => [static fn (): mixed => $row('usermeta-stale-header')['database_page_count_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'row stale member header inherits reason' => [static fn (): mixed => $row('cron-stale-member-header')['database_page_count_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'read retained page count current' => [static fn (): mixed => $read('read-1')['database_page_count_current'], true],
    'read retained page count value' => [static fn (): mixed => $read('read-1')['database_page_count'], $currentPageCount],
    'read retained within current count' => [static fn (): mixed => $read('read-1')['page_number_within_current_page_count'], true],
    'read stale page count source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-database-page-count-fence-current-source-next219'],
    'read stale page count reason' => [static fn (): mixed => $read('read-3')['database_page_count_reason'], 'reader_cache_reopened_after_database_page_count_change'],
    'read truncated page reason' => [static fn (): mixed => $read('read-6')['database_page_count_reason'], 'reader_page_number_exceeds_current_database_page_count'],
    'read truncated page within false' => [static fn (): mixed => $read('read-6')['page_number_within_current_page_count'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldPageCount))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldPageCount))['next_reads'][0]['database_page_count_reason'], 'reader_ticket_database_page_count_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldPageCount))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'current read ticket keeps first hit' => [static fn (): mixed => $plan(null, $reads($currentPageCount))['read_cache_hits']['read-1'], true],
    'changed current page count invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $expandedPageCount)['database_page_count_invalidated_cache_page_numbers'], [1, 2, 3, 6]],
    'changed current page count has no truncation for old larger file' => [static fn (): mixed => $plan(null, null, $expandedPageCount)['database_page_count_truncated_cache_page_numbers'], []],
    'changed current page count surfaces current value' => [static fn (): mixed => $plan(null, null, $expandedPageCount)['current_database_page_count'], $expandedPageCount],
    'changed header still inherited' => [static fn (): mixed => $plan(null, null, null, hash('sha256', 'new-current-header'))['database_header_invalidated_cache_page_numbers'], [1, 2, 3, 4, 6]],
    'changed database token still inherited' => [static fn (): mixed => $plan(null, null, null, null, $oldDatabaseToken)['database_file_token_invalidated_cache_page_numbers'], [1, 2, 3, 4, 6]],
    'all fresh cache no page-count invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_page_count_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'old page count fixture differs' => [static fn (): mixed => $oldPageCount !== $currentPageCount, true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next219 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'zero current page count rejected' => static fn () => $plan(null, null, 0),
    'negative current page count rejected' => static fn () => $plan(null, null, -1),
    'missing cache page count rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_page_count' => true])]),
    'zero cache page count rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['database_page_count' => 0])]),
    'string cache page count rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['database_page_count' => '5'])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'missing read page count rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_page_count' => true])]),
    'zero read page count rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['database_page_count' => 0])]),
    'empty read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'inherits next217 missing header rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_header_digest' => true])]),
    'inherits next217 missing read header rejection' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_header_digest' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next219 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
