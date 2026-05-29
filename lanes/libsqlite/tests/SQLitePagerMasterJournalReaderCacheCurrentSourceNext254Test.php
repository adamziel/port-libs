<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next254.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next254-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-recovery-receipt-next254';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 254), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503254), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$recoveredDigest = static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};

$before = [
    1 => $formatPage('next254 stale schema before recovery receipt fence'),
    2 => $page('next254 stale wp_options root before recovery receipt fence'),
    3 => $page('next254 stale active_plugins before recovery receipt fence'),
    4 => $page('next254 stale cron before recovery receipt fence'),
    5 => $page('next254 stale plugin cache before recovery receipt fence'),
    6 => $page('next254 stale autoload index before recovery receipt fence'),
];
$recovered = [
    1 => $formatPage('next254 current schema after recovery receipt fence'),
    2 => $page('next254 current wp_options root after recovery receipt fence'),
    3 => $page('next254 current active_plugins after recovery receipt fence'),
    4 => $page('next254 current cron after recovery receipt fence'),
    5 => $page('next254 current plugin cache after recovery receipt fence'),
    6 => $page('next254 current autoload index after recovery receipt fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2541:size=4096:mtime=25401:generation=main-current',
    $usersJournal => 'dev=8:ino=2542:size=1024:mtime=25402:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next254'),
    $usersJournal => hash('sha256', 'users rollback header next254'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 254, 0x57503254]));
