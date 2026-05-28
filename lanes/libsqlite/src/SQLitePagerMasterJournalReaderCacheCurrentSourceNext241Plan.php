<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext241Plan
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
        string $currentDatabasePathToken,
        int $currentDatabaseChangeCounter,
        string $currentSchemaRootDigest,
        int $currentSchemaCookie,
    ): array {
        if ($currentSchemaCookie < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next241 requires a positive schema cookie');
        }

        $cacheCookies = self::cacheSchemaCookies($readerCache);
        $readCookies = self::readSchemaCookies($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSchemaCookie($readerCache),
            array_map(static fn (array $read): array => self::stripOneSchemaCookie($read), $nextReads),
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
            $currentDatabasePathToken,
            $currentDatabaseChangeCounter,
            $currentSchemaRootDigest,
        );

        $cookieInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheCookie = $cacheCookies[$pageNumber] ?? 0;
            $cookieMatches = $cacheCookie === $currentSchemaCookie;
            $baseAdmitted = (bool) ($row['schema_root_digest_admitted'] ?? false);
            if ($baseAdmitted && !$cookieMatches) {
                $cookieInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'schema_cookie_admitted' => $baseAdmitted && $cookieMatches,
                'schema_cookie_reason' => $baseAdmitted
                    ? ($cookieMatches
                        ? 'reader_cache_schema_cookie_matches_master_journal_current_source'
                        : 'reader_cache_schema_cookie_predates_master_journal_current_source')
                    : (string) ($row['schema_root_digest_reason'] ?? $row['database_change_counter_reason'] ?? $row['reason']),
                'cache_schema_cookie' => $cacheCookie,
                'current_schema_cookie' => $currentSchemaCookie,
                'schema_cookie_matches' => $cookieMatches,
            ];
        }

        $cookieInvalidated = self::sortedUnique($cookieInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $cookieInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $cookieInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $cookieInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readCookies[$readerId] ?? 0) === $currentSchemaCookie;
            $pageInvalidated = in_array((int) $read['page_number'], $cookieInvalidated, true);
            $read['schema_cookie_current'] = $ticketCurrent;
            $read['schema_cookie'] = $currentSchemaCookie;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-schema-cookie-fence-current-source-next241';
                $read['schema_cookie_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_schema_cookie_change'
                    : 'reader_ticket_schema_cookie_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_schema_cookie_after_current_source_next241',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['schema_cookie_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next241';
        $base['reason'] = 'master_journal_reader_cache_rechecks_schema_cookie_before_current_source_reuse';
        $base['current_schema_cookie'] = $currentSchemaCookie;
        $base['reader_rows'] = $rows;
        $base['schema_cookie_invalidated_cache_page_numbers'] = $cookieInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSchemaCookie . '|' . implode(',', $cookieInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next241';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-schema-cookie-fence';
        $base['non_overlap'] = 'next241 fences reader-cache reuse on the page-1 schema cookie after next238 schema-root digest and next235 change-counter admission have already passed; it does not repeat schema-root digest, database path, cleanup token, master-journal bytes, member-journal headers, WAL savepoints, rollback-journal apply, VFS writer/sync/lock, B-tree, JSON, or SELECT behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,int> */
    private static function cacheSchemaCookies(array $cache): array
    {
        $cookies = [];
        foreach ($cache as $pageNumber => $entry) {
            $cookie = $entry['schema_cookie'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($cookie) || $cookie < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next241 cache entries require positive schema cookies');
            }
            $cookies[$pageNumber] = $cookie;
        }

        return $cookies;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,int> */
    private static function readSchemaCookies(array $reads): array
    {
        $cookies = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $cookie = $read['schema_cookie'] ?? null;
            if ($readerId === '' || !is_int($cookie) || $cookie < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next241 reads require reader ids and positive schema cookies');
            }
            $cookies[$readerId] = $cookie;
        }

        return $cookies;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSchemaCookie(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['schema_cookie']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneSchemaCookie(array $read): array
    {
        unset($read['schema_cookie']);

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
