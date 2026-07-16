<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next221.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next221-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-ticket-next221';
$publication = 221;
$masterDigest = hash('sha256', 'next221-master-source');
$recoverySequence = 221;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$currentMasterBytesDigest = hash('sha256', $masterBytes);
$currentMasterToken = 'dev=8:ino=2210:size=96:mtime=22100:generation=master-current';
$currentDatabaseToken = 'dev=8:ino=2219:size=3072:mtime=22199:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=221;change-counter=51;version-valid-for=51;page-count=5');
$oldHeaderDigest = hash('sha256', 'schema-cookie=220;change-counter=50;version-valid-for=50;page-count=6');
$currentTicket = ['change_counter' => 51, 'schema_cookie' => 221, 'version_valid_for' => 51, 'page_count' => 5];
$oldSchemaTicket = ['change_counter' => 51, 'schema_cookie' => 220, 'version_valid_for' => 51, 'page_count' => 5];
$oldCounterTicket = ['change_counter' => 50, 'schema_cookie' => 221, 'version_valid_for' => 50, 'page_count' => 5];
$oldPageCountTicket = ['change_counter' => 51, 'schema_cookie' => 221, 'version_valid_for' => 51, 'page_count' => 6];
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
    $page = substr_replace($page, pack('N', 221), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503231), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next221 stale schema before ticket recovery'),
    2 => $page('next221 stale wp_options root before ticket recovery'),
    3 => $page('next221 stale active_plugins before ticket recovery'),
    4 => $page('next221 stale autoload options before ticket recovery'),
    5 => $page('next221 stale usermeta before ticket recovery'),
    6 => $page('next221 stale comments before ticket recovery'),
];
$recovered = [
    1 => $formatPage('next221 current schema after ticket recovery'),
    2 => $page('next221 current wp_options root after ticket recovery'),
    3 => $page('next221 current active_plugins after ticket recovery'),
    4 => $page('next221 current autoload options after ticket recovery'),
    5 => $page('next221 current usermeta after ticket recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 221, 0x57503231]));
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
    $mainJournal => 'dev=8:ino=2211:size=4096:mtime=22101:generation=main-current',
    $usersJournal => 'dev=8:ino=2212:size=1024:mtime=22102:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-221'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-221'),
];
$oldMemberHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'users-old-rollback-header-221'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokenDigest = $mapDigest($currentTokens);
$currentMemberHeaderDigest = $mapDigest($currentHeaders);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 221,
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
    'pager_header_ticket' => $currentTicket,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-ticket', $recovered[1]),
    2 => $cacheEntry('root-refreshed-ticket', $before[2]),
    3 => $cacheEntry('active-stale-schema-cookie', $recovered[3], ['pager_header_ticket' => $oldSchemaTicket]),
    4 => $cacheEntry('autoload-stale-change-counter', $recovered[4], ['pager_header_ticket' => $oldCounterTicket]),
    5 => $cacheEntry('usermeta-stale-header-digest', $recovered[5], ['database_header_digest' => $oldHeaderDigest]),
    6 => $cacheEntry('comments-stale-member-header', $before[6], ['member_journal_header_digests' => $oldMemberHeaders]),
];
$reads = static fn (array $ticket = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 221,
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
        'database_file_token' => $currentDatabaseToken,
        'database_header_digest' => $currentHeaderDigest,
        'pager_header_ticket' => $ticket ?? $currentTicket,
    ],
    range(1, 6),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $ticket = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planPagerHeaderTicketFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    221,
    $publication,
    $masterDigest,
    $recoverySequence,
    $currentTokens,
    $currentHeaders,
    $currentMasterToken,
    $currentDatabaseToken,
    $currentHeaderDigest,
    $ticket ?? $currentTicket,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next221'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_structured_pager_header_ticket_before_current_source_reuse'],
    'current ticket' => [static fn (): mixed => $plan()['current_pager_header_ticket'], $currentTicket],
    'inherits header digest' => [static fn (): mixed => $plan()['current_database_header_digest'], $currentHeaderDigest],
    'ticket invalidated pages' => [static fn (): mixed => $plan()['pager_header_ticket_invalidated_cache_page_numbers'], [3, 4]],
    'all invalidated pages include inherited fences' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6']],
    'read hit retained' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale schema cookie' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'ticket invalidation operation count' => [static fn (): mixed => $opCount('invalidate_reader_cache_pager_header_ticket_after_current_source_next221'), 2],
    'dependency next221' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next221', $plan()['dependencies'], true), true],
    'dependency structured ticket' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-structured-header-ticket-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next217' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next217 database header digest'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-ticket')['pager_header_ticket_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-ticket')['pager_header_ticket_reason'], 'reader_cache_pager_header_ticket_matches_current_source'],
    'row retained cache ticket' => [static fn (): mixed => $row('schema-retained-ticket')['cache_pager_header_ticket'], $currentTicket],
    'row retained current ticket' => [static fn (): mixed => $row('schema-retained-ticket')['current_pager_header_ticket'], $currentTicket],
    'row retained ticket matches' => [static fn (): mixed => $row('schema-retained-ticket')['pager_header_ticket_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-ticket')['pager_header_ticket_admitted'], true],
    'row stale schema admitted false' => [static fn (): mixed => $row('active-stale-schema-cookie')['pager_header_ticket_admitted'], false],
    'row stale schema reason' => [static fn (): mixed => $row('active-stale-schema-cookie')['pager_header_ticket_reason'], 'reader_cache_pager_header_ticket_changed_after_master_journal_recovery'],
    'row stale schema ticket' => [static fn (): mixed => $row('active-stale-schema-cookie')['cache_pager_header_ticket'], $oldSchemaTicket],
    'row stale schema mismatch' => [static fn (): mixed => $row('active-stale-schema-cookie')['pager_header_ticket_matches'], false],
    'row stale counter admitted false' => [static fn (): mixed => $row('autoload-stale-change-counter')['pager_header_ticket_admitted'], false],
    'row stale counter ticket' => [static fn (): mixed => $row('autoload-stale-change-counter')['cache_pager_header_ticket'], $oldCounterTicket],
    'row stale counter reason' => [static fn (): mixed => $row('autoload-stale-change-counter')['pager_header_ticket_reason'], 'reader_cache_pager_header_ticket_changed_after_master_journal_recovery'],
    'row stale digest inherits reason' => [static fn (): mixed => $row('usermeta-stale-header-digest')['pager_header_ticket_reason'], 'reader_cache_database_header_digest_changed_after_master_journal_recovery'],
    'row stale member inherits reason' => [static fn (): mixed => $row('comments-stale-member-header')['pager_header_ticket_reason'], 'reader_cache_attached_member_journal_header_changed'],
    'read retained ticket current' => [static fn (): mixed => $read('read-1')['pager_header_ticket_current'], true],
    'read retained ticket' => [static fn (): mixed => $read('read-1')['pager_header_ticket'], $currentTicket],
    'read stale schema reason' => [static fn (): mixed => $read('read-3')['pager_header_ticket_reason'], 'reader_cache_reopened_after_pager_header_ticket_change'],
    'read stale schema source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-pager-header-ticket-current-source-next221'],
    'stale read ticket forces miss' => [static fn (): mixed => $plan(null, $reads($oldPageCountTicket))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldPageCountTicket))['next_reads'][0]['pager_header_ticket_reason'], 'reader_ticket_pager_header_ticket_predates_current_source'],
    'stale read page count ticket current false' => [static fn (): mixed => $plan(null, $reads($oldPageCountTicket))['next_reads'][0]['pager_header_ticket_current'], false],
    'all current read tickets keep first hit' => [static fn (): mixed => $plan(null, $reads($currentTicket))['read_cache_hits']['read-1'], true],
    'changed current ticket invalidates otherwise admitted cache' => [static fn (): mixed => $plan(null, null, ['change_counter' => 52, 'schema_cookie' => 221, 'version_valid_for' => 52, 'page_count' => 5])['pager_header_ticket_invalidated_cache_page_numbers'], [1, 2, 3, 4]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next221 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing current ticket field rejected' => static fn () => $plan(null, null, ['change_counter' => 51, 'schema_cookie' => 221, 'version_valid_for' => 51]),
    'negative current ticket field rejected' => static fn () => $plan(null, null, ['change_counter' => -1, 'schema_cookie' => 221, 'version_valid_for' => 51, 'page_count' => 5]),
    'missing cache ticket rejected' => static fn () => $plan([1 => array_diff_key($cache()[1], ['pager_header_ticket' => true])]),
    'invalid cache ticket rejected' => static fn () => $plan([1 => array_replace($cache()[1], ['pager_header_ticket' => ['change_counter' => 51]])]),
    'zero cache page rejected' => static fn () => $plan([0 => $cache()[1]]),
    'missing read ticket rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['pager_header_ticket' => true])]),
    'empty read id rejected' => static fn () => $plan(null, [array_replace($reads()[0], ['reader_id' => ''])]),
    'inherits next217 missing header digest rejection' => static fn () => $plan([1 => array_diff_key($cache()[1], ['database_header_digest' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next221 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
