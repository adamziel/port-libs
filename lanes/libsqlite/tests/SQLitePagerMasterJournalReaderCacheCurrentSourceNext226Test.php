<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next226.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next226-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-header-counter-next226';
$publication = 226;
$masterDigest = hash('sha256', 'next226-master-source');
$recoverySequence = 226;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2260:size=96:mtime=22600:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2269:size=2560:mtime=22699:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=226;change-counter=58;version-valid-for=58;page-count=5');
$oldHeaderDigest = hash('sha256', 'schema-cookie=225;change-counter=57;version-valid-for=57;page-count=5');
$currentPageCount = 5;
$currentCounter = 58;
$oldCounter = 57;
$futureCounter = 59;
$oldDatabaseToken = 'dev=8:ino=2269:size=2560:mtime=22698:generation=database-prior';
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
    $page = substr_replace($page, pack('N', 226), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503236), 68, 4);
    $page = substr_replace($page, pack('N', 58), 24, 4);
    $page = substr_replace($page, pack('N', 58), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next226 stale schema before counter recovery'),
    2 => $page('next226 stale wp_options root before counter recovery'),
    3 => $page('next226 stale active_plugins before counter recovery'),
    4 => $page('next226 stale cron before counter recovery'),
    5 => $page('next226 stale rewrite_rules before counter recovery'),
];
$recovered = [
    1 => $formatPage('next226 current schema after counter recovery'),
    2 => $page('next226 current wp_options root after counter recovery'),
    3 => $page('next226 current active_plugins after counter recovery'),
    4 => $page('next226 current cron after counter recovery'),
    5 => $page('next226 current rewrite_rules after counter recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 226, 0x57503236]));
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
    $mainJournal => 'dev=8:ino=2261:size=4096:mtime=22601:generation=main-current',
    $usersJournal => 'dev=8:ino=2262:size=1024:mtime=22602:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-226'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-226'),
];
$oldMemberHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-226'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, int $change, int $valid, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 226,
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
    'database_page_count' => $currentPageCount,
    'database_change_counter' => $change,
    'version_valid_for' => $valid,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-counter', $recovered[1], $currentCounter, $currentCounter),
    2 => $cacheEntry('root-refreshed-counter', $before[2], $currentCounter, $currentCounter),
    3 => $cacheEntry('active-stale-counter', $recovered[3], $oldCounter, $oldCounter),
    4 => $cacheEntry('cron-incoherent-counter', $recovered[4], $currentCounter, $oldCounter),
    5 => $cacheEntry('rewrite-stale-header', $recovered[5], $currentCounter, $currentCounter, ['database_header_digest' => $oldHeaderDigest]),
];
$reads = static fn (int $change = null, int $valid = null, string $headerDigest = null, string $databaseToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 226,
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
        'database_page_count' => $currentPageCount,
        'database_change_counter' => $change ?? $currentCounter,
        'version_valid_for' => $valid ?? $currentCounter,
    ],
    range(1, 5),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $change = null,
    ?int $valid = null,
    ?string $databaseHeader = null,
    ?string $databaseToken = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext226(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    226,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $databaseToken ?? $currentDatabaseToken,
    $databaseHeader ?? $currentHeaderDigest,
    $currentPageCount,
    $change ?? $currentCounter,
    $valid ?? $currentCounter,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next226'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_change_counter_version_valid_for_before_current_source_reuse'],
    'current change counter' => [static fn (): mixed => $plan()['current_database_change_counter'], $currentCounter],
    'current version valid for' => [static fn (): mixed => $plan()['current_version_valid_for'], $currentCounter],
    'inherits current page count' => [static fn (): mixed => $plan()['current_database_page_count'], $currentPageCount],
    'counter invalidated pages' => [static fn (): mixed => $plan()['header_counter_invalidated_cache_page_numbers'], [3, 4]],
    'incoherent pages' => [static fn (): mixed => $plan()['header_counter_incoherent_cache_page_numbers'], [4]],
    'all invalidated pages include header digest fence' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale counter' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit incoherent counter' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'header counter invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_header_counter_pair_after_current_source_next226'), 2],
    'dependency next226' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next226', $plan()['dependencies'], true), true],
    'dependency counter fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-header-counter-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next219' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next219 database page-count admission'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-counter')['header_counter_pair_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-counter')['header_counter_pair_reason'], 'reader_cache_header_counter_pair_matches_current_source'],
    'row retained change counter' => [static fn (): mixed => $row('schema-retained-counter')['cache_database_change_counter'], $currentCounter],
    'row retained version valid for' => [static fn (): mixed => $row('schema-retained-counter')['cache_version_valid_for'], $currentCounter],
    'row retained current change counter' => [static fn (): mixed => $row('schema-retained-counter')['current_database_change_counter'], $currentCounter],
    'row retained current version valid for' => [static fn (): mixed => $row('schema-retained-counter')['current_version_valid_for'], $currentCounter],
    'row retained coherent' => [static fn (): mixed => $row('schema-retained-counter')['header_counter_pair_coherent'], true],
    'row retained matches' => [static fn (): mixed => $row('schema-retained-counter')['header_counter_pair_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-counter')['header_counter_pair_admitted'], true],
    'row refreshed reason' => [static fn (): mixed => $row('root-refreshed-counter')['header_counter_pair_reason'], 'reader_cache_header_counter_pair_matches_current_source'],
    'row stale counter admitted false' => [static fn (): mixed => $row('active-stale-counter')['header_counter_pair_admitted'], false],
    'row stale counter reason' => [static fn (): mixed => $row('active-stale-counter')['header_counter_pair_reason'], 'reader_cache_header_counter_pair_changed_after_master_journal_recovery'],
    'row stale counter coherent' => [static fn (): mixed => $row('active-stale-counter')['header_counter_pair_coherent'], true],
    'row stale counter mismatch' => [static fn (): mixed => $row('active-stale-counter')['header_counter_pair_matches'], false],
    'row incoherent admitted false' => [static fn (): mixed => $row('cron-incoherent-counter')['header_counter_pair_admitted'], false],
    'row incoherent reason' => [static fn (): mixed => $row('cron-incoherent-counter')['header_counter_pair_reason'], 'reader_cache_header_counter_pair_incoherent_after_master_journal_recovery'],
    'row incoherent coherent false' => [static fn (): mixed => $row('cron-incoherent-counter')['header_counter_pair_coherent'], false],
    'row stale header inherits reason' => [static fn (): mixed => $row('rewrite-stale-header')['header_counter_pair_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'read retained counter current' => [static fn (): mixed => $read('read-1')['header_counter_pair_current'], true],
    'read retained coherent' => [static fn (): mixed => $read('read-1')['header_counter_pair_coherent'], true],
    'read retained change counter value' => [static fn (): mixed => $read('read-1')['database_change_counter'], $currentCounter],
    'read retained valid for value' => [static fn (): mixed => $read('read-1')['version_valid_for'], $currentCounter],
    'read stale counter source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-header-counter-fence-current-source-next226'],
    'read stale counter reason' => [static fn (): mixed => $read('read-3')['header_counter_pair_reason'], 'reader_cache_reopened_after_header_counter_change'],
    'read incoherent counter reason' => [static fn (): mixed => $read('read-4')['header_counter_pair_reason'], 'reader_cache_reopened_after_header_counter_change'],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldCounter, $oldCounter))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldCounter, $oldCounter))['next_reads'][0]['header_counter_pair_reason'], 'reader_ticket_header_counter_pair_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldCounter, $oldCounter))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5']],
    'incoherent read ticket reason' => [static fn (): mixed => $plan(null, $reads($currentCounter, $oldCounter))['next_reads'][0]['header_counter_pair_reason'], 'reader_ticket_header_counter_pair_incoherent'],
    'future current counter invalidates admitted cache' => [static fn (): mixed => $plan(null, null, $futureCounter, $futureCounter)['header_counter_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
    'future current counter surfaces current value' => [static fn (): mixed => $plan(null, null, $futureCounter, $futureCounter)['current_database_change_counter'], $futureCounter],
    'changed header still inherited' => [static fn (): mixed => $plan(null, null, null, null, hash('sha256', 'new-current-header'))['database_header_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'changed database token still inherited' => [static fn (): mixed => $plan(null, null, null, null, null, $oldDatabaseToken)['database_file_token_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'all fresh cache no counter invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentCounter, $currentCounter)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['header_counter_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentCounter, $currentCounter)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'old counter fixture differs' => [static fn (): mixed => $oldCounter !== $currentCounter, true],
    'database bytes fixture length' => [static fn (): mixed => strlen($databaseBytes), $pageSize * 5],
    'master bytes digest current' => [static fn (): mixed => $currentMasterBytesDigest, hash('sha256', $masterBytes)],
    'token digest current' => [static fn (): mixed => $currentTokenDigest, $mapDigest($currentTokens)],
    'member header digest current' => [static fn (): mixed => $currentMemberHeaderDigest, $mapDigest($currentHeaders)],
    'order digest current' => [static fn (): mixed => $orderDigest, hash('sha256', implode("\n", $members))],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next226 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'zero current change counter rejected' => static fn () => $plan(null, null, 0, $currentCounter),
    'zero current version valid for rejected' => static fn () => $plan(null, null, $currentCounter, 0),
    'incoherent current pair rejected' => static fn () => $plan(null, null, $currentCounter, $oldCounter),
    'missing cache change counter rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_change_counter' => true])]),
    'missing cache valid for rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['version_valid_for' => true])]),
    'zero cache change counter rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['database_change_counter' => 0])]),
    'string cache valid for rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['version_valid_for' => '58'])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'missing read change counter rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['database_change_counter' => true])]),
    'missing read valid for rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['version_valid_for' => true])]),
    'zero read change counter rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['database_change_counter' => 0])]),
    'empty read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'inherits next219 missing page count rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_page_count' => true])]),
    'inherits next217 missing header rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_header_digest' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next226 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
