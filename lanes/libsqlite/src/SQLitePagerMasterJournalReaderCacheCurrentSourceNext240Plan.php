<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext240Plan
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
    ): array {
        if ($currentStatementSchemaRootToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next240 requires a current statement schema-root token');
        }

        $cacheTokens = self::cacheStatementSchemaRootTokens($readerCache);
        $readTokens = self::readStatementSchemaRootTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext236Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripStatementSchemaRootToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneStatementSchemaRootToken($read), $nextReads),
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
        );

        $statementInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['schema_reparse_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentStatementSchemaRootToken);
            if ($baseAdmitted && !$tokenMatches) {
                $statementInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_statement_schema_root_after_current_source_next240',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_statement_schema_root_token_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'statement_schema_root_token_admitted' => $baseAdmitted && $tokenMatches,
                'statement_schema_root_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_statement_schema_root_token_matches_current_source'
                        : 'reader_cache_statement_schema_root_token_predates_master_journal_current_source')
                    : (string) ($row['schema_reparse_token_reason'] ?? $row['read_transaction_token_reason'] ?? $row['reason']),
                'cache_statement_schema_root_token' => $cacheToken,
                'current_statement_schema_root_token' => $currentStatementSchemaRootToken,
                'statement_schema_root_token_matches' => $tokenMatches,
            ];
        }

        $statementInvalidated = self::sortedUnique($statementInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $statementInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $statementInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $statementInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentStatementSchemaRootToken);
            $pageInvalidated = in_array((int) $read['page_number'], $statementInvalidated, true);
            $read['statement_schema_root_token_current'] = $ticketCurrent;
            $read['statement_schema_root_token'] = $currentStatementSchemaRootToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-statement-schema-root-fence-current-source-next240';
                $read['statement_schema_root_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_statement_schema_root_token_change'
                    : 'reader_ticket_statement_schema_root_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_statement_schema_root_after_current_source_next240',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['statement_schema_root_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next240';
        $base['reason'] = 'master_journal_reader_cache_rechecks_statement_schema_root_before_current_source_reuse';
        $base['current_statement_schema_root_token'] = $currentStatementSchemaRootToken;
        $base['reader_rows'] = $rows;
        $base['statement_schema_root_invalidated_cache_page_numbers'] = $statementInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentStatementSchemaRootToken . '|' . implode(',', $statementInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next240';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-statement-schema-root-fence';
        $base['non_overlap'] = 'next240 fences reader-cache reuse on prepared-statement schema-root tokens after next236 schema-reparse, next233 read-transaction, next229 pager-cache source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat application metadata, change-counter, page-count, payload digest, file-token, member-journal, cleanup-token, rollback-journal apply, WAL checkpoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheStatementSchemaRootTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['statement_schema_root_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next240 cache entries require statement schema-root tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readStatementSchemaRootTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['statement_schema_root_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next240 reads require reader ids and statement schema-root tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripStatementSchemaRootToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['statement_schema_root_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneStatementSchemaRootToken(array $read): array
    {
        unset($read['statement_schema_root_token']);

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
