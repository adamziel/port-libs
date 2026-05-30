<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next193.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next193-users.sqlite';
$master = '/srv/wp-content/database/wp-next193.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterBytes = $journal . "\n" . $usersJournal . "\n";
$masterByteDigest = hash('sha256', $masterBytes);
$stableToken = 'stable-master-read:' . substr(hash('sha256', $master . '|' . $masterByteDigest . '|2'), 0, 40);
$oldStableToken = 'stable-master-read:' . substr(hash('sha256', $master . '|' . hash('sha256', 'old-master-read') . '|2'), 0, 40);
$sourceId = 'pager-reader-cache-stable-read-next193';
$publication = 193;
$masterDigest = hash('sha256', 'next193-current-master-source');
$recoverySequence = 93;
$memberDigests = [
    $journal => hash('sha256', 'next193-main-rollback-journal'),
    $usersJournal => hash('sha256', 'next193-users-rollback-journal'),
];
$oldMemberDigest = hash('sha256', 'next193-old-member');
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
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 23, 0x57505033]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 22, 0x57505032]));
$before = [
    1 => $formatPage('next193 old wp header before stable read', 0, 1, 22, 0x57505032),
    2 => $page('next193 stale wp_options root before stable read'),
    3 => $page('next193 stale active_plugins before stable read'),
    4 => $page('next193 stale users before stable read'),
    5 => $page('next193 stale usermeta before stable read'),
    6 => $page('next193 stale cron before stable read'),
    7 => $page('next193 unchanged rewrite rules before stable read'),
    8 => $page('next193 stale plugin settings before stable read'),
];
$recovered = [
    1 => $formatPage('next193 current wp header after stable read', 4, 2, 23, 0x57505033),
    2 => $page('next193 recovered wp_options root after stable read'),
    3 => $page('next193 recovered active_plugins after stable read'),
    4 => $page('next193 recovered users after stable read'),
    5 => $page('next193 recovered usermeta after stable read'),
    6 => $page('next193 recovered cron after stable read'),
];
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
$currentRecoveredDigest = $recoveredDigest($recovered);
$oldRecoveredDigest = hash('sha256', 'next193-old-recovered-page-set');
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, string $memberPath, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 193,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_path' => $memberPath,
    'member_journal_digest' => $memberDigests[$memberPath],
    'stable_master_read_token' => $stableToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-stable-read', $recovered[1], $journal, ['shared' => true]),
    2 => $cacheEntry('options-refreshed-stable-read', $before[2], $journal),
    3 => $cacheEntry('active-old-stable-read', $recovered[3], $journal, ['stable_master_read_token' => $oldStableToken]),
    4 => $cacheEntry('users-retained-stable-read', $recovered[4], $usersJournal),
    5 => $cacheEntry('usermeta-old-member-digest', $recovered[5], $usersJournal, ['member_journal_digest' => $oldMemberDigest]),
    6 => $cacheEntry('cron-stale-recovery-set', $before[6], $journal, ['recovered_page_set_digest' => $oldRecoveredDigest]),
    7 => $cacheEntry('rewrite-dirty-stable-read', $before[7], $journal, ['dirty' => true]),
    8 => $cacheEntry('settings-stale-format', $recovered[6], $journal, ['format_signature' => $oldFormatSignature]),
];
$reads = static fn (?string $token = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 193,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_path' => in_array($pageNumber, [4, 5], true) ? $usersJournal : $journal,
        'member_journal_digest' => $memberDigests[in_array($pageNumber, [4, 5], true) ? $usersJournal : $journal],
        'stable_master_read_token' => $token ?? $stableToken,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $readDigests = null,
    ?array $memberDigestMap = null,
    ?array $recoveredPages = null,
    ?string $bytes = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::stableMasterReadTokenFence(
    $database,
    $master,
    $masterBytes,
    $bytes ?? $databaseBytes,
    $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    193,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberDigestMap ?? $memberDigests,
    $readDigests ?? [$masterByteDigest, $masterByteDigest],
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next193'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'stable_repeated_master_journal_read_token_fences_reader_cache_reuse'],
    'stable read token' => [static fn (): mixed => $plan()['stable_master_read']['token'], $stableToken],
    'stable read digest' => [static fn (): mixed => $plan()['stable_master_read']['digest'], $masterByteDigest],
    'stable read count' => [static fn (): mixed => $plan()['stable_master_read']['read_count'], 2],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 4]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 5, 6, 7, 8]],
    'stable invalidated pages' => [static fn (): mixed => $plan()['stable_master_read_invalidated_cache_page_numbers'], [3]],
    'member invalidated still present' => [static fn (): mixed => $plan()['member_journal_invalidated_cache_page_numbers'], [5]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row schema stable admitted' => [static fn (): mixed => $row('schema-retained-stable-read')['stable_master_read_admitted'], true],
    'row schema stable reason' => [static fn (): mixed => $row('schema-retained-stable-read')['stable_master_read_reason'], 'reader_cache_stable_master_read_token_matches_current_source'],
    'row options refreshed stable admitted' => [static fn (): mixed => $row('options-refreshed-stable-read')['stable_master_read_admitted'], true],
    'row active old stable rejected' => [static fn (): mixed => $row('active-old-stable-read')['stable_master_read_reason'], 'reader_cache_stable_master_read_token_predates_current_source'],
    'row usermeta carries member reason' => [static fn (): mixed => $row('usermeta-old-member-digest')['stable_master_read_reason'], 'reader_cache_member_journal_digest_predates_current_source'],
    'row cron carries recovery reason' => [static fn (): mixed => $row('cron-stale-recovery-set')['stable_master_read_reason'], 'reader_cache_recovered_page_set_digest_predates_current_source'],
    'row dirty carries base reason' => [static fn (): mixed => $row('rewrite-dirty-stable-read')['stable_master_read_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'row format carries base reason' => [static fn (): mixed => $row('settings-stale-format')['stable_master_read_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row stable token mismatch flag' => [static fn (): mixed => $row('active-old-stable-read')['stable_master_read_token_matches'], false],
    'row stable token current exposed' => [static fn (): mixed => $row('active-old-stable-read')['current_stable_master_read_token'], $stableToken],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read schema hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read options hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read active miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read users hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], true],
    'read active source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-stable-read-fence-current-source-next193'],
    'read active stable current' => [static fn (): mixed => $plan()['next_reads'][2]['stable_master_read_current'], true],
    'read active stable reason' => [static fn (): mixed => $plan()['next_reads'][2]['stable_master_read_reason'], 'reader_cache_reopened_after_stable_master_read_token_change'],
    'read schema stable token exposed' => [static fn (): mixed => $plan()['next_reads'][0]['stable_master_read_token'], $stableToken],
    'read options prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next193 recovered wp_options root after stable read'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-5', 'read-6', 'read-7', 'read-8']],
    'operation stable invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_stable_master_read_after_current_source_next193', array_column($plan()['operations'], 'op'), true), true],
    'operation stable invalidate count' => [static fn (): mixed => count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_stable_master_read_after_current_source_next193')), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next193', $plan()['dependencies'], true), true],
    'dependency stable fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-stable-master-journal-read-token', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next189', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next189'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'stale read ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldStableToken))['read_cache_hits']['read-1'], false],
    'changed current digest changes token' => [static fn (): mixed => $plan(null, null, [hash('sha256', $masterBytes), hash('sha256', $masterBytes), hash('sha256', $masterBytes)])['stable_master_read']['token'] !== $stableToken, true],
    'single current cache has no stable invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-stable-read', $recovered[1], $journal)], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 193, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal], 'stable_master_read_token' => $stableToken]])['stable_master_read_invalidated_cache_page_numbers'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next193 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'single read digest rejected' => static fn () => $plan(null, null, [$masterByteDigest]),
    'unstable read digest rejected' => static fn () => $plan(null, null, [$masterByteDigest, hash('sha256', 'changed')]),
    'digest not matching bytes rejected' => static fn () => $plan(null, null, [hash('sha256', 'wrong'), hash('sha256', 'wrong')]),
    'empty digest rejected' => static fn () => $plan(null, null, [$masterByteDigest, '']),
    'bad cache stable token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], $journal, ['stable_master_read_token' => ''])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 193, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal], 'stable_master_read_token' => $stableToken]]),
    'bad read stable token rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 193, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal], 'stable_master_read_token' => '']]),
    'base member digest map rejected' => static fn () => $plan(null, null, null, [$journal => $memberDigests[$journal]]),
    'base recovered page rejected' => static fn () => $plan(null, null, null, null, [1 => 'short']),
    'base unaligned database rejected' => static fn () => $plan(null, null, null, null, null, $databaseBytes . 'x'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next193 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
