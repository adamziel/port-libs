<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next234.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next234-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-application-metadata-next234';
$publication = 234;
$masterDigest = hash('sha256', 'next234-master-source');
$recoverySequence = 234;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2340:size=96:mtime=23400:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2349:size=2560:mtime=23499:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=234;change-counter=72;version-valid-for=72;user-version=604;application-id=0x575034');
$currentPageCount = 5;
$currentCounter = 72;
$oldCounter = 71;
$currentUserVersion = 604;
$currentApplicationId = 0x575034;
$oldUserVersion = 603;
$oldApplicationId = 0x575033;
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
$formatPage = static function (string $label) use ($pageSize, $currentUserVersion, $currentApplicationId): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $currentUserVersion), 60, 4);
    $page = substr_replace($page, pack('N', $currentApplicationId), 68, 4);
    $page = substr_replace($page, pack('N', 72), 24, 4);
    $page = substr_replace($page, pack('N', 72), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next234 stale schema before application metadata recovery'),
    2 => $page('next234 stale wp_options root before application metadata recovery'),
    3 => $page('next234 stale active_plugins before application metadata recovery'),
    4 => $page('next234 stale user version before application metadata recovery'),
    5 => $page('next234 stale application id before application metadata recovery'),
];
$recovered = [
    1 => $formatPage('next234 current schema after application metadata recovery'),
    2 => $page('next234 current wp_options root after application metadata recovery'),
    3 => $page('next234 current active_plugins after application metadata recovery'),
    4 => $page('next234 current user version after application metadata recovery'),
    5 => $page('next234 current application id after application metadata recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, $currentUserVersion, $currentApplicationId]));
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$currentTokens = [
    $mainJournal => 'dev=8:ino=2341:size=4096:mtime=23401:generation=main-current',
    $usersJournal => 'dev=8:ino=2342:size=1024:mtime=23402:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-234'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-234'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, int $user, int $app, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 234,
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
    'database_change_counter' => $currentCounter,
    'version_valid_for' => $currentCounter,
    'user_version' => $user,
    'application_id' => $app,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-application-metadata', $recovered[1], $currentUserVersion, $currentApplicationId),
    2 => $cacheEntry('root-refreshed-application-metadata', $before[2], $currentUserVersion, $currentApplicationId),
    3 => $cacheEntry('active-stale-user-version', $recovered[3], $oldUserVersion, $currentApplicationId),
    4 => $cacheEntry('cron-stale-application-id', $recovered[4], $currentUserVersion, $oldApplicationId),
    5 => $cacheEntry('rewrite-stale-both-metadata', $recovered[5], $oldUserVersion, $oldApplicationId),
];
$reads = static fn (int $user = null, int $app = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 234,
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
        'database_header_digest' => $currentHeaderDigest,
        'database_page_count' => $currentPageCount,
        'database_change_counter' => $currentCounter,
        'version_valid_for' => $currentCounter,
        'user_version' => $user ?? $currentUserVersion,
        'application_id' => $app ?? $currentApplicationId,
    ],
    range(1, 5),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $user = null,
    ?int $app = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterMemberOrderFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    234,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $currentDatabaseToken,
    $currentHeaderDigest,
    $currentPageCount,
    $currentCounter,
    $currentCounter,
    $user ?? $currentUserVersion,
    $app ?? $currentApplicationId,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next234'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_user_version_application_id_before_current_source_reuse'],
    'current user version' => [static fn (): mixed => $plan()['current_user_version'], $currentUserVersion],
    'current application id' => [static fn (): mixed => $plan()['current_application_id'], $currentApplicationId],
    'inherits current counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentCounter],
    'metadata invalidated pages' => [static fn (): mixed => $plan()['application_metadata_invalidated_cache_page_numbers'], [3, 4, 5]],
    'user version invalidated pages' => [static fn (): mixed => $plan()['user_version_invalidated_cache_page_numbers'], [3, 5]],
    'application id invalidated pages' => [static fn (): mixed => $plan()['application_id_invalidated_cache_page_numbers'], [4, 5]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale user version' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale application id' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'metadata invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_application_metadata_after_current_source_next234'), 3],
    'dependency next234' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next234', $plan()['dependencies'], true), true],
    'dependency application metadata fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-application-metadata-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next230' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next230 SQLite version-number'), true],
    'non overlap mentions next231' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next231 freelist header'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-application-metadata')['application_metadata_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-application-metadata')['application_metadata_reason'], 'reader_cache_application_metadata_matches_current_source'],
    'row retained user version' => [static fn (): mixed => $row('schema-retained-application-metadata')['cache_user_version'], $currentUserVersion],
    'row retained application id' => [static fn (): mixed => $row('schema-retained-application-metadata')['cache_application_id'], $currentApplicationId],
    'row retained current user version' => [static fn (): mixed => $row('schema-retained-application-metadata')['current_user_version'], $currentUserVersion],
    'row retained current application id' => [static fn (): mixed => $row('schema-retained-application-metadata')['current_application_id'], $currentApplicationId],
    'row retained user matches' => [static fn (): mixed => $row('schema-retained-application-metadata')['user_version_matches'], true],
    'row retained app matches' => [static fn (): mixed => $row('schema-retained-application-metadata')['application_id_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-application-metadata')['application_metadata_admitted'], true],
    'row stale user admitted false' => [static fn (): mixed => $row('active-stale-user-version')['application_metadata_admitted'], false],
    'row stale user reason' => [static fn (): mixed => $row('active-stale-user-version')['application_metadata_reason'], 'reader_cache_user_version_changed_after_master_journal_recovery'],
    'row stale user mismatch' => [static fn (): mixed => $row('active-stale-user-version')['user_version_matches'], false],
    'row stale app admitted false' => [static fn (): mixed => $row('cron-stale-application-id')['application_metadata_admitted'], false],
    'row stale app reason' => [static fn (): mixed => $row('cron-stale-application-id')['application_metadata_reason'], 'reader_cache_application_id_changed_after_master_journal_recovery'],
    'row stale app mismatch' => [static fn (): mixed => $row('cron-stale-application-id')['application_id_matches'], false],
    'row stale both admitted false' => [static fn (): mixed => $row('rewrite-stale-both-metadata')['application_metadata_admitted'], false],
    'row stale both reason' => [static fn (): mixed => $row('rewrite-stale-both-metadata')['application_metadata_reason'], 'reader_cache_application_metadata_changed_after_master_journal_recovery'],
    'read retained metadata current' => [static fn (): mixed => $read('read-1')['application_metadata_current'], true],
    'read retained user value' => [static fn (): mixed => $read('read-1')['user_version'], $currentUserVersion],
    'read retained app value' => [static fn (): mixed => $read('read-1')['application_id'], $currentApplicationId],
    'read stale user source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-application-metadata-fence-current-source-next234'],
    'read stale user reason' => [static fn (): mixed => $read('read-3')['application_metadata_reason'], 'reader_cache_reopened_after_application_metadata_change'],
    'stale read ticket user reason' => [static fn (): mixed => $plan(null, $reads($oldUserVersion, $currentApplicationId))['next_reads'][0]['application_metadata_reason'], 'reader_ticket_user_version_predates_current_source'],
    'stale read ticket app reason' => [static fn (): mixed => $plan(null, $reads($currentUserVersion, $oldApplicationId))['next_reads'][0]['application_metadata_reason'], 'reader_ticket_application_id_predates_current_source'],
    'stale read ticket both reason' => [static fn (): mixed => $plan(null, $reads($oldUserVersion, $oldApplicationId))['next_reads'][0]['application_metadata_reason'], 'reader_ticket_application_metadata_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldUserVersion, $oldApplicationId))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5']],
    'future current user invalidates admitted cache' => [static fn (): mixed => $plan(null, null, 605, $currentApplicationId)['application_metadata_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'future current app invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $currentUserVersion, 0x575035)['application_metadata_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'all fresh cache no invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentUserVersion, $currentApplicationId)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['application_metadata_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentUserVersion, $currentApplicationId)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen($databaseBytes), $pageSize * 5],
    'master bytes digest current' => [static fn (): mixed => $currentMasterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $currentTokenDigest, $mapDigest($currentTokens)],
    'member header digest current' => [static fn (): mixed => $currentMemberHeaderDigest, $mapDigest($currentHeaders)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next234 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'negative current user version rejected' => static fn () => $plan(null, null, -1, $currentApplicationId),
    'negative current application id rejected' => static fn () => $plan(null, null, $currentUserVersion, -1),
    'negative cache user version rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], -1, 0)]),
    'negative cache application id rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], 0, -1)]),
    'missing cache user version rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1], 0, 0), ['user_version' => true])]),
    'missing cache application id rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1], 0, 0), ['application_id' => true])]),
    'negative read user version rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_number' => 1] + $reads(-1, 0)[0]]),
    'negative read application id rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_number' => 1] + $reads(0, -1)[0]]),
    'missing read user version rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['user_version' => true])]),
    'missing read application id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['application_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next234 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
