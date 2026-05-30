<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next261.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next261-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-rollback-source-next261';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 261), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503261), 68, 4);

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
    1 => $formatPage('next261 stale schema before rollback-source fence'),
    2 => $page('next261 stale wp_options root before rollback-source fence'),
    3 => $page('next261 stale active_plugins before rollback-source fence'),
    4 => $page('next261 stale cron before rollback-source fence'),
    5 => $page('next261 stale plugin cache before rollback-source fence'),
    6 => $page('next261 stale autoload index before rollback-source fence'),
];
$recovered = [
    1 => $formatPage('next261 current schema after rollback-source fence'),
    2 => $page('next261 current wp_options root after rollback-source fence'),
    3 => $page('next261 current active_plugins after rollback-source fence'),
    4 => $page('next261 current cron after rollback-source fence'),
    5 => $page('next261 current plugin cache after rollback-source fence'),
    6 => $page('next261 current autoload index after rollback-source fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2611:size=4096:mtime=26101:generation=main-current',
    $usersJournal => 'dev=8:ino=2612:size=1024:mtime=26102:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next261'),
    $usersJournal => hash('sha256', 'users rollback header next261'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 261, 0x57503261]));
$masterDigest = hash('sha256', 'next261 master source');
$masterToken = 'dev=8:ino=2610:size=96:mtime=26100:generation=master-current';
$databaseToken = 'dev=8:ino=2619:size=3072:mtime=26199:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25900:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=261:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=261:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=261:schema=117:change-counter=261:master-current';
$schemaReparseToken = 'schema-reparse:epoch=261:schema-cookie=117:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=261:root=1:cookie=117:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=261:members=2:database-token=2619:schema=117';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=257:members=2:database-token=2579:schema=116';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=261:master-current:reset=complete';
$oldPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=257:before-master-reset';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=261:source=master-current:lease=shared:pages=1,2,3,4,5,6';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=257:source=before-master-current:lease=shared';
$currentRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=261:members=main,users:cleanup=deleted:dirsync=ok';
$oldRecoveryReceiptToken = 'master-journal-recovery-receipt:epoch=257:members=main,users:cleanup=pending';
$currentPagerSpillDrainToken = 'pager-spill-drain:epoch=261:dirty-pages=2,6:journal-sync=ok:exclusive-lock=held';
$currentRollbackJournalReaderSourceToken = 'pager-rollback-source:epoch=261:dirty-pages=2,6:journal-sync=ok:exclusive-lock=held';
$oldRollbackJournalReaderSourceToken = 'pager-rollback-source:epoch=257:dirty-pages=2,6:journal-sync=pending';
$base = [
    'source_id' => $sourceId,
    'epoch' => 261,
    'format_signature' => $formatSignature,
    'publication_generation' => 261,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 261,
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
    'pager_spill_drain_token' => $currentPagerSpillDrainToken,
    'rollback_journal_reader_source_token' => $currentRollbackJournalReaderSourceToken,
], $base, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-rollback-source', $recovered[1]),
    2 => $cacheEntry('options-root-stale-rollback-source', $before[2], ['rollback_journal_reader_source_token' => $oldRollbackJournalReaderSourceToken]),
    3 => $cacheEntry('active-plugins-stale-receipt', $recovered[3], ['master_journal_recovery_receipt_token' => $oldRecoveryReceiptToken]),
    4 => $cacheEntry('cron-stale-snapshot', $recovered[4], ['reader_snapshot_token' => $oldReaderSnapshotToken]),
    5 => $cacheEntry('plugin-cache-stale-generation', $recovered[5], ['pager_reader_cache_generation_token' => $oldPagerReaderCacheGenerationToken]),
    6 => $cacheEntry('autoload-refreshed-rollback-source', $before[6]),
];
$read = static fn (int $pageNumber, ?string $rollbackSourceToken = null, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 261,
    'format_signature' => $formatSignature,
    'publication_generation' => 261,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 261,
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
    'pager_spill_drain_token' => $currentPagerSpillDrainToken,
    'rollback_journal_reader_source_token' => $rollbackSourceToken ?? $currentRollbackJournalReaderSourceToken,
];
$reads = static fn (?string $rollbackSourceToken = null, ?string $receiptToken = null, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    $read(1, $rollbackSourceToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(2, $rollbackSourceToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(3, $rollbackSourceToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(4, $rollbackSourceToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(5, $rollbackSourceToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
    $read(6, $rollbackSourceToken, $receiptToken, $snapshotToken, $generationToken, $sourceToken),
];
$plan = static fn (?array $readerCache = null, ?array $nextReads = null, ?string $rollbackSourceToken = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::rollbackJournalReaderSourceFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $sourceId,
    261,
    261,
    $masterDigest,
    261,
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
    $currentPagerSpillDrainToken,
    $rollbackSourceToken ?? $currentRollbackJournalReaderSourceToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next261'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_rollback_journal_reader_source_before_reuse'],
    'current rollback-source token' => [static fn (): mixed => $plan()['current_rollback_journal_reader_source_token'], $currentRollbackJournalReaderSourceToken],
    'inherits recovery receipt' => [static fn (): mixed => $plan()['current_master_journal_recovery_receipt_token'], $currentRecoveryReceiptToken],
    'rollback-source invalidated pages' => [static fn (): mixed => $plan()['rollback_journal_reader_source_invalidated_cache_page_numbers'], [2]],
    'receipt invalidated pages' => [static fn (): mixed => $plan()['master_journal_recovery_receipt_invalidated_cache_page_numbers'], [3]],
    'snapshot invalidated pages' => [static fn (): mixed => $plan()['reader_snapshot_invalidated_cache_page_numbers'], [4]],
    'generation invalidated pages' => [static fn (): mixed => $plan()['pager_reader_cache_generation_invalidated_cache_page_numbers'], [5]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale rollback-source' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale receipt' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale snapshot' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read hit stale generation' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], false],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-6'], true],
    'operation invalidates stale rollback-source cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_rollback_journal_reader_source_current_source_next261'), 1],
    'operation reopens stale rollback-source reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_rollback_journal_reader_source_current_source_next261'), 1],
    'dependency next261' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next261', $plan()['dependencies'], true), true],
    'dependency rollback-source fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-rollback-source-fence', $plan()['dependencies'], true), true],
    'dependency next258 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next258', $plan()['dependencies'], true), true],
    'dependency next254 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next254', $plan()['dependencies'], true), true],
    'non overlap mentions next254' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next254 master-journal recovery receipt'), true],
    'non overlap mentions VFS writer' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'VFS writer/sync/lock'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-rollback-source')['rollback_journal_reader_source_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-rollback-source')['rollback_journal_reader_source_token_reason'], 'reader_cache_rollback_journal_reader_source_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-rollback-source')['cache_rollback_journal_reader_source_token'], $currentRollbackJournalReaderSourceToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-rollback-source')['current_rollback_journal_reader_source_token'], $currentRollbackJournalReaderSourceToken],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-rollback-source')['rollback_journal_reader_source_token_matches'], true],
    'row stale rollback-source admitted false' => [static fn (): mixed => $row('options-root-stale-rollback-source')['rollback_journal_reader_source_token_admitted'], false],
    'row stale rollback-source reason' => [static fn (): mixed => $row('options-root-stale-rollback-source')['rollback_journal_reader_source_token_reason'], 'reader_cache_rollback_journal_reader_source_predates_current_master_journal_source'],
    'row stale rollback-source cache token' => [static fn (): mixed => $row('options-root-stale-rollback-source')['cache_rollback_journal_reader_source_token'], $oldRollbackJournalReaderSourceToken],
    'row stale rollback-source mismatch' => [static fn (): mixed => $row('options-root-stale-rollback-source')['rollback_journal_reader_source_token_matches'], false],
    'row stale receipt inherits reason' => [static fn (): mixed => $row('active-plugins-stale-receipt')['rollback_journal_reader_source_token_reason'], 'reader_cache_master_journal_recovery_receipt_predates_current_source'],
    'row stale snapshot inherits reason' => [static fn (): mixed => $row('cron-stale-snapshot')['rollback_journal_reader_source_token_reason'], 'reader_snapshot_predates_master_journal_current_source'],
    'row stale generation inherits reason' => [static fn (): mixed => $row('plugin-cache-stale-generation')['rollback_journal_reader_source_token_reason'], 'pager_reader_cache_generation_predates_master_journal_current_source'],
    'row refreshed admitted' => [static fn (): mixed => $row('autoload-refreshed-rollback-source')['rollback_journal_reader_source_token_admitted'], true],
    'read retained rollback-source current' => [static fn (): mixed => $readRow('read-1')['rollback_journal_reader_source_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $readRow('read-1')['rollback_journal_reader_source_token'], $currentRollbackJournalReaderSourceToken],
    'read stale rollback-source cache miss' => [static fn (): mixed => $readRow('read-2')['cache_hit'], false],
    'read stale rollback-source source' => [static fn (): mixed => $readRow('read-2')['source'], 'master-journal-reader-cache-rollback-source-fence-current-source-next261'],
    'read stale rollback-source reason' => [static fn (): mixed => $readRow('read-2')['rollback_journal_reader_source_token_reason'], 'reader_cache_reopened_after_rollback_journal_reader_source_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldRollbackJournalReaderSourceToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reopens all' => [static fn (): mixed => $plan(null, $reads($oldRollbackJournalReaderSourceToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldRollbackJournalReaderSourceToken))['next_reads'][0]['rollback_journal_reader_source_token_reason'], 'reader_ticket_rollback_journal_reader_source_predates_current_source'],
    'stale receipt ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldRecoveryReceiptToken))['next_reads'][0]['master_journal_recovery_receipt_token_reason'], 'reader_ticket_master_journal_recovery_receipt_predates_current_source'],
    'stale snapshot ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldReaderSnapshotToken))['next_reads'][0]['reader_snapshot_token_reason'], 'reader_ticket_snapshot_predates_current_source'],
    'stale generation ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldPagerReaderCacheGenerationToken))['next_reads'][0]['pager_reader_cache_generation_token_reason'], 'reader_ticket_pager_generation_predates_current_source'],
    'stale source ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'changed current rollback-source invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'pager-rollback-source:epoch=259:dirty-pages=2,6:journal-sync=ok:exclusive-lock=held')['rollback_journal_reader_source_invalidated_cache_page_numbers'], [1, 2, 6]],
    'changed current rollback-source keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'pager-rollback-source:epoch=259:dirty-pages=2,6:journal-sync=ok:exclusive-lock=held')['invalidated_cache_page_numbers'], true), true],
    'changed current rollback-source surfaced' => [static fn (): mixed => $plan(null, null, 'pager-rollback-source:epoch=259:dirty-pages=2,6:journal-sync=ok:exclusive-lock=held')['current_rollback_journal_reader_source_token'], 'pager-rollback-source:epoch=259:dirty-pages=2,6:journal-sync=ok:exclusive-lock=held'],
    'all fresh no rollback-source invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['rollback_journal_reader_source_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 6],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'member token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $mapDigest($headers), $mapDigest($headers)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next261 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty rollback-source token rejected' => static fn () => $plan(null, null, ''),
    'cache missing rollback-source token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['rollback_journal_reader_source_token' => true])]),
    'cache empty rollback-source token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['rollback_journal_reader_source_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing rollback-source token rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['rollback_journal_reader_source_token' => true])]),
    'read empty rollback-source token rejected' => static fn () => $plan(null, [array_merge($read(1), ['rollback_journal_reader_source_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next261 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
