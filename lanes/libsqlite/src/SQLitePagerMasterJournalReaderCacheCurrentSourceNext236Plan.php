<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext236Plan
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
    ): array {
        if ($currentSchemaReparseToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next236 requires a current schema reparse token');
        }

        $cacheTokens = self::cacheSchemaReparseTokens($readerCache);
        $readTokens = self::readSchemaReparseTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext233Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSchemaReparseToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneSchemaReparseToken($read), $nextReads),
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
        );

        $schemaInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['read_transaction_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentSchemaReparseToken);
            if ($baseAdmitted && !$tokenMatches) {
                $schemaInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_schema_reparse_after_current_source_next236',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_schema_reparse_token_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'schema_reparse_token_admitted' => $baseAdmitted && $tokenMatches,
                'schema_reparse_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_schema_reparse_token_matches_current_source'
                        : 'reader_cache_schema_reparse_token_predates_master_journal_current_source')
                    : (string) ($row['read_transaction_token_reason'] ?? $row['pager_cache_source_token_reason'] ?? $row['reason']),
                'cache_schema_reparse_token' => $cacheToken,
                'current_schema_reparse_token' => $currentSchemaReparseToken,
                'schema_reparse_token_matches' => $tokenMatches,
            ];
        }

        $schemaInvalidated = self::sortedUnique($schemaInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $schemaInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $schemaInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $schemaInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentSchemaReparseToken);
            $pageInvalidated = in_array((int) $read['page_number'], $schemaInvalidated, true);
            $read['schema_reparse_token_current'] = $ticketCurrent;
            $read['schema_reparse_token'] = $currentSchemaReparseToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-schema-reparse-fence-current-source-next236';
                $read['schema_reparse_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_schema_reparse_token_change'
                    : 'reader_ticket_schema_reparse_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_schema_reparse_after_current_source_next236',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['schema_reparse_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next236';
        $base['reason'] = 'master_journal_reader_cache_rechecks_schema_reparse_before_current_source_reuse';
        $base['current_schema_reparse_token'] = $currentSchemaReparseToken;
        $base['reader_rows'] = $rows;
        $base['schema_reparse_invalidated_cache_page_numbers'] = $schemaInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSchemaReparseToken . '|' . implode(',', $schemaInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next236';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-schema-reparse-fence';
        $base['non_overlap'] = 'next236 fences reader-cache reuse on a schema-reparse token after next233 read-transaction, next229 pager-cache source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat page-count, payload-digest, file-token, member-journal, rollback-journal, WAL, VFS writer, B-tree, JSON, or SELECT behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheSchemaReparseTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['schema_reparse_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next236 cache entries require schema reparse tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readSchemaReparseTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['schema_reparse_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next236 reads require reader ids and schema reparse tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSchemaReparseToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['schema_reparse_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneSchemaReparseToken(array $read): array
    {
        unset($read['schema_reparse_token']);

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
