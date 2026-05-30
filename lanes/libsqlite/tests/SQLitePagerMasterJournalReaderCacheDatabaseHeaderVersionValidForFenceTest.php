<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next259.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next259-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-current-source-provenance-next259';
$publication = 259;
$masterDigest = hash('sha256', 'next259-master-source');
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2590:size=96:mtime=25900:snapshot=master-current';
$databaseToken = 'dev=8:ino=2599:size=6144:mtime=25999:snapshot=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25940:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=259:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=259:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=259:schema=106:change-counter=259:master-current';
$schemaReparseToken = 'schema-reparse:epoch=259:schema-cookie=106:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=259:root=1:cookie=106:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=259:members=2:database-token=2599:schema=106';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=252:members=2:database-token=2598:schema=105';
$currentDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=259:master-current:reset=complete';
$oldDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=252:before-master-reset';
$currentDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=259:schema-cookie=106:master-current';
$oldDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=252:schema-cookie=105:before-master-reset';
$currentDatabaseHeaderVersionValidForToken = 'database-header-version-valid-for:epoch=259:change-counter=259:schema-cookie=106:master-current';
$oldDatabaseHeaderVersionValidForToken = 'database-header-version-valid-for:epoch=252:change-counter=252:schema-cookie=105:before-master-reset';
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
    $page = substr_replace($page, pack('N', 106), 40, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 259), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503259), 68, 4);
    $page = substr_replace($page, pack('N', 259), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next259 stale header before version-valid-for recovery'),
    2 => $page('next259 stale wp_options root before version-valid-for recovery'),
    3 => $page('next259 stale active_plugins before version-valid-for recovery'),
    4 => $page('next259 stale plugin autoload before version-valid-for recovery'),
    5 => $page('next259 stale cron before version-valid-for recovery'),
    6 => $page('next259 stale rewrite rules before version-valid-for recovery'),
    7 => $page('next259 stale transient before version-valid-for recovery'),
    8 => $page('next259 stale usermeta before version-valid-for recovery'),
    9 => $page('next259 stale terms before version-valid-for recovery'),
    10 => $page('next259 stale comments before version-valid-for recovery'),
    11 => $page('next259 stale options statement before version-valid-for recovery'),
    12 => $page('next259 stale object cache before version-valid-for recovery'),
];
$recovered = [
    1 => $formatPage('next259 current header after version-valid-for recovery'),
    2 => $page('next259 current wp_options root after version-valid-for recovery'),
    3 => $page('next259 current active_plugins after version-valid-for recovery'),
    4 => $page('next259 current plugin autoload after version-valid-for recovery'),
    5 => $page('next259 current cron after version-valid-for recovery'),
    6 => $page('next259 current rewrite rules after version-valid-for recovery'),
    7 => $page('next259 current transient after version-valid-for recovery'),
    8 => $page('next259 current usermeta after version-valid-for recovery'),
    9 => $page('next259 current terms after version-valid-for recovery'),
    10 => $page('next259 current comments after version-valid-for recovery'),
    11 => $page('next259 current options statement after version-valid-for recovery'),
    12 => $page('next259 current object cache after version-valid-for recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 259, 0x57503259]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 252, 0x57503252]));
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
$tokens = [
    $mainJournal => 'dev=8:ino=2591:size=4096:mtime=25901:snapshot=main-current',
    $usersJournal => 'dev=8:ino=2592:size=1024:mtime=25902:snapshot=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-259'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-259'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 259,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 259,
    'recovered_page_set_digest' => $recoveredPageDigest,
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'read_transaction_token' => $readTransactionToken,
    'schema_reparse_token' => $schemaReparseToken,
    'statement_schema_root_token' => $statementSchemaRootToken,
    'current_source_provenance_token' => $currentSourceProvenanceToken,
    'database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken,
    'database_header_schema_cookie_token' => $currentDatabaseHeaderSchemaCookieToken,
    'database_header_version_valid_for_token' => $currentDatabaseHeaderVersionValidForToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-stale-version-valid-for', $recovered[1], ['database_header_version_valid_for_token' => $oldDatabaseHeaderVersionValidForToken]),
    2 => $cacheEntry('root-stale-schema-cookie', $recovered[2], ['database_header_schema_cookie_token' => $oldDatabaseHeaderSchemaCookieToken]),
    3 => $cacheEntry('active-stale-change-counter', $recovered[3], ['database_header_change_counter_token' => $oldDatabaseHeaderChangeCounterToken]),
    4 => $cacheEntry('autoload-stale-source-provenance', $recovered[4], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    5 => $cacheEntry('cron-fresh-current-source', $recovered[5]),
    6 => $cacheEntry('rewrite-dirty-format', $before[6], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
    7 => $cacheEntry('transient-stale-version-valid-for', $recovered[7], ['database_header_version_valid_for_token' => $oldDatabaseHeaderVersionValidForToken]),
    8 => $cacheEntry('usermeta-fresh-current-source', $recovered[8]),
    9 => $cacheEntry('terms-fresh-current-source', $recovered[9]),
    10 => $cacheEntry('comments-fresh-current-source', $recovered[10]),
    11 => $cacheEntry('statement-stale-version-valid-for', $recovered[11], ['database_header_version_valid_for_token' => $oldDatabaseHeaderVersionValidForToken]),
    12 => $cacheEntry('object-cache-fresh-current-source', $recovered[12]),
];
$reads = static fn (?string $readerVersionValidFor = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 259,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => 259,
        'recovered_page_set_digest' => $recoveredPageDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
        'database_file_token' => $databaseToken,
        'master_journal_cleanup_token' => $cleanupToken,
        'reader_lease_token' => $readerLeaseToken,
        'pager_cache_source_token' => $pagerCacheSourceToken,
        'read_transaction_token' => $readTransactionToken,
        'schema_reparse_token' => $schemaReparseToken,
        'statement_schema_root_token' => $statementSchemaRootToken,
        'current_source_provenance_token' => $currentSourceProvenanceToken,
        'database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken,
        'database_header_schema_cookie_token' => $currentDatabaseHeaderSchemaCookieToken,
        'database_header_version_valid_for_token' => $readerVersionValidFor ?? $currentDatabaseHeaderVersionValidForToken,
    ],
    range(1, 12),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentVersionValidFor = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::databaseHeaderVersionValidForFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    259,
    $publication,
    $masterDigest,
    259,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $readTransactionToken,
    $schemaReparseToken,
    $statementSchemaRootToken,
    $currentSourceProvenanceToken,
    $currentDatabaseHeaderChangeCounterToken,
    $currentDatabaseHeaderSchemaCookieToken,
    $currentVersionValidFor ?? $currentDatabaseHeaderVersionValidForToken,
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
$opCount = static function (array $plan, string $op): int {
    return count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next259'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_header_version_valid_for_before_reuse'],
    'current version-valid-for token' => [static fn (): mixed => $plan()['current_database_header_version_valid_for_token'], $currentDatabaseHeaderVersionValidForToken],
    'inherits schema-cookie token' => [static fn (): mixed => $plan()['current_database_header_schema_cookie_token'], $currentDatabaseHeaderSchemaCookieToken],
    'inherits change-counter token' => [static fn (): mixed => $plan()['current_database_header_change_counter_token'], $currentDatabaseHeaderChangeCounterToken],
    'version-valid-for invalidated pages' => [static fn (): mixed => $plan()['database_header_version_valid_for_invalidated_cache_page_numbers'], [1, 7, 11]],
    'schema-cookie invalidated pages' => [static fn (): mixed => $plan()['database_header_schema_cookie_invalidated_cache_page_numbers'], [2]],
    'change-counter invalidated pages' => [static fn (): mixed => $plan()['database_header_change_counter_invalidated_cache_page_numbers'], [3]],
    'source invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [1, 2, 3, 4, 6, 7, 11]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [5, 8, 9, 10, 12]],
    'requires reader reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-6', 'read-7', 'read-11']],
    'stale version row admitted false' => [static fn (): mixed => $row('schema-stale-version-valid-for')['database_header_version_valid_for_token_admitted'], false],
    'stale version row reason' => [static fn (): mixed => $row('schema-stale-version-valid-for')['database_header_version_valid_for_token_reason'], 'database_header_version_valid_for_predates_master_journal_current_source'],
    'stale version row cache token' => [static fn (): mixed => $row('schema-stale-version-valid-for')['cache_database_header_version_valid_for_token'], $oldDatabaseHeaderVersionValidForToken],
    'fresh row admitted true' => [static fn (): mixed => $row('cron-fresh-current-source')['database_header_version_valid_for_token_admitted'], true],
    'fresh row reason' => [static fn (): mixed => $row('cron-fresh-current-source')['database_header_version_valid_for_token_reason'], 'database_header_version_valid_for_matches_current_source'],
    'schema-cookie failure blocks version admission' => [static fn (): mixed => $row('root-stale-schema-cookie')['database_header_version_valid_for_token_admitted'], false],
    'change-counter failure blocks version admission' => [static fn (): mixed => $row('active-stale-change-counter')['database_header_version_valid_for_token_admitted'], false],
    'source failure blocks version admission' => [static fn (): mixed => $row('autoload-stale-source-provenance')['database_header_version_valid_for_token_admitted'], false],
    'read stale version cache miss' => [static fn (): mixed => $read('read-1')['cache_hit'], false],
    'read stale version source' => [static fn (): mixed => $read('read-1')['source'], 'master-journal-database-header-version-valid-for-fence-next259'],
    'read stale version reason' => [static fn (): mixed => $read('read-1')['database_header_version_valid_for_token_reason'], 'reader_cache_reopened_after_database_header_version_valid_for_change'],
    'read stale schema-cookie inherited source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-database-header-schema-cookie-fence-next256'],
    'read stale change-counter inherited source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-database-header-change-counter-fence-next253'],
    'read stale source inherited source' => [static fn (): mixed => $read('read-4')['source'], 'master-journal-reader-cache-current-source-provenance-fence-next243'],
    'fresh read still cache hit' => [static fn (): mixed => $read('read-5')['cache_hit'], true],
    'fresh read version current' => [static fn (): mixed => $read('read-5')['database_header_version_valid_for_token_current'], true],
    'dirty read inherited miss' => [static fn (): mixed => $read('read-6')['cache_hit'], false],
    'version operation count' => [static fn (): mixed => $opCount($plan(), 'invalidate_database_header_version_valid_for_after_master_journal_next259'), 3],
    'reader reopen version operation count' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_database_header_version_valid_for_after_master_journal_next259'), 3],
    'stale reader version ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldDatabaseHeaderVersionValidForToken))['read_cache_hits']['read-5'], false],
    'stale reader version ticket reason' => [static fn (): mixed => $plan(null, $reads($oldDatabaseHeaderVersionValidForToken))['next_reads'][4]['database_header_version_valid_for_token_reason'], 'reader_ticket_database_header_version_valid_for_predates_current_source'],
    'stale reader version ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldDatabaseHeaderVersionValidForToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9', 'read-10', 'read-11', 'read-12']],
    'stale reader version ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldDatabaseHeaderVersionValidForToken)), 'reopen_reader_for_database_header_version_valid_for_after_master_journal_next259'), 12],
    'changed current version invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'database-header-version-valid-for:epoch=260:change-counter=260:schema-cookie=106:master-current')['database_header_version_valid_for_invalidated_cache_page_numbers'], [1, 5, 7, 8, 9, 10, 11, 12]],
    'changed current version surfaced' => [static fn (): mixed => $plan(null, null, 'database-header-version-valid-for:epoch=260:change-counter=260:schema-cookie=106:master-current')['current_database_header_version_valid_for_token'], 'database-header-version-valid-for:epoch=260:change-counter=260:schema-cookie=106:master-current'],
    'all fresh no version invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_header_version_valid_for_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'source digest changes with version token' => [static fn (): mixed => $plan()['source_digest'] !== $plan(null, null, 'database-header-version-valid-for:epoch=260:change-counter=260:schema-cookie=106:master-current')['source_digest'], true],
    'dependencies include next259' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next259', $plan()['dependencies'], true), true],
    'dependencies include version fence' => [static fn (): mixed => in_array('sqlite-database-header-version-valid-for-fence', $plan()['dependencies'], true), true],
    'non-overlap names next256' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next256 schema-cookie'), true],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 12],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
    'version token embeds change counter' => [static fn (): mixed => str_contains($currentDatabaseHeaderVersionValidForToken, 'change-counter=259'), true],
    'version token embeds schema cookie' => [static fn (): mixed => str_contains($currentDatabaseHeaderVersionValidForToken, 'schema-cookie=106'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next259 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current version-valid-for token rejected' => static fn () => $plan(null, null, ''),
    'cache missing version-valid-for token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-version-valid-for', $recovered[1]), ['database_header_version_valid_for_token' => true])]),
    'cache empty version-valid-for token rejected' => static fn () => $plan([1 => $cacheEntry('empty-version-valid-for', $recovered[1], ['database_header_version_valid_for_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing version-valid-for token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_header_version_valid_for_token' => true])]),
    'read empty version-valid-for token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['database_header_version_valid_for_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next259 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
