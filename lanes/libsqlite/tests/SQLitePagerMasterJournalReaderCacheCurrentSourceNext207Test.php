<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next207.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next207-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-database-file-token-next207';
$publication = 207;
$masterDigest = hash('sha256', 'next207-master-source');
$recoverySequence = 207;
$members = [$mainJournal, $usersJournal];
$masterBytes = implode("\n", $members) . "\n";
$currentMasterToken = 'dev=8:ino=2070:size=64:mtime=20700:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2073:size=3584:mtime=20703:generation=database-current';
$oldDatabaseToken = 'dev=8:ino=2073:size=3584:mtime=20702:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 207), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503237), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next207 stale schema before database token recovery'),
    2 => $page('next207 stale wp_options root before database token recovery'),
    3 => $page('next207 stale active_plugins before database token recovery'),
    4 => $page('next207 stale usermeta before database token recovery'),
    5 => $page('next207 stale rewrite_rules before database token recovery'),
    6 => $page('next207 stale cron before database token recovery'),
    7 => $page('next207 stale comments before database token recovery'),
];
$recovered = [
    1 => $formatPage('next207 current schema after database token recovery'),
    2 => $page('next207 current wp_options root after database token recovery'),
    3 => $page('next207 current active_plugins after database token recovery'),
    4 => $page('next207 current usermeta after database token recovery'),
    6 => $page('next207 current cron after database token recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 207, 0x57503237]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 206, 0x57503236]));
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
    $mainJournal => 'dev=8:ino=2071:size=4096:mtime=20701:generation=main-current',
    $usersJournal => 'dev=8:ino=2072:size=1024:mtime=20702:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-207'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-207'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-207'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$oldHeaderDigest = $mapDigest($oldHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 207,
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
    'database_file_token' => $currentDatabaseToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-database-token', $recovered[1]),
    2 => $cacheEntry('root-refreshed-database-token', $before[2]),
    3 => $cacheEntry('active-stale-database-token', $recovered[3], ['database_file_token' => $oldDatabaseToken]),
    4 => $cacheEntry('usermeta-stale-header', $recovered[4], ['member_journal_header_digests' => $oldHeaders]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-pinned-database-token', $before[6], ['pinned' => true]),
    7 => $cacheEntry('comments-dirty-database-token', $before[7], ['dirty' => true]),
];
$reads = static fn (?string $databaseToken = null, ?string $header = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 207,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $header ?? $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'database_file_token' => $databaseToken ?? $currentDatabaseToken,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $databaseToken = null,
    ?array $headers = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext207(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    207,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $headers ?? $currentHeaders,
    $currentMasterToken,
    $databaseToken ?? $currentDatabaseToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next207'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_file_token_before_current_source_reuse'],
    'current database file token' => [static fn (): mixed => $plan()['current_database_file_token'], $currentDatabaseToken],
    'inherits master file token' => [static fn (): mixed => $plan()['current_master_journal_file_token'], $currentMasterToken],
    'inherits order digest' => [static fn (): mixed => $plan()['current_master_member_order_digest'], $orderDigest],
    'inherits header digest' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'database token invalidated page' => [static fn (): mixed => $plan()['database_file_token_invalidated_cache_page_numbers'], [3]],
    'header invalidated page preserved' => [static fn (): mixed => $plan()['member_header_invalidated_cache_page_numbers'], [4]],
    'master token invalidated empty' => [static fn (): mixed => $plan()['master_journal_file_token_invalidated_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema database token admitted' => [static fn (): mixed => $row('schema-retained-database-token')['database_file_token_admitted'], true],
    'schema database token reason' => [static fn (): mixed => $row('schema-retained-database-token')['database_file_token_reason'], 'reader_cache_database_file_token_matches_current_source'],
    'root refreshed database token admitted' => [static fn (): mixed => $row('root-refreshed-database-token')['database_file_token_admitted'], true],
    'stale database token rejected' => [static fn (): mixed => $row('active-stale-database-token')['database_file_token_admitted'], false],
    'stale database token reason' => [static fn (): mixed => $row('active-stale-database-token')['database_file_token_reason'], 'reader_cache_database_file_token_changed_after_master_recovery'],
    'stale database cache token' => [static fn (): mixed => $row('active-stale-database-token')['cache_database_file_token'], $oldDatabaseToken],
    'stale database current token' => [static fn (): mixed => $row('active-stale-database-token')['current_database_file_token'], $currentDatabaseToken],
    'database token mismatch flag' => [static fn (): mixed => $row('active-stale-database-token')['database_file_token_matches'], false],
    'stale row master token still matches' => [static fn (): mixed => $row('active-stale-database-token')['master_journal_file_token_matches'], true],
    'stale row member headers still match' => [static fn (): mixed => $row('active-stale-database-token')['cache_member_journal_header_digest'], $currentHeaderDigest],
    'header reason preserved' => [static fn (): mixed => $row('usermeta-stale-header')['database_file_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'format reason preserved' => [static fn (): mixed => $row('rewrite-stale-format')['database_file_token_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'pinned reason preserved' => [static fn (): mixed => $row('cron-pinned-database-token')['database_file_token_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'dirty reason preserved' => [static fn (): mixed => $row('comments-dirty-database-token')['database_file_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read database token miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read stale header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read database token current true' => [static fn (): mixed => $plan()['next_reads'][0]['database_file_token_current'], true],
    'read database token source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-database-file-token-fence-current-source-next207'],
    'read database token reason' => [static fn (): mixed => $plan()['next_reads'][2]['database_file_token_reason'], 'reader_cache_reopened_after_database_file_token_change'],
    'read stale database ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['read_cache_hits']['read-1'], false],
    'read stale database ticket reason' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['next_reads'][0]['database_file_token_reason'], 'reader_ticket_database_file_token_predates_current_source'],
    'read stale header still uses header reason' => [static fn (): mixed => $plan(null, $reads($currentDatabaseToken, $oldHeaderDigest))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation appended' => [static fn (): mixed => in_array('invalidate_reader_cache_database_file_token_after_current_source_next207', array_column($plan()['operations'], 'op'), true), true],
    'operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_database_file_token_after_current_source_next207'), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next207', $plan()['dependencies'], true), true],
    'dependency token marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-file-token-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next206', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next206'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'same member maps database token differs' => [static fn (): mixed => $row('active-stale-database-token')['cache_member_journal_header_digest'], $currentHeaderDigest],
    'same member order database token differs' => [static fn (): mixed => $row('active-stale-database-token')['master_member_order_digest_matches'], true],
    'all current single read hits' => [static fn (): mixed => $plan([
        1 => $cacheEntry('schema-retained-database-token', $recovered[1]),
    ], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 207,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'database_file_token' => $currentDatabaseToken,
    ]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next207 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current database token rejected' => static fn () => $plan(null, null, ''),
    'missing cache database token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1]), ['database_file_token' => true])], [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 207,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'database_file_token' => $currentDatabaseToken,
    ]]),
    'empty cache database token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['database_file_token' => ''])]),
    'missing read database token rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 207,
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
    'empty read database token rejected' => static fn () => $plan(null, [[
        'reader_id' => 'read-1',
        'page_number' => 1,
        'source_id' => $sourceId,
        'epoch' => 207,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'database_file_token' => '',
    ]]),
    'missing current header still rejected by base' => static fn () => $plan(null, null, null, [$mainJournal => $currentHeaders[$mainJournal]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next207 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
