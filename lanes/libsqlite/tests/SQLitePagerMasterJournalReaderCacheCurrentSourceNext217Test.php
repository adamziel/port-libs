<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next217.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next217-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-header-next217';
$publication = 217;
$masterDigest = hash('sha256', 'next217-master-source');
$recoverySequence = 217;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2170:size=96:mtime=21700:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2179:size=3584:mtime=21799:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=217;change-counter=44;version-valid-for=44;page-count=5');
$oldHeaderDigest = hash('sha256', 'schema-cookie=216;change-counter=43;version-valid-for=43;page-count=6');
$oldDatabaseToken = 'dev=8:ino=2179:size=3584:mtime=21798:generation=database-prior';
$members = [$mainJournal, $usersJournal];
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
    $page = substr_replace($page, pack('N', 217), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503237), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next217 stale schema before header recovery'),
    2 => $page('next217 stale wp_options root before header recovery'),
    3 => $page('next217 stale active_plugins before header recovery'),
    4 => $page('next217 stale usermeta before header recovery'),
    5 => $page('next217 stale cron before header recovery'),
    6 => $page('next217 stale comments before header recovery'),
];
$recovered = [
    1 => $formatPage('next217 current schema after header recovery'),
    2 => $page('next217 current wp_options root after header recovery'),
    3 => $page('next217 current active_plugins after header recovery'),
    4 => $page('next217 current usermeta after header recovery'),
    5 => $page('next217 current cron after header recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 217, 0x57503237]));
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
    $mainJournal => 'dev=8:ino=2171:size=4096:mtime=21701:generation=main-current',
    $usersJournal => 'dev=8:ino=2172:size=1024:mtime=21702:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-217'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-217'),
];
$oldMemberHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-217'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 217,
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
    'database_header_digest' => $currentHeaderDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-header', $recovered[1]),
    2 => $cacheEntry('root-refreshed-header', $before[2]),
    3 => $cacheEntry('active-stale-header', $recovered[3], ['database_header_digest' => $oldHeaderDigest]),
    4 => $cacheEntry('usermeta-stale-database-token', $recovered[4], ['database_file_token' => $oldDatabaseToken]),
    5 => $cacheEntry('cron-stale-member-header', $recovered[5], ['member_journal_header_digests' => $oldMemberHeaders]),
    6 => $cacheEntry('comments-dirty-header', $before[6], ['dirty' => true]),
];
$reads = static fn (string $headerDigest = null, string $databaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 217,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentMemberHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
        'database_file_token' => $databaseToken ?? $currentDatabaseToken,
        'database_header_digest' => $headerDigest ?? $currentHeaderDigest,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $databaseHeader = null,
    ?string $databaseToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext217(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    217,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $databaseToken ?? $currentDatabaseToken,
    $databaseHeader ?? $currentHeaderDigest,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next217'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_header_digest_before_current_source_reuse'],
    'current header digest' => [static fn (): mixed => $plan()['current_database_header_digest'], $currentHeaderDigest],
    'inherits database token' => [static fn (): mixed => $plan()['current_database_file_token'], $currentDatabaseToken],
    'inherits master bytes digest' => [static fn (): mixed => $plan()['current_master_journal_bytes_digest'], $currentMasterBytesDigest],
    'header invalidated pages' => [static fn (): mixed => $plan()['database_header_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages include inherited fences' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale header' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'header invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_database_header_after_current_source_next217'), 1],
    'dependency next217' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next217', $plan()['dependencies'], true), true],
    'dependency header fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-header-digest-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next212' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next212 database file-token'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-header')['database_header_digest_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-header')['database_header_digest_reason'], 'reader_cache_database_header_digest_matches_current_source'],
    'row retained cache digest' => [static fn (): mixed => $row('schema-retained-header')['cache_database_header_digest'], $currentHeaderDigest],
    'row retained current digest' => [static fn (): mixed => $row('schema-retained-header')['current_database_header_digest'], $currentHeaderDigest],
    'row retained digest matches' => [static fn (): mixed => $row('schema-retained-header')['database_header_digest_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-header')['database_header_digest_admitted'], true],
    'row refreshed reason' => [static fn (): mixed => $row('root-refreshed-header')['database_header_digest_reason'], 'reader_cache_database_header_digest_matches_current_source'],
    'row stale header admitted false' => [static fn (): mixed => $row('active-stale-header')['database_header_digest_admitted'], false],
    'row stale header reason' => [static fn (): mixed => $row('active-stale-header')['database_header_digest_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'row stale header cache digest' => [static fn (): mixed => $row('active-stale-header')['cache_database_header_digest'], $oldHeaderDigest],
    'row stale header current digest' => [static fn (): mixed => $row('active-stale-header')['current_database_header_digest'], $currentHeaderDigest],
    'row stale header mismatch' => [static fn (): mixed => $row('active-stale-header')['database_header_digest_matches'], false],
    'row stale database token inherits reason' => [static fn (): mixed => $row('usermeta-stale-database-token')['database_header_digest_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row stale member header inherits reason' => [static fn (): mixed => $row('cron-stale-member-header')['database_header_digest_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('comments-dirty-header')['database_header_digest_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained header current' => [static fn (): mixed => $read('read-1')['database_header_digest_current'], true],
    'read retained header digest' => [static fn (): mixed => $read('read-1')['database_header_digest'], $currentHeaderDigest],
    'read stale header reason' => [static fn (): mixed => $read('read-3')['database_header_digest_reason'], 'reader_cache_reopened_after_database_header_digest_change'],
    'read stale header source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-database-header-fence-current-source-next217'],
    'stale read ticket forces miss' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['next_reads'][0]['database_header_digest_reason'], 'reader_ticket_database_header_digest_predates_current_source'],
    'all current read tickets keep first hit' => [static fn (): mixed => $plan(null, $reads($currentHeaderDigest))['read_cache_hits']['read-1'], true],
    'changed current header invalidates all otherwise admitted cache' => [static fn (): mixed => $plan(null, null, hash('sha256', 'new-current-header'))['database_header_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed database token still inherited' => [static fn (): mixed => $plan(null, null, null, 'new-database-token')['invalidated_cache_page_numbers'], [1, 2, 3, 4, 5, 6]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next217 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'blank current header rejected' => static fn () => $plan(null, null, ''),
    'missing cache header rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_header_digest' => true])]),
    'empty cache header rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['database_header_digest' => ''])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'missing read header rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_header_digest' => true])]),
    'empty read header rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['database_header_digest' => ''])]),
    'empty read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'inherits next212 missing database token rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_file_token' => true])]),
    'inherits next212 missing read database token rejection' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_file_token' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next217 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
