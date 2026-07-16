<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next188.sqlite';
$master = '/srv/wp-content/database/wp-next188.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next188-users.sqlite-journal';
$sourceId = 'pager-reader-cache-nul-sector-next188';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$masterBytes = $usersJournal . "\0" . $journal . "\0" . $usersJournal . "\0" . str_repeat("\0", 64);
$members = [$usersJournal, $journal];
$memberDigest = hash('sha256', implode("\n", $members));
$memberToken = 'nul-sector-members:' . substr(hash('sha256', implode("\n", $members)), 0, 40);
$before = [
    1 => $page('next188 schema page before nul master journal read'),
    2 => $page('next188 stale options root before nul master journal read'),
    3 => $page('next188 comments page before nul master journal read'),
    4 => $page('next188 active plugins before nul master journal read'),
    5 => $page('next188 autoload index before nul master journal read'),
];
$databaseBytes = implode('', $before);
$refreshed = [
    2 => $page('next188 current wp_options root after nul master journal read'),
    5 => $page('next188 current autoload index after nul master journal read'),
];
$cacheEntry = static fn (string $reader, string $image, array $extra = []): array => array_merge([
    'reader_id' => $reader,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 188,
    'member_token' => $memberToken,
    'member_digest' => $memberDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $before[1]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('comments-dirty', $before[3], ['dirty' => true]),
    4 => $cacheEntry('active-stale-token', $before[4], ['member_token' => 'old-token']),
    5 => $cacheEntry('autoload-pinned-stale', $before[5], ['pinned' => true]),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $reads = null,
    ?array $refresh = null,
    ?string $masterJournalBytes = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 188,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planNulPaddedMemberBytesFence(
    $path ?? $database,
    $masterPath ?? $master,
    $masterJournalBytes ?? $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $reads ?? [1, 2, 3, 4, 5],
    $refresh ?? $refreshed,
    $source ?? $sourceId,
    $epoch,
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
        if ($operation['op'] === $op) {
            return true;
        }
    }

    return false;
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next188'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'sector-padded NUL master-journal member bytes fence reader-cache current-source admission'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members sorted deduped' => [static fn (): mixed => $plan()['current_members'], $members],
    'member token' => [static fn (): mixed => $plan()['current_member_token'], $memberToken],
    'member digest' => [static fn (): mixed => $plan()['current_member_digest'], $memberDigest],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 188],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-journal-nul-member-source:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 189],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5]],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'dirty_reader_cache_cannot_cross_nul_master_journal_read'],
    'token reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_master_member_token_mismatch_after_nul_parse'],
    'pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'pinned_reader_cache_image_predates_nul_master_read'],
    'row retained reason' => [static fn (): mixed => $row(1)['reason'], 'reader_cache_matches_nul_master_journal_source'],
    'row refreshed reason' => [static fn (): mixed => $row(2)['reason'], 'reader_cache_refreshed_from_nul_master_journal_source'],
    'row dirty flag' => [static fn (): mixed => $row(3)['dirty'], true],
    'row pinned flag' => [static fn (): mixed => $row(5)['pinned'], true],
    'row token before' => [static fn (): mixed => $row(1)['member_token_before'], $memberToken],
    'row token current' => [static fn (): mixed => $row(1)['member_token_current'], $memberToken],
    'row token matches' => [static fn (): mixed => $row(1)['member_token_matches'], true],
    'row token mismatch false' => [static fn (): mixed => $row(4)['member_token_matches'], false],
    'row digest matches' => [static fn (): mixed => $row(1)['member_digest_matches'], true],
    'row cache prefix' => [static fn (): mixed => $row(2)['cache_prefix'], 'next188 stale options root before nul master journal read'],
    'row current prefix' => [static fn (): mixed => $row(2)['current_prefix'], 'next188 current wp_options root after nul master journal read'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 5],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read three reopened' => [static fn (): mixed => $plan()['next_reads'][2]['reason'], 'next_read_reopens_after_nul_master_journal_parse'],
    'read four source id next' => [static fn (): mixed => $plan()['next_reads'][3]['source_id'], $plan()['next_source']['id']],
    'read five prefix' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next188 current autoload index after nul master journal read'],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_sector_padded_nul_master_journal_members_next188'],
    'operation invalidate' => [static fn (): mixed => $opExists('invalidate_reader_cache_after_nul_master_journal_parse_next188'), true],
    'operation refresh' => [static fn (): mixed => $opExists('refresh_reader_cache_after_nul_master_journal_parse_next188'), true],
    'operation retain' => [static fn (): mixed => $opExists('retain_reader_cache_after_nul_master_journal_parse_next188'), true],
    'operation cache hit read' => [static fn (): mixed => $opExists('next_read_uses_nul_master_reader_cache_next188'), true],
    'operation reopen read' => [static fn (): mixed => $opExists('next_read_reopens_after_nul_master_parse_next188'), true],
    'final source one' => [static fn (): mixed => $plan()['final_sources'][1], 'database-before-nul-master-reader-cache-next188'],
    'final source refreshed' => [static fn (): mixed => $plan()['final_sources'][2], 'nul-sector-master-journal-current-source-next188'],
    'final prefix refreshed' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next188 current autoload index after nul master journal read'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next188', $plan()['dependencies'], true), true],
    'dependency parser' => [static fn (): mixed => in_array('sqlite-master-journal-nul-sector-member-parser', $plan()['dependencies'], true), true],
    'dependency next185' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next185', $plan()['dependencies'], true), true],
    'non overlap next185' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next185 finite rollback truncation'), true],
    'newline separator equivalent' => [static fn (): mixed => $plan(null, null, null, $journal . "\n" . $usersJournal . "\n")['current_member_token'], $memberToken],
    'duplicate member ignored' => [static fn (): mixed => $plan(null, null, null, $journal . "\0" . $journal . "\0" . $usersJournal . "\0")['current_members'], $members],
    'digest mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('bad-digest', $before[1], ['member_digest' => hash('sha256', 'old')])], [1], [])['invalidated_reasons'][1], 'reader_cache_master_member_digest_mismatch_after_nul_parse'],
    'source mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('bad-source', $before[1], ['source_id' => 'old-source'])], [1], [])['invalidated_reasons'][1], 'reader_cache_source_id_mismatch_after_nul_master_read'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('bad-epoch', $before[1], ['epoch' => 187])], [1], [])['invalidated_reasons'][1], 'reader_cache_epoch_mismatch_after_nul_master_read'],
    'unpinned stale refreshes' => [static fn (): mixed => $plan([5 => $cacheEntry('autoload-stale', $before[5])], [5], $refreshed)['refreshed_page_numbers'], [5]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next188 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'blank master rejected' => static fn () => $plan(null, null, null, str_repeat("\0", 32)),
    'missing database journal rejected' => static fn () => $plan(null, null, null, $usersJournal . "\0"),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 0),
    'refreshed page outside image rejected' => static fn () => $plan(null, null, [9 => $page('outside')]),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $before[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['source_id' => ''])]),
    'empty cache reader rejected' => static fn () => $plan([1 => $cacheEntry('', $before[1])]),
    'empty cache token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['member_token' => ''])]),
    'empty cache digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['member_digest' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['epoch' => 0])]),
    'bad read page rejected' => static fn () => $plan(null, [0]),
    'read page outside image rejected' => static fn () => $plan(null, [8]),
    'bad refresh page rejected' => static fn () => $plan(null, null, [0 => $page('bad')]),
    'short refresh image rejected' => static fn () => $plan(null, null, [1 => 'short']),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next188 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
