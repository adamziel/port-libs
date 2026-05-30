<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next213.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next213-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-header-digest-next213';
$publication = 213;
$masterDigest = hash('sha256', 'next213-master-source');
$recoverySequence = 213;
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2130:size=96:mtime=21300:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2139:size=4096:mtime=21399:generation=database-current';
$oldDatabaseToken = 'dev=8:ino=2139:size=4096:mtime=21398:generation=database-prior';
$oldMasterToken = 'dev=8:ino=2130:size=96:mtime=21299:generation=master-prior';
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
$formatPage = static function (string $label, int $changeCounter, int $schemaCookie, int $versionValidFor) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', $changeCounter), 24, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 213), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503233), 68, 4);
    $page = substr_replace($page, pack('N', $schemaCookie), 40, 4);
    $page = substr_replace($page, pack('N', $versionValidFor), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$headerDigest = static fn (string $pageOne): string => hash('sha256', substr($pageOne, 0, 100));
$before = [
    1 => $formatPage('next213 stale schema before header digest recovery', 212, 70, 212),
    2 => $page('next213 stale wp_options root before header digest recovery'),
    3 => $page('next213 stale active_plugins before header digest recovery'),
    4 => $page('next213 stale usermeta before header digest recovery'),
    5 => $page('next213 stale rewrite_rules before header digest recovery'),
    6 => $page('next213 stale cron before header digest recovery'),
    7 => $page('next213 stale comments before header digest recovery'),
    8 => $page('next213 stale terms before header digest recovery'),
];
$recovered = [
    1 => $formatPage('next213 current schema after header digest recovery', 213, 71, 213),
    2 => $page('next213 current wp_options root after header digest recovery'),
    3 => $page('next213 current active_plugins after header digest recovery'),
    4 => $page('next213 current usermeta after header digest recovery'),
    6 => $page('next213 current cron after header digest recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 213, 0x57503233]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 212, 0x57503232]));
$currentHeaderDigest = $headerDigest($recovered[1]);
$oldHeaderDigest = $headerDigest($before[1]);
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
    $mainJournal => 'dev=8:ino=2131:size=4096:mtime=21301:generation=main-current',
    $usersJournal => 'dev=8:ino=2132:size=1024:mtime=21302:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-213'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-213'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-213'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentJournalHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 213,
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
    1 => $cacheEntry('schema-retained-header-digest', $recovered[1]),
    2 => $cacheEntry('root-refreshed-header-digest', $before[2]),
    3 => $cacheEntry('active-stale-header-digest', $recovered[3], ['database_header_digest' => $oldHeaderDigest]),
    4 => $cacheEntry('usermeta-stale-database-token', $recovered[4], ['database_file_token' => $oldDatabaseToken]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-master-token', $recovered[6], ['master_journal_file_token' => $oldMasterToken]),
    7 => $cacheEntry('comments-stale-member-header', $before[7], ['member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-header-digest', $before[8], ['dirty' => true]),
];
$reads = static fn (string $databaseHeaderDigest = null, string $databaseToken = null, string $masterToken = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 213,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $currentTokenDigest,
        'member_journal_header_digest' => $currentJournalHeaderDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken ?? $currentMasterToken,
        'master_journal_bytes_digest' => $currentMasterBytesDigest,
        'database_file_token' => $databaseToken ?? $currentDatabaseToken,
        'database_header_digest' => $databaseHeaderDigest ?? $currentHeaderDigest,
    ],
    range(1, 8),
);
$plan = static fn (?array $readerCache = null, ?array $readList = null, ?array $recoveredPages = null): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::databaseHeaderDigestFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    213,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $currentDatabaseToken,
);
$changedRecovered = [1 => $formatPage('next213 changed schema after second recovery', 214, 71, 214)] + array_slice($recovered, 1, null, true);
$changedRecoveredDigest = $recoveredDigest($changedRecovered);
$changedHeaderDigest = $headerDigest($changedRecovered[1]);
$changedCache = static fn (): array => [
    1 => $cacheEntry('schema-retained-header-digest', $changedRecovered[1], ['recovered_page_set_digest' => $changedRecoveredDigest]),
    2 => $cacheEntry('root-refreshed-header-digest', $before[2], ['recovered_page_set_digest' => $changedRecoveredDigest]),
    3 => $cacheEntry('active-stale-header-digest', $recovered[3], ['recovered_page_set_digest' => $changedRecoveredDigest, 'database_header_digest' => $oldHeaderDigest]),
    4 => $cacheEntry('usermeta-stale-database-token', $recovered[4], ['recovered_page_set_digest' => $changedRecoveredDigest, 'database_file_token' => $oldDatabaseToken]),
    5 => $cacheEntry('rewrite-stale-format', $before[5], ['recovered_page_set_digest' => $changedRecoveredDigest, 'format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('cron-stale-master-token', $recovered[6], ['recovered_page_set_digest' => $changedRecoveredDigest, 'master_journal_file_token' => $oldMasterToken]),
    7 => $cacheEntry('comments-stale-member-header', $before[7], ['recovered_page_set_digest' => $changedRecoveredDigest, 'member_journal_header_digests' => $oldHeaders]),
    8 => $cacheEntry('terms-dirty-header-digest', $before[8], ['recovered_page_set_digest' => $changedRecoveredDigest, 'dirty' => true]),
];
$changedReads = static fn (): array => array_map(
    static fn (array $read): array => array_replace($read, ['recovered_page_set_digest' => $changedRecoveredDigest, 'database_header_digest' => $changedHeaderDigest]),
    $reads(),
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next213'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_database_header_digest_before_current_source_reuse'],
    'current header digest' => [static fn (): mixed => $plan()['current_database_header_digest'], $currentHeaderDigest],
    'old header differs' => [static fn (): mixed => $oldHeaderDigest !== $currentHeaderDigest, true],
    'inherits database token' => [static fn (): mixed => $plan()['current_database_file_token'], $currentDatabaseToken],
    'inherits master bytes digest' => [static fn (): mixed => $plan()['current_master_journal_bytes_digest'], $currentMasterBytesDigest],
    'header invalidated pages' => [static fn (): mixed => $plan()['database_header_digest_invalidated_cache_page_numbers'], [3]],
    'all invalidated pages include inherited fences' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read header stale miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'operation count header digest' => [static fn (): mixed => $opCount($plan(), 'invalidate_reader_cache_database_header_digest_after_current_source_next213'), 1],
    'dependency next213' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next213', $plan()['dependencies'], true), true],
    'dependency header fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-database-header-digest-fence', $plan()['dependencies'], true), true],
    'dependency next212 retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next212', $plan()['dependencies'], true), true],
    'non overlap mentions next212' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next212 database file-token'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-header-digest')['database_header_digest_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-header-digest')['database_header_digest_reason'], 'reader_cache_database_header_digest_matches_current_source'],
    'row retained cache digest' => [static fn (): mixed => $row('schema-retained-header-digest')['cache_database_header_digest'], $currentHeaderDigest],
    'row retained current digest' => [static fn (): mixed => $row('schema-retained-header-digest')['current_database_header_digest'], $currentHeaderDigest],
    'row retained digest matches' => [static fn (): mixed => $row('schema-retained-header-digest')['database_header_digest_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-header-digest')['database_header_digest_admitted'], true],
    'row refreshed reason' => [static fn (): mixed => $row('root-refreshed-header-digest')['database_header_digest_reason'], 'reader_cache_database_header_digest_matches_current_source'],
    'row header stale admitted false' => [static fn (): mixed => $row('active-stale-header-digest')['database_header_digest_admitted'], false],
    'row header stale reason' => [static fn (): mixed => $row('active-stale-header-digest')['database_header_digest_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'row header stale cache digest' => [static fn (): mixed => $row('active-stale-header-digest')['cache_database_header_digest'], $oldHeaderDigest],
    'row header stale current digest' => [static fn (): mixed => $row('active-stale-header-digest')['current_database_header_digest'], $currentHeaderDigest],
    'row header stale mismatch' => [static fn (): mixed => $row('active-stale-header-digest')['database_header_digest_matches'], false],
    'row database token stale inherits reason' => [static fn (): mixed => $row('usermeta-stale-database-token')['database_header_digest_reason'], 'reader_cache_database_file_token_changed_after_master_journal_recovery'],
    'row format stale inherits reason' => [static fn (): mixed => $row('rewrite-stale-format')['database_header_digest_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row master token stale inherits reason' => [static fn (): mixed => $row('cron-stale-master-token')['database_header_digest_reason'], 'reader_cache_master_journal_file_token_changed'],
    'row member header stale inherits reason' => [static fn (): mixed => $row('comments-stale-member-header')['database_header_digest_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'row dirty inherits reason' => [static fn (): mixed => $row('terms-dirty-header-digest')['database_header_digest_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'read retained header current' => [static fn (): mixed => $read('read-1')['database_header_digest_current'], true],
    'read retained header digest assigned' => [static fn (): mixed => $read('read-1')['database_header_digest'], $currentHeaderDigest],
    'read retained cache hit' => [static fn (): mixed => $read('read-1')['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $read('read-2')['cache_hit'], true],
    'read stale page cache miss' => [static fn (): mixed => $read('read-3')['cache_hit'], false],
    'read stale page source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-database-header-digest-fence-current-source-next213'],
    'read stale page reason' => [static fn (): mixed => $read('read-3')['database_header_digest_reason'], 'reader_cache_reopened_after_database_header_digest_change'],
    'read inherited database token miss' => [static fn (): mixed => $read('read-4')['cache_hit'], false],
    'read inherited format miss' => [static fn (): mixed => $read('read-5')['cache_hit'], false],
    'read inherited master token miss' => [static fn (): mixed => $read('read-6')['cache_hit'], false],
    'read inherited member header miss' => [static fn (): mixed => $read('read-7')['cache_hit'], false],
    'read inherited dirty miss' => [static fn (): mixed => $read('read-8')['cache_hit'], false],
    'stale read ticket cache miss' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['next_reads'][0]['database_header_digest_reason'], 'reader_ticket_database_header_digest_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'stale read ticket operation count' => [static fn (): mixed => $opCount($plan(null, $reads($oldHeaderDigest)), 'invalidate_reader_cache_database_header_digest_after_current_source_next213'), 8],
    'stale read ticket source' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['next_reads'][1]['source'], 'master-journal-reader-cache-database-header-digest-fence-current-source-next213'],
    'stale read ticket current false' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['next_reads'][1]['database_header_digest_current'], false],
    'stale read ticket digest assigned current' => [static fn (): mixed => $plan(null, $reads($oldHeaderDigest))['next_reads'][1]['database_header_digest'], $currentHeaderDigest],
    'stale database token ticket still inherited' => [static fn (): mixed => $plan(null, $reads($currentHeaderDigest, $oldDatabaseToken))['next_reads'][0]['database_file_token_reason'], 'reader_ticket_database_file_token_predates_current_source'],
    'stale master token ticket still inherited' => [static fn (): mixed => $plan(null, $reads($currentHeaderDigest, null, $oldMasterToken))['next_reads'][0]['master_journal_file_token_reason'], 'reader_ticket_master_journal_file_token_predates_current_source'],
    'all fresh cache no header invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['database_header_digest_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1])], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'changed recovered header invalidates previously fresh pages' => [static fn (): mixed => $plan($changedCache(), $changedReads(), $changedRecovered)['database_header_digest_invalidated_cache_page_numbers'], [1, 2, 3]],
    'changed recovered header keeps inherited format invalidation' => [static fn (): mixed => in_array(5, $plan($changedCache(), $changedReads(), $changedRecovered)['invalidated_cache_page_numbers'], true), true],
    'changed recovered header digest surfaced' => [static fn (): mixed => $plan($changedCache(), $changedReads(), $changedRecovered)['current_database_header_digest'], $changedHeaderDigest],
    'current read ticket with stale cache only reopens inherited readers' => [static fn (): mixed => $plan(null, $reads())['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next213 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing cache header digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['database_header_digest' => null])], [['reader_id' => 'read-1', 'page_number' => 1] + $reads()[0]]),
    'empty cache header digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['database_header_digest' => ''])], [['reader_id' => 'read-1', 'page_number' => 1] + $reads()[0]]),
    'missing read header digest rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1] + array_diff_key($reads()[0], ['database_header_digest' => true])]),
    'empty read header digest rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1] + array_replace($reads()[0], ['database_header_digest' => ''])]),
    'missing read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'bad recovered page one rejected' => static fn () => $plan(null, null, [1 => 'short']),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next213 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
