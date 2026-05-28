<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array<string,mixed>> $readerCache
     * @param list<array<string,mixed>> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
     * @param array<string,string> $currentMemberJournalHeaderDigests
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
        array $currentMemberJournalTokens,
        array $currentMemberJournalHeaderDigests,
        string $currentMasterJournalFileToken,
        string $currentDatabaseFileToken,
        string $currentMasterJournalCleanupToken,
        string $currentReaderLeaseToken,
    ): array {
        $cacheImageDigests = self::cacheImageDigests($readerCache);
        $readImageDigests = self::readImageDigests($nextReads);
        $currentImageDigests = self::currentImageDigests($databaseBytes, $pageSize, $recoveredPages);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext224Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripImageDigest($readerCache),
            array_map(static fn (array $read): array => self::stripOneImageDigest($read), $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
            $currentDatabaseFileToken,
            $currentMasterJournalCleanupToken,
            $currentReaderLeaseToken,
        );

        $payloadInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheDigest = $cacheImageDigests[$pageNumber] ?? '';
            $currentDigest = $currentImageDigests[$pageNumber] ?? '';
            if ($currentDigest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next228 cache page is outside current source');
            }

            $baseAdmitted = (bool) ($row['reader_lease_token_admitted'] ?? false);
            $payloadMatches = $cacheDigest === $currentDigest;
            if ($baseAdmitted && !$payloadMatches) {
                $payloadInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_page_payload_digest_after_current_source_next228',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_page_payload_digest_predates_current_master_journal_source',
                ];
            }

            $rows[] = $row + [
                'page_payload_digest_admitted' => $baseAdmitted && $payloadMatches,
                'page_payload_digest_reason' => $baseAdmitted
                    ? ($payloadMatches
                        ? 'reader_cache_page_payload_digest_matches_current_source'
                        : 'reader_cache_page_payload_digest_predates_current_master_journal_source')
                    : (string) ($row['reader_lease_token_reason'] ?? $row['reason']),
                'cache_page_payload_digest' => $cacheDigest,
                'current_page_payload_digest' => $currentDigest,
                'page_payload_digest_matches' => $payloadMatches,
            ];
        }

        $payloadInvalidated = self::sortedUnique($payloadInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $payloadInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $payloadInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $payloadInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $pageNumber = (int) $read['page_number'];
            $ticketDigest = $readImageDigests[$readerId] ?? '';
            $currentDigest = $currentImageDigests[$pageNumber] ?? '';
            $ticketCurrent = $ticketDigest === $currentDigest;
            $pageInvalidated = in_array($pageNumber, $payloadInvalidated, true);
            $read['page_payload_digest_current'] = $ticketCurrent;
            $read['page_payload_digest'] = $currentDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-page-payload-fence-current-source-next228';
                $read['page_payload_digest_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_page_payload_digest_change'
                    : 'reader_ticket_page_payload_digest_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_page_payload_digest_after_current_source_next228',
                    'page_number' => $pageNumber,
                    'reader_id' => $readerId,
                    'reason' => $read['page_payload_digest_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next228';
        $base['reason'] = 'master_journal_reader_cache_rechecks_page_payload_digest_before_current_source_reuse';
        $base['reader_rows'] = $rows;
        $base['current_page_payload_digests'] = $currentImageDigests;
        $base['page_payload_invalidated_cache_page_numbers'] = $payloadInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . implode('|', $currentImageDigests) . '|' . implode(',', $payloadInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next228';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-page-payload-digest-fence';
        $base['non_overlap'] = 'next228 adds only per-page payload digest admission after next224 reader-lease cleanup and next218/next212 source-token fences have passed; it does not repeat master-journal membership, cleanup-token, database file-token, reader-lease, VFS/WAL writer, rollback-journal commit/apply, or page relocation behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheImageDigests(array $cache): array
    {
        $digests = [];
        foreach ($cache as $pageNumber => $entry) {
            $digest = $entry['page_payload_digest'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next228 cache entries require page payload digests');
            }
            $digests[$pageNumber] = $digest;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readImageDigests(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $digest = $read['page_payload_digest'] ?? '';
            if ($readerId === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next228 reads require reader ids and page payload digests');
            }
            $digests[$readerId] = $digest;
        }

        return $digests;
    }

    /** @param array<int,string> $recoveredPages @return array<int,string> */
    private static function currentImageDigests(string $databaseBytes, int $pageSize, array $recoveredPages): array
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0 || $databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next228 requires page-size aligned database bytes');
        }

        $digests = [];
        foreach (str_split($databaseBytes, $pageSize) as $index => $image) {
            $digests[$index + 1] = hash('sha256', $image);
        }
        foreach ($recoveredPages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next228 recovered pages must be page-size aligned');
            }
            $digests[$pageNumber] = hash('sha256', $image);
        }

        return $digests;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripImageDigest(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['page_payload_digest']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneImageDigest(array $read): array
    {
        unset($read['page_payload_digest']);

        return $read;
    }

    /** @param list<int> $values @return list<int> */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /** @param list<string> $values @return list<string> */
    private static function sortReaderIds(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL);

        return $values;
    }
}
