<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next202.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next202-users.sqlite';
$master = '/srv/wp-content/database/wp-next202.sqlite-mj';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$sourceId = 'pager-reader-cache-member-playback-next202';
$publication = 202;
$masterDigest = hash('sha256', 'next202-current-master-source');
$recoverySequence = 96;
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
    1 => $formatPage('next202 stale schema before playback recovery', 0, 1, 41, 0x57503231),
    2 => $page('next202 stale wp_options root before playback recovery'),
    3 => $page('next202 stale active_plugins before playback recovery'),
    4 => $page('next202 stale users table before playback recovery'),
    5 => $page('next202 unchanged rewrite rules before playback recovery'),
    6 => $page('next202 stale cron before playback recovery'),
    7 => $page('next202 unchanged comments cache before playback recovery'),
    8 => $page('next202 stale autoload index before playback recovery'),
];
$recovered = [
    1 => $formatPage('next202 current schema after playback recovery', 4, 2, 42, 0x57503232),
    2 => $page('next202 recovered wp_options root after playback recovery'),
    3 => $page('next202 recovered active_plugins after playback recovery'),
    4 => $page('next202 recovered users table after playback recovery'),
    6 => $page('next202 recovered cron after playback recovery'),
    8 => $page('next202 recovered autoload index after playback recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 42, 0x57503232]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 41, 0x57503231]));
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
    $mainJournal => 'dev=8:ino=202:size=4096:mtime=20200:generation=main-current',
    $usersJournal => 'dev=8:ino=203:size=2048:mtime=20201:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-salt-202'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-salt-202'),
];
$currentPlayback = [
    $mainJournal => hash('sha256', 'main-current-playback-pages-1-2-3-next202'),
    $usersJournal => hash('sha256', 'users-current-playback-pages-4-6-next202'),
];
$oldUsersPlayback = [
    $mainJournal => $currentPlayback[$mainJournal],
    $usersJournal => hash('sha256', 'users-prior-playback-pages-4-only-next201'),
];
$oldMainPlayback = [
    $mainJournal => hash('sha256', 'main-prior-playback-pages-1-2-only-next201'),
    $usersJournal => $currentPlayback[$usersJournal],
];
$oldUsersHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-prior-rollback-header-salt-201'),
];
$oldUsersTokens = [
    $mainJournal => $currentTokens[$mainJournal],
    $usersJournal => 'dev=8:ino=203:size=2048:mtime=20100:generation=users-prior',
];
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$currentPlaybackDigest = $mapDigest($currentPlayback);
$oldPlaybackDigest = $mapDigest($oldUsersPlayback);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 202,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
    'member_journal_playback_digests' => $currentPlayback,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-member-playback', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed-member-playback', $before[2]),
    3 => $cacheEntry('active-stale-users-playback', $recovered[3], ['member_journal_playback_digests' => $oldUsersPlayback]),
    4 => $cacheEntry('users-stale-main-playback', $recovered[4], ['member_journal_playback_digests' => $oldMainPlayback]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-header', $recovered[6], ['member_journal_header_digests' => $oldUsersHeaders]),
    7 => $cacheEntry('comments-stale-token', $before[7], ['member_journal_tokens' => $oldUsersTokens]),
    8 => $cacheEntry('autoload-pinned-playback', $before[8], ['pinned' => true]),
];
$reads = static fn (string $playbackDigest = null, string $headerDigest = null, string $tokenDigest = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 202,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest ?? $currentTokenDigest,
        'member_journal_header_digest' => $headerDigest ?? $currentHeaderDigest,
        'member_journal_playback_digest' => $playbackDigest ?? $currentPlaybackDigest,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $tokens = null,
    ?array $headers = null,
    ?array $playback = null,
    ?array $recoveredPages = null,
    ?string $bytes = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPlaybackDigestFence(
    $database,
    $master,
    $masterBytes,
    $bytes ?? $databaseBytes,
    $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    202,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens ?? $currentTokens,
    $headers ?? $currentHeaders,
    $playback ?? $currentPlayback,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next202'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_attached_member_journal_playback_before_current_source_reuse'],
    'current playback digest' => [static fn (): mixed => $plan()['current_member_journal_playback_digest'], $currentPlaybackDigest],
    'current playback main' => [static fn (): mixed => $plan()['current_member_journal_playback_digests'][$mainJournal], $currentPlayback[$mainJournal]],
    'current playback users' => [static fn (): mixed => $plan()['current_member_journal_playback_digests'][$usersJournal], $currentPlayback[$usersJournal]],
    'current header digest retained' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'current token digest retained' => [static fn (): mixed => $plan()['current_member_journal_token_digest'], $currentTokenDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'playback invalidated pages' => [static fn (): mixed => $plan()['member_playback_invalidated_cache_page_numbers'], [3, 4]],
    'header invalidated page preserved' => [static fn (): mixed => $plan()['member_header_invalidated_cache_page_numbers'], [6]],
    'token invalidated page preserved' => [static fn (): mixed => $plan()['member_token_invalidated_cache_page_numbers'], [7]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema row admitted' => [static fn (): mixed => $row('schema-retained-member-playback')['member_playback_admitted'], true],
    'schema row reason' => [static fn (): mixed => $row('schema-retained-member-playback')['member_playback_reason'], 'reader_cache_attached_member_journal_playback_matches_current_source'],
    'root row admitted' => [static fn (): mixed => $row('root-refreshed-member-playback')['member_playback_admitted'], true],
    'users playback row rejected' => [static fn (): mixed => $row('active-stale-users-playback')['member_playback_reason'], 'reader_cache_attached_member_journal_playback_changed'],
    'main playback row rejected' => [static fn (): mixed => $row('users-stale-main-playback')['member_playback_reason'], 'reader_cache_attached_member_journal_playback_changed'],
    'stale format base reason' => [static fn (): mixed => $row('rewrite-stale-format')['member_playback_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'stale header base reason' => [static fn (): mixed => $row('cron-stale-header')['member_playback_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'stale token base reason' => [static fn (): mixed => $row('comments-stale-token')['member_playback_reason'], 'reader_cache_attached_member_journal_token_changed'],
    'pinned base reason' => [static fn (): mixed => $row('autoload-pinned-playback')['member_playback_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'users mismatched playback' => [static fn (): mixed => $row('active-stale-users-playback')['mismatched_member_journal_playbacks'], [$usersJournal]],
    'main mismatched playback' => [static fn (): mixed => $row('users-stale-main-playback')['mismatched_member_journal_playbacks'], [$mainJournal]],
    'cache playback digest differs' => [static fn (): mixed => $row('active-stale-users-playback')['cache_member_journal_playback_digest'], $oldPlaybackDigest],
    'current playback digest on row' => [static fn (): mixed => $row('active-stale-users-playback')['current_member_journal_playback_digest'], $currentPlaybackDigest],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read users playback miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read main playback miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read playback current' => [static fn (): mixed => $plan()['next_reads'][0]['member_journal_playback_current'], true],
    'read stale playback source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-member-playback-fence-current-source-next202'],
    'read stale playback reason' => [static fn (): mixed => $plan()['next_reads'][2]['member_playback_reason'], 'reader_cache_reopened_after_attached_member_journal_playback_change'],
    'read stale ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldPlaybackDigest))['read_cache_hits']['read-1'], false],
    'read stale ticket reason' => [static fn (): mixed => $plan(null, $reads($oldPlaybackDigest))['next_reads'][0]['member_playback_reason'], 'reader_ticket_attached_member_journal_playback_predates_current_source'],
    'read stale header keeps header reason' => [static fn (): mixed => $plan(null, $reads($currentPlaybackDigest, $mapDigest($oldUsersHeaders)))['next_reads'][0]['member_header_reason'], 'reader_ticket_attached_member_journal_header_predates_current_source'],
    'read stale token keeps token reason' => [static fn (): mixed => $plan(null, $reads($currentPlaybackDigest, $currentHeaderDigest, $mapDigest($oldUsersTokens)))['next_reads'][0]['member_token_reason'], 'reader_ticket_attached_member_journal_token_predates_current_source'],
    'read root prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next202 recovered wp_options root after playback recovery'],
    'read active prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next202 recovered active_plugins after playback recovery'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'operation invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_attached_member_playback_after_master_current_source_next202', array_column($plan()['operations'], 'op'), true), true],
    'operation invalidate count' => [static fn (): mixed => $opCount('invalidate_reader_cache_attached_member_playback_after_master_current_source_next202'), 2],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next202', $plan()['dependencies'], true), true],
    'dependency playback fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-attached-member-journal-playback-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next196', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next196'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'single admitted cache has no playback invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-playback', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => $currentPlaybackDigest]])['member_playback_invalidated_cache_page_numbers'], []],
    'all current single read hits' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-playback', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => $currentPlaybackDigest]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next202 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing current playback rejected' => static fn () => $plan(null, null, null, null, [$mainJournal => $currentPlayback[$mainJournal]]),
    'empty current playback rejected' => static fn () => $plan(null, null, null, null, [$mainJournal => $currentPlayback[$mainJournal], $usersJournal => '']),
    'missing cache playback rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_playback_digests' => null])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => $currentPlaybackDigest]]),
    'missing cache member playback rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_playback_digests' => [$mainJournal => $currentPlayback[$mainJournal]]])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => $currentPlaybackDigest]]),
    'empty read playback rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => '']]),
    'empty read header rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => '', 'member_journal_playback_digest' => $currentPlaybackDigest]]),
    'empty read token rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => '', 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => $currentPlaybackDigest]]),
    'base missing header rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_header_digests' => [$mainJournal => $currentHeaders[$mainJournal]]])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 202, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest, 'member_journal_header_digest' => $currentHeaderDigest, 'member_journal_playback_digest' => $currentPlaybackDigest]]),
    'base bad recovery sequence rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPlaybackDigestFence($database, $master, $masterBytes, $databaseBytes, $pageSize, $recovered, $cache(), $reads(), $sourceId, 202, $publication, $masterDigest, 0, $currentTokens, $currentHeaders, $currentPlayback),
    'base unaligned database rejected' => static fn () => $plan(null, null, null, null, null, null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next202 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
