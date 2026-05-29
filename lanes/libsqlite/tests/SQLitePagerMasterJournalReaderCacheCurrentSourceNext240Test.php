<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next240.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next240-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-statement-schema-root-next240';
$publication = 240;
$masterDigest = hash('sha256', 'next240-master-source');
$recoverySequence = 240;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2400:size=96:mtime=24000:generation=master-current';
$databaseToken = 'dev=8:ino=2409:size=4096:mtime=24099:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24100:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=240:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=240:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=240:schema=100:change-counter=240:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=239:schema=99:change-counter=239:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=240:schema-cookie=100:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=239:schema-cookie=99:ddl=before-master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=240:root=1:cookie=100:sql=wp-options-current';
$oldStatementSchemaRootToken = 'statement-schema-root:epoch=239:root=1:cookie=99:sql=wp-options-prior';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=239:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24090:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2409:size=4096:mtime=24098:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 240), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503240), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next240 stale schema before statement schema root recovery'),
    2 => $page('next240 stale wp_options root before statement schema root recovery'),
    3 => $page('next240 stale active_plugins before statement schema root recovery'),
    4 => $page('next240 stale usermeta before statement schema root recovery'),
    5 => $page('next240 stale rewrite_rules before statement schema root recovery'),
    6 => $page('next240 stale cron before statement schema root recovery'),
    7 => $page('next240 stale comments before statement schema root recovery'),
    8 => $page('next240 stale terms before statement schema root recovery'),
    9 => $page('next240 stale options autoload statement before statement schema root recovery'),
];
$recovered = [
    1 => $formatPage('next240 current schema after statement schema root recovery'),
    2 => $page('next240 current wp_options root after statement schema root recovery'),
    3 => $page('next240 current active_plugins after statement schema root recovery'),
    4 => $page('next240 current usermeta after statement schema root recovery'),
    6 => $page('next240 current cron after statement schema root recovery'),
    9 => $page('next240 current options autoload statement after statement schema root recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 240, 0x57503240]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 239, 0x57503239]));
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
    $mainJournal => 'dev=8:ino=2401:size=4096:mtime=24001:generation=main-current',
    $usersJournal => 'dev=8:ino=2402:size=1024:mtime=24002:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-240'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-240'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-240'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 240,
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
    'statement_schema_root_token' => $statementSchemaRootToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-statement-root', $recovered[1]),
    2 => $cacheEntry('root-refreshed-statement-root', $before[2]),
    3 => $cacheEntry('active-stale-statement-root', $recovered[3], ['statement_schema_root_token' => $oldStatementSchemaRootToken]),
    4 => $cacheEntry('usermeta-stale-schema-reparse', $recovered[4], ['schema_reparse_token' => $oldSchemaReparseToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-statement-root', $before[8], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
    9 => $cacheEntry('autoload-stale-statement-root', $recovered[9], ['statement_schema_root_token' => 'statement-schema-root:epoch=239:root=2:cookie=99:sql=autoload-prior']),
];
$reads = static fn (?string $statementToken = null, ?string $schemaToken = null, ?string $readTransaction = null, ?string $pagerCache = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 240,
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
        'statement_schema_root_token' => $statementToken ?? $statementSchemaRootToken,
    ],
    range(1, 9),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentStatementToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext240(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    240,
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
    $currentStatementToken ?? $statementSchemaRootToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next240'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_statement_schema_root_before_current_source_reuse'],
    'current statement schema root token' => [static fn (): mixed => $plan()['current_statement_schema_root_token'], $statementSchemaRootToken],
    'inherits schema reparse token' => [static fn (): mixed => $plan()['current_schema_reparse_token'], $schemaReparseToken],
    'statement invalidated pages' => [static fn (): mixed => $plan()['statement_schema_root_invalidated_cache_page_numbers'], [3, 9]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8, 9]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale statement root' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale statement root cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_statement_schema_root_after_current_source_next240'), 2],
    'operation reopens stale statement reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_statement_schema_root_after_current_source_next240'), 2],
    'dependency next240' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next240', $plan()['dependencies'], true), true],
    'dependency statement fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-statement-schema-root-fence', $plan()['dependencies'], true), true],
    'dependency next236 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next236', $plan()['dependencies'], true), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next236 schema-reparse'), true],
    'non overlap mentions vfs writer' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'VFS writer'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-statement-root')['statement_schema_root_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-statement-root')['statement_schema_root_token_reason'], 'reader_cache_statement_schema_root_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-statement-root')['cache_statement_schema_root_token'], $statementSchemaRootToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-statement-root')['current_statement_schema_root_token'], $statementSchemaRootToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-statement-root')['statement_schema_root_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-statement-root')['statement_schema_root_token_admitted'], true],
    'row stale statement admitted false' => [static fn (): mixed => $row('active-stale-statement-root')['statement_schema_root_token_admitted'], false],
    'row stale statement reason' => [static fn (): mixed => $row('active-stale-statement-root')['statement_schema_root_token_reason'], 'reader_cache_statement_schema_root_token_predates_master_journal_current_source'],
    'row stale statement cache token' => [static fn (): mixed => $row('active-stale-statement-root')['cache_statement_schema_root_token'], $oldStatementSchemaRootToken],
    'row stale statement current token' => [static fn (): mixed => $row('active-stale-statement-root')['current_statement_schema_root_token'], $statementSchemaRootToken],
    'row stale statement mismatch' => [static fn (): mixed => $row('active-stale-statement-root')['statement_schema_root_token_matches'], false],
    'row stale second statement reason' => [static fn (): mixed => $row('autoload-stale-statement-root')['statement_schema_root_token_reason'], 'reader_cache_statement_schema_root_token_predates_master_journal_current_source'],
    'row schema reparse inherits reason' => [static fn (): mixed => $row('usermeta-stale-schema-reparse')['statement_schema_root_token_reason'], 'reader_cache_schema_reparse_token_predates_master_journal_current_source'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['statement_schema_root_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['statement_schema_root_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['statement_schema_root_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-statement-root')['statement_schema_root_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained statement current' => [static fn (): mixed => $read('read-1')['statement_schema_root_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['statement_schema_root_token'], $statementSchemaRootToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale statement cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale statement source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-statement-schema-root-fence-current-source-next240'],
    'read stale statement reason' => [static fn (): mixed => $read('read-3')['statement_schema_root_token_reason'], 'reader_cache_reopened_after_statement_schema_root_token_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldStatementSchemaRootToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldStatementSchemaRootToken))['next_reads'][0]['statement_schema_root_token_reason'], 'reader_ticket_statement_schema_root_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldStatementSchemaRootToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldStatementSchemaRootToken)), 'reopen_reader_for_statement_schema_root_after_current_source_next240'), 9],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no statement invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['statement_schema_root_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current statement invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'statement-schema-root:epoch=241:root=1:cookie=101:sql=wp-options-new')['statement_schema_root_invalidated_cache_page_numbers'], [1, 2, 3, 9]],
    'changed current statement keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'statement-schema-root:epoch=241:root=1:cookie=101:sql=wp-options-new')['invalidated_cache_page_numbers'], true), true],
    'changed current statement surfaced' => [static fn (): mixed => $plan(null, null, 'statement-schema-root:epoch=241:root=1:cookie=101:sql=wp-options-new')['current_statement_schema_root_token'], 'statement-schema-root:epoch=241:root=1:cookie=101:sql=wp-options-new'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 9],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next240 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty statement schema root token rejected' => static fn () => $plan(null, null, ''),
    'cache missing statement token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['statement_schema_root_token' => true])]),
    'cache empty statement token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['statement_schema_root_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing statement token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['statement_schema_root_token' => true])]),
    'read empty statement token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['statement_schema_root_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next240 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
