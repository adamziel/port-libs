<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Plan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next255.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next255-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-page-map-next255';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 255), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503255), 68, 4);

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
    1 => $formatPage('next255 stale schema before reader page-map digest fence'),
    2 => $page('next255 stale wp_options root before reader page-map digest fence'),
    3 => $page('next255 stale active_plugins before reader page-map digest fence'),
    4 => $page('next255 stale cron before reader page-map digest fence'),
    5 => $page('next255 stale plugin cache before reader page-map digest fence'),
];
$recovered = [
    1 => $formatPage('next255 current schema after reader page-map digest fence'),
    2 => $page('next255 current wp_options root after reader page-map digest fence'),
    3 => $page('next255 current active_plugins after reader page-map digest fence'),
    4 => $page('next255 current cron after reader page-map digest fence'),
    5 => $page('next255 current plugin cache after reader page-map digest fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2551:size=4096:mtime=25501:generation=main-current',
    $usersJournal => 'dev=8:ino=2552:size=1024:mtime=25502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next255'),
    $usersJournal => hash('sha256', 'users rollback header next255'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 255, 0x57503255]));
$masterDigest = hash('sha256', 'next255 master source');
$masterToken = 'dev=8:ino=2550:size=96:mtime=25500:generation=master-current';
$databaseToken = 'dev=8:ino=2559:size=2560:mtime=25599:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=255:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=255:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=255:schema=110:change-counter=255:master-current';
$schemaReparseToken = 'schema-reparse:epoch=255:schema-cookie=110:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=255:root=1:cookie=110:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=255:members=2:database-token=2559:schema=110';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=250:members=2:database-token=2558:schema=109';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=255:master-current:reset=complete';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=255:source=master-current:lease=shared:pages=1,2,3,4,5';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=250:source=before-master-current:lease=shared';
$currentReaderPageMapDigestToken = 'reader-page-map:epoch=255:source=master-current:lease=shared:pages=1,2,3,4,5';
$oldReaderPageMapDigestToken = 'reader-page-map:epoch=250:source=before-master-current:lease=shared';
$base = [
    'source_id' => $sourceId,
    'epoch' => 255,
    'format_signature' => $formatSignature,
    'publication_generation' => 255,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 255,
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
    'reader_page_map_digest_token' => $currentReaderPageMapDigestToken,
], $base, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-page-map', $recovered[1]),
    2 => $cacheEntry('options-root-stale-page-map', $before[2], ['reader_page_map_digest_token' => $oldReaderPageMapDigestToken]),
    3 => $cacheEntry('active-plugins-stale-snapshot', $recovered[3], ['reader_snapshot_token' => $oldReaderSnapshotToken]),
    4 => $cacheEntry('cron-stale-source', $recovered[4], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    5 => $cacheEntry('plugin-cache-refreshed-page-map', $before[5]),
];
$read = static fn (int $pageNumber, ?string $pageMapToken = null, ?string $snapshotToken = null, ?string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 255,
    'format_signature' => $formatSignature,
    'publication_generation' => 255,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 255,
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
    'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken,
    'reader_snapshot_token' => $snapshotToken ?? $currentReaderSnapshotToken,
    'reader_page_map_digest_token' => $pageMapToken ?? $currentReaderPageMapDigestToken,
];
$reads = static fn (?string $pageMapToken = null, ?string $snapshotToken = null, ?string $sourceToken = null): array => [
    $read(1, $pageMapToken, $snapshotToken, $sourceToken),
    $read(2, $pageMapToken, $snapshotToken, $sourceToken),
    $read(3, $pageMapToken, $snapshotToken, $sourceToken),
    $read(4, $pageMapToken, $snapshotToken, $sourceToken),
    $read(5, $pageMapToken, $snapshotToken, $sourceToken),
];
$plan = static fn (?array $readerCache = null, ?array $nextReads = null, ?string $pageMapToken = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Plan::plan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $sourceId,
    255,
    255,
    $masterDigest,
    255,
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
    $pageMapToken ?? $currentReaderPageMapDigestToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next255'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_reader_page_map_digest_before_reuse'],
    'current page-map token' => [static fn (): mixed => $plan()['current_reader_page_map_digest_token'], $currentReaderPageMapDigestToken],
    'inherits generation token' => [static fn (): mixed => $plan()['current_pager_reader_cache_generation_token'], $currentPagerReaderCacheGenerationToken],
    'page-map invalidated pages' => [static fn (): mixed => $plan()['reader_page_map_digest_invalidated_cache_page_numbers'], [2]],
    'snapshot invalidated pages' => [static fn (): mixed => $plan()['reader_snapshot_invalidated_cache_page_numbers'], [3]],
    'source invalidated pages' => [static fn (): mixed => $plan()['current_source_provenance_invalidated_cache_page_numbers'], [4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [5]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale page-map' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'read hit stale snapshot' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale source' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], true],
    'operation invalidates stale page-map cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_page_map_digest_after_master_journal_current_source_next255'), 1],
    'operation reopens stale page-map reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_page_map_digest_after_master_journal_current_source_next255'), 1],
    'dependency next255' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next255', $plan()['dependencies'], true), true],
    'dependency page-map fence' => [static fn (): mixed => in_array('sqlite-pager-reader-page-map-digest-current-source-fence', $plan()['dependencies'], true), true],
    'dependency next251 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next251', $plan()['dependencies'], true), true],
    'non overlap mentions next251' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next251 reader-snapshot'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal apply/commit'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-page-map')['reader_page_map_digest_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-page-map')['reader_page_map_digest_token_reason'], 'reader_page_map_digest_matches_master_journal_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-page-map')['cache_reader_page_map_digest_token'], $currentReaderPageMapDigestToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-page-map')['current_reader_page_map_digest_token'], $currentReaderPageMapDigestToken],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-page-map')['reader_page_map_digest_token_matches'], true],
    'row stale page-map admitted false' => [static fn (): mixed => $row('options-root-stale-page-map')['reader_page_map_digest_token_admitted'], false],
    'row stale page-map reason' => [static fn (): mixed => $row('options-root-stale-page-map')['reader_page_map_digest_token_reason'], 'reader_page_map_digest_predates_master_journal_current_source'],
    'row stale page-map cache token' => [static fn (): mixed => $row('options-root-stale-page-map')['cache_reader_page_map_digest_token'], $oldReaderPageMapDigestToken],
    'row stale page-map mismatch' => [static fn (): mixed => $row('options-root-stale-page-map')['reader_page_map_digest_token_matches'], false],
    'row stale snapshot inherits reason' => [static fn (): mixed => $row('active-plugins-stale-snapshot')['reader_page_map_digest_token_reason'], 'reader_snapshot_predates_master_journal_current_source'],
    'row stale source inherits reason' => [static fn (): mixed => $row('cron-stale-source')['reader_page_map_digest_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row refreshed admitted' => [static fn (): mixed => $row('plugin-cache-refreshed-page-map')['reader_page_map_digest_token_admitted'], true],
    'read retained page-map current' => [static fn (): mixed => $readRow('read-1')['reader_page_map_digest_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $readRow('read-1')['reader_page_map_digest_token'], $currentReaderPageMapDigestToken],
    'read stale page-map cache miss' => [static fn (): mixed => $readRow('read-2')['cache_hit'], false],
    'read stale page-map source' => [static fn (): mixed => $readRow('read-2')['source'], 'master-journal-reader-page-map-digest-fence-current-source-next255'],
    'read stale page-map reason' => [static fn (): mixed => $readRow('read-2')['reader_page_map_digest_token_reason'], 'reader_cache_reopened_after_reader_page_map_digest_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldReaderPageMapDigestToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reopens all' => [static fn (): mixed => $plan(null, $reads($oldReaderPageMapDigestToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5']],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldReaderPageMapDigestToken))['next_reads'][0]['reader_page_map_digest_token_reason'], 'reader_ticket_page_map_digest_predates_current_source'],
    'stale snapshot ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldReaderSnapshotToken))['next_reads'][0]['reader_snapshot_token_reason'], 'reader_ticket_snapshot_predates_current_source'],
    'stale source ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'changed current page-map invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'reader-page-map:epoch=252:source=master-current:lease=shared')['reader_page_map_digest_invalidated_cache_page_numbers'], [1, 2, 5]],
    'changed current page-map keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'reader-page-map:epoch=252:source=master-current:lease=shared')['invalidated_cache_page_numbers'], true), true],
    'changed current page-map surfaced' => [static fn (): mixed => $plan(null, null, 'reader-page-map:epoch=252:source=master-current:lease=shared')['current_reader_page_map_digest_token'], 'reader-page-map:epoch=252:source=master-current:lease=shared'],
    'all fresh no page-map invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['reader_page_map_digest_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [$read(1)])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 5],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'member token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next255 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty page-map token rejected' => static fn () => $plan(null, null, ''),
    'cache missing page-map token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['reader_page_map_digest_token' => true])]),
    'cache empty page-map token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['reader_page_map_digest_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing page-map token rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['reader_page_map_digest_token' => true])]),
    'read empty page-map token rejected' => static fn () => $plan(null, [array_merge($read(1), ['reader_page_map_digest_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($read(1), ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next255 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
