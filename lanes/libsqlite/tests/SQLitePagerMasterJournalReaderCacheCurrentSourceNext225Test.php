<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next225.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next225-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-validity-next225';
$publication = 225;
$masterDigest = hash('sha256', 'next225-master-source');
$recoverySequence = 225;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2250:size=96:mtime=22500:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2259:size=3072:mtime=22599:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=225;change-counter=84;version-valid-for=84;page-count=6');
$oldHeaderDigest = hash('sha256', 'schema-cookie=224;change-counter=83;version-valid-for=83;page-count=6');
$currentPageCount = 6;
$oldPageCount = 7;
$currentChangeCounter = 84;
$currentVersionValidFor = 84;
$oldChangeCounter = 83;
$oldVersionValidFor = 83;
$mixedVersionValidFor = 82;
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
$validityToken = static fn (int $changeCounter, int $versionValidFor): string => hash('sha256', $changeCounter . ':' . $versionValidFor);
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 225), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503235), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next225 stale schema before validity recovery'),
    2 => $page('next225 stale wp_options root before validity recovery'),
    3 => $page('next225 stale active_plugins before validity recovery'),
    4 => $page('next225 stale usermeta before validity recovery'),
    5 => $page('next225 stale cron before validity recovery'),
    6 => $page('next225 stale rewrite_rules before validity recovery'),
    7 => $page('next225 truncated comments before validity recovery'),
];
$recovered = [
    1 => $formatPage('next225 current schema after validity recovery'),
    2 => $page('next225 current wp_options root after validity recovery'),
    3 => $page('next225 current active_plugins after validity recovery'),
    4 => $page('next225 current usermeta after validity recovery'),
    5 => $page('next225 current cron after validity recovery'),
    6 => $page('next225 current rewrite_rules after validity recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 225, 0x57503235]));
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
    $mainJournal => 'dev=8:ino=2251:size=4096:mtime=22501:generation=main-current',
    $usersJournal => 'dev=8:ino=2252:size=1024:mtime=22502:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-225'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-225'),
];
$oldMemberHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-225'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 225,
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
    'database_change_counter' => $currentChangeCounter,
    'database_version_valid_for' => $currentVersionValidFor,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-validity', $recovered[1]),
    2 => $cacheEntry('root-refreshed-validity', $before[2]),
    3 => $cacheEntry('active-stale-change-counter', $recovered[3], ['database_change_counter' => $oldChangeCounter, 'database_version_valid_for' => $oldChangeCounter]),
    4 => $cacheEntry('usermeta-stale-version-valid-for', $recovered[4], ['database_version_valid_for' => $mixedVersionValidFor]),
    5 => $cacheEntry('cron-stale-header', $recovered[5], ['database_header_digest' => $oldHeaderDigest]),
    6 => $cacheEntry('rewrite-stale-member-header', $recovered[6], ['member_journal_header_digests' => $oldMemberHeaders]),
    7 => $cacheEntry('comments-truncated-page-count', $before[7], ['database_page_count' => $oldPageCount]),
];
$reads = static fn (
    ?int $changeCounter = null,
    ?int $versionValidFor = null,
    ?int $pageCount = null,
    ?string $databaseHeader = null,
): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 225,
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
        'database_file_token' => $currentDatabaseToken,
        'database_header_digest' => $databaseHeader ?? $currentHeaderDigest,
        'database_page_count' => $pageCount ?? $currentPageCount,
        'database_change_counter' => $changeCounter ?? $currentChangeCounter,
        'database_version_valid_for' => $versionValidFor ?? $currentVersionValidFor,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $pageCount = null,
    ?int $changeCounter = null,
    ?int $versionValidFor = null,
    ?string $databaseHeader = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext225(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    225,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $currentDatabaseToken,
    $databaseHeader ?? $currentHeaderDigest,
    $pageCount ?? $currentPageCount,
    $changeCounter ?? $currentChangeCounter,
    $versionValidFor ?? $currentVersionValidFor,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next225'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_change_counter_and_version_valid_for_before_current_source_reuse'],
    'current change counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentChangeCounter],
    'current version valid for' => [static fn (): mixed => $plan()['current_database_version_valid_for'], $currentVersionValidFor],
    'current validity token' => [static fn (): mixed => $plan()['current_database_cache_validity_token'], $validityToken($currentChangeCounter, $currentVersionValidFor)],
    'inherits page count' => [static fn (): mixed => $plan()['current_database_page_count'], $currentPageCount],
    'validity invalidated pages' => [static fn (): mixed => $plan()['database_cache_validity_invalidated_cache_page_numbers'], [3, 4]],
    'all invalidated pages include inherited fences' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_database_validity_after_current_source_next225'), 2],
    'dependency next225' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next225', $plan()['dependencies'], true), true],
    'dependency validity marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-change-counter-version-valid-for-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next219', $plan()['dependencies'], true), true],
    'non overlap mentions next219' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next219 page-count admission'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-validity')['database_cache_validity_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-validity')['database_cache_validity_reason'], 'reader_cache_database_validity_counters_match_current_source'],
    'row retained change counter' => [static fn (): mixed => $row('schema-retained-validity')['cache_database_change_counter'], $currentChangeCounter],
    'row retained version valid for' => [static fn (): mixed => $row('schema-retained-validity')['cache_database_version_valid_for'], $currentVersionValidFor],
    'row retained token' => [static fn (): mixed => $row('schema-retained-validity')['cache_database_validity_token'], $validityToken($currentChangeCounter, $currentVersionValidFor)],
    'row retained change counter matches' => [static fn (): mixed => $row('schema-retained-validity')['database_change_counter_matches'], true],
    'row retained version matches' => [static fn (): mixed => $row('schema-retained-validity')['database_version_valid_for_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-validity')['database_cache_validity_admitted'], true],
    'row changed counter admitted false' => [static fn (): mixed => $row('active-stale-change-counter')['database_cache_validity_admitted'], false],
    'row changed counter reason' => [static fn (): mixed => $row('active-stale-change-counter')['database_cache_validity_reason'], 'reader_cache_change_counter_changed_after_master_journal_recovery'],
    'row changed counter mismatch' => [static fn (): mixed => $row('active-stale-change-counter')['database_change_counter_matches'], false],
    'row changed counter version mismatch follows counter' => [static fn (): mixed => $row('active-stale-change-counter')['database_version_valid_for_matches'], false],
    'row changed counter cache token' => [static fn (): mixed => $row('active-stale-change-counter')['cache_database_validity_token'], $validityToken($oldChangeCounter, $oldChangeCounter)],
    'row changed version admitted false' => [static fn (): mixed => $row('usermeta-stale-version-valid-for')['database_cache_validity_admitted'], false],
    'row changed version reason' => [static fn (): mixed => $row('usermeta-stale-version-valid-for')['database_cache_validity_reason'], 'reader_cache_version_valid_for_changed_after_master_journal_recovery'],
    'row changed version counter matches' => [static fn (): mixed => $row('usermeta-stale-version-valid-for')['database_change_counter_matches'], true],
    'row changed version mismatch' => [static fn (): mixed => $row('usermeta-stale-version-valid-for')['database_version_valid_for_matches'], false],
    'row changed version cache token' => [static fn (): mixed => $row('usermeta-stale-version-valid-for')['cache_database_validity_token'], $validityToken($currentChangeCounter, $mixedVersionValidFor)],
    'row stale header inherits reason' => [static fn (): mixed => $row('cron-stale-header')['database_cache_validity_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'row stale member header inherits reason' => [static fn (): mixed => $row('rewrite-stale-member-header')['database_cache_validity_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row truncated inherits reason' => [static fn (): mixed => $row('comments-truncated-page-count')['database_cache_validity_reason'], 'reader_cache_page_number_exceeds_current_database_page_count'],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read change counter miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read version miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read header miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], false],
    'read truncated miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-7'], false],
    'read retained validity current' => [static fn (): mixed => $read('read-1')['database_cache_validity_current'], true],
    'read retained counter current' => [static fn (): mixed => $read('read-1')['database_change_counter_current'], true],
    'read retained version current' => [static fn (): mixed => $read('read-1')['database_version_valid_for_current'], true],
    'read retained token' => [static fn (): mixed => $read('read-1')['database_cache_validity_token'], $validityToken($currentChangeCounter, $currentVersionValidFor)],
    'read changed counter source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-validity-counter-fence-current-source-next225'],
    'read changed counter reason' => [static fn (): mixed => $read('read-3')['database_cache_validity_reason'], 'reader_cache_reopened_after_database_validity_counter_change'],
    'read changed version reason' => [static fn (): mixed => $read('read-4')['database_cache_validity_reason'], 'reader_cache_reopened_after_database_validity_counter_change'],
    'stale read counter misses retained' => [static fn (): mixed => $plan(null, $reads($oldChangeCounter, $currentVersionValidFor))['read_cache_hits']['read-1'], false],
    'stale read counter reason' => [static fn (): mixed => $plan(null, $reads($oldChangeCounter, $currentVersionValidFor))['next_reads'][0]['database_cache_validity_reason'], 'reader_ticket_change_counter_predates_current_source'],
    'stale read version misses retained' => [static fn (): mixed => $plan(null, $reads($currentChangeCounter, $oldVersionValidFor))['read_cache_hits']['read-1'], false],
    'stale read version reason' => [static fn (): mixed => $plan(null, $reads($currentChangeCounter, $oldVersionValidFor))['next_reads'][0]['database_cache_validity_reason'], 'reader_ticket_version_valid_for_predates_current_source'],
    'current ticket keeps first hit' => [static fn (): mixed => $plan(null, $reads($currentChangeCounter, $currentVersionValidFor))['read_cache_hits']['read-1'], true],
    'changed current counter invalidates admitted cache' => [static fn (): mixed => $plan(null, null, null, $currentChangeCounter + 1, $currentVersionValidFor + 1)['database_cache_validity_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'changed current version invalidates admitted cache' => [static fn (): mixed => $plan(null, null, null, $currentChangeCounter, $currentVersionValidFor + 1)['database_cache_validity_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'changed page count still inherited' => [static fn (): mixed => $plan(null, null, $oldPageCount)['database_page_count_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'changed header still inherited' => [static fn (): mixed => $plan(null, null, null, null, null, $oldHeaderDigest)['database_header_invalidated_cache_page_numbers'], [1, 2, 3, 4, 7]],
    'all fresh cache no validity invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_cache_validity_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next225 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'negative current change counter rejected' => static fn () => $plan(null, null, null, -1),
    'negative current version rejected' => static fn () => $plan(null, null, null, null, -1),
    'missing cache change counter rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_change_counter' => true])]),
    'missing cache version rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_version_valid_for' => true])]),
    'negative cache change counter rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['database_change_counter' => -1])]),
    'string cache version rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['database_version_valid_for' => '84'])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'missing read change counter rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_change_counter' => true])]),
    'missing read version rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_version_valid_for' => true])]),
    'negative read counter rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['database_change_counter' => -1])]),
    'empty read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'inherits next219 missing page count rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_page_count' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next225 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
