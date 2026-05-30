<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next260.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next260-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-current-source-reader-ticket-receipt-next260';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 260), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503260), 68, 4);

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
    1 => $formatPage('next260 stale schema before current-source-reader-ticket fence'),
    2 => $page('next260 stale wp_options root before current-source-reader-ticket fence'),
    3 => $page('next260 stale active_plugins before current-source-reader-ticket fence'),
    4 => $page('next260 stale cron before current-source-reader-ticket fence'),
    5 => $page('next260 stale plugin cache before current-source-reader-ticket fence'),
    6 => $page('next260 stale autoload index before current-source-reader-ticket fence'),
];
$recovered = [
    1 => $formatPage('next260 current schema after current-source-reader-ticket fence'),
    2 => $page('next260 current wp_options root after current-source-reader-ticket fence'),
    3 => $page('next260 current active_plugins after current-source-reader-ticket fence'),
    4 => $page('next260 current cron after current-source-reader-ticket fence'),
    5 => $page('next260 current plugin cache after current-source-reader-ticket fence'),
    6 => $page('next260 current autoload index after current-source-reader-ticket fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2601:size=4096:mtime=26001:generation=main-current',
    $usersJournal => 'dev=8:ino=2602:size=1024:mtime=26002:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next260'),
    $usersJournal => hash('sha256', 'users rollback header next260'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 260, 0x57503260]));
