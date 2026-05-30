<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next242.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next242-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-statement-snapshot-next242';
$publication = 242;
$masterDigest = hash('sha256', 'next242-master-source');
$recoverySequence = 242;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2420:size=96:mtime=24200:generation=master-current';
$databaseToken = 'dev=8:ino=2429:size=4096:mtime=24299:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=242:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=242:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=242:schema=96:change-counter=242:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=235:schema=95:change-counter=235:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=242:schema-cookie=96:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=235:schema-cookie=95:ddl=before-master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=242:schema-cookie=96:master-current';
$oldSharedCacheGenerationToken = 'shared-cache-generation:epoch=235:schema-cookie=95:before-master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=242:stmt-cache=wp-options:master-current';
$oldStatementSnapshotToken = 'statement-snapshot:epoch=235:stmt-cache=wp-options:before-master-current';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=235:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24290:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2429:size=4096:mtime=24298:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 242), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503242), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next242 stale schema before statement snapshot recovery'),
    2 => $page('next242 stale wp_options root before statement snapshot recovery'),
    3 => $page('next242 stale active_plugins before statement snapshot recovery'),
    4 => $page('next242 stale usermeta before statement snapshot recovery'),
    5 => $page('next242 stale rewrite_rules before statement snapshot recovery'),
    6 => $page('next242 stale cron before statement snapshot recovery'),
    7 => $page('next242 stale comments before statement snapshot recovery'),
    8 => $page('next242 stale terms before statement snapshot recovery'),
];
$recovered = [
    1 => $formatPage('next242 current schema after statement snapshot recovery'),
    2 => $page('next242 current wp_options root after statement snapshot recovery'),
    3 => $page('next242 current active_plugins after statement snapshot recovery'),
    4 => $page('next242 current usermeta after statement snapshot recovery'),
    6 => $page('next242 current cron after statement snapshot recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 242, 0x57503242]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 235, 0x57503235]));
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
    $mainJournal => 'dev=8:ino=2421:size=4096:mtime=24201:generation=main-current',
    $usersJournal => 'dev=8:ino=2422:size=1024:mtime=24202:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-242'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-242'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-242'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 242,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-reparse', $recovered[1]),
    2 => $cacheEntry('root-stale-statement-snapshot', $before[2], ['statement_snapshot_token' => $oldStatementSnapshotToken]),
    3 => $cacheEntry('active-stale-shared-generation', $recovered[3], ['shared_cache_generation_token' => $oldSharedCacheGenerationToken]),
    4 => $cacheEntry('usermeta-stale-schema-reparse', $recovered[4], ['schema_reparse_token' => $oldSchemaReparseToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-reparse', $page('next242 dirty terms cache'), ['dirty' => true, 'format_signature' => $oldFormatSignature]),
];
$reads = static fn (?string $schemaToken = null, ?string $readTransaction = null, ?string $pagerCache = null, ?string $sharedGeneration = null, ?string $statementSnapshot = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 242,
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
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentSchemaToken = null,
    ?string $currentSharedGenerationToken = null,
    ?string $currentStatementSnapshotToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planStatementSnapshotFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    242,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next242'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_statement_snapshot_before_current_source_reuse'],
    'current schema reparse token' => [static fn (): mixed => $plan()['current_schema_reparse_token'], $schemaReparseToken],
    'current shared generation token' => [static fn (): mixed => $plan()['current_shared_cache_generation_token'], $sharedCacheGenerationToken],
    'current statement snapshot token' => [static fn (): mixed => $plan()['current_statement_snapshot_token'], $statementSnapshotToken],
    'inherits read transaction token' => [static fn (): mixed => $plan()['current_read_transaction_token'], $readTransactionToken],
    'statement snapshot invalidated pages' => [static fn (): mixed => $plan()['statement_snapshot_invalidated_cache_page_numbers'], [2]],
    'shared generation invalidated pages' => [static fn (): mixed => $plan()['shared_cache_generation_invalidated_cache_page_numbers'], [3]],
    'schema reparse invalidated pages' => [static fn (): mixed => $plan()['schema_reparse_invalidated_cache_page_numbers'], [4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale statement snapshot' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale shared generation' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale statement snapshot cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_statement_snapshot_after_current_source_next242'), 1],
    'operation reopens stale statement snapshot reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_statement_snapshot_after_current_source_next242'), 1],
    'dependency next242' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next242', $plan()['dependencies'], true), true],
    'dependency statement snapshot fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-statement-snapshot-fence', $plan()['dependencies'], true), true],
    'dependency next239 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next239', $plan()['dependencies'], true), true],
    'non overlap mentions next239' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next239 shared schema-cache generation'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_reason'], 'reader_cache_schema_reparse_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-reparse')['cache_schema_reparse_token'], $schemaReparseToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-reparse')['current_schema_reparse_token'], $schemaReparseToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_matches'], true],
    'row statement stale shared admitted' => [static fn (): mixed => $row('root-stale-statement-snapshot')['shared_cache_generation_token_admitted'], true],
    'row statement stale admitted false' => [static fn (): mixed => $row('root-stale-statement-snapshot')['statement_snapshot_token_admitted'], false],
    'row statement stale reason' => [static fn (): mixed => $row('root-stale-statement-snapshot')['statement_snapshot_token_reason'], 'reader_cache_statement_snapshot_predates_master_journal_current_source'],
    'row statement stale cache token' => [static fn (): mixed => $row('root-stale-statement-snapshot')['cache_statement_snapshot_token'], $oldStatementSnapshotToken],
    'row statement stale current token' => [static fn (): mixed => $row('root-stale-statement-snapshot')['current_statement_snapshot_token'], $statementSnapshotToken],
    'row statement stale mismatch' => [static fn (): mixed => $row('root-stale-statement-snapshot')['statement_snapshot_token_matches'], false],
    'row shared stale admitted false' => [static fn (): mixed => $row('active-stale-shared-generation')['shared_cache_generation_token_admitted'], false],
    'row shared stale reason' => [static fn (): mixed => $row('active-stale-shared-generation')['shared_cache_generation_token_reason'], 'reader_cache_shared_cache_generation_predates_master_journal_current_source'],
    'row stale reparse admitted false' => [static fn (): mixed => $row('usermeta-stale-schema-reparse')['schema_reparse_token_admitted'], false],
    'row stale reparse reason' => [static fn (): mixed => $row('usermeta-stale-schema-reparse')['schema_reparse_token_reason'], 'reader_cache_schema_reparse_token_predates_master_journal_current_source'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['schema_reparse_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['schema_reparse_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['schema_reparse_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-reparse')['schema_reparse_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained schema current' => [static fn (): mixed => $read('read-1')['schema_reparse_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['schema_reparse_token'], $schemaReparseToken],
    'read retained shared generation current' => [static fn (): mixed => $read('read-1')['shared_cache_generation_token_current'], true],
    'read retained shared generation surfaced' => [static fn (): mixed => $read('read-1')['shared_cache_generation_token'], $sharedCacheGenerationToken],
    'read retained statement snapshot current' => [static fn (): mixed => $read('read-1')['statement_snapshot_token_current'], true],
    'read retained statement snapshot surfaced' => [static fn (): mixed => $read('read-1')['statement_snapshot_token'], $statementSnapshotToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale statement snapshot source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-reader-cache-statement-snapshot-fence-current-source-next242'],
    'read stale statement snapshot reason' => [static fn (): mixed => $read('read-2')['statement_snapshot_token_reason'], 'reader_cache_reopened_after_statement_snapshot_change'],
    'read stale shared generation source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-shared-generation-fence-current-source-next239'],
    'read stale shared generation reason' => [static fn (): mixed => $read('read-3')['shared_cache_generation_token_reason'], 'reader_cache_reopened_after_shared_cache_generation_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldSchemaReparseToken)), 'reopen_reader_for_schema_reparse_after_current_source_next236'), 8],
    'stale shared generation ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['read_cache_hits']['read-1'], false],
    'stale shared generation ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['next_reads'][0]['shared_cache_generation_token_reason'], 'reader_ticket_shared_cache_generation_predates_current_source'],
    'stale shared generation ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale shared generation ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken)), 'reopen_reader_for_shared_generation_after_current_source_next239'), 8],
    'stale statement snapshot ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldStatementSnapshotToken))['read_cache_hits']['read-1'], false],
    'stale statement snapshot ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldStatementSnapshotToken))['next_reads'][0]['statement_snapshot_token_reason'], 'reader_ticket_statement_snapshot_predates_current_source'],
    'stale statement snapshot ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldStatementSnapshotToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale statement snapshot ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, null, $oldStatementSnapshotToken)), 'reopen_reader_for_statement_snapshot_after_current_source_next242'), 8],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no schema invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['schema_reparse_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current schema invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['schema_reparse_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'changed current schema keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['invalidated_cache_page_numbers'], true), true],
    'changed current schema surfaced' => [static fn (): mixed => $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['current_schema_reparse_token'], 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse'],
    'changed current shared generation invalidates admitted pages' => [static fn (): mixed => $plan(null, null, null, 'shared-cache-generation:epoch=240:schema-cookie=100:after-master-current')['shared_cache_generation_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed current shared generation surfaced' => [static fn (): mixed => $plan(null, null, null, 'shared-cache-generation:epoch=240:schema-cookie=100:after-master-current')['current_shared_cache_generation_token'], 'shared-cache-generation:epoch=240:schema-cookie=100:after-master-current'],
    'changed current statement snapshot invalidates admitted page' => [static fn (): mixed => $plan(null, null, null, null, 'statement-snapshot:epoch=243:stmt-cache=after-current')['statement_snapshot_invalidated_cache_page_numbers'], [1, 2]],
    'changed current statement snapshot surfaced' => [static fn (): mixed => $plan(null, null, null, null, 'statement-snapshot:epoch=243:stmt-cache=after-current')['current_statement_snapshot_token'], 'statement-snapshot:epoch=243:stmt-cache=after-current'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next242 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty statement snapshot token rejected' => static fn () => $plan(null, null, null, null, ''),
    'cache missing statement snapshot token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['statement_snapshot_token' => true])]),
    'cache empty statement snapshot token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['statement_snapshot_token' => ''])]),
    'cache missing shared generation token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-shared', $recovered[1]), ['shared_cache_generation_token' => true])]),
    'cache empty shared generation token rejected' => static fn () => $plan([1 => $cacheEntry('empty-shared', $recovered[1], ['shared_cache_generation_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing statement snapshot token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['statement_snapshot_token' => true])]),
    'read empty statement snapshot token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['statement_snapshot_token' => ''])]),
    'read missing shared generation token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['shared_cache_generation_token' => true])]),
    'read empty shared generation token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['shared_cache_generation_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next242 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
