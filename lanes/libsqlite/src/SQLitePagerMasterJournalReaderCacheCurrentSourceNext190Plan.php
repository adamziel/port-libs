<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext190Plan
{
    /**
     * @param array<int,array{image:string,label?:string,source_id?:string,epoch?:int,master_member_ordinals?:array<string,int>,master_complete_read_digest?:string,master_byte_length?:int,page_source_digest?:string,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,page_source_digest?:string}> $nextReads
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
        $readPages = self::readPages($nextReads);
        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            self::stripPageSourceDigest($readerCache),
            $readPages,
            $currentPages,
            $currentSourceId,
            $currentEpoch,
        );

        $sources = self::sourceMap($databaseBytes, $pageSize, $currentPages, $currentPageSources);
        $cacheDigests = self::cachePageSourceDigests($readerCache);
        $readDigests = self::readPageSourceDigests($nextReads);
        $currentDigests = [];
        foreach ($sources as $pageNumber => $source) {
            $currentDigests[$pageNumber] = self::pageSourceDigest($pageNumber, $source['image'], $source['source']);
        }

        $sourceInvalidated = [];
        $sourceRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $currentDigest = $currentDigests[$pageNumber] ?? null;
            if ($currentDigest === null) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next190 page {$pageNumber} is outside the current source map");
            }

            $cacheDigest = $cacheDigests[$pageNumber] ?? '';
            $sourceReason = null;
            if ($cacheDigest !== $currentDigest) {
                $sourceReason = 'reader_cache_page_source_digest_predates_current_source';
            }

            if ((bool) ($row['admitted'] ?? false) && $sourceReason !== null) {
                $sourceInvalidated[] = $pageNumber;
            }

            $sourceRows[] = $row + [
                'source_admitted' => (bool) ($row['admitted'] ?? false) && $sourceReason === null,
                'source_reason' => (bool) ($row['admitted'] ?? false)
                    ? ($sourceReason ?? 'reader_cache_page_source_matches_current_source')
                    : ($row['reason'] ?? 'reader_cache_rejected_before_page_source_check'),
                'cache_page_source_digest' => $cacheDigest,
                'current_page_source_digest' => $currentDigest,
                'page_source_digest_matches' => $cacheDigest === $currentDigest,
                'current_page_source' => $sources[$pageNumber]['source'],
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $sourceInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $sourceInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $sourceInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = [];
        foreach ($base['next_reads'] as &$read) {
            $pageNumber = $read['page_number'];
            $readerId = self::readerIdForPage($nextReads, $pageNumber);
            $expectedDigest = $readDigests[$readerId] ?? '';
            $currentDigest = $currentDigests[$pageNumber] ?? '';
            $current = $expectedDigest === $currentDigest;
            $pageInvalidated = in_array($pageNumber, $sourceInvalidated, true);
            $read['reader_id'] = $readerId;
            $read['page_source_current'] = $current;
            $read['page_source_digest'] = $currentDigest;
            $read['page_source'] = $sources[$pageNumber]['source'] ?? 'unknown';
            if (!$current || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-page-source-fence-current-source-next190';
                $read['source_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_page_source_change'
                    : 'reader_ticket_page_source_digest_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_page_source_after_master_current_source_next190',
                    'page_number' => $pageNumber,
                    'reader_id' => $readerId,
                    'reason' => $read['source_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next190';
        $base['reason'] = 'master_journal_reader_cache_page_source_digest_fences_current_source_reuse';
        $base['reader_rows'] = $sourceRows;
        $base['current_page_sources'] = array_column($sources, 'source');
        $base['current_page_source_digests'] = $currentDigests;
        $base['page_source_invalidated_cache_page_numbers'] = $sourceInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortedStrings(array_unique(array_merge($base['reopen_reader_ids'] ?? [], array_keys($reopenReaders))));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . implode(',', $sourceInvalidated) . '|' . hash('sha256', json_encode($currentDigests, JSON_THROW_ON_ERROR)));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next190';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-page-source-digest-fence';
        $base['non_overlap'] = 'next190 fences per-page current-source provenance for reader-cache reuse after a complete master-journal read; it does not repeat next187 complete membership ordinal/digest fencing or next186 recovered-page-set sequence fencing.';

        return $base;
    }

    /** @param list<array<string,mixed>> $reads @return list<int> */
    private static function readPages(array $reads): array
    {
        if ($reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 requires reads');
        }
        $pages = [];
        foreach ($reads as $read) {
            $page = $read['page_number'] ?? 0;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 reads require one-based page numbers');
            }
            $pages[] = $page;
        }

        return $pages;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPageSourceDigest(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['page_source_digest']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePageSourceDigests(array $cache): array
    {
        $digests = [];
        foreach ($cache as $pageNumber => $entry) {
            $digest = $entry['page_source_digest'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 cache entries require page source digests');
            }
            $digests[$pageNumber] = $digest;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPageSourceDigests(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? ('read-' . ($read['page_number'] ?? '')));
            $digest = $read['page_source_digest'] ?? '';
            if ($readerId === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 reads require reader ids and page source digests');
            }
            $digests[$readerId] = $digest;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads */
    private static function readerIdForPage(array $reads, int $pageNumber): string
    {
        foreach ($reads as $read) {
            if (($read['page_number'] ?? null) === $pageNumber) {
                return (string) ($read['reader_id'] ?? ('read-' . $pageNumber));
            }
        }

        return 'read-' . $pageNumber;
    }

    /**
     * @param array<int,string> $currentPages
     * @param array<int,string> $currentPageSources
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, array $currentPages, array $currentPageSources): array
    {
        if ($currentPages !== [] && $currentPageSources === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 requires current page sources');
        }
        $map = [];
        foreach (str_split($databaseBytes, $pageSize) as $index => $image) {
            $map[$index + 1] = ['image' => $image, 'source' => 'database-image-before-master-journal-recovery-next190'];
        }
        foreach ($currentPages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !isset($map[$pageNumber]) || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 current pages must be in-range page-size images');
            }
            $source = $currentPageSources[$pageNumber] ?? null;
            if (!is_string($source) || $source === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next190 current pages require non-empty source labels');
            }
            $map[$pageNumber] = ['image' => $image, 'source' => $source];
        }

        return $map;
    }

    private static function pageSourceDigest(int $pageNumber, string $image, string $source): string
    {
        return hash('sha256', $pageNumber . '|' . $source . '|' . hash('sha256', $image));
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
