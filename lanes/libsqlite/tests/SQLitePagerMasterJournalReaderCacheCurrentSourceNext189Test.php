<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next189.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next189-users.sqlite';
$master = '/srv/wp-content/database/wp-next189.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$sourceId = 'pager-reader-cache-member-digest-next189';
$masterBytes = $journal . "\n" . $usersJournal . "\n";
$publication = 189;
$masterDigest = hash('sha256', 'next189-current-master-source');
$recoverySequence = 77;
$oldMemberDigest = hash('sha256', 'next189-old-member-journal');
$memberDigests = [
    $journal => hash('sha256', 'next189-current-main-rollback-journal'),
    $usersJournal => hash('sha256', 'next189-current-users-rollback-journal'),
];
$formatPage = static function (string $label, int $reserved, int $encoding, int $userVersion, int $applicationId) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr($reserved), 20, 1);
    $page = substr_replace($page, pack('N', $encoding), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 19, 0x57504f59]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 18, 0x57504f58]));
$before = [
    1 => $formatPage('next189 old wp header before member digest', 0, 1, 18, 0x57504f58),
    2 => $page('next189 stale wp_options root before member digest'),
    3 => $page('next189 stale active plugins before member digest'),
    4 => $page('next189 stale users table before member digest'),
    5 => $page('next189 stale usermeta table before member digest'),
    6 => $page('next189 stale plugin settings before member digest'),
    7 => $page('next189 stale cron before member digest'),
    8 => $page('next189 unchanged optionmeta before member digest'),
    9 => $page('next189 unchanged site options before member digest'),
];
$recovered = [
    1 => $formatPage('next189 current wp header after member digest', 4, 2, 19, 0x57504f59),
    2 => $page('next189 recovered wp_options root after member digest'),
    3 => $page('next189 recovered active plugins after member digest'),
    4 => $page('next189 recovered users table after member digest'),
    5 => $page('next189 recovered usermeta table after member digest'),
    7 => $page('next189 recovered cron after member digest'),
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
$oldRecoveredDigest = hash('sha256', 'next189-old-recovered-page-set');
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, string $memberPath, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 189,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_path' => $memberPath,
    'member_journal_digest' => $memberDigests[$memberPath],
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-member-digest', $recovered[1], $journal, ['shared' => true]),
    2 => $cacheEntry('options-refreshed-member-digest', $before[2], $journal),
    3 => $cacheEntry('active-old-main-member-digest', $recovered[3], $journal, ['member_journal_digest' => $oldMemberDigest]),
    4 => $cacheEntry('users-retained-member-digest', $recovered[4], $usersJournal),
    5 => $cacheEntry('usermeta-old-attached-member-digest', $recovered[5], $usersJournal, ['member_journal_digest' => $oldMemberDigest]),
    6 => $cacheEntry('settings-stale-recovery-sequence', $before[6], $journal, ['recovery_sequence' => 76]),
    7 => $cacheEntry('cron-stale-format', $recovered[7], $journal, ['format_signature' => $oldFormatSignature]),
    8 => $cacheEntry('optionmeta-dirty-member-digest', $before[8], $journal, ['dirty' => true]),
    9 => $cacheEntry('site-options-retained-member-digest', $before[9], $journal),
];
$reads = static fn (?array $override = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 189,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_path' => in_array($pageNumber, [4, 5], true) ? $usersJournal : $journal,
        'member_journal_digest' => $memberDigests[in_array($pageNumber, [4, 5], true) ? $usersJournal : $journal],
    ],
    range(1, 9),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $digests = null,
    ?array $recoveredPages = null,
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext189(
    $database,
    $master,
    $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    189,
    $publication,
    $masterDigest,
    $recoverySequence,
    $digests ?? $memberDigests,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next189'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_member_journal_digest_fences_current_source_reuse'],
    'member digest map' => [static fn (): mixed => $plan()['current_member_journal_digests'], $memberDigests],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 4, 9]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 5, 6, 7, 8]],
    'member invalidated pages' => [static fn (): mixed => $plan()['member_journal_invalidated_cache_page_numbers'], [3, 5]],
    'recovery invalidated unchanged' => [static fn (): mixed => $plan()['recovery_invalidated_cache_page_numbers'], [6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row schema admitted' => [static fn (): mixed => $row('schema-retained-member-digest')['member_journal_admitted'], true],
    'row schema reason' => [static fn (): mixed => $row('schema-retained-member-digest')['member_journal_reason'], 'reader_cache_member_journal_digest_matches_current_source'],
    'row options refreshed admitted' => [static fn (): mixed => $row('options-refreshed-member-digest')['member_journal_admitted'], true],
    'row active old digest rejected' => [static fn (): mixed => $row('active-old-main-member-digest')['member_journal_reason'], 'reader_cache_member_journal_digest_predates_current_source'],
    'row attached old digest rejected' => [static fn (): mixed => $row('usermeta-old-attached-member-digest')['member_journal_reason'], 'reader_cache_member_journal_digest_predates_current_source'],
    'row recovery carries base reason' => [static fn (): mixed => $row('settings-stale-recovery-sequence')['member_journal_reason'], 'reader_cache_recovery_sequence_predates_master_journal_source'],
    'row format carries base reason' => [static fn (): mixed => $row('cron-stale-format')['member_journal_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row dirty carries base reason' => [static fn (): mixed => $row('optionmeta-dirty-member-digest')['member_journal_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'row attached path' => [static fn (): mixed => $row('users-retained-member-digest')['cache_member_journal_path'], $usersJournal],
    'row main digest matches' => [static fn (): mixed => $row('schema-retained-member-digest')['member_journal_digest_matches'], true],
    'row old digest mismatch' => [static fn (): mixed => $row('active-old-main-member-digest')['member_journal_digest_matches'], false],
    'row current digest exposed' => [static fn (): mixed => $row('active-old-main-member-digest')['current_member_journal_digest'], $memberDigests[$journal]],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 9],
    'read schema hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read options hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read active miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read users hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], true],
    'read usermeta miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], false],
    'read active source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-member-journal-fence-current-source-next189'],
    'read active member current' => [static fn (): mixed => $plan()['next_reads'][2]['member_journal_current'], true],
    'read active reason' => [static fn (): mixed => $plan()['next_reads'][2]['member_journal_reason'], 'reader_cache_reopened_after_member_journal_digest_change'],
    'read active digest current' => [static fn (): mixed => $plan()['next_reads'][2]['member_journal_digest'], $memberDigests[$journal]],
    'read options prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next189 recovered wp_options root after member digest'],
    'read user prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-4'], 'next189 recovered users table after member digest'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-5', 'read-6', 'read-7', 'read-8']],
    'operation member invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_member_journal_after_master_current_source_next189', array_column($plan()['operations'], 'op'), true), true],
    'operation member invalidate count' => [static fn (): mixed => count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_member_journal_after_master_current_source_next189')), 2],
    'operation member invalidate path' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_member_journal_after_master_current_source_next189'))[1]['member_journal_path'], $usersJournal],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next189', $plan()['dependencies'], true), true],
    'dependency member fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-member-journal-digest-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next186', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next186'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'changed main digest invalidates retained main cache' => [static fn (): mixed => $plan(null, null, [$journal => hash('sha256', 'changed-main'), $usersJournal => $memberDigests[$usersJournal]])['read_cache_hits']['read-1'], false],
    'changed attached digest invalidates retained attached cache' => [static fn (): mixed => $plan(null, null, [$journal => $memberDigests[$journal], $usersJournal => hash('sha256', 'changed-users')])['read_cache_hits']['read-4'], false],
    'stale read ticket misses retained cache' => [static fn (): mixed => $plan(null, array_replace($reads(), [0 => array_merge($reads()[0], ['member_journal_digest' => $oldMemberDigest])]))['read_cache_hits']['read-1'], false],
    'unknown read member misses cache' => [static fn (): mixed => $plan(null, array_replace($reads(), [0 => array_merge($reads()[0], ['member_journal_path' => '/tmp/missing-journal', 'member_journal_digest' => $oldMemberDigest])]))['read_cache_hits']['read-1'], false],
    'single current cache has no member invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-digest', $recovered[1], $journal)], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]]])['member_journal_invalidated_cache_page_numbers'], []],
    'all current cache retains page' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-digest', $recovered[1], $journal)], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]]])['retained_cache_page_numbers'], [1]],
    'all current cache read hit' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-digest', $recovered[1], $journal)], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]]])['read_cache_hits']['read-1'], true],
    'old recovered digest still base miss' => [static fn (): mixed => $plan(null, array_replace($reads(), [0 => array_merge($reads()[0], ['recovered_page_set_digest' => $oldRecoveredDigest])]))['read_cache_hits']['read-1'], false],
    'unaligned database rejected by base' => [static fn (): mixed => str_contains($plan(null, null, null, null, $databaseBytes . 'x')['status'] ?? '', 'never'), false],
];

unset($cases['unaligned database rejected by base']);

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next189 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing member digest map rejected' => static fn () => $plan(null, null, []),
    'partial member digest map rejected' => static fn () => $plan(null, null, [$journal => $memberDigests[$journal]]),
    'empty digest path rejected' => static fn () => $plan(null, null, ['' => $memberDigests[$journal], $usersJournal => $memberDigests[$usersJournal]]),
    'empty digest rejected' => static fn () => $plan(null, null, [$journal => '', $usersJournal => $memberDigests[$usersJournal]]),
    'bad cache member path rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], $journal, ['member_journal_path' => ''])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]]]),
    'bad cache member digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], $journal, ['member_journal_digest' => ''])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]]]),
    'bad read member path rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => '', 'member_journal_digest' => $memberDigests[$journal]]]),
    'bad read member digest rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => '']]),
    'bad recovered page rejected' => static fn () => $plan(null, null, null, [1 => 'short']),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, $databaseBytes . 'x'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next189 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