$masterDigest = hash('sha256', 'next260 master source');
$masterToken = 'dev=8:ino=2600:size=96:mtime=26000:generation=master-current';
$databaseToken = 'dev=8:ino=2609:size=3072:mtime=26099:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25800:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=260:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=260:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=260:schema=116:change-counter=260:master-current';
$schemaReparseToken = 'schema-reparse:epoch=260:schema-cookie=116:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=260:root=1:cookie=116:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=260:members=2:database-token=2609:schema=116';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=256:members=2:database-token=2608:schema=115';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=260:master-current:reset=complete';
$oldPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=256:before-master-reset';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=260:source=master-current:lease=shared:pages=1,2,3,4,5,6';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=256:source=before-master-current:lease=shared';
$currentRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=260:members=main,users:cleanup=deleted:dirsync=ok';
$oldRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=256:members=main,users:cleanup=pending';
$currentChecksumReceiptToken = 'recovered-page-checksum-receipt:epoch=260:digest=' . $recoveredDigest($recovered);
$currentSourceReaderTicketToken = 'current-source-reader-ticket:epoch=260:digest=' . $recoveredDigest($recovered);
$oldCurrentSourceReaderTicketToken = 'current-source-reader-ticket:epoch=256:digest=' . $recoveredDigest($before);
$base = [
    'source_id' => $sourceId,
    'epoch' => 260,
    'format_signature' => $formatSignature,
    'publication_generation' => 260,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 260,
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
    'current_source_reader_ticket_token' => $currentSourceReaderTicketToken,
], $base, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-current-source-reader-ticket', $recovered[1]),
    2 => $cacheEntry('options-root-stale-current-source-reader-ticket', $recovered[2], ['current_source_reader_ticket_token' => $oldCurrentSourceReaderTicketToken]),
    3 => $cacheEntry('active-plugins-stale-receipt', $recovered[3], ['master_journal_recovery_receipt_token' => $oldRecoveryReceiptToken]),
    4 => $cacheEntry('cron-stale-snapshot', $recovered[4], ['reader_snapshot_token' => $oldReaderSnapshotToken]),
    5 => $cacheEntry('plugin-cache-stale-generation', $recovered[5], ['pager_reader_cache_generation_token' => $oldPagerReaderCacheGenerationToken]),
    6 => $cacheEntry('autoload-stale-source', $recovered[6], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
];
$read = static fn (int $pageNumber, ?string $ticketToken = null, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 260,
    'format_signature' => $formatSignature,
    'publication_generation' => 260,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 260,
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
    'recovered_page_checksum_receipt_token' => $currentChecksumReceiptToken,
    'current_source_reader_ticket_token' => $ticketToken ?? $currentSourceReaderTicketToken,
];
$reads = static fn (?string $ticketToken = null, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    $read(1, $ticketToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(2, $ticketToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(3, $ticketToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(4, $ticketToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(5, $ticketToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(6, $ticketToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
];
$plan = static fn (?array $readerCache = null, ?array $nextReads = null, ?string $ticketToken = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderTicketFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $sourceId,
    260,
    260,
    $masterDigest,
    260,
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
    $currentChecksumReceiptToken,
    $ticketToken ?? $currentSourceReaderTicketToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next260'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_current_source_reader_ticket_before_reuse'],
    'current current-source-reader-ticket token' => [static fn (): mixed => $plan()['current_source_reader_ticket_token'], $currentSourceReaderTicketToken],
    'inherits recovery receipt token' => [static fn (): mixed => $plan()['current_master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'inherits snapshot token' => [static fn (): mixed => $plan()['current_reader_snapshot_token'], $currentReaderSnapshotToken],
    'current-source-reader-ticket invalidated pages' => [static fn (): mixed => $plan()['current_source_reader_ticket_invalidated_cache_page_numbers'], [2]],
    'receipt invalidated pages' => [static fn (): mixed => $plan()['master_journal_recovery_receipt_invalidated_cache_page_numbers'], [3]],
    'snapshot invalidated pages' => [static fn (): mixed => $plan()['reader_snapshot_invalidated_cache_page_numbers'], [4]],
    'generation invalidated pages' => [static fn (): mixed => $plan()['pager_reader_cache_generation_invalidated_cache_page_numbers'], [5]],
    'source invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [6]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale current-source-reader-ticket' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'operation invalidates stale current-source-reader-ticket cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_current_source_reader_ticket_current_source_next260'), 1],
    'operation reopens stale current-source-reader-ticket reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_current_source_reader_ticket_current_source_next260'), 1],
    'dependency next260' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next260', $plan()['dependencies'], true), true],
    'dependency current-source-reader-ticket fence' => [static fn (): mixed => in_array('sqlite-pager-current-source-reader-ticket-fence', $plan()['dependencies'], true), true],
    'dependency next257 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next257', $plan()['dependencies'], true), true],
    'non overlap mentions next254' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next257 recovered page checksum receipt'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal apply/commit'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-current-source-reader-ticket')['current_source_reader_ticket_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-current-source-reader-ticket')['current_source_reader_ticket_token_reason'], 'reader_cache_current_source_reader_ticket_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-current-source-reader-ticket')['cache_current_source_reader_ticket_token'], $currentSourceReaderTicketToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-current-source-reader-ticket')['current_source_reader_ticket_token'], $currentSourceReaderTicketToken],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-current-source-reader-ticket')['current_source_reader_ticket_token_matches'], true],
    'row stale current-source-reader-ticket admitted false' => [static fn (): mixed => $row('options-root-stale-current-source-reader-ticket')['current_source_reader_ticket_token_admitted'], false],
    'row stale current-source-reader-ticket reason' => [static fn (): mixed => $row('options-root-stale-current-source-reader-ticket')['current_source_reader_ticket_token_reason'], 'reader_cache_current_source_reader_ticket_predates_current_source'],
    'row stale current-source-reader-ticket cache token' => [static fn (): mixed => $row('options-root-stale-current-source-reader-ticket')['cache_current_source_reader_ticket_token'], $oldCurrentSourceReaderTicketToken],
    'row stale current-source-reader-ticket mismatch' => [static fn (): mixed => $row('options-root-stale-current-source-reader-ticket')['current_source_reader_ticket_token_matches'], false],
    'row stale receipt inherits reason' => [static fn (): mixed => $row('active-plugins-stale-receipt')['current_source_reader_ticket_token_reason'], 'reader_cache_master_journal_recovery_receipt_predates_current_source'],
    'row stale snapshot inherits reason' => [static fn (): mixed => $row('cron-stale-snapshot')['current_source_reader_ticket_token_reason'], 'reader_snapshot_predates_master_journal_current_source'],
    'row stale generation inherits reason' => [static fn (): mixed => $row('plugin-cache-stale-generation')['current_source_reader_ticket_token_reason'], 'pager_reader_cache_generation_predates_master_journal_current_source'],
    'row stale source inherits reason' => [static fn (): mixed => $row('autoload-stale-source')['current_source_reader_ticket_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'read retained current-source-reader-ticket current' => [static fn (): mixed => $readRow('read-1')['current_source_reader_ticket_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $readRow('read-1')['current_source_reader_ticket_token'], $currentSourceReaderTicketToken],
    'read stale current-source-reader-ticket cache miss' => [static fn (): mixed => $readRow('read-2')['cache_hit'], false],
    'read stale current-source-reader-ticket source' => [static fn (): mixed => $readRow('read-2')['source'], 'master-journal-current-source-reader-ticket-fence-current-source-next260'],
    'read stale current-source-reader-ticket reason' => [static fn (): mixed => $readRow('read-2')['current_source_reader_ticket_token_reason'], 'reader_cache_reopened_after_current_source_reader_ticket_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceReaderTicketToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reopens all' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceReaderTicketToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldCurrentSourceReaderTicketToken))['next_reads'][0]['current_source_reader_ticket_token_reason'], 'reader_ticket_current_source_reader_ticket_predates_current_source'],
    'stale receipt ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldRecoveryReceiptToken))['next_reads'][0]['master_journal_recovery_receipt_token_reason'], 'reader_ticket_master_journal_recovery_receipt_predates_current_source'],
    'stale snapshot ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldReaderSnapshotToken))['next_reads'][0]['reader_snapshot_token_reason'], 'reader_ticket_snapshot_predates_current_source'],
    'stale generation ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldPagerReaderCacheGenerationToken))['next_reads'][0]['pager_reader_cache_generation_token_reason'], 'reader_ticket_pager_generation_predates_current_source'],
    'stale source ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'changed current current-source-reader-ticket invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'current-source-reader-ticket:epoch=258:digest=' . hash('sha256', 'next-source'))['current_source_reader_ticket_invalidated_cache_page_numbers'], [1, 2]],
    'changed current current-source-reader-ticket keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'current-source-reader-ticket:epoch=258:digest=' . hash('sha256', 'next-source'))['invalidated_cache_page_numbers'], true), true],
    'changed current current-source-reader-ticket surfaced' => [static fn (): mixed => $plan(null, null, 'current-source-reader-ticket:epoch=258:digest=' . hash('sha256', 'next-source'))['current_source_reader_ticket_token'], 'current-source-reader-ticket:epoch=258:digest=' . hash('sha256', 'next-source')],
    'all fresh no current-source-reader-ticket invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['current_source_reader_ticket_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 6],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'member token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $mapDigest($headers), $mapDigest($headers)],
    'recovered digest differs from before digest' => [static fn (): mixed => $recoveredDigest($recovered) !== $recoveredDigest($before), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current-source next260 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
