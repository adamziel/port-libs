<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext233Plan
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
        string $currentPagerCacheSourceToken,
        string $currentReadTransactionToken,
    ): array {
        if ($currentReadTransactionToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next233 requires a current read transaction token');
        }

        $cacheTokens = self::cacheReadTransactionTokens($readerCache);
        $readTokens = self::readTransactionTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext229Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripReadTransactionToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneReadTransactionToken($read), $nextReads),
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
            $currentPagerCacheSourceToken,
        );

        $transactionInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['pager_cache_source_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentReadTransactionToken);
            if ($baseAdmitted && !$tokenMatches) {
                $transactionInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_read_transaction_after_current_source_next233',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_read_transaction_token_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'read_transaction_token_admitted' => $baseAdmitted && $tokenMatches,
                'read_transaction_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_read_transaction_token_matches_current_source'
                        : 'reader_cache_read_transaction_token_predates_master_journal_current_source')
                    : (string) ($row['pager_cache_source_token_reason'] ?? $row['reader_lease_token_reason'] ?? $row['reason']),
                'cache_read_transaction_token' => $cacheToken,
                'current_read_transaction_token' => $currentReadTransactionToken,
                'read_transaction_token_matches' => $tokenMatches,
            ];
        }

        $transactionInvalidated = self::sortedUnique($transactionInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $transactionInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $transactionInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $transactionInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentReadTransactionToken);
            $pageInvalidated = in_array((int) $read['page_number'], $transactionInvalidated, true);
            $read['read_transaction_token_current'] = $ticketCurrent;
            $read['read_transaction_token'] = $currentReadTransactionToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-read-transaction-fence-current-source-next233';
                $read['read_transaction_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_read_transaction_token_change'
                    : 'reader_ticket_read_transaction_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_read_transaction_after_current_source_next233',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['read_transaction_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next233';
        $base['reason'] = 'master_journal_reader_cache_rechecks_read_transaction_before_current_source_reuse';
        $base['current_read_transaction_token'] = $currentReadTransactionToken;
        $base['reader_rows'] = $rows;
        $base['read_transaction_invalidated_cache_page_numbers'] = $transactionInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentReadTransactionToken . '|' . implode(',', $transactionInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next233';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-read-transaction-fence';
        $base['non_overlap'] = 'next233 fences reader-cache reuse on the read-transaction token after next229 pager-cache source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat payload-digest, pager-cache source, reader-lease, cleanup-token, database file-token, member-journal, rollback-journal, WAL, VFS writer, or B-tree behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheReadTransactionTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['read_transaction_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next233 cache entries require read transaction tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readTransactionTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['read_transaction_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next233 reads require reader ids and read transaction tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripReadTransactionToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['read_transaction_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneReadTransactionToken(array $read): array
    {
        unset($read['read_transaction_token']);

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
