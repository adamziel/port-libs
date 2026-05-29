<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Plan
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
        string $currentSchemaReparseToken,
        string $currentStatementSchemaRootToken,
        string $currentSourceProvenanceToken,
    ): array {
        if ($currentSourceProvenanceToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next243 requires a current-source provenance token');
        }

        $cacheTokens = self::cacheCurrentSourceProvenanceTokens($readerCache);
        $readTokens = self::readCurrentSourceProvenanceTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext240Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripCurrentSourceProvenanceToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneCurrentSourceProvenanceToken($read), $nextReads),
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
            $currentReadTransactionToken,
            $currentSchemaReparseToken,
            $currentStatementSchemaRootToken,
        );

        $provenanceInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['statement_schema_root_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentSourceProvenanceToken);
            if ($baseAdmitted && !$tokenMatches) {
                $provenanceInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_current_source_provenance_after_master_journal_next243',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_current_source_provenance_predates_master_journal_recovery',
                ];
            }

            $rows[] = $row + [
                'current_source_provenance_token_admitted' => $baseAdmitted && $tokenMatches,
                'current_source_provenance_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_current_source_provenance_matches_master_journal_recovery'
                        : 'reader_cache_current_source_provenance_predates_master_journal_recovery')
                    : (string) ($row['statement_schema_root_token_reason'] ?? $row['schema_reparse_token_reason'] ?? $row['reason']),
                'cache_current_source_provenance_token' => $cacheToken,
                'current_source_provenance_token' => $currentSourceProvenanceToken,
                'current_source_provenance_token_matches' => $tokenMatches,
            ];
        }

        $provenanceInvalidated = self::sortedUnique($provenanceInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $provenanceInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $provenanceInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $provenanceInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentSourceProvenanceToken);
            $pageInvalidated = in_array((int) $read['page_number'], $provenanceInvalidated, true);
            $read['current_source_provenance_token_current'] = $ticketCurrent;
            $read['current_source_provenance_token'] = $currentSourceProvenanceToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-current-source-provenance-fence-next243';
                $read['current_source_provenance_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_current_source_provenance_change'
                    : 'reader_ticket_current_source_provenance_predates_recovery';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_current_source_provenance_after_master_journal_next243',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['current_source_provenance_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next243';
        $base['reason'] = 'master_journal_reader_cache_rechecks_current_source_provenance_before_reuse';
        $base['current_source_provenance_token'] = $currentSourceProvenanceToken;
        $base['reader_rows'] = $rows;
        $base['current_source_provenance_invalidated_cache_page_numbers'] = $provenanceInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSourceProvenanceToken . '|' . implode(',', $provenanceInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next243';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-current-source-provenance-fence';
        $base['non_overlap'] = 'next243 fences reader-cache reuse on current-source provenance after next240 statement schema-root admission; it does not repeat next240 statement-root, next236 schema-reparse, next233 read-transaction, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheCurrentSourceProvenanceTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['current_source_provenance_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next243 cache entries require current-source provenance tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readCurrentSourceProvenanceTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['current_source_provenance_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next243 reads require reader ids and current-source provenance tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripCurrentSourceProvenanceToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['current_source_provenance_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneCurrentSourceProvenanceToken(array $read): array
    {
        unset($read['current_source_provenance_token']);

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
