<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext183Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string}> $nextReads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $recoveredPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
        int $currentPublicationGeneration,
        string $currentMasterSourceDigest,
    ): array {
        if ($currentPublicationGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next183 publication generation must be positive');
        }
        if ($currentMasterSourceDigest === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next183 requires a current master source digest');
        }

        $cache = self::normalizePublicationCache($readerCache);
        $reads = self::normalizePublicationReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext180Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPublicationFence($readerCache),
            array_map(static fn (array $read): array => [
                'reader_id' => $read['reader_id'],
                'page_number' => $read['page_number'],
                'source_id' => $read['source_id'],
                'epoch' => $read['epoch'],
                'format_signature' => $read['format_signature'],
            ], $reads),
            $currentSourceId,
            $currentEpoch,
        );

        $publicationInvalidated = [];
        $publicationRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $publication = $cache[$pageNumber];
            $reason = null;
            if ($publication['master_source_digest'] !== $currentMasterSourceDigest) {
                $reason = 'reader_cache_master_source_digest_predates_publication_source';
            } elseif ($publication['publication_generation'] !== $currentPublicationGeneration) {
                $reason = 'reader_cache_publication_generation_predates_current_source';
            }

            if ((bool) $row['admitted'] && $reason !== null) {
                $publicationInvalidated[] = $pageNumber;
            }

            $publicationRows[] = $row + [
                'publication_admitted' => (bool) $row['admitted'] && $reason === null,
                'publication_reason' => (bool) $row['admitted']
                    ? ($reason ?? 'reader_cache_publication_matches_current_source')
                    : $row['reason'],
                'cache_publication_generation' => $publication['publication_generation'],
                'current_publication_generation' => $currentPublicationGeneration,
                'cache_master_source_digest' => $publication['master_source_digest'],
                'current_master_source_digest' => $currentMasterSourceDigest,
                'master_source_digest_matches' => $publication['master_source_digest'] === $currentMasterSourceDigest,
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $publicationInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $publicationInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $publicationInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($reads as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $publication = $readById[$read['reader_id']];
            $publicationCurrent = $publication['publication_generation'] === $currentPublicationGeneration
                && $publication['master_source_digest'] === $currentMasterSourceDigest;
            $pageInvalidated = in_array($read['page_number'], $publicationInvalidated, true);
            $read['publication_current'] = $publicationCurrent;
            $read['publication_generation'] = $currentPublicationGeneration;
            $read['master_source_digest'] = $currentMasterSourceDigest;
            if (!$publicationCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-publication-fence-current-source-next183';
                $read['publication_reason'] = $pageInvalidated
                    ? 'reader_cache_publication_reopened_after_master_source_change'
                    : 'reader_ticket_publication_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_publication_after_master_current_source_next183',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'reason' => $read['publication_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next183';
        $base['reason'] = 'master_journal_reader_cache_publication_generation_fences_current_source_reuse';
        $base['current_publication_generation'] = $currentPublicationGeneration;
        $base['current_master_source_digest'] = $currentMasterSourceDigest;
        $base['reader_rows'] = $publicationRows;
        $base['publication_invalidated_cache_page_numbers'] = $publicationInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPublicationGeneration . '|' . $currentMasterSourceDigest . '|' . implode(',', $publicationInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next183';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-publication-generation-fence';
        $base['non_overlap'] = 'next183 fences reader-cache publication generation and master-source digest after master-journal recovery; it does not repeat next180 format-ticket checks, next168 source-page digest fences, or accepted pager master-journal reader-cache publication coverage.';

        return $base;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array{publication_generation:int,master_source_digest:string}>
     */
    private static function normalizePublicationCache(array $readerCache): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next183 cache page numbers must be one-based integers');
            }
            $generation = $entry['publication_generation'] ?? 0;
            $digest = $entry['master_source_digest'] ?? '';
            if (!is_int($generation) || $generation < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next183 cache entries require publication generation and master source digest');
            }
            $normalized[$pageNumber] = [
                'publication_generation' => $generation,
                'master_source_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string,publication_generation:int,master_source_digest:string}>
     */
    private static function normalizePublicationReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $generation = $read['publication_generation'] ?? 0;
            $digest = $read['master_source_digest'] ?? '';
            if (!is_int($generation) || $generation < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next183 reads require publication generation and master source digest');
            }
            $normalized[] = [
                'reader_id' => (string) ($read['reader_id'] ?? ''),
                'page_number' => $read['page_number'] ?? 0,
                'source_id' => (string) ($read['source_id'] ?? ''),
                'epoch' => $read['epoch'] ?? 0,
                'format_signature' => (string) ($read['format_signature'] ?? ''),
                'publication_generation' => $generation,
                'master_source_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripPublicationFence(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['publication_generation'], $entry['master_source_digest']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /**
     * @param list<string> $readerIds
     * @return list<string>
     */
    private static function sortReaderIds(array $readerIds): array
    {
        usort($readerIds, static function (string $left, string $right): int {
            $leftNumber = preg_match('/(\d+)$/', $left, $leftMatch) === 1 ? (int) $leftMatch[1] : null;
            $rightNumber = preg_match('/(\d+)$/', $right, $rightMatch) === 1 ? (int) $rightMatch[1] : null;
            if ($leftNumber !== null && $rightNumber !== null && $leftNumber !== $rightNumber) {
                return $leftNumber <=> $rightNumber;
            }

            return $left <=> $right;
        });

        return $readerIds;
    }
}
