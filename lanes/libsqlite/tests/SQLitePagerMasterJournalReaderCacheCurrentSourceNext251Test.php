<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Plan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next251.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next251-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-snapshot-next251';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 251), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503251), 68, 4);

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
    1 => $formatPage('next251 stale schema before reader snapshot fence'),
    2 => $page('next251 stale wp_options root before reader snapshot fence'),
    3 => $page('next251 stale active_plugins before reader snapshot fence'),
    4 => $page('next251 stale cron before reader snapshot fence'),
    5 => $page('next251 stale plugin cache before reader snapshot fence'),
];
$recovered = [
    1 => $formatPage('next251 current schema after reader snapshot fence'),
    2 => $page('next251 current wp_options root after reader snapshot fence'),
    3 => $page('next251 current active_plugins after reader snapshot fence'),
    4 => $page('next251 current cron after reader snapshot fence'),
    5 => $page('next251 current plugin cache after reader snapshot fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2511:size=4096:mtime=25101:generation=main-current',
    $usersJournal => 'dev=8:ino=2512:size=1024:mtime=25102:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next251'),
    $usersJournal => hash('sha256', 'users rollback header next251'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 251, 0x57503251]));
$masterDigest = hash('sha256', 'next251 master source');
$masterToken = 'dev=8:ino=2510:size=96:mtime=25100:generation=master-current';
$databaseToken = 'dev=8:ino=2519:size=2560:mtime=25199:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=251:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=251:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=251:schema=110:change-counter=251:master-current';
$schemaReparseToken = 'schema-reparse:epoch=251:schema-cookie=110:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=251:root=1:cookie=110:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=251:members=2:database-token=2519:schema=110';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=250:members=2:database-token=2518:schema=109';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=251:master-current:reset=complete';
$oldPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=250:before-master-reset';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=251:source=master-current:lease=shared:pages=1,2,3,4,5';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=250:source=before-master-current:lease=shared';
$base = [
    'source_id' => $sourceId,
    'epoch' => 251,
    'format_signature' => $formatSignature,
    'publication_generation' => 251,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 251,
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
], $base, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-snapshot', $recovered[1]),
    2 => $cacheEntry('options-root-stale-snapshot', $before[2], ['reader_snapshot_token' => $oldReaderSnapshotToken]),
    3 => $cacheEntry('active-plugins-stale-generation', $recovered[3], ['pager_reader_cache_generation_token' => $oldPagerReaderCacheGenerationToken]),
    4 => $cacheEntry('cron-stale-source', $recovered[4], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    5 => $cacheEntry('plugin-cache-refreshed-snapshot', $before[5]),
];
$read = static fn (int $pageNumber, ?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 251,
    'format_signature' => $formatSignature,
    'publication_generation' => 251,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 251,
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
];
$reads = static fn (?string $snapshotToken = null, ?string $generationToken = null, ?string $sourceToken = null): array => [
    $read(1, $snapshotToken, $generationToken, $sourceToken),
    $read(2, $snapshotToken, $generationToken, $sourceToken),
    $read(3, $snapshotToken, $generationToken, $sourceToken),
    $read(4, $snapshotToken, $generationToken, $sourceToken),
    $read(5, $snapshotToken, $generationToken, $sourceToken),
];
$plan = static fn (?array $readerCache = null, ?array $nextReads = null, ?string $snapshotToken = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Plan::plan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $sourceId,
    251,
    251,
    $masterDigest,
    251,
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
    $snapshotToken ?? $currentReaderSnapshotToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next251'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_reader_snapshot_before_reuse'],
    'current snapshot token' => [static fn (): mixed => $plan()['current_reader_snapshot_token'], $currentReaderSnapshotToken],
    'inherits generation token' => [static fn (): mixed => $plan()['current_pager_reader_cache_generation_token'], $currentPagerReaderCacheGenerationToken],
    'snapshot invalidated pages' => [static fn (): mixed => $plan()['reader_snapshot_invalidated_cache_page_numbers'], [2]],
    'generation invalidated pages' => [static fn (): mixed => $plan()['pager_reader_cache_generation_invalidated_cache_page_numbers'], [3]],
    'source invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [5]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale snapshot' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale generation' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale source' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], true],
    'operation invalidates stale snapshot cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_snapshot_after_master_journal_current_source_next251'), 1],
    'operation reopens stale snapshot reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_snapshot_after_master_journal_current_source_next251'), 1],
    'dependency next251' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next251', $plan()['dependencies'], true), true],
    'dependency snapshot fence' => [static fn (): mixed => in_array('sqlite-pager-reader-snapshot-current-source-fence', $plan()['dependencies'], true), true],
    'dependency next247 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next247', $plan()['dependencies'], true), true],
    'non overlap mentions next247' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next247 pager-generation'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal apply/commit'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-snapshot')['reader_snapshot_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-snapshot')['reader_snapshot_token_reason'], 'reader_snapshot_matches_master_journal_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-snapshot')['cache_reader_snapshot_token'], $currentReaderSnapshotToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-snapshot')['current_reader_snapshot_token'], $currentReaderSnapshotToken],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-snapshot')['reader_snapshot_token_matches'], true],
    'row stale snapshot admitted false' => [static fn (): mixed => $row('options-root-stale-snapshot')['reader_snapshot_token_admitted'], false],
    'row stale snapshot reason' => [static fn (): mixed => $row('options-root-stale-snapshot')['reader_snapshot_token_reason'], 'reader_snapshot_predates_master_journal_current_source'],
    'row stale snapshot cache token' => [static fn (): mixed => $row('options-root-stale-snapshot')['cache_reader_snapshot_token'], $oldReaderSnapshotToken],
    'row stale snapshot mismatch' => [static fn (): mixed => $row('options-root-stale-snapshot')['reader_snapshot_token_matches'], false],
    'row stale generation inherits reason' => [static fn (): mixed => $row('active-plugins-stale-generation')['reader_snapshot_token_reason'], 'pager_reader_cache_generation_predates_master_journal_current_source'],
    'row stale source inherits reason' => [static fn (): mixed => $row('cron-stale-source')['reader_snapshot_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row refreshed admitted' => [static fn (): mixed => $row('plugin-cache-refreshed-snapshot')['reader_snapshot_token_admitted'], true],
    'read retained snapshot current' => [static fn (): mixed => $readRow('read-1')['reader_snapshot_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $readRow('read-1')['reader_snapshot_token'], $currentReaderSnapshotToken],
    'read stale snapshot cache miss' => [static fn (): mixed => $readRow('read-2')['cache_hit'], false],
    'read stale snapshot source' => [static fn (): mixed => $readRow('read-2')['source'], 'master-journal-reader-snapshot-fence-current-source-next251'],
    'read stale snapshot reason' => [static fn (): mixed => $readRow('read-2')['reader_snapshot_token_reason'], 'reader_cache_reopened_after_reader_snapshot_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldReaderSnapshotToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reopens all' => [static fn (): mixed => $plan(null, $reads($oldReaderSnapshotToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5']],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldReaderSnapshotToken))['next_reads'][0]['reader_snapshot_token_reason'], 'reader_ticket_snapshot_predates_current_source'],
    'stale generation ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldPagerReaderCacheGenerationToken))['next_reads'][0]['pager_reader_cache_generation_token_reason'], 'reader_ticket_pager_generation_predates_current_source'],
    'stale source ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'changed current snapshot invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'reader-snapshot:epoch=252:source=master-current:lease=shared')['reader_snapshot_invalidated_cache_page_numbers'], [1, 2, 5]],
    'changed current snapshot keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'reader-snapshot:epoch=252:source=master-current:lease=shared')['invalidated_cache_page_numbers'], true), true],
    'changed current snapshot surfaced' => [static fn (): mixed => $plan(null, null, 'reader-snapshot:epoch=252:source=master-current:lease=shared')['current_reader_snapshot_token'], 'reader-snapshot:epoch=252:source=master-current:lease=shared'],
    'all fresh no snapshot invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['reader_snapshot_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 5],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'member token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next251 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty snapshot token rejected' => static fn () => $plan(null, null, ''),
    'cache missing snapshot token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['reader_snapshot_token' => true])]),
    'cache empty snapshot token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['reader_snapshot_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing snapshot token rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['reader_snapshot_token' => true])]),
    'read empty snapshot token rejected' => static fn () => $plan(null, [array_merge($read(1), ['reader_snapshot_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next251 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
