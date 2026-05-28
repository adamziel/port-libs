<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Plan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next257.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next257-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-checksum-receipt-next257';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 257), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503257), 68, 4);

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
    1 => $formatPage('next257 stale schema before checksum receipt fence'),
    2 => $page('next257 stale wp_options root before checksum receipt fence'),
    3 => $page('next257 stale active_plugins before checksum receipt fence'),
    4 => $page('next257 stale cron before checksum receipt fence'),
    5 => $page('next257 stale plugin cache before checksum receipt fence'),
    6 => $page('next257 stale autoload index before checksum receipt fence'),
];
$recovered = [
    1 => $formatPage('next257 current schema after checksum receipt fence'),
    2 => $page('next257 current wp_options root after checksum receipt fence'),
    3 => $page('next257 current active_plugins after checksum receipt fence'),
    4 => $page('next257 current cron after checksum receipt fence'),
    5 => $page('next257 current plugin cache after checksum receipt fence'),
    6 => $page('next257 current autoload index after checksum receipt fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2571:size=4096:mtime=25701:generation=main-current',
    $usersJournal => 'dev=8:ino=2572:size=1024:mtime=25702:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next257'),
    $usersJournal => hash('sha256', 'users rollback header next257'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 257, 0x57503257]));
$masterDigest = hash('sha256', 'next257 master source');
$masterToken = 'dev=8:ino=2570:size=96:mtime=25700:generation=master-current';
$databaseToken = 'dev=8:ino=2579:size=3072:mtime=25799:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25800:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=257:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=257:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=257:schema=116:change-counter=257:master-current';
$schemaReparseToken = 'schema-reparse:epoch=257:schema-cookie=116:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=257:root=1:cookie=116:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=257:members=2:database-token=2579:schema=116';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=256:members=2:database-token=2578:schema=115';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=257:master-current:reset=complete';
$oldPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=256:before-master-reset';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=257:source=master-current:lease=shared:pages=1,2,3,4,5,6';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=256:source=before-master-current:lease=shared';
$currentRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=257:members=main,users:cleanup=deleted:dirsync=ok';
$oldRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=256:members=main,users:cleanup=pending';
$currentChecksumReceiptToken = 'recovered-page-checksum-receipt:epoch=257:digest=' . $recoveredDigest($recovered);
$oldChecksumReceiptToken = 'recovered-page-checksum-receipt:epoch=256:digest=' . $recoveredDigest($before);
$base = [
    'source_id' => $sourceId,
    'epoch' => 257,
    'format_signature' => $formatSignature,
    'publication_generation' => 257,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 257,
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
    'recovered_page_checksum_receipt_token' => $currentChecksumReceiptToken,
], $base, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-checksum', $recovered[1]),
    2 => $cacheEntry('options-root-stale-checksum', $recovered[2], ['recovered_page_checksum_receipt_token' => $oldChecksumReceiptToken]),
    3 => $cacheEntry('active-plugins-stale-receipt', $recovered[3], ['master_journal_recovery_receipt_token' => $oldRecoveryReceiptToken]),
    4 => $cacheEntry('cron-stale-snapshot', $recovered[4], ['reader_snapshot_token' => $oldReaderSnapshotToken]),
    5 => $cacheEntry('plugin-cache-stale-generation', $recovered[5], ['pager_reader_cache_generation_token' => $oldPagerReaderCacheGenerationToken]),
    6 => $cacheEntry('autoload-stale-source', $recovered[6], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
];
$read = static fn (int $pageNumber, ?string $checksumToken = null, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 257,
    'format_signature' => $formatSignature,
    'publication_generation' => 257,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 257,
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
    'recovered_page_checksum_receipt_token' => $checksumToken ?? $currentChecksumReceiptToken,
];
$reads = static fn (?string $checksumToken = null, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    $read(1, $checksumToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(2, $checksumToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(3, $checksumToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(4, $checksumToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(5, $checksumToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(6, $checksumToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
];
$plan = static fn (?array $readerCache = null, ?array $nextReads = null, ?string $checksumToken = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Plan::plan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $sourceId,
    257,
    257,
    $masterDigest,
    257,
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
    $currentRecoveryReceiptToken,
    $checksumToken ?? $currentChecksumReceiptToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next257'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_recovered_page_checksum_receipt_before_reuse'],
    'current checksum token' => [static fn (): mixed => $plan()['current_recovered_page_checksum_receipt_token'], $currentChecksumReceiptToken],
    'inherits recovery receipt token' => [static fn (): mixed => $plan()['current_master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'inherits snapshot token' => [static fn (): mixed => $plan()['current_reader_snapshot_token'], $currentReaderSnapshotToken],
    'checksum invalidated pages' => [static fn (): mixed => $plan()['recovered_page_checksum_receipt_invalidated_cache_page_numbers'], [2]],
    'receipt invalidated pages' => [static fn (): mixed => $plan()['master_journal_recovery_receipt_invalidated_cache_page_numbers'], [3]],
    'snapshot invalidated pages' => [static fn (): mixed => $plan()['reader_snapshot_invalidated_cache_page_numbers'], [4]],
    'generation invalidated pages' => [static fn (): mixed => $plan()['pager_reader_cache_generation_invalidated_cache_page_numbers'], [5]],
    'source invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [6]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale checksum' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'operation invalidates stale checksum cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_recovered_page_checksum_receipt_current_source_next257'), 1],
    'operation reopens stale checksum reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_recovered_page_checksum_receipt_current_source_next257'), 1],
    'dependency next257' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next257', $plan()['dependencies'], true), true],
    'dependency checksum fence' => [static fn (): mixed => in_array('sqlite-pager-recovered-page-checksum-receipt-reader-cache-fence', $plan()['dependencies'], true), true],
    'dependency next254 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next254', $plan()['dependencies'], true), true],
    'non overlap mentions next254' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next254 master-journal recovery-receipt'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal apply/commit'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-checksum')['recovered_page_checksum_receipt_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-checksum')['recovered_page_checksum_receipt_token_reason'], 'reader_cache_recovered_page_checksum_receipt_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-checksum')['cache_recovered_page_checksum_receipt_token'], $currentChecksumReceiptToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-checksum')['current_recovered_page_checksum_receipt_token'], $currentChecksumReceiptToken],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-checksum')['recovered_page_checksum_receipt_token_matches'], true],
    'row stale checksum admitted false' => [static fn (): mixed => $row('options-root-stale-checksum')['recovered_page_checksum_receipt_token_admitted'], false],
    'row stale checksum reason' => [static fn (): mixed => $row('options-root-stale-checksum')['recovered_page_checksum_receipt_token_reason'], 'reader_cache_recovered_page_checksum_receipt_predates_current_source'],
    'row stale checksum cache token' => [static fn (): mixed => $row('options-root-stale-checksum')['cache_recovered_page_checksum_receipt_token'], $oldChecksumReceiptToken],
    'row stale checksum mismatch' => [static fn (): mixed => $row('options-root-stale-checksum')['recovered_page_checksum_receipt_token_matches'], false],
    'row stale receipt inherits reason' => [static fn (): mixed => $row('active-plugins-stale-receipt')['recovered_page_checksum_receipt_token_reason'], 'reader_cache_master_journal_recovery_receipt_predates_current_source'],
    'row stale snapshot inherits reason' => [static fn (): mixed => $row('cron-stale-snapshot')['recovered_page_checksum_receipt_token_reason'], 'reader_snapshot_predates_master_journal_current_source'],
    'row stale generation inherits reason' => [static fn (): mixed => $row('plugin-cache-stale-generation')['recovered_page_checksum_receipt_token_reason'], 'pager_reader_cache_generation_predates_master_journal_current_source'],
    'row stale source inherits reason' => [static fn (): mixed => $row('autoload-stale-source')['recovered_page_checksum_receipt_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'read retained checksum current' => [static fn (): mixed => $readRow('read-1')['recovered_page_checksum_receipt_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $readRow('read-1')['recovered_page_checksum_receipt_token'], $currentChecksumReceiptToken],
    'read stale checksum cache miss' => [static fn (): mixed => $readRow('read-2')['cache_hit'], false],
    'read stale checksum source' => [static fn (): mixed => $readRow('read-2')['source'], 'master-journal-recovered-page-checksum-receipt-fence-current-source-next257'],
    'read stale checksum reason' => [static fn (): mixed => $readRow('read-2')['recovered_page_checksum_receipt_token_reason'], 'reader_cache_reopened_after_recovered_page_checksum_receipt_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldChecksumReceiptToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reopens all' => [static fn (): mixed => $plan(null, $reads($oldChecksumReceiptToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldChecksumReceiptToken))['next_reads'][0]['recovered_page_checksum_receipt_token_reason'], 'reader_ticket_recovered_page_checksum_receipt_predates_current_source'],
    'stale receipt ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldRecoveryReceiptToken))['next_reads'][0]['master_journal_recovery_receipt_token_reason'], 'reader_ticket_master_journal_recovery_receipt_predates_current_source'],
    'stale snapshot ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldReaderSnapshotToken))['next_reads'][0]['reader_snapshot_token_reason'], 'reader_ticket_snapshot_predates_current_source'],
    'stale generation ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldPagerReaderCacheGenerationToken))['next_reads'][0]['pager_reader_cache_generation_token_reason'], 'reader_ticket_pager_generation_predates_current_source'],
    'stale source ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'changed current checksum invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'recovered-page-checksum-receipt:epoch=258:digest=' . hash('sha256', 'next-source'))['recovered_page_checksum_receipt_invalidated_cache_page_numbers'], [1, 2]],
    'changed current checksum keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'recovered-page-checksum-receipt:epoch=258:digest=' . hash('sha256', 'next-source'))['invalidated_cache_page_numbers'], true), true],
    'changed current checksum surfaced' => [static fn (): mixed => $plan(null, null, 'recovered-page-checksum-receipt:epoch=258:digest=' . hash('sha256', 'next-source'))['current_recovered_page_checksum_receipt_token'], 'recovered-page-checksum-receipt:epoch=258:digest=' . hash('sha256', 'next-source')],
    'all fresh no checksum invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['recovered_page_checksum_receipt_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 6],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'member token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $mapDigest($headers), $mapDigest($headers)],
    'recovered digest differs from before digest' => [static fn (): mixed => $recoveredDigest($recovered) !== $recoveredDigest($before), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next257 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
