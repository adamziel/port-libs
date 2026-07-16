<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next245.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next245-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-rootpage-map-next245';
$publication = 245;
$masterDigest = hash('sha256', 'next245-master-source');
$recoverySequence = 245;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2450:size=96:mtime=24500:generation=master-current';
$databaseToken = 'dev=8:ino=2459:size=4096:mtime=24599:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=245:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=245:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=245:schema=101:change-counter=245:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=238:schema=100:change-counter=238:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=245:schema-cookie=101:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=238:schema-cookie=100:ddl=before-master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=245:schema-cookie=101:master-current';
$oldSharedCacheGenerationToken = 'shared-cache-generation:epoch=238:schema-cookie=100:before-master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=245:stmt-cache=wp-options:master-current';
$oldStatementSnapshotToken = 'statement-snapshot:epoch=238:stmt-cache=wp-options:before-master-current';
$rootpageMapToken = 'rootpage-map:epoch=245:wp_options=2:autoload=4:option_name=6:users=8';
$oldRootpageMapToken = 'rootpage-map:epoch=238:wp_options=2:autoload=5:option_name=7:users=8';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=238:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24590:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2459:size=4096:mtime=24598:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 245), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503245), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next245 stale schema before rootpage map recovery'),
    2 => $page('next245 stale wp_options root before rootpage map recovery'),
    3 => $page('next245 stale active_plugins before rootpage map recovery'),
    4 => $page('next245 stale autoload index before rootpage map recovery'),
    5 => $page('next245 stale usermeta before rootpage map recovery'),
    6 => $page('next245 stale comments before rootpage map recovery'),
    7 => $page('next245 stale terms before rootpage map recovery'),
    8 => $page('next245 stale rewrite_rules before rootpage map recovery'),
];
$recovered = [
    1 => $formatPage('next245 current schema after rootpage map recovery'),
    2 => $page('next245 current wp_options root after rootpage map recovery'),
    3 => $page('next245 current active_plugins after rootpage map recovery'),
    4 => $page('next245 current autoload index after rootpage map recovery'),
    5 => $page('next245 current usermeta after rootpage map recovery'),
    6 => $page('next245 current comments after rootpage map recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 245, 0x57503245]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 238, 0x57503238]));
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
    $mainJournal => 'dev=8:ino=2451:size=4096:mtime=24501:generation=main-current',
    $usersJournal => 'dev=8:ino=2452:size=1024:mtime=24502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-245'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-245'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-245'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 245,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-rootpage', $recovered[1]),
    2 => $cacheEntry('root-stale-rootpage-map', $recovered[2], ['rootpage_map_token' => $oldRootpageMapToken]),
    3 => $cacheEntry('active-stale-statement-snapshot', $recovered[3], ['statement_snapshot_token' => $oldStatementSnapshotToken]),
    4 => $cacheEntry('autoload-stale-shared-generation', $recovered[4], ['shared_cache_generation_token' => $oldSharedCacheGenerationToken]),
    5 => $cacheEntry('usermeta-stale-schema-reparse', $recovered[5], ['schema_reparse_token' => $oldSchemaReparseToken]),
    6 => $cacheEntry('comments-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('terms-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('rewrite-dirty-rootpage', $page('next245 dirty rewrite cache'), ['dirty' => true, 'format_signature' => $oldFormatSignature, 'master_journal_cleanup_token' => $oldCleanupToken]),
];
$reads = static fn (
    ?string $schemaToken = null,
    ?string $readTransaction = null,
    ?string $pagerCache = null,
    ?string $sharedGeneration = null,
    ?string $statementSnapshot = null,
    ?string $rootpageMap = null,
): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 245,
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
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentSchemaToken = null,
    ?string $currentSharedGenerationToken = null,
    ?string $currentStatementSnapshotToken = null,
    ?string $currentRootpageMapToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderPublicationFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    245,
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
    $currentSchemaToken ?? $schemaReparseToken,
    $currentSharedGenerationToken ?? $sharedCacheGenerationToken,
    $currentStatementSnapshotToken ?? $statementSnapshotToken,
    $currentRootpageMapToken ?? $rootpageMapToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next245'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_schema_rootpage_map_before_current_source_reuse'],
    'current rootpage map token' => [static fn (): mixed => $plan()['current_rootpage_map_token'], $rootpageMapToken],
    'inherits statement snapshot token' => [static fn (): mixed => $plan()['current_statement_snapshot_token'], $statementSnapshotToken],
    'inherits shared generation token' => [static fn (): mixed => $plan()['current_shared_cache_generation_token'], $sharedCacheGenerationToken],
    'rootpage map invalidated pages' => [static fn (): mixed => $plan()['rootpage_map_invalidated_cache_page_numbers'], [2]],
    'statement snapshot invalidated pages' => [static fn (): mixed => $plan()['statement_snapshot_invalidated_cache_page_numbers'], [3]],
    'shared generation invalidated pages' => [static fn (): mixed => $plan()['shared_cache_generation_invalidated_cache_page_numbers'], [4]],
    'schema reparse invalidated pages' => [static fn (): mixed => $plan()['schema_reparse_invalidated_cache_page_numbers'], [5]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale rootpage' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale statement' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale rootpage map cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_rootpage_map_after_current_source_next245'), 1],
    'operation reopens stale rootpage reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_rootpage_map_after_current_source_next245'), 1],
    'dependency next245' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next245', $plan()['dependencies'], true), true],
    'dependency rootpage map fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-rootpage-map-fence', $plan()['dependencies'], true), true],
    'dependency next242 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next242', $plan()['dependencies'], true), true],
    'non overlap mentions next242' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next242 statement snapshots'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-rootpage')['rootpage_map_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-rootpage')['rootpage_map_token_reason'], 'reader_cache_rootpage_map_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-rootpage')['cache_rootpage_map_token'], $rootpageMapToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-rootpage')['current_rootpage_map_token'], $rootpageMapToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-rootpage')['rootpage_map_token_matches'], true],
    'row rootpage stale statement admitted' => [static fn (): mixed => $row('root-stale-rootpage-map')['statement_snapshot_token_admitted'], true],
    'row rootpage stale admitted false' => [static fn (): mixed => $row('root-stale-rootpage-map')['rootpage_map_token_admitted'], false],
    'row rootpage stale reason' => [static fn (): mixed => $row('root-stale-rootpage-map')['rootpage_map_token_reason'], 'reader_cache_rootpage_map_predates_master_journal_current_source'],
    'row rootpage stale cache token' => [static fn (): mixed => $row('root-stale-rootpage-map')['cache_rootpage_map_token'], $oldRootpageMapToken],
    'row rootpage stale current token' => [static fn (): mixed => $row('root-stale-rootpage-map')['current_rootpage_map_token'], $rootpageMapToken],
    'row rootpage stale mismatch' => [static fn (): mixed => $row('root-stale-rootpage-map')['rootpage_map_token_matches'], false],
    'row statement stale rootpage admitted false' => [static fn (): mixed => $row('active-stale-statement-snapshot')['rootpage_map_token_admitted'], false],
    'row statement stale inherits reason' => [static fn (): mixed => $row('active-stale-statement-snapshot')['rootpage_map_token_reason'], 'reader_cache_statement_snapshot_predates_master_journal_current_source'],
    'row shared stale inherits reason' => [static fn (): mixed => $row('autoload-stale-shared-generation')['rootpage_map_token_reason'], 'reader_cache_shared_cache_generation_predates_master_journal_current_source'],
    'row stale reparse inherits reason' => [static fn (): mixed => $row('usermeta-stale-schema-reparse')['rootpage_map_token_reason'], 'reader_cache_schema_reparse_token_predates_master_journal_current_source'],
    'row database token inherits reason' => [static fn (): mixed => $row('comments-stale-database-token')['rootpage_map_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('terms-stale-header')['rootpage_map_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('rewrite-dirty-rootpage')['rootpage_map_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained rootpage current' => [static fn (): mixed => $read('read-1')['rootpage_map_token_current'], true],
    'read retained rootpage surfaced' => [static fn (): mixed => $read('read-1')['rootpage_map_token'], $rootpageMapToken],
    'read retained statement snapshot current' => [static fn (): mixed => $read('read-1')['statement_snapshot_token_current'], true],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale rootpage source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-reader-cache-rootpage-map-fence-current-source-next245'],
    'read stale rootpage reason' => [static fn (): mixed => $read('read-2')['rootpage_map_token_reason'], 'reader_cache_reopened_after_rootpage_map_change'],
    'read stale statement source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-statement-snapshot-fence-current-source-next242'],
    'read stale statement reason' => [static fn (): mixed => $read('read-3')['statement_snapshot_token_reason'], 'reader_cache_reopened_after_statement_snapshot_change'],
    'stale rootpage read ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldRootpageMapToken))['read_cache_hits']['read-1'], false],
    'stale rootpage read ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldRootpageMapToken))['next_reads'][0]['rootpage_map_token_reason'], 'reader_ticket_rootpage_map_predates_current_source'],
    'stale rootpage read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldRootpageMapToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale rootpage ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, null, null, $oldRootpageMapToken)), 'reopen_reader_for_rootpage_map_after_current_source_next245'), 8],
    'stale statement ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldStatementSnapshotToken))['next_reads'][0]['statement_snapshot_token_reason'], 'reader_ticket_statement_snapshot_predates_current_source'],
    'stale shared ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['next_reads'][0]['shared_cache_generation_token_reason'], 'reader_ticket_shared_cache_generation_predates_current_source'],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no rootpage invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['rootpage_map_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current rootpage invalidates admitted pages' => [static fn (): mixed => $plan(null, null, null, null, null, 'rootpage-map:epoch=246:wp_options=9:autoload=10')['rootpage_map_invalidated_cache_page_numbers'], [1, 2]],
    'changed current rootpage surfaced' => [static fn (): mixed => $plan(null, null, null, null, null, 'rootpage-map:epoch=246:wp_options=9:autoload=10')['current_rootpage_map_token'], 'rootpage-map:epoch=246:wp_options=9:autoload=10'],
    'changed current statement invalidates before rootpage' => [static fn (): mixed => $plan(null, null, null, null, 'statement-snapshot:epoch=246:stmt-cache=after-current')['statement_snapshot_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed current statement prevents rootpage admission' => [static fn (): mixed => $plan(null, null, null, null, 'statement-snapshot:epoch=246:stmt-cache=after-current')['rootpage_map_invalidated_cache_page_numbers'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next245 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty rootpage map token rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'cache missing rootpage map token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-rootpage', $recovered[1]), ['rootpage_map_token' => true])]),
    'cache empty rootpage map token rejected' => static fn () => $plan([1 => $cacheEntry('empty-rootpage', $recovered[1], ['rootpage_map_token' => ''])]),
    'cache missing statement snapshot token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-statement', $recovered[1]), ['statement_snapshot_token' => true])]),
    'cache empty statement snapshot token rejected' => static fn () => $plan([1 => $cacheEntry('empty-statement', $recovered[1], ['statement_snapshot_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing rootpage map token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['rootpage_map_token' => true])]),
    'read empty rootpage map token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['rootpage_map_token' => ''])]),
    'read missing statement snapshot token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['statement_snapshot_token' => true])]),
    'read empty statement snapshot token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['statement_snapshot_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next245 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
