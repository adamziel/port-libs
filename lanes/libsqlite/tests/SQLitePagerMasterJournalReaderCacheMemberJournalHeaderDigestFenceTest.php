<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next196.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next196-users.sqlite';
$master = '/srv/wp-content/database/wp-next196.sqlite-mj';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$sourceId = 'pager-reader-cache-member-header-next196';
$publication = 196;
$masterDigest = hash('sha256', 'next196-current-master-source');
$recoverySequence = 81;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label, int $reserved, int $encoding, int $userVersion, int $applicationId) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr($reserved), 20, 1);
    $page = substr_replace($page, pack('N', $encoding), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next196 stale schema before member header recovery', 0, 1, 31, 0x57503136),
    2 => $page('next196 stale wp_options root before member header recovery'),
    3 => $page('next196 stale active_plugins before member header recovery'),
    4 => $page('next196 stale users table before member header recovery'),
    5 => $page('next196 unchanged rewrite rules before member header recovery'),
    6 => $page('next196 stale cron before member header recovery'),
    7 => $page('next196 unchanged comments cache before member header recovery'),
    8 => $page('next196 stale autoload index before member header recovery'),
];
$recovered = [
    1 => $formatPage('next196 current schema after member header recovery', 4, 2, 32, 0x57503137),
    2 => $page('next196 recovered wp_options root after member header recovery'),
    3 => $page('next196 recovered active_plugins after member header recovery'),
    4 => $page('next196 recovered users table after member header recovery'),
    6 => $page('next196 recovered cron after member header recovery'),
    8 => $page('next196 recovered autoload index after member header recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 32, 0x57503137]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 31, 0x57503136]));
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $number => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad page fixture');
        }
        $parts[] = $number . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokens = [
    $mainJournal => 'dev=8:ino=196:size=4096:mtime=9600:generation=main-current',
    $usersJournal => 'dev=8:ino=197:size=1024:mtime=9601:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-salt-196'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-salt-196'),
];
$oldUsersHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-prior-rollback-header-salt-195'),
];
$oldMainHeaders = [
    $mainJournal => hash('sha256', 'main-prior-rollback-header-salt-195'),
    $usersJournal => $currentHeaders[$usersJournal],
];
$oldUsersTokens = [
    $mainJournal => $currentTokens[$mainJournal],
    $usersJournal => 'dev=8:ino=197:size=1024:mtime=9500:generation=users-prior',
];
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$oldHeaderDigest = $mapDigest($oldUsersHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 196,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-member-header', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed-member-header', $before[2]),
    3 => $cacheEntry('active-stale-users-header', $recovered[3], ['member_journal_header_digests' => $oldUsersHeaders]),
    4 => $cacheEntry('users-stale-main-header', $recovered[4], ['member_journal_header_digests' => $oldMainHeaders]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-token', $recovered[6], ['member_journal_tokens' => $oldUsersTokens]),
    7 => $cacheEntry('comments-dirty-header', $before[7], ['dirty' => true]),
    8 => $cacheEntry('autoload-pinned-header', $before[8], ['pinned' => true]),
];
$reads = static fn (string $headerDigest = null, string $tokenDigest = null, string $source = null, int $epoch = 196): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $source ?? $sourceId,
        'epoch' => $epoch,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest ?? $currentTokenDigest,
        'member_journal_header_digest' => $headerDigest ?? $currentHeaderDigest,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $tokens = null,
    ?array $headers = null,
    ?array $recoveredPages = null,
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::memberJournalHeaderDigestFence(
    $database,
    $master,
    $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    196,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens ?? $currentTokens,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next196'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_attached_member_journal_headers_before_current_source_reuse'],
    'current header digest' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'current header main' => [static fn (): mixed => $plan()['current_member_journal_header_digests'][$mainJournal], $currentHeaders[$mainJournal]],
    'current header users' => [static fn (): mixed => $plan()['current_member_journal_header_digests'][$usersJournal], $currentHeaders[$usersJournal]],
    'current token digest retained' => [static fn (): mixed => $plan()['current_member_journal_token_digest'], $currentTokenDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'header invalidated pages' => [static fn (): mixed => $plan()['member_header_invalidated_cache_page_numbers'], [3, 4]],
    'token invalidated page preserved' => [static fn (): mixed => $plan()['member_token_invalidated_cache_page_numbers'], [6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema row admitted' => [static fn (): mixed => $row('schema-retained-member-header')['member_header_admitted'], true],
    'schema row reason' => [static fn (): mixed => $row('schema-retained-member-header')['member_header_reason'], 'reader_cache_attached_member_journal_headers_match_current_source'],
    'root row admitted' => [static fn (): mixed => $row('root-refreshed-member-header')['member_header_admitted'], true],
    'users header row rejected' => [static fn (): mixed => $row('active-stale-users-header')['member_header_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'main header row rejected' => [static fn (): mixed => $row('users-stale-main-header')['member_header_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'stale format base reason' => [static fn (): mixed => $row('rewrite-stale-format')['member_header_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'stale token base reason' => [static fn (): mixed => $row('cron-stale-token')['member_header_reason'], 'reader_cache_attached_member_journal_token_changed'],
    'dirty base reason' => [static fn (): mixed => $row('comments-dirty-header')['member_header_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'pinned base reason' => [static fn (): mixed => $row('autoload-pinned-header')['member_header_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'users mismatched header' => [static fn (): mixed => $row('active-stale-users-header')['mismatched_member_journal_headers'], [$usersJournal]],
    'main mismatched header' => [static fn (): mixed => $row('users-stale-main-header')['mismatched_member_journal_headers'], [$mainJournal]],
    'cache header digest differs' => [static fn (): mixed => $row('active-stale-users-header')['cache_member_journal_header_digest'], $oldHeaderDigest],
    'current header digest on row' => [static fn (): mixed => $row('active-stale-users-header')['current_member_journal_header_digest'], $currentHeaderDigest],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read users header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read main header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read header current' => [static fn (): mixed => $plan()['next_reads'][0]['member_journal_header_current'], true],
    'read stale header source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-member-header-fence-current-source-next196'],
    'read stale header reason' => [static fn (): mixed => $plan()['next_reads'][2]['member_header_reason'], 'reader_cache_reopened_after_attached_member_journal_header_change'],
    'read stale ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['read_cache_hits']['read-1'], false],
    'read stale ticket reason' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'read token stale still uses token reason' => [static fn (): mixed => $plan(null, $reads($currentHeaderDigest, $mapDigest($oldUsersTokens)))['next_reads'][0]['member_token_reason'], 'reader_ticket_attached_member_journal_token_predates_current_source'],
    'read root prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next196 recovered wp_options root after member header recovery'],
    'read active prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next196 recovered active_plugins after member header recovery'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'operation invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_attached_member_header_after_master_current_source_next196', array_column($plan()['operations'], 'op'), true), true],
    'operation invalidate count' => [static fn (): mixed => $opCount('invalidate_reader_cache_attached_member_header_after_master_current_source_next196'), 2],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next196', $plan()['dependencies'], true), true],
    'dependency header fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-attached-member-journal-header-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next192', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next192'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'single admitted cache has no header invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-header', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest]])['member_header_invalidated_cache_page_numbers'], []],
    'all current single read hits' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-header', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next196 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing current header rejected' => static fn () => $plan(null, null, null, [$mainJournal => $currentHeaders[$mainJournal]]),
    'empty current header rejected' => static fn () => $plan(null, null, null, [$mainJournal => $currentHeaders[$mainJournal], $usersJournal => '']),
    'missing cache headers rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_header_digests' => null])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest]]),
    'missing cache member header rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_header_digests' => [$mainJournal => $currentHeaders[$mainJournal]]])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest]]),
    'empty read header rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => '']]),
    'empty read token rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => '', 'member_journal_header_digest' => $currentHeaderDigest]]),
    'base missing token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_tokens' => [$mainJournal => $currentTokens[$mainJournal]]])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 196, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest]]),
    'base bad recovery sequence rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::memberJournalHeaderDigestFence($database, $master, $masterBytes, $databaseBytes, $pageSize, $recovered, $cache(), $reads(), $sourceId, 196, $publication, $masterDigest, 0, $currentTokens, $currentHeaders),
    'base unaligned database rejected' => static fn () => $plan(null, null, null, null, null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next196 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
