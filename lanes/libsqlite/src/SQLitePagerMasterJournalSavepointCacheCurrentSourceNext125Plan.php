<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalSavepointCacheCurrentSourceNext125Plan
{
    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool}> $cachePages
     * @param list<int> $releaseReadPages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        array $databases,
        int $pageSize,
        string $primaryDatabasePath,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $retryPageWrites,
        array $cachePages,
        array $releaseReadPages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
    ): array {
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 requires a current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 source epoch must be positive');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 requires cache pages');
        }
        if ($releaseReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 requires release read pages');
        }

        self::assertCachePages($cachePages, $pageSize);
        self::assertPageList($releaseReadPages);

        $recovery = SQLitePagerMasterJournalCacheRecoveryCurrentSourceNext122Plan::currentSourceNext(
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $currentMasterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            clone $savepoints,
            $retryPageWrites
        );

        $currentMembers = $recovery['current_members'] ?? [];
        $nextSourceId = self::sourceId($masterJournalPath, is_array($currentMembers) ? $currentMembers : []);
        $nextEpoch = $currentSourceEpoch + 1;
        $currentSourceVerified = ($recovery['current_source_verified'] ?? false) === true;
        $recoveredBytes = (string) ($recovery['recovery']['retry_recovery']['recovered_database_bytes'] ?? '');
        $rollbackBytes = (string) ($recovery['payloads'][$primaryDatabasePath . '#master-savepoint-rollback-preview-next108'] ?? $recoveredBytes);
        $finalBytes = (string) ($recovery['payloads'][$primaryDatabasePath . '#master-savepoint-current-source-next108'] ?? $recoveredBytes);
        $capturedPages = self::intList($recovery['recovery']['captured_page_numbers'] ?? []);
        $rollbackPages = self::intList($recovery['rollback_preview']['restored_page_numbers'] ?? []);

        $validCache = [];
        $invalidated = [];
        $operations = $recovery['operations'] ?? [];
        if (!is_array($operations)) {
            $operations = [];
        }

        foreach ($cachePages as $pageNumber => $entry) {
            $image = $entry['image'];
            $dirty = ($entry['dirty'] ?? false) === true;
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = (int) ($entry['epoch'] ?? 0);
            $source = (string) ($entry['source'] ?? 'unknown');
            $reason = null;
            if (!$currentSourceVerified) {
                $reason = 'master_journal_current_source_unverified';
            } elseif ($dirty) {
                $reason = 'dirty_cache_from_aborted_savepoint_retry';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_current_source_id';
            } elseif ($epoch !== $currentSourceEpoch) {
                $reason = 'stale_current_source_epoch';
            } elseif (!self::matchesAnyPageImage($image, $pageNumber, $pageSize, [$recoveredBytes, $rollbackBytes, $finalBytes])) {
                $reason = 'cached_image_not_from_recovered_current_source';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_master_journal_savepoint_cache_page',
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'reason' => $reason,
                ];
                continue;
            }

            $validCache[$pageNumber] = [
                'image' => $image,
                'source' => $source,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
        }

        foreach (array_unique(array_merge($capturedPages, $rollbackPages)) as $pageNumber) {
            $image = self::pageImage($rollbackBytes !== '' ? $rollbackBytes : $recoveredBytes, $pageNumber, $pageSize);
            $validCache[$pageNumber] = [
                'image' => $image,
                'source' => in_array($pageNumber, $capturedPages, true)
                    ? 'master-journal-savepoint-before-image'
                    : 'master-journal-savepoint-rollback-image',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'install_master_journal_savepoint_cache_page',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'cache_page_from_recovered_master_journal_savepoint_source',
            ];
        }
        ksort($validCache, SORT_NUMERIC);

        $releaseReads = [];
        foreach ($releaseReadPages as $pageNumber) {
            $entry = $validCache[$pageNumber] ?? null;
            $hit = is_array($entry)
                && ($entry['source_id'] ?? null) === $nextSourceId
                && ($entry['epoch'] ?? null) === $nextEpoch
                && ($entry['dirty'] ?? true) === false;
            $releaseReads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $hit,
                'source' => $hit ? (string) $entry['source'] : 'pager-read-miss',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'prefix' => $hit ? self::prefix((string) $entry['image']) : '',
                'zero_filled_short_read' => !$hit && self::pageImage($rollbackBytes, $pageNumber, $pageSize) === str_repeat("\0", $pageSize),
            ];
            $operations[] = [
                'op' => $hit ? 'release_read_master_journal_cache_hit' : 'release_read_master_journal_cache_miss',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'read_after_savepoint_release_uses_current_source_cache',
            ];
        }

        $status = $currentSourceVerified
            ? 'pager_master_journal_savepoint_cache_current_source_next125'
            : 'pager_master_journal_savepoint_cache_current_source_blocked_next125';

        return [
            'status' => $status,
            'reason' => $currentSourceVerified
                ? 'savepoint_cache_pages_rebased_to_master_journal_current_source'
                : 'master_journal_current_source_not_verified_for_savepoint_cache',
            'master_journal_path' => $masterJournalPath,
            'primary_database_path' => $primaryDatabasePath,
            'savepoint' => $savepointName,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ],
            'current_source_verified' => $currentSourceVerified,
            'cache_stale_rejected' => ($recovery['cache_stale_rejected'] ?? false) === true,
            'cache' => [
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'installed_page_numbers' => array_values(array_unique(array_merge($capturedPages, $rollbackPages))),
                'final_page_numbers' => array_keys($validCache),
                'final_sources' => self::sources($validCache),
                'final_source_ids' => self::sourceIds($validCache),
                'dirty_page_numbers' => self::dirtyPageNumbers($validCache),
            ],
            'release_reads' => $releaseReads,
            'recovery' => $recovery,
            'operations' => $operations,
            'payloads' => $recovery['payloads'] ?? [],
            'dependencies' => array_values(array_unique(array_merge(
                is_array($recovery['dependencies'] ?? null) ? $recovery['dependencies'] : [],
                [
                    'sqlite-pager-master-journal-savepoint-cache-current-source-next125',
                    'sqlite-pager-master-journal-cache-recovery-current-source-next122',
                    'sqlite-pager-cache-current-source-token',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $cachePages
     */
    private static function assertCachePages(array $cachePages, int $pageSize): void
    {
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next125 page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 release read pages must be one-based integers');
            }
        }
    }

    /**
     * @param list<mixed> $values
     * @return list<int>
     */
    private static function intList(array $values): array
    {
        $ints = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $ints[] = $value;
            }
        }

        return $ints;
    }

    /**
     * @param list<string> $images
     */
    private static function matchesAnyPageImage(string $image, int $pageNumber, int $pageSize, array $images): bool
    {
        foreach ($images as $databaseBytes) {
            if ($databaseBytes !== '' && $image === self::pageImage($databaseBytes, $pageNumber, $pageSize)) {
                return true;
            }
        }

        return false;
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        $offset = ($pageNumber - 1) * $pageSize;

        return str_pad(substr($databaseBytes, $offset, $pageSize), $pageSize, "\0");
    }

    /**
     * @param list<string> $members
     */
    private static function sourceId(string $masterJournalPath, array $members): string
    {
        return 'master-journal:' . substr(hash('sha256', $masterJournalPath . "\n" . implode("\n", $members)), 0, 16);
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function sources(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) ($entry['source'] ?? 'unknown');
        }

        return $sources;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function sourceIds(array $pages): array
    {
        $sourceIds = [];
        foreach ($pages as $pageNumber => $entry) {
            $sourceIds[$pageNumber] = (string) ($entry['source_id'] ?? '');
        }

        return $sourceIds;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return list<int>
     */
    private static function dirtyPageNumbers(array $pages): array
    {
        $dirty = [];
        foreach ($pages as $pageNumber => $entry) {
            if (($entry['dirty'] ?? false) === true) {
                $dirty[] = $pageNumber;
            }
        }

        return $dirty;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
