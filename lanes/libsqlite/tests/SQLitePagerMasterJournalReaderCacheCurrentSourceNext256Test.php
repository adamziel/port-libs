<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next256.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next256-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-current-source-provenance-next256';
$publication = 256;
$masterDigest = hash('sha256', 'next256-master-source');
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2560:size=96:mtime=25600:snapshot=master-current';
$databaseToken = 'dev=8:ino=2569:size=5120:mtime=25699:snapshot=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24400:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=256:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=256:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=256:schema=103:change-counter=256:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=242:schema=102:change-counter=242:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=256:schema-cookie=103:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=242:schema-cookie=102:ddl=before-master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=256:root=1:cookie=103:sql=wp-options-current';
$oldStatementSchemaRootToken = 'statement-schema-root:epoch=242:root=1:cookie=102:sql=wp-options-prior';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=256:members=2:database-token=2569:schema=103';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=242:members=2:database-token=2568:schema=102';
$currentDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=256:master-current:reset=complete';
$oldDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=246:before-master-reset';
$currentDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=256:schema-cookie=103:master-current';
$oldDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=246:schema-cookie=102:before-master-reset';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=242:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=25690:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2569:size=5120:mtime=25698:snapshot=database-prior';
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
    $page = substr_replace($page, pack('N', 256), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503256), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next256 stale schema before source provenance recovery'),
    2 => $page('next256 stale wp_options root before source provenance recovery'),
    3 => $page('next256 stale active_plugins before source provenance recovery'),
    4 => $page('next256 stale usermeta before source provenance recovery'),
    5 => $page('next256 stale rewrite_rules before source provenance recovery'),
    6 => $page('next256 stale cron before source provenance recovery'),
    7 => $page('next256 stale comments before source provenance recovery'),
    8 => $page('next256 stale terms before source provenance recovery'),
    9 => $page('next256 stale options autoload statement before source provenance recovery'),
    10 => $page('next256 stale transient timeout before source provenance recovery'),
];
$recovered = [
    1 => $formatPage('next256 current schema after source provenance recovery'),
    2 => $page('next256 current wp_options root after source provenance recovery'),
    3 => $page('next256 current active_plugins after source provenance recovery'),
    4 => $page('next256 current usermeta after source provenance recovery'),
    6 => $page('next256 current cron after source provenance recovery'),
    9 => $page('next256 current options autoload statement after source provenance recovery'),
    10 => $page('next256 current transient timeout after source provenance recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 256, 0x57503256]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 242, 0x57503242]));
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
    $mainJournal => 'dev=8:ino=2561:size=4096:mtime=25601:snapshot=main-current',
    $usersJournal => 'dev=8:ino=2562:size=1024:mtime=25602:snapshot=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-256'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-256'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-256'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 256,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 256,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-stale-header-schema-cookie', $recovered[1], ['database_header_schema_cookie_token' => $oldDatabaseHeaderSchemaCookieToken]),
    2 => $cacheEntry('root-stale-change-counter', $before[2], ['database_header_change_counter_token' => $oldDatabaseHeaderChangeCounterToken]),
    3 => $cacheEntry('active-stale-source-provenance', $recovered[3], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    4 => $cacheEntry('usermeta-stale-statement-root', $recovered[4], ['statement_schema_root_token' => $oldStatementSchemaRootToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-source-provenance', $before[8], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
    9 => $cacheEntry('autoload-stale-source-provenance', $recovered[9], ['current_source_provenance_token' => 'current-source:master-journal:epoch=242:statement=autoload-prior']),
    10 => $cacheEntry('transient-stale-change-counter', $recovered[10], ['database_header_change_counter_token' => $oldDatabaseHeaderChangeCounterToken]),
];
$reads = static fn (
    ?string $sourceToken = null,
    ?string $statementToken = null,
    ?string $schemaToken = null,
    ?string $readTransaction = null,
    ?string $pagerCache = null,
    ?string $readerChangeCounter = null,
    ?string $readerSchemaCookie = null,
): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 256,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => 256,
        'recovered_page_set_digest' => $recoveredPageDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
        'database_file_token' => $databaseToken,
        'master_journal_cleanup_token' => $cleanupToken,
        'reader_lease_token' => $readerLeaseToken,
        'pager_cache_source_token' => $pagerCache ?? $pagerCacheSourceToken,
        'read_transaction_token' => $readTransaction ?? $readTransactionToken,
        'schema_reparse_token' => $schemaToken ?? $schemaReparseToken,
        'statement_schema_root_token' => $statementToken ?? $statementSchemaRootToken,
        'current_source_provenance_token' => $sourceToken ?? $currentSourceProvenanceToken,
        'database_header_change_counter_token' => $readerChangeCounter ?? $currentDatabaseHeaderChangeCounterToken,
        'database_header_schema_cookie_token' => $readerSchemaCookie ?? $currentDatabaseHeaderSchemaCookieToken,
    ],
    range(1, 10),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentSourceToken = null,
    ?string $currentReaderChangeCounterToken = null,
    ?string $currentReaderSchemaCookieToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext256(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    256,
    $publication,
    $masterDigest,
    256,
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
    $currentSourceToken ?? $currentSourceProvenanceToken,
    $currentReaderChangeCounterToken ?? $currentDatabaseHeaderChangeCounterToken,
    $currentReaderSchemaCookieToken ?? $currentDatabaseHeaderSchemaCookieToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next256'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_header_schema_cookie_before_reuse'],
    'current source provenance token' => [static fn (): mixed => $plan()['current_source_provenance_token'], $currentSourceProvenanceToken],
    'current database header change-counter token' => [static fn (): mixed => $plan()['current_database_header_change_counter_token'], $currentDatabaseHeaderChangeCounterToken],
    'current database header schema-cookie token' => [static fn (): mixed => $plan()['current_database_header_schema_cookie_token'], $currentDatabaseHeaderSchemaCookieToken],
    'inherits statement schema root token' => [static fn (): mixed => $plan()['current_statement_schema_root_token'], $statementSchemaRootToken],
    'source provenance invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [3, 9]],
    'database header change-counter invalidated pages' => [static fn (): mixed => $plan()['database_header_change_counter_invalidated_cache_page_numbers'], [2, 10]],
    'database header schema-cookie invalidated pages' => [static fn (): mixed => $plan()['database_header_schema_cookie_invalidated_cache_page_numbers'], [1]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], []],
    'refreshed page invalidated by change-counter' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9', 'read-10']],
    'read hit stale schema-cookie schema' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], false],
    'read hit stale change-counter root' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale source' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale change-counter transient' => [static fn (): mixed => $plan()['read_cache_hits']['read-10'], false],
    'operation invalidates stale change-counter cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_database_header_change_counter_after_master_journal_next253'), 2],
    'operation reopens stale change-counter reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_database_header_change_counter_after_master_journal_next253'), 2],
    'operation invalidates stale schema-cookie cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_database_header_schema_cookie_after_master_journal_next256'), 1],
    'operation reopens stale schema-cookie reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_database_header_schema_cookie_after_master_journal_next256'), 1],
    'dependency next256' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next256', $plan()['dependencies'], true), true],
    'dependency change-counter fence' => [static fn (): mixed => in_array('sqlite-database-header-change-counter-fence', $plan()['dependencies'], true), true],
    'dependency schema-cookie fence' => [static fn (): mixed => in_array('sqlite-database-header-schema-cookie-fence', $plan()['dependencies'], true), true],
    'dependency next243 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next243', $plan()['dependencies'], true), true],
    'dependency next240 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next240', $plan()['dependencies'], true), true],
    'non overlap mentions next240' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next240 statement-root'), true],
    'non overlap mentions wal checkpoint' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'WAL checkpoint'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row schema-cookie source admitted' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['current_source_provenance_token_admitted'], true],
    'row schema-cookie change-counter admitted' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['database_header_change_counter_token_admitted'], true],
    'row schema-cookie admitted false' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['database_header_schema_cookie_token_admitted'], false],
    'row schema-cookie reason' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['database_header_schema_cookie_token_reason'], 'database_header_schema_cookie_predates_master_journal_current_source'],
    'row schema-cookie cache token' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['cache_database_header_schema_cookie_token'], $oldDatabaseHeaderSchemaCookieToken],
    'row schema-cookie current token' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['current_database_header_schema_cookie_token'], $currentDatabaseHeaderSchemaCookieToken],
    'row schema-cookie mismatch' => [static fn (): mixed => $row('schema-stale-header-schema-cookie')['database_header_schema_cookie_token_matches'], false],
    'row stale change-counter source admitted' => [static fn (): mixed => $row('root-stale-change-counter')['current_source_provenance_token_admitted'], true],
    'row stale change-counter admitted false' => [static fn (): mixed => $row('root-stale-change-counter')['database_header_change_counter_token_admitted'], false],
    'row stale change-counter reason' => [static fn (): mixed => $row('root-stale-change-counter')['database_header_change_counter_token_reason'], 'database_header_change_counter_predates_master_journal_current_source'],
    'row stale change-counter cache token' => [static fn (): mixed => $row('root-stale-change-counter')['cache_database_header_change_counter_token'], $oldDatabaseHeaderChangeCounterToken],
    'row stale change-counter current token' => [static fn (): mixed => $row('root-stale-change-counter')['current_database_header_change_counter_token'], $currentDatabaseHeaderChangeCounterToken],
    'row stale change-counter mismatch' => [static fn (): mixed => $row('root-stale-change-counter')['database_header_change_counter_token_matches'], false],
    'row stale source admitted false' => [static fn (): mixed => $row('active-stale-source-provenance')['current_source_provenance_token_admitted'], false],
    'row stale source reason' => [static fn (): mixed => $row('active-stale-source-provenance')['current_source_provenance_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row stale source cache token' => [static fn (): mixed => $row('active-stale-source-provenance')['cache_current_source_provenance_token'], $oldCurrentSourceProvenanceToken],
    'row stale source current token' => [static fn (): mixed => $row('active-stale-source-provenance')['current_source_provenance_token'], $currentSourceProvenanceToken],
    'row stale source mismatch' => [static fn (): mixed => $row('active-stale-source-provenance')['current_source_provenance_token_matches'], false],
    'row stale second source reason' => [static fn (): mixed => $row('autoload-stale-source-provenance')['current_source_provenance_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row statement root inherits reason' => [static fn (): mixed => $row('usermeta-stale-statement-root')['current_source_provenance_token_reason'], 'reader_cache_statement_schema_root_token_predates_master_journal_current_source'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['current_source_provenance_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['current_source_provenance_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['current_source_provenance_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-source-provenance')['current_source_provenance_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained source current' => [static fn (): mixed => $read('read-1')['current_source_provenance_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['current_source_provenance_token'], $currentSourceProvenanceToken],
    'read retained change-counter current' => [static fn (): mixed => $read('read-1')['database_header_change_counter_token_current'], true],
    'read retained change-counter surfaced' => [static fn (): mixed => $read('read-1')['database_header_change_counter_token'], $currentDatabaseHeaderChangeCounterToken],
    'read stale schema-cookie current ticket' => [static fn (): mixed => $read('read-1')['database_header_schema_cookie_token_current'], true],
    'read stale schema-cookie surfaced' => [static fn (): mixed => $read('read-1')['database_header_schema_cookie_token'], $currentDatabaseHeaderSchemaCookieToken],
    'read stale schema-cookie cache miss' => [static fn (): mixed => $read('read-1')['cache_hit'], false],
    'read stale schema-cookie source' => [static fn (): mixed => $read('read-1')['source'], 'master-journal-database-header-schema-cookie-fence-next256'],
    'read stale schema-cookie reason' => [static fn (): mixed => $read('read-1')['database_header_schema_cookie_token_reason'], 'reader_cache_reopened_after_database_header_schema_cookie_change'],
    'read stale change-counter cache miss' => [static fn (): mixed => $read('read-2')['cache_hit'], false],
    'read stale change-counter source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-database-header-change-counter-fence-next253'],
    'read stale change-counter reason' => [static fn (): mixed => $read('read-2')['database_header_change_counter_token_reason'], 'reader_cache_reopened_after_database_header_change_counter_change'],
    'read stale source cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale source source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-current-source-provenance-fence-next243'],
    'read stale source reason' => [static fn (): mixed => $read('read-3')['current_source_provenance_token_reason'], 'reader_cache_reopened_after_current_source_provenance_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceProvenanceToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceProvenanceToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9', 'read-10']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldCurrentSourceProvenanceToken)), 'reopen_reader_for_current_source_provenance_after_master_journal_next243'), 10],
    'stale change-counter ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldDatabaseHeaderChangeCounterToken))['read_cache_hits']['read-1'], false],
    'stale change-counter ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldDatabaseHeaderChangeCounterToken))['next_reads'][0]['database_header_change_counter_token_reason'], 'reader_ticket_database_header_change_counter_predates_current_source'],
    'stale change-counter ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldDatabaseHeaderChangeCounterToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9', 'read-10']],
    'stale change-counter ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, null, null, $oldDatabaseHeaderChangeCounterToken)), 'reopen_reader_for_database_header_change_counter_after_master_journal_next253'), 10],
    'stale schema-cookie ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, null, $oldDatabaseHeaderSchemaCookieToken))['read_cache_hits']['read-2'], false],
    'stale schema-cookie ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, null, $oldDatabaseHeaderSchemaCookieToken))['next_reads'][1]['database_header_schema_cookie_token_reason'], 'reader_ticket_database_header_schema_cookie_predates_current_source'],
    'stale schema-cookie ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, null, $oldDatabaseHeaderSchemaCookieToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9', 'read-10']],
    'stale schema-cookie ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, null, null, null, $oldDatabaseHeaderSchemaCookieToken)), 'reopen_reader_for_database_header_schema_cookie_after_master_journal_next256'), 10],
    'stale statement ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldStatementSchemaRootToken))['next_reads'][0]['statement_schema_root_token_reason'], 'reader_ticket_statement_schema_root_predates_current_source'],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no source invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['current_source_provenance_invalidated_cache_page_numbers'], []],
    'all fresh no change-counter invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_header_change_counter_invalidated_cache_page_numbers'], []],
    'all fresh no schema-cookie invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_header_schema_cookie_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current source invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104')['current_source_provenance_invalidated_cache_page_numbers'], [1, 2, 3, 9, 10]],
    'changed current source keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104')['invalidated_cache_page_numbers'], true), true],
    'changed current source surfaced' => [static fn (): mixed => $plan(null, null, 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104')['current_source_provenance_token'], 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104'],
    'changed database header change-counter invalidates admitted pages' => [static fn (): mixed => $plan(null, null, null, 'database-header-change-counter:epoch=248:master-current:reset=second')['database_header_change_counter_invalidated_cache_page_numbers'], [1, 2, 10]],
    'changed database header change-counter surfaced' => [static fn (): mixed => $plan(null, null, null, 'database-header-change-counter:epoch=248:master-current:reset=second')['current_database_header_change_counter_token'], 'database-header-change-counter:epoch=248:master-current:reset=second'],
    'changed database header schema-cookie invalidates admitted pages' => [static fn (): mixed => $plan(null, null, null, null, 'database-header-schema-cookie:epoch=248:schema-cookie=104:master-current')['database_header_schema_cookie_invalidated_cache_page_numbers'], [1]],
    'changed database header schema-cookie surfaced' => [static fn (): mixed => $plan(null, null, null, null, 'database-header-schema-cookie:epoch=248:schema-cookie=104:master-current')['current_database_header_schema_cookie_token'], 'database-header-schema-cookie:epoch=248:schema-cookie=104:master-current'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 10],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
    'provenance token embeds epoch' => [static fn (): mixed => str_contains($currentSourceProvenanceToken, 'epoch=256'), true],
    'provenance token embeds schema' => [static fn (): mixed => str_contains($currentSourceProvenanceToken, 'schema=103'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next256 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current source provenance token rejected' => static fn () => $plan(null, null, ''),
    'empty database header change-counter token rejected' => static fn () => $plan(null, null, null, ''),
    'empty database header schema-cookie token rejected' => static fn () => $plan(null, null, null, null, ''),
    'cache missing source token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['current_source_provenance_token' => true])]),
    'cache empty source token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['current_source_provenance_token' => ''])]),
    'cache missing database header change-counter token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-snapshot', $recovered[1]), ['database_header_change_counter_token' => true])]),
    'cache empty database header change-counter token rejected' => static fn () => $plan([1 => $cacheEntry('empty-snapshot', $recovered[1], ['database_header_change_counter_token' => ''])]),
    'cache missing database header schema-cookie token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-schema-cookie', $recovered[1]), ['database_header_schema_cookie_token' => true])]),
    'cache empty database header schema-cookie token rejected' => static fn () => $plan([1 => $cacheEntry('empty-schema-cookie', $recovered[1], ['database_header_schema_cookie_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing source token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['current_source_provenance_token' => true])]),
    'read empty source token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['current_source_provenance_token' => ''])]),
    'read missing database header change-counter token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_header_change_counter_token' => true])]),
    'read empty database header change-counter token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['database_header_change_counter_token' => ''])]),
    'read missing database header schema-cookie token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_header_schema_cookie_token' => true])]),
    'read empty database header schema-cookie token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['database_header_schema_cookie_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next256 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
