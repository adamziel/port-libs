<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext194Plan
{
    /**
     * @param array<int,array{image:string,label?:string,source_id?:string,epoch?:int,master_member_ordinals?:array<string,int>,master_complete_read_digest?:string,master_byte_length?:int,page_source_digest?:string,reader_transaction_id?:string,reader_snapshot_digest?:string,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<array{reader_id?:string,reader_transaction_id?:string,page_number:int,page_source_digest?:string,reader_snapshot_digest?:string}> $nextReads
     * @param array<int,string> $currentPages
     * @param array<int,string> $currentPageSources
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $nextReads,
        array $currentPages,
        array $currentPageSources,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        $readGroups = self::readGroups($nextReads);
        $cacheGroups = self::cacheGroups($readerCache);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext190Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            self::stripSnapshotFence($readerCache),
            array_map(static fn (array $read): array => [
                'reader_id' => $read['reader_id'] ?? null,
                'page_number' => $read['page_number'],
                'page_source_digest' => $read['page_source_digest'] ?? null,
            ], $nextReads),
            $currentPages,
            $currentPageSources,
            $currentSourceId,
            $currentEpoch,
        );

        $currentDigests = $base['current_page_source_digests'];
        $currentSnapshotDigests = self::currentSnapshotDigests($readGroups, $currentDigests);

        $snapshotInvalidated = [];
        $snapshotRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $groupId = $cacheGroups[$pageNumber]['reader_transaction_id'] ?? '';
            $currentDigest = $currentSnapshotDigests[$groupId] ?? null;
            if ($currentDigest === null) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next194 cache transaction {$groupId} is not represented by current reads");
            }

            $snapshotReason = null;
            if (!hash_equals($cacheGroups[$pageNumber]['reader_snapshot_digest'], $currentDigest)) {
                $snapshotReason = 'reader_cache_transaction_snapshot_predates_current_master_source';
            }

            if ((bool) ($row['source_admitted'] ?? false) && $snapshotReason !== null) {
                foreach (array_keys($readGroups[$groupId]) as $groupPage) {
                    $snapshotInvalidated[] = $groupPage;
                }
            }

            $snapshotRows[] = $row + [
                'reader_transaction_id' => $groupId,
                'snapshot_admitted' => (bool) ($row['source_admitted'] ?? false) && $snapshotReason === null,
                'snapshot_reason' => (bool) ($row['source_admitted'] ?? false)
                    ? ($snapshotReason ?? 'reader_cache_transaction_snapshot_matches_current_source')
                    : ($row['source_reason'] ?? $row['reason'] ?? 'reader_cache_rejected_before_snapshot_check'),
                'cache_reader_snapshot_digest' => $cacheGroups[$pageNumber]['reader_snapshot_digest'],
                'current_reader_snapshot_digest' => $currentDigest,
                'reader_snapshot_digest_matches' => hash_equals($cacheGroups[$pageNumber]['reader_snapshot_digest'], $currentDigest),
            ];
        }

        $snapshotInvalidated = self::sortedUnique($snapshotInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $snapshotInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $snapshotInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $snapshotInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($nextReads as $read) {
            $readerId = (string) ($read['reader_id'] ?? ('read-' . $read['page_number']));
            $readById[$readerId] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'] ?? [], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticket = $readById[$readerId] ?? null;
            if ($ticket === null) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next194 missing ticket for reader {$readerId}");
            }
            $groupId = (string) ($ticket['reader_transaction_id'] ?? '');
            $expectedSnapshot = (string) ($ticket['reader_snapshot_digest'] ?? '');
            $currentSnapshot = $currentSnapshotDigests[$groupId] ?? '';
            if ($groupId === '' || $expectedSnapshot === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next194 reads require transaction id and snapshot digest');
            }

            $snapshotCurrent = hash_equals($expectedSnapshot, $currentSnapshot);
            $groupInvalidated = in_array($read['page_number'], $snapshotInvalidated, true);
            $read['reader_transaction_id'] = $groupId;
            $read['reader_snapshot_current'] = $snapshotCurrent;
            $read['reader_snapshot_digest'] = $currentSnapshot;
            if (!$snapshotCurrent || $groupInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-transaction-snapshot-fence-current-source-next194';
                $read['snapshot_reason'] = $groupInvalidated
                    ? 'reader_transaction_reopened_after_snapshot_source_change'
                    : 'reader_ticket_snapshot_predates_current_master_source';
                foreach (array_keys($readGroups[$groupId]) as $readerPage) {
                    $reopenReaders['tx:' . $groupId . ':page:' . $readerPage] = true;
                }
                $base['operations'][] = [
                    'op' => 'invalidate_reader_transaction_snapshot_after_master_current_source_next194',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reader_transaction_id' => $groupId,
                    'reason' => $read['snapshot_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next194';
        $base['reason'] = 'master_journal_reader_cache_transaction_snapshot_fences_current_source_reuse';
        $base['reader_rows'] = $snapshotRows;
        $base['current_reader_snapshot_digests'] = $currentSnapshotDigests;
        $base['transaction_snapshot_invalidated_cache_page_numbers'] = $snapshotInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortedStrings(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . implode(',', $snapshotInvalidated) . '|' . hash('sha256', json_encode($currentSnapshotDigests, JSON_THROW_ON_ERROR)));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next194';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-transaction-snapshot-fence';
        $base['non_overlap'] = 'next194 adds transaction-wide reader snapshot cohesion after next190 per-page current-source digest fencing; it does not repeat next190 per-page source digests, next189 member rollback-journal digests, next187 complete master membership, or accepted WAL/VFS rollback application clusters.';

        return $base;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,array<int,true>> */
    private static function readGroups(array $reads): array
    {
        if ($reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next194 requires reads');
        }

        $groups = [];
        foreach ($reads as $read) {
            $group = (string) ($read['reader_transaction_id'] ?? '');
            $page = $read['page_number'] ?? 0;
            $snapshot = $read['reader_snapshot_digest'] ?? '';
            if ($group === '' || !is_int($page) || $page < 1 || !is_string($snapshot) || $snapshot === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next194 reads require transaction id, snapshot digest, and one-based page numbers');
            }
            $groups[$group][$page] = true;
        }

        return $groups;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array{reader_transaction_id:string,reader_snapshot_digest:string}> */
    private static function cacheGroups(array $cache): array
    {
        $groups = [];
        foreach ($cache as $pageNumber => $entry) {
            $group = $entry['reader_transaction_id'] ?? '';
            $snapshot = $entry['reader_snapshot_digest'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($group) || $group === '' || !is_string($snapshot) || $snapshot === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next194 cache entries require transaction id and snapshot digest');
            }
            $groups[$pageNumber] = [
                'reader_transaction_id' => $group,
                'reader_snapshot_digest' => $snapshot,
            ];
        }

        return $groups;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSnapshotFence(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['reader_transaction_id'], $entry['reader_snapshot_digest']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /**
     * @param array<string,array<int,true>> $groups
     * @param array<int,string> $currentDigests
     * @return array<string,string>
     */
    private static function currentSnapshotDigests(array $groups, array $currentDigests): array
    {
        $digests = [];
        foreach ($groups as $group => $pages) {
            $parts = [];
            foreach (array_keys($pages) as $pageNumber) {
                $digest = $currentDigests[$pageNumber] ?? null;
                if (!is_string($digest) || $digest === '') {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next194 page {$pageNumber} is outside current source digests");
                }
                $parts[] = $pageNumber . ':' . $digest;
            }
            sort($parts, SORT_NATURAL);
            $digests[$group] = hash('sha256', $group . '|' . implode('|', $parts));
        }

        return $digests;
    }

    /** @param list<int> $values @return list<int> */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /** @param list<string> $values @return list<string> */
    private static function sortedStrings(array $values): array
    {
        sort($values, SORT_NATURAL);

        return $values;
    }
}
