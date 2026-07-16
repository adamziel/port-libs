<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next238.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next238-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-schema-root-next238';
$publication = 238;
$masterDigest = hash('sha256', 'next238-master-source');
$recoverySequence = 238;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2380:size=96:mtime=23800:generation=master-current';
$databaseToken = 'dev=8:ino=2389:size=4096:mtime=23899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23900:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=238:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=238:master-journal-recovery=complete';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=237:before-master-journal-recovery';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next238.sqlite';
$usersPathToken = 'db-path-token:users:/srv/wp-content/database/wp-next238-users.sqlite';
$currentChangeCounter = 238001;
$oldChangeCounter = 237999;
$currentSchemaRootDigest = hash('sha256', 'next238 sqlite_schema root after recovered plugin table DDL');
$oldSchemaRootDigest = hash('sha256', 'next237 sqlite_schema root before plugin table DDL');
$futureSchemaRootDigest = hash('sha256', 'next239 sqlite_schema root after next plugin DDL');
$oldCleanupToken = 'master-cleanup:exists:mtime=23890:dirsync=pending';
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
$formatPage = static function (string $label) use ($pageSize, $currentChangeCounter): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $currentChangeCounter), 24, 4);
    $page = substr_replace($page, pack('N', 238), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next238 stale schema before root digest recovery'),
    2 => $page('next238 stale wp_options root before root digest recovery'),
    3 => $page('next238 stale active_plugins before root digest recovery'),
    4 => $page('next238 stale usermeta attached database page'),
    5 => $page('next238 stale rewrite_rules cleanup page'),
    6 => $page('next238 stale comments dirty page'),
];
$recovered = [
    1 => $formatPage('next238 current schema after root digest recovery'),
    2 => $page('next238 current wp_options root after root digest recovery'),
    3 => $page('next238 current active_plugins after root digest recovery'),
    4 => $page('next238 current usermeta attached database page'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 238, 0x57503238]));
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
    $mainJournal => 'dev=8:ino=2381:size=4096:mtime=23801:generation=main-current',
    $usersJournal => 'dev=8:ino=2382:size=1024:mtime=23802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-238'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-238'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 238,
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
    'database_path_token' => $mainPathToken,
    'database_change_counter' => $currentChangeCounter,
    'schema_root_digest' => $currentSchemaRootDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-current-root', $recovered[1]),
    2 => $cacheEntry('root-refreshed-current-root', $before[2]),
    3 => $cacheEntry('active-plugins-old-schema-root', $recovered[3], ['schema_root_digest' => $oldSchemaRootDigest]),
    4 => $cacheEntry('usermeta-users-path', $recovered[4], ['database_path_token' => $usersPathToken]),
    5 => $cacheEntry('rewrite-stale-cleanup', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('comments-dirty', $before[6], ['dirty' => true]),
];
$reads = static fn (?string $schemaRootDigest = null, ?int $changeCounter = null, ?string $pathToken = null, ?string $cacheSourceToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 238,
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
        'pager_cache_source_token' => $cacheSourceToken ?? $pagerCacheSourceToken,
        'database_path_token' => $pathToken ?? $mainPathToken,
        'database_change_counter' => $changeCounter ?? $currentChangeCounter,
        'schema_root_digest' => $schemaRootDigest ?? $currentSchemaRootDigest,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $schemaRootDigest = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::schemaRootDigestReaderCacheFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    238,
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
    $mainPathToken,
    $currentChangeCounter,
    $schemaRootDigest ?? $currentSchemaRootDigest,
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
$opCount = static function (array $plan): int {
    return count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_schema_root_digest_after_current_source_next238'));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next238'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_schema_root_digest_before_current_source_reuse'],
    'current schema root digest' => [static fn (): mixed => $plan()['current_schema_root_digest'], $currentSchemaRootDigest],
    'inherits change counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentChangeCounter],
    'inherits path token' => [static fn (): mixed => $plan()['current_database_path_token'], $mainPathToken],
    'schema root invalidated pages' => [static fn (): mixed => $plan()['schema_root_digest_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read miss old schema root' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count schema root' => [static fn (): mixed => $opCount($plan()), 1],
    'dependency next238' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next238', $plan()['dependencies'], true), true],
    'dependency schema root fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-schema-root-digest-fence', $plan()['dependencies'], true), true],
    'dependency next235 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next235', $plan()['dependencies'], true), true],
    'non overlap mentions next235' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next235 database change-counter'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-current-root')['schema_root_digest_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-current-root')['schema_root_digest_reason'], 'reader_cache_schema_root_digest_matches_current_source'],
    'row retained cache digest' => [static fn (): mixed => $row('schema-retained-current-root')['cache_schema_root_digest'], $currentSchemaRootDigest],
    'row retained current digest' => [static fn (): mixed => $row('schema-retained-current-root')['current_schema_root_digest'], $currentSchemaRootDigest],
    'row retained digest matches' => [static fn (): mixed => $row('schema-retained-current-root')['schema_root_digest_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-current-root')['schema_root_digest_admitted'], true],
    'row old schema root admitted false' => [static fn (): mixed => $row('active-plugins-old-schema-root')['schema_root_digest_admitted'], false],
    'row old schema root reason' => [static fn (): mixed => $row('active-plugins-old-schema-root')['schema_root_digest_reason'], 'reader_cache_schema_root_digest_predates_master_journal_current_source'],
    'row old schema root value' => [static fn (): mixed => $row('active-plugins-old-schema-root')['cache_schema_root_digest'], $oldSchemaRootDigest],
    'row old schema root mismatch' => [static fn (): mixed => $row('active-plugins-old-schema-root')['schema_root_digest_matches'], false],
    'row users path inherits reason' => [static fn (): mixed => $row('usermeta-users-path')['schema_root_digest_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup')['schema_root_digest_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty')['schema_root_digest_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained digest current' => [static fn (): mixed => $read('read-1')['schema_root_digest_current'], true],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read old schema root cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read old schema root source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-schema-root-digest-fence-current-source-next238'],
    'read old schema root reason' => [static fn (): mixed => $read('read-3')['schema_root_digest_reason'], 'reader_cache_reopened_after_schema_root_digest_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldSchemaRootDigest))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSchemaRootDigest))['next_reads'][0]['schema_root_digest_reason'], 'reader_ticket_schema_root_digest_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSchemaRootDigest))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldSchemaRootDigest))), 6],
    'stale change counter still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-counter', $recovered[3], ['database_change_counter' => $oldChangeCounter])])['reader_rows'][0]['schema_root_digest_reason'], 'reader_cache_database_change_counter_predates_master_journal_current_source'],
    'stale path still inherited' => [static fn (): mixed => $plan([4 => $cacheEntry('stale-path', $recovered[4], ['database_path_token' => $usersPathToken])])['reader_rows'][0]['schema_root_digest_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'stale cache source still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-cache-source', $recovered[3], ['pager_cache_source_token' => $oldPagerCacheSourceToken])])['reader_rows'][0]['schema_root_digest_reason'], 'reader_cache_pager_cache_source_token_predates_master_journal_current_source'],
    'all fresh no schema invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['schema_root_digest_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'future schema root invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $futureSchemaRootDigest)['schema_root_digest_invalidated_cache_page_numbers'], [1, 2, 3]],
    'future schema root surfaced' => [static fn (): mixed => $plan(null, null, $futureSchemaRootDigest)['current_schema_root_digest'], $futureSchemaRootDigest],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next238 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current schema root rejected' => static fn () => $plan(null, null, ''),
    'cache missing schema root rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-schema-root', $recovered[1]), ['schema_root_digest' => true])]),
    'cache empty schema root rejected' => static fn () => $plan([1 => $cacheEntry('empty-schema-root', $recovered[1], ['schema_root_digest' => ''])]),
    'cache short schema root rejected' => static fn () => $plan([1 => $cacheEntry('short-schema-root', $recovered[1], ['schema_root_digest' => 'abc'])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing schema root rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['schema_root_digest' => true])]),
    'read empty schema root rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['schema_root_digest' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next238 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
