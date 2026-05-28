<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext225Plan
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
        string $currentDatabaseHeaderDigest,
        int $currentDatabasePageCount,
        int $currentChangeCounter,
        int $currentVersionValidFor,
    ): array {
        if ($currentChangeCounter < 0 || $currentVersionValidFor < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next225 requires non-negative cache validity counters');
        }

        $cacheCounters = self::cacheValidityCounters($readerCache);
        $readCounters = self::readValidityCounters($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext219Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripValidityCounters($readerCache),
            array_map(static fn (array $read): array => self::stripOneValidityCounter($read), $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
            $currentDatabaseFileToken,
            $currentDatabaseHeaderDigest,
            $currentDatabasePageCount,
        );

        $currentToken = self::validityToken($currentChangeCounter, $currentVersionValidFor);
        $counterInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cache = $cacheCounters[$pageNumber] ?? ['change_counter' => -1, 'version_valid_for' => -1];
            $reason = null;
            if ($cache['change_counter'] !== $currentChangeCounter) {
                $reason = 'reader_cache_change_counter_changed_after_master_journal_recovery';
            } elseif ($cache['version_valid_for'] !== $currentVersionValidFor) {
                $reason = 'reader_cache_version_valid_for_changed_after_master_journal_recovery';
            }

            if ((bool) ($row['database_page_count_admitted'] ?? false) && $reason !== null) {
                $counterInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'database_cache_validity_admitted' => (bool) ($row['database_page_count_admitted'] ?? false) && $reason === null,
                'database_cache_validity_reason' => (bool) ($row['database_page_count_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_database_validity_counters_match_current_source')
                    : ($row['database_page_count_reason'] ?? $row['database_header_digest_reason'] ?? $row['reason']),
                'cache_database_change_counter' => $cache['change_counter'],
                'current_database_change_counter' => $currentChangeCounter,
                'cache_database_version_valid_for' => $cache['version_valid_for'],
                'current_database_version_valid_for' => $currentVersionValidFor,
                'cache_database_validity_token' => self::validityToken($cache['change_counter'], $cache['version_valid_for']),
                'current_database_validity_token' => $currentToken,
                'database_change_counter_matches' => $cache['change_counter'] === $currentChangeCounter,
                'database_version_valid_for_matches' => $cache['version_valid_for'] === $currentVersionValidFor,
            ];
        }

        $counterInvalidated = self::sortedUnique($counterInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $counterInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $counterInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $counterInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticket = $readCounters[$readerId] ?? ['change_counter' => -1, 'version_valid_for' => -1];
            $ticketCurrent = $ticket['change_counter'] === $currentChangeCounter
                && $ticket['version_valid_for'] === $currentVersionValidFor;
            $pageInvalidated = in_array($read['page_number'], $counterInvalidated, true);
            $read['database_change_counter_current'] = $ticket['change_counter'] === $currentChangeCounter;
            $read['database_version_valid_for_current'] = $ticket['version_valid_for'] === $currentVersionValidFor;
            $read['database_cache_validity_current'] = $ticketCurrent;
            $read['database_change_counter'] = $currentChangeCounter;
            $read['database_version_valid_for'] = $currentVersionValidFor;
            $read['database_cache_validity_token'] = $currentToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-validity-counter-fence-current-source-next225';
                $read['database_cache_validity_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_database_validity_counter_change'
                    : ($ticket['change_counter'] !== $currentChangeCounter
                        ? 'reader_ticket_change_counter_predates_current_source'
                        : 'reader_ticket_version_valid_for_predates_current_source');
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_database_validity_after_current_source_next225',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_cache_validity_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next225';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_change_counter_and_version_valid_for_before_current_source_reuse';
        $base['current_database_change_counter'] = $currentChangeCounter;
        $base['current_database_version_valid_for'] = $currentVersionValidFor;
        $base['current_database_cache_validity_token'] = $currentToken;
        $base['reader_rows'] = $rows;
        $base['database_cache_validity_invalidated_cache_page_numbers'] = $counterInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentToken . '|' . implode(',', $counterInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next225';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-change-counter-version-valid-for-fence';
        $base['non_overlap'] = 'next225 fences reader-cache reuse on the SQLite page-1 change-counter/version-valid-for tuple after next219 page-count admission; it does not repeat database header digest, page-count truncation, master-journal bytes/tokens, rollback-journal apply, WAL, VFS writer, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array{change_counter:int,version_valid_for:int}> */
    private static function cacheValidityCounters(array $cache): array
    {
        $counters = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next225 cache page numbers must be one-based integers');
            }
            $changeCounter = $entry['database_change_counter'] ?? null;
            $versionValidFor = $entry['database_version_valid_for'] ?? null;
            if (!is_int($changeCounter) || $changeCounter < 0 || !is_int($versionValidFor) || $versionValidFor < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next225 cache entries require non-negative database validity counters');
            }
            $counters[$pageNumber] = [
                'change_counter' => $changeCounter,
                'version_valid_for' => $versionValidFor,
            ];
        }

        return $counters;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,array{change_counter:int,version_valid_for:int}> */
    private static function readValidityCounters(array $reads): array
    {
        $counters = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $changeCounter = $read['database_change_counter'] ?? null;
            $versionValidFor = $read['database_version_valid_for'] ?? null;
            if ($readerId === '' || !is_int($changeCounter) || $changeCounter < 0 || !is_int($versionValidFor) || $versionValidFor < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next225 reads require reader ids and non-negative database validity counters');
            }
            $counters[$readerId] = [
                'change_counter' => $changeCounter,
                'version_valid_for' => $versionValidFor,
            ];
        }

        return $counters;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripValidityCounters(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_change_counter'], $entry['database_version_valid_for']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneValidityCounter(array $read): array
    {
        unset($read['database_change_counter'], $read['database_version_valid_for']);

        return $read;
    }

    private static function validityToken(int $changeCounter, int $versionValidFor): string
    {
        return hash('sha256', $changeCounter . ':' . $versionValidFor);
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
