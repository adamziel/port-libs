<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next230.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next230-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-sqlite-version-next230';
$publication = 230;
$masterDigest = hash('sha256', 'next230-master-source');
$recoverySequence = 230;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2300:size=96:mtime=23000:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2309:size=2560:mtime=23099:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=230;change-counter=62;version-valid-for=62;sqlite-version=3046000;page-count=5');
$oldHeaderDigest = hash('sha256', 'schema-cookie=229;change-counter=61;version-valid-for=61;sqlite-version=3045000;page-count=5');
$currentPageCount = 5;
$currentCounter = 62;
$oldCounter = 61;
$currentSqliteVersion = 3046000;
$oldSqliteVersion = 3045000;
$futureSqliteVersion = 3047000;
$oldDatabaseToken = 'dev=8:ino=2309:size=2560:mtime=23098:generation=database-prior';
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
$formatPage = static function (string $label, int $sqliteVersion) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 230), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503230), 68, 4);
    $page = substr_replace($page, pack('N', 62), 24, 4);
    $page = substr_replace($page, pack('N', 62), 92, 4);
    $page = substr_replace($page, pack('N', $sqliteVersion), 96, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next230 stale schema before sqlite version recovery', $oldSqliteVersion),
    2 => $page('next230 stale wp_options root before sqlite version recovery'),
    3 => $page('next230 stale active_plugins before sqlite version recovery'),
    4 => $page('next230 stale cron before sqlite version recovery'),
    5 => $page('next230 stale rewrite_rules before sqlite version recovery'),
];
$recovered = [
    1 => $formatPage('next230 current schema after sqlite version recovery', $currentSqliteVersion),
    2 => $page('next230 current wp_options root after sqlite version recovery'),
    3 => $page('next230 current active_plugins after sqlite version recovery'),
    4 => $page('next230 current cron after sqlite version recovery'),
    5 => $page('next230 current rewrite_rules after sqlite version recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 230, 0x57503230]));
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
    $mainJournal => 'dev=8:ino=2301:size=4096:mtime=23001:generation=main-current',
    $usersJournal => 'dev=8:ino=2302:size=1024:mtime=23002:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-230'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-230'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, int $change, int $valid, int $sqliteVersion, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 230,
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
    'database_change_counter' => $change,
    'version_valid_for' => $valid,
    'sqlite_version_number' => $sqliteVersion,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-version', $recovered[1], $currentCounter, $currentCounter, $currentSqliteVersion),
    2 => $cacheEntry('root-refreshed-version', $before[2], $currentCounter, $currentCounter, $currentSqliteVersion),
    3 => $cacheEntry('active-stale-version', $recovered[3], $currentCounter, $currentCounter, $oldSqliteVersion),
    4 => $cacheEntry('cron-stale-counter', $recovered[4], $oldCounter, $oldCounter, $currentSqliteVersion),
    5 => $cacheEntry('rewrite-stale-header', $recovered[5], $currentCounter, $currentCounter, $currentSqliteVersion, ['database_header_digest' => $oldHeaderDigest]),
];
$reads = static fn (int $sqliteVersion = null, int $change = null, int $valid = null, string $headerDigest = null, string $databaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 230,
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
        'database_page_count' => $currentPageCount,
        'database_change_counter' => $change ?? $currentCounter,
        'version_valid_for' => $valid ?? $currentCounter,
        'sqlite_version_number' => $sqliteVersion ?? $currentSqliteVersion,
    ],
    range(1, 5),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $sqliteVersion = null,
    ?int $change = null,
    ?int $valid = null,
    ?string $databaseHeader = null,
    ?string $databaseToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext230(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    230,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $databaseToken ?? $currentDatabaseToken,
    $databaseHeader ?? $currentHeaderDigest,
    $currentPageCount,
    $change ?? $currentCounter,
    $valid ?? $currentCounter,
    $sqliteVersion ?? $currentSqliteVersion,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next230'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_sqlite_version_number_before_current_source_reuse'],
    'current sqlite version' => [static fn (): mixed => $plan()['current_sqlite_version_number'], $currentSqliteVersion],
    'inherits current change counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentCounter],
    'inherits current version valid for' => [static fn (): mixed => $plan()['current_version_valid_for'], $currentCounter],
    'sqlite version invalidated pages' => [static fn (): mixed => $plan()['sqlite_version_number_invalidated_cache_page_numbers'], [3]],
    'header counter invalidated pages inherited' => [static fn (): mixed => $plan()['header_counter_invalidated_cache_page_numbers'], [4]],
    'all invalidated pages include header digest fence' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale version' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'version invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_sqlite_version_number_after_current_source_next230'), 1],
    'dependency next230' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next230', $plan()['dependencies'], true), true],
    'dependency version fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-sqlite-version-number-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next226' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next226 header counter admission'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-version')['sqlite_version_number_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-version')['sqlite_version_number_reason'], 'reader_cache_sqlite_version_number_matches_current_source'],
    'row retained cache version' => [static fn (): mixed => $row('schema-retained-version')['cache_sqlite_version_number'], $currentSqliteVersion],
    'row retained current version' => [static fn (): mixed => $row('schema-retained-version')['current_sqlite_version_number'], $currentSqliteVersion],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-version')['sqlite_version_number_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-version')['sqlite_version_number_admitted'], true],
    'row stale version admitted false' => [static fn (): mixed => $row('active-stale-version')['sqlite_version_number_admitted'], false],
    'row stale version reason' => [static fn (): mixed => $row('active-stale-version')['sqlite_version_number_reason'], 'reader_cache_sqlite_version_number_changed_after_master_journal_recovery'],
    'row stale version mismatch' => [static fn (): mixed => $row('active-stale-version')['sqlite_version_number_matches'], false],
    'row stale counter inherits reason' => [static fn (): mixed => $row('cron-stale-counter')['sqlite_version_number_reason'], 'reader_cache_header_counter_pair_changed_after_master_journal_recovery'],
    'row stale header inherits reason' => [static fn (): mixed => $row('rewrite-stale-header')['sqlite_version_number_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'read retained version current' => [static fn (): mixed => $read('read-1')['sqlite_version_number_current'], true],
    'read retained version value' => [static fn (): mixed => $read('read-1')['sqlite_version_number'], $currentSqliteVersion],
    'read stale version source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-sqlite-version-number-fence-current-source-next230'],
    'read stale version reason' => [static fn (): mixed => $read('read-3')['sqlite_version_number_reason'], 'reader_cache_reopened_after_sqlite_version_number_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldSqliteVersion))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSqliteVersion))['next_reads'][0]['sqlite_version_number_reason'], 'reader_ticket_sqlite_version_number_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSqliteVersion))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5']],
    'future current sqlite version invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $futureSqliteVersion)['sqlite_version_number_invalidated_cache_page_numbers'], [1, 2, 3]],
    'future current sqlite version surfaces current value' => [static fn (): mixed => $plan(null, null, $futureSqliteVersion)['current_sqlite_version_number'], $futureSqliteVersion],
    'changed header still inherited' => [static fn (): mixed => $plan(null, null, null, null, null, hash('sha256', 'new-current-header'))['database_header_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'changed database token still inherited' => [static fn (): mixed => $plan(null, null, null, null, null, null, $oldDatabaseToken)['database_file_token_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'all fresh cache no version invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentCounter, $currentCounter, $currentSqliteVersion)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['sqlite_version_number_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentCounter, $currentCounter, $currentSqliteVersion)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'old sqlite version fixture differs' => [static fn (): mixed => $oldSqliteVersion !== $currentSqliteVersion, true],
    'database bytes fixture length' => [static fn (): mixed => strlen($databaseBytes), $pageSize * 5],
    'page one current sqlite version encoded' => [static fn (): mixed => unpack('N', substr($recovered[1], 96, 4))[1], $currentSqliteVersion],
    'page one old sqlite version encoded' => [static fn (): mixed => unpack('N', substr($before[1], 96, 4))[1], $oldSqliteVersion],
    'master bytes digest current' => [static fn (): mixed => $currentMasterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $currentTokenDigest, $mapDigest($currentTokens)],
    'member header digest current' => [static fn (): mixed => $currentMemberHeaderDigest, $mapDigest($currentHeaders)],
    'order digest current' => [static fn (): mixed => $orderDigest, hash('sha256', implode("\n", $members))],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next230 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'zero current sqlite version rejected' => static fn () => $plan(null, null, 0),
    'missing cache sqlite version rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['sqlite_version_number' => true])]),
    'zero cache sqlite version rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['sqlite_version_number' => 0])]),
    'string cache sqlite version rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['sqlite_version_number' => '3046000'])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'missing read sqlite version rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['sqlite_version_number' => true])]),
    'zero read sqlite version rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['sqlite_version_number' => 0])]),
    'empty read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'inherits next226 zero current change counter rejection' => static fn () => $plan(null, null, null, 0, $currentCounter),
    'inherits next226 incoherent counter rejection' => static fn () => $plan(null, null, null, $currentCounter, $oldCounter),
    'inherits next219 missing page count rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_page_count' => true])]),
    'inherits next217 missing header rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_header_digest' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next230 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
