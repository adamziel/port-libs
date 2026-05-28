<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext168Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_journal_digest?:string,change_counter?:int,schema_cookie?:int,version_valid_for?:int,page_source_digest?:string,source_generation?:int}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $writePages
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
        array $readPages,
        array $writePages,
        string $currentSourceId,
        int $currentEpoch,
        string $currentSourceDigest,
        int $currentSourceGeneration,
    ): array {
        if ($currentSourceDigest === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next168 requires a current source digest');
        }
        if ($currentSourceGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next168 source generation must be positive');
        }

        $cacheSource = self::normalizeSourceFence($readerCache);
        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSourceFence($readerCache),
            $readPages,
            $writePages,
            $currentSourceId,
            $currentEpoch,
        );

        $expectedPageDigests = [];
        foreach ($base['final_prefixes'] as $pageNumber => $_prefix) {
            if (!isset($recoveredPages[$pageNumber])) {
                continue;
            }
            $expectedPageDigests[$pageNumber] = self::pageDigest($currentSourceDigest, $currentSourceGeneration, $pageNumber, $recoveredPages[$pageNumber]);
        }

        $sourceInvalidated = [];
        $sourceRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $source = $cacheSource[$pageNumber];
            $expectedDigest = $expectedPageDigests[$pageNumber] ?? self::pageDigest($currentSourceDigest, $currentSourceGeneration, $pageNumber, '');
            $reason = null;
            if ($source['source_generation'] !== $currentSourceGeneration) {
                $reason = 'reader_cache_source_generation_predates_current_source';
            } elseif ($source['page_source_digest'] !== $expectedDigest) {
                $reason = 'reader_cache_page_source_digest_predates_current_source';
            }

            $admittedByHeader = (bool) $row['admitted'];
            if ($admittedByHeader && $reason !== null) {
                $sourceInvalidated[] = $pageNumber;
            }

            $sourceReason = $admittedByHeader
                ? ($reason ?? 'reader_cache_source_digest_matches_current_source')
                : $row['reason'];

            $sourceRows[] = $row + [
                'source_admitted' => $admittedByHeader && $reason === null,
                'source_reason' => $sourceReason,
                'cache_page_source_digest' => $source['page_source_digest'],
                'current_page_source_digest' => $expectedDigest,
                'cache_source_generation' => $source['source_generation'],
                'current_source_generation' => $currentSourceGeneration,
            ];
        }

        if ($sourceInvalidated !== []) {
            $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $sourceInvalidated));
            $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $sourceInvalidated));
            $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $sourceInvalidated));
            $base['requires_reader_reopen'] = true;

            foreach ($base['next_reads'] as &$read) {
                if (in_array($read['page_number'], $sourceInvalidated, true)) {
                    $read['cache_hit'] = false;
                    $read['source'] = 'master-journal-reader-cache-source-fence-current-source';
                    $read['source_fence_reason'] = 'source_digest_or_generation_reopened_reader_cache';
                }
            }
            unset($read);

            foreach ($sourceInvalidated as $pageNumber) {
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_after_master_current_source_digest',
                    'page_number' => $pageNumber,
                    'reason' => $sourceRows[array_search($pageNumber, array_column($sourceRows, 'page_number'), true)]['source_reason'],
                ];
            }
        }

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next168';
        $base['reason'] = 'master_journal_reader_cache_requires_current_source_digest_generation_fence';
        $base['current_source_digest'] = $currentSourceDigest;
        $base['current_source_generation'] = $currentSourceGeneration;
        $base['reader_rows'] = $sourceRows;
        $base['source_invalidated_cache_page_numbers'] = $sourceInvalidated;
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSourceDigest . '|' . $currentSourceGeneration . '|' . implode(',', $sourceInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next168';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-current-source-digest-fence';

        return $base;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array{page_source_digest:string,source_generation:int}>
     */
    private static function normalizeSourceFence(array $readerCache): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next168 cache page numbers must be one-based integers');
            }
            $digest = (string) ($entry['page_source_digest'] ?? '');
            $generation = $entry['source_generation'] ?? 0;
            if ($digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next168 cache entries require a page source digest');
            }
            if (!is_int($generation) || $generation < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next168 cache source generation must be positive');
            }
            $normalized[$pageNumber] = [
                'page_source_digest' => $digest,
                'source_generation' => $generation,
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripSourceFence(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['page_source_digest'], $entry['source_generation']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    private static function pageDigest(string $sourceDigest, int $generation, int $pageNumber, string $image): string
    {
        return hash('sha256', $sourceDigest . '|' . $generation . '|' . $pageNumber . '|' . hash('sha256', $image));
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
}
