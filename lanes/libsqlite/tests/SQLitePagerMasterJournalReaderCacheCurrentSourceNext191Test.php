<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next191.sqlite';
$master = '/srv/wp-content/database/wp-next191.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next191-users.sqlite-journal';
$sourceId = 'pager-reader-cache-master-delete-next191';
$syncGeneration = 77;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$masterBytes = $usersJournal . "\0" . $journal . "\0" . $usersJournal . "\0" . str_repeat("\0", 32);
$members = [$usersJournal, $journal];
$memberDigest = hash('sha256', implode("\n", $members));
$deleteToken = 'master-delete-synced:' . substr(hash('sha256', $master . '|' . $syncGeneration . '|' . implode("\n", $members)), 0, 40);
$before = [
    1 => $page('next191 schema before master journal delete sync'),
    2 => $page('next191 stale options root before master journal delete sync'),
    3 => $page('next191 active plugins before master journal delete sync'),
    4 => $page('next191 plugin settings before master journal delete sync'),
    5 => $page('next191 autoload index before master journal delete sync'),
    6 => $page('next191 comments cache before master journal delete sync'),
];
$current = [
    2 => $page('next191 current options root after master journal delete sync'),
    5 => $page('next191 current autoload index after master journal delete sync'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $reader, string $image, array $extra = []): array => array_merge([
    'reader_id' => $reader,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 191,
    'master_delete_token' => $deleteToken,
    'directory_sync_generation' => $syncGeneration,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $before[1]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('active-stale-delete', $before[3], ['master_delete_token' => 'old-delete-token']),
    4 => $cacheEntry('settings-stale-dir-sync', $before[4], ['directory_sync_generation' => 76]),
    5 => $cacheEntry('autoload-pinned-stale', $before[5], ['pinned' => true]),
    6 => $cacheEntry('comments-dirty', $before[6], ['dirty' => true]),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $reads = null,
    ?array $pages = null,
    ?string $masterJournalBytes = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 191,
    int $directoryGeneration = 77,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext191(
    $path ?? $database,
    $masterPath ?? $master,
    $masterJournalBytes ?? $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $reads ?? [1, 2, 3, 4, 5, 6],
    $pages ?? $current,
    $source ?? $sourceId,
    $epoch,
    $directoryGeneration,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next191'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_delete_and_directory_sync_fence_reader_cache_admission'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members sorted deduped' => [static fn (): mixed => $plan()['current_members'], $members],
    'member digest' => [static fn (): mixed => $plan()['current_member_digest'], $memberDigest],
    'delete token' => [static fn (): mixed => $plan()['current_master_delete_token'], $deleteToken],
    'directory sync generation' => [static fn (): mixed => $plan()['current_directory_sync_generation'], 77],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 191],
    'next source prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-journal-delete-synced-source:'), true],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 192],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 6],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6]],
    'requires reader reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'delete reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'reader_cache_master_delete_token_mismatch'],
    'dir sync reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_directory_sync_generation_mismatch'],
    'pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'pinned_reader_cache_image_predates_master_journal_delete'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'dirty_reader_cache_cannot_cross_master_journal_delete'],
    'row retained reason' => [static fn (): mixed => $row(1)['reason'], 'reader_cache_matches_master_journal_delete_source'],
    'row refreshed reason' => [static fn (): mixed => $row(2)['reason'], 'reader_cache_refreshed_after_master_journal_delete'],
    'row delete token before' => [static fn (): mixed => $row(1)['delete_token_before'], $deleteToken],
    'row delete token current' => [static fn (): mixed => $row(1)['delete_token_current'], $deleteToken],
    'row delete token matches' => [static fn (): mixed => $row(1)['delete_token_matches'], true],
    'row delete token mismatch' => [static fn (): mixed => $row(3)['delete_token_matches'], false],
    'row sync before' => [static fn (): mixed => $row(4)['directory_sync_generation_before'], 76],
    'row sync current' => [static fn (): mixed => $row(4)['directory_sync_generation_current'], 77],
    'row sync mismatch' => [static fn (): mixed => $row(4)['directory_sync_generation_matches'], false],
    'row dirty flag' => [static fn (): mixed => $row(6)['dirty'], true],
    'row pinned flag' => [static fn (): mixed => $row(5)['pinned'], true],
    'row cache prefix' => [static fn (): mixed => $row(2)['cache_prefix'], 'next191 stale options root before master journal delete sync'],
    'row current prefix' => [static fn (): mixed => $row(2)['current_prefix'], 'next191 current options root after master journal delete sync'],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'next read retained hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'next read refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'next read invalidated miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'next read miss reason' => [static fn (): mixed => $plan()['next_reads'][3]['reason'], 'next_read_reopens_after_master_journal_delete'],
    'next read source id' => [static fn (): mixed => $plan()['next_reads'][0]['source_id'], $plan()['next_source']['id']],
    'next read epoch' => [static fn (): mixed => $plan()['next_reads'][0]['epoch'], 192],
    'next read refreshed prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next191 current options root after master journal delete sync'],
    'operation delete proof' => [static fn (): mixed => $plan()['operations'][0]['op'], 'verify_master_journal_deleted_and_directory_synced_next191'],
    'operation delete token' => [static fn (): mixed => $plan()['operations'][0]['delete_token'], $deleteToken],
    'operation retain exists' => [static fn (): mixed => $opExists('retain_reader_cache_after_master_journal_delete_next191'), true],
    'operation refresh exists' => [static fn (): mixed => $opExists('refresh_reader_cache_after_master_journal_delete_next191'), true],
    'operation invalidate exists' => [static fn (): mixed => $opExists('invalidate_reader_cache_after_master_journal_delete_next191'), true],
    'final source retained' => [static fn (): mixed => $plan()['final_sources'][1], 'database-before-master-delete-reader-cache-next191'],
    'final source current' => [static fn (): mixed => $plan()['final_sources'][2], 'master-journal-delete-synced-current-source-next191'],
    'final prefix current' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next191 current autoload index after master journal delete sync'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next191', $plan()['dependencies'], true), true],
    'dependency delete fence marker' => [static fn (): mixed => in_array('sqlite-master-journal-delete-directory-sync-fence', $plan()['dependencies'], true), true],
    'non overlap next188' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next188 NUL member parsing'), true],
    'newline separator equivalent' => [static fn (): mixed => $plan(null, [1], [], $journal . "\n" . $usersJournal . "\n")['current_master_delete_token'], $deleteToken],
    'duplicate member ignored' => [static fn (): mixed => $plan(null, [1], [], $journal . "\0" . $journal . "\0" . $usersJournal . "\0")['current_members'], $members],
    'all current cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1])], [1], [])['requires_reader_reopen'], false],
    'source mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('bad-source', $before[1], ['source_id' => 'old'])], [1], [])['invalidated_reasons'][1], 'reader_cache_source_predates_master_journal_delete'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('bad-epoch', $before[1], ['epoch' => 190])], [1], [])['invalidated_reasons'][1], 'reader_cache_epoch_predates_master_journal_delete'],
    'unpinned stale refreshes' => [static fn (): mixed => $plan([5 => $cacheEntry('autoload-stale', $before[5])], [5], $current)['refreshed_page_numbers'], [5]],
    'different sync generation changes token' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['master_delete_token' => 'master-delete-synced:' . substr(hash('sha256', $master . '|78|' . implode("\n", $members)), 0, 40), 'directory_sync_generation' => 78])], [1], [], null, null, null, null, null, null, 191, 78)['current_master_delete_token'] !== $deleteToken, true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next191 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, '', 191),
    'blank master rejected' => static fn () => $plan(null, null, null, str_repeat("\0", 32)),
    'missing database journal rejected' => static fn () => $plan(null, null, null, $usersJournal . "\0"),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 0),
    'bad sync generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 191, 0),
    'current page outside rejected' => static fn () => $plan(null, null, [9 => $page('outside')]),
    'bad current page rejected' => static fn () => $plan(null, null, [0 => $page('bad')]),
    'short current page rejected' => static fn () => $plan(null, null, [1 => 'short']),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', $before[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache reader rejected' => static fn () => $plan([1 => $cacheEntry('', $before[1])]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['source_id' => ''])]),
    'empty delete token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_delete_token' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['epoch' => 0])]),
    'bad cache sync rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['directory_sync_generation' => 0])]),
    'cache page outside rejected' => static fn () => $plan([9 => $cacheEntry('bad', $page('outside'))]),
    'bad read page rejected' => static fn () => $plan(null, [0]),
    'read page outside rejected' => static fn () => $plan(null, [9]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next191 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
