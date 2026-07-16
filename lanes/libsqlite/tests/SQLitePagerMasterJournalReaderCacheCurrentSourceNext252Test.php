<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next252.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next252-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-member-manifest-next252';
$publication = 252;
$masterDigest = hash('sha256', 'next252-master-source');
$recoverySequence = 252;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2520:size=96:mtime=25200:generation=master-current';
$databaseToken = 'dev=8:ino=2529:size=4096:mtime=25299:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=252:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=252:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=252:schema=112:change-counter=252:master-current';
$schemaReparseToken = 'schema-reparse:epoch=252:schema-cookie=112:ddl=master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=252:schema-cookie=112:master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=252:stmt-cache=wp-options:master-current';
$rootpageMapToken = 'rootpage-map:epoch=252:wp_options=2:autoload=4:option_name=6:users=8';
$oldRootpageMapToken = 'rootpage-map:epoch=251:wp_options=2:autoload=5:option_name=7:users=8';
$pageOwnerMapToken = 'page-owner-map:epoch=252:p1=schema:p2=wp_options:p3=plugins:p4=autoload:p5=usermeta:p6=comments';
$oldPageOwnerMapToken = 'page-owner-map:epoch=251:p1=schema:p2=freelist:p3=plugins:p4=autoload:p5=usermeta:p6=comments';
$manifestToken = 'master-member-manifest:epoch=252:main=' . substr(hash('sha256', $mainJournal), 0, 12) . ':users=' . substr(hash('sha256', $usersJournal), 0, 12);
$oldManifestToken = 'master-member-manifest:epoch=251:main=old-main:users=old-users';
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 252, 0x57503252]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 251, 0x57503251]));
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 252), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503252), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next252 stale schema before manifest recovery'),
    2 => $page('next252 stale wp_options root before manifest recovery'),
    3 => $page('next252 stale active_plugins before manifest recovery'),
    4 => $page('next252 stale autoload index before manifest recovery'),
    5 => $page('next252 stale usermeta before manifest recovery'),
    6 => $page('next252 stale comments before manifest recovery'),
    7 => $page('next252 stale terms before manifest recovery'),
];
$recovered = [
    1 => $formatPage('next252 current schema after manifest recovery'),
    2 => $page('next252 current wp_options root after manifest recovery'),
    3 => $page('next252 current active_plugins after manifest recovery'),
    4 => $page('next252 current autoload index after manifest recovery'),
    5 => $page('next252 current usermeta after manifest recovery'),
    6 => $page('next252 current comments after manifest recovery'),
];
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
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
    $mainJournal => 'dev=8:ino=2521:size=4096:mtime=25201:generation=main-current',
    $usersJournal => 'dev=8:ino=2522:size=1024:mtime=25202:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-252'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-252'),
];
$oldHeaders = [
    $mainJournal => $headers[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-252'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$orderDigest = hash('sha256', implode("\n", $members));
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 252,
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
    'shared_cache_generation_token' => $sharedCacheGenerationToken,
    'statement_snapshot_token' => $statementSnapshotToken,
    'rootpage_map_token' => $rootpageMapToken,
    'page_owner_map_token' => $pageOwnerMapToken,
    'master_member_manifest_token' => $manifestToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-manifest', $recovered[1]),
    2 => $cacheEntry('root-stale-manifest', $recovered[2], ['master_member_manifest_token' => $oldManifestToken]),
    3 => $cacheEntry('active-stale-owner-map', $recovered[3], ['page_owner_map_token' => $oldPageOwnerMapToken]),
    4 => $cacheEntry('autoload-stale-rootpage', $recovered[4], ['rootpage_map_token' => $oldRootpageMapToken]),
    5 => $cacheEntry('usermeta-stale-header', $recovered[5], ['member_journal_header_digests' => $oldHeaders]),
    6 => $cacheEntry('comments-dirty-manifest', $page('next252 dirty comments cache'), ['dirty' => true, 'format_signature' => $oldFormatSignature]),
    7 => $cacheEntry('terms-stale-manifest-outside', $before[7], ['master_member_manifest_token' => $oldManifestToken]),
];
$reads = static fn (?string $manifest = null, ?string $owner = null, ?string $rootpage = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 252,
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
        'pager_cache_source_token' => $pagerCacheSourceToken,
        'read_transaction_token' => $readTransactionToken,
        'schema_reparse_token' => $schemaReparseToken,
        'shared_cache_generation_token' => $sharedCacheGenerationToken,
        'statement_snapshot_token' => $statementSnapshotToken,
        'rootpage_map_token' => $rootpage ?? $rootpageMapToken,
        'page_owner_map_token' => $owner ?? $pageOwnerMapToken,
        'master_member_manifest_token' => $manifest ?? $manifestToken,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $currentManifestToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalReaderCacheReceipt(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    252,
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
    $sharedCacheGenerationToken,
    $statementSnapshotToken,
    $rootpageMapToken,
    $pageOwnerMapToken,
    $currentManifestToken ?? $manifestToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next252'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_recovered_member_manifest_before_current_source_reuse'],
    'current manifest token' => [static fn (): mixed => $plan()['current_master_member_manifest_token'], $manifestToken],
    'inherits owner map token' => [static fn (): mixed => $plan()['current_page_owner_map_token'], $pageOwnerMapToken],
    'manifest invalidated pages' => [static fn (): mixed => $plan()['master_member_manifest_invalidated_cache_page_numbers'], [2, 7]],
    'owner map invalidated pages' => [static fn (): mixed => $plan()['page_owner_map_invalidated_cache_page_numbers'], [3]],
    'rootpage invalidated pages' => [static fn (): mixed => $plan()['rootpage_map_invalidated_cache_page_numbers'], [4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6, 7]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit stale manifest' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], false],
    'operation invalidates manifest cache' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_master_member_manifest_after_current_source_next252'), 2],
    'operation reopens manifest reader' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_master_member_manifest_after_current_source_next252'), 2],
    'dependency next252' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next252', $plan()['dependencies'], true), true],
    'dependency manifest fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-master-member-manifest-fence', $plan()['dependencies'], true), true],
    'dependency next248 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next248', $plan()['dependencies'], true), true],
    'non overlap mentions next248' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next248 page-owner-map'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-manifest')['master_member_manifest_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-manifest')['master_member_manifest_token_reason'], 'reader_cache_master_member_manifest_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-manifest')['cache_master_member_manifest_token'], $manifestToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-manifest')['current_master_member_manifest_token'], $manifestToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-manifest')['master_member_manifest_token_matches'], true],
    'row stale manifest admitted false' => [static fn (): mixed => $row('root-stale-manifest')['master_member_manifest_token_admitted'], false],
    'row stale manifest reason' => [static fn (): mixed => $row('root-stale-manifest')['master_member_manifest_token_reason'], 'reader_cache_master_member_manifest_predates_master_journal_current_source'],
    'row stale manifest cache token' => [static fn (): mixed => $row('root-stale-manifest')['cache_master_member_manifest_token'], $oldManifestToken],
    'row stale manifest current token' => [static fn (): mixed => $row('root-stale-manifest')['current_master_member_manifest_token'], $manifestToken],
    'row stale manifest mismatch' => [static fn (): mixed => $row('root-stale-manifest')['master_member_manifest_token_matches'], false],
    'row owner inherits reason' => [static fn (): mixed => $row('active-stale-owner-map')['master_member_manifest_token_reason'], 'reader_cache_page_owner_map_predates_master_journal_current_source'],
    'row rootpage inherits reason' => [static fn (): mixed => $row('autoload-stale-rootpage')['master_member_manifest_token_reason'], 'reader_cache_rootpage_map_predates_master_journal_current_source'],
    'row header inherits reason' => [static fn (): mixed => $row('usermeta-stale-header')['master_member_manifest_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty-manifest')['master_member_manifest_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained manifest current' => [static fn (): mixed => $read('read-1')['master_member_manifest_token_current'], true],
    'read retained token surfaced' => [static fn (): mixed => $read('read-1')['master_member_manifest_token'], $manifestToken],
    'read stale manifest cache miss' => [static fn (): mixed => $read('read-2')['cache_hit'], false],
    'read stale manifest source' => [static fn (): mixed => $read('read-2')['source'], 'master-journal-reader-cache-master-member-manifest-fence-current-source-next252'],
    'read stale manifest reason' => [static fn (): mixed => $read('read-2')['master_member_manifest_token_reason'], 'reader_cache_reopened_after_master_member_manifest_change'],
    'stale manifest ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldManifestToken))['read_cache_hits']['read-1'], false],
    'stale manifest ticket reason' => [static fn (): mixed => $plan(null, $reads($oldManifestToken))['next_reads'][0]['master_member_manifest_token_reason'], 'reader_ticket_master_member_manifest_predates_current_source'],
    'stale manifest ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldManifestToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'stale owner ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, $oldPageOwnerMapToken))['next_reads'][0]['page_owner_map_token_reason'], 'reader_ticket_page_owner_map_predates_current_source'],
    'stale rootpage ticket inherited' => [static fn (): mixed => $plan(null, $reads(null, null, $oldRootpageMapToken))['next_reads'][0]['rootpage_map_token_reason'], 'reader_ticket_rootpage_map_predates_current_source'],
    'all fresh no manifest invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['master_member_manifest_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current manifest invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'master-member-manifest:epoch=253:main=new:users=new')['master_member_manifest_invalidated_cache_page_numbers'], [1, 2, 7]],
    'changed current manifest keeps inherited invalidation' => [static fn (): mixed => in_array(3, $plan(null, null, 'master-member-manifest:epoch=253:main=new:users=new')['invalidated_cache_page_numbers'], true), true],
    'changed current manifest surfaced' => [static fn (): mixed => $plan(null, null, 'master-member-manifest:epoch=253:main=new:users=new')['current_master_member_manifest_token'], 'master-member-manifest:epoch=253:main=new:users=new'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 7],
    'master bytes digest current' => [static fn (): mixed => $masterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $tokenDigest, $mapDigest($tokens)],
    'member header digest current' => [static fn (): mixed => $headerDigest, $mapDigest($headers)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next252 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty manifest token rejected' => static fn () => $plan(null, null, ''),
    'cache missing manifest token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-manifest', $recovered[1]), ['master_member_manifest_token' => true])]),
    'cache empty manifest token rejected' => static fn () => $plan([1 => $cacheEntry('empty-manifest', $recovered[1], ['master_member_manifest_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing manifest token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['master_member_manifest_token' => true])]),
    'read empty manifest token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['master_member_manifest_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next252 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
