<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next249.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next249-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-source-handoff-next249';
$publication = 249;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterDigest = hash('sha256', 'next249-master-source');
$masterToken = 'dev=8:ino=2490:size=96:mtime=24900:generation=master-current';
$databaseToken = 'dev=8:ino=2499:size=3072:mtime=24999:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25000:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=249:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=249:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=249:schema=109:change-counter=249:master-current';
$schemaReparseToken = 'schema-reparse:epoch=249:schema-cookie=109:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=249:root=1:cookie=109:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=249:members=2:database-token=2499:schema=109';
$currentVersionVectorToken = 'version-vector:main=249/109/users=44/12/options-root=2/autoload=10';
$oldVersionVectorToken = 'version-vector:main=248/108/users=43/11/options-root=2/autoload=9';
$currentHandoffToken = 'reader-cache-handoff:epoch=249:master=249:source=wp-options-recovered:lease=771';
$oldHandoffToken = 'reader-cache-handoff:epoch=248:master=248:source=wp-options-stale:lease=770';
$oldProvenanceToken = 'current-source:master-journal:epoch=248:members=2:database-token=2498:schema=108';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 249), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503249), 68, 4);

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
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad page fixture');
        }
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$before = [
    1 => $formatPage('next249 stale schema before source handoff'),
    2 => $page('next249 stale wp_options root before source handoff'),
    3 => $page('next249 stale active_plugins before source handoff'),
    4 => $page('next249 stale usermeta before source handoff'),
    5 => $page('next249 stale cron before source handoff'),
    6 => $page('next249 stale rewrite rules before source handoff'),
];
$recovered = [
    1 => $formatPage('next249 current schema after source handoff'),
    2 => $page('next249 current wp_options root after source handoff'),
    3 => $page('next249 current active_plugins after source handoff'),
    4 => $page('next249 current usermeta after source handoff'),
    5 => $page('next249 current cron after source handoff'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2491:size=4096:mtime=24901:generation=main-current',
    $usersJournal => 'dev=8:ino=2492:size=1024:mtime=24902:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-249'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-249'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 249, 0x57503249]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 248, 0x57503248]));
$recoveredPageDigest = $recoveredDigest($recovered);
$baseEntry = [
    'source_id' => $sourceId,
    'epoch' => 249,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 249,
    'recovered_page_set_digest' => $recoveredPageDigest,
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
    'current_source_provenance_token' => $currentSourceProvenanceToken,
    'current_source_version_vector_token' => $currentVersionVectorToken,
    'reader_cache_source_handoff_token' => $currentHandoffToken,
];
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'reader_id' => $label . '-reader',
    'image' => $image,
], $baseEntry, $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-handoff', $recovered[1]),
    2 => $cacheEntry('root-refreshed-handoff', $before[2]),
    3 => $cacheEntry('active-stale-handoff', $recovered[3], ['reader_cache_source_handoff_token' => $oldHandoffToken]),
    4 => $cacheEntry('usermeta-stale-version-vector', $recovered[4], ['current_source_version_vector_token' => $oldVersionVectorToken]),
    5 => $cacheEntry('cron-stale-provenance', $recovered[5], ['current_source_provenance_token' => $oldProvenanceToken]),
    6 => $cacheEntry('rewrite-dirty-handoff', $before[6], ['dirty' => true, 'format_signature' => $oldFormatSignature]),
];
$readBase = [
    'source_id' => $sourceId,
    'epoch' => 249,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 249,
    'recovered_page_set_digest' => $recoveredPageDigest,
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
    'current_source_provenance_token' => $currentSourceProvenanceToken,
    'current_source_version_vector_token' => $currentVersionVectorToken,
    'reader_cache_source_handoff_token' => $currentHandoffToken,
];
$reads = static fn (?string $handoffToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'reader_cache_source_handoff_token' => $handoffToken ?? $currentHandoffToken,
    ] + $readBase,
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $handoffToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext249(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    249,
    $publication,
    $masterDigest,
    249,
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
    $currentVersionVectorToken,
    $handoffToken ?? $currentHandoffToken,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next249'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_source_handoff_before_reuse'],
    'handoff token surfaced' => [static fn (): mixed => $plan()['reader_cache_source_handoff_token'], $currentHandoffToken],
    'inherits version vector token' => [static fn (): mixed => $plan()['current_source_version_vector_token'], $currentVersionVectorToken],
    'handoff invalidated pages' => [static fn (): mixed => $plan()['reader_cache_source_handoff_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read handoff miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read inherited vector miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read retained token current' => [static fn (): mixed => $read('read-1')['reader_cache_source_handoff_token_current'], true],
    'read stale handoff source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-source-handoff-fence-next249'],
    'read stale handoff reason' => [static fn (): mixed => $read('read-3')['reader_cache_source_handoff_token_reason'], 'reader_cache_reopened_after_source_handoff_change'],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-handoff')['reader_cache_source_handoff_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-handoff')['reader_cache_source_handoff_token_reason'], 'reader_cache_source_handoff_matches_master_journal_recovery'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-handoff')['cache_reader_cache_source_handoff_token'], $currentHandoffToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-handoff')['reader_cache_source_handoff_token'], $currentHandoffToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-handoff')['reader_cache_source_handoff_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-handoff')['reader_cache_source_handoff_token_admitted'], true],
    'row stale handoff admitted false' => [static fn (): mixed => $row('active-stale-handoff')['reader_cache_source_handoff_token_admitted'], false],
    'row stale handoff reason' => [static fn (): mixed => $row('active-stale-handoff')['reader_cache_source_handoff_token_reason'], 'reader_cache_source_handoff_predates_master_journal_recovery'],
    'row stale handoff cache token' => [static fn (): mixed => $row('active-stale-handoff')['cache_reader_cache_source_handoff_token'], $oldHandoffToken],
    'row stale handoff current token' => [static fn (): mixed => $row('active-stale-handoff')['reader_cache_source_handoff_token'], $currentHandoffToken],
    'row stale handoff mismatch' => [static fn (): mixed => $row('active-stale-handoff')['reader_cache_source_handoff_token_matches'], false],
    'row version vector inherits reason' => [static fn (): mixed => $row('usermeta-stale-version-vector')['reader_cache_source_handoff_token_reason'], 'reader_cache_current_source_version_vector_predates_master_journal_recovery'],
    'row provenance inherits reason' => [static fn (): mixed => $row('cron-stale-provenance')['reader_cache_source_handoff_token_reason'], 'reader_cache_current_source_provenance_predates_master_journal_recovery'],
    'row dirty inherits reason' => [static fn (): mixed => $row('rewrite-dirty-handoff')['reader_cache_source_handoff_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'operation invalidates stale handoff' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_source_handoff_after_master_journal_next249'), 1],
    'operation reopens stale handoff' => [static fn (): mixed => $opCount($plan(), 'reopen_reader_for_source_handoff_after_master_journal_next249'), 1],
    'dependency next249' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next249', $plan()['dependencies'], true), true],
    'dependency handoff fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-source-handoff-fence', $plan()['dependencies'], true), true],
    'dependency next246 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next246', $plan()['dependencies'], true), true],
    'non overlap mentions next246' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next246 version-vector'), true],
    'non overlap mentions rollback journal' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'rollback-journal'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldHandoffToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldHandoffToken))['next_reads'][0]['reader_cache_source_handoff_token_reason'], 'reader_ticket_source_handoff_predates_recovery'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldHandoffToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldHandoffToken)), 'reopen_reader_for_source_handoff_after_master_journal_next249'), 6],
    'all fresh no handoff invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['reader_cache_source_handoff_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed handoff invalidates admitted pages' => [static fn (): mixed => $plan(null, null, 'reader-cache-handoff:epoch=250:master=250:source=wp-options-recovered:lease=772')['reader_cache_source_handoff_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed handoff keeps inherited invalidation' => [static fn (): mixed => in_array(4, $plan(null, null, 'reader-cache-handoff:epoch=250:master=250:source=wp-options-recovered:lease=772')['invalidated_cache_page_numbers'], true), true],
    'changed handoff surfaced' => [static fn (): mixed => $plan(null, null, 'reader-cache-handoff:epoch=250:master=250:source=wp-options-recovered:lease=772')['reader_cache_source_handoff_token'], 'reader-cache-handoff:epoch=250:master=250:source=wp-options-recovered:lease=772'],
    'database bytes fixture length' => [static fn (): mixed => strlen(implode('', $before)), $pageSize * 6],
    'master bytes digest current' => [static fn (): mixed => hash('sha256', $masterBytes), hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $mapDigest($tokens), $mapDigest($tokens)],
    'header digest current' => [static fn (): mixed => $mapDigest($headers), $mapDigest($headers)],
    'handoff embeds epoch' => [static fn (): mixed => str_contains($currentHandoffToken, 'epoch=249'), true],
    'handoff embeds recovered source' => [static fn (): mixed => str_contains($currentHandoffToken, 'wp-options-recovered'), true],
    'handoff distinct from version vector' => [static fn (): mixed => $currentHandoffToken !== $currentVersionVectorToken, true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next249 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty handoff token rejected' => static fn () => $plan(null, null, ''),
    'cache missing handoff rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['reader_cache_source_handoff_token' => true])]),
    'cache empty handoff rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['reader_cache_source_handoff_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing handoff rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_cache_source_handoff_token' => true])]),
    'read empty handoff rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['reader_cache_source_handoff_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next249 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
