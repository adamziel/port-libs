<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next194.sqlite';
$masterPath = '/srv/wp-content/database/wp-next194.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next194-meta.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n";
$members = [$mainJournal, $metaJournal];
$ordinals = [$mainJournal => 1, $metaJournal => 2];
$completeDigest = hash('sha256', $masterPath . '|' . strlen($masterBytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $masterBytes));
$sourceId = 'next194-current-page-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('next194 schema before snapshot fence'),
    2 => $page('next194 options root before snapshot fence'),
    3 => $page('next194 active_plugins before snapshot fence'),
    4 => $page('next194 plugin settings before snapshot fence'),
    5 => $page('next194 cron before snapshot fence'),
    6 => $page('next194 transient before snapshot fence'),
];
$current = [
    3 => $page('next194 active_plugins after snapshot fence'),
    4 => $page('next194 plugin settings before snapshot fence'),
    5 => $page('next194 cron after snapshot fence'),
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
$snapshot = static function (string $group, array $pages, callable $digestFor): string {
    $parts = [];
    foreach ($pages as $pageNumber) {
        $parts[] = $pageNumber . ':' . $digestFor($pageNumber);
    }
    sort($parts, SORT_NATURAL);

    return hash('sha256', $group . '|' . implode('|', $parts));
};
$currentSnapshot = static fn (string $group, array $pages): string => $snapshot($group, $pages, $currentDigest);
$oldSnapshot = static fn (string $group, array $pages): string => $snapshot($group, $pages, $oldDigest);

$cacheEntry = static fn (string $label, int $pageNumber, string $image, string $group, string $snapshotDigest, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 194,
    'master_member_ordinals' => $ordinals,
    'master_complete_read_digest' => $completeDigest,
    'master_byte_length' => strlen($masterBytes),
    'page_source_digest' => $currentDigest($pageNumber),
    'reader_transaction_id' => $group,
    'reader_snapshot_digest' => $snapshotDigest,
], $extra);
$read = static fn (string $id, string $group, int $pageNumber, string $snapshotDigest, ?string $pageDigest = null): array => [
    'reader_id' => $id,
    'reader_transaction_id' => $group,
    'page_number' => $pageNumber,
    'page_source_digest' => $pageDigest ?? $currentDigest($pageNumber),
    'reader_snapshot_digest' => $snapshotDigest,
];
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-snapshot', 1, $before[1], 'tx-options', $currentSnapshot('tx-options', [1, 2])),
    2 => $cacheEntry('root-retained-snapshot', 2, $before[2], 'tx-options', $currentSnapshot('tx-options', [1, 2])),
    3 => $cacheEntry('active-refresh-stale-snapshot', 3, $before[3], 'tx-plugin', $oldSnapshot('tx-plugin', [3, 4])),
    4 => $cacheEntry('settings-same-bytes-stale-snapshot', 4, $current[4], 'tx-plugin', $oldSnapshot('tx-plugin', [3, 4])),
    5 => $cacheEntry('cron-current-cache-read-ticket-stale', 5, $current[5], 'tx-cron', $currentSnapshot('tx-cron', [5])),
    6 => $cacheEntry('transient-dirty-snapshot', 6, $before[6], 'tx-transient', $currentSnapshot('tx-transient', [6]), ['dirty' => true]),
];
$reads = static fn (?string $cronSnapshot = null): array => [
    $read('schema-reader', 'tx-options', 1, $currentSnapshot('tx-options', [1, 2])),
    $read('root-reader', 'tx-options', 2, $currentSnapshot('tx-options', [1, 2])),
    $read('active-reader', 'tx-plugin', 3, $currentSnapshot('tx-plugin', [3, 4])),
    $read('settings-reader', 'tx-plugin', 4, $currentSnapshot('tx-plugin', [3, 4])),
    $read('cron-reader', 'tx-cron', 5, $cronSnapshot ?? $currentSnapshot('tx-cron', [5])),
    $read('transient-reader', 'tx-transient', 6, $currentSnapshot('tx-transient', [6])),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $pages = null,
    ?array $sources = null,
    ?string $bytes = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext194(
    $databasePath,
    $masterPath,
    $masterBytes,
    $bytes ?? implode('', $before),
    $pageSize,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $pages ?? $current,
    $sources ?? $currentSources,
    $sourceId,
    194,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next194'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_transaction_snapshot_fences_current_source_reuse'],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 2, 5]],
    'refreshed pages removed by transaction fence' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 6]],
    'transaction invalidated pages' => [static fn (): mixed => $plan()['transaction_snapshot_invalidated_cache_page_numbers'], [3, 4]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'options row admitted' => [static fn (): mixed => $row('schema-retained-snapshot')['snapshot_admitted'], true],
    'options row reason' => [static fn (): mixed => $row('schema-retained-snapshot')['snapshot_reason'], 'reader_cache_transaction_snapshot_matches_current_source'],
    'plugin refresh row denied by snapshot' => [static fn (): mixed => $row('active-refresh-stale-snapshot')['snapshot_admitted'], false],
    'plugin refresh reason' => [static fn (): mixed => $row('active-refresh-stale-snapshot')['snapshot_reason'], 'reader_cache_transaction_snapshot_predates_current_master_source'],
    'same bytes row denied by snapshot' => [static fn (): mixed => $row('settings-same-bytes-stale-snapshot')['snapshot_admitted'], false],
    'same bytes row source still matches per-page' => [static fn (): mixed => $row('settings-same-bytes-stale-snapshot')['page_source_digest_matches'], true],
    'dirty row keeps base reason' => [static fn (): mixed => $row('transient-dirty-snapshot')['snapshot_reason'], 'dirty_reader_cache_before_complete_master_membership'],
    'cache snapshot digest exposed' => [static fn (): mixed => $row('active-refresh-stale-snapshot')['cache_reader_snapshot_digest'], $oldSnapshot('tx-plugin', [3, 4])],
    'current snapshot digest exposed' => [static fn (): mixed => $row('active-refresh-stale-snapshot')['current_reader_snapshot_digest'], $currentSnapshot('tx-plugin', [3, 4])],
    'snapshot digest mismatch' => [static fn (): mixed => $row('active-refresh-stale-snapshot')['reader_snapshot_digest_matches'], false],
    'snapshot digest match' => [static fn (): mixed => $row('root-retained-snapshot')['reader_snapshot_digest_matches'], true],
    'current snapshot map options' => [static fn (): mixed => $plan()['current_reader_snapshot_digests']['tx-options'], $currentSnapshot('tx-options', [1, 2])],
    'current snapshot map plugin' => [static fn (): mixed => $plan()['current_reader_snapshot_digests']['tx-plugin'], $currentSnapshot('tx-plugin', [3, 4])],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'schema cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'root cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['root-reader'], true],
    'active cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['active-reader'], false],
    'settings cache miss from grouped invalidation' => [static fn (): mixed => $plan()['read_cache_hits']['settings-reader'], false],
    'cron cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['cron-reader'], true],
    'transient cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['transient-reader'], false],
    'active snapshot current true for read ticket' => [static fn (): mixed => $plan()['next_reads'][2]['reader_snapshot_current'], true],
    'active read invalidated by group' => [static fn (): mixed => $plan()['next_reads'][2]['snapshot_reason'], 'reader_transaction_reopened_after_snapshot_source_change'],
    'settings read invalidated by group' => [static fn (): mixed => $plan()['next_reads'][3]['snapshot_reason'], 'reader_transaction_reopened_after_snapshot_source_change'],
    'active read source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-transaction-snapshot-fence-current-source-next194'],
    'read transaction id exposed' => [static fn (): mixed => $plan()['next_reads'][3]['reader_transaction_id'], 'tx-plugin'],
    'read snapshot digest exposed' => [static fn (): mixed => $plan()['next_reads'][3]['reader_snapshot_digest'], $currentSnapshot('tx-plugin', [3, 4])],
    'stale read ticket misses cron cache' => [static fn (): mixed => $plan(null, $reads($oldSnapshot('tx-cron', [5])))['read_cache_hits']['cron-reader'], false],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSnapshot('tx-cron', [5])))['next_reads'][4]['snapshot_reason'], 'reader_ticket_snapshot_predates_current_master_source'],
    'operation present' => [static fn (): mixed => in_array('invalidate_reader_transaction_snapshot_after_master_current_source_next194', array_column($plan()['operations'], 'op'), true), true],
    'operation count' => [static fn (): mixed => count(array_filter($plan()['operations'], static fn (array $op): bool => ($op['op'] ?? '') === 'invalidate_reader_transaction_snapshot_after_master_current_source_next194')), 2],
    'operation transaction id' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $op): bool => ($op['op'] ?? '') === 'invalidate_reader_transaction_snapshot_after_master_current_source_next194'))[0]['reader_transaction_id'], 'tx-plugin'],
    'operation page number' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $op): bool => ($op['op'] ?? '') === 'invalidate_reader_transaction_snapshot_after_master_current_source_next194'))[1]['page_number'], 4],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next194', $plan()['dependencies'], true), true],
    'dependency snapshot marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-transaction-snapshot-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next190', $plan()['dependencies'], true), true],
    'non-overlap next190' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next190'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'all snapshots current retained' => [static fn (): mixed => $plan([1 => $cacheEntry('only', 1, $before[1], 'tx-one', $currentSnapshot('tx-one', [1]))], [$read('only-reader', 'tx-one', 1, $currentSnapshot('tx-one', [1]))], [], [])['retained_cache_page_numbers'], [1]],
    'all snapshots current no invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('only', 1, $before[1], 'tx-one', $currentSnapshot('tx-one', [1]))], [$read('only-reader', 'tx-one', 1, $currentSnapshot('tx-one', [1]))], [], [])['transaction_snapshot_invalidated_cache_page_numbers'], []],
    'all snapshots current no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('only', 1, $before[1], 'tx-one', $currentSnapshot('tx-one', [1]))], [$read('only-reader', 'tx-one', 1, $currentSnapshot('tx-one', [1]))], [], [])['requires_reader_reopen'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next194 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty reads rejected' => static fn () => $plan(null, []),
    'read missing transaction rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'page_source_digest' => $currentDigest(1), 'reader_snapshot_digest' => $currentSnapshot('bad', [1])]]),
    'read empty snapshot rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'bad', 'page_number' => 1, 'page_source_digest' => $currentDigest(1), 'reader_snapshot_digest' => '']]),
    'read bad page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'bad', 'page_number' => 0, 'page_source_digest' => $currentDigest(1), 'reader_snapshot_digest' => $currentSnapshot('bad', [1])]]),
    'cache missing transaction rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, $before[1], 'tx-one', $currentSnapshot('tx-one', [1]), ['reader_transaction_id' => ''])]),
    'cache empty snapshot rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, $before[1], 'tx-one', $currentSnapshot('tx-one', [1]), ['reader_snapshot_digest' => ''])]),
    'cache transaction absent from reads rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, $before[1], 'tx-missing', $currentSnapshot('tx-one', [1]))], [$read('only-reader', 'tx-one', 1, $currentSnapshot('tx-one', [1]))]),
    'read outside current digest rejected' => static fn () => $plan(null, [$read('outside', 'tx-outside', 7, hash('sha256', 'outside'), hash('sha256', 'outside'))]),
    'base empty page source digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, $before[1], 'tx-one', $currentSnapshot('tx-one', [1]), ['page_source_digest' => ''])], [$read('only-reader', 'tx-one', 1, $currentSnapshot('tx-one', [1]))]),
    'base missing current source rejected' => static fn () => $plan(null, null, [3 => $current[3]], []),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next194 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
