<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext234Plan
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
    ): array {
        if ($currentUserVersion < 0 || $currentApplicationId < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next234 requires non-negative user_version and application_id values');
        }

        $cacheMetadata = self::cacheApplicationMetadata($readerCache);
        $readMetadata = self::readApplicationMetadata($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext226Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripApplicationMetadata($readerCache),
            array_map(static fn (array $read): array => self::stripOneApplicationMetadata($read), $nextReads),
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
        );

        $metadataInvalidated = [];
        $userVersionInvalidated = [];
        $applicationIdInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $metadata = $cacheMetadata[$pageNumber] ?? ['user' => -1, 'app' => -1];
            $userCurrent = $metadata['user'] === $currentUserVersion;
            $appCurrent = $metadata['app'] === $currentApplicationId;
            $reason = match (true) {
                !$userCurrent && !$appCurrent => 'reader_cache_application_metadata_changed_after_master_journal_recovery',
                !$userCurrent => 'reader_cache_user_version_changed_after_master_journal_recovery',
                !$appCurrent => 'reader_cache_application_id_changed_after_master_journal_recovery',
                default => null,
            };

            if ((bool) ($row['header_counter_pair_admitted'] ?? false) && $reason !== null) {
                $metadataInvalidated[] = $pageNumber;
                if (!$userCurrent) {
                    $userVersionInvalidated[] = $pageNumber;
                }
                if (!$appCurrent) {
                    $applicationIdInvalidated[] = $pageNumber;
                }
            }

            $rows[] = $row + [
                'application_metadata_admitted' => (bool) ($row['header_counter_pair_admitted'] ?? false) && $reason === null,
                'application_metadata_reason' => (bool) ($row['header_counter_pair_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_application_metadata_matches_current_source')
                    : ($row['header_counter_pair_reason'] ?? $row['database_page_count_reason'] ?? $row['reason']),
                'cache_user_version' => $metadata['user'],
                'cache_application_id' => $metadata['app'],
                'current_user_version' => $currentUserVersion,
                'current_application_id' => $currentApplicationId,
                'user_version_matches' => $userCurrent,
                'application_id_matches' => $appCurrent,
            ];
        }

        $metadataInvalidated = self::sortedUnique($metadataInvalidated);
        $userVersionInvalidated = self::sortedUnique($userVersionInvalidated);
        $applicationIdInvalidated = self::sortedUnique($applicationIdInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $metadataInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $metadataInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $metadataInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $metadata = $readMetadata[$readerId] ?? ['user' => -1, 'app' => -1];
            $ticketCurrent = $metadata['user'] === $currentUserVersion && $metadata['app'] === $currentApplicationId;
            $pageInvalidated = in_array($read['page_number'], $metadataInvalidated, true);
            $read['application_metadata_current'] = $ticketCurrent;
            $read['user_version'] = $currentUserVersion;
            $read['application_id'] = $currentApplicationId;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-application-metadata-fence-current-source-next234';
                $read['application_metadata_reason'] = match (true) {
                    $pageInvalidated => 'reader_cache_reopened_after_application_metadata_change',
                    $metadata['user'] !== $currentUserVersion && $metadata['app'] !== $currentApplicationId => 'reader_ticket_application_metadata_predates_current_source',
                    $metadata['user'] !== $currentUserVersion => 'reader_ticket_user_version_predates_current_source',
                    default => 'reader_ticket_application_id_predates_current_source',
                };
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_application_metadata_after_current_source_next234',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['application_metadata_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next234';
        $base['reason'] = 'master_journal_reader_cache_rechecks_user_version_application_id_before_current_source_reuse';
        $base['current_user_version'] = $currentUserVersion;
        $base['current_application_id'] = $currentApplicationId;
        $base['reader_rows'] = $rows;
        $base['application_metadata_invalidated_cache_page_numbers'] = $metadataInvalidated;
        $base['user_version_invalidated_cache_page_numbers'] = $userVersionInvalidated;
        $base['application_id_invalidated_cache_page_numbers'] = $applicationIdInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentUserVersion . '|' . $currentApplicationId . '|' . implode(',', $metadataInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next234';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-application-metadata-fence';
        $base['non_overlap'] = 'next234 fences reader-cache reuse on SQLite page-1 user_version/application_id metadata after next226 header-counter admission; it does not repeat next230 SQLite version-number, next231 freelist header, master-journal bytes, member token/header/order, cleanup token, database file-token, header digest, page-count, rollback-journal apply, WAL, VFS writer, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array{user:int,app:int}> */
    private static function cacheApplicationMetadata(array $cache): array
    {
        $metadata = [];
        foreach ($cache as $pageNumber => $entry) {
            $user = $entry['user_version'] ?? null;
            $app = $entry['application_id'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($user) || !is_int($app) || $user < 0 || $app < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next234 cache entries require non-negative user_version and application_id values');
            }
            $metadata[$pageNumber] = ['user' => $user, 'app' => $app];
        }

        return $metadata;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,array{user:int,app:int}> */
    private static function readApplicationMetadata(array $reads): array
    {
        $metadata = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $user = $read['user_version'] ?? null;
            $app = $read['application_id'] ?? null;
            if ($readerId === '' || !is_int($user) || !is_int($app) || $user < 0 || $app < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next234 reads require reader ids and non-negative user_version/application_id values');
            }
            $metadata[$readerId] = ['user' => $user, 'app' => $app];
        }

        return $metadata;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripApplicationMetadata(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['user_version'], $entry['application_id']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneApplicationMetadata(array $read): array
    {
        unset($read['user_version'], $read['application_id']);

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
