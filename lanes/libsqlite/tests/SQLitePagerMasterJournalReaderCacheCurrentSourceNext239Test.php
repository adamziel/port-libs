<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next239.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next239-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-schema-reparse-next239';
$publication = 239;
$masterDigest = hash('sha256', 'next239-master-source');
$recoverySequence = 239;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2390:size=96:mtime=23900:generation=master-current';
$databaseToken = 'dev=8:ino=2399:size=4096:mtime=23999:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=239:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=239:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=239:schema=96:change-counter=239:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=235:schema=95:change-counter=235:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=239:schema-cookie=96:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=235:schema-cookie=95:ddl=before-master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=239:schema-cookie=96:master-current';
$oldSharedCacheGenerationToken = 'shared-cache-generation:epoch=235:schema-cookie=95:before-master-current';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=235:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=23990:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2399:size=4096:mtime=23998:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 239), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next239 stale schema before schema reparse recovery'),
    2 => $page('next239 stale wp_options root before schema reparse recovery'),
    3 => $page('next239 stale active_plugins before schema reparse recovery'),
    4 => $page('next239 stale usermeta before schema reparse recovery'),
    5 => $page('next239 stale rewrite_rules before schema reparse recovery'),
    6 => $page('next239 stale cron before schema reparse recovery'),
    7 => $page('next239 stale comments before schema reparse recovery'),
    8 => $page('next239 stale terms before schema reparse recovery'),
];
$recovered = [
    1 => $formatPage('next239 current schema after schema reparse recovery'),
    2 => $page('next239 current wp_options root after schema reparse recovery'),
    3 => $page('next239 current active_plugins after schema reparse recovery'),
    4 => $page('next239 current usermeta after schema reparse recovery'),
    6 => $page('next239 current cron after schema reparse recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 239, 0x57503239]));
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
    $mainJournal => 'dev=8:ino=2391:size=4096:mtime=23901:generation=main-current',
    $usersJournal => 'dev=8:ino=2392:size=1024:mtime=23902:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-239'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-239'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-239'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 239,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-reparse', $recovered[1]),
    2 => $cacheEntry('root-stale-shared-generation', $before[2], ['shared_cache_generation_token' => $oldSharedCacheGenerationToken]),
    3 => $cacheEntry('active-stale-schema-reparse', $recovered[3], ['schema_reparse_token' => $oldSchemaReparseToken]),
    4 => $cacheEntry('usermeta-stale-read-transaction', $recovered[4], ['read_transaction_token' => $oldReadTransactionToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-reparse', $page('next239 dirty terms cache'), ['dirty' => true, 'format_signature' => $oldFormatSignature]),
];
$reads = static fn (?string $schemaToken = null, ?string $readTransaction = null, ?string $pagerCache = null, ?string $sharedGeneration = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 239,
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
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentSchemaToken = null,
    ?string $currentSharedGenerationToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReadGroupTokenFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    239,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next239'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_shared_cache_generation_before_current_source_reuse'],
    'current schema reparse token' => [static fn (): mixed => $plan()['current_schema_reparse_token'], $schemaReparseToken],
    'current shared generation token' => [static fn (): mixed => $plan()['current_shared_cache_generation_token'], $sharedCacheGenerationToken],
    'inherits read transaction token' => [static fn (): mixed => $plan()['current_read_transaction_token'], $readTransactionToken],
    'schema reparse invalidated pages' => [static fn (): mixed => $plan()['schema_reparse_invalidated_cache_page_numbers'], [3]],
    'shared generation invalidated pages' => [static fn (): mixed => $plan()['shared_cache_generation_invalidated_cache_page_numbers'], [2]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale shared generation' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale schema reparse' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale shared generation cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_shared_generation_after_current_source_next239'), 1],
    'operation reopens stale shared generation reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_shared_generation_after_current_source_next239'), 1],
    'dependency next239' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next239', $plan()['dependencies'], true), true],
    'dependency shared generation fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-shared-generation-fence', $plan()['dependencies'], true), true],
    'dependency next236 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next236', $plan()['dependencies'], true), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next236 schema-reparse'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_reason'], 'reader_cache_schema_reparse_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-reparse')['cache_schema_reparse_token'], $schemaReparseToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-reparse')['current_schema_reparse_token'], $schemaReparseToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_matches'], true],
    'row shared stale schema admitted' => [static fn (): mixed => $row('root-stale-shared-generation')['schema_reparse_token_admitted'], true],
    'row shared stale generation admitted false' => [static fn (): mixed => $row('root-stale-shared-generation')['shared_cache_generation_token_admitted'], false],
    'row shared stale generation reason' => [static fn (): mixed => $row('root-stale-shared-generation')['shared_cache_generation_token_reason'], 'reader_cache_shared_cache_generation_predates_master_journal_current_source'],
    'row shared stale generation cache token' => [static fn (): mixed => $row('root-stale-shared-generation')['cache_shared_cache_generation_token'], $oldSharedCacheGenerationToken],
    'row shared stale generation current token' => [static fn (): mixed => $row('root-stale-shared-generation')['current_shared_cache_generation_token'], $sharedCacheGenerationToken],
    'row shared stale generation mismatch' => [static fn (): mixed => $row('root-stale-shared-generation')['shared_cache_generation_token_matches'], false],
    'row stale reparse admitted false' => [static fn (): mixed => $row('active-stale-schema-reparse')['schema_reparse_token_admitted'], false],
    'row stale reparse reason' => [static fn (): mixed => $row('active-stale-schema-reparse')['schema_reparse_token_reason'], 'reader_cache_schema_reparse_token_predates_master_journal_current_source'],
    'row stale reparse cache token' => [static fn (): mixed => $row('active-stale-schema-reparse')['cache_schema_reparse_token'], $oldSchemaReparseToken],
    'row stale reparse current token' => [static fn (): mixed => $row('active-stale-schema-reparse')['current_schema_reparse_token'], $schemaReparseToken],
    'row stale reparse mismatch' => [static fn (): mixed => $row('active-stale-schema-reparse')['schema_reparse_token_matches'], false],
    'row read transaction inherits reason' => [static fn (): mixed => $row('usermeta-stale-read-transaction')['schema_reparse_token_reason'], 'reader_cache_read_transaction_token_predates_master_journal_current_source'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['schema_reparse_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['schema_reparse_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['schema_reparse_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-reparse')['schema_reparse_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained schema current' => [static fn (): mixed => $read('read-1')['schema_reparse_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['schema_reparse_token'], $schemaReparseToken],
    'read retained shared generation current' => [static fn (): mixed => $read('read-1')['shared_cache_generation_token_current'], true],
    'read retained shared generation surfaced' => [static fn (): mixed => $read('read-1')['shared_cache_generation_token'], $sharedCacheGenerationToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale shared generation source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-reader-cache-shared-generation-fence-current-source-next239'],
    'read stale shared generation reason' => [static fn (): mixed => $read('read-2')['shared_cache_generation_token_reason'], 'reader_cache_reopened_after_shared_cache_generation_change'],
    'read stale schema cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale schema source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-schema-reparse-fence-current-source-next236'],
    'read stale schema reason' => [static fn (): mixed => $read('read-3')['schema_reparse_token_reason'], 'reader_cache_reopened_after_schema_reparse_token_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldSchemaReparseToken)), 'reopen_reader_for_schema_reparse_after_current_source_next236'), 8],
    'stale shared generation ticket cache miss' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['read_cache_hits']['read-1'], false],
    'stale shared generation ticket reason' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['next_reads'][0]['shared_cache_generation_token_reason'], 'reader_ticket_shared_cache_generation_predates_current_source'],
    'stale shared generation ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale shared generation ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads(null, null, null, $oldSharedCacheGenerationToken)), 'reopen_reader_for_shared_generation_after_current_source_next239'), 8],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no schema invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['schema_reparse_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current schema invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['schema_reparse_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed current schema keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['invalidated_cache_page_numbers'], true), true],
    'changed current schema surfaced' => [static fn (): mixed => $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['current_schema_reparse_token'], 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse'],
    'changed current shared generation invalidates admitted pages' => [static fn (): mixed => $plan(null, null, null, 'shared-cache-generation:epoch=240:schema-cookie=100:after-master-current')['shared_cache_generation_invalidated_cache_page_numbers'], [1, 2]],
    'changed current shared generation surfaced' => [static fn (): mixed => $plan(null, null, null, 'shared-cache-generation:epoch=240:schema-cookie=100:after-master-current')['current_shared_cache_generation_token'], 'shared-cache-generation:epoch=240:schema-cookie=100:after-master-current'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next239 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty shared generation token rejected' => static fn () => $plan(null, null, null, ''),
    'cache missing schema reparse token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['schema_reparse_token' => true])]),
    'cache empty schema reparse token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['schema_reparse_token' => ''])]),
    'cache missing shared generation token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-shared', $recovered[1]), ['shared_cache_generation_token' => true])]),
    'cache empty shared generation token rejected' => static fn () => $plan([1 => $cacheEntry('empty-shared', $recovered[1], ['shared_cache_generation_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing schema reparse token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['schema_reparse_token' => true])]),
    'read empty schema reparse token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['schema_reparse_token' => ''])]),
    'read missing shared generation token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['shared_cache_generation_token' => true])]),
    'read empty shared generation token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['shared_cache_generation_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next239 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
