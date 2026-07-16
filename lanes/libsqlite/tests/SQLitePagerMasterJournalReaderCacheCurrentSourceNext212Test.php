<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next212.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next212-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-database-token-next212';
$publication = 212;
$masterDigest = hash('sha256', 'next212-master-source');
$recoverySequence = 212;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2120:size=96:mtime=21200:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2129:size=3584:mtime=21299:generation=database-current';
$oldDatabaseToken = 'dev=8:ino=2129:size=3584:mtime=21298:generation=database-prior';
$oldMasterToken = 'dev=8:ino=2120:size=96:mtime=21199:generation=master-prior';
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
    $page = substr_replace($page, pack('N', 212), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503232), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next212 stale schema before database token recovery'),
    2 => $page('next212 stale wp_options root before database token recovery'),
    3 => $page('next212 stale active_plugins before database token recovery'),
    4 => $page('next212 stale usermeta before database token recovery'),
    5 => $page('next212 stale rewrite_rules before database token recovery'),
    6 => $page('next212 stale cron before database token recovery'),
    7 => $page('next212 stale comments before database token recovery'),
];
$recovered = [
    1 => $formatPage('next212 current schema after database token recovery'),
    2 => $page('next212 current wp_options root after database token recovery'),
    3 => $page('next212 current active_plugins after database token recovery'),
    4 => $page('next212 current usermeta after database token recovery'),
    6 => $page('next212 current cron after database token recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 212, 0x57503232]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 211, 0x57503231]));
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
$currentTokens = [
    $mainJournal => 'dev=8:ino=2121:size=4096:mtime=21201:generation=main-current',
    $usersJournal => 'dev=8:ino=2122:size=1024:mtime=21202:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-212'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-212'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-212'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentHeaderDigest = $mapDigest($currentHeaders);
$oldHeaderDigest = $mapDigest($oldHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 212,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
    'member_journal_header_digests' => $currentHeaders,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $currentMasterToken,
    'master_journal_bytes_digest' => $currentMasterBytesDigest,
    'database_file_token' => $currentDatabaseToken,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-database-token', $recovered[1]),
    2 => $cacheEntry('root-refreshed-database-token', $before[2]),
    3 => $cacheEntry('active-stale-database-token', $recovered[3], ['database_file_token' => $oldDatabaseToken]),
    4 => $cacheEntry('usermeta-stale-header', $recovered[4], ['member_journal_header_digests' => $oldHeaders]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-master-token', $recovered[6], ['master_journal_file_token' => $oldMasterToken]),
    7 => $cacheEntry('comments-dirty-database-token', $before[7], ['dirty' => true]),
];
$reads = static fn (string $databaseToken = null, string $masterToken = null, string $header = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 212,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $header ?? $currentHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken ?? $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
        'database_file_token' => $databaseToken ?? $currentDatabaseToken,
    ],
    range(1, 7),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $databaseToken = null,
    ?string $masterToken = null,
    ?array $headers = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPublicationGenerationFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    212,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $headers ?? $currentHeaders,
    $masterToken ?? $currentMasterToken,
    $databaseToken ?? $currentDatabaseToken,
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
$opCount = static function (string $op) use ($plan): int {
    return count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next212'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_file_token_before_current_source_reuse'],
    'database token' => [static fn (): mixed => $plan()['current_database_file_token'], $currentDatabaseToken],
    'inherits master bytes digest' => [static fn (): mixed => $plan()['current_master_journal_bytes_digest'], $currentMasterBytesDigest],
    'inherits master file token' => [static fn (): mixed => $plan()['current_master_journal_file_token'], $currentMasterToken],
    'inherits order digest' => [static fn (): mixed => $plan()['current_master_member_order_digest'], $orderDigest],
    'inherits header digest' => [static fn (): mixed => $plan()['current_member_journal_header_digest'], $currentHeaderDigest],
    'database token invalidated pages' => [static fn (): mixed => $plan()['database_file_token_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages include inherited fences' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'read hit map retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit map refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit map database token stale' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count database token' => [static fn (): mixed => $opCount('invalidate_reader_cache_database_file_token_after_current_source_next212'), 1],
    'dependency next212' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next212', $plan()['dependencies'], true), true],
    'dependency token fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-file-token-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next209' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next209 raw master-journal bytes'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-database-token')['database_file_token_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-database-token')['database_file_token_reason'], 'reader_cache_database_file_token_matches_current_source'],
    'row retained cache token' => [static fn (): mixed => $row('schema-retained-database-token')['cache_database_file_token'], $currentDatabaseToken],
    'row retained current token' => [static fn (): mixed => $row('schema-retained-database-token')['current_database_file_token'], $currentDatabaseToken],
    'row retained token matches' => [static fn (): mixed => $row('schema-retained-database-token')['database_file_token_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-database-token')['database_file_token_admitted'], true],
    'row refreshed token reason' => [static fn (): mixed => $row('root-refreshed-database-token')['database_file_token_reason'], 'reader_cache_database_file_token_matches_current_source'],
    'row database token stale admitted false' => [static fn (): mixed => $row('active-stale-database-token')['database_file_token_admitted'], false],
    'row database token stale reason' => [static fn (): mixed => $row('active-stale-database-token')['database_file_token_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row database token stale cache token' => [static fn (): mixed => $row('active-stale-database-token')['cache_database_file_token'], $oldDatabaseToken],
    'row database token stale current token' => [static fn (): mixed => $row('active-stale-database-token')['current_database_file_token'], $currentDatabaseToken],
    'row database token stale mismatch' => [static fn (): mixed => $row('active-stale-database-token')['database_file_token_matches'], false],
    'row header stale inherits reason' => [static fn (): mixed => $row('usermeta-stale-header')['database_file_token_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row format stale inherits reason' => [static fn (): mixed => $row('rewrite-stale-format')['database_file_token_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row master token stale inherits reason' => [static fn (): mixed => $row('cron-stale-master-token')['database_file_token_reason'], 'reader_cache_master_journal_file_token_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty-database-token')['database_file_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained database token current' => [static fn (): mixed => $read('read-1')['database_file_token_current'], true],
    'read retained database token value' => [static fn (): mixed => $read('read-1')['database_file_token'], $currentDatabaseToken],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale page cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale page source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-database-file-token-fence-current-source-next212'],
    'read stale page reason' => [static fn (): mixed => $read('read-3')['database_file_token_reason'], 'reader_cache_reopened_after_database_file_token_change'],
    'read inherited header miss' => [static fn (): mixed => $read('read-4')['cache_hit'], false],
    'read inherited format miss' => [static fn (): mixed => $read('read-5')['cache_hit'], false],
    'read inherited master token miss' => [static fn (): mixed => $read('read-6')['cache_hit'], false],
    'read inherited dirty miss' => [static fn (): mixed => $read('read-7')['cache_hit'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['next_reads'][0]['database_file_token_reason'], 'reader_ticket_database_file_token_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'current read ticket with stale cache only reopens inherited readers' => [static fn (): mixed => $plan(null, $reads())['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'all fresh cache no invalidated database token pages' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_file_token_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed current database token invalidates previously fresh cache' => [static fn (): mixed => $plan(null, null, 'dev=8:ino=2129:size=3584:mtime=21300:generation=database-new')['database_file_token_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed current database token keeps inherited format invalidation' => [static fn (): mixed => in_array(5, $plan(null, null, 'dev=8:ino=2129:size=3584:mtime=21300:generation=database-new')['invalidated_cache_page_numbers'], true), true],
    'changed current database token current value surfaced' => [static fn (): mixed => $plan(null, null, 'dev=8:ino=2129:size=3584:mtime=21300:generation=database-new')['current_database_file_token'], 'dev=8:ino=2129:size=3584:mtime=21300:generation=database-new'],
    'alternate master token inherited invalidation still present' => [static fn (): mixed => $plan(null, null, null, 'dev=8:ino=2120:size=96:mtime=21201:generation=master-new')['master_journal_file_token_invalidated_cache_page_numbers'], [1, 2, 3, 6]],
    'alternate headers inherited invalidation still present' => [static fn (): mixed => $plan(null, null, null, null, $oldHeaders)['member_header_invalidated_cache_page_numbers'], [1, 2, 3, 6]],
    'old header digest fixture differs' => [static fn (): mixed => $oldHeaderDigest !== $currentHeaderDigest, true],
    'read stale ticket operation count' => [static fn (): mixed => count(array_filter($plan(null, $reads($oldDatabaseToken))['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_database_file_token_after_current_source_next212')), 7],
    'read stale ticket source' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['next_reads'][1]['source'], 'master-journal-reader-cache-database-file-token-fence-current-source-next212'],
    'read stale ticket database token current false' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['next_reads'][1]['database_file_token_current'], false],
    'read stale ticket database token assigned current' => [static fn (): mixed => $plan(null, $reads($oldDatabaseToken))['next_reads'][1]['database_file_token'], $currentDatabaseToken],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next212 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database token rejected' => static fn () => $plan(null, null, ''),
    'cache missing database token rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('missing-token', $recovered[1]), ['database_file_token' => true])]),
    'cache empty database token rejected' => static fn () => $plan([1 => $cacheEntry('empty-token', $recovered[1], ['database_file_token' => ''])]),
    'cache bad page rejected' => static fn () => $plan([0 => $cacheEntry('bad-page', $recovered[1])]),
    'read missing database token rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_file_token' => true])]),
    'read empty database token rejected' => static fn () => $plan(null, [array_merge($reads()[0], ['database_file_token' => ''])]),
    'read missing reader id rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['reader_id' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next212 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
