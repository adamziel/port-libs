<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext186Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string}> $nextReads
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
        int $currentRecoverySequence,
    ): array {
        if ($currentRecoverySequence < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next186 recovery sequence must be positive');
        }

        $currentRecoveredPageSetDigest = self::recoveredPageSetDigest($recoveredPages, $pageSize);
        $cache = self::normalizeRecoveryCache($readerCache);
        $reads = self::normalizeRecoveryReads($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext183Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripRecoveryFence($readerCache),
            array_map(static fn (array $read): array => [
                'reader_id' => $read['reader_id'],
                'page_number' => $read['page_number'],
                'source_id' => $read['source_id'],
                'epoch' => $read['epoch'],
                'format_signature' => $read['format_signature'],
                'publication_generation' => $read['publication_generation'],
                'master_source_digest' => $read['master_source_digest'],
            ], $reads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
        );

        $recoveryInvalidated = [];
        $recoveryRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $fence = $cache[$pageNumber];
            $reason = null;
            if ($fence['recovery_sequence'] !== $currentRecoverySequence) {
                $reason = 'reader_cache_recovery_sequence_predates_master_journal_source';
            } elseif ($fence['recovered_page_set_digest'] !== $currentRecoveredPageSetDigest) {
                $reason = 'reader_cache_recovered_page_set_digest_predates_current_source';
            }

            if ((bool) ($row['publication_admitted'] ?? false) && $reason !== null) {
                $recoveryInvalidated[] = $pageNumber;
            }

            $recoveryRows[] = $row + [
                'recovery_admitted' => (bool) ($row['publication_admitted'] ?? false) && $reason === null,
                'recovery_reason' => (bool) ($row['publication_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_recovery_sequence_matches_current_source')
                    : ($row['publication_reason'] ?? $row['reason']),
                'cache_recovery_sequence' => $fence['recovery_sequence'],
                'current_recovery_sequence' => $currentRecoverySequence,
                'cache_recovered_page_set_digest' => $fence['recovered_page_set_digest'],
                'current_recovered_page_set_digest' => $currentRecoveredPageSetDigest,
                'recovered_page_set_digest_matches' => $fence['recovered_page_set_digest'] === $currentRecoveredPageSetDigest,
            ];
        }

        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $recoveryInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $recoveryInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $recoveryInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $readById = [];
        foreach ($reads as $read) {
            $readById[$read['reader_id']] = $read;
        }

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $ticket = $readById[$read['reader_id']];
            $recoveryCurrent = $ticket['recovery_sequence'] === $currentRecoverySequence
                && $ticket['recovered_page_set_digest'] === $currentRecoveredPageSetDigest;
            $pageInvalidated = in_array($read['page_number'], $recoveryInvalidated, true);
            $read['recovery_current'] = $recoveryCurrent;
            $read['recovery_sequence'] = $currentRecoverySequence;
            $read['recovered_page_set_digest'] = $currentRecoveredPageSetDigest;
            if (!$recoveryCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-recovered-page-set-fence-current-source-next186';
                $read['recovery_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_recovered_page_set_change'
                    : 'reader_ticket_recovery_sequence_predates_current_source';
                $reopenReaders[$read['reader_id']] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_recovered_page_set_after_master_current_source_next186',
                    'page_number' => $read['page_number'],
                    'reader_id' => $read['reader_id'],
                    'reason' => $read['recovery_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next186';
        $base['reason'] = 'master_journal_reader_cache_recovered_page_set_sequence_fences_current_source_reuse';
        $base['current_recovery_sequence'] = $currentRecoverySequence;
        $base['current_recovered_page_set_digest'] = $currentRecoveredPageSetDigest;
        $base['reader_rows'] = $recoveryRows;
        $base['recovery_invalidated_cache_page_numbers'] = $recoveryInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentRecoverySequence . '|' . $currentRecoveredPageSetDigest . '|' . implode(',', $recoveryInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next186';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-recovered-page-set-sequence-fence';
        $base['non_overlap'] = 'next186 fences reader-cache reuse on the exact recovered-page set and recovery sequence after master-journal recovery; it does not repeat next183 publication-generation/master-source digest fencing or next180 page-1 format-ticket checks.';

        return $base;
    }

    /**
     * @param array<int,string> $recoveredPages
     */
    private static function recoveredPageSetDigest(array $recoveredPages, int $pageSize): string
    {
        if ($recoveredPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next186 requires recovered pages');
        }

        ksort($recoveredPages, SORT_NUMERIC);
        $parts = [];
        foreach ($recoveredPages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next186 recovered pages must be one-based page-size images');
            }
            $parts[] = $pageNumber . ':' . hash('sha256', $image);
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array{recovery_sequence:int,recovered_page_set_digest:string}>
     */
    private static function normalizeRecoveryCache(array $readerCache): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next186 cache page numbers must be one-based integers');
            }
            $sequence = $entry['recovery_sequence'] ?? 0;
            $digest = $entry['recovered_page_set_digest'] ?? '';
            if (!is_int($sequence) || $sequence < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next186 cache entries require recovery sequence and recovered page-set digest');
            }
            $normalized[$pageNumber] = [
                'recovery_sequence' => $sequence,
                'recovered_page_set_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,format_signature:string,publication_generation:int,master_source_digest:string,recovery_sequence:int,recovered_page_set_digest:string}>
     */
    private static function normalizeRecoveryReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $sequence = $read['recovery_sequence'] ?? 0;
            $digest = $read['recovered_page_set_digest'] ?? '';
            if (!is_int($sequence) || $sequence < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next186 reads require recovery sequence and recovered page-set digest');
            }
            $normalized[] = [
                'reader_id' => (string) ($read['reader_id'] ?? ''),
                'page_number' => $read['page_number'] ?? 0,
                'source_id' => (string) ($read['source_id'] ?? ''),
                'epoch' => $read['epoch'] ?? 0,
                'format_signature' => (string) ($read['format_signature'] ?? ''),
                'publication_generation' => $read['publication_generation'] ?? 0,
                'master_source_digest' => (string) ($read['master_source_digest'] ?? ''),
                'recovery_sequence' => $sequence,
                'recovered_page_set_digest' => $digest,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @return array<int,array<string,mixed>>
     */
    private static function stripRecoveryFence(array $readerCache): array
    {
        $stripped = [];
        foreach ($readerCache as $pageNumber => $entry) {
            unset($entry['recovery_sequence'], $entry['recovered_page_set_digest']);
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
