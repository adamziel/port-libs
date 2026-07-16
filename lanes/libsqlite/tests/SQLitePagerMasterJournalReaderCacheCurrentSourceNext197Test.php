<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next197.sqlite';
$master = '/srv/wp-content/database/wp-next197.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next197-users.sqlite-journal';
$sourceId = 'pager-reader-cache-master-member-next197';
$nonce = 'current-source-nonce-next197';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$masterBytes = $usersJournal . "\n" . $journal . "\0" . $usersJournal . "\0";
$members = [$usersJournal, $journal];
$memberDigest = hash('sha256', implode("\n", $members));
$oldMemberDigest = hash('sha256', 'old-master-member-set-next197');
$before = [
    1 => $page('next197 schema before active master member fence'),
    2 => $page('next197 stale options root before active master member fence'),
    3 => $page('next197 active plugins stale member digest before fence'),
    4 => $page('next197 rewrite rules stale nonce before fence'),
    5 => $page('next197 pinned autoload page before member fence'),
    6 => $page('next197 dirty comments page before member fence'),
    7 => $page('next197 stale source id before member fence'),
    8 => $page('next197 stale epoch before member fence'),
];
$current = [
    2 => $page('next197 current options root after active master member fence'),
    5 => $page('next197 current autoload page after active master member fence'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $reader, string $image, array $extra = []): array => array_merge([
    'reader_id' => $reader,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 197,
    'master_member_digest' => $memberDigest,
    'current_source_nonce' => $nonce,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $before[1]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('active-stale-member', $before[3], ['master_member_digest' => $oldMemberDigest]),
    4 => $cacheEntry('rewrite-stale-nonce', $before[4], ['current_source_nonce' => 'old-source-nonce-next197']),
    5 => $cacheEntry('autoload-pinned-stale', $before[5], ['pinned' => true]),
    6 => $cacheEntry('comments-dirty', $before[6], ['dirty' => true]),
    7 => $cacheEntry('source-stale', $before[7], ['source_id' => 'old-source']),
    8 => $cacheEntry('epoch-stale', $before[8], ['epoch' => 196]),
];
$reads = static fn (array $extra = []): array => array_map(
    static fn (int $pageNumber): array => array_merge([
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 197,
        'master_member_digest' => $memberDigest,
        'current_source_nonce' => $nonce,
    ], $extra),
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readRequests = null,
    ?array $pages = null,
    ?string $masterJournalBytes = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 197,
    ?string $sourceNonce = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::masterJournalMemberSourceFence(
    $path ?? $database,
    $masterPath ?? $master,
    $masterJournalBytes ?? $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $readRequests ?? $reads(),
    $pages ?? $current,
    $source ?? $sourceId,
    $epoch,
    $sourceNonce ?? $nonce,
);
$row = static function (int $pageNumber) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['page_number'] === $pageNumber) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $pageNumber);
};
$opExists = static function (string $op) use ($plan): bool {
    foreach ($plan()['operations'] as $operation) {
        if (($operation['op'] ?? '') === $op) {
            return true;
        }
    }

    return false;
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next197'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_member_digest_and_source_nonce_fence_reader_cache_admission'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members sorted deduped' => [static fn (): mixed => $plan()['current_members'], $members],
    'member digest' => [static fn (): mixed => $plan()['current_member_digest'], $memberDigest],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 197],
    'current source nonce' => [static fn (): mixed => $plan()['current_source']['nonce'], $nonce],
    'next source prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-journal-member-source:'), true],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 198],
    'next source digest' => [static fn (): mixed => $plan()['next_source']['member_digest'], $memberDigest],
    'next source nonce' => [static fn (): mixed => $plan()['next_source']['nonce'], $nonce],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'requires reader reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'member reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'reader_cache_master_member_digest_mismatch'],
    'nonce reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_current_source_nonce_mismatch'],
    'pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'pinned_reader_cache_image_predates_master_member_source'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'dirty_reader_cache_cannot_cross_master_journal_member_source'],
    'source reason' => [static fn (): mixed => $plan()['invalidated_reasons'][7], 'reader_cache_source_id_predates_master_member_source'],
    'epoch reason' => [static fn (): mixed => $plan()['invalidated_reasons'][8], 'reader_cache_epoch_predates_master_member_source'],
    'row retained reason' => [static fn (): mixed => $row(1)['reason'], 'reader_cache_matches_master_journal_member_source'],
    'row refreshed reason' => [static fn (): mixed => $row(2)['reason'], 'reader_cache_refreshed_after_master_journal_member_source'],
    'row member before' => [static fn (): mixed => $row(3)['member_digest_before'], $oldMemberDigest],
    'row member current' => [static fn (): mixed => $row(3)['member_digest_current'], $memberDigest],
    'row member mismatch' => [static fn (): mixed => $row(3)['member_digest_matches'], false],
    'row nonce before' => [static fn (): mixed => $row(4)['source_nonce_before'], 'old-source-nonce-next197'],
    'row nonce current' => [static fn (): mixed => $row(4)['source_nonce_current'], $nonce],
    'row nonce mismatch' => [static fn (): mixed => $row(4)['source_nonce_matches'], false],
    'row dirty flag' => [static fn (): mixed => $row(6)['dirty'], true],
    'row pinned flag' => [static fn (): mixed => $row(5)['pinned'], true],
    'row source before' => [static fn (): mixed => $row(7)['source_id_before'], 'old-source'],
    'row epoch before' => [static fn (): mixed => $row(8)['epoch_before'], 196],
    'row cache prefix' => [static fn (): mixed => $row(2)['cache_prefix'], 'next197 stale options root before active master member fence'],
    'row current prefix' => [static fn (): mixed => $row(2)['current_prefix'], 'next197 current options root after active master member fence'],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'next read retained hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'next read refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'next read invalidated miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'next read stale request current false' => [static fn (): mixed => $plan(null, $reads(['master_member_digest' => $oldMemberDigest]))['next_reads'][0]['request_current'], false],
    'next read stale request miss' => [static fn (): mixed => $plan(null, $reads(['current_source_nonce' => 'old-source-nonce-next197']))['read_cache_hits']['read-1'], false],
    'next read miss reason' => [static fn (): mixed => $plan()['next_reads'][3]['reason'], 'next_read_reopens_after_master_member_source_change'],
    'next read source id' => [static fn (): mixed => $plan()['next_reads'][0]['source_id'], $plan()['next_source']['id']],
    'next read epoch' => [static fn (): mixed => $plan()['next_reads'][0]['epoch'], 198],
    'next read refreshed prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next197 current options root after active master member fence'],
    'operation verify proof' => [static fn (): mixed => $plan()['operations'][0]['op'], 'verify_master_journal_members_current_source_next197'],
    'operation member digest' => [static fn (): mixed => $plan()['operations'][0]['member_digest'], $memberDigest],
    'operation source nonce' => [static fn (): mixed => $plan()['operations'][0]['source_nonce'], $nonce],
    'operation retain exists' => [static fn (): mixed => $opExists('retain_reader_cache_after_master_member_source_next197'), true],
    'operation refresh exists' => [static fn (): mixed => $opExists('refresh_reader_cache_after_master_member_source_next197'), true],
    'operation invalidate exists' => [static fn (): mixed => $opExists('invalidate_reader_cache_after_master_member_source_next197'), true],
    'final source retained' => [static fn (): mixed => $plan()['final_sources'][1], 'database-before-master-member-reader-cache-next197'],
    'final source current' => [static fn (): mixed => $plan()['final_sources'][2], 'master-journal-member-current-source-next197'],
    'final prefix current' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next197 current autoload page after active master member fence'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next197', $plan()['dependencies'], true), true],
    'dependency member fence marker' => [static fn (): mixed => in_array('sqlite-master-journal-member-digest-current-source-fence', $plan()['dependencies'], true), true],
    'non overlap next191' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next191 delete/directory-sync'), true],
    'crlf separator equivalent' => [static fn (): mixed => $plan(null, [ ['reader_id' => 'read-1', 'page_number' => 1] ], [], $journal . "\r\n" . $usersJournal . "\r\n")['current_member_digest'], $memberDigest],
    'duplicate member ignored' => [static fn (): mixed => $plan(null, [ ['reader_id' => 'read-1', 'page_number' => 1] ], [], $journal . "\0" . $journal . "\0" . $usersJournal . "\0")['current_members'], $members],
    'all current cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1])], [['reader_id' => 'read-1', 'page_number' => 1]], [])['requires_reader_reopen'], false],
    'unpinned stale refreshes' => [static fn (): mixed => $plan([5 => $cacheEntry('autoload-stale', $before[5])], [['reader_id' => 'read-5', 'page_number' => 5]], $current)['refreshed_page_numbers'], [5]],
    'changed nonce changes next source' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['current_source_nonce' => 'nonce-two'])], [['reader_id' => 'read-1', 'page_number' => 1, 'current_source_nonce' => 'nonce-two']], [], null, null, null, null, null, null, 197, 'nonce-two')['next_source']['id'] !== $plan()['next_source']['id'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next197 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source nonce rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 197, ''),
    'blank master rejected' => static fn () => $plan(null, null, null, str_repeat("\0", 32)),
    'missing database journal rejected' => static fn () => $plan(null, null, null, $usersJournal . "\0"),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 0),
    'current page outside rejected' => static fn () => $plan(null, null, [9 => $page('outside')]),
    'bad current page rejected' => static fn () => $plan(null, null, [0 => $page('bad')]),
    'short current page rejected' => static fn () => $plan(null, null, [1 => 'short']),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $before[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache reader rejected' => static fn () => $plan([1 => $cacheEntry('', $before[1])]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['source_id' => ''])]),
    'empty cache member digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_member_digest' => ''])]),
    'empty cache source nonce rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['current_source_nonce' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['epoch' => 0])]),
    'cache page outside rejected' => static fn () => $plan([9 => $cacheEntry('bad', $page('outside'))]),
    'bad read request rejected' => static fn () => $plan(null, [['reader_id' => '', 'page_number' => 1]]),
    'bad read page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 0]]),
    'read page outside rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 9]]),
    'empty read source rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '']]),
    'bad read epoch rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'epoch' => 0]]),
    'empty read member digest rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'master_member_digest' => '']]),
    'empty read source nonce rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'current_source_nonce' => '']]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next197 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
