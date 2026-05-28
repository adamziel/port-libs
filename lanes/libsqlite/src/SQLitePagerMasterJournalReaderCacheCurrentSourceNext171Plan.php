<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_generation?:int,master_deleted?:bool,master_digest?:string,recovery_sequence?:int,read_lock_generation?:int,dirty?:bool,pinned?:bool,shared?:bool}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,master_generation?:int,recovery_sequence?:int,read_lock_generation?:int}> $reads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $reads,
        string $currentSourceId,
        int $currentEpoch,
        int $currentMasterGeneration,
        bool $masterJournalDeleted,
        int $currentRecoverySequence,
        int $currentReadLockGeneration,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1 || $currentMasterGeneration < 1 || $currentRecoverySequence < 1 || $currentReadLockGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 epoch, generation, recovery sequence, and read-lock generation must be positive');
        }
        if ($currentPages === [] || $readerCache === [] || $reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 requires current pages, reader cache, and reads');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next171 current master journal does not reference the database journal');
        }

        $currentPages = self::normalizePages($currentPages, $pageSize, 'current');
        $masterDigest = hash('sha256', implode("\n", $members));
        $readerCache = self::normalizeCache($readerCache, $pageSize, $masterDigest);
        $reads = self::normalizeReads($reads);

        $sourceTicket = [
            'id' => $currentSourceId,
            'epoch' => $currentEpoch,
            'master_generation' => $currentMasterGeneration,
            'master_journal_deleted' => $masterJournalDeleted,
            'master_journal_digest' => $masterDigest,
            'recovery_sequence' => $currentRecoverySequence,
            'read_lock_generation' => $currentReadLockGeneration,
        ];

        $operations = [[
            'op' => 'read_current_master_journal_for_reader_cache_recovery_ticket',
            'path' => $masterJournalPath,
            'members' => $members,
            'generation' => $currentMasterGeneration,
            'recovery_sequence' => $currentRecoverySequence,
            'read_lock_generation' => $currentReadLockGeneration,
            'deleted_after_recovery' => $masterJournalDeleted,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next171 cache page {$pageNumber} is outside current source");
            }

            $currentImage = $currentPages[$pageNumber];
            $imageMatches = hash_equals(self::digest($currentImage), self::digest($entry['image']));
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_after_master_journal_recovery';
            } elseif ($entry['master_digest'] !== $masterDigest) {
                $reason = 'reader_cache_master_digest_mismatch';
            } elseif ($entry['recovery_sequence'] !== $currentRecoverySequence) {
                $reason = 'reader_cache_recovery_sequence_mismatch';
            } elseif ($entry['read_lock_generation'] !== $currentReadLockGeneration) {
                $reason = 'reader_cache_read_lock_generation_mismatch';
            } elseif ($entry['master_deleted'] !== $masterJournalDeleted) {
                $reason = 'reader_cache_master_deleted_state_mismatch';
            } elseif ($entry['master_generation'] !== $currentMasterGeneration) {
                $reason = 'reader_cache_master_generation_mismatch';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch';
            } elseif ($entry['pinned'] && !$imageMatches) {
                $reason = 'pinned_reader_cache_image_mismatch_after_master_recovery';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                    'master_generation' => $entry['master_generation'],
                    'master_deleted' => $entry['master_deleted'],
                    'recovery_sequence' => $entry['recovery_sequence'],
                    'read_lock_generation' => $entry['read_lock_generation'],
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                ];
                $operations[] = [
                    'op' => 'invalidate_reader_cache_recovery_ticket',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
            } elseif (!$imageMatches) {
                $refreshed[$pageNumber] = [
                    'image' => $currentImage,
                    'reader_id' => $entry['reader_id'],
                    'shared' => $entry['shared'],
                    'source' => 'reader-cache-refreshed-current-master-recovery-ticket',
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_from_current_master_recovery_ticket',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $retained[$pageNumber] = [
                    'image' => $entry['image'],
                    'reader_id' => $entry['reader_id'],
                    'shared' => $entry['shared'],
                    'source' => 'reader-cache-retained-current-master-recovery-ticket',
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_current_master_recovery_ticket',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($imageMatches ? 'reader_cache_matches_current_master_recovery_ticket' : 'reader_cache_refreshed_from_current_master_recovery_ticket'),
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'master_generation' => $entry['master_generation'],
                'master_deleted' => $entry['master_deleted'],
                'master_digest_matches' => $entry['master_digest'] === $masterDigest,
                'recovery_sequence' => $entry['recovery_sequence'],
                'read_lock_generation' => $entry['read_lock_generation'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'shared' => $entry['shared'],
                'image_matches_current_source' => $imageMatches,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $nextReads = [];
        $reopenReaders = [];
        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next171 read page {$pageNumber} is outside current source");
            }
            $ticketCurrent = $read['source_id'] === $currentSourceId
                && $read['epoch'] === $currentEpoch
                && $read['master_generation'] === $currentMasterGeneration
                && $read['recovery_sequence'] === $currentRecoverySequence
                && $read['read_lock_generation'] === $currentReadLockGeneration;
            $cache = $ticketCurrent ? ($retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null) : null;
            if (!$ticketCurrent) {
                $reopenReaders[$read['reader_id']] = $read['reader_id'];
            }
            $image = is_array($cache) ? $cache['image'] : $currentPages[$pageNumber];
            $nextReads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => is_array($cache),
                'source' => is_array($cache) ? $cache['source'] : 'current-master-recovery-source',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'master_generation' => $currentMasterGeneration,
                'recovery_sequence' => $currentRecoverySequence,
                'read_lock_generation' => $currentReadLockGeneration,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => is_array($cache) ? 'next_reader_cache_hit_current_master_recovery_ticket' : 'next_reader_reopen_current_master_recovery_page',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next171',
            'reason' => 'master_journal_recovery_sequence_and_read_lock_are_part_of_reader_cache_ticket',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_source' => $sourceTicket,
            'cache_rows' => $rows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
            'invalidated_entries' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [] || $reopenReaders !== [],
            'next_reads' => $nextReads,
            'reopen_reader_ids' => array_values($reopenReaders),
            'read_cache_hits' => array_column($nextReads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($nextReads, 'prefix', 'reader_id'),
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . $currentMasterGeneration . '|' . $currentRecoverySequence . '|' . $currentReadLockGeneration . '|' . ($masterJournalDeleted ? 'deleted' : 'present') . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($refreshed)) . '|' . implode(',', array_column($invalidated, 'page_number'))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next171',
                'sqlite-pager-master-journal-reader-cache-current-source-next167',
                'sqlite-master-journal-recovery-sequence-reader-ticket',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $members[$line] = $line;
            }
        }

        return array_values($members);
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next171 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next171 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_generation?:int,master_deleted?:bool,master_digest?:string,recovery_sequence?:int,read_lock_generation?:int,dirty?:bool,pinned?:bool,shared?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,master_generation:int,master_deleted:bool,master_digest:string,recovery_sequence:int,read_lock_generation:int,dirty:bool,pinned:bool,shared:bool}>
     */
    private static function normalizeCache(array $cache, int $pageSize, string $defaultMasterDigest): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next171 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '';
            $epoch = $entry['epoch'] ?? 0;
            $generation = $entry['master_generation'] ?? 0;
            $recoverySequence = $entry['recovery_sequence'] ?? 0;
            $readLockGeneration = $entry['read_lock_generation'] ?? 0;
            if ($sourceId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 cache entries require source id');
            }
            if (!is_int($epoch) || $epoch < 1 || !is_int($generation) || $generation < 1 || !is_int($recoverySequence) || $recoverySequence < 1 || !is_int($readLockGeneration) || $readLockGeneration < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 cache entries require positive epoch, generation, recovery sequence, and read-lock generation');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : 'reader-' . $pageNumber,
                'master_generation' => $generation,
                'master_deleted' => (bool) ($entry['master_deleted'] ?? false),
                'master_digest' => isset($entry['master_digest']) && is_string($entry['master_digest']) && $entry['master_digest'] !== '' ? $entry['master_digest'] : $defaultMasterDigest,
                'recovery_sequence' => $recoverySequence,
                'read_lock_generation' => $readLockGeneration,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'shared' => (bool) ($entry['shared'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,master_generation?:int,recovery_sequence?:int,read_lock_generation?:int}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,master_generation:int,recovery_sequence:int,read_lock_generation:int}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $index => $read) {
            $readerId = isset($read['reader_id']) && is_string($read['reader_id']) ? $read['reader_id'] : '';
            if ($readerId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 reads require reader id');
            }
            $pageNumber = $read['page_number'] ?? 0;
            $epoch = $read['epoch'] ?? 0;
            $generation = $read['master_generation'] ?? 0;
            $recoverySequence = $read['recovery_sequence'] ?? 0;
            $readLockGeneration = $read['read_lock_generation'] ?? 0;
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next171 read {$index} page number must be one-based");
            }
            if (!isset($read['source_id']) || !is_string($read['source_id']) || $read['source_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 reads require source id');
            }
            if (!is_int($epoch) || $epoch < 1 || !is_int($generation) || $generation < 1 || !is_int($recoverySequence) || $recoverySequence < 1 || !is_int($readLockGeneration) || $readLockGeneration < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next171 reads require positive epoch, generation, recovery sequence, and read-lock generation');
            }
            $normalized[] = [
                'reader_id' => $readerId,
                'page_number' => $pageNumber,
                'source_id' => $read['source_id'],
                'epoch' => $epoch,
                'master_generation' => $generation,
                'recovery_sequence' => $recoverySequence,
                'read_lock_generation' => $readLockGeneration,
            ];
        }

        return $normalized;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 64), ".\0");
    }

    private static function digest(string $bytes): string
    {
        return hash('sha256', $bytes);
    }
}