$masterDigest = hash('sha256', 'next254 master source');
$masterToken = 'dev=8:ino=2540:size=96:mtime=25400:generation=master-current';
$databaseToken = 'dev=8:ino=2549:size=3072:mtime=25499:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=254:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=254:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=254:schema=113:change-counter=254:master-current';
$schemaReparseToken = 'schema-reparse:epoch=254:schema-cookie=113:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=254:root=1:cookie=113:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=254:members=2:database-token=2549:schema=113';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=253:members=2:database-token=2548:schema=112';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=254:master-current:reset=complete';
$oldPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=253:before-master-reset';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=254:source=master-current:lease=shared:pages=1,2,3,4,5,6';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=253:source=before-master-current:lease=shared';
$currentRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=254:members=main,users:cleanup=deleted:dirsync=ok';
$oldRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=253:members=main,users:cleanup=pending';
$base = [
    'source_id' => $sourceId,
    'epoch' => 254,
    'format_signature' => $formatSignature,
    'publication_generation' => 254,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 254,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'read_transaction_token' => $readTransactionToken,
    'schema_reparse_token' => $schemaReparseToken,
    'statement_schema_root_token' => $statementSchemaRootToken,
];
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'reader_id' => $label . '-reader',
    'image' => $image,
    'current_source_provenance_token' => $currentSourceProvenanceToken,
    'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken,
    'reader_snapshot_token' => $currentReaderSnapshotToken,
    'master_journal_recovery_receipt_token' => $currentRecoveryReceiptToken,
], $base, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-receipt', $recovered[1]),
    2 => $cacheEntry('options-root-stale-receipt', $before[2], ['master_journal_recovery_receipt_token' => $oldRecoveryReceiptToken]),
    3 => $cacheEntry('active-plugins-stale-snapshot', $recovered[3], ['reader_snapshot_token' => $oldReaderSnapshotToken]),
    4 => $cacheEntry('cron-stale-generation', $recovered[4], ['pager_reader_cache_generation_token' => $oldPagerReaderCacheGenerationToken]),
    5 => $cacheEntry('plugin-cache-stale-source', $recovered[5], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    6 => $cacheEntry('autoload-refreshed-receipt', $before[6]),
];
$read = static fn (int $pageNumber, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 254,
    'format_signature' => $formatSignature,
    'publication_generation' => 254,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 254,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'read_transaction_token' => $readTransactionToken,
    'schema_reparse_token' => $schemaReparseToken,
    'statement_schema_root_token' => $statementSchemaRootToken,
    'current_source_provenance_token' => $sourceToken ?? $currentSourceProvenanceToken,
    'pager_reader_cache_generation_token' => $generationToken ?? $currentPagerReaderCacheGenerationToken,
    'reader_snapshot_token' => $snapshotToken ?? $currentReaderSnapshotToken,
    'master_journal_recovery_receipt_token' => $receiptToken ?? $currentRecoveryReceiptToken,
];
$reads = static fn (?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    $read(1, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(2, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(3, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(4, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(5, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(6, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
];
$plan = static fn (?array $readerCache = null, ?array $nextReads = null, ?string $receiptToken = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext254(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $sourceId,
    254,
    254,
    $masterDigest,
    254,
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
    $currentPagerReaderCacheGenerationToken,
    $currentReaderSnapshotToken,
    $receiptToken ?? $currentRecoveryReceiptToken,
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};
$readRow = static function (string $readerId) use ($plan): array {
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next254'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_recovery_receipt_before_reuse'],
    'current receipt token' => [static fn (): mixed => $plan()['current_master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'inherits snapshot token' => [static fn (): mixed => $plan()['current_reader_snapshot_token'], $currentReaderSnapshotToken],
    'inherits generation token' => [static fn (): mixed => $plan()['current_pager_reader_cache_generation_token'], $currentPagerReaderCacheGenerationToken],
    'receipt invalidated pages' => [static fn (): mixed => $plan()['master_journal_recovery_receipt_invalidated_cache_page_numbers'], [2]],
    'snapshot invalidated pages' => [static fn (): mixed => $plan()['reader_snapshot_invalidated_cache_page_numbers'], [3]],
    'generation invalidated pages' => [static fn (): mixed => $plan()['pager_reader_cache_generation_invalidated_cache_page_numbers'], [4]],
    'source invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [5]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale receipt' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale snapshot' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale generation' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read hit stale source' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], false],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-6'], true],
    'operation invalidates stale receipt cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_master_journal_recovery_receipt_current_source_next254'), 1],
    'operation reopens stale receipt reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_master_journal_recovery_receipt_current_source_next254'), 1],
    'dependency next254' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next254', $plan()['dependencies'], true), true],
    'dependency receipt fence' => [static fn (): mixed => in_array('sqlite-pager-master-journal-recovery-receipt-reader-cache-fence', $plan()['dependencies'], true), true],
    'dependency next251 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next251', $plan()['dependencies'], true), true],
    'non overlap mentions next251' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next251 reader-snapshot'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal apply/commit'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-receipt')['master_journal_recovery_receipt_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-receipt')['master_journal_recovery_receipt_token_reason'], 'reader_cache_master_journal_recovery_receipt_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-receipt')['cache_master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-receipt')['current_master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-receipt')['master_journal_recovery_receipt_token_matches'], true],
    'row stale receipt admitted false' => [static fn (): mixed => $row('options-root-stale-receipt')['master_journal_recovery_receipt_token_admitted'], false],
    'row stale receipt reason' => [static fn (): mixed => $row('options-root-stale-receipt')['master_journal_recovery_receipt_token_reason'], 'reader_cache_master_journal_recovery_receipt_predates_current_source'],
    'row stale receipt cache token' => [static fn (): mixed => $row('options-root-stale-receipt')['cache_master_journal_recovery_receipt_token'], $oldRecoveryReceiptToken],
    'row stale receipt mismatch' => [static fn (): mixed => $row('options-root-stale-receipt')['master_journal_recovery_receipt_token_matches'], false],
    'row stale snapshot inherits reason' => [static fn (): mixed => $row('active-plugins-stale-snapshot')['master_journal_recovery_receipt_token_reason'], 'reader_snapshot_predates_master_journal_current_source'],
    'row stale generation inherits reason' => [static fn (): mixed => $row('cron-stale-generation')['master_journal_recovery_receipt_token_reason'], 'pager_reader_cache_generation_predates_master_journal_current_source'],
    'row stale source inherits reason' => [static fn (): mixed => $row('plugin-cache-stale-source')['master_journal_recovery_receipt_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row refreshed admitted' => [static fn (): mixed => $row('autoload-refreshed-receipt')['master_journal_recovery_receipt_token_admitted'], true],
    'read retained receipt current' => [static fn (): mixed => $readRow('read-1')['master_journal_recovery_receipt_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $readRow('read-1')['master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'read stale receipt cache miss' => [static fn (): mixed => $readRow('read-2')['cache_hit'], false],
    'read stale receipt source' => [static fn (): mixed => $readRow('read-2')['source'], 'master-journal-recovery-receipt-fence-current-source-next254'],
    'read stale receipt reason' => [static fn (): mixed => $readRow('read-2')['master_journal_recovery_receipt_token_reason'], 'reader_cache_reopened_after_master_journal_recovery_receipt_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldRecoveryReceiptToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reopens all' => [static fn (): mixed => $plan(null, $reads($oldRecoveryReceiptToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldRecoveryReceiptToken))['next_reads'][0]['master_journal_recovery_receipt_token_reason'], 'reader_ticket_master_journal_recovery_receipt_predates_current_source'],
    'stale snapshot ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReaderSnapshotToken))['next_reads'][0]['reader_snapshot_token_reason'], 'reader_ticket_snapshot_predates_current_source'],
    'stale generation ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldPagerReaderCacheGenerationToken))['next_reads'][0]['pager_reader_cache_generation_token_reason'], 'reader_ticket_pager_generation_predates_current_source'],
    'stale source ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'changed current receipt invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'master-journal-recovery-receipt:epoch=255:members=main,users:cleanup=deleted:dirsync=ok')['master_journal_recovery_receipt_invalidated_cache_page_numbers'], [1, 2, 6]],
    'changed current receipt keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'master-journal-recovery-receipt:epoch=255:members=main,users:cleanup=deleted:dirsync=ok')['invalidated_cache_page_numbers'], true), true],
    'changed current receipt surfaced' => [static fn (): mixed => $plan(null, null, 'master-journal-recovery-receipt:epoch=255:members=main,users:cleanup=deleted:dirsync=ok')['current_master_journal_recovery_receipt_token'], 'master-journal-recovery-receipt:epoch=255:members=main,users:cleanup=deleted:dirsync=ok'],
    'all fresh no receipt invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['master_journal_recovery_receipt_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 6],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'member token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $mapDigest($headers), $mapDigest($headers)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next254 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty receipt token rejected' => static fn () => $plan(null, null, ''),
    'cache missing receipt token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['master_journal_recovery_receipt_token' => true])]),
    'cache empty receipt token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['master_journal_recovery_receipt_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing receipt token rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['master_journal_recovery_receipt_token' => true])]),
    'read empty receipt token rejected' => static fn () => $plan(null, [array_merge($read(1), ['master_journal_recovery_receipt_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next254 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
