<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next241.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next241-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-schema-cookie-next241';
$publication = 241;
$masterDigest = hash('sha256', 'next241-master-source');
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2410:size=96:mtime=24100:generation=master-current';
$databaseToken = 'dev=8:ino=2419:size=4096:mtime=24199:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=241:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=241:master-journal-recovery=complete';
$oldPagerCacheSourceToken = 'pager-cache-source:epoch=240:before-master-journal-recovery';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next241.sqlite';
$usersPathToken = 'db-path-token:users:/srv/wp-content/database/wp-next241-users.sqlite';
$currentChangeCounter = 241001;
$oldChangeCounter = 240999;
$currentSchemaRootDigest = hash('sha256', 'next241 sqlite_schema root after recovered plugin table DDL');
$oldSchemaRootDigest = hash('sha256', 'next240 sqlite_schema root before plugin table DDL');
$currentSchemaCookie = 24177;
$oldSchemaCookie = 24077;
$futureSchemaCookie = 24277;
$oldCleanupToken = 'master-cleanup:exists:mtime=24190:dirsync=pending';
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
$formatPage = static function (string $label) use ($pageSize, $currentChangeCounter, $currentSchemaCookie): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $currentChangeCounter), 24, 4);
    $page = substr_replace($page, pack('N', 241), 60, 4);
    $page = substr_replace($page, pack('N', $currentSchemaCookie), 40, 4);
    $page = substr_replace($page, pack('N', 0x57503241), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next241 stale schema before schema cookie recovery'),
    2 => $page('next241 stale wp_options root before schema cookie recovery'),
    3 => $page('next241 stale active_plugins before schema cookie recovery'),
    4 => $page('next241 stale usermeta attached database page'),
    5 => $page('next241 stale rewrite_rules cleanup page'),
    6 => $page('next241 stale comments dirty page'),
];
$recovered = [
    1 => $formatPage('next241 current schema after schema cookie recovery'),
    2 => $page('next241 current wp_options root after schema cookie recovery'),
    3 => $page('next241 current active_plugins after schema cookie recovery'),
    4 => $page('next241 current usermeta attached database page'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 241, 0x57503241]));
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
    $mainJournal => 'dev=8:ino=2411:size=4096:mtime=24101:generation=main-current',
    $usersJournal => 'dev=8:ino=2412:size=1024:mtime=24102:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-241'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-241'),
];
$recoveredPageDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 241,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 241,
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
    'schema_cookie' => $currentSchemaCookie,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-current-cookie', $recovered[1]),
    2 => $cacheEntry('root-refreshed-current-cookie', $before[2]),
    3 => $cacheEntry('active-plugins-old-cookie', $recovered[3], ['schema_cookie' => $oldSchemaCookie]),
    4 => $cacheEntry('usermeta-users-path', $recovered[4], ['database_path_token' => $usersPathToken]),
    5 => $cacheEntry('rewrite-stale-cleanup', $before[5], ['master_journal_cleanup_token' => $oldCleanupToken]),
    6 => $cacheEntry('comments-dirty', $before[6], ['dirty' => true]),
];
$reads = static fn (?int $schemaCookie = null, ?string $schemaRootDigest = null, ?int $changeCounter = null, ?string $pathToken = null, ?string $cacheSourceToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 241,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => 241,
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
        'schema_cookie' => $schemaCookie ?? $currentSchemaCookie,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $schemaCookie = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalReadFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    241,
    $publication,
    $masterDigest,
    241,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $mainPathToken,
    $currentChangeCounter,
    $currentSchemaRootDigest,
    $schemaCookie ?? $currentSchemaCookie,
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
    return count(array_filter($plan['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_schema_cookie_after_current_source_next241'));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next241'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_schema_cookie_before_current_source_reuse'],
    'current schema cookie' => [static fn (): mixed => $plan()['current_schema_cookie'], $currentSchemaCookie],
    'inherits schema root' => [static fn (): mixed => $plan()['current_schema_root_digest'], $currentSchemaRootDigest],
    'inherits change counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentChangeCounter],
    'schema cookie invalidated pages' => [static fn (): mixed => $plan()['schema_cookie_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read miss old schema cookie' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count schema cookie' => [static fn (): mixed => $opCount($plan()), 1],
    'dependency next241' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next241', $plan()['dependencies'], true), true],
    'dependency schema cookie fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-schema-cookie-fence', $plan()['dependencies'], true), true],
    'dependency next238 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next238', $plan()['dependencies'], true), true],
    'non overlap mentions next238' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next238 schema-root digest'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-current-cookie')['schema_cookie_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-current-cookie')['schema_cookie_reason'], 'reader_cache_schema_cookie_matches_master_journal_current_source'],
    'row retained cache cookie' => [static fn (): mixed => $row('schema-retained-current-cookie')['cache_schema_cookie'], $currentSchemaCookie],
    'row retained current cookie' => [static fn (): mixed => $row('schema-retained-current-cookie')['current_schema_cookie'], $currentSchemaCookie],
    'row retained cookie matches' => [static fn (): mixed => $row('schema-retained-current-cookie')['schema_cookie_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-current-cookie')['schema_cookie_admitted'], true],
    'row old schema cookie admitted false' => [static fn (): mixed => $row('active-plugins-old-cookie')['schema_cookie_admitted'], false],
    'row old schema cookie reason' => [static fn (): mixed => $row('active-plugins-old-cookie')['schema_cookie_reason'], 'reader_cache_schema_cookie_predates_master_journal_current_source'],
    'row old schema cookie value' => [static fn (): mixed => $row('active-plugins-old-cookie')['cache_schema_cookie'], $oldSchemaCookie],
    'row old schema cookie mismatch' => [static fn (): mixed => $row('active-plugins-old-cookie')['schema_cookie_matches'], false],
    'row users path inherits reason' => [static fn (): mixed => $row('usermeta-users-path')['schema_cookie_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'row cleanup inherits reason' => [static fn (): mixed => $row('rewrite-stale-cleanup')['schema_cookie_reason'], 'reader_cache_master_journal_cleanup_token_changed_after_recovery'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty')['schema_cookie_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained cookie current' => [static fn (): mixed => $read('read-1')['schema_cookie_current'], true],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read old schema cookie cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read old schema cookie source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-schema-cookie-fence-current-source-next241'],
    'read old schema cookie reason' => [static fn (): mixed => $read('read-3')['schema_cookie_reason'], 'reader_cache_reopened_after_schema_cookie_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldSchemaCookie))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSchemaCookie))['next_reads'][0]['schema_cookie_reason'], 'reader_ticket_schema_cookie_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSchemaCookie))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldSchemaCookie))), 6],
    'stale schema root still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-root', $recovered[3], ['schema_root_digest' => $oldSchemaRootDigest])])['reader_rows'][0]['schema_cookie_reason'], 'reader_cache_schema_root_digest_predates_master_journal_current_source'],
    'stale change counter still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-counter', $recovered[3], ['database_change_counter' => $oldChangeCounter])])['reader_rows'][0]['schema_cookie_reason'], 'reader_cache_database_change_counter_predates_master_journal_current_source'],
    'stale path still inherited' => [static fn (): mixed => $plan([4 => $cacheEntry('stale-path', $recovered[4], ['database_path_token' => $usersPathToken])])['reader_rows'][0]['schema_cookie_reason'], 'reader_cache_database_path_token_crosses_master_journal_database_slot'],
    'stale cache source still inherited' => [static fn (): mixed => $plan([3 => $cacheEntry('stale-cache-source', $recovered[3], ['pager_cache_source_token' => $oldPagerCacheSourceToken])])['reader_rows'][0]['schema_cookie_reason'], 'reader_cache_pager_cache_source_token_predates_master_journal_current_source'],
    'all fresh no cookie invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['schema_cookie_invalidated_cache_page_numbers'], []],
    'all fresh no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'future schema cookie invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $futureSchemaCookie)['schema_cookie_invalidated_cache_page_numbers'], [1, 2, 3]],
    'future schema cookie surfaced' => [static fn (): mixed => $plan(null, null, $futureSchemaCookie)['current_schema_cookie'], $futureSchemaCookie],
    'future schema cookie cache hits' => [static fn (): mixed => array_values($plan(null, null, $futureSchemaCookie)['read_cache_hits']), [false, false, false, false, false, false]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next241 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'zero current schema cookie rejected' => static fn () => $plan(null, null, 0),
    'negative current schema cookie rejected' => static fn () => $plan(null, null, -1),
    'cache missing schema cookie rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-schema-cookie', $recovered[1]), ['schema_cookie' => true])]),
    'cache string schema cookie rejected' => static fn () => $plan([1 => $cacheEntry('string-schema-cookie', $recovered[1], ['schema_cookie' => '241'])]),
    'cache zero schema cookie rejected' => static fn () => $plan([1 => $cacheEntry('zero-schema-cookie', $recovered[1], ['schema_cookie' => 0])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing schema cookie rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['schema_cookie' => true])]),
    'read string schema cookie rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['schema_cookie' => '241'])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next241 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
