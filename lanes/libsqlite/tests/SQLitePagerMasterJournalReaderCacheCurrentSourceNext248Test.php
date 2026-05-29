<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next248.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next248-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-page-owner-next248';
$publication = 248;
$masterDigest = hash('sha256', 'next248-master-source');
$recoverySequence = 248;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2480:size=96:mtime=24800:generation=master-current';
$databaseToken = 'dev=8:ino=2489:size=4096:mtime=24899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24800:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=248:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=248:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=248:schema=108:change-counter=248:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=245:schema=107:change-counter=245:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=248:schema-cookie=108:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=245:schema-cookie=107:ddl=before-master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=248:schema-cookie=108:master-current';
$oldSharedCacheGenerationToken = 'shared-cache-generation:epoch=245:schema-cookie=107:before-master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=248:stmt-cache=wp-options:master-current';
$oldStatementSnapshotToken = 'statement-snapshot:epoch=245:stmt-cache=wp-options:before-master-current';
$rootpageMapToken = 'rootpage-map:epoch=248:wp_options=2:autoload=4:option_name=6:users=8';
$oldRootpageMapToken = 'rootpage-map:epoch=245:wp_options=2:autoload=5:option_name=7:users=8';
$pageOwnerMapToken = 'page-owner-map:epoch=248:p1=schema:p2=wp_options:p3=active_plugins:p4=autoload:p5=usermeta:p6=comments';
$oldPageOwnerMapToken = 'page-owner-map:epoch=245:p1=schema:p2=wp_options:p3=freelist:p4=autoload:p5=usermeta:p6=comments';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=245:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24890:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2489:size=4096:mtime=24898:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 248), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503248), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next248 stale schema before page owner recovery'),
    2 => $page('next248 stale wp_options root before page owner recovery'),
    3 => $page('next248 stale active_plugins before page owner recovery'),
    4 => $page('next248 stale autoload index before page owner recovery'),
    5 => $page('next248 stale usermeta before page owner recovery'),
    6 => $page('next248 stale comments before page owner recovery'),
    7 => $page('next248 stale terms before page owner recovery'),
    8 => $page('next248 stale rewrite rules before page owner recovery'),
];
$recovered = [
    1 => $formatPage('next248 current schema after page owner recovery'),
    2 => $page('next248 current wp_options root after page owner recovery'),
    3 => $page('next248 current active_plugins after page owner recovery'),
    4 => $page('next248 current autoload index after page owner recovery'),
    5 => $page('next248 current usermeta after page owner recovery'),
    6 => $page('next248 current comments after page owner recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 248, 0x57503248]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 245, 0x57503245]));
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
    $mainJournal => 'dev=8:ino=2481:size=4096:mtime=24801:generation=main-current',
    $usersJournal => 'dev=8:ino=2482:size=1024:mtime=24802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-248'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-248'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-248'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 248,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
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
    'shared_cache_generation_token' => $sharedCacheGenerationToken,
    'statement_snapshot_token' => $statementSnapshotToken,
    'rootpage_map_token' => $rootpageMapToken,
    'page_owner_map_token' => $pageOwnerMapToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-owner', $recovered[1]),
    2 => $cacheEntry('root-stale-owner-map', $recovered[2], ['page_owner_map_token' => $oldPageOwnerMapToken]),
    3 => $cacheEntry('active-stale-rootpage-map', $recovered[3], ['rootpage_map_token' => $oldRootpageMapToken]),
    4 => $cacheEntry('autoload-stale-statement-snapshot', $recovered[4], ['statement_snapshot_token' => $oldStatementSnapshotToken]),
    5 => $cacheEntry('usermeta-stale-shared-generation', $recovered[5], ['shared_cache_generation_token' => $oldSharedCacheGenerationToken]),
    6 => $cacheEntry('comments-stale-schema-reparse', $recovered[6], ['schema_reparse_token' => $oldSchemaReparseToken]),
    7 => $cacheEntry('terms-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('rewrite-dirty-owner', $page('next248 dirty rewrite cache'), ['dirty' => true, 'format_signature' => $oldFormatSignature, 'master_journal_cleanup_token' => $oldCleanupToken, 'database_file_token' => $oldDatabaseToken]),
];
$reads = static fn (
    ?string $schemaToken = null,
    ?string $readTransaction = null,
    ?string $pagerCache = null,
    ?string $sharedGeneration = null,
    ?string $statementSnapshot = null,
    ?string $rootpageMap = null,
    ?string $pageOwnerMap = null,
): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 248,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
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
        'shared_cache_generation_token' => $sharedGeneration ?? $sharedCacheGenerationToken,
        'statement_snapshot_token' => $statementSnapshot ?? $statementSnapshotToken,
        'rootpage_map_token' => $rootpageMap ?? $rootpageMapToken,
        'page_owner_map_token' => $pageOwnerMap ?? $pageOwnerMapToken,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentRootpageMapToken = null,
    ?string $currentPageOwnerMapToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext248(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    248,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $readTransactionToken,
    $schemaReparseToken,
    $sharedCacheGenerationToken,
    $statementSnapshotToken,
    $currentRootpageMapToken ?? $rootpageMapToken,
    $currentPageOwnerMapToken ?? $pageOwnerMapToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next248'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_btree_page_owner_map_before_current_source_reuse'],
    'current owner map token' => [static fn (): mixed => $plan()['current_page_owner_map_token'], $pageOwnerMapToken],
    'inherits rootpage map token' => [static fn (): mixed => $plan()['current_rootpage_map_token'], $rootpageMapToken],
    'owner map invalidated pages' => [static fn (): mixed => $plan()['page_owner_map_invalidated_cache_page_numbers'], [2]],
    'rootpage map invalidated pages' => [static fn (): mixed => $plan()['rootpage_map_invalidated_cache_page_numbers'], [3]],
    'statement snapshot invalidated pages' => [static fn (): mixed => $plan()['statement_snapshot_invalidated_cache_page_numbers'], [4]],
    'shared generation invalidated pages' => [static fn (): mixed => $plan()['shared_cache_generation_invalidated_cache_page_numbers'], [5]],
    'schema reparse invalidated pages' => [static fn (): mixed => $plan()['schema_reparse_invalidated_cache_page_numbers'], [6]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale owner' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale rootpage' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale owner cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_page_owner_map_after_current_source_next248'), 1],
    'operation reopens stale owner reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_page_owner_map_after_current_source_next248'), 1],
    'dependency next248' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next248', $plan()['dependencies'], true), true],
    'dependency owner map fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-page-owner-map-fence', $plan()['dependencies'], true), true],
    'dependency next245 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next245', $plan()['dependencies'], true), true],
    'non overlap mentions next245' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next245 rootpage-map'), true],
    'non overlap mentions page image receipts' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'page-image digest receipts'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-owner')['page_owner_map_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-owner')['page_owner_map_token_reason'], 'reader_cache_page_owner_map_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-owner')['cache_page_owner_map_token'], $pageOwnerMapToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-owner')['current_page_owner_map_token'], $pageOwnerMapToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-owner')['page_owner_map_token_matches'], true],
    'row stale owner admitted false' => [static fn (): mixed => $row('root-stale-owner-map')['page_owner_map_token_admitted'], false],
    'row stale owner reason' => [static fn (): mixed => $row('root-stale-owner-map')['page_owner_map_token_reason'], 'reader_cache_page_owner_map_predates_master_journal_current_source'],
    'row stale owner cache token' => [static fn (): mixed => $row('root-stale-owner-map')['cache_page_owner_map_token'], $oldPageOwnerMapToken],
    'row stale owner current token' => [static fn (): mixed => $row('root-stale-owner-map')['current_page_owner_map_token'], $pageOwnerMapToken],
    'row stale owner mismatch' => [static fn (): mixed => $row('root-stale-owner-map')['page_owner_map_token_matches'], false],
    'row rootpage inherits reason' => [static fn (): mixed => $row('active-stale-rootpage-map')['page_owner_map_token_reason'], 'reader_cache_rootpage_map_predates_master_journal_current_source'],
    'row statement inherits reason' => [static fn (): mixed => $row('autoload-stale-statement-snapshot')['page_owner_map_token_reason'], 'reader_cache_statement_snapshot_predates_master_journal_current_source'],
    'row shared inherits reason' => [static fn (): mixed => $row('usermeta-stale-shared-generation')['page_owner_map_token_reason'], 'reader_cache_shared_cache_generation_predates_master_journal_current_source'],
    'row schema inherits reason' => [static fn (): mixed => $row('comments-stale-schema-reparse')['page_owner_map_token_reason'], 'reader_cache_schema_reparse_token_predates_master_journal_current_source'],
    'row header inherits reason' => [static fn (): mixed => $row('terms-stale-header')['page_owner_map_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('rewrite-dirty-owner')['page_owner_map_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained owner current' => [static fn (): mixed => $read('read-1')['page_owner_map_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['page_owner_map_token'], $pageOwnerMapToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale owner cache miss' => [static fn (): mixed => $read('read-2')['cache_hit'], false],
    'read stale owner source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-reader-cache-page-owner-map-fence-current-source-next248'],
    'read stale owner reason' => [static fn (): mixed => $read('read-2')['page_owner_map_token_reason'], 'reader_cache_reopened_after_page_owner_map_change'],
    'stale owner ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, null, $oldPageOwnerMapToken))['read_cache_hits']['read-1'], false],
    'stale owner ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, null, $oldPageOwnerMapToken))['next_reads'][0]['page_owner_map_token_reason'], 'reader_ticket_page_owner_map_predates_current_source'],
    'stale owner ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, null, $oldPageOwnerMapToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale owner ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, null, null, null, $oldPageOwnerMapToken)), 'reopen_reader_for_page_owner_map_after_current_source_next248'), 8],
    'stale rootpage ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldRootpageMapToken))['next_reads'][0]['rootpage_map_token_reason'], 'reader_ticket_rootpage_map_predates_current_source'],
    'stale statement ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldStatementSnapshotToken))['next_reads'][0]['statement_snapshot_token_reason'], 'reader_ticket_statement_snapshot_predates_current_source'],
    'stale shared ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['next_reads'][0]['shared_cache_generation_token_reason'], 'reader_ticket_shared_cache_generation_predates_current_source'],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no owner invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['page_owner_map_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current owner invalidates admitted pages' => [static fn (): mixed => $plan(null, null, null, 'page-owner-map:epoch=249:p1=schema:p2=wp_options:p3=active_plugins')['page_owner_map_invalidated_cache_page_numbers'], [1, 2]],
    'changed current owner keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, null, 'page-owner-map:epoch=249:p1=schema:p2=wp_options:p3=active_plugins')['invalidated_cache_page_numbers'], true), true],
    'changed current owner surfaced' => [static fn (): mixed => $plan(null, null, null, 'page-owner-map:epoch=249:p1=schema:p2=wp_options:p3=active_plugins')['current_page_owner_map_token'], 'page-owner-map:epoch=249:p1=schema:p2=wp_options:p3=active_plugins'],
    'changed current rootpage still blocks owner admission' => [static fn (): mixed => $plan(null, null, 'rootpage-map:epoch=249:wp_options=2')['page_owner_map_invalidated_cache_page_numbers'], []],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 8],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next248 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty owner map token rejected' => static fn () => $plan(null, null, null, ''),
    'cache missing owner map token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-owner', $recovered[1]), ['page_owner_map_token' => true])]),
    'cache empty owner map token rejected' => static fn () => $plan([1 => $cacheEntry('empty-owner', $recovered[1], ['page_owner_map_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing owner map token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['page_owner_map_token' => true])]),
    'read empty owner map token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['page_owner_map_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next248 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
