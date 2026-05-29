<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next190.sqlite';
$masterPath = '/srv/wp-content/database/wp-next190.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next190-meta.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n";
$members = [$mainJournal, $metaJournal];
$ordinals = [$mainJournal => 1, $metaJournal => 2];
$completeDigest = hash('sha256', $masterPath . '|' . strlen($masterBytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $masterBytes));
$sourceId = 'next190-current-page-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('next190 schema before source fence'),
    2 => $page('next190 wp_options root before source fence'),
    3 => $page('next190 active_plugins before source fence'),
    4 => $page('next190 plugin settings before source fence'),
    5 => $page('next190 cron before source fence'),
    6 => $page('next190 transient before source fence'),
    7 => $page('next190 autoload index before source fence'),
    8 => $page('next190 comments before source fence'),
];
$current = [
    3 => $page('next190 active_plugins after source fence'),
    4 => $page('next190 plugin settings before source fence'),
    5 => $page('next190 cron after source fence'),
];
$currentSources = [
    3 => 'master-journal-member:' . $mainJournal,
    4 => 'master-journal-member:' . $metaJournal,
    5 => 'master-journal-member:' . $metaJournal,
];
$digest = static fn (int $pageNumber, string $image, string $source): string => hash('sha256', $pageNumber . '|' . $source . '|' . hash('sha256', $image));
$sourceFor = static fn (int $pageNumber): string => $currentSources[$pageNumber] ?? 'database-image-before-master-journal-recovery-next190';
$currentDigest = static fn (int $pageNumber): string => $digest($pageNumber, $current[$pageNumber] ?? $before[$pageNumber], $sourceFor($pageNumber));
$oldDigest = static fn (int $pageNumber): string => $digest($pageNumber, $before[$pageNumber], 'database-image-before-master-journal-recovery-next190');
$cacheEntry = static fn (string $label, int $pageNumber, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 190,
    'master_member_ordinals' => $ordinals,
    'master_complete_read_digest' => $completeDigest,
    'master_byte_length' => strlen($masterBytes),
    'page_source_digest' => $currentDigest($pageNumber),
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-source', 1, $before[1]),
    2 => $cacheEntry('root-retained-source', 2, $before[2]),
    3 => $cacheEntry('active-refresh-source', 3, $before[3]),
    4 => $cacheEntry('settings-same-bytes-source-changed', 4, $current[4], ['page_source_digest' => $oldDigest(4)]),
    5 => $cacheEntry('cron-stale-source-digest', 5, $current[5], ['page_source_digest' => $oldDigest(5)]),
    6 => $cacheEntry('transient-source-mismatch', 6, $before[6], ['source_id' => 'old-source']),
    7 => $cacheEntry('autoload-dirty-source', 7, $before[7], ['dirty' => true]),
    8 => $cacheEntry('comments-pinned-source', 8, $page('next190 pinned comments stale'), ['pinned' => true]),
];
$reads = static fn (?string $digestOverride = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'page_source_digest' => $digestOverride ?? $currentDigest($pageNumber),
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $pages = null,
    ?array $sources = null,
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext190(
    $databasePath,
    $masterPath,
    $masterBytes,
    $bytes ?? implode('', $before),
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $pages ?? $current,
    $sources ?? $currentSources,
    $sourceId,
    190,
);

$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next190'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_page_source_digest_fences_current_source_reuse'],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 2]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [3]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [4, 5, 6, 7, 8]],
    'page source invalidated' => [static fn (): mixed => $plan()['page_source_invalidated_cache_page_numbers'], [4, 5]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row source admitted' => [static fn (): mixed => $row('schema-retained-source')['source_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-source')['source_reason'], 'reader_cache_page_source_matches_current_source'],
    'row same bytes source changed reason' => [static fn (): mixed => $row('settings-same-bytes-source-changed')['source_reason'], 'reader_cache_page_source_digest_predates_current_source'],
    'row stale digest reason' => [static fn (): mixed => $row('cron-stale-source-digest')['source_reason'], 'reader_cache_page_source_digest_predates_current_source'],
    'row base source mismatch reason' => [static fn (): mixed => $row('transient-source-mismatch')['source_reason'], 'reader_cache_source_predates_complete_master_read'],
    'row dirty reason' => [static fn (): mixed => $row('autoload-dirty-source')['source_reason'], 'dirty_reader_cache_before_complete_master_membership'],
    'row pinned reason' => [static fn (): mixed => $row('comments-pinned-source')['source_reason'], 'pinned_reader_cache_image_predates_complete_master_read'],
    'row digest matches retained' => [static fn (): mixed => $row('root-retained-source')['page_source_digest_matches'], true],
    'row digest mismatch same bytes' => [static fn (): mixed => $row('settings-same-bytes-source-changed')['page_source_digest_matches'], false],
    'row current source label' => [static fn (): mixed => $row('settings-same-bytes-source-changed')['current_page_source'], 'master-journal-member:' . $metaJournal],
    'row current digest' => [static fn (): mixed => $row('active-refresh-source')['current_page_source_digest'], $currentDigest(3)],
    'row cache digest' => [static fn (): mixed => $row('cron-stale-source-digest')['cache_page_source_digest'], $oldDigest(5)],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], true],
    'read same bytes source miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read stale source miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-5'], false],
    'read source current' => [static fn (): mixed => $plan()['next_reads'][0]['page_source_current'], true],
    'read source label' => [static fn (): mixed => $plan()['next_reads'][3]['page_source'], 'master-journal-member:' . $metaJournal],
    'read source digest' => [static fn (): mixed => $plan()['next_reads'][4]['page_source_digest'], $currentDigest(5)],
    'read invalidated source' => [static fn (): mixed => $plan()['next_reads'][3]['source'], 'master-journal-reader-cache-page-source-fence-current-source-next190'],
    'read invalidated reason' => [static fn (): mixed => $plan()['next_reads'][3]['source_reason'], 'reader_cache_reopened_after_page_source_change'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-4', 'read-5']],
    'operation source invalidation present' => [static fn (): mixed => in_array('invalidate_reader_cache_page_source_after_master_current_source_next190', array_column($plan()['operations'], 'op'), true), true],
    'operation source invalidation count' => [static fn (): mixed => count(array_filter($plan()['operations'], static fn (array $op): bool => ($op['op'] ?? '') === 'invalidate_reader_cache_page_source_after_master_current_source_next190')), 2],
    'operation reader id' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $op): bool => ($op['op'] ?? '') === 'invalidate_reader_cache_page_source_after_master_current_source_next190'))[0]['reader_id'], 'read-4'],
    'current page source map' => [static fn (): mixed => $plan()['current_page_sources'][4], 'master-journal-member:' . $metaJournal],
    'current page digest map' => [static fn (): mixed => $plan()['current_page_source_digests'][4], $currentDigest(4)],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next190', $plan()['dependencies'], true), true],
    'dependency page source marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-page-source-digest-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next187', $plan()['dependencies'], true), true],
    'non-overlap next187' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next187'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'stale read ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldDigest(4)))['read_cache_hits']['read-1'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldDigest(4)))['next_reads'][0]['source_reason'], 'reader_ticket_page_source_digest_predates_current_source'],
    'all source current no page source invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-source', 1, $before[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'page_source_digest' => $currentDigest(1)]], [], [])['page_source_invalidated_cache_page_numbers'], []],
    'all source current no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-source', 1, $before[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'page_source_digest' => $currentDigest(1)]], [], [])['requires_reader_reopen'], false],
    'same bytes changed source invalidates even when image matches' => [static fn (): mixed => $row('settings-same-bytes-source-changed')['image_matches_current_source'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next190 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad read page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 0, 'page_source_digest' => $currentDigest(1)]]),
    'empty read digest rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'page_source_digest' => '']]),
    'empty read id rejected' => static fn () => $plan(null, [['reader_id' => '', 'page_number' => 1, 'page_source_digest' => $currentDigest(1)]]),
    'empty cache digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, $before[1], ['page_source_digest' => ''])]),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', 1, $before[1])]),
    'missing current source labels rejected' => static fn () => $plan(null, null, $current, []),
    'missing source for current page rejected' => static fn () => $plan(null, null, [3 => $current[3]], []),
    'empty source label rejected' => static fn () => $plan(null, null, [3 => $current[3]], [3 => '']),
    'short current page rejected' => static fn () => $plan(null, null, [3 => 'short'], [3 => $currentSources[3]]),
    'outside current page rejected' => static fn () => $plan(null, null, [9 => $page('outside')], [9 => 'master-journal-member:outside']),
    'unaligned database rejected by base' => static fn () => $plan(null, null, null, null, 'short'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next190 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
