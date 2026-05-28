<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext237Plan
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
        int $currentDatabaseChangeCounter,
        int $currentVersionValidFor,
        int $currentUserVersion,
        int $currentApplicationId,
        int $currentSchemaFormatNumber,
    ): array {
        if ($currentSchemaFormatNumber < 1 || $currentSchemaFormatNumber > 4) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next237 requires a schema format number from 1 through 4');
        }

        $cacheFormats = self::cacheSchemaFormats($readerCache);
        $readFormats = self::readSchemaFormats($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext234Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSchemaFormat($readerCache),
            array_map(static fn (array $read): array => self::stripOneSchemaFormat($read), $nextReads),
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
            $currentDatabaseChangeCounter,
            $currentVersionValidFor,
            $currentUserVersion,
            $currentApplicationId,
        );

        $formatInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheFormat = $cacheFormats[$pageNumber] ?? 0;
            $formatMatches = $cacheFormat === $currentSchemaFormatNumber;
            if ((bool) ($row['application_metadata_admitted'] ?? false) && !$formatMatches) {
                $formatInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'schema_format_number_admitted' => (bool) ($row['application_metadata_admitted'] ?? false) && $formatMatches,
                'schema_format_number_reason' => (bool) ($row['application_metadata_admitted'] ?? false)
                    ? ($formatMatches
                        ? 'reader_cache_schema_format_number_matches_current_source'
                        : 'reader_cache_schema_format_number_changed_after_master_journal_recovery')
                    : (string) ($row['application_metadata_reason'] ?? $row['header_counter_pair_reason'] ?? $row['reason']),
                'cache_schema_format_number' => $cacheFormat,
                'current_schema_format_number' => $currentSchemaFormatNumber,
                'schema_format_number_matches' => $formatMatches,
            ];
        }

        $formatInvalidated = self::sortedUnique($formatInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $formatInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $formatInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $formatInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readFormats[$readerId] ?? 0) === $currentSchemaFormatNumber;
            $pageInvalidated = in_array((int) $read['page_number'], $formatInvalidated, true);
            $read['schema_format_number_current'] = $ticketCurrent;
            $read['schema_format_number'] = $currentSchemaFormatNumber;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-schema-format-fence-current-source-next237';
                $read['schema_format_number_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_schema_format_number_change'
                    : 'reader_ticket_schema_format_number_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_schema_format_number_after_current_source_next237',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['schema_format_number_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next237';
        $base['reason'] = 'master_journal_reader_cache_rechecks_schema_format_number_before_current_source_reuse';
        $base['current_schema_format_number'] = $currentSchemaFormatNumber;
        $base['reader_rows'] = $rows;
        $base['schema_format_number_invalidated_cache_page_numbers'] = $formatInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSchemaFormatNumber . '|' . implode(',', $formatInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next237';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-schema-format-fence';
        $base['non_overlap'] = 'next237 fences reader-cache reuse on the SQLite page-1 schema-format number after next234 application metadata admission; it does not repeat next230 SQLite version-number, next231 freelist header, next234 user_version/application_id, schema-cookie, master-journal bytes, rollback-journal apply, WAL, VFS writer, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,int> */
    private static function cacheSchemaFormats(array $cache): array
    {
        $formats = [];
        foreach ($cache as $pageNumber => $entry) {
            $format = $entry['schema_format_number'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($format) || $format < 1 || $format > 4) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next237 cache entries require schema format numbers from 1 through 4');
            }
            $formats[$pageNumber] = $format;
        }

        return $formats;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,int> */
    private static function readSchemaFormats(array $reads): array
    {
        $formats = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $format = $read['schema_format_number'] ?? null;
            if ($readerId === '' || !is_int($format) || $format < 1 || $format > 4) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next237 reads require reader ids and schema format numbers from 1 through 4');
            }
            $formats[$readerId] = $format;
        }

        return $formats;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSchemaFormat(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['schema_format_number']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneSchemaFormat(array $read): array
    {
        unset($read['schema_format_number']);

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
