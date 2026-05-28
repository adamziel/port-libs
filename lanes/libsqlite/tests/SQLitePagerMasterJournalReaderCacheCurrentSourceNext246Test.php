<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext246Plan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next246.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next246-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-current-source-version-vector-next246';
$publication = 246;
$masterDigest = hash('sha256', 'next246-master-source');
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2460:size=96:mtime=24600:generation=master-current';
$databaseToken = 'dev=8:ino=2469:size=4096:mtime=24699:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=246:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=246:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=246:schema=106:change-counter=246:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=245:schema=105:change-counter=245:before-master-current';
$schemaReparseToken = 'schema-reparse:epoch=246:schema-cookie=106:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=245:schema-cookie=105:ddl=before-master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=246:root=1:cookie=106:sql=wp-options-current';
$oldStatementSchemaRootToken = 'statement-schema-root:epoch=245:root=1:cookie=105:sql=wp-options-prior';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=246:members=2:database-token=2469:schema=106';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=245:members=2:database-token=2468:schema=105';
$currentVersionVectorToken = 'version-vector:main=246/106/users=41/9/options-root=2/autoload=7';
$oldVersionVectorToken = 'version-vector:main=245/105/users=40/8/options-root=2/autoload=6';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=245:before-master-journal-recovery';
$oldCleanupToken = 'master-cleanup:exists:mtime=24690:dirsync=pending';
$oldDatabaseToken = 'dev=8:ino=2469:size=4096:mtime=24698:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 246), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503246), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next246 stale schema before version vector recovery'),
    2 => $page('next246 stale wp_options root before version vector recovery'),
    3 => $page('next246 stale active_plugins before version vector recovery'),
    4 => $page('next246 stale usermeta before version vector recovery'),
    5 => $page('next246 stale rewrite_rules before version vector recovery'),
    6 => $page('next246 stale cron before version vector recovery'),
    7 => $page('next246 stale comments before version vector recovery'),
    8 => $page('next246 stale terms before version vector recovery'),
];
$recovered = [
    1 => $formatPage('next246 current schema after version vector recovery'),
    2 => $page('next246 current wp_options root after version vector recovery'),
    3 => $page('next246 current active_plugins after version vector recovery'),
    4 => $page('next246 current usermeta after version vector recovery'),
    6 => $page('next246 current cron after version vector recovery'),
    7 => $page('next246 current comments after version vector recovery'),
    8 => $page('next246 current terms after version vector recovery'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 246, 0x57503246]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 245, 0x57503245]));
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
    $mainJournal => 'dev=8:ino=2461:size=4096:mtime=24601:generation=main-current',
    $usersJournal => 'dev=8:ino=2462:size=1024:mtime=24602:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-246'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-246'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-246'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 246,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 246,
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
    'current_source_provenance_token' => $currentSourceProvenanceToken,
    'current_source_version_vector_token' => $currentVersionVectorToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-version-vector', $recovered[1]),
    2 => $cacheEntry('root-refreshed-version-vector', $before[2]),
    3 => $cacheEntry('active-stale-version-vector', $recovered[3], ['current_source_version_vector_token' => $oldVersionVectorToken]),
    4 => $cacheEntry('usermeta-stale-provenance', $recovered[4], ['current_source_provenance_token' => $oldCurrentSourceProvenanceToken]),
    5 => $cacheEntry('rewrite-stale-cleanup-token', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('cron-stale-database-token', $recovered[6], ['database_file_token' => $oldDatabaseToken]),
    7 => $cacheEntry('comments-stale-header', $recovered[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-version-vector', $before[8], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
];
$reads = static fn (
    ?string $vectorToken = null,
    ?string $sourceToken = null,
    ?string $statementToken = null,
    ?string $schemaToken = null,
    ?string $readTransaction = null,
    ?string $pagerCache = null,
): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 246,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => 246,
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
        'current_source_provenance_token' => $sourceToken ?? $currentSourceProvenanceToken,
        'current_source_version_vector_token' => $vectorToken ?? $currentVersionVectorToken,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentVectorToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNext246Plan::plan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    246,
    $publication,
    $masterDigest,
    246,
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
    $currentVectorToken ?? $currentVersionVectorToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next246'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_current_source_version_vector_before_reuse'],
    'version vector token' => [static fn (): mixed => $plan()['current_source_version_vector_token'], $currentVersionVectorToken],
    'inherits provenance token' => [static fn (): mixed => $plan()['current_source_provenance_token'], $currentSourceProvenanceToken],
    'vector invalidated pages' => [static fn (): mixed => $plan()['current_source_version_vector_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read vector miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read inherited provenance miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read retained vector current' => [static fn (): mixed => $read('read-1')['current_source_version_vector_token_current'], true],
    'read retained vector surfaced' => [static fn (): mixed => $read('read-1')['current_source_version_vector_token'], $currentVersionVectorToken],
    'read stale vector source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-current-source-version-vector-fence-next246'],
    'read stale vector reason' => [static fn (): mixed => $read('read-3')['current_source_version_vector_token_reason'], 'reader_cache_reopened_after_current_source_version_vector_change'],
    'operation invalidates stale vector cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_current_source_version_vector_after_master_journal_next246'), 1],
    'operation reopens stale vector reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_current_source_version_vector_after_master_journal_next246'), 1],
    'dependency next246' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next246', $plan()['dependencies'], true), true],
    'dependency vector fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-current-source-version-vector-fence', $plan()['dependencies'], true), true],
    'dependency next243 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next243', $plan()['dependencies'], true), true],
    'non overlap mentions next243' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next243 provenance'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-version-vector')['current_source_version_vector_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-version-vector')['current_source_version_vector_token_reason'], 'reader_cache_current_source_version_vector_matches_master_journal_recovery'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-version-vector')['cache_current_source_version_vector_token'], $currentVersionVectorToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-version-vector')['current_source_version_vector_token'], $currentVersionVectorToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-version-vector')['current_source_version_vector_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-version-vector')['current_source_version_vector_token_admitted'], true],
    'row stale vector admitted false' => [static fn (): mixed => $row('active-stale-version-vector')['current_source_version_vector_token_admitted'], false],
    'row stale vector reason' => [static fn (): mixed => $row('active-stale-version-vector')['current_source_version_vector_token_reason'], 'reader_cache_current_source_version_vector_predates_master_journal_recovery'],
    'row stale vector cache token' => [static fn (): mixed => $row('active-stale-version-vector')['cache_current_source_version_vector_token'], $oldVersionVectorToken],
    'row stale vector current token' => [static fn (): mixed => $row('active-stale-version-vector')['current_source_version_vector_token'], $currentVersionVectorToken],
    'row stale vector mismatch' => [static fn (): mixed => $row('active-stale-version-vector')['current_source_version_vector_token_matches'], false],
    'row provenance inherits reason' => [static fn (): mixed => $row('usermeta-stale-provenance')['current_source_version_vector_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup-token')['current_source_version_vector_token_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row database inherits reason' => [static fn (): mixed => $row('cron-stale-database-token')['current_source_version_vector_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row header inherits reason' => [static fn (): mixed => $row('comments-stale-header')['current_source_version_vector_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-version-vector')['current_source_version_vector_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldVersionVectorToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldVersionVectorToken))['next_reads'][0]['current_source_version_vector_token_reason'], 'reader_ticket_current_source_version_vector_predates_recovery'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldVersionVectorToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldVersionVectorToken)), 'reopen_reader_for_current_source_version_vector_after_master_journal_next246'), 8],
    'stale source ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldCurrentSourceProvenanceToken))['next_reads'][0]['current_source_provenance_token_reason'], 'reader_ticket_current_source_provenance_predates_recovery'],
    'stale statement ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldStatementSchemaRootToken))['next_reads'][0]['statement_schema_root_token_reason'], 'reader_ticket_statement_schema_root_predates_current_source'],
    'stale schema ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, $oldSchemaReparseToken))['next_reads'][0]['schema_reparse_token_reason'], 'reader_ticket_schema_reparse_predates_current_source'],
    'stale read transaction still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, $oldReadTransactionToken))['next_reads'][0]['read_transaction_token_reason'], 'reader_ticket_read_transaction_predates_current_source'],
    'stale pager cache ticket still inherited' => [static fn (): mixed => $plan(null, $reads(null, null, null, null, null, $oldPagerCacheSourceToken))['next_reads'][0]['pager_cache_source_token_reason'], 'reader_ticket_pager_cache_source_predates_current_source'],
    'all fresh no vector invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['current_source_version_vector_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed vector invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'version-vector:main=247/107/users=42/10/options-root=2/autoload=8')['current_source_version_vector_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed vector keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'version-vector:main=247/107/users=42/10/options-root=2/autoload=8')['invalidated_cache_page_numbers'], true), true],
    'changed vector surfaced' => [static fn (): mixed => $plan(null, null, 'version-vector:main=247/107/users=42/10/options-root=2/autoload=8')['current_source_version_vector_token'], 'version-vector:main=247/107/users=42/10/options-root=2/autoload=8'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 8],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
    'version vector embeds schema' => [static fn (): mixed => str_contains($currentVersionVectorToken, 'main=246/106'), true],
    'version vector embeds attached users' => [static fn (): mixed => str_contains($currentVersionVectorToken, 'users=41/9'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next246 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty version vector token rejected' => static fn () => $plan(null, null, ''),
    'cache missing version vector rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['current_source_version_vector_token' => true])]),
    'cache empty version vector rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['current_source_version_vector_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing version vector rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['current_source_version_vector_token' => true])]),
    'read empty version vector rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['current_source_version_vector_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next246 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
