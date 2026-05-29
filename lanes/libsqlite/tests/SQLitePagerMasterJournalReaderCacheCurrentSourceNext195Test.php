<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next195.sqlite';
$master = '/srv/wp-content/database/wp-next195.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next195-users.sqlite-journal';
$sourceId = 'pager-reader-cache-member-ticket-next195';
$syncGeneration = 195;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$members = [$usersJournal, $journal];
$masterBytes = $usersJournal . "\0" . $journal . "\0" . $usersJournal . "\0";
$deleteToken = 'master-delete-synced:' . substr(hash('sha256', $master . '|' . $syncGeneration . '|' . implode("\n", $members)), 0, 40);
$journalDigests = [
    $usersJournal => hash('sha256', 'users-current-rollback-journal-next195'),
    $journal => hash('sha256', 'main-current-rollback-journal-next195'),
];
$tickets = [];
foreach ($members as $member) {
    $tickets[$member] = 'master-member-ticket:' . substr(hash('sha256', $member . '|' . $journalDigests[$member] . '|' . $deleteToken . '|' . $syncGeneration), 0, 40);
}
ksort($tickets, SORT_STRING);
$ticketValues = array_values($tickets);
$ticketFor = static fn (int $pageNumber): string => $ticketValues[($pageNumber - 1) % count($ticketValues)];
$before = [
    1 => $page('next195 schema before master member ticket fence'),
    2 => $page('next195 current alloptions same bytes after member fence'),
    3 => $page('next195 active_plugins before member ticket fence'),
    4 => $page('next195 rewrite_rules before member ticket fence'),
    5 => $page('next195 usermeta before member ticket fence'),
    6 => $page('next195 comments before member ticket fence'),
];
$current = [
    3 => $page('next195 current active_plugins after member ticket fence'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (int $pageNumber, string $reader, string $image, array $extra = []): array => array_merge([
    'reader_id' => $reader,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 195,
    'master_delete_token' => $deleteToken,
    'directory_sync_generation' => $syncGeneration,
    'master_member_ticket' => $ticketFor($pageNumber),
], $extra);
$oldTicket = 'master-member-ticket:' . str_repeat('0', 40);
$cache = static fn (): array => [
    1 => $cacheEntry(1, 'schema-retained', $before[1]),
    2 => $cacheEntry(2, 'alloptions-same-bytes-stale-ticket', $before[2], ['master_member_ticket' => $oldTicket]),
    3 => $cacheEntry(3, 'active-refreshed', $before[3]),
    4 => $cacheEntry(4, 'rewrite-dirty', $before[4], ['dirty' => true]),
    5 => $cacheEntry(5, 'usermeta-stale-delete', $before[5], ['master_delete_token' => 'old-delete-token']),
    6 => $cacheEntry(6, 'comments-retained', $before[6]),
];
$reads = static fn (): array => [
    ['reader_id' => 'schema-read', 'page_number' => 1, 'master_member_ticket' => $ticketFor(1)],
    ['reader_id' => 'alloptions-read', 'page_number' => 2, 'master_member_ticket' => $oldTicket],
    ['reader_id' => 'active-read', 'page_number' => 3, 'master_member_ticket' => $ticketFor(3)],
    ['reader_id' => 'rewrite-read', 'page_number' => 4, 'master_member_ticket' => $ticketFor(4)],
    ['reader_id' => 'usermeta-read', 'page_number' => 5, 'master_member_ticket' => $ticketFor(5)],
    ['reader_id' => 'comments-read', 'page_number' => 6, 'master_member_ticket' => $ticketFor(6)],
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?array $pages = null,
    ?array $digests = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext195(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $readerCache ?? $cache(),
    $nextReads ?? $reads(),
    $pages ?? $current,
    $sourceId,
    195,
    $syncGeneration,
    $digests ?? $journalDigests,
);
$row = static function (int $pageNumber) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['page_number'] === $pageNumber) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $pageNumber);
};
$read = static function (string $readerId) use ($plan): array {
    foreach ($plan()['next_reads'] as $read) {
        if (($read['reader_id'] ?? '') === $readerId) {
            return $read;
        }
    }
    throw new RuntimeException('missing read ' . $readerId);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next195'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_member_ticket_fences_current_source_reuse'],
    'inherits next191 delete token' => [static fn (): mixed => $plan()['current_master_delete_token'], $deleteToken],
    'member tickets sorted' => [static fn (): mixed => $plan()['current_master_member_tickets'], $tickets],
    'reader row count' => [static fn (): mixed => count($plan()['reader_rows']), 6],
    'retained pages exclude stale ticket' => [static fn (): mixed => $plan()['retained_page_numbers'], [1, 6]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [3]],
    'invalidated pages include ticket and inherited reasons' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [2, 4, 5]],
    'ticket invalidated page' => [static fn (): mixed => $plan()['member_ticket_invalidated_page_numbers'], [2]],
    'requires reader reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row retained admitted' => [static fn (): mixed => $row(1)['member_ticket_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row(1)['member_ticket_reason'], 'reader_cache_master_member_ticket_matches_current_source'],
    'row stale ticket denied' => [static fn (): mixed => $row(2)['member_ticket_admitted'], false],
    'row stale ticket reason' => [static fn (): mixed => $row(2)['member_ticket_reason'], 'reader_cache_master_member_ticket_predates_current_source'],
    'row dirty preserves inherited reason' => [static fn (): mixed => $row(4)['member_ticket_reason'], 'dirty_reader_cache_cannot_cross_master_journal_delete'],
    'row delete preserves inherited reason' => [static fn (): mixed => $row(5)['member_ticket_reason'], 'reader_cache_master_delete_token_mismatch'],
    'row ticket before' => [static fn (): mixed => $row(1)['cache_master_member_ticket'], $ticketFor(1)],
    'row ticket current' => [static fn (): mixed => $row(1)['current_master_member_ticket'], $ticketFor(1)],
    'row ticket matches' => [static fn (): mixed => $row(1)['master_member_ticket_matches'], true],
    'row ticket mismatch' => [static fn (): mixed => $row(2)['master_member_ticket_matches'], false],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read retained hit' => [static fn (): mixed => $read('schema-read')['cache_hit'], true],
    'read stale ticket miss' => [static fn (): mixed => $read('alloptions-read')['cache_hit'], false],
    'read stale ticket source' => [static fn (): mixed => $read('alloptions-read')['source'], 'master-journal-reader-cache-member-ticket-fence-current-source-next195'],
    'read stale ticket reason' => [static fn (): mixed => $read('alloptions-read')['source_reason'], 'reader_cache_reopened_after_master_member_ticket_change'],
    'read refreshed hit' => [static fn (): mixed => $read('active-read')['cache_hit'], true],
    'read dirty inherited miss' => [static fn (): mixed => $read('rewrite-read')['cache_hit'], false],
    'read ticket current true' => [static fn (): mixed => $read('schema-read')['master_member_ticket_current'], true],
    'read ticket current false' => [static fn (): mixed => $read('alloptions-read')['master_member_ticket_current'], false],
    'read ticket value' => [static fn (): mixed => $read('comments-read')['master_member_ticket'], $ticketFor(6)],
    'read cache hits map' => [static fn (): mixed => $plan()['read_cache_hits']['schema-read'], true],
    'read cache misses map' => [static fn (): mixed => $plan()['read_cache_hits']['alloptions-read'], false],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['alloptions-read']],
    'operation appended' => [static fn (): mixed => in_array('invalidate_reader_cache_master_member_ticket_after_delete_next195', array_column($plan()['operations'], 'op'), true), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next195 marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next195', $plan()['dependencies'], true), true],
    'dependency ticket marker' => [static fn (): mixed => in_array('sqlite-pager-master-member-ticket-fence', $plan()['dependencies'], true), true],
    'non overlap next191' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next191 delete-sync'), true],
    'same bytes stale ticket invalidates' => [static fn (): mixed => $row(2)['cache_prefix'], 'next195 current alloptions same bytes after member fence'],
    'same bytes current prefix' => [static fn (): mixed => $row(2)['current_prefix'], 'next195 current alloptions same bytes after member fence'],
    'all current tickets no reopen' => [static fn (): mixed => $plan([
        1 => $cacheEntry(1, 'schema-retained', $before[1]),
        6 => $cacheEntry(6, 'comments-retained', $before[6]),
    ], [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'master_member_ticket' => $ticketFor(1)],
        ['reader_id' => 'comments-read', 'page_number' => 6, 'master_member_ticket' => $ticketFor(6)],
    ], [])['requires_reader_reopen'], false],
    'changed journal digest changes ticket' => [static fn (): mixed => $plan(null, null, null, [
        $usersJournal => hash('sha256', 'users-current-rollback-journal-next195-v2'),
        $journal => $journalDigests[$journal],
    ])['current_master_member_tickets'][$usersJournal] !== $tickets[$usersJournal], true],
    'reader ticket mismatch without cache invalidation misses' => [static fn (): mixed => $plan([
        1 => $cacheEntry(1, 'schema-retained', $before[1]),
    ], [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'master_member_ticket' => $oldTicket],
    ], [])['next_reads'][0]['source_reason'], 'reader_ticket_master_member_predates_current_source'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next195 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad read page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 0, 'master_member_ticket' => $ticketFor(1)]]),
    'missing cache ticket rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry(1, 'schema', $before[1]), ['master_member_ticket' => true])]),
    'empty cache ticket rejected' => static fn () => $plan([1 => $cacheEntry(1, 'schema', $before[1], ['master_member_ticket' => ''])]),
    'missing read ticket rejected' => static fn () => $plan(null, [['reader_id' => 'schema-read', 'page_number' => 1]]),
    'empty read id rejected' => static fn () => $plan(null, [['reader_id' => '', 'page_number' => 1, 'master_member_ticket' => $ticketFor(1)]]),
    'missing journal digest rejected' => static fn () => $plan(null, null, null, [$journal => $journalDigests[$journal]]),
    'empty journal digest rejected' => static fn () => $plan(null, null, null, [$usersJournal => '', $journal => $journalDigests[$journal]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next195 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
