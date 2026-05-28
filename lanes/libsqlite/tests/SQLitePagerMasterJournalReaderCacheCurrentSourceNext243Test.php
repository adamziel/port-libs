<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Plan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next243.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next243-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-current-source-provenance-next243';
$publication = 243;
$masterDigest = hash('sha256', 'next243-master-source');
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2430:size=96:mtime=24300:generation=master-current';
$databaseToken = 'dev=8:ino=2439:size=5120:mtime=24399:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24400:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=243:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=243:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=243:schema=103:change-counter=243:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=242:schema=102:change-counter=242:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=243:schema-cookie=103:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=242:schema-cookie=102:ddl=before-master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=243:root=1:cookie=103:sql=wp-options-current';
$oldStatementSchemaRootToken = 'statement-schema-root:epoch=242:root=1:cookie=102:sql=wp-options-prior';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=243:members=2:database-token=2439:schema=103';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=242:members=2:database-token=2438:schema=102';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=242:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24390:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2439:size=5120:mtime=24398:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 243), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503243), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next243 stale schema before source provenance recovery'),
    2 => $page('next243 stale wp_options root before source provenance recovery'),
    3 => $page('next243 stale active_plugins before source provenance recovery'),
    4 => $page('next243 stale usermeta before source provenance recovery'),
    5 => $page('next243 stale rewrite_rules before source provenance recovery'),
    6 => $page('next243 stale cron before source provenance recovery'),
    7 => $page('next243 stale comments before source provenance recovery'),
    8 => $page('next243 stale terms before source provenance recovery'),
    9 => $page('next243 stale options autoload statement before source provenance recovery'),
    10 => $page('next243 stale transient timeout before source provenance recovery'),
];
$recovered = [
    1 => $formatPage('next243 current schema after source provenance recovery'),
    2 => $page('next243 current wp_options root after source provenance recovery'),
    3 => $page('next243 current active_plugins after source provenance recovery'),
    4 => $page('next243 current usermeta after source provenance recovery'),
    6 => $page('next243 current cron after source provenance recovery'),
    9 => $page('next243 current options autoload statement after source provenance recovery'),
    10 => $page('next243 current transient timeout after source provenance recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 243, 0x57503243]));
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
    $mainJournal => 'dev=8:ino=2431:size=4096:mtime=24301:generation=main-current',
    $usersJournal => 'dev=8:ino=2432:size=1024:mtime=24302:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-243'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-243'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-243'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 243,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 243,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-source-provenance', $recovered[1]),
    2 => $cacheEntry('root-refreshed-source-provenance', $before[2]),
    3 => $cacheEntry('active-stale-source-provenance', $recovered[3], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    4 => $cacheEntry('usermeta-stale-statement-root', $recovered[4], ['statement_schema_root_token' => $oldStatementSchemaRootToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-source-provenance', $before[8], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
    9 => $cacheEntry('autoload-stale-source-provenance', $recovered[9], ['current_source_provenance_token' => 'current-source:master-journal:epoch=242:statement=autoload-prior']),
    10 => $cacheEntry('transient-retained-source-provenance', $recovered[10]),
];
$reads = static fn (
    ?string $sourceToken = null,
    ?string $statementToken = null,
    ?string $schemaToken = null,
    ?string $readTransaction = null,
    ?string $pagerCache = null,
): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 243,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => 243,
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
    ],
    range(1, 10),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentSourceToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Plan::plan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    243,
    $publication,
    $masterDigest,
    243,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next243'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_current_source_provenance_before_reuse'],
    'current source provenance token' => [static fn (): mixed => $plan()['current_source_provenance_token'], $currentSourceProvenanceToken],
    'inherits statement schema root token' => [static fn (): mixed => $plan()['current_statement_schema_root_token'], $statementSchemaRootToken],
    'source provenance invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [3, 9]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8, 9]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 10]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9']],
    'read hit retained schema' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed root' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale source' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit retained transient' => [static fn (): mixed => $plan()['read_cache_hits']['read-10'], true],
    'operation invalidates stale source cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_current_source_provenance_after_master_journal_next243'), 2],
    'operation reopens stale source reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_current_source_provenance_after_master_journal_next243'), 2],
    'dependency next243' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next243', $plan()['dependencies'], true), true],
    'dependency provenance fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-current-source-provenance-fence', $plan()['dependencies'], true), true],
    'dependency next240 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next240', $plan()['dependencies'], true), true],
    'non overlap mentions next240' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next240 statement-root'), true],
    'non overlap mentions wal checkpoint' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'WAL checkpoint'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-source-provenance')['current_source_provenance_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-source-provenance')['current_source_provenance_token_reason'], 'reader_cache_current_source_provenance_matches_master_journal_recovery'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-source-provenance')['cache_current_source_provenance_token'], $currentSourceProvenanceToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-source-provenance')['current_source_provenance_token'], $currentSourceProvenanceToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-source-provenance')['current_source_provenance_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-source-provenance')['current_source_provenance_token_admitted'], true],
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
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale source cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale source source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-current-source-provenance-fence-next243'],
    'read stale source reason' => [static fn (): mixed => $read('read-3')['current_source_provenance_token_reason'], 'reader_cache_reopened_after_current_source_provenance_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceProvenanceToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceProvenanceToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9', 'read-10']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldCurrentSourceProvenanceToken)), 'reopen_reader_for_current_source_provenance_after_master_journal_next243'), 10],
    'stale statement ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldStatementSchemaRootToken))['next_reads'][0]['statement_schema_root_token_reason'], 'reader_ticket_statement_schema_root_predates_current_source'],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no source invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['current_source_provenance_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current source invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104')['current_source_provenance_invalidated_cache_page_numbers'], [1, 2, 3, 9, 10]],
    'changed current source keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104')['invalidated_cache_page_numbers'], true), true],
    'changed current source surfaced' => [static fn (): mixed => $plan(null, null, 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104')['current_source_provenance_token'], 'current-source:master-journal:epoch=244:members=2:database-token=2440:schema=104'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 10],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
    'provenance token embeds epoch' => [static fn (): mixed => str_contains($currentSourceProvenanceToken, 'epoch=243'), true],
    'provenance token embeds schema' => [static fn (): mixed => str_contains($currentSourceProvenanceToken, 'schema=103'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next243 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current source provenance token rejected' => static fn () => $plan(null, null, ''),
    'cache missing source token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['current_source_provenance_token' => true])]),
    'cache empty source token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['current_source_provenance_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing source token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['current_source_provenance_token' => true])]),
    'read empty source token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['current_source_provenance_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next243 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
