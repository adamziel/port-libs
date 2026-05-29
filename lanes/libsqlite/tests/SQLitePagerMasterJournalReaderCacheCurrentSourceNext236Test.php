<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next236.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next236-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-schema-reparse-next236';
$publication = 236;
$masterDigest = hash('sha256', 'next236-master-source');
$recoverySequence = 236;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2360:size=96:mtime=23600:generation=master-current';
$databaseToken = 'dev=8:ino=2369:size=4096:mtime=23699:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=236:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=236:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=236:schema=96:change-counter=236:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=235:schema=95:change-counter=235:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=236:schema-cookie=96:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=235:schema-cookie=95:ddl=before-master-current';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=235:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=23690:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2369:size=4096:mtime=23698:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 236), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503236), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next236 stale schema before schema reparse recovery'),
    2 => $page('next236 stale wp_options root before schema reparse recovery'),
    3 => $page('next236 stale active_plugins before schema reparse recovery'),
    4 => $page('next236 stale usermeta before schema reparse recovery'),
    5 => $page('next236 stale rewrite_rules before schema reparse recovery'),
    6 => $page('next236 stale cron before schema reparse recovery'),
    7 => $page('next236 stale comments before schema reparse recovery'),
    8 => $page('next236 stale terms before schema reparse recovery'),
];
$recovered = [
    1 => $formatPage('next236 current schema after schema reparse recovery'),
    2 => $page('next236 current wp_options root after schema reparse recovery'),
    3 => $page('next236 current active_plugins after schema reparse recovery'),
    4 => $page('next236 current usermeta after schema reparse recovery'),
    6 => $page('next236 current cron after schema reparse recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 236, 0x57503236]));
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
    $mainJournal => 'dev=8:ino=2361:size=4096:mtime=23601:generation=main-current',
    $usersJournal => 'dev=8:ino=2362:size=1024:mtime=23602:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-236'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-236'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-236'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 236,
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
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-reparse', $recovered[1]),
    2 => $cacheEntry('root-refreshed-reparse', $before[2]),
    3 => $cacheEntry('active-stale-schema-reparse', $recovered[3], ['schema_reparse_token' => $oldSchemaReparseToken]),
    4 => $cacheEntry('usermeta-stale-read-transaction', $recovered[4], ['read_transaction_token' => $oldReadTransactionToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-reparse', $page('next236 dirty terms cache'), ['dirty' => true, 'format_signature' => $oldFormatSignature]),
];
$reads = static fn (?string $schemaToken = null, ?string $readTransaction = null, ?string $pagerCache = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 236,
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
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentSchemaToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext236(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    236,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next236'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_schema_reparse_before_current_source_reuse'],
    'current schema reparse token' => [static fn (): mixed => $plan()['current_schema_reparse_token'], $schemaReparseToken],
    'inherits read transaction token' => [static fn (): mixed => $plan()['current_read_transaction_token'], $readTransactionToken],
    'schema reparse invalidated pages' => [static fn (): mixed => $plan()['schema_reparse_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale schema reparse' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale schema reparse cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_schema_reparse_after_current_source_next236'), 1],
    'operation reopens stale schema reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_schema_reparse_after_current_source_next236'), 1],
    'dependency next236' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next236', $plan()['dependencies'], true), true],
    'dependency schema reparse fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-schema-reparse-fence', $plan()['dependencies'], true), true],
    'dependency next233 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next233', $plan()['dependencies'], true), true],
    'non overlap mentions next233' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next233 read-transaction'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_reason'], 'reader_cache_schema_reparse_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-reparse')['cache_schema_reparse_token'], $schemaReparseToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-reparse')['current_schema_reparse_token'], $schemaReparseToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-reparse')['schema_reparse_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-reparse')['schema_reparse_token_admitted'], true],
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
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale schema cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale schema source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-schema-reparse-fence-current-source-next236'],
    'read stale schema reason' => [static fn (): mixed => $read('read-3')['schema_reparse_token_reason'], 'reader_cache_reopened_after_schema_reparse_token_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSchemaReparseToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldSchemaReparseToken)), 'reopen_reader_for_schema_reparse_after_current_source_next236'), 8],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no schema invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['schema_reparse_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current schema invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['schema_reparse_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed current schema keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['invalidated_cache_page_numbers'], true), true],
    'changed current schema surfaced' => [static fn (): mixed => $plan(null, null, 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse')['current_schema_reparse_token'], 'schema-reparse:epoch=237:schema-cookie=97:ddl=after-reparse'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next236 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty schema reparse token rejected' => static fn () => $plan(null, null, ''),
    'cache missing schema reparse token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['schema_reparse_token' => true])]),
    'cache empty schema reparse token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['schema_reparse_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing schema reparse token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['schema_reparse_token' => true])]),
    'read empty schema reparse token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['schema_reparse_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next236 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
