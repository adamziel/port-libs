<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Plan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next244.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next244-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-page-image-digest-next244';
$publication = 244;
$masterDigest = hash('sha256', 'next244-master-source');
$recoverySequence = 244;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2440:size=96:mtime=24400:generation=master-current';
$databaseToken = 'dev=8:ino=2449:size=4096:mtime=24499:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=244:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=244:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=244:schema=104:change-counter=244:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=243:schema=103:change-counter=243:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=244:schema-cookie=104:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=243:schema-cookie=103:ddl=before-master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=244:root=1:cookie=104:sql=wp-options-current';
$oldStatementSchemaRootToken = 'statement-schema-root:epoch=243:root=1:cookie=103:sql=wp-options-prior';
$pageImageDigestReceiptToken = 'page-image-digest-receipt:epoch=244:master=complete:pages=1,2,3,4,6,9';
$oldPageImageDigestReceiptToken = 'page-image-digest-receipt:epoch=243:master=prior:pages=1,2,3,4,6,9';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=243:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24490:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2449:size=4096:mtime=24498:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 244), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503244), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next244 stale schema before page image digest receipt'),
    2 => $page('next244 stale wp_options root before page image digest receipt'),
    3 => $page('next244 stale active_plugins before page image digest receipt'),
    4 => $page('next244 stale usermeta before page image digest receipt'),
    5 => $page('next244 stale rewrite_rules before page image digest receipt'),
    6 => $page('next244 stale cron before page image digest receipt'),
    7 => $page('next244 stale comments before page image digest receipt'),
    8 => $page('next244 stale terms before page image digest receipt'),
    9 => $page('next244 stale autoload query before page image digest receipt'),
];
$recovered = [
    1 => $formatPage('next244 current schema after page image digest receipt'),
    2 => $page('next244 current wp_options root after page image digest receipt'),
    3 => $page('next244 current active_plugins after page image digest receipt'),
    4 => $page('next244 current usermeta after page image digest receipt'),
    6 => $page('next244 current cron after page image digest receipt'),
    9 => $page('next244 current autoload query after page image digest receipt'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 244, 0x57503244]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 243, 0x57503243]));
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
    $mainJournal => 'dev=8:ino=2441:size=4096:mtime=24401:generation=main-current',
    $usersJournal => 'dev=8:ino=2442:size=1024:mtime=24402:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-244'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-244'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-244'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 244,
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
    'page_image_digest_receipt_token' => $pageImageDigestReceiptToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-digest-receipt', $recovered[1]),
    2 => $cacheEntry('root-refreshed-digest-receipt', $before[2]),
    3 => $cacheEntry('active-stale-digest-receipt', $recovered[3], ['page_image_digest_receipt_token' => $oldPageImageDigestReceiptToken]),
    4 => $cacheEntry('usermeta-stale-statement-root', $recovered[4], ['statement_schema_root_token' => $oldStatementSchemaRootToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-digest-receipt', $before[8], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
    9 => $cacheEntry('autoload-stale-digest-receipt', $recovered[9], ['page_image_digest_receipt_token' => 'page-image-digest-receipt:epoch=243:autoload-prior']),
];
$reads = static fn (?string $digestReceipt = null, ?string $statementToken = null, ?string $schemaToken = null, ?string $readTransaction = null, ?string $pagerCache = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 244,
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
        'page_image_digest_receipt_token' => $digestReceipt ?? $pageImageDigestReceiptToken,
    ],
    range(1, 9),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentDigestReceipt = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Plan::plan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    244,
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
    $statementSchemaRootToken,
    $currentDigestReceipt ?? $pageImageDigestReceiptToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next244'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_page_image_digest_receipts_before_current_source_reuse'],
    'current digest receipt token' => [static fn (): mixed => $plan()['current_page_image_digest_receipt_token'], $pageImageDigestReceiptToken],
    'inherits statement schema root token' => [static fn (): mixed => $plan()['current_statement_schema_root_token'], $statementSchemaRootToken],
    'digest invalidated pages' => [static fn (): mixed => $plan()['page_image_digest_receipt_invalidated_cache_page_numbers'], [3, 9]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8, 9]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale digest receipt' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation invalidates stale digest cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_page_image_digest_receipt_after_current_source_next244'), 2],
    'operation reopens stale digest reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_page_image_digest_receipt_after_current_source_next244'), 2],
    'dependency next244' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next244', $plan()['dependencies'], true), true],
    'dependency digest fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-page-image-digest-receipt-fence', $plan()['dependencies'], true), true],
    'dependency next240 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next240', $plan()['dependencies'], true), true],
    'non overlap mentions next240' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next240 statement schema-root'), true],
    'non overlap mentions page counters' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'page-1 header counters'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-digest-receipt')['page_image_digest_receipt_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-digest-receipt')['page_image_digest_receipt_reason'], 'reader_cache_page_image_digest_receipt_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-digest-receipt')['cache_page_image_digest_receipt_token'], $pageImageDigestReceiptToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-digest-receipt')['current_page_image_digest_receipt_token'], $pageImageDigestReceiptToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-digest-receipt')['page_image_digest_receipt_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-digest-receipt')['page_image_digest_receipt_admitted'], true],
    'row stale digest admitted false' => [static fn (): mixed => $row('active-stale-digest-receipt')['page_image_digest_receipt_admitted'], false],
    'row stale digest reason' => [static fn (): mixed => $row('active-stale-digest-receipt')['page_image_digest_receipt_reason'], 'reader_cache_page_image_digest_receipt_predates_master_journal_current_source'],
    'row stale digest cache token' => [static fn (): mixed => $row('active-stale-digest-receipt')['cache_page_image_digest_receipt_token'], $oldPageImageDigestReceiptToken],
    'row stale digest current token' => [static fn (): mixed => $row('active-stale-digest-receipt')['current_page_image_digest_receipt_token'], $pageImageDigestReceiptToken],
    'row stale digest mismatch' => [static fn (): mixed => $row('active-stale-digest-receipt')['page_image_digest_receipt_matches'], false],
    'row stale second digest reason' => [static fn (): mixed => $row('autoload-stale-digest-receipt')['page_image_digest_receipt_reason'], 'reader_cache_page_image_digest_receipt_predates_master_journal_current_source'],
    'row statement root inherits reason' => [static fn (): mixed => $row('usermeta-stale-statement-root')['page_image_digest_receipt_reason'], 'reader_cache_statement_schema_root_token_predates_master_journal_current_source'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['page_image_digest_receipt_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['page_image_digest_receipt_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['page_image_digest_receipt_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-digest-receipt')['page_image_digest_receipt_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained digest current' => [static fn (): mixed => $read('read-1')['page_image_digest_receipt_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['page_image_digest_receipt_token'], $pageImageDigestReceiptToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read stale digest cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale digest source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-page-image-digest-fence-current-source-next244'],
    'read stale digest reason' => [static fn (): mixed => $read('read-3')['page_image_digest_receipt_reason'], 'reader_cache_reopened_after_page_image_digest_receipt_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldPageImageDigestReceiptToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldPageImageDigestReceiptToken))['next_reads'][0]['page_image_digest_receipt_reason'], 'reader_ticket_page_image_digest_receipt_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldPageImageDigestReceiptToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8', 'read-9']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldPageImageDigestReceiptToken)), 'reopen_reader_for_page_image_digest_receipt_after_current_source_next244'), 9],
    'stale statement ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldStatementSchemaRootToken))['next_reads'][0]['statement_schema_root_token_reason'], 'reader_ticket_statement_schema_root_predates_current_source'],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no digest invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['page_image_digest_receipt_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current digest invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'page-image-digest-receipt:epoch=245:master=complete:pages=1,2,3,4,6,9')['page_image_digest_receipt_invalidated_cache_page_numbers'], [1, 2, 3, 9]],
    'changed current digest keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'page-image-digest-receipt:epoch=245:master=complete:pages=1,2,3,4,6,9')['invalidated_cache_page_numbers'], true), true],
    'changed current digest surfaced' => [static fn (): mixed => $plan(null, null, 'page-image-digest-receipt:epoch=245:master=complete:pages=1,2,3,4,6,9')['current_page_image_digest_receipt_token'], 'page-image-digest-receipt:epoch=245:master=complete:pages=1,2,3,4,6,9'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 9],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next244 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty digest receipt token rejected' => static fn () => $plan(null, null, ''),
    'cache missing digest receipt token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['page_image_digest_receipt_token' => true])]),
    'cache empty digest receipt token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['page_image_digest_receipt_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing digest receipt token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['page_image_digest_receipt_token' => true])]),
    'read empty digest receipt token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['page_image_digest_receipt_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next244 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
