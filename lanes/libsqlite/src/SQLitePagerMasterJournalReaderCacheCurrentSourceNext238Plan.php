<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Plan
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
    ): array {
        if ($currentSchemaRootDigest === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next238 requires a current schema root digest');
        }

        $cacheSchemaRootDigests = self::cacheSchemaRootDigests($readerCache);
        $readSchemaRootDigests = self::readSchemaRootDigests($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSchemaRootDigest($readerCache),
            array_map(static fn (array $read): array => self::stripOneSchemaRootDigest($read), $nextReads),
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
        );

        $schemaInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheDigest = $cacheSchemaRootDigests[$pageNumber] ?? '';
            $reason = hash_equals($cacheDigest, $currentSchemaRootDigest)
                ? null
                : 'reader_cache_schema_root_digest_predates_master_journal_current_source';

            if ((bool) ($row['database_change_counter_admitted'] ?? false) && $reason !== null) {
                $schemaInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'schema_root_digest_admitted' => (bool) ($row['database_change_counter_admitted'] ?? false) && $reason === null,
                'schema_root_digest_reason' => (bool) ($row['database_change_counter_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_schema_root_digest_matches_current_source')
                    : (string) ($row['database_change_counter_reason'] ?? $row['database_path_token_reason'] ?? $row['reason']),
                'cache_schema_root_digest' => $cacheDigest,
                'current_schema_root_digest' => $currentSchemaRootDigest,
                'schema_root_digest_matches' => hash_equals($cacheDigest, $currentSchemaRootDigest),
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
            $ticketCurrent = hash_equals($readSchemaRootDigests[$readerId] ?? '', $currentSchemaRootDigest);
            $pageInvalidated = in_array($read['page_number'], $schemaInvalidated, true);
            $read['schema_root_digest_current'] = $ticketCurrent;
            $read['schema_root_digest'] = $currentSchemaRootDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-schema-root-digest-fence-current-source-next238';
                $read['schema_root_digest_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_schema_root_digest_change'
                    : 'reader_ticket_schema_root_digest_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_schema_root_digest_after_current_source_next238',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['schema_root_digest_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next238';
        $base['reason'] = 'master_journal_reader_cache_rechecks_schema_root_digest_before_current_source_reuse';
        $base['current_schema_root_digest'] = $currentSchemaRootDigest;
        $base['reader_rows'] = $rows;
        $base['schema_root_digest_invalidated_cache_page_numbers'] = $schemaInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSchemaRootDigest . '|' . implode(',', $schemaInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next238';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-schema-root-digest-fence';
        $base['non_overlap'] = 'next238 fences reader-cache reuse on the recovered sqlite_schema root digest after next235 database change-counter, next232 database path, next229 pager-cache-source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat schema-cookie, page-count, database header digest, change-counter, database path, master-journal bytes, member-journal, WAL, VFS writer, sync-plan, rollback-journal apply, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheSchemaRootDigests(array $cache): array
    {
        $digests = [];
        foreach ($cache as $pageNumber => $entry) {
            $digest = $entry['schema_root_digest'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($digest) || strlen($digest) !== 64) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next238 cache entries require 64-byte schema root digests');
            }
            $digests[$pageNumber] = $digest;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readSchemaRootDigests(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $digest = $read['schema_root_digest'] ?? '';
            if ($readerId === '' || !is_string($digest) || strlen($digest) !== 64) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next238 reads require reader ids and 64-byte schema root digests');
            }
            $digests[$readerId] = $digest;
        }

        return $digests;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSchemaRootDigest(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['schema_root_digest']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneSchemaRootDigest(array $read): array
    {
        unset($read['schema_root_digest']);

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
